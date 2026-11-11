<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FunderController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\LogbookEntryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectTypeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ScheduledSessionController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\StatisticsExportController;
use App\Http\Controllers\Api\SubProjectController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Limiteur « login » (AppServiceProvider, #150) : 5 essais par minute pour un compte depuis une IP, 30 par IP.
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

// Mot de passe oublié (#81) : routes publiques, limitées par adresse IP (compteur à part).
Route::middleware('throttle:5,1,password-reset')->group(function () {
    Route::post('forgot-password', [PasswordResetController::class, 'forgot']);
    Route::post('reset-password', [PasswordResetController::class, 'reset']);
});

// Invitations (#126) : lecture et activation publiques, par le jeton reçu par mail.
// Compteur distinct de celui de la connexion (préfixe), sinon l'un épuise l'autre.
Route::middleware('throttle:10,1,invitations')->group(function () {
    Route::get('invitations/{token}', [InvitationController::class, 'show']);
    Route::post('invitations/{token}/activate', [InvitationController::class, 'activate']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    // Profil (#71) : changer son mot de passe, en donnant l'actuel. Limité contre l'essai de mots de passe.
    Route::put('me/password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1,change-password');

    // Tableau de bord (#146) : page d'accueil, tous les rôles.
    Route::get('dashboard', DashboardController::class);

    // Lieux : tout utilisateur peut en choisir un (contexte client).
    Route::get('locations', [LocationController::class, 'index']);
    // Créer un lieu (#134) : tout utilisateur, pour les projets en tournée.
    Route::post('locations', [LocationController::class, 'store']);

    // Projets en lecture : tous les rôles. Le contrôleur limite un intervenant à ses
    // affectations (RG02, #89).
    Route::get('projects', [ProjectController::class, 'index']);
    Route::get('projects/{project}', [ProjectController::class, 'show']);

    // Planning (#102) : lecture par tous les rôles, filtrée selon RG02.
    Route::get('projects/{project}/sessions', [ScheduledSessionController::class, 'indexForProject']);
    Route::get('sessions', [ScheduledSessionController::class, 'index']);

    // Fiches de séance (#15, #19) : le contrôle des droits est fait par fiche.
    Route::get('logbook-entries', [LogbookEntryController::class, 'index']);
    Route::get('logbook-entries/{entry}', [LogbookEntryController::class, 'show']);
    Route::patch('logbook-entries/{entry}', [LogbookEntryController::class, 'update']);
    Route::post('logbook-entries/{entry}/submit', [LogbookEntryController::class, 'submit']);
    // Demande de réouverture par l'auteur (#130) ; seule l'administratrice rouvre.
    Route::post('logbook-entries/{entry}/unlock-request', [LogbookEntryController::class, 'requestUnlock']);
    Route::get('sessions/{session}/logbook-entry', [LogbookEntryController::class, 'forSession']);
    Route::post('sessions/{session}/logbook-entry', [LogbookEntryController::class, 'store']);

    // Notifications (#122) : chacun lit les siennes.
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);

    // Statistiques (#113) : administration seulement, en lecture.
    Route::get('statistics', StatisticsController::class)->middleware('role:admin,super_user');
    // Export Excel de l'écran Statistiques (#219) : mêmes filtres, mêmes droits.
    Route::get('statistics/export', StatisticsExportController::class)->middleware('role:admin,super_user');
    // Pré-bilan Word d'un projet pour un financeur (#143).
    Route::get('projects/{project}/pre-report', [ReportController::class, 'preReport'])->middleware('role:admin,super_user');

    // Projets : création réservée à l'administratrice (RG02, #55).
    Route::middleware('role:admin')->group(function () {
        Route::post('projects', [ProjectController::class, 'store']);
        // Modifier, archiver (nom confirmé) et restaurer un projet (#152) : jamais effacé.
        Route::patch('projects/{project}', [ProjectController::class, 'update']);
        Route::post('projects/{project}/archive', [ProjectController::class, 'archive']);
        Route::post('projects/{project}/restore', [ProjectController::class, 'restore']);
        Route::post('projects/{project}/sessions', [ScheduledSessionController::class, 'store']);
        // Groupes d'un projet après sa création (#250) : ajouter, corriger, retirer, remettre.
        Route::post('projects/{project}/sub-projects', [SubProjectController::class, 'store']);
        Route::patch('sub-projects/{subProject}', [SubProjectController::class, 'update']);
        // Modifier, annuler et rétablir une séance sans fiche (#180) : rien n'est effacé.
        Route::patch('sessions/{session}', [ScheduledSessionController::class, 'update']);
        Route::post('sessions/{session}/cancel', [ScheduledSessionController::class, 'cancel']);
        Route::post('sessions/{session}/restore', [ScheduledSessionController::class, 'restore']);

        // Types de projet : liste gérée par l'administratrice, sans suppression.
        Route::get('project-types', [ProjectTypeController::class, 'index']);
        Route::post('project-types', [ProjectTypeController::class, 'store']);
        Route::patch('project-types/{projectType}', [ProjectTypeController::class, 'update']);

        // Listes pour les menus du formulaire projet (#92).
        Route::get('users', [UserController::class, 'index']);
        // Gestion des comptes (#159) : liste, désactivation et réactivation, jamais de suppression.
        Route::get('accounts', [AccountController::class, 'index']);
        Route::patch('accounts/{account}', [AccountController::class, 'update']);
        // Financeurs (#249) : liste gérée par l'administratrice, sans suppression.
        Route::get('funders', [FunderController::class, 'index']);
        Route::post('funders', [FunderController::class, 'store']);
        Route::patch('funders/{funder}', [FunderController::class, 'update']);
        // Corriger, désactiver ou réactiver un lieu (#249) ; la création reste ouverte à tous.
        Route::patch('locations/{location}', [LocationController::class, 'update']);

        // Inviter une personne à créer son compte (#126).
        Route::post('invitations', [InvitationController::class, 'store']);

        // Rouvrir une fiche verrouillée ou hors délai (#109), opération tracée.
        Route::post('logbook-entries/{entry}/unlock', [LogbookEntryController::class, 'unlock']);
    });
});
