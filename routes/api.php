<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\LocationController;
use App\Http\Controllers\CircleController;
use App\Http\Controllers\SubscriptionController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    //endpoint cek subscription, upgrade ke premium, dan cancel subscription
    Route::get('/subscription', [SubscriptionController::class, 'show']);
    Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgradeToPremium']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);

    // Endpoint untuk update dan ambil lokasi real-time
    Route::post('/location', [LocationController::class, 'updateLocation']);
    Route::get('/circles/{circle}/locations', [LocationController::class, 'getCircleLocations']);

    // Endpoint untuk bergabung dan keluar circle
    Route::post('/circles/join', [CircleController::class, 'join']);
    Route::post('/circles/leave', [CircleController::class, 'leave']);
});

require __DIR__.'/auth.php';
