<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Domain\Time;

use App\Foundation\Domain\Time\Clock;
use PHPUnit\Framework\TestCase;

/**
 * PF-047 — the published `Clock` contract.
 *
 * These assertions pin the contract's exact shape, so a later accidental
 * widening — an extra convenience method, a nullable return, or a return type
 * relaxed to `\DateTimeInterface` — fails here rather than silently reaching
 * every consumer.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly and boots no
 * Laravel application.
 */
final class ClockTest extends TestCase
{
    public function test_clock_is_an_interface(): void
    {
        $this->assertTrue((new \ReflectionClass(Clock::class))->isInterface());
    }

    public function test_clock_declares_exactly_one_method(): void
    {
        $this->assertCount(1, (new \ReflectionClass(Clock::class))->getMethods());
    }

    public function test_the_declared_method_is_named_now(): void
    {
        $methods = (new \ReflectionClass(Clock::class))->getMethods();

        $this->assertSame('now', $methods[0]->getName());
    }

    public function test_now_is_public(): void
    {
        $this->assertTrue((new \ReflectionMethod(Clock::class, 'now'))->isPublic());
    }

    public function test_now_takes_no_parameters(): void
    {
        $this->assertSame(0, (new \ReflectionMethod(Clock::class, 'now'))->getNumberOfParameters());
    }

    public function test_now_returns_a_non_nullable_date_time_immutable(): void
    {
        $returnType = (new \ReflectionMethod(Clock::class, 'now'))->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(\DateTimeImmutable::class, $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }

    public function test_clock_declares_no_constants(): void
    {
        $this->assertSame([], (new \ReflectionClass(Clock::class))->getConstants());
    }

    public function test_this_test_runs_without_booting_laravel(): void
    {
        $this->assertInstanceOf(TestCase::class, $this);
        $this->assertFalse(is_subclass_of($this, 'Illuminate\Foundation\Testing\TestCase'));
        $this->assertFalse(is_subclass_of($this, 'Tests\TestCase'));
        $this->assertFalse(property_exists($this, 'app'));
    }
}
