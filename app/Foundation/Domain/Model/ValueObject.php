<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Model;

/**
 * The contract every OneLegalPro value object satisfies (PF-042).
 *
 * A value object has **value equality, not entity identity**: two instances
 * carrying the same value are interchangeable, and neither is "the" one. This
 * is what distinguishes it from an entity, whose equality is identity-based,
 * and it is the whole of this contract.
 *
 * **Implementations are immutable.** An operation that would change a value
 * returns a new instance; it never edits an existing one. Declaring an
 * implementation `final readonly class` is recommended wherever the concrete
 * type's own requirements permit, because `readonly` is the only mechanical
 * immutability enforcement the language offers. It is deliberately **not**
 * imposed here: this contract is an interface precisely so that each value
 * object keeps that choice, and its inheritance slot, for itself. Note that
 * `readonly` is shallow — a property holding a mutable object still permits
 * that object's interior to change — so hold immutable values only.
 *
 * **Every approved creation or reconstitution path enforces the concrete
 * type's invariants, and a value object is never observable in an invalid
 * state.** Whether creation happens through a public constructor, a named
 * constructor, a factory, or another explicit mechanism is decided by the
 * story that owns the concrete type; this contract requires none of them and
 * forbids none of them. Implementations report failure through the PF-049
 * Foundation exception taxonomy — `InvalidArgument` for an unacceptable
 * supplied argument, `InvariantViolation` for a broken domain guarantee —
 * which this contract references by convention only: it imports nothing and
 * depends on nothing. This interface itself throws nothing, and supplies no
 * factory, no primitive-reconstitution API, and no comparison implementation.
 *
 * **This contract is not** a serialization, persistence, transport,
 * stringification, hashing, ordering, or localization API. Whether a given
 * value object is stringable, serializable, loggable, or safe to send outside
 * the platform is decided by the type that owns it — never assumed here, and
 * never granted implicitly by satisfying this contract.
 *
 * Breaking changes to this published contract require explicit human approval.
 */
interface ValueObject
{
    /**
     * Whether this value equals $other by value.
     *
     * The contract every implementation satisfies:
     *
     * - **Exact runtime type.** Differently typed value objects are unequal.
     *   A cross-class comparison returns `false`; it never throws a
     *   `TypeError`, so two identifiers of different types are unequal rather
     *   than incomparable. A `final` leaf implementation may express this as
     *   `$other instanceof self`, which is exact there only because such a
     *   class can have no subclasses — **that technique is not universally
     *   sufficient.** An inheritable base that implements this method on
     *   behalf of its subclasses must compare the runtime classes explicitly,
     *   for example `$other::class === $this::class`, before comparing value
     *   state; otherwise equality stops being symmetric across a hierarchy.
     * - **Total, reflexive, symmetric, and transitive.** `$a->equals($a)` is
     *   always `true`, and `$a->equals($b) === $b->equals($a)` always holds.
     * - **Strict and non-coercive.** The string `'1'` never equals the integer
     *   `1`. Canonical form is established by the concrete value object's own
     *   creation path — it is never repaired during comparison, because a
     *   comparison-time fix-up is what breaks transitivity.
     * - **Value state only.** A cache, a memoized rendering, or any other
     *   derived property never participates.
     *
     * **This is not a constant-time comparison, and no timing guarantee is
     * made.** Ordinary equality short-circuits. A value object holding secret
     * or privileged material must implement this method under its own
     * explicitly approved comparison discipline — `hash_equals()` or an
     * equivalent — and should preferably not retain retrievable secret
     * material at all.
     */
    public function equals(ValueObject $other): bool;
}
