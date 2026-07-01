<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// API-only application. This root route exists only as a lightweight health
// check for load balancers and Docker health checks.
Route::get('/', fn () => response()->json(['service' => 'wallet-api', 'status' => 'ok']));
