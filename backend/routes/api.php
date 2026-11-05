<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\LogbookController;
use App\Http\Controllers\Api\PlanningController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\StatisticsController;
use Illuminate\Support\Facades\Route;

// Routes publiques d'authentification
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/invitations/{token}', [AuthController::class, 'getInvitation']);
Route::post('/invitations/{token}/activate', [AuthController::class, 'activateInvitation']);

// Routes protégées par token Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // Profil et session courante
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Gestion des projets de danse
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    // Planning et séances d'atelier
    Route::get('/planning', [PlanningController::class, 'index']);
    Route::post('/planning', [PlanningController::class, 'store']);

    // Carnet de bord (formulaire mobile 6 étapes, auto-save et déverrouillage J+10)
    Route::get('/sessions/{sessionId}/logbook', [LogbookController::class, 'showBySession']);
    Route::get('/logbook/sessions/{sessionId}', [LogbookController::class, 'showBySession']);
    Route::post('/logbook/draft', [LogbookController::class, 'saveDraft']);
    Route::post('/logbook/submit', [LogbookController::class, 'submit']);
    Route::post('/logbook/{entry}/request-unlock', [LogbookController::class, 'requestUnlock']);
    Route::post('/logbook/{entry}/unlock', [LogbookController::class, 'unlock']);

    // Lieux d'ateliers et studios permanents
    Route::get('/locations', [LocationController::class, 'index']);
    Route::post('/locations', [LocationController::class, 'store']);

    // Statistiques et indicateurs agrégés (Règle MAX dédoublonnée)
    Route::get('/statistics/projects', [StatisticsController::class, 'getProjectsSummary']);
    Route::get('/statistics/projects/{projectId}', [StatisticsController::class, 'getProjectStats']);

});
