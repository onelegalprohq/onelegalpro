<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Aggregates;

use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Domain\Model\AggregateRoot;
use App\Modules\PlatformAdministration\Domain\Events\FirmCreated;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmJurisdiction;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmLifecycleState;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmName;

/**
 * The `Firm` aggregate root (PA-001).
 *
 * The `Firm` record every other bounded context references and none previously
 * owned. It holds exactly its identity, its canonical name, its opaque
 * jurisdiction reference, and its lifecycle state — **and nothing else**.
 *
 * **Creation produces `Requested`, always.** {@see self::create()} is the only
 * named creation constructor, the constructor itself is private, and no
 * transition, setter, mutator, `with*`, rename, or state-assignment member
 * exists anywhere on this class. *"A Firm never becomes usable as a side
 * effect"* therefore holds **structurally**: no reachable code path can leave
 * `Requested`. Every `Requested → Provisioned → Active → Closed` transition,
 * with its authorization and reason recording, is **`PA-002`'s**.
 *
 * **Exactly one `FirmCreated` is recorded, into memory, and nothing else
 * happens.** Nothing is persisted, dispatched, published, serialized, logged,
 * or emitted as a metric or trace, and **no claim is made that administrative
 * audit persistence exists** — that needs `PA-006` and `PF-091`, neither of
 * which exists. Recording a domain event in memory is not an audit record.
 *
 * **No aggregate version, and that is a decision rather than an omission.**
 * Optimistic-concurrency versioning is a **persistence** concern; because
 * PA-001 delivers no persistence, the version and its conflict semantics
 * belong to **`PA-007`**, which must decide them explicitly rather than leave
 * them to a migration author.
 *
 * **What this aggregate is not.** It performs no authentication, holds no
 * credential, session, authenticator, recovery material, or invitation, makes
 * no authorization decision, defines no operator access path, and never
 * stores, reads, derives, aggregates, or exports a Firm's business data.
 * Owning the `Firm` record is never access to what is inside the Firm. It
 * decides no Ethical Wall — that remains Practice Management's alone — and
 * completes no part of tenant isolation.
 *
 * **It reads no ambient time and no ambient randomness**, and holds neither a
 * `Clock` nor a `UuidV7Generator`: the identity, the event identifier, and the
 * occurrence instant are all supplied by the caller. It imports no framework
 * symbol, constructs no `FirmContext`, opens no transaction, and looks nothing
 * up in a container.
 *
 * **No persistence hook, serialization member, `__toString()`, or
 * authorization method exists**, and none may be added by a story that does
 * not own persistence.
 *
 * @extends AggregateRoot<FirmId>
 */
final class Firm extends AggregateRoot
{
    /**
     * Private, so {@see self::create()} is the only path to a `Firm` and every
     * instance necessarily recorded its creation.
     *
     * The three non-identity attributes are `readonly` and are the whole of
     * this aggregate's state beyond the identity `Entity` already holds.
     */
    private function __construct(
        FirmId $id,
        private readonly FirmName $name,
        private readonly FirmJurisdiction $jurisdiction,
        private readonly FirmLifecycleState $lifecycleState,
    ) {
        parent::__construct($id);
    }

    /**
     * Create a `Firm`, in `Requested`, recording exactly one `FirmCreated`.
     *
     * The supplied identifier, name, and jurisdiction instances are preserved
     * exactly — nothing is copied, re-derived, re-canonicalized, or replaced,
     * because each already canonicalized itself on its own creation path.
     *
     * `$eventId` and `$occurredAt` are the recorded fact's occurrence identity
     * and instant. They are parameters rather than something this aggregate
     * obtains for itself, which is what keeps the domain free of ambient time
     * and ambient randomness; `FirmCreated` rejects an instant that is not a
     * plain native `\DateTimeImmutable` carrying the UTC timezone identity —
     * a zero offset alone is not UTC and is not accepted.
     *
     * **No actor is accepted or recorded.** No authoritative actor exists yet,
     * and accepting an identifier and calling it attribution would manufacture
     * a verified fact from an unverified input.
     */
    public static function create(
        FirmId $id,
        FirmName $name,
        FirmJurisdiction $jurisdiction,
        UuidV7 $eventId,
        \DateTimeImmutable $occurredAt,
    ): self {
        $firm = new self($id, $name, $jurisdiction, FirmLifecycleState::Requested);

        $firm->recordThat(new FirmCreated(
            eventId: $eventId,
            occurredAt: $occurredAt,
            firmId: $id,
            firmName: $name,
            firmJurisdiction: $jurisdiction,
            lifecycleState: FirmLifecycleState::Requested,
        ));

        return $firm;
    }

    /**
     * This Firm's canonical name.
     *
     * Read-only. There is no `rename()` and no setter: a name change is a
     * later story's recorded fact, not a mutation performed here.
     */
    public function name(): FirmName
    {
        return $this->name;
    }

    /**
     * This Firm's opaque jurisdiction reference.
     *
     * Read-only, and it resolves nothing — see
     * {@see FirmJurisdiction} for why holding one asserts no legal, tax, or
     * regulatory conclusion.
     */
    public function jurisdiction(): FirmJurisdiction
    {
        return $this->jurisdiction;
    }

    /**
     * This Firm's lifecycle state — always `Requested` in PA-001.
     *
     * Read-only, and reading it is not authorization: knowing a Firm's state
     * grants nothing inside it.
     */
    public function lifecycleState(): FirmLifecycleState
    {
        return $this->lifecycleState;
    }
}
