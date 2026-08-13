<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Events;

use App\Foundation\Domain\Event\DomainEvent;
use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Identity\UuidV7;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmJurisdiction;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmLifecycleState;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmName;

/**
 * The single fact PA-001 records: a `Firm` was created (PA-001).
 *
 * **Identity and time are constructor data, never self-generated.** The
 * aggregate reads no ambient time and no ambient randomness, and holds neither
 * a `Clock` nor a `UuidV7Generator` — so both the occurrence identifier and
 * the occurrence instant are supplied by the caller that already has them.
 * Nothing here regenerates, re-parses, or substitutes either one.
 *
 * **`occurredAt()` returns a plain native `\DateTimeImmutable` in UTC.** The
 * `DomainEvent` interface can enforce neither property — PHP return-type
 * covariance would let a `Carbon\CarbonImmutable` satisfy the declared type —
 * so this class enforces both itself, at construction, by **rejecting** a
 * non-conforming instant rather than converting one. Converting would coerce
 * an unacceptable input into validity, which the fail-closed rule prohibits;
 * a caller that supplied the wrong type or zone has a defect worth surfacing.
 *
 * **"In UTC" means the timezone *identity* is UTC, not merely that the offset
 * happens to be zero at that instant.** A zero-offset test is not the same
 * rule and is not a weaker version of it — it is a different, date-dependent
 * one. `Europe/London` carries a zero offset every winter and a `+01:00`
 * offset every summer, so an offset test would accept the same caller's zone
 * in January and refuse it in July; `Atlantic/Reykjavik` and `Africa/Abidjan`
 * would be accepted year-round. Any of them would then travel through the
 * system labelled UTC while remaining a named regional zone that a later
 * consumer's date arithmetic or formatting would resolve against its own
 * transition rules. The identity check is therefore the rule, and
 * {@see self::UTC_TIMEZONE_IDENTITY} is the only accepted value.
 *
 * **Only the canonical `UTC` identity is accepted.** `GMT`, `Z`, `Etc/UTC`,
 * and a fixed `+00:00` offset all denote the same instant but are distinct
 * timezone identities, and the accepted contract names UTC alone — so each is
 * refused rather than silently treated as an alias. This is compatible with
 * Foundation's PF-047 `SystemClock`, which constructs its instant with
 * `new \DateTimeZone('UTC')` and therefore always satisfies this rule.
 *
 * **The payload is identifiers and immutable values only** — the Firm's
 * identity, canonical name, jurisdiction reference, and the resulting
 * lifecycle state. It carries **no `Entity`, no aggregate reference, no
 * mutable object, no actor, and no confidential content**.
 *
 * **No actor is recorded, and none is fabricated.** IdentityAccess's
 * `Principal` and actor reference do not exist yet, and accepting an abstract
 * identifier and calling it attribution would manufacture a verified fact from
 * an unverified input. Actor attribution is added by the story that supplies
 * an authoritative actor.
 *
 * **Recording this event is not an audit record.** It is appended to the
 * aggregate's in-memory buffer and nothing else: **nothing is persisted,
 * dispatched, published, serialized, logged, or emitted as a metric, trace, or
 * telemetry event**, and durable administrative audit requires `PA-006`, the
 * append-only administrative audit relation, and `PF-091` — none of which
 * exists. An in-memory event is not durable and is never described as an audit
 * record.
 *
 * **An internal domain event is never automatically a public integration
 * contract**, and possessing this event or its identifier grants no
 * authorization. Satisfying the `DomainEvent` contract makes no event safe to
 * log, dump, serialize, expose, or publish.
 */
final readonly class FirmCreated implements DomainEvent
{
    /**
     * The one timezone identity a domain event instant may carry.
     *
     * Private, because it is this class's own construction rule rather than a
     * published part of the event's payload or API.
     */
    private const string UTC_TIMEZONE_IDENTITY = 'UTC';

    /**
     * @param  UuidV7  $eventId  the stable occurrence identity, supplied by the caller
     * @param  \DateTimeImmutable  $occurredAt  a plain native instant in UTC, supplied by the caller
     *
     * @throws InvalidArgument when $occurredAt is not a plain native `\DateTimeImmutable` in UTC
     */
    public function __construct(
        private UuidV7 $eventId,
        private \DateTimeImmutable $occurredAt,
        private FirmId $firmId,
        private FirmName $firmName,
        private FirmJurisdiction $firmJurisdiction,
        private FirmLifecycleState $lifecycleState,
    ) {
        // Exact runtime class, not `instanceof`: a framework or vendor subclass
        // such as Carbon\CarbonImmutable passes `instanceof` and would carry
        // framework behaviour into a domain event.
        if ($occurredAt::class !== \DateTimeImmutable::class) {
            throw new InvalidArgument(
                'A domain event instant must be a plain native DateTimeImmutable, not a subclass.',
            );
        }

        // Compared by timezone identity, never by offset: a zero offset is a
        // property of an instant, not of a zone, so `Europe/London` in winter
        // would pass an offset test and then behave as a DST zone ever after.
        //
        // The exact-class check above already guarantees a constructed native
        // `\DateTimeImmutable`, for which `getTimezone()` always returns a
        // `\DateTimeZone`. A `=== false` branch here would be provably dead
        // code, and PHPStan level 5 reports it as such rather than letting it
        // pass as defensive.
        //
        // No conversion is performed: an unacceptable instant is rejected,
        // never coerced into UTC. The message names no supplied value.
        if ($occurredAt->getTimezone()->getName() !== self::UTC_TIMEZONE_IDENTITY) {
            throw new InvalidArgument('A domain event instant must carry the UTC timezone identity.');
        }
    }

    /**
     * This event's stable occurrence identity — the exact `UuidV7` instance
     * supplied at construction.
     *
     * Never a time source, ordering key, deduplication key, authorization
     * input, or proof of provenance or authenticity. A `UuidV7` additionally
     * discloses its own generation time to millisecond precision, which is
     * **additional technical metadata alongside** {@see self::occurredAt()},
     * not a duplicate of it — the two may differ and must never be assumed
     * equal.
     */
    public function eventId(): UuidV7
    {
        return $this->eventId;
    }

    /**
     * The UTC instant this creation occurred at.
     *
     * Never a sort key, deduplication key, or idempotency key, and never a
     * substitute for recorded-at, committed-at, published-at, or delivered-at
     * — none of which exists, because nothing here is recorded durably,
     * committed, published, or delivered.
     */
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * The identity of the Firm that was created.
     */
    public function firmId(): FirmId
    {
        return $this->firmId;
    }

    /**
     * The Firm's canonical name at creation.
     */
    public function firmName(): FirmName
    {
        return $this->firmName;
    }

    /**
     * The Firm's opaque jurisdiction reference at creation.
     */
    public function firmJurisdiction(): FirmJurisdiction
    {
        return $this->firmJurisdiction;
    }

    /**
     * The lifecycle state creation resulted in — always `Requested`.
     */
    public function lifecycleState(): FirmLifecycleState
    {
        return $this->lifecycleState;
    }
}
