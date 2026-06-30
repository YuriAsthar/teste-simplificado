<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class AuthorizerClient
{
    private const string AUTHORIZE_URL = 'https://util.devi.tools/api/v2/authorize';

    private const int TIMEOUT_SECONDS = 10;

    /**
     * @var array<int, int>
     */
    private const array RETRY_BACKOFF_MS = [2000];

    public function authorize(): bool
    {
        $url = config('services.authorizer.url', self::AUTHORIZE_URL);
        $timeout = (int) config('services.authorizer.timeout', self::TIMEOUT_SECONDS);

        try {
            $response = Http::timeout($timeout)
                ->retry(self::RETRY_BACKOFF_MS, 0, static function ($exception): bool {
                    return $exception instanceof ConnectionException;
                })
                ->get($url);

            return $response->successful() && $response->json('data.authorization') === true;
        } catch (ConnectionException) {
            return false;
        } catch (RequestException) {
            return false;
        }
    }
}
