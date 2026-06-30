<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\TransferPublisherInterface;
use App\Services\TransferMessageBuilder;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class KafkaProduceTransferCommand extends Command
{
    protected $signature = 'kafka:produce-transfer {payer_id} {payee_id} {amount} {--dry-run : Show envelope without publishing}';

    protected $description = 'Publish a manual transfer event to Kafka (local dev/test only)';

    public function __construct(
        private readonly TransferMessageBuilder $messageBuilder,
        private readonly TransferPublisherInterface $publisher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $payerId = (int) $this->argument('payer_id');
        $payeeId = (int) $this->argument('payee_id');
        $amountCents = (int) $this->argument('amount');
        $isDryRun = (bool) $this->option('dry-run');

        try {
            $this->validateArguments($payerId, $payeeId, $amountCents);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $transferId = 'txn_' . bin2hex(random_bytes(8));
        $payload = [
            'transfer_id' => $transferId,
            'payer_id' => $payerId,
            'payee_id' => $payeeId,
            'amount' => $amountCents,
            'occurred_at' => now()->toIso8601String(),
        ];

        $message = $this->messageBuilder->build($payload);

        if ($isDryRun) {
            $this->warn("[DRY-RUN] Would publish to topic [{$message['topic']}]");
            $this->warn("Key: {$message['key']}");
            $this->warn('Envelope: ' . json_encode($message['envelope'], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->publisher->publish($message['topic'], $message['envelope'], $message['key']);

        $this->info("Published transfer [{$transferId}] to topic [{$message['topic']}]");

        return self::SUCCESS;
    }

    private function validateArguments(int $payerId, int $payeeId, int $amountCents): void
    {
        if ($payerId <= 0 || $payeeId <= 0 || $amountCents <= 0) {
            throw new InvalidArgumentException('payer_id, payee_id, and amount must be positive integers.');
        }
    }
}
