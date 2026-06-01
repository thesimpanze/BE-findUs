<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\LocationController;
use App\Http\Controllers\CircleController;
use App\Http\Controllers\MidtransNotificationController;
use App\Http\Controllers\SubscriptionController;

// Endpoint webhook Midtrans untuk update status pembayaran subscription
Route::post('/midtrans/notification', [MidtransNotificationController::class, 'handle']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', [\App\Http\Controllers\UserController::class, 'getUser']);

    // endpoint untuk cek subscription, upgrade ke premium, dan cancel subscription 
    Route::get('/subscription', [SubscriptionController::class, 'show']);
    Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgradeToPremium']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);

    // endpoint untuk melihat riwayat pembayaran dan cancel pembayaran subscription pending
    Route::get('/subscription/payments', [SubscriptionController::class, 'payments']);
    Route::post('/subscription/payment/cancel', [SubscriptionController::class, 'cancelPayment']);

    // Endpoint untuk update dan ambil lokasi real-time
    Route::post('/location', [LocationController::class, 'updateLocation']);
    Route::get('/circles/{circle}/locations', [LocationController::class, 'getCircleLocations']);
    Route::get('/circles/{circle}/location-history', [LocationController::class, 'getCircleLocationHistory']);

    // Endpoint untuk bergabung dan keluar circle
    Route::post('/circles/join', [CircleController::class, 'join']);
    Route::post('/circles/leave', [CircleController::class, 'leave']);
    Route::get('/circles/{circle}/members', [CircleController::class, 'members']);

    // Endpoint untuk update foto profil
    Route::post('/user/photo', [\App\Http\Controllers\UserController::class, 'updatePhoto']);
    Route::put('/user', [\App\Http\Controllers\UserController::class, 'updateProfile']);
});

require __DIR__.'/auth.php';
