<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Identity;

use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Exception\InvariantViolation;
use App\Foundation\Domain\Model\ValueObject;
use Random\RandomException;

/**
 * The base every OneLegalPro business identifier extends (PF-044).
 *
 * A business identifier is a UUIDv7 with a type. Two different concrete
 * identifier types may wrap the very same UUID and are still never equal,
 * because equality here compares the exact runtime class before it compares
 * any value. That is why this type exists: a bare {@see UuidV7} passed where a
 * specific identifier was meant is a mistake the type system cannot otherwise
 * see, and a value of one identifier type reaching a parameter expecting
 * another is a mistake this makes impossible.
 *
 * **It prevents accidental interchange — nothing more.** Deliberate
 * reconstruction across types, by taking one identifier's canonical text and
 * passing it to another type's {@see self::fromString()}, remains possible and
 * is not prevented here. Any type with a reconstitution path has that property
 * necessarily. **This class is not an authorization boundary, not a security
 * boundary, and not an ownership or referential-integrity control.**
 * Authorization, aggregate ownership, and referential integrity remain the
 * responsibility of the owning module's own approved stories and the
 * platform's separate controls.
 *
 * **Abstract and `readonly`, deliberately.** `readonly` is viral in PHP, so
 * every concrete identifier is necessarily immutable, and a `readonly` class
 * may declare no static property — so no identifier can acquire a static
 * generator, cache, registry, or counter.
 *
 * **Concrete identifiers are empty `final readonly` marker subclasses.** That
 * is an architectural rule, and the language enforces only part of it. PHP does
 * enforce the private base constructor, the protected construction seam, the
 * immutability of the stored `UuidV7`, and the finality of the base factories
 * and comparison. PHP does **not** make a subclass factory alias or an extra
 * subclass property technically impossible, so a future production leaf must,
 * by rule and by review, **add no state, no invariant, no constructor
 * parameter, no factory alias, and no behaviour**. **Foundation ships no
 * concrete identifier**, and PF-044 names none.
 *
 * **A business identifier is not a secret.** Possession proves neither identity
 * nor authorization, and the composed UUIDv7 discloses its approximate
 * creation time to anyone holding it — see {@see UuidV7}.
 *
 * **Validation is inherited whole from {@see UuidV7} and is not extended
 * here.** {@see self::fromString()} accepts any textual form `UuidV7` accepts,
 * including uppercase and mixed-case hexadecimal, and stores the canonical
 * lowercase value it returns. This class adds no nil rejection, no timestamp
 * policy, no tenant policy, no parsing policy, and no domain-specific
 * invariant of its own.
 *
 * **This type is not** a serialization, persistence, or transport API.
 * {@see self::toString()} exposes canonical text; it defines and authorizes no
 * database mapping, cast, DTO, event payload, route binding, or wire format.
 * Those belong to future repositories, adapters, and owning module stories.
 *
 * Breaking changes to this published contract require explicit human approval.
 */
abstract readonly class BusinessIdentifier implements ValueObject
{
    /**
     * `final` so no subclass can replace it: {@see self::fromUuid()} relies on
     * `new static(...)` resolving to exactly this signature, and a subclass
     * that declared its own constructor would break that at runtime. `private`
     * so the named constructors below are the only paths to an instance.
     *
     * The composed `UuidV7` is an encapsulated implementation detail. No
     * accessor exposes it; one may be added later only if an approved consumer
     * demonstrates a real requirement, which would be additive.
     */
    final private function __construct(private UuidV7 $value) {}

    /**
     * The identifier this UUID denotes, of the concrete class called.
     *
     * The construction seam: `protected`, so a subclass may build itself from
     * an already-valid `UuidV7` without the value ever becoming publicly
     * constructible from an arbitrary object.
     *
     * Never call this on `BusinessIdentifier` itself — the class is abstract,
     * and PHP raises `\Error: Cannot instantiate abstract class`.
     */
    final protected static function fromUuid(UuidV7 $value): static
    {
        return new static($value);
    }

    /**
     * The identifier the given UUID text denotes.
     *
     * Validation, accepted input forms, and canonicalization are entirely
     * {@see UuidV7::fromString()}'s: any textual representation that method
     * accepts is accepted here — hexadecimal in either case included — and the
     * canonical lowercase value it returns is what this identifier stores and
     * later emits. Nothing is validated, normalised, or rejected a second time.
     *
     * **The rejected text never appears in the exception message**, because no
     * message is constructed here; `UuidV7`'s own diagnostic, which excludes
     * the input, propagates untouched.
     *
     * Accepting text is a reconstitution path, not a claim of ownership over
     * any persistence or transport contract.
     *
     * @throws InvalidArgument if $value is not text `UuidV7` accepts
     */
    final public static function fromString(string $value): static
    {
        return static::fromUuid(UuidV7::fromString($value));
    }

    /**
     * A new identifier of the concrete class called, from the supplied
     * generator.
     *
     * The generator is a parameter rather than a collaborator this class
     * holds, so nothing here reads ambient time or ambient randomness and no
     * static factory, clock, or entropy source exists anywhere on the type.
     * `generate()` is called exactly once.
     *
     * `RandomException` propagates untranslated when the platform CSPRNG fails
     * — see {@see UuidV7Generator::generate()}.
     *
     * @throws RandomException
     * @throws InvariantViolation
     */
    final public static function generate(UuidV7Generator $generator): static
    {
        return static::fromUuid($generator->generate());
    }

    /**
     * The canonical lowercase hyphenated UUID text.
     *
     * It defines no database mapping, cast, DTO field, event payload, route
     * binding, or serialization format.
     */
    final public function toString(): string
    {
        return $this->value->toString();
    }

    /**
     * The same canonical text, for string context.
     *
     * Note that {@see UuidV7} deliberately declares no `__toString()`: a raw
     * UUID must never reach a log line or a URL without someone deciding to
     * put it there. A business identifier is a named domain type rather than a
     * raw value, and rendering it is an approved convenience here.
     * {@see self::toString()} remains the explicit form and is preferred
     * wherever the call site can name it.
     */
    final public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * The state equality compares, and the whole of it.
     *
     * The canonical text rather than the `UuidV7` object, so comparison is a
     * strict scalar comparison that cannot depend on object identity. Caches
     * and derived values never appear here, because there are none.
     *
     * @return list<string>
     */
    final protected function equalityComponents(): array
    {
        return [$this->value->toString()];
    }

    /**
     * Whether this identifier equals $other (PF-042).
     *
     * **Exact runtime class first, value second.** `instanceof self` alone
     * would be wrong here — this class is inheritable, so it would make every
     * identifier type equal to every other. `$other::class === $this::class` is
     * what keeps distinct identifier types distinct and keeps equality
     * symmetric across the hierarchy. The `instanceof` that precedes it narrows
     * the type; it never relaxes the class check.
     *
     * A differently typed value object is unequal rather than incomparable:
     * this returns `false` and never throws a `\TypeError`.
     *
     * Comparison never canonicalises. The stored value was canonicalised on the
     * creation path, which is what keeps equality transitive.
     *
     * **Not constant-time, and no timing guarantee is made** — an identifier is
     * not secret material.
     */
    final public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other::class === $this::class
            && $other->equalityComponents() === $this->equalityComponents();
    }
}
