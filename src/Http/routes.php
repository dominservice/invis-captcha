<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Dominservice\Invisible\ML\Scorer;

Route::post('/invis-captcha/token', function(\Illuminate\Http\Request $req){
    $sig = $req->all();
    $score = 1 - ($sig['wd']?0.4:0) - ($sig['mm']<3?0.2:0) - ($sig['kb']<1?0.2:0);

    if (config('invis.ml_model.enabled')) {
        $score = Scorer::load(config('invis.ml_model.path'))->predict($sig);
    }

    $jwt = JWT::encode([
        'score'=>$score,
        'exp'  => now()->addMinutes(2)->timestamp,
        'ip'   => $req->ip(),
    ], config('invis.secret'), 'HS256');

    Log::channel('invis')->info('score', ['ip'=>$req->ip(),'s'=>$score]);
    return ['token'=>$jwt,'score'=>$score];
});

/* 1-px pixel */
if (config('invis.track_pixel.enabled')) {
    Route::get(config('invis.track_pixel.route'), function(\Illuminate\Http\Request $r){
        Log::channel('invis')->info('pixel', ['ip'=>$r->ip(),'ua'=>$r->userAgent()]);
        return response()->stream(fn()=>'GIF87a', 200, ['Content-Type'=>'image/gif']);
    });
}
