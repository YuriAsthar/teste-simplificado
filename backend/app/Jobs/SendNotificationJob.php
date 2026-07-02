<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TransferStatus;
use App\Exceptions\NotificationException;
use App\Models\Transfer;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $transferId)
    {
        $this->onConnection('rabbitmq');
    }

    public function handle(NotificationService $notificationService): void
    {
        $transfer = Transfer::query()->with('payee')->find($this->transferId);

        if (is_null($transfer)) {
            Log::warning('Transfer not found for notification.', ['transfer_id' => $this->transferId]);

            return;
        }

        if (!is_null($transfer->notified_at)) {
            Log::info('Notification already sent; skipping.', ['transfer_id' => $this->transferId]);

            return;
        }

        if ($transfer->status !== TransferStatus::Completed) {
            Log::info('Notification skipped; transfer is not completed.', [
                'transfer_id' => $this->transferId,
                'status' => $transfer->status->value,
            ]);

            return;
        }

        try {
            $notificationService->notifyTransfer($transfer);
        } catch (NotificationException $exception) {
            Log::warning('Notification attempt failed; will retry if attempts remain.', [
                'transfer_id' => $this->transferId,
                'attempt' => $this->attempts(),
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('Notification sent successfuly, updating notified_at column to now', [
            'transfer_id' => $this->transferId,
        ]);

        $transfer->forceFill(['notified_at' => now()])->save();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Notification job failed permanently after retries.', [
            'transfer_id' => $this->transferId,
            'job_id' => $this->job?->getJobId(),
            'exception' => $exception->getMessage(),
        ]);
    }
}
