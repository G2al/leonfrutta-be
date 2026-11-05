<?php

use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PwaManifestController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/manifest.webmanifest', PwaManifestController::class)->name('pwa.manifest');

Route::post('/admin/push-subscriptions', [PushSubscriptionController::class, 'store'])
    ->middleware('auth')
    ->name('push-subscriptions.store');
