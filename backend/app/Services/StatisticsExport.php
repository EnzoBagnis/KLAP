<?php

namespace App\Services;

use App\Models\LogbookEntry;
use App\Support\XlsxWriter;
use Illuminate\Support\Carbon;

/**
 * Export Excel de l'écran Statistiques (#219) : on y met les mêmes chiffres, avec les mêmes
 * filtres, rangés en onglets. Les nombres restent des nombres (Excel peut calculer).
 */
class StatisticsExport
{
    /** Les sensations, telles qu'on les nomme à l'écran. */
    private const FEELINGS = [
        'well_being' => 'Bien-être', 'discomfort' => 'Mal-être', 'tension' => 'Tensions', 'buy_in' => 'Adhésion',
        'enthusiasm' => 'Enthousiasme', 'shyness' => 'Timidité', 'other' => 'Autre',
    ];

    /** Les évolutions, telles qu'on les nomme à l'écran. */
    private const PROGRESS = [
        'first_session' => 'Première séance', 'improvement' => 'Amélioration',
        'stagnation' => 'Stagnation', 'regression' => 'Retour en arrière',
    ];

    /**
     * @param  array<string, mixed>  $data  le résultat de StatisticsService::compute()
     * @param  string|null  $projectName  le projet filtré, ou null pour tous les projets
     */
    public function __construct(private readonly array $data, private readonly ?string $projectName) {}

    /**
     * On écrit le classeur à l'emplacement donné, un onglet par tableau de l'écran.
     */
    public function write(string $path): void
    {
        [$summary, $notes] = $this->summary();
        (new XlsxWriter)
            ->sheet('Synthèse', $summary, [0], $notes)
            // On sépare les projets et les groupes : ainsi, la somme d'une colonne ne compte rien
            // deux fois (les participants d'un projet sont déjà la somme de ses groupes).
            ->sheet('Projets', $this->projects())
            ->sheet('Groupes', $this->groups())
            ->sheet('Provenance', $this->breakdown('by_city', ['Ville', 'Code postal'], fn ($row) => [($row['city'] ?? '') ?: 'Non renseignée', ($row['postal_code'] ?? '') ?: 'Non renseigné']))
            ->sheet('Âge', $this->breakdown('by_age_range', ['Tranche d’âge'], fn ($row) => [($row['age_range'] ?? '') ?: 'Non renseignée']))
            ->sheet('Observations', ...$this->observations())
            ->save($path);
    }

    /**
     * Une moyenne sur 5, ou « Non renseigné » sans aucune note (jamais 0, comme à l'écran).
     */
    private static function average(?float $value): float|string
    {
        return $value ?? 'Non renseigné';
    }

    /**
     * L'onglet Synthèse : les filtres, les totaux, puis nos notes de lecture.
     *
     * @return array{0: list<list<string|int|float|null>>, 1: list<int>} les lignes, et les indices des lignes de note
     */
    private function summary(): array
    {
        $filters = $this->data['filters'];
        $totals = $this->data['totals'];
        $date = fn (?string $value) => $value === null ? null : Carbon::parse($value)->format('d/m/Y');

        $rows = [
            ['Statistiques KLAP'],
            ['Projet', $this->projectName ?? 'Tous les projets'],
            ['Du', $date($filters['from']) ?? 'Début des projets'],
            ['Au', $date($filters['to']) ?? 'Aujourd’hui'],
            ['Exporté le', LogbookEntry::localNow()->format('d/m/Y à H:i')],
            [],
            ['Participants', $totals['participants']],
            ['Séances documentées', $totals['sessions_documented']],
            ['Fiches en retard', $totals['entries_missing']],
            ['dont hors délai', $totals['entries_overdue']],
            ['Engagement (moyenne sur 5)', self::average($totals['avg_engagement'])],
            ['Expression (moyenne sur 5)', self::average($totals['avg_expression'])],
            ['Autonomie (moyenne sur 5)', self::average($totals['avg_autonomy'])],
            [],
            ['Participants d’un groupe : le maximum des présents sur ses séances. Participants d’un projet : la somme de ses groupes (une classe venue à plusieurs séances n’est comptée qu’une fois). Ce n’est pas un nombre de personnes distinctes.'],
            ['Assiduité : présents en moyenne sur les séances documentées, rapportés à l’effectif prévu du groupe (plus de 100 % si plus de présents que prévu).'],
            ['Fiches en retard : séances passées sans fiche rendue ; hors délai : échéance de modification passée.'],
            ['Calculé à partir des fiches rendues ; séances annulées exclues.'],
        ];

        return [$rows, [count($rows) - 4, count($rows) - 3, count($rows) - 2, count($rows) - 1]];
    }

    /**
     * L'onglet Projets : une ligne par projet.
     *
     * @return list<list<string|int|float|null>>
     */
    private function projects(): array
    {
        $rows = [['Projet', 'Participants', 'Séances documentées', 'Fiches en retard', 'dont hors délai', 'Engagement', 'Expression', 'Autonomie']];
        foreach ($this->data['projects'] as $project) {
            $rows[] = [
                $project['name'], $project['participants'], $project['sessions_documented'], $project['entries_missing'], $project['entries_overdue'],
                self::average($project['avg_engagement']), self::average($project['avg_expression']), self::average($project['avg_autonomy']),
            ];
        }

        return $rows;
    }

    /**
     * L'onglet Groupes : une ligne par groupe.
     *
     * @return list<list<string|int|float|null>>
     */
    private function groups(): array
    {
        $rows = [['Projet', 'Groupe', 'Tranche d’âge', 'Ville', 'Code postal', 'Effectif prévu', 'Participants', 'Séances documentées', 'Présents en moyenne', 'Assiduité (%)']];
        foreach ($this->data['projects'] as $project) {
            // On répète le nom du projet sur chaque groupe : le filtre d'Excel garde ainsi le lien.
            foreach ($project['sub_projects'] as $group) {
                $rows[] = [
                    $project['name'], $group['name'], $group['age_range'], $group['city'], $group['postal_code'], $group['expected_headcount'],
                    $group['participants'], $group['sessions_documented'], $group['average_attendees'], $group['attendance_rate'],
                ];
            }
        }

        return $rows;
    }

    /**
     * Un onglet de répartition (provenance, âge).
     *
     * @param  string  $key  la clé du résultat à lire : by_city ou by_age_range
     * @param  list<string>  $columns  les titres des colonnes de libellé
     * @param  callable(array<string, mixed>): list<string>  $labels  les libellés d'une ligne
     * @return list<list<string|int|float|null>>
     */
    private function breakdown(string $key, array $columns, callable $labels): array
    {
        $rows = [[...$columns, 'Groupes documentés', 'Participants']];
        foreach ($this->data[$key] as $row) {
            $rows[] = [...$labels($row), $row['groups'], $row['participants']];
        }

        return $rows;
    }

    /**
     * L'onglet Observations : trois tableaux à la suite (sensation, interaction, évolution).
     *
     * @return array{0: list<list<string|int|null>>, 1: list<int>} les lignes, et les indices des lignes d'en-tête en gras
     */
    private function observations(): array
    {
        $observations = $this->data['observations'];
        $rows = [['Sur '.$observations['entries'].' fiche(s) rendue(s)'], [], ['Sensation', 'Fiches']];
        $bold = [2];
        foreach ($observations['feelings'] as $row) {
            $rows[] = [self::FEELINGS[$row['key']] ?? $row['key'], $row['entries']];
        }
        $rows[] = [];
        $bold[] = count($rows);
        $rows[] = ['Interaction : mot-clé cité', 'Fiches'];
        foreach ($observations['interaction_keywords'] as $row) {
            $rows[] = [mb_convert_case($row['key'], MB_CASE_TITLE), $row['entries']];
        }
        $rows[] = [];
        $bold[] = count($rows);
        $rows[] = ['Évolution par rapport à la séance précédente', 'Fiches'];
        foreach ($observations['progress'] as $row) {
            $rows[] = [self::PROGRESS[$row['key']] ?? $row['key'], $row['entries']];
        }

        return [$rows, $bold];
    }
}
