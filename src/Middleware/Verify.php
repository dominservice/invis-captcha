<?php
namespace Dominservice\Invisible\Middleware;

use Closure,
    Firebase\JWT\JWT,
    Firebase\JWT\Key;
use Dominservice\Invisible\Helpers\DynamicFields;

class Verify
{
    public function handle($req, Closure $next, $threshold=null)
    {
        $cfg = config('invis');
        $thr = $threshold ?? $cfg['threshold'];

        /* honey-field */
        if ($cfg['honey_field']['enabled']
            && $req->filled($cfg['honey_field']['name'])) {
            abort(419,'Bot (honey field)');
        }

        /* Turnstile bypass */
        if ($cfg['turnstile']['enabled'] && $req->filled('turnstile_token')) {
            // weryfikacja REST do Turnstile
            $ok = $this->verifyTurnstile($req->input('turnstile_token'), $req->ip());
            if ($ok) return $next($req);
        }

        /* Invisible token */
        $jwt = $req->input('invis_token');
        if (!$jwt) abort(419,'Brak tokenu');

        try {
            $data = JWT::decode($jwt, new Key($cfg['secret'], 'HS256'));
        } catch (\Exception) {
            abort(419,'Token nieważny');
        }
        if ($data->exp < time() || $data->ip !== $req->ip() || $data->score < $thr) {
            abort(419,'Podejrzane');
        }

        /* normalizacja dynamicznych pól */
        if ($cfg['dynamic_fields']['enabled']) {
            foreach ($req->all() as $k=>$v)
                if ($o = DynamicFields::original($k))
                    $req->merge([$o=>$v]);
        }
        return $next($req);
    }

    protected function verifyTurnstile(string $token, string $ip): bool
    {
        $resp = Http::asForm()->post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            ['secret'=>config('invis.turnstile.secret'),'response'=>$token,'remoteip'=>$ip]
        )->json();
        return $resp['success']??false;
    }
}
