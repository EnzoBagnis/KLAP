<?php

namespace App\Services;

use App\Support\XlsxWriter;
use Illuminate\Support\Carbon;

/**
 * Export Excel de l'écran Statistiques (#219) : les mêmes chiffres, avec les mêmes filtres,
 * rangés en onglets. Les nombres restent des nombres (Excel peut calculer).
 */
class StatisticsExport
{
    private const FEELINGS = [
        'well_being' => 'Bien-être', 'discomfort' => 'Mal-être', 'tension' => 'Tensions', 'buy_in' => 'Adhésion',
        'enthusiasm' => 'Enthousiasme', 'shyness' => 'Timidité', 'other' => 'Autre',
    ];

    private const PROGRESS = [
        'first_session' => 'Première séance', 'improvement' => 'Amélioration',
        'stagnation' => 'Stagnation', 'regression' => 'Retour en arrière',
    ];

    /** @param array<string, mixed> $data résultat de StatisticsService::compute() */
    public function __construct(private readonly array $data, private readonly ?string $projectName) {}

    public function write(string $path): void
    {
        (new XlsxWriter)
            ->sheet('Synthèse', $this->summary(), [0])
            ->sheet('Par projet', $this->projects())
            ->sheet('Provenance', $this->breakdown('by_city', ['Ville', 'Code postal'], fn ($row) => [$row['city'] ?? 'Non renseignée', $row['postal_code'] ?? 'Non renseigné']))
            ->sheet('Âge', $this->breakdown('by_age_range', ['Tranche d’âge'], fn ($row) => [$row['age_range'] ?? 'Non renseignée']))
            ->sheet('Observations', ...$this->observations())
            ->save($path);
    }

    private function summary(): array
    {
        $filters = $this->data['filters'];
        $totals = $this->data['totals'];
        $date = fn (?string $value) => $value === null ? null : Carbon::parse($value)->format('d/m/Y');

        return [
            ['Statistiques KLAP'],
            ['Projet', $this->projectName ?? 'Tous les projets'],
            ['Du', $date($filters['from']) ?? 'Début des projets'],
            ['Au', $date($filters['to']) ?? 'Aujourd’hui'],
            ['Exporté le', now('Europe/Paris')->format('d/m/Y à H:i')],
            [],
            ['Participants', $totals['participants']],
            ['Séances documentées', $totals['sessions_documented']],
            ['Fiches en retard', $totals['entries_missing']],
            ['dont hors délai', $totals['entries_overdue']],
            ['Engagement (moyenne sur 5)', $totals['avg_engagement']],
            ['Expression (moyenne sur 5)', $totals['avg_expression']],
            ['Autonomie (moyenne sur 5)', $totals['avg_autonomy']],
            [],
            ['Participants d’un groupe : le maximum des présents sur ses séances. Participants d’un projet : la somme de ses groupes (une classe venue à plusieurs séances n’est comptée qu’une fois).'],
            ['Calculé à partir des fiches rendues ; séances annulées exclues.'],
        ];
    }

    private function projects(): array
    {
        $rows = [[
            'Projet', 'Groupe', 'Tranche d’âge', 'Ville', 'Code postal', 'Effectif prévu', 'Participants', 'Séances documentées',
            'Présents en moyenne', 'Assiduité (%)', 'Fiches en retard', 'dont hors délai', 'Engagement', 'Expression', 'Autonomie',
        ]];
        foreach ($this->data['projects'] as $project) {
            $rows[] = [
                $project['name'], 'Tout le projet', null, null, null, null, $project['participants'], $project['sessions_documented'],
                null, null, $project['entries_missing'], $project['entries_overdue'],
                $project['avg_engagement'], $project['avg_expression'], $project['avg_autonomy'],
            ];
            // Le nom du projet est répété sur chaque groupe : le filtre d'Excel garde le lien.
            foreach ($project['sub_projects'] as $group) {
                $rows[] = [
                    $project['name'], $group['name'], $group['age_range'], $group['city'], $group['postal_code'], $group['expected_headcount'],
                    $group['participants'], $group['sessions_documented'], $group['average_attendees'], $group['attendance_rate'],
                ];
            }
        }

        return $rows;
    }

    private function breakdown(string $key, array $columns, callable $labels): array
    {
        $rows = [[...$columns, 'Groupes documentés', 'Participants']];
        foreach ($this->data[$key] as $row) {
            $rows[] = [...$labels($row), $row['groups'], $row['participants']];
        }

        return $rows;
    }

    /** @return array{0: list<list<string|int|null>>, 1: list<int>} lignes, et lignes d'en-tête en gras */
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
