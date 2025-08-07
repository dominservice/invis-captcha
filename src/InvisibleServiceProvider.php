<?php
namespace Dominservice\Invisible;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Illuminate\Support\Facades\Blade;

class InvisibleServiceProvider extends LaravelServiceProvider
{
    public function register() { }

    public function boot()
    {
        /* publishing files */
        $this->publishes([
            __DIR__.'/../config/invis.php' => config_path('invis.php'),
            __DIR__.'/../public'           => public_path('vendor/invis-captcha'),
            __DIR__.'/../resources/views'  => resource_path('views/vendor/invis'),
        ], 'invis');
        
        /* register middleware */
        $this->app['router']->aliasMiddleware('invis.verify', \Dominservice\Invisible\Middleware\Verify::class);

        /* directive @invisCaptcha */
        Blade::directive('invisCaptcha', function () {
            $cfg = json_encode([
                'dynamic_fields' => config('invis.dynamic_fields'),
                'polyfill_poison'=> config('invis.polyfill_poison'),
                'turnstile'      => config('invis.turnstile'),
            ]);
            $html =
                '<script defer src="'.asset('vendor/invis-captcha/invis.js').'"
                 data-cfg=\''.e($cfg).'\'></script>';

            if (config('invis.track_pixel.enabled')) {
                $html .= '<img src="'.url(config('invis.track_pixel.route'))
                    .'?id='. \Illuminate\Support\Str::uuid().'" width="1" height="1" style="display:none">';
            }
            if (config('invis.honey_field.enabled')) {
                $name = e(config('invis.honey_field.name'));
                $html .= '<input type="text" name="'.$name.'" style="display:none" tabindex="-1" autocomplete="off">';
            }
            return $html;
        });

        // ── AUTO-GENERATION of model.json ──────────────────────────────────
        $ml = config('invis.ml_model');

        if ($ml['enabled'] && ($ml['auto_generate'] ?? false)) {
            \Dominservice\Invisible\ML\ModelGenerator::ensure(
                $ml['path'],
                $ml['mode'] ?? 'thresholds'
            );
        }

        // ⇒ REGISTRATION of built-in Artisan command
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Dominservice\Invisible\Console\Commands\GenerateModel::class,
            ]);
        }
    }
}
