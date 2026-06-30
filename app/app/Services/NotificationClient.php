<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class NotificationClient
{
    private const string NOTIFY_URL = 'https://util.devi.tools/api/v1/notify';

    private const int TIMEOUT_SECONDS = 10;

    public function notify(): bool
    {
        $url = config('services.notifier.url', self::NOTIFY_URL);
        $timeout = (int) config('services.notifier.timeout', self::TIMEOUT_SECONDS);

        try {
            $response = Http::timeout($timeout)
                ->get($url);

            return $response->successful() && $response->json('status') === 'success';
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Notification service unavailable: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }
    }
}
