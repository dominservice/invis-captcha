<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Dominservice\Invisible\ML\Scorer;

if (!function_exists('dominservice_invis_log')) {
    function dominservice_invis_log(string $level, string $message, array $context = []): void
    {
        $channel = config('logging.channels.invis') ? 'invis' : config('logging.default');
        Log::channel($channel)->{$level}($message, $context);
    }
}

Route::post('/invis-captcha/token', function(\Illuminate\Http\Request $req){
    $sig = $req->all();
    $score = 1 - ($sig['wd']?0.4:0) - ($sig['mm']<3?0.2:0) - ($sig['kb']<1?0.2:0);

    if (config('invis.ml_model.enabled')) {
        $modelPath = config('invis.ml_model.path');
        if (!is_string($modelPath)) {
            $modelPath = storage_path('app/invis/model.json');
        }
        $score = Scorer::load($modelPath)->predict($sig);
    }

    $jwt = JWT::encode([
        'score'=>$score,
        'exp'  => now()->addMinutes(2)->timestamp,
        'ip'   => $req->ip(),
        'fingerprint' => $sig['fingerprint'] ?? null,
        'tracking_event_ulid' => $sig['tracking_event_ulid'] ?? null,
    ], config('invis.secret'), 'HS256');

    dominservice_invis_log('info', 'token_issued', [
        'ip' => $req->ip(),
        'score' => $score,
        'mm' => $sig['mm'] ?? null,
        'kb' => $sig['kb'] ?? null,
        'wd' => $sig['wd'] ?? null,
        'fingerprint' => $sig['fingerprint'] ?? null,
        'tracking_event_ulid' => $sig['tracking_event_ulid'] ?? null,
        'ua' => $req->userAgent(),
    ]);
    return ['token'=>$jwt,'score'=>$score];
});

Route::post(config('invis.debug.endpoint', '/invis-captcha/client-debug'), function(\Illuminate\Http\Request $req){
    if (!config('invis.debug.enabled', false)) {
        return response()->json(['enabled' => false], 404);
    }

    dominservice_invis_log('debug', 'client_debug', [
        'event' => $req->input('event'),
        'context' => $req->input('context', []),
        'ip' => $req->ip(),
        'ua' => $req->userAgent(),
        'url' => $req->input('url'),
    ]);

    return ['ok' => true];
});

/* Save dynamic field mappings */
if (config('invis.dynamic_fields.enabled')) {
    Route::post('/invis-captcha/field-map', function(\Illuminate\Http\Request $req){
        $map = session('_invis_field_map', []);
        $newMappings = $req->input('mappings', []);
        
        foreach ($newMappings as $original => $dynamic) {
            $map[$original] = $dynamic;
        }
        
        session()->put('_invis_field_map', $map);
        return ['success' => true];
    });
}

/* 1-px pixel */
if (config('invis.track_pixel.enabled')) {
    Route::get(config('invis.track_pixel.route'), function(\Illuminate\Http\Request $r){
        dominservice_invis_log('info', 'pixel', ['ip'=>$r->ip(),'ua'=>$r->userAgent()]);
        return response()->stream(fn()=>'GIF87a', 200, ['Content-Type'=>'image/gif']);
    });
}
