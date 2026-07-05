<?php

namespace Dominservice\Invisible\Tests\Unit\Middleware;

use Dominservice\Invisible\Helpers\DynamicFields;
use Dominservice\Invisible\Middleware\Verify;
use Dominservice\Invisible\Tests\TestCase;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerifyTest extends TestCase
{
    protected Verify $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new Verify();
    }

    public function test_it_aborts_when_honey_field_is_filled()
    {
        $request = Request::create('/submit', 'POST', ['website' => 'spam']);

        $this->expectHttpException(__('invis::errors.honey_field'));

        $this->middleware->handle($request, fn () => new Response());
    }

    public function test_it_aborts_when_no_token_is_provided()
    {
        $request = Request::create('/submit', 'POST');

        $this->expectHttpException(__('invis::errors.missing_token'));

        $this->middleware->handle($request, fn () => new Response());
    }

    public function test_it_aborts_when_token_is_invalid()
    {
        $request = Request::create('/submit', 'POST', [
            'invis_token' => 'invalid-token',
        ]);

        $this->expectHttpException('Wrong number of segments');

        $this->middleware->handle($request, fn () => new Response());
    }

    public function test_it_aborts_when_token_is_expired()
    {
        $request = Request::create('/submit', 'POST', [
            'invis_token' => $this->makeToken([
                'exp' => time() - 3600,
                'ip' => '127.0.0.1',
                'score' => 0.8,
            ]),
        ], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $this->expectHttpException(__('invis::errors.token_expired'));

        $this->middleware->handle($request, fn () => new Response());
    }

    public function test_it_aborts_when_ip_doesnt_match()
    {
        $request = Request::create('/submit', 'POST', [
            'invis_token' => $this->makeToken([
                'exp' => time() + 3600,
                'ip' => '192.168.1.1',
                'score' => 0.8,
            ]),
        ], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $this->expectHttpException('IP address mismatch');

        $this->middleware->handle($request, fn () => new Response());
    }

    public function test_it_aborts_when_score_is_below_threshold()
    {
        $request = Request::create('/submit', 'POST', [
            'invis_token' => $this->makeToken([
                'exp' => time() + 3600,
                'ip' => '127.0.0.1',
                'score' => 0.3,
            ]),
        ], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $this->expectHttpException('Suspicious activity detected');

        $this->middleware->handle($request, fn () => new Response());
    }

    public function test_it_allows_valid_requests()
    {
        $request = Request::create('/submit', 'POST', [
            'invis_token' => $this->makeToken([
                'exp' => time() + 3600,
                'ip' => '127.0.0.1',
                'score' => 0.8,
            ]),
        ], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $response = new Response('OK');

        $result = $this->middleware->handle($request, fn () => $response);

        $this->assertSame($response, $result);
    }

    public function test_it_normalizes_dynamic_fields()
    {
        $dynamicField = DynamicFields::map('email');

        $request = Request::create('/submit', 'POST', [
            'invis_token' => $this->makeToken([
                'exp' => time() + 3600,
                'ip' => '127.0.0.1',
                'score' => 0.8,
            ]),
            $dynamicField => 'test@example.com',
        ], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $response = new Response('OK');

        $result = $this->middleware->handle($request, fn () => $response);

        $this->assertSame($response, $result);
        $this->assertSame('test@example.com', $request->input('email'));
        $this->assertNull($request->input($dynamicField));
    }

    protected function expectHttpException(string $message): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($message);
    }

    protected function makeToken(array $payload): string
    {
        return JWT::encode($payload, config('invis.secret'), 'HS256');
    }
}
