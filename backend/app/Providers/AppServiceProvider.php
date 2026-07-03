<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\TransferPublisherInterface;
use App\Services\KafkaTransferPublisher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TransferPublisherInterface::class, KafkaTransferPublisher::class);
    }
}
