<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\TransferPublisherInterface;
use App\Services\DryRun\DryRunContext;
use App\Services\DryRun\DryRunRecorder;
use App\Services\Kafka\DryRunTransferPublisher;
use App\Services\KafkaTransferPublisher;
use App\Services\TransferMessageBuilder;
use App\Services\TransferMessageConsumer;
use App\Services\TransferRetryMessageConsumer;
use App\Services\TransferRetryPolicy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DryRunRecorder::class);
        $this->app->singleton(DryRunContext::class, static function ($app): DryRunContext {
            return new DryRunContext($app->make(DryRunRecorder::class));
        });

        $this->app->bind(TransferPublisherInterface::class, static function ($app): TransferPublisherInterface {
            return new DryRunTransferPublisher(
                $app->make(DryRunContext::class),
                $app->make(KafkaTransferPublisher::class),
            );
        });

        $this->app->singleton(TransferMessageBuilder::class, static fn () => new TransferMessageBuilder());
        $this->app->when(TransferMessageConsumer::class)
            ->needs(TransferPublisherInterface::class)
            ->give(DryRunTransferPublisher::class);
        $this->app->when(TransferRetryMessageConsumer::class)
            ->needs(TransferPublisherInterface::class)
            ->give(DryRunTransferPublisher::class);
        $this->app->when(TransferRetryPolicy::class)
            ->needs(TransferPublisherInterface::class)
            ->give(DryRunTransferPublisher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
