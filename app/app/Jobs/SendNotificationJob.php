<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;

final class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $payerId,
        public readonly int $payeeId,
        public readonly int $amountCents,
        public readonly string $transferId,
    ) {
    }

    public function handle(LoggerInterface $logger): void
    {
        $logger->info('Sending transfer notification', [
            'transfer_id' => $this->transferId,
            'payer_id' => $this->payerId,
            'payee_id' => $this->payeeId,
            'amount_cents' => $this->amountCents,
        ]);
    }
}
