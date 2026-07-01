<?php

declare(strict_types=1);

namespace Tests\Unit\Casts;

use App\Casts\MoneyCast;
use App\Models\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyCastTest extends TestCase
{
    public function test_get_coerces_string_value_to_int(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->assertSame(1050, $cast->get($model, 'balance', '1050', []));
    }

    public function test_get_coerces_int_value_to_int(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->assertSame(1050, $cast->get($model, 'balance', 1050, []));
    }

    public function test_get_rejects_null(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->expectException(InvalidArgumentException::class);

        $cast->get($model, 'balance', null, []);
    }

    public function test_set_accepts_int_cents(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->assertSame(1050, $cast->set($model, 'balance', 1050, []));
    }

    public function test_set_rejects_string(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line argument.type */
        $cast->set($model, 'balance', '10.50', []);
    }

    public function test_set_rejects_float(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line argument.type */
        $cast->set($model, 'balance', 10.50, []);
    }

    public function test_set_rejects_bool(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line argument.type */
        $cast->set($model, 'balance', true, []);
    }

    public function test_set_rejects_array(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line argument.type */
        $cast->set($model, 'balance', [], []);
    }

    public function test_set_rejects_null(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->expectException(InvalidArgumentException::class);

        $cast->set($model, 'balance', null, []);
    }

    public function test_set_rejects_object(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line argument.type */
        $cast->set($model, 'balance', new \stdClass(), []);
    }
}
