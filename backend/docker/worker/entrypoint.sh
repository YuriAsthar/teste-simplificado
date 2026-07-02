#!/bin/sh
set -e

queue_name="${RABBITMQ_QUEUE:-default}"
connection="${RABBITMQ_QUEUE_CONNECTION:-rabbitmq}"

# Ensure the target RabbitMQ queue is declared before the worker starts consuming.
# The laravel-queue-rabbitmq driver auto-declares queues on push, but not on pop,
# so a fresh RabbitMQ instance causes: "not_found: no queue '<name>' in vhost '/'".
php artisan rabbitmq:queue-declare "${queue_name}" "${connection}"

exec php artisan queue:work "${connection}" --queue="${queue_name}" "$@"
