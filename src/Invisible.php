<?php
namespace Dominservice\Invisible;

class Invisible
{
    /**
     * Generate the JavaScript code for Livewire forms
     *
     * @return string
     */
    public function livewireScript(): string
    {
        if (config('invis.skip_authenticated') && app()->bound('auth') && auth()->check()) {
            return '';
        }
        $cfg = json_encode([
            'dynamic_fields' => config('invis.dynamic_fields'),
            'polyfill_poison'=> config('invis.polyfill_poison'),
            'turnstile'      => config('invis.turnstile'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "
        <script>
            // Set global config for invis.js
            window.invisConfig = ".$cfg.";

            document.addEventListener('livewire:load', function() {
                // Initialize on page load
                initInvisForLivewire();

                // Listen for Livewire updates to re-initialize
                Livewire.hook('message.processed', (message, component) => {
                    initInvisForLivewire();
                });

                function initInvisForLivewire() {
                    window.invisCaptcha(window.invisConfig);
                }
            });
        </script>";
    }
}
