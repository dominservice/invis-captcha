<?php
/* Publikowane do config_path('invis.php') */
return [
    'threshold' => 0.7,
    'secret'    => env('INVIS_SECRET', \Illuminate\Support\Str::random(32)),

    /* — moduły — */
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
        'enabled' => false,
        'path'    => storage_path('app/invis/model.json'),
    ],

    'turnstile' => [
        'enabled'  => true,
        'sitekey'  => env('TURNSTILE_SITEKEY'),
        'secret'   => env('TURNSTILE_SECRET'),
        'fallback' => 0.30,
    ],
];
