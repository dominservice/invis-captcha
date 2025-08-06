<?php

namespace Dominservice\Invisible\Tests\Feature;

use Dominservice\Invisible\Tests\TestCase;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Route;

class InvisibleCaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Define test routes
        Route::middleware('web')->group(function () {
            Route::get('/test-blade', function () {
                return view('test-blade');
            });
            
            Route::post('/protected-route', function () {
                return response()->json(['success' => true]);
            })->middleware('invis.verify');
        });
        
        // Create test blade view
        if (!is_dir(base_path('resources/views'))) {
            mkdir(base_path('resources/views'), 0755, true);
        }
        
        $viewContent = <<<'EOT'
<!DOCTYPE html>
<html>
<head>
    <title>Test Invisible Captcha</title>
</head>
<body>
    <form method="POST" action="/protected-route">
        @csrf
        @invisCaptcha
        <input type="text" name="name" value="Test User">
        <button type="submit">Submit</button>
    </form>
</body>
</html>
EOT;
        
        file_put_contents(base_path('resources/views/test-blade.blade.php'), $viewContent);
    }
    
    /** @test */
    public function blade_directive_renders_correctly()
    {
        // For debugging
        $this->withoutExceptionHandling();
        
        $response = $this->get('/test-blade');
        
        $response->assertStatus(200);
        $response->assertSee('vendor/invis-captcha/invis.js', false);
    }
    
    /** @test */
    public function protected_route_rejects_requests_without_token()
    {
        $response = $this->post('/protected-route', [
            'name' => 'Test User'
        ]);
        
        $response->assertStatus(419);
    }
    
    /** @test */
    public function protected_route_rejects_requests_with_invalid_token()
    {
        $response = $this->post('/protected-route', [
            'name' => 'Test User',
            'invis_token' => 'invalid-token'
        ]);
        
        $response->assertStatus(419);
    }
    
    /** @test */
    public function protected_route_accepts_requests_with_valid_token()
    {
        // For debugging
        $this->withoutExceptionHandling();
        
        $payload = [
            'exp' => time() + 3600,
            'ip' => '127.0.0.1',
            'score' => 0.8
        ];
        
        $token = JWT::encode($payload, config('invis.secret'), 'HS256');
        
        $response = $this->post('/protected-route', [
            'name' => 'Test User',
            'invis_token' => $token
        ]);
        
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
    
    /** @test */
    public function honey_field_rejects_bot_submissions()
    {
        $payload = [
            'exp' => time() + 3600,
            'ip' => '127.0.0.1',
            'score' => 0.8
        ];
        
        $token = JWT::encode($payload, config('invis.secret'), 'HS256');
        
        $response = $this->post('/protected-route', [
            'name' => 'Test User',
            'invis_token' => $token,
            'website' => 'spam' // Honey field is filled
        ]);
        
        $response->assertStatus(419);
    }
    
    protected function tearDown(): void
    {
        // Clean up test view
        if (file_exists(base_path('resources/views/test-blade.blade.php'))) {
            unlink(base_path('resources/views/test-blade.blade.php'));
        }
        
        parent::tearDown();
    }
}