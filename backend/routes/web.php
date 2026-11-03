<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterTestController;
use App\Http\Controllers\ConnexionTextController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function () {
    return view('test');
});

Route::get('/connexion', function () {
    return view('connexion');
});

Route::post('/connexion', [ConnexionTextController::class, 'connexion']);


Route::get('/register', function () {
    return view('register');
});

Route::post('user/register', [RegisterTestController::class, 'store']);