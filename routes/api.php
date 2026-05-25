<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\LocationController;
use App\Http\Controllers\CircleController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Endpoint untuk update dan ambil lokasi real-time
    Route::post('/location', [LocationController::class, 'updateLocation']);
    Route::get('/circles/{circle}/locations', [LocationController::class, 'getCircleLocations']);

    // Endpoint untuk bergabung dan keluar circle
    Route::post('/circles/join', [CircleController::class, 'join']);
    Route::post('/circles/leave', [CircleController::class, 'leave']);

    // Endpoint untuk update foto profil
    Route::post('/user/photo', [\App\Http\Controllers\UserController::class, 'updatePhoto']);
});

require __DIR__.'/auth.php';
