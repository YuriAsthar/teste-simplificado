<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\TransferStatus;
use App\Jobs\SendTransferNotificationJob;
use App\Models\Transfer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class RetryNotificationsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_dispatches_notification_jobs_for_pending_notifications(): void
    {
        Queue::fake();

        $completedTransfer = Transfer::factory()->create([
            'status' => TransferStatus::Completed,
            'notified_at' => null,
        ]);

        Transfer::factory()->create([
            'status' => TransferStatus::Completed,
            'notified_at' => now(),
        ]);

        Transfer::factory()->create([
            'status' => TransferStatus::Failed,
            'notified_at' => null,
        ]);

        $this->artisan('notifications:retry')
            ->assertSuccessful()
            ->expectsOutput('Dispatched 1 notification retry jobs.');

        Queue::assertPushed(SendTransferNotificationJob::class, function ($job) use ($completedTransfer): bool {
            return $job->transferId === $completedTransfer->getKey();
        });
    }
}
