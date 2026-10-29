<?php

use Illuminate\Support\Facades\Route;
use M0d1xd\ExpoNotification\Http\Controllers\PushEventController;
use M0d1xd\ExpoNotification\Http\Controllers\PushTokenController;

Route::prefix(config('expo-notification.routes.prefix'))
    ->middleware(config('expo-notification.routes.middleware'))
    ->name('expo-notification.')
    ->group(function () {
        Route::put('push-tokens', [PushTokenController::class, 'register'])->name('push-tokens.register');
        Route::post('push-tokens/detach', [PushTokenController::class, 'detach'])->name('push-tokens.detach');
        Route::delete('push-tokens', [PushTokenController::class, 'destroy'])->name('push-tokens.destroy');
        Route::post('push-events', [PushEventController::class, 'store'])->name('push-events.store');
    });
