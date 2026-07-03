<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\UserCreated;
use App\Listeners\CreateUserWallet;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserCreated::class => [
            CreateUserWallet::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
    }
}
