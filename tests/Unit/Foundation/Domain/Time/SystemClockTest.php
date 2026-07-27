<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Domain\Time;

use App\Foundation\Domain\Time\Clock;
use App\Foundation\Domain\Time\SystemClock;
use PHPUnit\Framework\TestCase;

/**
 * PF-047 — the native UTC `SystemClock`.
 *
 * Every assertion here is deterministic. Nothing sleeps, nothing allows a
 * fixed elapsed-time tolerance, and nothing asserts that two readings differ
 * or that successive readings never decrease: `SystemClock` reads the host's
 * **wall clock**, which may be corrected backwards, and two calls may
 * legitimately land in the same microsecond.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly and boots no
 * Laravel application.
 */
final class SystemClockTest extends TestCase
{
    public function test_system_clock_is_final(): void
    {
        $this->assertTrue((new \ReflectionClass(SystemClock::class))->isFinal());
    }

    public function test_system_clock_implements_the_clock_contract(): void
    {
        $this->assertContains(Clock::class, class_implements(SystemClock::class));
    }

    public function test_system_clock_needs_no_constructor_arguments(): void
    {
        $constructor = (new \ReflectionClass(SystemClock::class))->getConstructor();

        $this->assertNull($constructor, 'SystemClock must be constructible with no arguments.');
    }

    public function test_now_returns_a_date_time_immutable(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, (new SystemClock)->now());
    }

    public function test_now_returns_the_utc_timezone_by_name(): void
    {
        $this->assertSame('UTC', (new SystemClock)->now()->getTimezone()->getName());
    }

    public function test_now_returns_a_zero_utc_offset(): void
    {
        $this->assertSame(0, (new SystemClock)->now()->getOffset());
    }

    public function test_separate_calls_return_separate_instances(): void
    {
        $clock = new SystemClock;

        $this->assertNotSame($clock->now(), $clock->now());
    }

    /**
     * The reading falls between two native UTC readings taken around the call.
     *
     * Bracketing rather than a tolerance: this compares ordering against
     * independently obtained instants, so it stays correct on a slow runner
     * and needs no arbitrary millisecond allowance.
     */
    public function test_now_is_bracketed_by_native_readings_taken_around_the_call(): void
    {
        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $instant = (new SystemClock)->now();
        $after = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->assertGreaterThanOrEqual($before, $instant);
        $this->assertLessThanOrEqual($after, $instant);
    }

    /**
     * The ambient default timezone cannot change the result.
     *
     * `SystemClock` passes UTC to the constructor explicitly, so it never
     * depends on `date.timezone`, on `date_default_timezone_set()`, or on
     * Laravel having applied its own configuration first. The original value
     * is restored in `finally` so this can never leak into another test.
     */
    public function test_ambient_default_timezone_does_not_change_the_result(): void
    {
        $original = date_default_timezone_get();

        try {
            date_default_timezone_set('Asia/Bangkok');

            $instant = (new SystemClock)->now();

            $this->assertSame('UTC', $instant->getTimezone()->getName());
            $this->assertSame(0, $instant->getOffset());
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function test_this_test_runs_without_booting_laravel(): void
    {
        $this->assertInstanceOf(TestCase::class, $this);
        $this->assertFalse(is_subclass_of($this, 'Illuminate\Foundation\Testing\TestCase'));
        $this->assertFalse(is_subclass_of($this, 'Tests\TestCase'));
        $this->assertFalse(property_exists($this, 'app'));
    }
}
