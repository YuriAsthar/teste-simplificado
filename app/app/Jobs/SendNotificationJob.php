<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Transfer;
use App\Services\NotificationClient;
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

    public function __construct(
        public readonly int $transferId,
    ) {
    }

    public function handle(NotificationClient $client): void
    {
        $transfer = Transfer::query()
            ->with(['payer.wallet', 'payee.wallet'])
            ->find($this->transferId);

        if (is_null($transfer)) {
            Log::warning('Transfer not found for notification.', [
                'transfer_id' => $this->transferId,
            ]);

            return;
        }

        if ($client->notify()) {
            $transfer->forceFill(['notified_at' => now()])->save();

            return;
        }

        Log::warning('Notification service returned non-success.', [
            'transfer_id' => $this->transferId,
        ]);
    }
}
