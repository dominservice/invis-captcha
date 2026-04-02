<?php

namespace Dominservice\Invisible\Tests\Feature;

use Dominservice\Invisible\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

class InvisibleLivewireTest extends TestCase
{
    public function test_it_renders_livewire_directive()
    {
        $this->app->singleton('invis', function () {
            return new \Dominservice\Invisible\Invisible();
        });

        $output = Blade::render('@invisLivewire');
        
        // Check if the script contains Livewire-specific code
        $this->assertStringContainsString('livewire:load', $output);
        $this->assertStringContainsString('Livewire.hook', $output);
        $this->assertStringContainsString('window.invisConfig', $output);
        $this->assertStringContainsString('initInvisForLivewire', $output);
        $this->assertStringContainsString('window.invisCaptcha(window.invisConfig)', $output);
    }

    public function test_it_generates_livewire_script()
    {
        $invisible = new \Dominservice\Invisible\Invisible();
        $script = $invisible->livewireScript();
        
        // Check if the script contains the expected elements
        $this->assertStringContainsString('livewire:load', $script);
        $this->assertStringContainsString('Livewire.hook', $script);
        $this->assertStringContainsString('window.invisConfig', $script);
        $this->assertStringContainsString('initInvisForLivewire', $script);
        $this->assertStringContainsString('window.invisCaptcha(window.invisConfig)', $script);
    }
}
