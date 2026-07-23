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

            Route::post('/protected-route-with-payload', function (\Illuminate\Http\Request $request) {
                return response()->json([
                    'success' => true,
                    'fingerprint' => $request->input('fingerprint'),
                    'tracking_event_ulid' => $request->input('tracking_event_ulid'),
                    'payload' => $request->attributes->get('invis_payload'),
                ]);
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
    
    public function test_blade_directive_renders_correctly()
    {
        // For debugging
        $this->withoutExceptionHandling();
        
        $response = $this->get('/test-blade');
        
        $response->assertStatus(200);
        $response->assertSee('vendor/invis-captcha/invis.js', false);
        $response->assertSee('src="http://localhost/invis-captcha/pixel"', false);
        $response->assertDontSee('/invis-captcha/pixel?id=', false);
    }

    public function test_tracking_pixel_uses_a_stable_non_indexable_url()
    {
        $response = $this->get('/invis-captcha/pixel');

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/gif')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertSame('GIF89a', substr((string) $response->getContent(), 0, 6));
    }
    
    public function test_protected_route_rejects_requests_without_token()
    {
        $response = $this->post('/protected-route', [
            'name' => 'Test User'
        ]);
        
        $response->assertStatus(419);
    }
    
    public function test_protected_route_rejects_requests_with_invalid_token()
    {
        $response = $this->post('/protected-route', [
            'name' => 'Test User',
            'invis_token' => 'invalid-token'
        ]);
        
        $response->assertStatus(419);
    }
    
    public function test_protected_route_accepts_requests_with_valid_token()
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
    
    public function test_honey_field_rejects_bot_submissions()
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

    public function test_middleware_exposes_tracking_payload_on_request()
    {
        $payload = [
            'exp' => time() + 3600,
            'ip' => '127.0.0.1',
            'score' => 0.8,
            'fingerprint' => 'fp-invis-1',
            'tracking_event_ulid' => '01JZC9E6F2Q3T6Z8R1N4B7M9CC',
        ];

        $token = JWT::encode($payload, config('invis.secret'), 'HS256');

        $response = $this->post('/protected-route-with-payload', [
            'name' => 'Test User',
            'invis_token' => $token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'fingerprint' => 'fp-invis-1',
                'tracking_event_ulid' => '01JZC9E6F2Q3T6Z8R1N4B7M9CC',
            ])
            ->assertJsonPath('payload.fingerprint', 'fp-invis-1')
            ->assertJsonPath('payload.tracking_event_ulid', '01JZC9E6F2Q3T6Z8R1N4B7M9CC')
            ->assertJsonPath('payload.score', 0.8);
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
