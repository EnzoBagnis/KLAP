<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Statistics\StatisticsRequest;
use App\Models\LogbookEntry;
use App\Services\StatisticsExport;
use App\Services\StatisticsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Export Excel des statistiques (#219), avec les mêmes filtres et les mêmes droits que l'écran.
 */
class StatisticsExportController extends Controller
{
    /**
     * On écrit le classeur dans un fichier temporaire, puis on l'envoie en téléchargement.
     *
     * @throws RuntimeException si on ne peut pas créer le fichier temporaire
     */
    public function __invoke(StatisticsRequest $request): BinaryFileResponse
    {
        $projectId = $request->validated('project_id');
        $projectId = $projectId === null ? null : (int) $projectId;
        $data = (new StatisticsService($projectId, $request->validated('from'), $request->validated('to')))->compute();
        $projectName = $projectId === null ? null : DB::table('projects')->where('id', $projectId)->value('name');

        $path = tempnam(sys_get_temp_dir(), 'klap-statistiques-');
        if ($path === false) {
            throw new RuntimeException('Impossible de créer un fichier temporaire.');
        }
        try {
            (new StatisticsExport($data, $projectName))->write($path);
        } catch (Throwable $error) {
            @unlink($path);
            throw $error;
        }

        $name = 'statistiques-'.($projectName === null ? 'tous-les-projets' : (Str::slug($projectName) ?: 'projet'))
            .'-'.LogbookEntry::localNow()->format('Y-m-d').'.xlsx';

        $response = response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
        // Ces chiffres sont réservés à l'administration : on interdit à tout cache partagé de
        // les garder.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
