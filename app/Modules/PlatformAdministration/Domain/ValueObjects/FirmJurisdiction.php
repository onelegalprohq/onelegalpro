<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\ValueObjects;

use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Exception\InvariantViolation;
use App\Foundation\Domain\Model\ValueObject;

/**
 * A `Firm`'s jurisdiction reference (PA-001) — **opaque, and nothing more**.
 *
 * **This is an identifier-only cross-bounded-context reference, not a resolved
 * record and not a country code.** `Jurisdiction` as an authoritative record —
 * its identifier contract, code, name, official languages, scope, and default
 * policy — belongs to **Legal Intelligence**, and that story does not exist
 * yet. This type therefore resolves nothing, seeds nothing, and **defines no
 * jurisdiction identifier contract on Legal Intelligence's behalf**. Such a
 * reference may legitimately be unresolvable, and no foreign key crosses a
 * context boundary.
 *
 * **A jurisdiction is not synonymous with a country, and this type must not
 * silently foreclose one that is not.** No approved source declares that a
 * jurisdiction identifier is permanently an ISO alpha-2 country code, so this
 * is **not a country-code type** and is **not constrained to two characters**.
 * Future national, subnational, court-system, regulatory, and other separately
 * approved jurisdiction identifiers must remain expressible. `TH` — the seeded
 * Thailand default in the Legal Intelligence architecture — **is a valid
 * example of the shape, never the universal shape**; `TH-BKK` and a future
 * court-system or regulatory code satisfy it equally, and nothing in this type
 * prefers one form over another.
 *
 * **The shape is the minimum needed to reject a malformed value, and is
 * deliberately never enough to assign meaning:**
 *
 * - **character set** — uppercase ASCII `A`–`Z`, ASCII digits `0`–`9`, and the
 *   ASCII hyphen `-` as a **non-semantic separator**;
 * - **length** — at least {@see self::MINIMUM_LENGTH} and at most
 *   {@see self::MAXIMUM_LENGTH} characters;
 * - **structure** — no leading hyphen, no trailing hyphen, no consecutive
 *   hyphens; no whitespace, no other punctuation, no non-ASCII character;
 * - **canonicalization** — input is trimmed and uppercased **on the creation
 *   path only, never during comparison**, so equality stays transitive.
 *
 * These bounds carry **no architectural authority and no semantics**. The
 * technical owner selected them rather than leaving them to an implementation
 * author. **Widening them later is additive** and anticipated; **narrowing
 * them would be a breaking change to a published type** requiring explicit
 * human approval.
 *
 * **No seeded list, no country table, no allow-list, no registry lookup, and
 * no Thailand special case.** **The code is never parsed**: no component,
 * prefix, suffix, or hyphen-delimited segment is extracted, interpreted, or
 * exposed, and nothing here derives a country, region, court, language, tax
 * treatment, or regulatory meaning from it. **Equality compares the canonical
 * opaque value only** and resolves no Legal Intelligence record.
 *
 * Holding one of these proves nothing about a `Jurisdiction` existing and
 * grants nothing. When Legal Intelligence delivers its own `Jurisdiction`, the
 * authoritative contract is **its** decision; this reference is shaped so that
 * adopting it is a mapping exercise rather than a redesign.
 *
 * **Failures are fail-closed and disclose nothing.** Nothing is defaulted,
 * coerced, truncated, or approximated into validity. Every rejection throws
 * through the PF-049 taxonomy, and **no exception message, code, or property
 * contains the rejected value**.
 */
final readonly class FirmJurisdiction implements ValueObject
{
    /**
     * The fewest characters a canonical jurisdiction reference may have.
     */
    public const int MINIMUM_LENGTH = 2;

    /**
     * The most characters a canonical jurisdiction reference may have.
     */
    public const int MAXIMUM_LENGTH = 32;

    /**
     * The one canonical shape a jurisdiction reference may take.
     *
     * One expression covers the character set **and** all three hyphen rules
     * at once: `[A-Z0-9]+` requires a non-empty run before any hyphen (so no
     * leading hyphen) and after the last one (so no trailing hyphen), and
     * `(-[A-Z0-9]+)*` requires at least one permitted character between
     * successive hyphens (so no consecutive hyphens). Whitespace, other
     * punctuation, and every non-ASCII character fall outside it and are
     * refused. Expressing it once means the three structural rules can never
     * drift apart from one another.
     */
    private const string CANONICAL_PATTERN = '/\A[A-Z0-9]+(-[A-Z0-9]+)*\z/';

    /**
     * Private, so {@see self::fromString()} is the only path to an instance
     * and no caller can construct one that skipped canonicalization.
     */
    private function __construct(private string $value) {}

    /**
     * The opaque jurisdiction reference the given text denotes.
     *
     * UTF-8 validity is checked first so a malformed byte sequence is rejected
     * as such rather than surviving into a pattern match. Trimming uses the
     * Unicode-aware `\s` class, so a non-ASCII surrounding space is removed
     * too; uppercasing is `strtoupper()`, which is byte-wise over ASCII and
     * therefore locale-independent — anything it leaves untouched is not a
     * permitted character and the pattern refuses it next.
     *
     * The length bound is applied to the canonical value, where one character
     * is one byte because the pattern already guarantees ASCII.
     *
     * @throws InvalidArgument when the supplied text is not acceptable input
     * @throws InvariantViolation when the input cannot be trimmed
     */
    public static function fromString(string $jurisdiction): self
    {
        if (preg_match('//u', $jurisdiction) !== 1) {
            throw new InvalidArgument('A Firm jurisdiction reference must be valid UTF-8.');
        }

        $trimmed = preg_replace('/^\s+|\s+$/u', '', $jurisdiction);

        if (! \is_string($trimmed)) {
            throw new InvariantViolation('A Firm jurisdiction reference could not be trimmed.');
        }

        $canonical = strtoupper($trimmed);

        if (preg_match(self::CANONICAL_PATTERN, $canonical) !== 1) {
            throw new InvalidArgument(
                'A Firm jurisdiction reference must contain only uppercase ASCII letters, digits, '
                .'and non-leading, non-trailing, non-consecutive ASCII hyphens.',
            );
        }

        $length = \strlen($canonical);

        if ($length < self::MINIMUM_LENGTH || $length > self::MAXIMUM_LENGTH) {
            throw new InvalidArgument(
                'A Firm jurisdiction reference must be between '
                .self::MINIMUM_LENGTH.' and '.self::MAXIMUM_LENGTH.' characters.',
            );
        }

        return new self($canonical);
    }

    /**
     * The canonical opaque reference.
     *
     * Deliberately named for what it is — a value — rather than for anything
     * it might be taken to mean. There is **no** `country()`, `region()`,
     * `court()`, `language()`, `taxTreatment()`, `segments()`, `prefix()`,
     * `suffix()`, or `parse()` accessor, and none may be added here: any of
     * them would assign meaning this reference deliberately does not carry and
     * would pre-empt Legal Intelligence's own contract.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Whether this reference equals $other (PF-042).
     *
     * **Exact runtime class first, canonical value second.** Because this
     * class is `final`, `instanceof self` *is* the exact-class test — no
     * subtype can exist to satisfy it loosely — so a separate
     * `$other::class === $this::class` comparison would be provably redundant
     * rather than an additional guarantee. **Should this class ever stop being
     * `final`, that comparison becomes mandatory.**
     *
     * A differently typed value object is unequal rather than incomparable:
     * this returns `false` and never throws a `\TypeError`.
     *
     * **Comparison never canonicalizes**, because both operands were
     * canonicalized on their own creation paths — which is what keeps the
     * relation transitive. It resolves nothing in Legal Intelligence and
     * proves nothing about any `Jurisdiction` existing.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->value === $this->value;
    }
}
