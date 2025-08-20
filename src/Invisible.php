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
            window.invisConfig = ".e($cfg).";
            
            document.addEventListener('livewire:load', function() {
                // Initialize on page load
                initInvisForLivewire();
                
                // Listen for Livewire updates to re-initialize
                Livewire.hook('message.processed', (message, component) => {
                    initInvisForLivewire();
                });
                
                function initInvisForLivewire() {
                    // Add data-invis attribute to Livewire forms if not already present
                    document.querySelectorAll('form[wire\\\\:submit], form[wire\\\\:submit\\.prevent], form[wire\\\\:submit\\.debounce], form[wire\\\\:submit\\.throttle], form[wire\\\\:model]').forEach(form => {
                        if (!form.hasAttribute('data-invis')) {
                            form.setAttribute('data-invis', '');
                        }
                    });
                    
                    // Always load a fresh instance of invis.js to ensure it runs with the latest forms
                    // Remove any existing dynamically added invis scripts (not the original one)
                    document.querySelectorAll('script.invis-dynamic').forEach(script => {
                        script.remove();
                    });
                    
                    // Create and add a new script element
                    const script = document.createElement('script');
                    script.className = 'invis-dynamic';
                    script.src = '".asset('vendor/invis-captcha/invis.js')."';
                    
                    // Execute immediately without defer to ensure it runs right away
                    document.head.appendChild(script);
                }
            });
        </script>";
    }
}