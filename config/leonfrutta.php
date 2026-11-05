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
    | Public order API
    |--------------------------------------------------------------------------
    */

    'orders' => [
        'rate_limit_per_minute' => (int) env('LEONFRUTTA_ORDERS_RATE_LIMIT', 5),
        'tracking_rate_limit_per_minute' => (int) env('LEONFRUTTA_ORDERS_TRACKING_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp click-to-chat links (https://wa.me)
    |--------------------------------------------------------------------------
    |
    | default_country_code: prefix applied to numbers typed without one.
    | max_summary_length:   the order text is shortened inside the prefilled
    |                       message to keep the wa.me URL at a safe size.
    |
    */

    'whatsapp' => [
        'default_country_code' => env('LEONFRUTTA_WHATSAPP_COUNTRY_CODE', '39'),
        'max_summary_length' => (int) env('LEONFRUTTA_WHATSAPP_MAX_SUMMARY_LENGTH', 1000),
    ],

];
