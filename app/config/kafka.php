<?php

declare(strict_types=1);

return [
    'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),
    'securityProtocol' => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),
    'sasl' => [
        'mechanisms' => env('KAFKA_MECHANISMS', 'PLAINTEXT'),
        'username' => env('KAFKA_USERNAME'),
        'password' => env('KAFKA_PASSWORD'),
    ],
    'consumer_group_id' => env('KAFKA_CONSUMER_GROUP_ID', 'wallet-transfer-consumers'),
    'consumer_group_id_retry' => env('KAFKA_CONSUMER_GROUP_ID_RETRY', env('KAFKA_CONSUMER_GROUP_ID', 'wallet-transfer-consumers') . '-retry'),
    'consumer_timeout_ms' => (int) env('KAFKA_CONSUMER_DEFAULT_TIMEOUT', 2000),
    'offset_reset' => env('KAFKA_OFFSET_RESET', 'earliest'),
    'auto_commit' => (bool) env('KAFKA_AUTO_COMMIT', true),
    'sleep_on_error' => (int) env('KAFKA_ERROR_SLEEP', 5),
    'partition' => (int) env('KAFKA_PARTITION', 0),
    'compression' => env('KAFKA_COMPRESSION_TYPE', 'snappy'),
    'debug' => (bool) env('KAFKA_DEBUG', false),
    'flush_retry_sleep_in_ms' => 100,
    'flush_retries' => 10,
    'flush_timeout_in_ms' => 1000,
    'cache_driver' => env('KAFKA_CACHE_DRIVER', env('CACHE_DRIVER', env('CACHE_STORE', 'database'))),
    'message_id_key' => env('MESSAGE_ID_KEY', 'laravel-kafka::message-id'),
    'topic_completed' => env('KAFKA_TOPIC_COMPLETED', 'wallet.transfer.completed'),
    'topic_dlq' => env('KAFKA_TOPIC_DLQ', 'wallet.transfer.dlq'),
    'topic_retry' => env('KAFKA_TOPIC_RETRY', 'wallet.transfer.retry'),
    'idempotency_ttl' => (int) env('KAFKA_IDEMPOTENCY_TTL', 3600),
    'retry_attempts' => (int) env('KAFKA_RETRY_ATTEMPTS', 3),
    'retry_backoff_seconds' => (int) env('KAFKA_RETRY_BACKOFF_SECONDS', 60),
    'commit_after_handle' => (bool) env('KAFKA_COMMIT_AFTER_HANDLE', true),
];
