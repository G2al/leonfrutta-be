<?php

use App\Http\Controllers\PwaManifestController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/manifest.webmanifest', PwaManifestController::class)->name('pwa.manifest');
