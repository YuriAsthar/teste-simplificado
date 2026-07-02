<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\NotificationException;
use App\Models\Transfer;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function test_it_sends_notification_with_success_response(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success'], 200),
        ]);

        /** @var User $payee */
        $payee = User::factory()->create();

        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create([
            'payee_id' => $payee->id,
            'amount' => 2500,
            'status' => 'completed',
        ]);

        $service = new NotificationService();
        $service->notifyTransfer($transfer);

        Http::assertSent(static function ($request) use ($payee, $transfer): bool {
            return $request->url() === 'https://util.devi.tools/api/v1/notify'
                && $request->method() === 'POST'
                && $request['user'] === $payee->email
                && $request['transfer_id'] === $transfer->id
                && $request['amount'] === 2500
                && $request['message'] === "Transfer #{$transfer->id} received.";
        });
    }

    public function test_it_accepts_204_no_content_as_success(): void
    {
        Http::fake([
            '*' => Http::response('', 204),
        ]);

        /** @var User $payee */
        $payee = User::factory()->create();

        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create([
            'payee_id' => $payee->id,
            'amount' => 2500,
            'status' => 'completed',
        ]);

        $service = new NotificationService();
        $service->notifyTransfer($transfer);

        Http::assertSent(static function ($request) use ($payee, $transfer): bool {
            return $request['user'] === $payee->email
                && $request['transfer_id'] === $transfer->id;
        });
    }

    public function test_it_throws_exception_when_response_status_is_error(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'error'], 500),
        ]);

        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create();

        $this->expectException(NotificationException::class);
        $this->expectExceptionMessage('Notification service returned non-success: HTTP 500');

        (new NotificationService())->notifyTransfer($transfer);
    }

    public function test_it_throws_exception_when_response_json_status_is_not_success(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'fail'], 200),
        ]);

        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create();

        $this->expectException(NotificationException::class);
        $this->expectExceptionMessage('Notification service returned non-success status: fail');

        (new NotificationService())->notifyTransfer($transfer);
    }

    public function test_it_throws_exception_on_connection_failure(): void
    {
        Http::fake([
            '*' => function (): void {
                throw new ConnectionException('Connection refused');
            },
        ]);

        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create();

        $this->expectException(NotificationException::class);
        $this->expectExceptionMessage('Notification service unreachable: Connection refused');

        (new NotificationService())->notifyTransfer($transfer);
    }

    public function test_it_falls_back_to_payee_id_when_payee_email_is_missing(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success'], 200),
        ]);

        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create([
            'payee_id' => 99999,
            'amount' => 1000,
            'status' => 'completed',
        ]);

        $service = new NotificationService();
        $service->notifyTransfer($transfer);

        Http::assertSent(static function ($request) use ($transfer): bool {
            return $request['user'] === '99999'
                && $request['transfer_id'] === $transfer->id;
        });
    }
}
