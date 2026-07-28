<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Model;

use App\Foundation\Domain\Identity\BusinessIdentifier;

/**
 * The base every OneLegalPro domain entity extends (PF-041).
 *
 * An entity is a thing with **stable identity**: two entities are the same
 * entity when they are the same type and carry the same identifier — never
 * because their attributes happen to match, and never ceasing to be the same
 * entity when an attribute changes. That is exactly the distinction
 * {@see ValueObject} draws from the other side — a value object has value
 * equality, not entity identity — and it is the whole of this contract.
 *
 * **`Entity` does not implement {@see ValueObject}, deliberately.** Value
 * equality and identity equality are different relations, and a type offering
 * both would let a caller pick the wrong one for a given comparison.
 *
 * **Not `readonly`, and that is a permanent architectural trade-off, not a
 * free choice.** `readonly` is viral in PHP, so a readonly base would make
 * every entity on the platform permanently immutable. A non-readonly base
 * instead **permits** mutable lifecycle state and **permanently prohibits
 * `readonly` entity subclasses** — PHP forbids a readonly class extending a
 * non-readonly one, even when the parent declares no property of its own. An
 * entity is not required to be mutable: an immutable entity is expressed with
 * private properties and no mutators, never with the `readonly` modifier.
 * Reversing this later would be a breaking change requiring explicit human
 * approval.
 *
 * **Identity is stable, not unforgeable.** Once constructed, an instance's
 * identifier can never be replaced — a within-instance guarantee only.
 * {@see BusinessIdentifier::fromString()} permits reconstructing an *equal
 * identifier value* from its canonical text — it says nothing about whether an
 * *entity* carrying that identifier can be constructed: that is a decision the
 * owning module's own construction rules make, not this base. **"Same
 * identity" is never proof of provenance, authenticity, ownership,
 * authorization, or entitlement**, and comparing two entities performs no
 * tenant, ownership, or Ethical Wall authorization.
 *
 * **This class owns identity storage and identity comparison, and nothing
 * else.** No state beyond the identifier, no mutation helper, no change
 * tracking, no timestamp, no version, no event recording, no persistence hook.
 * Domain events belong to PF-043, aggregate versioning and event release to
 * PF-040, and every other concern to the module that owns it.
 *
 * **This class adds no custom stringification, serialization, or debugging
 * API.** It declares no `__toString()`, implements neither `\Stringable` nor
 * `\JsonSerializable`, and defines no `__debugInfo()`, `toArray()`, or dump
 * helper — a deliberate divergence from PF-044's `BusinessIdentifier`, which
 * carries an approved `__toString()`. **The absence of these members is not a
 * confidentiality control**: standard PHP debugging, `var_dump()`,
 * reflection, or careless logging can still expose an entity's internal
 * state regardless of what this base declares. An entity must never be
 * dumped, interpolated, serialized, or logged as though this base made that
 * safe — that discipline belongs to the calling code and the owning module,
 * not to the absence of these members. `BusinessIdentifier` is not a secret,
 * but an identifier can still be sensitive metadata and is not automatically
 * safe to log or disclose; its approved `__toString()` does not make
 * identifier text safe to log, disclose, or use for authorization.
 *
 * **The template is invariant, deliberately, and `Entity<*>` is the chosen,
 * verified comparison annotation.** `Entity<*>` on {@see self::sameIdentityAs()}
 * accepts any concrete `Entity` template argument while leaving the class
 * template itself invariant. Other forms may also satisfy PHPStan — declaring
 * `@template-covariant` also passes, because the constructor is exempt from
 * its variance check — but each was rejected on its own ground rather than for
 * failing to type-check: `@template-covariant` would permanently forbid any
 * future method that consumes the template in a parameter, which `Entity<*>`
 * does not foreclose. `Entity<BusinessIdentifier>` fails outright at level 5,
 * because the template is invariant and it rejects *every* concrete entity,
 * including comparing an entity with itself. `Entity<mixed>` fails because
 * `mixed` is not a subtype of the `of BusinessIdentifier` bound. `self` inside
 * this generic base infers as the bare, unparametrized `Entity`, producing the
 * identical missing-generics diagnostic from level 6, so it is not an
 * alternative either. `@template-covariant` is not declared here, and neither
 * `Entity<BusinessIdentifier>` nor `Entity<mixed>` is used for
 * {@see self::sameIdentityAs()}'s parameter.
 *
 * **Every concrete entity must declare `@extends Entity<ConcreteIdentifier>`.**
 * This is a static-analysis-only guarantee — PHP has no runtime generics — but
 * it also delivers construction-site checking: PHPStan flags a wrong-subtype
 * argument passed to a subclass's own constructor once `@extends` is declared,
 * independent of that constructor's native parameter type. A missing
 * `@extends` is reported only from level 6, not from this project's level 5,
 * so its presence is a standing **review obligation** on every later entity
 * story, not something the current toolchain enforces. A concrete entity's own
 * constructor should additionally narrow its native parameter type to the
 * exact identifier subtype it accepts — recommended for the runtime
 * enforcement that PHPDoc generics cannot provide, not load-bearing for the
 * static guarantee above.
 *
 * Breaking changes to this published contract require explicit human approval.
 *
 * @template TIdentifier of BusinessIdentifier
 */
abstract class Entity
{
    /**
     * Identity is supplied once, at construction, and can never be replaced —
     * not by a subclass, not by this class, not through reflection.
     *
     * Deliberately **not** `final`: an entity legitimately carries its own
     * state, so a subclass declares its own constructor and calls
     * `parent::__construct($id)`. This diverges from PF-044
     * `BusinessIdentifier`'s `final private` constructor, and deliberately so
     * — an identifier is a closed value, an entity is not.
     *
     * **A subclass that omits `parent::__construct()` fails latently, not
     * immediately.** The object constructs successfully and remains usable;
     * only when {@see self::id()} or {@see self::sameIdentityAs()} is first
     * reached does PHP raise `\Error: Typed property must not be accessed
     * before initialization`. No guard, null check, or nullable type may
     * soften this — each would trade a loud late failure for a silent wrong
     * one. Every concrete entity must therefore have a test that constructs it
     * and then reads `id()`.
     *
     * **A subclass may also declare its own property named `id`.** PHP keeps
     * the two separately — distinct mangled property names — and this
     * property still governs `id()` and `sameIdentityAs()` regardless; the
     * subclass can neither access nor replace it. Shadowing is therefore
     * harmless to this contract but confusing in `var_dump` and array casts,
     * and is prohibited by convention, not by the language.
     *
     * @param  TIdentifier  $id
     */
    protected function __construct(
        private readonly BusinessIdentifier $id,
    ) {}

    /**
     * This entity's identifier.
     *
     * The native return type is the base identifier, because PHP has no
     * runtime generics; `@return TIdentifier` is what narrows it to the exact
     * identifier type a concrete entity declared through `@extends`.
     *
     * @return TIdentifier
     */
    final public function id(): BusinessIdentifier
    {
        return $this->id;
    }

    /**
     * Whether $other is the same entity as this one.
     *
     * Deliberately not named `equals()`: that name belongs to
     * {@see ValueObject} and means something else — value equality, not
     * identity.
     *
     * **Exact runtime entity class first, then identifier equality.** The
     * entity-class check is not redundant: nothing prevents two entity types
     * from legitimately sharing an identifier type, and identifier equality
     * alone would conflate them. Identifier comparison then delegates to
     * {@see BusinessIdentifier::equals()}, which itself compares exact
     * identifier class before canonical value — so a differently typed
     * identifier can never produce a false positive.
     *
     * **Entity state never participates.** Two instances of one entity type
     * carrying one identifier are the same entity however far their
     * attributes have drifted.
     *
     * An unrelated or differently typed entity is not the same entity: this
     * returns `false` and never throws a `\TypeError`. The relation is total,
     * reflexive, symmetric, transitive, strict, and non-coercive.
     *
     * **This is not an authorization check, and it carries no timing
     * guarantee** — an identifier is not secret material.
     *
     * @param  Entity<*>  $other
     */
    final public function sameIdentityAs(Entity $other): bool
    {
        return $other::class === $this::class
            && $other->id->equals($this->id);
    }
}
