<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TransferController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', \App\Http\Controllers\Api\V1\TokenController::class)->name('auth.token');

Route::middleware('auth:sanctum')->post('/transfer', \App\Http\Controllers\Api\V1\TransferController::class)->name('transfer.store');
