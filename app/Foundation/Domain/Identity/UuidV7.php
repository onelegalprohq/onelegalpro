<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Identity;

use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Model\ValueObject;

/**
 * An RFC 9562 version 7 UUID (PF-048).
 *
 * This is the single identifier primitive every OneLegalPro identifier is built
 * from. PF-044 `BusinessIdentifier` and every module identifier after it consume
 * this type rather than inventing their own format, validation, or time source.
 *
 * **Every reachable instance is a valid version 7 UUID with the RFC variant.**
 * There is exactly one path from text — {@see self::fromString()} — and it
 * validates before constructing, so an instance is never observable in an
 * invalid state. The constructor is private for that reason.
 *
 * **The value is canonical.** {@see self::toString()} always returns the
 * lowercase hyphenated `8-4-4-4-12` form. Hexadecimal digits are accepted in
 * either case on input, as RFC 9562 §4 requires, and are normalised to
 * lowercase on the creation path — never during comparison, because a
 * comparison-time fix-up is what breaks transitivity (PF-042).
 *
 * **A UUIDv7 is time-sortable, and that word is chosen precisely. It is the
 * whole of what this type promises about time:**
 *
 * - Two values whose encoded millisecond timestamps differ sort by those
 *   timestamps, both lexically as canonical strings and bytewise.
 * - **Two values created within the same millisecond have no defined relative
 *   order.** Their ordering is whatever their random bits happen to produce.
 * - **No monotonicity is promised**, in any scope. This type holds no counter,
 *   no sequence, and no state between values.
 * - **Clock rollback is permitted and unguarded.** The generating clock is a
 *   wall clock that may be corrected backwards (PF-047), so a value created
 *   later may sort before one created earlier. Nothing here detects, corrects,
 *   or reports that.
 * - **No global, cross-process, or cross-host ordering is promised.** There is
 *   no coordination, no shared state, and no node identifier.
 *
 * **Uniqueness is probabilistic, not proven.** Seventy-four bits of
 * cryptographically secure randomness per millisecond make a collision
 * negligibly unlikely; they do not make one impossible. Nothing here claims
 * exactly-once generation or collision impossibility.
 *
 * **A UUIDv7 is an identifier, never a secret.** Its 48-bit timestamp prefix is
 * fully predictable by construction, and every value discloses its creation
 * time to millisecond precision to anyone holding it. It is never a session
 * token, API credential, magic link, recovery code, webhook secret, capability,
 * or proof of identity, and **possessing one is never an authorization
 * decision** — authorization stays a server-side composition, and Ethical Wall
 * decisions remain Practice Management's alone.
 *
 * **This type is not** a serialization, persistence, transport, stringification,
 * hashing, ordering, or timestamp-extraction API. It declares no `__toString()`
 * and does not implement `\Stringable`, deliberately: implicit stringification
 * is how an identifier reaches a log line, an exception message, a URL, or a
 * concatenated query with nobody having decided to put it there. Rendering is an
 * explicit act through {@see self::toString()}.
 *
 * Breaking changes to this published contract require explicit human approval.
 */
final readonly class UuidV7 implements ValueObject
{
    /**
     * The canonical form, and the whole of what this type accepts.
     *
     * One expression enforces every rule at once: exact length, hyphens in
     * exactly the canonical positions, hexadecimal digits only, the version
     * nibble `7`, and an RFC variant nibble (`8`, `9`, `a`, or `b`). It
     * therefore also rejects the nil UUID, the max UUID, every other version,
     * every reserved variant, brace-wrapped and `urn:uuid:` forms, and the
     * unhyphenated 32-character form, without needing a separate check for any
     * of them.
     *
     * `\A` and `\z` rather than `^` and `$`: PCRE's `$` also matches before a
     * trailing newline, which would let `"…-5b85bd74d75f\n"` through.
     *
     * The `i` modifier is what accepts uppercase hexadecimal on input; the
     * matched value is lowercased before it is stored.
     */
    private const CANONICAL_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i';

    /**
     * @param  string  $value  Canonical lowercase form, already validated.
     */
    private function __construct(private string $value) {}

    /**
     * The UUIDv7 the given canonical text denotes.
     *
     * Accepts the canonical hyphenated `8-4-4-4-12` form with hexadecimal
     * digits in either case, and nothing else. Surrounding whitespace, braces,
     * a `urn:uuid:` prefix, the unhyphenated form, the nil and max UUIDs, any
     * version other than 7, and any non-RFC variant are all rejected.
     *
     * **The rejected text never appears in the exception message.** It arrives
     * from outside the domain, may be attacker-controlled, and must not be
     * carried into a diagnostic that could reach a log.
     *
     * @throws InvalidArgument if $uuid is not canonical version 7 UUID text
     */
    public static function fromString(string $uuid): self
    {
        if (preg_match(self::CANONICAL_PATTERN, $uuid) !== 1) {
            throw new InvalidArgument(
                'The supplied value is not a canonical RFC 9562 version 7 UUID.',
            );
        }

        return new self(strtolower($uuid));
    }

    /**
     * The canonical lowercase hyphenated representation.
     *
     * Named rather than implicit, so every place a UUID becomes text is a
     * deliberate call a reviewer can see.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Whether this identifier equals $other by value (PF-042).
     *
     * Exact runtime type, then canonical value. This class is `final`, so it
     * can have no subclass and `instanceof self` is exact-class-correct here —
     * a property of a leaf class, not a technique that generalises. A
     * differently typed value object is unequal rather than incomparable: the
     * comparison returns `false` and never throws a `\TypeError`.
     *
     * Comparison never canonicalises. Both operands were canonicalised on their
     * own creation path, which is what keeps equality transitive.
     *
     * **Not constant-time, and no timing guarantee is made** — a UUID is an
     * identifier, not secret material, so ordinary short-circuiting comparison
     * is correct here.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }
}
