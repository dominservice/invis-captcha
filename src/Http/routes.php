<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Dominservice\Invisible\ML\Scorer;

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

    $logChannel = config('logging.channels.invis') ? 'invis' : config('logging.default');
    Log::channel($logChannel)->info('score', ['ip'=>$req->ip(),'s'=>$score]);
    return ['token'=>$jwt,'score'=>$score];
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
        $logChannel = config('logging.channels.invis') ? 'invis' : config('logging.default');
        Log::channel($logChannel)->info('pixel', ['ip'=>$r->ip(),'ua'=>$r->userAgent()]);
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true);

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => (string) strlen($pixel),
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    });
}
