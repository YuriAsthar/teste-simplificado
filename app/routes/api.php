<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\TransferController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', TokenController::class)->name('auth.token');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', LogoutController::class)->name('auth.logout');
    Route::post('/transfer', TransferController::class)->name('transfer.store');
});
