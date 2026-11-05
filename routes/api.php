<?php

use App\Http\Controllers\Api\V1\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('orders', [OrderController::class, 'store'])
        ->middleware('throttle:orders')
        ->name('orders.store');

    Route::get('orders/{order:tracking_token}', [OrderController::class, 'show'])
        ->middleware('throttle:order-tracking')
        ->name('orders.show');
});
