<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotificationException;
use App\Models\Transfer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class NotificationService
{
    public function notifyTransfer(Transfer $transfer): void
    {
        $url = (string) config('services.notifier.url', 'https://util.devi.tools/api/v1/notify');
        $timeout = (int) config('services.notifier.timeout', 10);

        $payload = [
            'user' => $transfer->payee->email ?? (string) $transfer->payee_id,
            'message' => "Transfer #{$transfer->id} received.",
            'transfer_id' => $transfer->id,
            'amount' => (int) $transfer->getRawOriginal('amount'),
        ];

        try {
            $response = Http::timeout($timeout)->post($url, $payload);
        } catch (ConnectionException $exception) {
            throw new NotificationException(
                'Notification service unreachable: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }

        if (!$response->successful()) {
            throw new NotificationException(
                'Notification service returned non-success: HTTP ' . $response->status(),
                $response->status(),
            );
        }

        if ($response->body() !== '' && $response->json('status') !== 'success') {
            throw new NotificationException(
                'Notification service returned non-success status: ' . $response->json('status'),
                $response->status(),
            );
        }

        Log::info('Notification service returned success: HTTP ' . $response->status(), $payload);
    }
}
