<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\TransferStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Transfer;
use App\Models\User;
use App\Services\NotificationClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SendNotificationJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_marks_transfer_notified_when_notification_succeeds(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v1/notify' => Http::response(['status' => 'success'], 200),
        ]);

        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Completed,
            'notified_at' => null,
        ]);

        $job = new SendNotificationJob($transfer->getKey());
        $job->handle(new NotificationClient());

        $this->assertNotNull($transfer->fresh()?->notified_at);
    }

    public function test_it_does_not_mark_notified_when_notification_fails(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v1/notify' => Http::response(['status' => 'error'], 500),
        ]);

        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Completed,
            'notified_at' => null,
        ]);

        $job = new SendNotificationJob($transfer->getKey());
        $job->handle(new NotificationClient());

        $this->assertNull($transfer->fresh()?->notified_at);
    }
}
