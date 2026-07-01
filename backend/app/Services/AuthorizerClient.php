<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuthorizerResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuthorizerClient
{
    private const string AUTHORIZE_URL = 'https://util.devi.tools/api/v2/authorize';

    private const int TIMEOUT_SECONDS = 10;

    /**
     * @var array<int, int>
     */
    private const array RETRY_BACKOFF_MS = [2000];

    public function authorize(): AuthorizerResult
    {
        $url = config('services.authorizer.url', self::AUTHORIZE_URL);
        $timeout = (int) config('services.authorizer.timeout', self::TIMEOUT_SECONDS);

        try {
            $response = Http::timeout($timeout)
                ->retry(self::RETRY_BACKOFF_MS, 0, static function ($exception): bool {
                    return $exception instanceof ConnectionException;
                })
                ->get($url);

            if ($response->serverError() || $response->status() === HttpResponse::HTTP_SERVICE_UNAVAILABLE) {
                return AuthorizerResult::Transient;
            }

            if ($response->successful() && $response->json('data.authorization') === true) {
                return AuthorizerResult::Authorized;
            }

            return AuthorizerResult::Rejected;
        } catch (ConnectionException) {
            return AuthorizerResult::Transient;
        } catch (RequestException $exception) {
            $response = $exception->response;

            if (!is_null($response) && ($response->serverError() || $response->status() === HttpResponse::HTTP_SERVICE_UNAVAILABLE)) {
                return AuthorizerResult::Transient;
            }

            return AuthorizerResult::Rejected;
        }
    }
}
