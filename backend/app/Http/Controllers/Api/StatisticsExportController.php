<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Statistics\StatisticsRequest;
use App\Services\StatisticsExport;
use App\Services\StatisticsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Export Excel des statistiques (#219), mêmes filtres et mêmes droits que l'écran. */
class StatisticsExportController extends Controller
{
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
            .'-'.now('Europe/Paris')->format('Y-m-d').'.xlsx';

        $response = response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
        // Des chiffres réservés à l'administration : jamais gardés par un cache partagé.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
