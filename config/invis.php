<?php
/* Published to config_path('invis.php') */
return [
    'threshold' => 0.7,
    // HS256 in firebase/php-jwt 7.x requires at least 32 bytes.
    'secret'    => env('INVIS_SECRET', \Illuminate\Support\Str::random(32)),
    'bind_ip' => env('INVIS_BIND_IP', true),
    'skip_authenticated' => env('INVIS_SKIP_AUTHENTICATED', false),

    /* — modules — */
    'track_pixel' => [
        'enabled' => true,
        'route'   => '/invis-captcha/pixel',
    ],

    'polyfill_poison' => [
        'enabled' => true,
        'targets' => ['HTMLCanvasElement.prototype.toDataURL'],
    ],

    'honey_field' => [
        'enabled' => true,
        'name'    => 'website',
    ],

    'dynamic_fields' => [
        'enabled' => true,
        'length'  => 8,
        'prefixes'=> ['name','email','message'],
    ],

    'ml_model'  => [
        'enabled' => true,
        'path'    => storage_path('app/invis/model.json'),
        'auto_generate' => true,
        'mode'    => 'thresholds',  // 'weights' or 'thresholds'
    ],

    'turnstile' => [
        'enabled'  => true,
        'sitekey'  => env('TURNSTILE_SITEKEY'),
        'secret'   => env('TURNSTILE_SECRET'),
        'fallback' => 0.30,
    ],

    'integrations' => [
        'fingerprint_tracking' => [
            'enabled' => env('INVIS_FINGERPRINT_TRACKING_ENABLED', true),
            'payload_attribute' => 'invis_payload',
        ],
    ],
];
