<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AuthorizerClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AuthorizerClientTest extends TestCase
{
    public function test_returns_true_when_authorizer_responds_2xx(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true],
            ], 200),
        ]);

        $client = new AuthorizerClient();

        $this->assertTrue($client->authorize());
    }

    public function test_returns_false_when_authorizer_responds_4xx(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([], 403),
        ]);

        $client = new AuthorizerClient();

        $this->assertFalse($client->authorize());
    }

    public function test_returns_false_when_authorizer_responds_5xx(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([], 500),
        ]);

        $client = new AuthorizerClient();

        $this->assertFalse($client->authorize());
    }

    public function test_retries_on_failed_connection_and_succeeds(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::sequence()
                ->pushFailedConnection('timeout')
                ->push([
                    'data' => ['authorization' => true],
                ]),
        ]);

        $client = new AuthorizerClient();

        $this->assertTrue($client->authorize());
    }

    public function test_does_not_retry_on_4xx_response(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([], 403),
        ]);

        $client = new AuthorizerClient();

        $this->assertFalse($client->authorize());

        Http::assertSentCount(1);
    }

    public function test_does_not_retry_on_5xx_response(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([], 500),
        ]);

        $client = new AuthorizerClient();

        $this->assertFalse($client->authorize());

        Http::assertSentCount(1);
    }

    public function test_returns_false_after_retries_exhausted_on_connection_exception(): void
    {
        $calls = 0;
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => function () use (&$calls): never {
                ++$calls;

                throw new ConnectionException('timeout');
            },
        ]);

        $client = new AuthorizerClient();

        $this->assertFalse($client->authorize());
        $this->assertSame(2, $calls);
    }

    public function test_returns_false_on_request_exception(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => fn (): never => throw new RequestException(
                new \Illuminate\Http\Client\Response(
                    new \GuzzleHttp\Psr7\Response(500),
                ),
            ),
        ]);

        $client = new AuthorizerClient();

        $this->assertFalse($client->authorize());
    }
}
