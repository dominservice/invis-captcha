<?php

namespace Dominservice\Invisible\Tests\Unit\Middleware;

use Dominservice\Invisible\Middleware\Verify;
use Dominservice\Invisible\Tests\TestCase;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerifyTest extends TestCase
{
    protected $middleware;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new Verify();
    }
    
    /** @test */
    public function it_aborts_when_honey_field_is_filled()
    {
        $request = new Request();
        $request->merge(['website' => 'spam']);
        
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Bot (honey field)');
        
        $this->middleware->handle($request, function() {
            return new Response();
        });
    }
    
    /** @test */
    public function it_aborts_when_no_token_is_provided()
    {
        $request = new Request();
        
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Brak tokenu');
        
        $this->middleware->handle($request, function() {
            return new Response();
        });
    }
    
    /** @test */
    public function it_aborts_when_token_is_invalid()
    {
        $request = new Request();
        $request->merge(['invis_token' => 'invalid-token']);
        
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Token nieważny');
        
        $this->middleware->handle($request, function() {
            return new Response();
        });
    }
    
    /** @test */
    public function it_aborts_when_token_is_expired()
    {
        $payload = [
            'exp' => time() - 3600, // Expired 1 hour ago
            'ip' => '127.0.0.1',
            'score' => 0.8
        ];
        
        $token = JWT::encode($payload, config('invis.secret'), 'HS256');
        
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('input')->with('invis_token')->andReturn($token);
        $request->shouldReceive('ip')->andReturn('127.0.0.1');
        $request->shouldReceive('filled')->andReturn(false);
        
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Token nieważny');
        
        $this->middleware->handle($request, function() {
            return new Response();
        });
    }
    
    /** @test */
    public function it_aborts_when_ip_doesnt_match()
    {
        $payload = [
            'exp' => time() + 3600, // Valid for 1 hour
            'ip' => '192.168.1.1', // Different IP
            'score' => 0.8
        ];
        
        $token = JWT::encode($payload, config('invis.secret'), 'HS256');
        
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('input')->with('invis_token')->andReturn($token);
        $request->shouldReceive('ip')->andReturn('127.0.0.1');
        $request->shouldReceive('filled')->andReturn(false);
        
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Podejrzane');
        
        $this->middleware->handle($request, function() {
            return new Response();
        });
    }
    
    /** @test */
    public function it_aborts_when_score_is_below_threshold()
    {
        $payload = [
            'exp' => time() + 3600, // Valid for 1 hour
            'ip' => '127.0.0.1',
            'score' => 0.3 // Below default threshold of 0.5
        ];
        
        $token = JWT::encode($payload, config('invis.secret'), 'HS256');
        
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('input')->with('invis_token')->andReturn($token);
        $request->shouldReceive('ip')->andReturn('127.0.0.1');
        $request->shouldReceive('filled')->andReturn(false);
        
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Podejrzane');
        
        $this->middleware->handle($request, function() {
            return new Response();
        });
    }
    
    /** @test */
    public function it_allows_valid_requests()
    {
        $payload = [
            'exp' => time() + 3600, // Valid for 1 hour
            'ip' => '127.0.0.1',
            'score' => 0.8 // Above threshold
        ];
        
        $token = JWT::encode($payload, config('invis.secret'), 'HS256');
        
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('input')->with('invis_token')->andReturn($token);
        $request->shouldReceive('ip')->andReturn('127.0.0.1');
        $request->shouldReceive('filled')->andReturn(false);
        $request->shouldReceive('all')->andReturn([]);
        $request->shouldReceive('merge')->andReturn(null);
        
        $response = new Response('OK');
        $next = function() use ($response) {
            return $response;
        };
        
        $result = $this->middleware->handle($request, $next);
        $this->assertSame($response, $result);
    }
    
    /** @test */
    public function it_normalizes_dynamic_fields()
    {
        $payload = [
            'exp' => time() + 3600,
            'ip' => '127.0.0.1',
            'score' => 0.8
        ];
        
        $token = JWT::encode($payload, config('invis.secret'), 'HS256');
        
        // Mock the DynamicFields helper
        $this->mock('alias:Dominservice\Invisible\Helpers\DynamicFields', function ($mock) {
            $mock->shouldReceive('original')
                ->with('email_xyz')
                ->andReturn('email');
        });
        
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('input')->with('invis_token')->andReturn($token);
        $request->shouldReceive('ip')->andReturn('127.0.0.1');
        $request->shouldReceive('filled')->andReturn(false);
        $request->shouldReceive('all')->andReturn(['email_xyz' => 'test@example.com']);
        $request->shouldReceive('merge')->with(['email' => 'test@example.com'])->once();
        
        $response = new Response('OK');
        $next = function() use ($response) {
            return $response;
        };
        
        $result = $this->middleware->handle($request, $next);
        $this->assertSame($response, $result);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}