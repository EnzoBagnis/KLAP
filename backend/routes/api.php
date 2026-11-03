<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\LogbookController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PlanningController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\StatisticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes d'Authentification Publiques (Sans token)
|--------------------------------------------------------------------------
*/

// Connexion avec protection anti-brute force (5 tentatives/min par IP)
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// Consultation et activation sécurisée d'une invitation par jeton SHA-256 temporisé
Route::get('/invitations/{token}', [AuthController::class, 'getInvitation']);
Route::post('/invitations/{token}/activate', [AuthController::class, 'activateInvitation']);

/*
|--------------------------------------------------------------------------
| Routes Protégées par Authentification (Laravel Sanctum & Session SPA)
|--------------------------------------------------------------------------
*/
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

    // Carnet de bord (Formulaire 6 étapes, auto-save et déverrouillage)
    Route::get('/sessions/{sessionId}/logbook', [LogbookController::class, 'showBySession']);
    Route::post('/logbook/draft', [LogbookController::class, 'saveDraft']);
    Route::post('/logbook/submit', [LogbookController::class, 'submit']);
    Route::post('/logbook/{entry}/request-unlock', [LogbookController::class, 'requestUnlock']);
    Route::post('/logbook/{entry}/unlock', [LogbookController::class, 'unlock']);

    // Lieux d'ateliers et studios permanents
    Route::get('/locations', [LocationController::class, 'index']);
    Route::post('/locations', [LocationController::class, 'store']);

    // Notifications internes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // Statistiques et indicateurs agrégés (Règle MAX dédoublonnée)
    Route::get('/statistics/projects', [StatisticsController::class, 'getProjectsSummary']);
    Route::get('/statistics/projects/{projectId}', [StatisticsController::class, 'getProjectStats']);

    // Génération et export des pré-bilans (Word & Excel/CSV - strictement AUCUN PDF)
    Route::get('/exports/projects/{projectId}/word', [ExportController::class, 'exportWord']);
    Route::get('/exports/projects/{projectId}/excel', [ExportController::class, 'exportCsv']);
    Route::get('/exports/projects/{projectId}/csv', [ExportController::class, 'exportCsv']);
    Route::get('/exports/projects/{projectId}/pdf', [ExportController::class, 'rejectPdf']);
});
