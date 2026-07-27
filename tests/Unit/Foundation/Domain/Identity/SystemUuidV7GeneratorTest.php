<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Domain\Identity;

use App\Foundation\Domain\Exception\DomainException;
use App\Foundation\Domain\Exception\FoundationException;
use App\Foundation\Domain\Exception\InvariantViolation;
use App\Foundation\Domain\Identity\SystemUuidV7Generator;
use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Domain\Identity\UuidV7Generator;
use App\Foundation\Domain\Time\Clock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PF-048 — the native `SystemUuidV7Generator`.
 *
 * Every assertion is deterministic. Nothing sleeps, nothing allows an elapsed-
 * time tolerance, nothing reads the real clock, and nothing asserts an ordering
 * between two values generated in the same millisecond — that ordering is
 * explicitly not guaranteed, and a test asserting it would be asserting a
 * promise the contract refuses to make.
 *
 * Determinism comes from the two `Clock` fixtures at the bottom of this file.
 * `app/Foundation/README.md` requires a fixed-time fixture to live under
 * `tests/` rather than in `app/Foundation`, and PF-048 is the first story to
 * need one.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly and boots no
 * Laravel application.
 */
final class SystemUuidV7GeneratorTest extends TestCase
{
    /** The inclusive bounds of the 48-bit `unix_ts_ms` field, per RFC 9562 §5.7. */
    private const MINIMUM_MILLISECONDS = 0;

    private const MAXIMUM_MILLISECONDS = 281474976710655;

    // ---------------------------------------------------------------- shape

    public function test_system_uuid_v7_generator_is_final(): void
    {
        $this->assertTrue((new \ReflectionClass(SystemUuidV7Generator::class))->isFinal());
    }

    public function test_system_uuid_v7_generator_implements_the_generator_contract(): void
    {
        $this->assertContains(UuidV7Generator::class, class_implements(SystemUuidV7Generator::class));
    }

    public function test_the_constructor_takes_exactly_one_required_clock(): void
    {
        $constructor = (new \ReflectionClass(SystemUuidV7Generator::class))->getConstructor();

        $this->assertInstanceOf(\ReflectionMethod::class, $constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertSame(1, $constructor->getNumberOfRequiredParameters());

        $type = $constructor->getParameters()[0]->getType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(Clock::class, $type->getName());
        $this->assertFalse($type->allowsNull());
    }

    /**
     * No static property, and therefore no process-global generator state.
     *
     * This is the structural half of the "no monotonic state" guarantee. The
     * behavioural half is asserted further down: two instances sharing one
     * clock stay independent.
     */
    public function test_the_generator_declares_no_static_property(): void
    {
        $this->assertSame(
            [],
            (new \ReflectionClass(SystemUuidV7Generator::class))->getProperties(\ReflectionProperty::IS_STATIC),
        );
    }

    // ----------------------------------------------------------- generation

    public function test_generate_returns_a_uuid_v7(): void
    {
        $this->assertInstanceOf(UuidV7::class, $this->generatorAt(1_785_117_770_046)->generate());
    }

    public function test_generated_values_are_canonical_with_version_7_and_an_rfc_variant(): void
    {
        $uuid = $this->generatorAt(1_785_117_770_046)->generate()->toString();

        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
            $uuid,
        );
        $this->assertSame('7', $uuid[14]);
        $this->assertContains($uuid[19], ['8', '9', 'a', 'b']);
    }

    /**
     * A generated value survives a round trip through the published parser.
     *
     * If the assembled bytes ever stopped satisfying `UuidV7::fromString()`,
     * generation would raise `InvariantViolation` instead — this pins that the
     * two halves of the story agree.
     */
    public function test_a_generated_value_round_trips_through_from_string(): void
    {
        $generated = $this->generatorAt(1_785_117_770_046)->generate();

        $this->assertTrue($generated->equals(UuidV7::fromString($generated->toString())));
    }

    // -------------------------------------------------- millisecond encoding

    /**
     * The leading 48 bits are exactly the clock's instant in Unix milliseconds.
     *
     * The expected prefix is computed independently here, from the integer the
     * fixture was built with, rather than by re-running the generator's own
     * arithmetic.
     */
    #[DataProvider('encodableInstants')]
    public function test_the_leading_48_bits_encode_the_clock_instant(int $milliseconds, string $expectedPrefix): void
    {
        $uuid = $this->generatorAt($milliseconds)->generate()->toString();

        $this->assertSame($expectedPrefix, substr($uuid, 0, 8).substr($uuid, 9, 4));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function encodableInstants(): iterable
    {
        yield 'minimum, the Unix epoch' => [self::MINIMUM_MILLISECONDS, '000000000000'];
        yield 'one millisecond after the epoch' => [1, '000000000001'];
        yield 'sub-second component only' => [999, '0000000003e7'];
        yield 'whole second, no milliseconds' => [1000, '0000000003e8'];
        yield 'a realistic present-day instant' => [1_785_117_770_046, '019fa14f813e'];
        yield 'one below the maximum' => [self::MAXIMUM_MILLISECONDS - 1, 'fffffffffffe'];
        yield 'maximum, 2**48 - 1' => [self::MAXIMUM_MILLISECONDS, 'ffffffffffff'];
    }

    /**
     * Milliseconds, not seconds, occupy the timestamp field.
     *
     * Two instants inside the same second produce different prefixes; a
     * seconds-only implementation would produce the same one for both.
     */
    public function test_two_instants_within_one_second_encode_differently(): void
    {
        $first = $this->generatorAt(1_785_117_770_000)->generate()->toString();
        $second = $this->generatorAt(1_785_117_770_999)->generate()->toString();

        $this->assertNotSame(substr($first, 0, 13), substr($second, 0, 13));
    }

    // ------------------------------------------------------- timestamp range

    #[DataProvider('unencodableInstants')]
    public function test_an_instant_outside_the_48_bit_range_is_rejected(int $milliseconds): void
    {
        $this->expectException(InvariantViolation::class);

        $this->generatorAt($milliseconds)->generate();
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function unencodableInstants(): iterable
    {
        yield 'one millisecond below the minimum' => [self::MINIMUM_MILLISECONDS - 1];
        yield 'one second below the minimum' => [-1000];
        yield 'far below the minimum' => [-987_654_321_000];
        yield 'one millisecond above the maximum' => [self::MAXIMUM_MILLISECONDS + 1];
        yield 'far above the maximum' => [self::MAXIMUM_MILLISECONDS + 86_400_000];
    }

    public function test_the_range_rejection_is_catchable_through_the_foundation_taxonomy(): void
    {
        try {
            $this->generatorAt(self::MAXIMUM_MILLISECONDS + 1)->generate();
        } catch (InvariantViolation $violation) {
            $this->assertInstanceOf(DomainException::class, $violation);
            $this->assertInstanceOf(FoundationException::class, $violation);
            $this->assertInstanceOf(\RuntimeException::class, $violation);

            return;
        }

        $this->fail('An out-of-range instant must raise InvariantViolation.');
    }

    /**
     * The offending instant never reaches the exception message.
     *
     * The message names the fixed bounds, which are literals and leak nothing;
     * it must not carry the value the clock actually reported.
     */
    #[DataProvider('unencodableInstantMarkers')]
    public function test_the_out_of_range_instant_never_appears_in_the_exception_message(
        int $milliseconds,
        string $marker,
    ): void {
        try {
            $this->generatorAt($milliseconds)->generate();
        } catch (InvariantViolation $violation) {
            $this->assertStringNotContainsString($marker, $violation->getMessage());

            return;
        }

        $this->fail('An out-of-range instant must raise InvariantViolation.');
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function unencodableInstantMarkers(): iterable
    {
        yield 'above the maximum' => [self::MAXIMUM_MILLISECONDS + 1, '281474976710656'];
        yield 'below the minimum' => [-987_654_321_000, '987654321000'];
    }

    /**
     * An instant so distant that `seconds * 1000` would leave the integer domain.
     *
     * `\DateTimeImmutable` happily represents Unix seconds beyond
     * `intdiv(PHP_INT_MAX, 1000)`. For those, the old
     * `((int) format('U')) * 1000 + ((int) format('v'))` expression **overflowed
     * to a float before the range check ran**, which contradicted PF-048's
     * integer-only guarantee even though the resulting huge float was still
     * rejected. The generator now compares whole seconds before multiplying, so
     * no operand ever leaves the integer domain on either path.
     *
     * The first two assertions establish the premise rather than the behaviour:
     * they prove this fixture really is in the overflowing region, so the test
     * cannot quietly stop exercising it if PHP's date range ever changes.
     *
     * @param  non-empty-string  $timestamp
     */
    #[DataProvider('integerOverflowingInstants')]
    public function test_an_instant_whose_seconds_would_overflow_the_multiplication_is_rejected(string $timestamp): void
    {
        $instant = new \DateTimeImmutable($timestamp, new \DateTimeZone('UTC'));
        $seconds = (int) $instant->format('U');

        $this->assertGreaterThan(intdiv(PHP_INT_MAX, 1000), abs($seconds));
        $this->assertIsFloat($seconds * 1000);

        $this->expectException(InvariantViolation::class);

        (new SystemUuidV7Generator(new FixedClock($instant)))->generate();
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function integerOverflowingInstants(): iterable
    {
        yield 'one second past the positive overflow threshold' => ['@9223372036854776'];
        yield 'far past the positive overflow threshold' => ['@10000000000000000'];
        yield 'past the negative overflow threshold' => ['@-9223372036854776'];
    }

    /**
     * The largest instant that does not overflow is still rejected as out of range.
     *
     * This sits between the encodable maximum and the overflow threshold, so it
     * pins that the seconds comparison — not the overflow behaviour — is what
     * rejects it.
     */
    public function test_the_largest_non_overflowing_instant_is_still_rejected(): void
    {
        $instant = new \DateTimeImmutable('@'.intdiv(PHP_INT_MAX, 1000), new \DateTimeZone('UTC'));

        $this->assertIsInt(((int) $instant->format('U')) * 1000);

        $this->expectException(InvariantViolation::class);

        (new SystemUuidV7Generator(new FixedClock($instant)))->generate();
    }

    /**
     * The boundary is enforced on seconds and the millisecond remainder together.
     *
     * The maximum encodable instant is `281474976710` whole seconds plus a
     * `655` millisecond remainder. Within that final second, `655` is accepted
     * and `656` is not — which is exactly the case a seconds-only comparison
     * would get wrong in one direction and a naive whole-millisecond comparison
     * could only get right after an overflow-prone multiplication.
     */
    public function test_the_final_representable_second_is_bounded_by_its_millisecond_remainder(): void
    {
        $maximumSeconds = intdiv(self::MAXIMUM_MILLISECONDS, 1000);
        $maximumRemainder = self::MAXIMUM_MILLISECONDS % 1000;

        $this->assertSame(281474976710, $maximumSeconds);
        $this->assertSame(655, $maximumRemainder);

        $accepted = $this->generatorAt($maximumSeconds * 1000 + $maximumRemainder)->generate()->toString();

        $this->assertSame('ffffffffffff', substr($accepted, 0, 8).substr($accepted, 9, 4));

        $this->expectException(InvariantViolation::class);

        $this->generatorAt($maximumSeconds * 1000 + $maximumRemainder + 1)->generate();
    }

    /**
     * An out-of-range instant is refused outright, never coerced.
     *
     * Truncating, wrapping, clamping, or reinterpreting would each yield a
     * syntactically valid UUID carrying a silently wrong timestamp — the worst
     * available outcome. Nothing is returned at all.
     */
    public function test_an_out_of_range_instant_is_never_truncated_wrapped_or_clamped(): void
    {
        $generator = $this->generatorAt(self::MAXIMUM_MILLISECONDS + 1);

        $produced = null;

        try {
            $produced = $generator->generate();
        } catch (InvariantViolation) {
            // Expected.
        }

        $this->assertNull($produced, 'No value may be produced for an unencodable instant.');
    }

    // --------------------------------------------------------- clock sourcing

    public function test_the_generator_reads_the_injected_clock_and_never_ambient_time(): void
    {
        $generator = $this->generatorAt(1_785_117_770_046);

        $first = $generator->generate()->toString();
        $second = $generator->generate()->toString();

        $this->assertSame('019fa14f813e', substr($first, 0, 8).substr($first, 9, 4));
        $this->assertSame('019fa14f813e', substr($second, 0, 8).substr($second, 9, 4));
    }

    /**
     * The ambient default timezone cannot change the result.
     *
     * The generator reads absolute Unix time, so a `date.timezone` change must
     * be invisible to it. The original value is restored in `finally` so this
     * can never leak into another test.
     */
    public function test_ambient_default_timezone_does_not_change_the_encoded_timestamp(): void
    {
        $original = date_default_timezone_get();

        try {
            $generator = $this->generatorAt(1_785_117_770_046);

            date_default_timezone_set('Asia/Bangkok');
            $bangkok = $generator->generate()->toString();

            date_default_timezone_set('America/New_York');
            $newYork = $generator->generate()->toString();

            $this->assertSame('019fa14f813e', substr($bangkok, 0, 8).substr($bangkok, 9, 4));
            $this->assertSame('019fa14f813e', substr($newYork, 0, 8).substr($newYork, 9, 4));
        } finally {
            date_default_timezone_set($original);
        }
    }

    // --------------------------------------------------------- random variation

    /**
     * A thousand generations at one fixed millisecond were all distinct.
     *
     * **This observes that the random bits vary. It does not, and cannot,
     * prove that a collision is impossible**, and it is not a guarantee the
     * contract makes — uniqueness is a probabilistic property of 74
     * cryptographically secure random bits, never a proof. No statistical or
     * entropy claim is made or tested here.
     */
    public function test_repeated_generation_at_one_instant_produced_distinct_values(): void
    {
        $generator = $this->generatorAt(1_785_117_770_046);

        $generated = [];

        for ($i = 0; $i < 1000; $i++) {
            $generated[$generator->generate()->toString()] = true;
        }

        $this->assertCount(1000, $generated);
    }

    public function test_values_generated_at_one_instant_share_a_timestamp_but_differ_in_their_random_bits(): void
    {
        $generator = $this->generatorAt(1_785_117_770_046);

        $first = $generator->generate()->toString();
        $second = $generator->generate()->toString();

        $this->assertSame(substr($first, 0, 13), substr($second, 0, 13));
        $this->assertNotSame(substr($first, 14), substr($second, 14));
    }

    // ------------------------------------------------------------- sortability

    /**
     * Values from different milliseconds sort by their encoded timestamps.
     *
     * Deterministic because the timestamp prefixes differ, so the random bits
     * can never decide the comparison. **Nothing is asserted about two values
     * from the same millisecond**, whose relative order is undefined.
     */
    public function test_values_from_different_milliseconds_sort_by_their_timestamps(): void
    {
        $generator = new SystemUuidV7Generator(new ScriptedClock([
            self::instantAt(1_785_117_770_000),
            self::instantAt(1_785_117_770_001),
            self::instantAt(1_785_117_771_000),
            self::instantAt(1_785_200_000_000),
        ]));

        $generated = [
            $generator->generate()->toString(),
            $generator->generate()->toString(),
            $generator->generate()->toString(),
            $generator->generate()->toString(),
        ];

        $sorted = $generated;
        sort($sorted, SORT_STRING);

        $this->assertSame($generated, $sorted);
    }

    /**
     * Clock rollback is permitted and unguarded, and this pins that.
     *
     * `Clock` is a wall clock that may be corrected backwards (PF-047). The
     * generator holds no last-seen timestamp, so a value created after a
     * rollback sorts **before** one created earlier. That is a documented
     * non-guarantee, not a defect — and asserting it here means a future change
     * that silently introduced monotonic state would fail this test instead of
     * quietly widening the published contract.
     */
    public function test_clock_rollback_is_permitted_and_produces_an_earlier_sorting_value(): void
    {
        $generator = new SystemUuidV7Generator(new ScriptedClock([
            self::instantAt(1_785_117_772_000),
            self::instantAt(1_785_117_770_000),
        ]));

        $earlierCall = $generator->generate()->toString();
        $laterCall = $generator->generate()->toString();

        $this->assertLessThan(0, strcmp($laterCall, $earlierCall));
        $this->assertSame('019fa14f88e0', substr($earlierCall, 0, 8).substr($earlierCall, 9, 4));
        $this->assertSame('019fa14f8110', substr($laterCall, 0, 8).substr($laterCall, 9, 4));
    }

    // ------------------------------------------------------------ independence

    /**
     * Two generators sharing one clock share no state.
     *
     * The behavioural counterpart to the no-static-property assertion above.
     * Neither instance can see or influence the other's output, which is what
     * makes the absence of a monotonic counter observable.
     */
    public function test_two_generators_sharing_one_clock_produce_independent_values(): void
    {
        $clock = new FixedClock(self::instantAt(1_785_117_770_046));

        $first = new SystemUuidV7Generator($clock);
        $second = new SystemUuidV7Generator($clock);

        $this->assertNotSame($first->generate()->toString(), $second->generate()->toString());
    }

    public function test_this_test_runs_without_booting_laravel(): void
    {
        $this->assertInstanceOf(TestCase::class, $this);
        $this->assertFalse(is_subclass_of($this, 'Illuminate\Foundation\Testing\TestCase'));
        $this->assertFalse(is_subclass_of($this, 'Tests\TestCase'));
        $this->assertFalse(property_exists($this, 'app'));
    }

    // ----------------------------------------------------------------- helpers

    private function generatorAt(int $milliseconds): SystemUuidV7Generator
    {
        return new SystemUuidV7Generator(new FixedClock(self::instantAt($milliseconds)));
    }

    /**
     * A UTC instant at exactly the given Unix millisecond.
     *
     * Integer arithmetic only, so a boundary value is never nudged by a
     * floating-point round trip. PHP normalises an instant to a floored second
     * plus a non-negative sub-second part, which is why a negative remainder is
     * carried into the seconds rather than left in place.
     */
    private static function instantAt(int $milliseconds): \DateTimeImmutable
    {
        $seconds = intdiv($milliseconds, 1000);
        $remainder = $milliseconds % 1000;

        if ($remainder < 0) {
            $seconds--;
            $remainder += 1000;
        }

        $instant = \DateTimeImmutable::createFromFormat(
            'U.v',
            sprintf('%d.%03d', $seconds, $remainder),
            new \DateTimeZone('UTC'),
        );

        self::assertInstanceOf(\DateTimeImmutable::class, $instant);

        return $instant->setTimezone(new \DateTimeZone('UTC'));
    }
}

/**
 * A `Clock` frozen at one instant.
 *
 * Test-only, and declared here rather than in `app/Foundation`, where
 * `app/Foundation/README.md` prohibits a production test double. PF-048 is the
 * first story to need a fixed-time fixture.
 */
final readonly class FixedClock implements Clock
{
    public function __construct(private \DateTimeImmutable $instant) {}

    public function now(): \DateTimeImmutable
    {
        return $this->instant;
    }
}

/**
 * A `Clock` returning a prepared sequence of instants, one per call.
 *
 * The sequence may move backwards — that is precisely what the clock-rollback
 * test needs. Once exhausted it keeps returning the final instant, so a test
 * can never accidentally depend on an unprepared reading.
 */
final class ScriptedClock implements Clock
{
    private int $index = 0;

    /** @var non-empty-list<\DateTimeImmutable> */
    private array $instants;

    /**
     * @param  non-empty-list<\DateTimeImmutable>  $instants
     */
    public function __construct(array $instants)
    {
        $this->instants = $instants;
    }

    public function now(): \DateTimeImmutable
    {
        $instant = $this->instants[$this->index] ?? $this->instants[array_key_last($this->instants)];

        $this->index++;

        return $instant;
    }
}
