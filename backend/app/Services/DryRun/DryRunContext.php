<?php

declare(strict_types=1);

namespace App\Services\DryRun;

final class DryRunContext
{
    private bool $enabled = false;

    public function __construct(
        private readonly DryRunRecorder $recorder,
    ) {
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function record(string $action, array $context): void
    {
        $this->recorder->record($action, $context);
    }

    /**
     * @return list<array{action: string, context: array<string, mixed>}>
     */
    public function flush(): array
    {
        return $this->recorder->flush();
    }
}
