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

        Log::info('Dispatching notification.', [
            'transfer_id' => $transfer->id,
            'payee_id' => $transfer->payee_id,
        ]);

        $payload = [
            'user' => $transfer->payee->email ?? (string) $transfer->payee_id,
            'message' => "Transfer #{$transfer->id} received.",
            'transfer_id' => $transfer->id,
            'amount' => (int) $transfer->getRawOriginal('amount'),
        ];

        try {
            $response = Http::timeout($timeout)->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Notification service unreachable.', [
                'transfer_id' => $transfer->id,
                'exception' => $exception->getMessage(),
            ]);

            throw new NotificationException(
                'Notification service unreachable: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }

        if (!$response->successful()) {
            Log::warning('Notification service returned non-success status.', [
                'transfer_id' => $transfer->id,
                'http_status' => $response->status(),
            ]);

            throw new NotificationException(
                'Notification service returned non-success: HTTP ' . $response->status(),
                $response->status(),
            );
        }

        if ($response->body() !== '' && $response->json('status') !== 'success') {
            Log::warning('Notification service returned non-success body status.', [
                'transfer_id' => $transfer->id,
                'http_status' => $response->status(),
                'status' => $response->json('status'),
            ]);

            throw new NotificationException(
                'Notification service returned non-success status: ' . $response->json('status'),
                $response->status(),
            );
        }

        Log::info('Notification dispatched successfully.', [
            'transfer_id' => $transfer->id,
            'payee_id' => $transfer->payee_id,
        ]);
    }
}
