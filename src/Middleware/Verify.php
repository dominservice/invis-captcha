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
use Illuminate\Support\Facades\Log;

class Verify
{
    public function handle($req, Closure $next, $threshold=null)
    {
        $cfg = config('invis');
        $thr = $threshold ?? $cfg['threshold'];

        if (!empty($cfg['skip_authenticated']) && app()->bound('auth') && $req->user()) {
            return $next($req);
        }

        /* honey-field */
        if ($cfg['honey_field']['enabled']) {
            // Check in regular request
            if ($req->filled($cfg['honey_field']['name'])) {
                $this->abortWithLog($req, 'honey_field_regular', Lang::has('invis::errors.honey_field')
                    ? __('invis::errors.honey_field')
                    : 'Bot (honey field)');
            }
            
            // Check in Livewire request
            if ($req->has('components') && is_array($req->input('components'))) {
                foreach ($req->input('components') as $component) {
                    // Check in snapshot data (JSON string)
                    if (isset($component['snapshot'])) {
                        $snapshot = json_decode($component['snapshot'], true);
                        if (isset($snapshot['data'][$cfg['honey_field']['name']])) {
                            $this->abortWithLog($req, 'honey_field_livewire_snapshot', Lang::has('invis::errors.honey_field')
                                ? __('invis::errors.honey_field')
                                : 'Bot (honey field)');
                        }
                    }
                    
                    // Check in updates data
                    if (isset($component['updates'][$cfg['honey_field']['name']])) {
                        $this->abortWithLog($req, 'honey_field_livewire_updates', Lang::has('invis::errors.honey_field')
                            ? __('invis::errors.honey_field')
                            : 'Bot (honey field)');
                    }
                    
                    // Legacy check in direct data
                    if (isset($component['data'][$cfg['honey_field']['name']])) {
                        $this->abortWithLog($req, 'honey_field_livewire_data', Lang::has('invis::errors.honey_field')
                            ? __('invis::errors.honey_field')
                            : 'Bot (honey field)');
                    }
                }
            }
        }

        /* Turnstile bypass */
        if ($cfg['turnstile']['enabled']) {
            $turnstileToken = null;
            
            // Check in regular request
            if ($req->filled('turnstile_token')) {
                $turnstileToken = $req->input('turnstile_token');
            }
            
            // Check in Livewire request
            if (!$turnstileToken && $req->has('components') && is_array($req->input('components'))) {
                foreach ($req->input('components') as $component) {
                    // Check in snapshot data (JSON string)
                    if (isset($component['snapshot'])) {
                        $snapshot = json_decode($component['snapshot'], true);
                        if (isset($snapshot['data']['turnstile_token'])) {
                            $turnstileToken = $snapshot['data']['turnstile_token'];
                            break;
                        }
                    }
                    
                    // Check in updates data
                    if (isset($component['updates']['turnstile_token'])) {
                        $turnstileToken = $component['updates']['turnstile_token'];
                        break;
                    }
                    
                    // Legacy check in direct data
                    if (isset($component['data']['turnstile_token'])) {
                        $turnstileToken = $component['data']['turnstile_token'];
                        break;
                    }
                }
            }
            
            // Verify token if found
            if ($turnstileToken) {
                try {
                    $ok = $this->verifyTurnstile($turnstileToken, $req->ip());
                    if ($ok) return $next($req);
                } catch (\Exception $e) {
                    $this->abortWithLog($req, 'turnstile_error', Lang::has('invis::errors.turnstile_error')
                        ? __('invis::errors.turnstile_error')
                        : 'Błąd weryfikacji Turnstile', ['exception' => $e->getMessage()]);
                }
            }
        }

        /* Invisible token */
        $jwt = $req->input('invis_token');
        
        // Check in Livewire request if not found in regular request
        if (!$jwt && $req->has('components') && is_array($req->input('components'))) {
            foreach ($req->input('components') as $component) {
                // Check in snapshot data (JSON string)
                if (isset($component['snapshot'])) {
                    $snapshot = json_decode($component['snapshot'], true);
                    if (isset($snapshot['data']['invis_token'])) {
                        $jwt = $snapshot['data']['invis_token'];
                        break;
                    }
                }
                
                // Check in updates data
                if (isset($component['updates']['invis_token'])) {
                    $jwt = $component['updates']['invis_token'];
                    break;
                }
                
                // Legacy check in direct data
                if (isset($component['data']['invis_token'])) {
                    $jwt = $component['data']['invis_token'];
                    break;
                }
            }
        }
        
        if (!$jwt) {
            $this->abortWithLog($req, 'missing_token', Lang::has('invis::errors.missing_token')
                ? __('invis::errors.missing_token')
                : 'Brak tokenu');
        }

        try {
            $data = JWT::decode($jwt, new Key($cfg['secret'], 'HS256'));
            $payload = (array) $data;
            
            // Check if token is valid (not expired, same IP, score above threshold)
            if ($data->exp < time()) {
                $this->abortWithLog($req, 'token_expired', Lang::has('invis::errors.token_expired')
                    ? __('invis::errors.token_expired')
                    : 'Token wygasł', ['payload' => $payload]);
            }
            
            if (($cfg['bind_ip'] ?? true) && ($data->ip ?? null) !== $req->ip()) {
                $this->abortWithLog($req, 'ip_mismatch', Lang::has('invis::errors.ip_mismatch')
                    ? __('invis::errors.ip_mismatch')
                    : 'Niezgodność IP', ['payload_ip' => $data->ip ?? null, 'request_ip' => $req->ip()]);
            }
            
            if ($data->score < $thr) {
                $this->abortWithLog($req, 'score_too_low', Lang::has('invis::errors.score_too_low')
                    ? __('invis::errors.score_too_low')
                    : 'Podejrzane działanie', ['score' => $data->score, 'threshold' => $thr]);
            }

            $this->hydrateRequestFromPayload($req, $payload);
            $this->synchronizeFingerprintTracking($req, $payload);
        } catch (ExpiredException $e) {
            $this->abortWithLog($req, 'token_expired_exception', Lang::has('invis::errors.token_expired')
                ? __('invis::errors.token_expired')
                : 'Token wygasł', ['exception' => $e->getMessage()]);
        } catch (SignatureInvalidException $e) {
            $this->abortWithLog($req, 'invalid_signature', Lang::has('invis::errors.invalid_signature')
                ? __('invis::errors.invalid_signature')
                : 'Nieprawidłowy podpis tokenu', ['exception' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            $this->abortWithLog($req, 'runtime_exception', $e->getMessage(), ['exception' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->abortWithLog($req, 'invalid_token', Lang::has('invis::errors.invalid_token')
                ? __('invis::errors.invalid_token')
                : 'Token nieważny', ['exception' => $e->getMessage()]);
        }

        /* normalizacja dynamicznych pól */
        if ($cfg['dynamic_fields']['enabled']) {
            // Process regular request fields
            $originalFields = [];
            $dynamicFields = [];
            
            foreach ($req->all() as $k=>$v) {
                if ($o = DynamicFields::original($k)) {
                    $originalFields[$o] = $v;
                    $dynamicFields[] = $k;
                }
            }
            
            // Remove dynamic fields and add original ones for regular request
            if (!empty($originalFields)) {
                $req->merge($originalFields);
                foreach ($dynamicFields as $field) {
                    $req->request->remove($field);
                }
            }
            
            // Process Livewire request fields
            if ($req->has('components') && is_array($req->input('components'))) {
                $components = $req->input('components');
                $updatedComponents = false;
                
                foreach ($components as $i => $component) {
                    // Process snapshot data
                    if (isset($component['snapshot'])) {
                        $snapshot = json_decode($component['snapshot'], true);
                        if (isset($snapshot['data']) && is_array($snapshot['data'])) {
                            $originalSnapshotFields = [];
                            $dynamicSnapshotFields = [];
                            
                            foreach ($snapshot['data'] as $k => $v) {
                                if ($o = DynamicFields::original($k)) {
                                    $originalSnapshotFields[$o] = $v;
                                    $dynamicSnapshotFields[] = $k;
                                    $updatedComponents = true;
                                }
                            }
                            
                            // Add original fields to snapshot data
                            foreach ($originalSnapshotFields as $field => $value) {
                                $snapshot['data'][$field] = $value;
                            }
                            
                            // Remove dynamic fields from snapshot data
                            foreach ($dynamicSnapshotFields as $field) {
                                unset($snapshot['data'][$field]);
                            }
                            
                            // Update the snapshot in the component
                            $components[$i]['snapshot'] = json_encode($snapshot);
                        }
                    }
                    
                    // Process updates data
                    if (isset($component['updates']) && is_array($component['updates'])) {
                        $originalUpdatesFields = [];
                        $dynamicUpdatesFields = [];
                        
                        foreach ($component['updates'] as $k => $v) {
                            if ($o = DynamicFields::original($k)) {
                                $originalUpdatesFields[$o] = $v;
                                $dynamicUpdatesFields[] = $k;
                                $updatedComponents = true;
                            }
                        }
                        
                        // Add original fields to updates data
                        foreach ($originalUpdatesFields as $field => $value) {
                            $components[$i]['updates'][$field] = $value;
                        }
                        
                        // Remove dynamic fields from updates data
                        foreach ($dynamicUpdatesFields as $field) {
                            unset($components[$i]['updates'][$field]);
                        }
                    }
                    
                    // Legacy: Process direct data fields
                    if (isset($component['data']) && is_array($component['data'])) {
                        $originalLivewireFields = [];
                        $dynamicLivewireFields = [];
                        
                        foreach ($component['data'] as $k => $v) {
                            if ($o = DynamicFields::original($k)) {
                                $originalLivewireFields[$o] = $v;
                                $dynamicLivewireFields[] = $k;
                                $updatedComponents = true;
                            }
                        }
                        
                        // Add original fields to component data
                        foreach ($originalLivewireFields as $field => $value) {
                            $components[$i]['data'][$field] = $value;
                        }
                        
                        // Remove dynamic fields from component data
                        foreach ($dynamicLivewireFields as $field) {
                            unset($components[$i]['data'][$field]);
                        }
                    }
                }
                
                // Update the request with normalized component data
                if ($updatedComponents) {
                    $req->merge(['components' => $components]);
                }
            }
        }
        return $next($req);
    }

    protected function abortWithLog($req, string $reason, string $message, array $context = []): void
    {
        $channel = config('logging.channels.invis') ? 'invis' : config('logging.default');

        Log::channel($channel)->warning('verify_failed', array_merge([
            'reason' => $reason,
            'message' => $message,
            'request_ip' => $req->ip(),
            'url' => $req->fullUrl(),
            'method' => $req->method(),
            'has_invis_token' => $req->filled('invis_token'),
            'has_turnstile_token' => $req->filled('turnstile_token'),
            'fingerprint' => $req->input('fingerprint'),
            'tracking_event_ulid' => $req->input('tracking_event_ulid'),
            'user_agent' => $req->userAgent(),
        ], $context));

        abort(419, $message);
    }

    protected function hydrateRequestFromPayload($req, array $payload): void
    {
        $payloadAttribute = (string) config('invis.integrations.fingerprint_tracking.payload_attribute', 'invis_payload');
        $req->attributes->set($payloadAttribute, $payload);

        $merge = [];

        if (!empty($payload['fingerprint']) && !$req->filled('fingerprint')) {
            $merge['fingerprint'] = $payload['fingerprint'];
        }

        if (!empty($payload['tracking_event_ulid']) && !$req->filled('tracking_event_ulid')) {
            $merge['tracking_event_ulid'] = $payload['tracking_event_ulid'];
        }

        if (!empty($merge)) {
            $req->merge($merge);
        }
    }

    protected function synchronizeFingerprintTracking($req, array $payload): void
    {
        if (!config('invis.integrations.fingerprint_tracking.enabled', true)) {
            return;
        }

        $synchronizerClass = 'Dominservice\\FingerprintTracking\\Services\\TrackingEventSynchronizer';

        if (!class_exists($synchronizerClass) || !app()->bound($synchronizerClass)) {
            return;
        }

        app($synchronizerClass)->synchronizeProtectedRequest($req, $payload);
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
