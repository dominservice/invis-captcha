<?php
namespace Dominservice\Invisible\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Dominservice\Invisible\Helpers\DynamicFields;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Http;

class Verify
{
    public function handle($req, Closure $next, $threshold=null)
    {
        $cfg = config('invis');
        $thr = $threshold ?? $cfg['threshold'];

        /* honey-field */
        if ($cfg['honey_field']['enabled']
            && $req->filled($cfg['honey_field']['name'])) {
            abort(419, Lang::has('invis::errors.honey_field') 
                ? __('invis::errors.honey_field') 
                : 'Bot (honey field)');
        }

        /* Turnstile bypass */
        if ($cfg['turnstile']['enabled'] && $req->filled('turnstile_token')) {
            // Turnstile REST verification
            try {
                $ok = $this->verifyTurnstile($req->input('turnstile_token'), $req->ip());
                if ($ok) return $next($req);
            } catch (\Exception $e) {
                abort(419, Lang::has('invis::errors.turnstile_error') 
                    ? __('invis::errors.turnstile_error') 
                    : 'Błąd weryfikacji Turnstile');
            }
        }

        /* Invisible token */
        $jwt = $req->input('invis_token');
        if (!$jwt) {
            abort(419, Lang::has('invis::errors.missing_token') 
                ? __('invis::errors.missing_token') 
                : 'Brak tokenu');
        }

        try {
            $data = JWT::decode($jwt, new Key($cfg['secret'], 'HS256'));
            
            // Check if token is valid (not expired, same IP, score above threshold)
            if ($data->exp < time()) {
                abort(419, Lang::has('invis::errors.token_expired') 
                    ? __('invis::errors.token_expired') 
                    : 'Token wygasł');
            }
            
            if ($data->ip !== $req->ip()) {
                abort(419, Lang::has('invis::errors.ip_mismatch') 
                    ? __('invis::errors.ip_mismatch') 
                    : 'Niezgodność IP');
            }
            
            if ($data->score < $thr) {
                abort(419, Lang::has('invis::errors.score_too_low') 
                    ? __('invis::errors.score_too_low') 
                    : 'Podejrzane działanie');
            }
        } catch (ExpiredException $e) {
            abort(419, Lang::has('invis::errors.token_expired') 
                ? __('invis::errors.token_expired') 
                : 'Token wygasł');
        } catch (SignatureInvalidException $e) {
            abort(419, Lang::has('invis::errors.invalid_signature') 
                ? __('invis::errors.invalid_signature') 
                : 'Nieprawidłowy podpis tokenu');
        } catch (\Exception $e) {
            abort(419, Lang::has('invis::errors.invalid_token') 
                ? __('invis::errors.invalid_token') 
                : 'Token nieważny');
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
        try {
            $resp = Http::asForm()->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'secret' => config('invis.turnstile.secret'),
                    'response' => $token,
                    'remoteip' => $ip
                ]
            )->json();
            
            return $resp['success'] ?? false;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Turnstile verification error', [
                'error' => $e->getMessage(),
                'ip' => $ip
            ]);
            return false;
        }
    }
}
