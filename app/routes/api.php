<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TransferController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/transfers', TransferController::class);
