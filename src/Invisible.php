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
        $cfg = json_encode([
            'dynamic_fields' => config('invis.dynamic_fields'),
            'polyfill_poison'=> config('invis.polyfill_poison'),
            'turnstile'      => config('invis.turnstile'),
        ]);

        return "
        <script>
            // Set global config for invis.js
            window.invisConfig = '".e($cfg)."';
            
            document.addEventListener('livewire:load', function() {
                // Initialize on page load
                initInvisForLivewire();
                
                // Listen for Livewire updates to re-initialize
                Livewire.hook('message.processed', (message, component) => {
                    initInvisForLivewire();
                });
                
                function initInvisForLivewire() {
                    // Add data-invis attribute to Livewire forms if not already present
                    document.querySelectorAll('form[wire\\\\:submit]').forEach(form => {
                        if (!form.hasAttribute('data-invis')) {
                            form.setAttribute('data-invis', '');
                        }
                    });
                    
                    // Check if invis.js is already loaded
                    if (!document.querySelector('script[src*=\"invis.js\"]')) {
                        const script = document.createElement('script');
                        script.defer = true;
                        script.src = '".asset('vendor/invis-captcha/invis.js')."';
                        document.head.appendChild(script);
                    } else {
                        // If script is already loaded, we need to re-run it for newly added forms
                        // This will create a new instance that will use window.invisConfig
                        const existingScript = document.querySelector('script[src*=\"invis.js\"]');
                        const newScript = document.createElement('script');
                        newScript.defer = true;
                        newScript.src = existingScript.src;
                        document.head.appendChild(newScript);
                    }
                }
            });
        </script>";
    }
}