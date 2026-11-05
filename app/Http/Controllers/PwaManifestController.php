<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Web app manifest of the admin panel. It is served by Laravel, not as a
 * static file, so names and colors come from config/leonfrutta.php and the
 * response always carries the official manifest content type.
 */
class PwaManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $pwa = config('leonfrutta.pwa');

        return response()->json([
            'id' => '/admin',
            'name' => $pwa['name'],
            'short_name' => $pwa['short_name'],
            'description' => $pwa['description'],
            'lang' => 'it',
            'dir' => 'ltr',
            'start_url' => '/admin',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'theme_color' => $pwa['theme_color'],
            'background_color' => $pwa['background_color'],
            'icons' => [
                ['src' => '/pwa/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/pwa/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/pwa/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], headers: [
            'Content-Type' => 'application/manifest+json',
        ], options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
