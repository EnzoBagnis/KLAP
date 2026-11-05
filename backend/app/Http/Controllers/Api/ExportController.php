<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur d'export des pré-bilans KLAP pour les financeurs.
 * Respecte les exigences strictes de Mariette Travard :
 * - Export Word (.docx) : document modifiable pour transmission aux financeurs.
 * - Export Excel/CSV (.csv) : tableur avec séparateurs et encodage UTF-8 BOM.
 * - Rejet formel et immédiat des demandes au format PDF (le client ne veut AUCUN PDF figé).
 */
class ExportController extends Controller
{
    public function __construct(
        private ReportExportService $exportService
    ) {}

    /**
     * Exporte le pré-bilan d'un projet au format Word (.docx).
     * Vérifie la permission de consultation (RBAC / protection IDOR).
     */
    public function exportWord(int $projectId, Request $request): Response
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('export', $project);

        $docxData = $this->exportService->generateWordDocument($project);
        $slug = Str::slug($project->name);
        $fileName = 'Pre-Bilan-' . $slug . '-' . date('Ymd') . '.docx';

        return response($docxData, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * Exporte le pré-bilan au format Excel / CSV (.csv).
     * Vérifie la permission de consultation (RBAC / protection IDOR).
     */
    public function exportCsv(int $projectId, Request $request): Response
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('export', $project);

        $csvData = $this->exportService->generateCsvReport($project);
        $slug = Str::slug($project->name);
        $fileName = 'Pre-Bilan-' . $slug . '-' . date('Ymd') . '.csv';

        return response($csvData, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * Rejet pédagogique si une tentative d'export PDF est effectuée.
     * Aligné sur la demande expresse de Mariette Travard : Pas de PDF figé !
     */
    public function rejectPdf(Request $request): JsonResponse
    {
        return response()->json([
            'error' => 'Format non autorisé',
            'message' => "L'export au format PDF a été expressément exclu par Mariette Travard (KLAP Kelemenis & cie). " .
                         "Les bilans doivent impérativement être exportés en Word (.docx) ou Excel/CSV (.csv) " .
                         "afin de permettre leur édition et intégration dans les dossiers de subventions des financeurs.",
        ], 400);
    }
}
