<?php

declare(strict_types=1);

namespace Tests\Unit\Services\DryRun;

use App\Services\DryRun\DryRunContext;
use App\Services\DryRun\DryRunRecorder;
use PHPUnit\Framework\TestCase;

final class DryRunContextTest extends TestCase
{
    public function test_is_disabled_by_default(): void
    {
        $context = new DryRunContext(new DryRunRecorder());

        $this->assertFalse($context->isEnabled());
    }

    public function test_enable_and_disable_toggle_state(): void
    {
        $context = new DryRunContext(new DryRunRecorder());

        $context->enable();
        $this->assertTrue($context->isEnabled());

        $context->disable();
        $this->assertFalse($context->isEnabled());
    }

    public function test_records_and_flushes_entries(): void
    {
        $context = new DryRunContext(new DryRunRecorder());

        $context->record('kafka.publish', ['topic' => 'wallet.transfer.completed']);

        $entries = $context->flush();

        $this->assertCount(1, $entries);
        $this->assertSame('kafka.publish', $entries[0]['action']);
        $this->assertSame('wallet.transfer.completed', $entries[0]['context']['topic']);

        $this->assertCount(0, $context->flush());
    }
}
