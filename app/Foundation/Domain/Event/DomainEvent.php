<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Event;

use App\Foundation\Domain\Identity\UuidV7;

/**
 * The contract every OneLegalPro domain event satisfies (PF-043).
 *
 * "Something happened" has exactly one shared shape: a distinct, stable
 * occurrence identity, and the UTC instant it occurred at. Everything else —
 * event name, version, payload, correlation, causation, actor, Firm,
 * dispatch, or delivery — varies per event or belongs to a later layer, and
 * is deliberately absent from this contract.
 *
 * **An interface, not an abstract class, and the reasoning is permanent.**
 * PHP's `readonly` inheritance is viral and bidirectionally exclusive — the
 * same reasoning `app/Foundation/README.md` already records as PF-042's
 * permanent grounds for rejecting an abstract `ValueObject` base. An abstract
 * base here would have forced *every* platform event to be `readonly`, or
 * forbidden *every* one of them from being `readonly`, permanently; it would
 * additionally have consumed each module event's single `extends` slot and
 * imposed one base constructor shape on every event, where the approved
 * convention leaves each concrete type's creation path to its own story.
 * **This contract must also never extend `App\Foundation\Domain\Model\Entity`:**
 * `Entity` is deliberately not `readonly`, and PHP forbids a `readonly` class
 * extending a non-`readonly` one, so an event derived from `Entity` could
 * never be `final readonly` — a decisive technical disqualification, not a
 * preference.
 *
 * **Event-identity semantics.** {@see self::eventId()} supplies **stable
 * occurrence identity: identity, not value equality, distinguishes
 * occurrences**, so two events carrying identical payload data are different
 * occurrences. It returns **the existing {@see UuidV7} technical identifier
 * primitive directly** — an event occurrence identifier is technical
 * occurrence identity, not a business identifier, so this contract creates no
 * new identifier type of any kind: no `DomainEventId`, and no concrete
 * `BusinessIdentifier` subclass. **Within one in-memory event object,
 * `eventId()` returns the exact `UuidV7` instance supplied at construction** —
 * nothing regenerates, re-parses, or substitutes it. **Across outbox
 * persistence, serialization, reconstitution, publication, translation, and
 * retries, what is preserved is the same canonical UUID *value*, not the same
 * PHP object instance** — a reconstituted `UuidV7` is legitimately a
 * different PHP object representing the same stable occurrence identity, and
 * equality at those boundaries is established by canonical text, never by
 * object identity. This contract ships no persistence, serialization, or
 * reconstitution path, so only the in-memory instance property is provable
 * here; cross-boundary value preservation is an obligation on later outbox,
 * publication, and `Integrations` stories.
 *
 * **Deduplication — a source of keys, not a key.** `eventId()` is the stable
 * occurrence identifier **from which** a later workflow, outbox, publication,
 * or integration layer may derive its own correlation or deduplication key.
 * This contract itself performs **no deduplication**, defines **no
 * deduplication store, consumer-idempotency contract, or delivery
 * mechanism**, and requires no downstream layer to reuse the raw internal
 * identifier verbatim. **Internal-to-external identifier mapping belongs to
 * `Integrations`**, and an internal identifier is never exposed externally
 * merely for convenience.
 *
 * **Occurrence-time semantics.** {@see self::occurredAt()} returns the
 * business instant in **UTC**. It **originates from the injected PF-047
 * `Clock`**, read once per logical operation; a concrete event **receives it
 * as constructor data and never reads ambient time or holds a `Clock`** —
 * and, symmetrically, never holds a `UuidV7Generator` either, since its
 * identifier is likewise supplied, not self-generated. Concrete events return
 * an instance whose **exact runtime class is plain native
 * `\DateTimeImmutable`** — never `Carbon\CarbonImmutable` or any other
 * framework or vendor subclass. **This interface can enforce none of that
 * mechanically** — not UTC, not the plain-`\DateTimeImmutable` rule, not
 * `final readonly`, not payload safety: PHP return-type covariance lets a
 * subclass satisfy a declared return type, so each is a **review obligation
 * and a per-concrete-event test obligation**, not an enforced property.
 * **Concrete module-owned events are `final readonly` classes by
 * architectural convention**, and each concrete event's own test verifies
 * these obligations for itself.
 *
 * **No guarantee of exactly-once delivery, ordering, monotonicity,
 * authenticity, provenance, authorization, or non-repudiation is made by this
 * contract, ever.** `Clock` is a wall clock that may be corrected backwards,
 * so two events may share an instant and a later event may carry an earlier
 * one. Satisfying this contract makes no event safe to log, dump, serialize,
 * expose, or publish, and the absence of stringification and serialization
 * members is not a confidentiality control.
 *
 * **`eventId()` is an identifier, never a secret, capability, or proof of
 * provenance, authenticity, ownership, or entitlement — receiving or
 * possessing an event or its identifier grants no authorization.** A
 * `UuidV7` additionally exposes its own generation time to millisecond
 * precision to anyone holding it, by construction; that generation timestamp
 * is **additional, potentially sensitive technical metadata carried
 * alongside `occurredAt()`, not a duplicate of it**, and the two may differ
 * and must never be assumed equal.
 *
 * **Internal domain events are never automatically a public integration
 * contract.** A stable, versioned integration envelope is authored
 * deliberately for external delivery by `Integrations`; confidential fields
 * are never carried into it merely because they exist on the internal event.
 *
 * Breaking changes to this published contract require explicit human
 * approval.
 */
interface DomainEvent
{
    /**
     * This event's stable occurrence identity.
     *
     * Never a time source, ordering key, authorization input, or proof of
     * provenance or authenticity.
     */
    public function eventId(): UuidV7;

    /**
     * The UTC instant this event occurred at.
     *
     * Never a sort key, deduplication key, or idempotency key, and never a
     * substitute for the four other, later times — recorded-at, committed-at,
     * published-at, delivered-at — this contract never conflates with it.
     */
    public function occurredAt(): \DateTimeImmutable;
}
