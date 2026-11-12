<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\PreReportRequest;
use App\Models\Funder;
use App\Models\LogbookEntry;
use App\Services\PreReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pré-bilan Word (#143), pour l'administratrice et la super-utilisatrice. On trace chaque
 * export dans report_exports.
 */
class ReportController extends Controller
{
    /**
     * On génère le pré-bilan d'un projet pour un financeur et une période, et on l'envoie
     * en téléchargement.
     */
    public function preReport(PreReportRequest $request): BinaryFileResponse
    {
        $project = $request->project();
        $funder = Funder::query()->findOrFail($request->validated('funder_id'));
        $from = $request->validated('from');
        $to = $request->validated('to');

        $path = (new PreReportService($project, $funder, $from, $to))->write();
        DB::table('report_exports')->insert([
            'project_id' => $project->id,
            'funder_id' => $funder->id,
            'generated_by' => $request->user()->id,
            'period_start' => $from,
            'period_end' => $to,
            'options' => json_encode(['format' => 'docx', 'sections' => ['chiffres', 'groupes', 'provenance', 'age', 'observations']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $slug = fn (string $text, string $fallback) => Str::slug($text) ?: $fallback;
        $name = 'pre-bilan-'.$slug($project->name, 'projet').'-'.$slug($funder->name, 'financeur')
            .'-'.LogbookEntry::localNow()->format('Y-m-d').'.docx';

        $response = response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend();
        // Le document est réservé à l'administration : on interdit à tout cache partagé de le
        // garder (#219).
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
