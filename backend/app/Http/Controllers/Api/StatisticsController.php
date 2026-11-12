<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Statistics\StatisticsRequest;
use App\Services\StatisticsService;
use Illuminate\Http\JsonResponse;

/**
 * Statistiques agrégées (#113), en lecture seule, pour l'administratrice et la
 * super-utilisatrice.
 */
class StatisticsController extends Controller
{
    /**
     * On calcule les statistiques pour les filtres demandés (projet, période).
     */
    public function __invoke(StatisticsRequest $request): JsonResponse
    {
        $projectId = $request->validated('project_id');
        $service = new StatisticsService(
            $projectId === null ? null : (int) $projectId,
            $request->validated('from'),
            $request->validated('to'),
        );

        return response()->json(['data' => $service->compute()]);
    }
}
