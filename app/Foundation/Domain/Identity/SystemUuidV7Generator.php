<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Identity;

use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Exception\InvariantViolation;
use App\Foundation\Domain\Time\Clock;
use Random\RandomException;

/**
 * The production {@see UuidV7Generator}, building RFC 9562 version 7 UUIDs from
 * the injected {@see Clock} and the platform CSPRNG (PF-048).
 *
 * Uses the PHP standard library only — no Laravel helper, no Carbon, no PSR
 * interface, no `ramsey/uuid`, no `symfony/uid`, no package of any kind. Both
 * of those libraries are present, but only transitively via the framework, and
 * a transitive dependency authorizes nothing; the same rule PF-047 applied when
 * it declined PSR-20 and Carbon.
 *
 * The `Clock` is passed to the constructor rather than to `generate()`, so the
 * interface method stays parameterless and a test substitutes a fixed clock
 * once instead of at every call. Nothing here reads ambient system time.
 *
 * **Stateless between calls, deliberately.** No counter, no sequence, no
 * last-seen timestamp, no static property, and no coordination with any other
 * process. Two instances sharing one clock are fully independent. That is what
 * makes the documented non-guarantees honest: **no monotonicity, no defined
 * order within a millisecond, no clock-rollback correction, and no global,
 * cross-process, or cross-host ordering.** Adding same-millisecond monotonic
 * increment would be a new implementation behind this same contract, under its
 * own approved story — not a change to this one.
 */
final class SystemUuidV7Generator implements UuidV7Generator
{
    /**
     * The inclusive bounds of the UUIDv7 `unix_ts_ms` field.
     *
     * RFC 9562 §5.7 defines it as a **48-bit unsigned** count of milliseconds
     * since the Unix epoch, so the representable range is `0` through
     * `2**48 - 1` — 1970-01-01T00:00:00.000Z through 10889-08-02T05:31:50.655Z.
     * An instant outside it cannot be encoded, and this generator refuses to
     * try rather than truncating, wrapping, clamping, or reinterpreting it.
     */
    private const MAXIMUM_UNIX_MILLISECONDS = 281474976710655;

    public function __construct(private readonly Clock $clock) {}

    /**
     * A new UUIDv7 stamped with the clock's current instant.
     *
     * `RandomException` propagates untranslated when the platform CSPRNG fails
     * — see {@see UuidV7Generator::generate()}. `InvariantViolation` means the
     * clock reported an instant outside the 48-bit `unix_ts_ms` range, or the
     * assembled value somehow failed {@see UuidV7} validation.
     *
     * @throws RandomException
     * @throws InvariantViolation
     */
    public function generate(): UuidV7
    {
        $bytes = $this->timestampBytes($this->unixMilliseconds()).random_bytes(10);

        // Byte 6 high nibble: version 7. Byte 8 top two bits: RFC variant 0b10.
        // Both bytes come from the random block, so 74 random bits survive —
        // 12 in rand_a (byte 6 low nibble plus byte 7) and 62 in rand_b.
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x70);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

        $hex = bin2hex($bytes);

        try {
            return UuidV7::fromString(implode('-', [
                substr($hex, 0, 8),
                substr($hex, 8, 4),
                substr($hex, 12, 4),
                substr($hex, 16, 4),
                substr($hex, 20, 12),
            ]));
        } catch (InvalidArgument $rejected) {
            // Unreachable by construction: the bytes above always satisfy the
            // canonical pattern. If it ever happens, this generator has broken
            // its own guarantee — an invariant violation, not a bad argument
            // from a caller — so the failure is re-labelled rather than
            // escaping as though a caller had supplied something invalid. The
            // original is retained as the previous exception.
            throw new InvariantViolation(
                'The generated value is not a canonical RFC 9562 version 7 UUID.',
                0,
                $rejected,
            );
        }
    }

    /**
     * The clock's current instant as whole Unix milliseconds.
     *
     * **Integer arithmetic only, and no floating point anywhere — including on
     * the rejection path.** A float cannot hold a millisecond timestamp exactly
     * across the whole 48-bit range, and a `(float)` round trip invites an
     * off-by-one at a boundary.
     *
     * **The bounds are therefore checked before the multiplication, never
     * after.** `\DateTimeImmutable` can represent instants whose Unix seconds
     * exceed `intdiv(PHP_INT_MAX, 1000)` — `new \DateTimeImmutable('@9223372036854776')`
     * is perfectly constructible — and for those, `$seconds * 1000` overflows
     * the integer domain and silently becomes a float. Multiplying first would
     * mean the guarantee above held only for inputs that were already in range,
     * which is precisely the case where it does not matter. Comparing whole
     * seconds first keeps every operand inside the integer domain on both the
     * accepting and the rejecting path.
     *
     * The comparison is split across the two fields the value is assembled
     * from: seconds against `intdiv(MAX, 1000)`, and — only on the final
     * representable second — the millisecond remainder against `MAX % 1000`.
     * Negative seconds are rejected outright, since `unix_ts_ms` is unsigned.
     *
     * `U` yields whole seconds and `v` the millisecond component. PHP
     * normalises an instant to a floored second plus a **non-negative**
     * sub-second part, so `$milliseconds` is always `0`–`999` and the sign of
     * an instant lives entirely in `$seconds`.
     *
     * Once both checks pass, `$seconds <= 281474976710`, so the product is at
     * most `281474976710000` and the sum at most `281474976710655` — the
     * multiplication cannot overflow, by construction.
     *
     * @throws InvariantViolation if the instant is outside the 48-bit range
     */
    private function unixMilliseconds(): int
    {
        $instant = $this->clock->now();

        $seconds = (int) $instant->format('U');
        $milliseconds = (int) $instant->format('v');

        $maximumSeconds = intdiv(self::MAXIMUM_UNIX_MILLISECONDS, 1000);
        $maximumFinalSecondMilliseconds = self::MAXIMUM_UNIX_MILLISECONDS % 1000;

        if ($seconds < 0
            || $seconds > $maximumSeconds
            || ($seconds === $maximumSeconds && $milliseconds > $maximumFinalSecondMilliseconds)
        ) {
            // The offending instant is deliberately absent from the message:
            // Foundation exception messages are developer-facing diagnostics
            // and carry no values read from outside this method. The bounds are
            // fixed literals, so naming them leaks nothing.
            throw new InvariantViolation(
                'The clock reported an instant outside the range an RFC 9562 version 7 '
                .'timestamp can represent (0 to 281474976710655 Unix milliseconds inclusive).',
            );
        }

        return $seconds * 1000 + $milliseconds;
    }

    /**
     * The 48-bit timestamp as six big-endian bytes.
     *
     * `pack('J', …)` states big-endian explicitly, so the result never depends
     * on the host's byte order. It produces eight bytes; the leading two are
     * necessarily zero for a value the caller has already range-checked, so
     * dropping them is lossless.
     *
     * @param  int  $milliseconds  already range-checked by {@see self::unixMilliseconds()}
     *                             to `0`–`281474976710655`; this method performs no
     *                             check of its own and is private for that reason
     */
    private function timestampBytes(int $milliseconds): string
    {
        return substr(pack('J', $milliseconds), -6);
    }
}
