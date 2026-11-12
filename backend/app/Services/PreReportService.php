<?php

namespace App\Services;

use App\Models\Funder;
use App\Models\LogbookEntry;
use App\Models\Project;
use App\Support\LogbookRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
use Throwable;

/**
 * Pré-bilan Word d'un projet pour un financeur (#143). On n'y met que des titres et des
 * données saisies : l'application ne rédige aucune phrase (contexte client). Mariette le
 * retravaille avant envoi, d'où un vrai Word avec des styles de titres. On tire les chiffres
 * de StatisticsService : ils sont donc identiques à ceux de l'écran Statistiques. On a repris
 * la structure de la branche de Lucas (#121).
 */
class PreReportService
{
    /**
     * Les réponses qui ne disent rien : on ne les reprend pas dans le pré-bilan. On les compare
     * sous leur forme simple (LogbookRules::plain : sans accent, minuscules, sans ponctuation
     * aux bouts).
     */
    private const EMPTY_ANSWERS = ['rien', 'aucun', 'aucune', 'rien a signaler', 'ras', 'r.a.s', 'neant', 'non', '-', '/', ''];

    /** Le nom de notre style de paragraphe courant. */
    private const BODY = 'Corps';

    /** La largeur utile d'une page A4 aux marges par défaut de phpword (2,54 cm), en twips, arrondie. */
    private const USABLE_WIDTH = 9000;

    /** La largeur qu'une colonne de tableau ne dépasse pas, en twips. */
    private const MAX_COLUMN_WIDTH = 2600;

    /**
     * Les champs de la fiche qu'on reprend (contexte client) : les temps forts et les notes
     * complémentaires. On garde en interne le bilan de l'intervenant·e et « à reprendre » : ce
     * sont des remarques sur des mineurs. On n'élargira qu'avec l'accord de la cliente
     * (questions-cliente.md, §9).
     */
    private const FIELDS = [
        'highlights' => 'Temps forts et anecdotes',
        'additional_notes' => 'Notes complémentaires',
    ];

    /**
     * @param  string|null  $from  le début de la période (AAAA-MM-JJ), ou null pour ne pas borner
     * @param  string|null  $to  la fin de la période (AAAA-MM-JJ), ou null pour ne pas borner
     */
    public function __construct(
        private readonly Project $project,
        private readonly Funder $funder,
        private readonly ?string $from = null,
        private readonly ?string $to = null,
    ) {}

    /**
     * On écrit le document dans un fichier temporaire et on renvoie son chemin. C'est à
     * l'appelant de le supprimer une fois envoyé.
     *
     * @throws Throwable si phpword n'arrive pas à écrire le fichier
     */
    public function write(): string
    {
        // Sans ce réglage, phpword écrit le texte tel quel dans le XML : un « & » dans un nom
        // de projet rend le fichier illisible.
        Settings::setOutputEscapingEnabled(true);
        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(11);
        $word->addTitleStyle(0, ['size' => 20, 'bold' => true]);
        $word->addTitleStyle(1, ['size' => 15, 'bold' => true], ['spaceBefore' => Converter::pointToTwip(14), 'spaceAfter' => Converter::pointToTwip(6)]);
        $word->addTitleStyle(2, ['size' => 12, 'bold' => true], ['spaceBefore' => Converter::pointToTwip(10), 'spaceAfter' => Converter::pointToTwip(4)]);
        $table = ['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 60];
        $word->addTableStyle('Grille', $table);
        // On nomme le style de paragraphe partout : sinon phpword cherche un style « null »
        // (dépréciation de PHP 8.5).
        $word->addParagraphStyle(self::BODY, ['spaceAfter' => Converter::pointToTwip(4)]);
        $section = $word->addSection();

        $stats = (new StatisticsService($this->project->id, $this->from, $this->to))->compute();
        $row = $stats['projects'][0] ?? null;

        $section->addTitle('Pré-bilan : '.$this->project->name, 0);
        $this->facts($section, [
            'Financeur' => $this->funder->name,
            'Période' => $this->period(),
            'Dates du projet' => $this->projectDates(),
            'Édité le' => LogbookEntry::localNow()->format('d/m/Y'),
        ]);

        $section->addTitle('1. Le projet en chiffres', 1);
        $this->facts($section, [
            'Participants' => (string) ($row['participants'] ?? 0),
            'Séances documentées' => (string) ($row['sessions_documented'] ?? 0),
            'Engagement (sur 5)' => $this->average($row['avg_engagement'] ?? null),
            'Expression (sur 5)' => $this->average($row['avg_expression'] ?? null),
            'Autonomie (sur 5)' => $this->average($row['avg_autonomy'] ?? null),
        ]);

        $section->addTitle('2. Les groupes', 1);
        /** @var iterable<int, array<string, mixed>> $subProjects */
        $subProjects = $row['sub_projects'] ?? [];
        $groups = collect($subProjects);
        $this->table($section, ['Groupe', 'Âge', 'Ville', 'Participants', 'Séances'], $groups->map(fn ($g) => [
            $g['name'], $g['age_range'] ?? '', trim(($g['city'] ?? '').' '.($g['postal_code'] ? '('.$g['postal_code'].')' : '')),
            (string) $g['participants'], (string) $g['sessions_documented'],
        ])->all());

        $section->addTitle('3. Provenance', 1);
        $this->table($section, ['Ville', 'Code postal', 'Participants'], collect($stats['by_city'])->map(fn ($c) => [
            $c['city'] ?? 'Non renseignée', $c['postal_code'] ?? 'Non renseigné', (string) $c['participants'],
        ])->all());

        $section->addTitle('4. Âge', 1);
        $this->table($section, ['Tranche d’âge', 'Participants'], collect($stats['by_age_range'])->map(fn ($a) => [
            $a['age_range'] ?? 'Non renseignée', (string) $a['participants'],
        ])->all());

        $section->addTitle('5. Observations des séances', 1);
        $entries = $this->entries();
        if ($entries->isEmpty()) {
            $section->addText('Aucune fiche rendue sur la période.', null, self::BODY);
        }
        $written = 0;
        foreach ($entries as $entry) {
            // Pour une fiche qui ne dit que « Rien », on n'écrit pas un titre sans contenu dessous (#240).
            $lines = collect(self::FIELDS)
                ->map(fn ($label, $field) => [$label, $this->meaningful($entry->{$field})])
                ->filter(fn ($line) => $line[1] !== null);
            if ($lines->isEmpty()) {
                continue;
            }
            $session = $entry->scheduledSession;
            $groupNames = $session->subProjects->pluck('name')->join(', ');
            $section->addTitle($session->session_date->locale('fr')->translatedFormat('l j F Y')
                .($groupNames !== '' ? ' : '.$groupNames : ''), 2);
            foreach ($lines as [$label, $text]) {
                $run = $section->addTextRun(self::BODY);
                $run->addText($label.' : ', ['bold' => true]);
                $run->addText($text);
            }
            $written++;
        }
        if ($entries->isNotEmpty() && $written === 0) {
            $section->addText('Aucune observation à reprendre dans les fiches rendues sur la période.', null, self::BODY);
        }

        $path = tempnam(sys_get_temp_dir(), 'prebilan');
        try {
            $word->save($path, 'Word2007');
        } catch (Throwable $e) {
            @unlink($path);
            throw $e;
        }

        return $path;
    }

    /**
     * Les fiches rendues des séances non annulées du projet, sur la période, dans l'ordre
     * chronologique.
     *
     * @return Collection<int, LogbookEntry>
     */
    private function entries(): Collection
    {
        return LogbookEntry::query()
            ->whereIn('logbook_entries.status', ['submitted', 'locked'])
            ->join('scheduled_sessions', 'scheduled_sessions.id', '=', 'logbook_entries.scheduled_session_id')
            ->where('scheduled_sessions.project_id', $this->project->id)
            ->where('scheduled_sessions.status', '<>', 'cancelled')
            ->when($this->from, fn ($q, $from) => $q->whereDate('scheduled_sessions.session_date', '>=', $from))
            ->when($this->to, fn ($q, $to) => $q->whereDate('scheduled_sessions.session_date', '<=', $to))
            ->orderBy('scheduled_sessions.session_date')->orderBy('scheduled_sessions.start_time')
            ->select('logbook_entries.*')
            ->with('scheduledSession.subProjects')
            ->get();
    }

    /**
     * On renvoie le texte s'il dit quelque chose, null si c'est une réponse vide (« rien », « RAS »…).
     */
    private function meaningful(?string $text): ?string
    {
        $text = trim((string) $text);

        return in_array(LogbookRules::plain($text), self::EMPTY_ANSWERS, true) ? null : $text;
    }

    /**
     * On écrit une suite de lignes « libellé : valeur ».
     *
     * @param  array<string, string>  $facts
     */
    private function facts(Section $section, array $facts): void
    {
        foreach ($facts as $label => $value) {
            $run = $section->addTextRun(self::BODY);
            $run->addText($label.' : ', ['bold' => true]);
            $run->addText($value);
        }
    }

    /**
     * On écrit un tableau, ou une phrase s'il n'y a aucune ligne.
     *
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     */
    private function table(Section $section, array $header, array $rows): void
    {
        if ($rows === []) {
            $section->addText('Aucune donnée sur la période.', null, self::BODY);

            return;
        }
        // On répartit les colonnes sur la largeur utile de la page : à 2 600 twips chacune, un
        // tableau de 5 colonnes débordait de la marge droite et la dernière était coupée.
        $width = min(self::MAX_COLUMN_WIDTH, intdiv(self::USABLE_WIDTH, max(1, count($header))));
        $table = $section->addTable('Grille');
        $table->addRow();
        foreach ($header as $cell) {
            $table->addCell($width)->addText($cell, ['bold' => true], self::BODY);
        }
        foreach ($rows as $row) {
            $table->addRow();
            foreach ($row as $cell) {
                $table->addCell($width)->addText($cell, null, self::BODY);
            }
        }
    }

    /**
     * Une moyenne sur 5 avec une virgule, ou « Non renseigné » sans aucune note.
     */
    private function average(?float $value): string
    {
        return $value === null ? 'Non renseigné' : str_replace('.', ',', (string) $value);
    }

    /**
     * La période du pré-bilan, en toutes lettres.
     */
    private function period(): string
    {
        $date = fn (?string $d) => $d ? Carbon::parse($d)->format('d/m/Y') : null;
        if (! $this->from && ! $this->to) {
            return 'Toutes les séances';
        }

        return match (true) {
            $this->from && $this->to => 'du '.$date($this->from).' au '.$date($this->to),
            (bool) $this->from => 'à partir du '.$date($this->from),
            default => 'jusqu’au '.$date($this->to),
        };
    }

    /**
     * Les dates du projet, en toutes lettres.
     */
    private function projectDates(): string
    {
        $start = $this->project->start_date?->format('d/m/Y');
        $end = $this->project->end_date?->format('d/m/Y');

        return $start && $end ? "du {$start} au {$end}" : ($start ? "à partir du {$start}" : 'Non renseignées');
    }
}
