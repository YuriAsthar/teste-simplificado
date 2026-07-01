<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendNotificationJob;
use App\Models\Transfer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Bus\Dispatcher;

final class RetryNotificationsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'notifications:retry';

    /**
     * @var string
     */
    protected $description = 'Retry notifications for completed transfers from the last 30 days.';

    public function handle(Dispatcher $dispatcher): int
    {
        $count = 0;

        Transfer::query()
            ->pendingNotification()
            ->chunkById(100, static function ($transfers) use ($dispatcher, &$count): void {
                foreach ($transfers as $transfer) {
                    $dispatcher->dispatch(new SendNotificationJob($transfer->getKey()));
                    ++$count;
                }
            });

        $this->info("Dispatched {$count} notification retry jobs.");

        return self::SUCCESS;
    }
}
