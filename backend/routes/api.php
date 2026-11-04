<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectTypeController;
use Illuminate\Support\Facades\Route;

// 5 tentatives par minute et par IP, pour limiter le forçage de mot de passe.
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    // Projets : création et lecture réservées à l'administratrice (RG02, #55).
    Route::middleware('role:admin')->group(function () {
        Route::get('projects', [ProjectController::class, 'index']);
        Route::post('projects', [ProjectController::class, 'store']);
        Route::get('projects/{project}', [ProjectController::class, 'show']);

        // Types de projet : liste gérée par l'administratrice, sans suppression.
        Route::get('project-types', [ProjectTypeController::class, 'index']);
        Route::post('project-types', [ProjectTypeController::class, 'store']);
        Route::patch('project-types/{projectType}', [ProjectTypeController::class, 'update']);
    });
});
