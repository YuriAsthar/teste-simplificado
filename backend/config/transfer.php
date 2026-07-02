<?php

declare(strict_types=1);

return [
    'idempotency_processing_ttl_seconds' => (int) env('TRANSFER_IDEMPOTENCY_PROCESSING_TTL_SECONDS', 300),
];
