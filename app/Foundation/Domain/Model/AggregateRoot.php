<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Model;

use App\Foundation\Domain\Event\DomainEvent;
use App\Foundation\Domain\Identity\BusinessIdentifier;

/**
 * The base every OneLegalPro aggregate root extends (PF-040).
 *
 * An aggregate root is an {@see Entity} that additionally **collects the domain
 * events its own behaviour produced**, so a later application layer can take
 * them and do something with them. That is the whole of this contract: one
 * in-memory buffer, one protected way to append to it, and one public way to
 * take everything and leave the buffer empty.
 *
 * **This class contains no aggregate version, and that is a decision rather
 * than an omission.** Optimistic-concurrency versioning is a **persistence
 * concern** — it exists to reconcile a write against what a store already
 * holds, which requires a store — so it belongs to a later, separately
 * approved persistence story, outside Foundation entirely. No `version`,
 * `expectedVersion`, `aggregateVersion`, `sequenceNumber`, or
 * `concurrencyToken` member exists here, and none may be added by a Foundation
 * story. {@see Entity}'s own docblock is corrected to say the same.
 *
 * **Two members, and deliberately only two.** {@see self::recordThat()} is
 * `protected`, because recording is the aggregate's own account of what it
 * did — a caller outside the aggregate must never be able to attribute an
 * event to it. {@see self::releaseEvents()} is `public`, because taking the
 * events is the surrounding layer's job. There is **no separate peek, read,
 * count, clear, flush, discard, or public recording method**: a reader that
 * could look without taking would invite two callers to publish the same
 * batch, and a clear that discarded without returning would silently destroy
 * recorded history. One combined take-and-clear operation makes the batch's
 * ownership unambiguous at every call site.
 *
 * **Recording appends, and inspects nothing.** {@see self::recordThat()}
 * performs **no validation, filtering, sorting, reordering, deduplication,
 * merging, capping, or rejection** of any kind. Two calls with the identical
 * event instance record it twice; two distinct events carrying identical data
 * are two entries. Whether that is meaningful is the recording aggregate's own
 * business rule, never this base's guess.
 *
 * **Order is recording order and nothing more.** {@see self::releaseEvents()}
 * returns events in the order they were recorded, because the buffer is an
 * insertion-ordered list. **That is not a chronological, causal, global,
 * cross-aggregate, cross-process, or delivery ordering guarantee, and must
 * never be read as one.** `DomainEvent::occurredAt()` comes from PF-047's wall
 * clock, which may be corrected backwards, so a later-recorded event may carry
 * an earlier instant — this base neither detects, corrects, nor reports that,
 * and never sorts by it. **No exactly-once, at-least-once, ordering, or
 * delivery claim of any kind is made here**, because this base delivers
 * nothing.
 *
 * **Release is a transfer of the whole batch, not a copy of it.** The buffer is
 * emptied as part of the same operation that returns its contents, so no
 * partial or half-released state is ever observable: a second release returns
 * an empty list, and anything recorded afterwards is a **new batch** that owes
 * nothing to the previous one. The returned value is a plain PHP array, so a
 * caller mutating it changes only its own copy and can never reach back into
 * the aggregate.
 *
 * **The buffer is never part of identity.** {@see Entity::sameIdentityAs()}
 * compares exact runtime entity class and identifier only; recording,
 * releasing, and re-recording change nothing about whether two aggregates are
 * the same aggregate. The buffer is also **per instance and private**: two
 * aggregates never see, share, or affect each other's events, and no static
 * property holds event state anywhere on this type.
 *
 * **This class dispatches nothing.** No dispatcher, listener, subscriber,
 * handler, bus, queue, job, or broadcaster; no transactional outbox, publisher,
 * consumer, retry, or deduplication store; no persistence, repository,
 * reconstitution, serialization, or wire format; no auditing, logging,
 * telemetry, tenancy, `FirmId`, actor, or authorization semantics. Dispatch,
 * the outbox, publication, and consumption are PF-090, PF-091, PF-092, and
 * PF-093 — separately approved Sprint 0.4 stories. **Reconstituting an
 * aggregate from storage must never route through {@see self::recordThat()}**:
 * replaying history is not new behaviour, and a reconstitution path that
 * recorded would re-publish the past. How reconstitution avoids that is the
 * owning persistence story's problem, not something this base can enforce.
 *
 * **Releasing events is not authorization, and an event is not safe merely
 * because it was recorded.** Possessing a released batch grants nothing;
 * `CheckEthicalWallAccess` remains Practice Management's alone. Events carry
 * identifiers and immutable snapshots only — never credentials, secrets,
 * document bytes, privileged narratives, or cross-Firm content — and the
 * absence of stringification and serialization members here is **not a
 * confidentiality control**: `var_dump()`, reflection, and careless logging
 * still expose a buffered event's full contents. **Satisfying this contract
 * makes no event safe to log, dump, serialize, expose, or publish.**
 *
 * **No constructor is declared**, deliberately: identity construction is
 * already {@see Entity}'s, and the buffer needs no initialization beyond its
 * default. A concrete aggregate declares its own constructor and calls
 * `parent::__construct($id)`, exactly as any entity does — and inherits
 * {@see Entity}'s documented latent-failure and property-shadowing
 * consequences unchanged.
 *
 * **Every concrete aggregate must declare `@extends AggregateRoot<ConcreteIdentifier>`**,
 * for the same static-analysis reasons and with the same review-obligation
 * caveat {@see Entity} records: a missing tag is reported only from PHPStan
 * level 6, not from this project's level 5.
 *
 * Breaking changes to this published contract require explicit human approval.
 *
 * @template TIdentifier of BusinessIdentifier
 *
 * @extends Entity<TIdentifier>
 */
abstract class AggregateRoot extends Entity
{
    /**
     * The events this aggregate has recorded and not yet released.
     *
     * **Private, non-static, and insertion-ordered.** Private so no subclass
     * can read, replace, reorder, or empty it behind the two approved members;
     * non-static so each instance owns its own batch; insertion-ordered
     * because recording order is the only order this base knows. It is
     * **never** `readonly` — a released buffer must be replaceable with an
     * empty one, which is the entire mechanism.
     *
     * @var list<DomainEvent>
     */
    private array $recordedEvents = [];

    /**
     * Record that something happened, appending it to this aggregate's buffer.
     *
     * `protected`, so only the aggregate itself may attribute an event to
     * itself, and `final`, so a subclass can neither weaken that visibility
     * nor slip validation, filtering, sorting, or deduplication into the
     * append. It appends unconditionally and returns nothing; the event is
     * retained as the exact instance supplied.
     *
     * Nothing is dispatched, persisted, published, or logged by this call.
     */
    final protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Take every event recorded since the last release, and empty the buffer.
     *
     * One combined operation, `public` and `final`: the caller receives the
     * batch in recording order and the aggregate is left holding nothing, so
     * the same batch can never be released twice. Calling this on an aggregate
     * that recorded nothing — or calling it again immediately — returns an
     * empty list rather than failing, because "no events" is a normal state
     * and not a broken invariant. Anything recorded afterwards forms a fresh
     * batch.
     *
     * The buffer is read and reset within this one operation, so no
     * partially-released state is observable, and the returned array is the
     * caller's own: mutating it cannot reach the aggregate. **Recording order
     * is not a chronological, causal, or delivery ordering guarantee** — see
     * the class docblock.
     *
     * @return list<DomainEvent>
     */
    final public function releaseEvents(): array
    {
        $released = $this->recordedEvents;

        $this->recordedEvents = [];

        return $released;
    }
}
