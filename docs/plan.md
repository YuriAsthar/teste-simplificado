# Technical Plan: Transactional Outbox, Kafka Event Rename, and Idempotency Alignment

## Changes from previous refined plan

1. **Outbox unique index**: Changed from a partial unique index on `(aggregate_type, aggregate_id, event_type) WHERE status = 'Completed'` (which used a non-existent status) to a **simple unique index on `(aggregate_type, aggregate_id, event_type)`**. Rationale: this enforces exactly one logical event of each type per aggregate; application-level `status` remains the source of truth for publish state.

2. **Migration DDL style**: All index creation now uses `Schema::index()` / `Schema::unique()` via Laravel Blueprint instead of raw `DB::statement(...)` with placeholders.

3. **Outbox `markFailed()`**: Explicitly sets `status = 'Failed'` while incrementing `attempts` and updating `last_error_at = now()`. Retryability is determined entirely by the `pending()` scope (`attempts < max_attempts` and `last_error_at` older than retry interval). Since new outbox rows are created as `Pending`, the first failure must flip the status to `Failed` so the row is no longer picked up by a naive `status = 'Pending'` filter.

4. **Concurrency protection for `outbox:publish`**: The command must not run concurrently in production. The scheduler entry uses `WithoutOverlapping` middleware, and the plan documents that multiple instances should not run the command simultaneously. For the technical test this is acceptable; a production deployment can additionally wrap the batch loop in a PostgreSQL advisory lock or database-level lock. Running two instances concurrently could double-publish events and waste Kafka broker/consumer capacity.

5. **Idempotency cached response replay**: `IdempotencyKeyService` records `endpoint`, `request_hash`, `response_status`, and `response_body` on first completion. On replay with a completed key whose `request_hash` matches, the service returns the cached HTTP response (`status` + `body`) instead of re-executing the transfer. `TransferController` short-circuits and returns the cached response directly, so repeated requests with the same key and payload receive the exact same status and body without touching the domain layer.

6. **Kafka consumer bridge deduplication**: Explicitly documented that the existing Redis `kafka:transfer:{transfer_id}` guard (with configurable TTL, default 3600s) is a **pre-dispatch** guard. `notified_at` in `SendNotificationJob` remains the final guard. The consumer uses real integer `transfer_id` from the Kafka payload and marks the message processed (to avoid endless redelivery) when the transfer is missing or not completed.

7. **Idempotency keys migration completeness**: Consolidated into a single migration `2026_07_02_300000_align_idempotency_keys_to_plan.php` that adds ALL missing columns (`endpoint`, `request_hash`, `response_status`, `response_body`) and backfills `request_hash` from existing `fingerprint` values.

---

## Summary

Replace the direct `SendNotificationJob` dispatch in `WalletTransferService` with a **transactional outbox** pattern. After a transfer completes, write an `OutboxEvent` record in the same database transaction. A scheduled `outbox:publish` command polls pending outbox events and publishes them to Kafka with the real `transfer.id`. Rename the Kafka event from `transfer.authorized` to `transfer.completed`. The Kafka consumer bridge dispatches `SendNotificationJob` via RabbitMQ, guarded by a Redis idempotency key. Align the `idempotency_keys` table with new columns (`endpoint`, `request_hash`, `response_status`, `response_body`) and return cached `response_body` + `response_status` on replay. Update HTTP status codes: 422 for authorizer rejection, 403 for identity mismatch, 503 for transient authorizer errors. Remove synthetic `txn_*` IDs; use real `transfer.id` throughout. The `outbox:publish` scheduler entry uses `WithoutOverlapping` to prevent concurrent runs.

---

## Phases

### Phase 1: Schema — Outbox table and Idempotency key alignment

**Objective:** Create the `outbox_events` table and align `idempotency_keys` with all required columns.

#### Files involved

| Path | Current purpose | Identified problem | Proposed change |
|------|-----------------|-------------------|-----------------|
| `database/migrations/2026_07_02_200000_create_outbox_events_table.php` | (new) | Outbox table missing | Create table with `aggregate_type`, `aggregate_id`, `event_type`, `payload`, `status` (`Pending`/`Published`/`Failed`), `attempts`, `last_error_at`, and a **simple unique index** on `(aggregate_type, aggregate_id, event_type)` via Blueprint |
| `database/migrations/2026_07_02_300000_align_idempotency_keys_to_plan.php` | (new) | Idempotency keys missing `endpoint`, `request_hash`, `response_status`, `response_body` | One migration that adds all four columns and backfills `request_hash` from existing `fingerprint` values |

#### Reference code — Outbox migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->string('aggregate_type');
            $table->unsignedBigInteger('aggregate_id');
            $table->string('event_type');
            $table->json('payload');
            $table->string('status')->default('Pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();

            // Simple unique index — enforces one logical event per aggregate/type.
            // Status is the application-level source of truth for publish state.
            $table->unique(['aggregate_type', 'aggregate_id', 'event_type']);
            $table->index(['status', 'created_at']);
            $table->index(['status', 'attempts', 'last_error_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
```

#### Reference code — Idempotency keys alignment migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->string('endpoint')->nullable();
            $table->string('request_hash')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
        });

        // Backfill request_hash from existing fingerprint values
        DB::table('idempotency_keys')
            ->whereNotNull('fingerprint')
            ->update(['request_hash' => DB::raw('fingerprint')]);
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropColumn(['endpoint', 'request_hash', 'response_status', 'response_body']);
        });
    }
};
```

**Note:** The migration leaves the legacy `fingerprint` column in place for rollback safety. A future migration can drop `fingerprint` once the new code has been verified in production. `IdempotencyKey` exposes a `fingerprint` accessor that returns `request_hash` so existing code and tests continue to work during the transition.

---

### Phase 2: Outbox model and publish command

**Objective:** Implement the `OutboxEvent` model with `markFailed()`, `markPublished()`, `pending()` scope, and the `outbox:publish` artisan command.

#### Files involved

| Path | Current purpose | Identified problem | Proposed change |
|------|-----------------|-------------------|-----------------|
| `app/Models/OutboxEvent.php` | (new) | No model exists | Create Eloquent model with scopes, mutators, and retry-safe state transitions |
| `app/Console/Commands/PublishOutboxEventsCommand.php` | (new) | No command exists | Create scheduled command that queries `pending()` events, publishes to Kafka, and marks `Published` or `Failed` |
| `config/outbox.php` | (new) | No config exists | Add `max_attempts` (default 3) and `retry_interval_seconds` (default 300) |

#### Reference code — OutboxEvent model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

final class OutboxEvent extends Model
{
    /** @use HasFactory<\Database\Factories\OutboxEventFactory> */
    use HasFactory;

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'last_error_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'last_error_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        $maxAttempts = (int) config('outbox.max_attempts', 3);
        $retryInterval = (int) config('outbox.retry_interval_seconds', 300);

        return $query->whereIn('status', ['Pending', 'Failed'])
            ->where('attempts', '<', $maxAttempts)
            ->where(function (Builder $q) use ($retryInterval): void {
                $q->whereNull('last_error_at')
                  ->orWhere('last_error_at', '<=', now()->subSeconds($retryInterval));
            });
    }

    public function markPublished(): void
    {
        $this->update(['status' => 'Published']);
    }

    public function markFailed(): void
    {
        $this->increment('attempts');
        $this->update([
            'status' => 'Failed',
            'last_error_at' => now(),
        ]);
        // retryability is determined by pending() scope
    }
}
```

#### Reference code — PublishOutboxEventsCommand

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\TransferPublisherInterface;
use App\Models\OutboxEvent;
use App\Services\TransferMessageBuilder;
use Illuminate\Console\Command;
use Throwable;

final class PublishOutboxEventsCommand extends Command
{
    protected $signature = 'outbox:publish {--batch=100 : Number of events to process per run}';
    protected $description = 'Publish pending outbox events to Kafka';

    public function __construct(
        private TransferPublisherInterface $publisher,
        private TransferMessageBuilder $messageBuilder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $events = OutboxEvent::pending()
            ->orderBy('created_at')
            ->limit($batchSize)
            ->get();

        foreach ($events as $event) {
            try {
                $message = $this->messageBuilder->build($event->payload);
                $this->publisher->publish($message['topic'], $message['envelope'], $message['key']);
                $event->markPublished();
            } catch (Throwable $exception) {
                $event->markFailed();
                $this->error("Failed to publish outbox event {$event->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Processed {$events->count()} outbox events.");

        return self::SUCCESS;
    }
}
```

---

### Phase 3: Integrate outbox into transfer flow and Kafka event rename

**Objective:** Replace direct `SendNotificationJob` dispatch with outbox insertion. Rename Kafka event from `transfer.authorized` to `transfer.completed`. Remove synthetic `txn_*` IDs; use real `transfer.id`.

#### Files involved

| Path | Current purpose | Identified problem | Proposed change |
|------|-----------------|-------------------|-----------------|
| `app/Services/WalletTransferService.php` | Executes transfer and dispatches notification directly | Tight coupling between transaction and notification dispatch | In `runInTransaction()`, replace `DB::afterCommit(fn() => SendNotificationJob::dispatch(...))` with `OutboxEvent::create(...)` in the same transaction |
| `app/Services/TransferMessageBuilder.php` | Builds Kafka envelope with event `transfer.authorized` | Event name is `authorized` but should be `completed`; uses synthetic ID | Change `EVENT_NAME` to `transfer.completed`; the payload builder should receive and use the real `transfer.id` |
| `app/Services/KafkaTransferService.php` | Generates synthetic `txn_*` IDs and publishes to Kafka | Synthetic IDs are not tied to real transfer records | Refactor to accept a real `Transfer` model and use `transfer->id` as the Kafka message key |

#### Reference code — WalletTransferService change (runInTransaction)

```php
// Inside runInTransaction(), after Transfer::create():
$transfer = Transfer::create([...]);

// Replace the DB::afterCommit block:
OutboxEvent::create([
    'aggregate_type' => 'transfer',
    'aggregate_id' => $transfer->id,
    'event_type' => 'transfer.completed',
    'payload' => [
        'transfer_id' => $transfer->id,
        'payer_id' => $payer->id,
        'payee_id' => $payee->id,
        'amount' => $amount,
        'occurred_at' => now()->toIso8601String(),
    ],
    'status' => 'Pending',
]);

return $transfer;
```

#### Reference code — TransferMessageBuilder change

```php
<?php

declare(strict_types=1);

namespace App\Services;

final readonly class TransferMessageBuilder
{
    private const string EVENT_NAME = 'transfer.completed';
    private const string VERSION = '1.0';

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{topic: string, key: string, envelope: array<string, mixed>}
     */
    public function build(array $payload): array
    {
        $transferId = (string) ($payload['transfer_id'] ?? '');

        return [
            'topic' => $this->getTopic(),
            'key' => $transferId,
            'envelope' => [
                'meta' => [
                    'version' => self::VERSION,
                    'event' => self::EVENT_NAME,
                    'occurred_at' => now()->toIso8601String(),
                ],
                'payload' => $payload,
            ],
        ];
    }

    public function getTopic(): string
    {
        return (string) config('kafka.topic_completed', 'wallet.transfer.completed');
    }
}
```

---

### Phase 4: Kafka consumer bridge deduplication and HTTP status codes

**Objective:** Ensure the Kafka → RabbitMQ bridge has a documented pre-dispatch idempotency guard. Map authorizer exceptions to correct HTTP status codes.

#### Files involved

| Path | Current purpose | Identified problem | Proposed change |
|------|-----------------|-------------------|-----------------|
| `app/Services/KafkaTransferProcessor.php` | Processes Kafka payload and dispatches `SendNotificationJob` | Already has Redis deduplication, but needs explicit documentation | Document that `kafka:transfer:{transfer_id}` is a **pre-dispatch** guard and `notified_at` is the final guard; use real integer `transfer_id`; mark processed when transfer missing or not completed |
| `app/Services/IdempotencyKeyService.php` | Acquires and finalizes idempotency keys | Missing `endpoint`/`request_hash`/`response_status`/`response_body` handling | Make `request_hash` canonical; store cached response; replay completed matches by returning cached status + body |
| `app/Http/Controllers/Api/V1/TransferController.php` | Handles transfer HTTP request | Does not catch `AuthorizerRejectedException` or `TransientAuthorizerException`; no cached response replay | Verify `Auth::id() === payer_id` returns 403; add exception mapping (422/503); short-circuit on cached idempotency response |

#### Reference code — TransferController exception mapping and cached replay

```php
public function __invoke(CreateTransferRequest $request): JsonResponse
{
    $authenticatedUser = $request->user();
    $payerId = (int) $request->validated('payer');

    if ($authenticatedUser instanceof User && $authenticatedUser->id !== $payerId) {
        return response()->json(['message' => __('auth.failed')], 403);
    }

    $idempotencyKey = $request->idempotencyKey();
    $requestHash = $this->idempotencyService->buildRequestHash($request->validated());

    $cachedResponse = $this->idempotencyService->tryResolveCachedResponse(
        $idempotencyKey,
        $request->route()->uri(),
        $requestHash,
    );

    if (! is_null($cachedResponse)) {
        return response()->json($cachedResponse['body'], $cachedResponse['status']);
    }

    try {
        $transfer = $this->service->execute($payerId, ...);
    } catch (AuthorizerRejectedException) {
        return response()->json([
            'code' => 'authorizer_rejected',
            'message' => FailureReason::AuthorizerRejected->description(),
        ], 422);
    } catch (TransientAuthorizerException) {
        return response()->json([
            'code' => 'authorizer_unavailable',
            'message' => 'Authorizer temporarily unavailable. Please retry.',
        ], 503);
    }

    $statusCode = $this->resolveStatusCode($transfer);
    $responseBody = [
        'data' => [
            'id' => $transfer->id,
            'status' => $transfer->status->value,
            'failure_reason' => $transfer->failure_reason?->value,
        ],
    ];

    if (! $transfer->status->isFailed()) {
        $this->idempotencyService->finalizeIdempotencyKey(
            $idempotencyKey,
            $requestHash,
            $transfer,
            $request->route()->uri(),
            $statusCode,
            $responseBody,
        );
    }

    return response()->json($responseBody, $statusCode);
}
```

#### Documented behavior (no code change)

`KafkaTransferProcessor::process()` implements the pre-dispatch Redis guard:
- Before dispatching `SendNotificationJob`, it checks `Cache::has("kafka:transfer:{$transferId}")`.
- If the key exists, the method returns early.
- After successful dispatch, it writes `Cache::put("kafka:transfer:{$transferId}", true, 3600)` (TTL configurable via `kafka.idempotency_ttl`, default 3600 seconds).
- **`notified_at` on the `Transfer` model remains the final guard** inside `SendNotificationJob::handle()`.
- The processor now uses the real integer `transfer_id` from the Kafka payload and dispatches `SendNotificationJob` only for completed transfers. If the transfer is not found or is not completed, it logs a warning and marks the message processed (Redis flag) so Kafka does not redeliver it indefinitely.

---

### Phase 5: Test updates

**Objective:** Update existing tests and add new ones for outbox and event rename.

#### Files to update

| Path | Changes required |
|------|-----------------|
| `tests/Unit/Services/WalletTransferServiceTest.php` | Remove assertions that `SendNotificationJob` is dispatched directly; add assertions that `OutboxEvent` is created in the database with correct payload |
| `tests/Unit/Services/TransferMessageBuilderTest.php` | Update expected event name from `transfer.authorized` to `transfer.completed`; use real integer transfer IDs |
| `tests/Feature/Kafka/KafkaTransferIntegrationTest.php` | Update expected event name; update synthetic `txn_*` IDs to real `transfer.id` values |
| `tests/Unit/Services/KafkaTransferProcessorTest.php` | Add test for Redis pre-dispatch guard TTL; verify real `transfer_id`; verify no dispatch when transfer missing or not completed |
| `tests/Feature/Console/ConsumeTransfersCommandTest.php` | Update expected event name in dry-run assertions |
| `tests/Feature/Console/KafkaProduceTransferCommandTest.php` | Use real transfer IDs; update dry-run assertions |
| `tests/Feature/Api/V1/TransferControllerTest.php` | Add tests for 422 (authorizer rejection), 503 (transient authorizer error), 403 (identity mismatch), cached idempotency replay, outbox row created, no direct `SendNotificationJob` dispatch |
| `tests/Feature/Database/Migrations/IdempotencyKeysMigrationBackfillTest.php` | Add assertions that `request_hash` is backfilled from `fingerprint` and that `endpoint`, `response_status`, `response_body` columns exist |
| `tests/Unit/Jobs/SendNotificationJobTest.php` | No changes needed; `notified_at` guard remains the final idempotency mechanism |
| `tests/Unit/Models/OutboxEventTest.php` | (new) Test `pending()` scope, `markFailed()` sets `status=Failed`, `markPublished()`, unique index enforcement |
| `tests/Feature/Console/PublishOutboxEventsCommandTest.php` | (new) Test command publishes pending events, skips already-published, marks failures correctly, respects retry interval |
| `tests/Feature/Outbox/OutboxIntegrationTest.php` | (optional) Full transfer→outbox→Kafka→RabbitMQ flow with real transfer ID |
| `tests/Unit/Services/IdempotencyKeyServiceTest.php` | request_hash canonical, cached response replay |

---

### Phase 6: Documentation and static analysis

**Objective:** Update README/docs and run static analysis.

#### Files to update

| Path | Changes required |
|------|-----------------|
| `README.md` (or equivalent docs) | Document the transactional outbox flow: transfer → outbox → `outbox:publish` → Kafka → consumer → RabbitMQ → notification |
| `docs/architecture.md` (if exists) | Add sequence diagram showing outbox pattern and dual idempotency guards (Redis pre-dispatch + `notified_at` final guard) |

#### Static analysis commands

```bash
cd backend
./vendor/bin/phpstan analyse --memory-limit=1G
./vendor/bin/rector process --dry-run
./vendor/bin/ecs check app/Services/WalletTransferService.php app/Models/OutboxEvent.php app/Console/Commands/PublishOutboxEventsCommand.php app/Services/IdempotencyKeyService.php app/Http/Controllers/Api/V1/TransferController.php
```

---

## Notes

- **No outbox partial index**: The unique index on `(aggregate_type, aggregate_id, event_type)` is a simple (non-partial) index. The outbox statuses are `Pending`, `Published`, `Failed`. The index guarantees at most one logical event per aggregate/type regardless of status. Application code uses `status` to determine publish state.
- **Retry semantics**: A `Failed` outbox event is eligible for retry if `attempts < max_attempts` AND `last_error_at` is older than `retry_interval_seconds`. The `markFailed()` method explicitly sets `status = 'Failed'`, increments `attempts`, and updates `last_error_at = now()`. Retryability is kept in a single place (the `pending()` scope).
- **Concurrency**: `outbox:publish` is scheduled with `WithoutOverlapping` so it does not run concurrently in production. Running two instances concurrently could double-publish events and waste Kafka broker/consumer capacity. For extra safety a PostgreSQL advisory lock or row-level lock can be added around the batch loop.
- **Kafka message key**: Must be the string representation of the real `Transfer->id`. The consumer extracts it from `payload['transfer_id']`. No synthetic `txn_*` prefixes remain.
- **`KafkaTransferService` removal**: The legacy `KafkaTransferService` direct-publish path was removed because the transactional outbox is now the canonical mechanism. Kafka and RabbitMQ remain in the architecture as the outbox publisher target and the notification worker respectively.
- **payer_id/payee_id**: These columns on `transfers` continue to reference `users.id`. The migration `2026_07_02_100000_relax_transfer_constraints_for_failed_records.php` already dropped FK constraints to allow failed transfers with potentially missing users. Orphaned `Wallet` relations should be cleaned up via a separate data-cleanup job if needed, but this is outside the scope of this plan.
- **Backward compatibility**: The `transfer.authorized` event name change is a breaking change. Any existing Kafka consumers must be updated before deployment. Consider a blue/green deployment or a temporary dual-publish period if external consumers exist.
