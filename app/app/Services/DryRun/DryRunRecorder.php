<?php

declare(strict_types=1);

namespace App\Services\DryRun;

final class DryRunRecorder
{
    /** @var list<array{action: string, context: array<string, mixed>}> */
    private array $entries = [];

    /**
     * @param array<string, mixed> $context
     */
    public function record(string $action, array $context): void
    {
        $this->entries[] = [
            'action' => $action,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{action: string, context: array<string, mixed>}>
     */
    public function flush(): array
    {
        $entries = $this->entries;
        $this->entries = [];

        return $entries;
    }

    public function reset(): void
    {
        $this->entries = [];
    }
}
