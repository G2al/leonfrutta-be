<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company
    |--------------------------------------------------------------------------
    */

    'company_name' => env('LEONFRUTTA_COMPANY_NAME', 'LeonFrutta'),

    /*
    |--------------------------------------------------------------------------
    | Admin panel display timezone
    |--------------------------------------------------------------------------
    |
    | Dates are stored in UTC and shown to the administrator in this timezone.
    |
    */

    'display_timezone' => env('LEONFRUTTA_DISPLAY_TIMEZONE', 'Europe/Rome'),

    /*
    |--------------------------------------------------------------------------
    | Installable admin app (PWA)
    |--------------------------------------------------------------------------
    |
    | Values of the web app manifest that make the admin panel installable on
    | Android (install button) and iPhone (Add to Home Screen).
    |
    */

    'pwa' => [
        'name' => env('LEONFRUTTA_PWA_NAME', 'Admin Leonfrutta'),
        // Shown under the icon on the home screen (Android and iPhone).
        'short_name' => env('LEONFRUTTA_PWA_SHORT_NAME', 'Admin Leonfrutta'),
        'description' => 'Gestione delle richieste d\'ordine LeonFrutta.',
        // Dark background of the Filament panel (gray-950), the same as the app icons.
        'theme_color' => '#09090b',
        'background_color' => '#09090b',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public order API
    |--------------------------------------------------------------------------
    */

    'orders' => [
        'rate_limit_per_minute' => (int) env('LEONFRUTTA_ORDERS_RATE_LIMIT', 5),
        'tracking_rate_limit_per_minute' => (int) env('LEONFRUTTA_ORDERS_TRACKING_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp numbers (wa.me links and automatic WAHA messages)
    |--------------------------------------------------------------------------
    |
    | default_country_code: prefix applied to numbers typed without one.
    | The WAHA connection itself lives in config/services.php.
    |
    */

    'whatsapp' => [
        'default_country_code' => env('LEONFRUTTA_WHATSAPP_COUNTRY_CODE', '39'),
    ],

];
