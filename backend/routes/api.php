<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Routes publiques d'authentification
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/invitations/{token}', [AuthController::class, 'getInvitation']);
Route::post('/invitations/{token}/activate', [AuthController::class, 'activateInvitation']);

// Routes protégées par token Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
