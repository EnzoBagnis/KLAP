<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'KLAP API',
        'message' => 'API opérationnelle',
        'version' => '1.0'
    ]);
});
