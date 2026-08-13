<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\ValueObjects;

use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Exception\InvariantViolation;
use App\Foundation\Domain\Model\ValueObject;

/**
 * A `Firm`'s canonical name (PA-001).
 *
 * **One canonical string, canonicalized once on the creation path.** The
 * single named constructor {@see self::fromString()} trims, rejects any
 * Unicode control character, applies Unicode NFC normalization, rejects an
 * empty canonical result, and bounds the length **in code points** — so a
 * Thai-script name is measured and accepted correctly by construction rather
 * than by a byte count that would reject it. Nothing is canonicalized during
 * comparison, which is what keeps equality transitive.
 *
 * **This type carries no uniqueness of any kind, and none may ever be added.**
 * That is a security decision, not an omission. Referential-integrity and
 * unique-constraint checks run with row security suspended, so a global unique
 * constraint on a law firm's name would return a violation to a caller who
 * cannot see the conflicting row — an existence disclosure across a conflicts
 * boundary. **The defence is the constraint's absence, not the caller's
 * visibility.** The platform-generated-identifier exception covers `id` alone
 * and never extends to a business attribute.
 *
 * **Length bounds, and what they are worth.** Between
 * {@see self::MINIMUM_LENGTH} and {@see self::MAXIMUM_LENGTH} code points
 * inclusive. They carry **no architectural authority and no semantics** —
 * they exist solely to reject a malformed value. Widening them later is
 * additive (no previously accepted value becomes invalid) and is anticipated;
 * **narrowing them would be a breaking change to a published type** requiring
 * explicit human approval.
 *
 * **What this type deliberately does not do.** It provides no collation, no
 * sorting, no locale-aware comparison, no case folding, no rendering, no
 * display formatting, no persistence, no serialization, and no
 * `__toString()`. Collation, sorting, locale-aware comparison, and rendering
 * remain EPIC-012 story 28's; persistence is `PA-007`'s.
 *
 * **Failures are fail-closed and disclose nothing.** Nothing is defaulted,
 * coerced, truncated, or approximated into validity, and no partially
 * constructed instance is ever observable. Every rejection throws through the
 * PF-049 taxonomy, and **no exception message, code, or property contains the
 * rejected input**. Messages are developer-facing diagnostics and are never
 * exposed verbatim to an external caller.
 */
final readonly class FirmName implements ValueObject
{
    /**
     * The fewest code points a canonical Firm name may have.
     *
     * One, which follows from the empty-result rejection rather than adding a
     * second rule on top of it.
     */
    public const int MINIMUM_LENGTH = 1;

    /**
     * The most code points a canonical Firm name may have.
     *
     * Selected by the technical owner rather than left to an implementation
     * author, on the same reasoning the jurisdiction shape records: fixing it
     * silently would create an unsupported permanent contract. Generous enough
     * for a Thai- or English-script law firm name, and deliberately not a
     * `varchar` figure — PA-001 delivers no persistence, so no column width
     * justifies a number here.
     */
    public const int MAXIMUM_LENGTH = 200;

    /**
     * Private, so {@see self::fromString()} is the only path to an instance
     * and no caller can construct one that skipped canonicalization.
     *
     * The stored value is always already trimmed, control-character-free,
     * NFC-normalized, non-empty, and within bounds.
     */
    private function __construct(private string $value) {}

    /**
     * The canonical Firm name the given text denotes.
     *
     * The steps run in exactly this order, and the order matters:
     *
     * 1. **UTF-8 validity**, first. Every later step reads the input as
     *    Unicode; a malformed byte sequence would make `preg_*` fail silently
     *    and `Normalizer` return `false`, so it is rejected up front rather
     *    than diagnosed later as some other fault.
     * 2. **Trim**, using the Unicode-aware `\s` class rather than `trim()`'s
     *    fixed ASCII set, so an ideographic or non-breaking surrounding space
     *    is removed too. Leading and trailing only — interior spacing is part
     *    of the name.
     * 3. **Reject any Unicode control character** (`\p{Cc}`). Trimming first
     *    is deliberate: a trailing newline is surrounding whitespace and is
     *    removed, while a control character *inside* the name is a malformed
     *    value and is refused.
     * 4. **NFC normalization**, so two byte sequences that denote the same
     *    text produce the same canonical value and therefore compare equal.
     * 5. **Reject an empty canonical result**, which is what a
     *    whitespace-only input becomes.
     * 6. **Bound the length in code points**, never in bytes.
     *
     * @throws InvalidArgument when the supplied text is not acceptable input
     * @throws InvariantViolation when normalization cannot be completed
     */
    public static function fromString(string $name): self
    {
        if (preg_match('//u', $name) !== 1) {
            throw new InvalidArgument('A Firm name must be valid UTF-8.');
        }

        $trimmed = preg_replace('/^\s+|\s+$/u', '', $name);

        if (! \is_string($trimmed)) {
            throw new InvariantViolation('A Firm name could not be trimmed.');
        }

        if (preg_match('/\p{Cc}/u', $trimmed) === 1) {
            throw new InvalidArgument('A Firm name must contain no Unicode control character.');
        }

        $canonical = \Normalizer::normalize($trimmed, \Normalizer::FORM_C);

        // Fail closed. The un-normalized value is never used as a fallback:
        // storing it would mean two equal names could compare unequal, which
        // is exactly the defect normalization exists to prevent.
        if (! \is_string($canonical)) {
            throw new InvariantViolation('A Firm name could not be normalized to Unicode NFC.');
        }

        // Measured in code points, never bytes: a Thai-script name is many
        // bytes per character, and a byte count would reject a valid name.
        // Measured before the emptiness check so the bound below is enforced
        // as its own independent rule rather than as a consequence of it.
        $length = mb_strlen($canonical, 'UTF-8');

        if ($canonical === '') {
            throw new InvalidArgument('A Firm name must not be empty once canonicalized.');
        }

        if ($length < self::MINIMUM_LENGTH || $length > self::MAXIMUM_LENGTH) {
            throw new InvalidArgument(
                'A Firm name must be between '.self::MINIMUM_LENGTH.' and '.self::MAXIMUM_LENGTH.' code points.',
            );
        }

        return new self($canonical);
    }

    /**
     * The canonical name.
     *
     * Deliberately not named `toString()` and deliberately not exposed through
     * `__toString()`: this is the canonical domain value, not a serialization,
     * display, or transport representation, and no such representation is
     * authorized by PA-001.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Whether this name equals $other (PF-042).
     *
     * **Exact runtime class first, canonical value second.** Because this
     * class is `final`, `instanceof self` *is* the exact-class test — no
     * subtype can exist to satisfy it loosely — so a separate
     * `$other::class === $this::class` comparison would be provably redundant
     * rather than an additional guarantee. **Should this class ever stop being
     * `final`, that comparison becomes mandatory**, exactly as
     * `BusinessIdentifier` needs it: `instanceof` alone on an inheritable type
     * would make every subtype equal to its base.
     *
     * A differently typed value object is unequal rather than incomparable:
     * this returns `false` and never throws a `\TypeError`.
     *
     * **Comparison never canonicalizes.** Both operands were canonicalized on
     * their own creation paths, which is what keeps the relation transitive. A
     * comparison that normalized or trimmed here would make equality depend on
     * how a value was written rather than on what it is.
     *
     * This is a value comparison, not an identity, uniqueness, authorization,
     * or conflicts check, and it is **not constant-time**.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->value === $this->value;
    }
}
