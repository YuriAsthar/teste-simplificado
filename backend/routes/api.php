<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\TransferController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', LoginController::class)->name('auth.login');
Route::post('/auth/register', RegisterController::class)->name('auth.register');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', LogoutController::class)->name('auth.logout');
    Route::post('/transfer', TransferController::class)->name('transfer.store');
});
