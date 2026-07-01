<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\TransferStatus;
use App\Exceptions\NotificationException;
use App\Jobs\SendNotificationJob;
use App\Models\Transfer;
use App\Services\NotificationService;
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
        $job->handle(new NotificationService());

        $this->assertNotNull($transfer->fresh()?->notified_at);
    }

    public function test_it_throws_notification_exception_and_does_not_mark_notified_when_call_fails(): void
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

        try {
            $job->handle(new NotificationService());
            $this->fail('Expected NotificationException to be thrown.');
        } catch (NotificationException) {
            $this->assertNull($transfer->fresh()?->notified_at);
        }
    }

    public function test_it_skips_when_transfer_is_not_completed(): void
    {
        Http::fake();

        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Failed,
            'notified_at' => null,
        ]);

        $job = new SendNotificationJob($transfer->getKey());
        $job->handle(new NotificationService());

        $this->assertNull($transfer->fresh()?->notified_at);
        Http::assertNothingSent();
    }

    public function test_it_skips_when_already_notified(): void
    {
        Http::fake();

        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Completed,
            'notified_at' => now(),
        ]);

        $job = new SendNotificationJob($transfer->getKey());
        $job->handle(new NotificationService());

        Http::assertNothingSent();
    }

    public function test_it_logs_warning_when_transfer_not_found(): void
    {
        Http::fake();

        $job = new SendNotificationJob(99999);
        $job->handle(new NotificationService());

        Http::assertNothingSent();
    }
}
