<?php

declare(strict_types=1);

return [
    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 3),
    'retry_interval_seconds' => (int) env('OUTBOX_RETRY_INTERVAL_SECONDS', 300),
];
