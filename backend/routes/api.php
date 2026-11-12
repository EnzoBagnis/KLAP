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
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\ProjectTypeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ScheduledSessionController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\StatisticsExportController;
use App\Http\Controllers\Api\SubProjectController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// On limite la connexion (limiteur « login » d'AppServiceProvider, #150) : 5 essais par
// minute pour un compte depuis une IP, 30 par IP.
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

// Mot de passe oublié (#81) : des routes publiques, qu'on limite par adresse IP, avec un
// compteur à part.
Route::middleware('throttle:5,1,password-reset')->group(function () {
    Route::post('forgot-password', [PasswordResetController::class, 'forgot']);
    Route::post('reset-password', [PasswordResetController::class, 'reset']);
});

// Invitations (#126) : on les lit et on les active sans être connecté, avec le jeton reçu
// par mail. On leur donne un compteur distinct de celui de la connexion (préfixe) : sinon
// l'un épuise l'autre.
Route::middleware('throttle:10,1,invitations')->group(function () {
    Route::get('invitations/{token}', [InvitationController::class, 'show']);
    Route::post('invitations/{token}/activate', [InvitationController::class, 'activate']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    // Profil (#71) : on change son mot de passe en donnant l'actuel. On limite la route,
    // contre l'essai de mots de passe.
    Route::put('me/password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1,change-password');

    // Tableau de bord (#146) : la page d'accueil de tous les rôles.
    Route::get('dashboard', DashboardController::class);

    // Lieux : tout utilisateur peut en choisir un (contexte client).
    Route::get('locations', [LocationController::class, 'index']);
    // Créer un lieu (#134) : tout utilisateur, pour les projets en tournée.
    Route::post('locations', [LocationController::class, 'store']);

    // Projets en lecture : tous les rôles. C'est le contrôleur qui limite un intervenant à
    // ses affectations (RG02, #89).
    Route::get('projects', [ProjectController::class, 'index']);
    Route::get('projects/{project}', [ProjectController::class, 'show']);

    // Planning (#102) : tous les rôles le lisent, et on le filtre selon RG02.
    Route::get('projects/{project}/sessions', [ScheduledSessionController::class, 'indexForProject']);
    Route::get('sessions', [ScheduledSessionController::class, 'index']);

    // Fiches de séance (#15, #19) : on contrôle les droits fiche par fiche.
    Route::get('logbook-entries', [LogbookEntryController::class, 'index']);
    Route::get('logbook-entries/{entry}', [LogbookEntryController::class, 'show']);
    Route::patch('logbook-entries/{entry}', [LogbookEntryController::class, 'update']);
    Route::post('logbook-entries/{entry}/submit', [LogbookEntryController::class, 'submit']);
    // L'auteur demande la réouverture (#130) ; seule l'administratrice rouvre.
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
    // Export Excel de l'écran Statistiques (#219) : on garde les mêmes filtres et les mêmes droits.
    Route::get('statistics/export', StatisticsExportController::class)->middleware('role:admin,super_user');
    // Pré-bilan Word d'un projet pour un financeur (#143).
    Route::get('projects/{project}/pre-report', [ReportController::class, 'preReport'])->middleware('role:admin,super_user');

    // Tout ce qui suit est réservé à l'administratrice, à commencer par la création d'un
    // projet (RG02, #55).
    Route::middleware('role:admin')->group(function () {
        Route::post('projects', [ProjectController::class, 'store']);
        // Modifier, archiver (en confirmant le nom) et restaurer un projet (#152) : on ne
        // l'efface jamais.
        Route::patch('projects/{project}', [ProjectController::class, 'update']);
        Route::post('projects/{project}/archive', [ProjectController::class, 'archive']);
        Route::post('projects/{project}/restore', [ProjectController::class, 'restore']);
        Route::post('projects/{project}/sessions', [ScheduledSessionController::class, 'store']);
        // Groupes d'un projet après sa création (#250) : ajouter, corriger, retirer, remettre.
        Route::post('projects/{project}/sub-projects', [SubProjectController::class, 'store']);
        Route::patch('sub-projects/{subProject}', [SubProjectController::class, 'update']);
        // Équipe d'un projet (#251) : ajouter ou retirer un·e intervenant·e ponctuel·le.
        Route::post('projects/{project}/members', [ProjectMemberController::class, 'store']);
        Route::delete('projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy']);
        // Modifier, annuler et rétablir une séance sans fiche (#180) : on n'efface rien.
        Route::patch('sessions/{session}', [ScheduledSessionController::class, 'update']);
        Route::post('sessions/{session}/cancel', [ScheduledSessionController::class, 'cancel']);
        Route::post('sessions/{session}/restore', [ScheduledSessionController::class, 'restore']);

        // Types de projet : une liste que l'administratrice tient elle-même, sans suppression.
        Route::get('project-types', [ProjectTypeController::class, 'index']);
        Route::post('project-types', [ProjectTypeController::class, 'store']);
        Route::patch('project-types/{projectType}', [ProjectTypeController::class, 'update']);

        // Listes pour les menus du formulaire projet (#92).
        Route::get('users', [UserController::class, 'index']);
        // Gestion des comptes (#159) : on les liste, on les désactive, on les réactive, on ne
        // les supprime jamais.
        Route::get('accounts', [AccountController::class, 'index']);
        Route::patch('accounts/{account}', [AccountController::class, 'update']);
        // Financeurs (#249) : une liste que l'administratrice tient elle-même, sans suppression.
        Route::get('funders', [FunderController::class, 'index']);
        Route::post('funders', [FunderController::class, 'store']);
        Route::patch('funders/{funder}', [FunderController::class, 'update']);
        // Corriger, désactiver ou réactiver un lieu (#249) ; la création reste ouverte à tous.
        Route::patch('locations/{location}', [LocationController::class, 'update']);

        // Inviter une personne à créer son compte (#126).
        Route::post('invitations', [InvitationController::class, 'store']);

        // Rouvrir une fiche verrouillée ou hors délai (#109) : on trace l'opération.
        Route::post('logbook-entries/{entry}/unlock', [LogbookEntryController::class, 'unlock']);
    });
});
