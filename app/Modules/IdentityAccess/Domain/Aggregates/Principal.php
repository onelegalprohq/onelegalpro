<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Domain\Aggregates;

use App\Foundation\Domain\Model\AggregateRoot;
use App\Modules\IdentityAccess\Domain\ValueObjects\ActorReference;
use App\Modules\IdentityAccess\Domain\ValueObjects\IdentityRealm;
use App\Modules\IdentityAccess\Domain\ValueObjects\PrincipalId;

/**
 * An authenticated human subject in one security realm (IA-001).
 *
 * This pure domain slice neither performs authentication nor retains its
 * proof. Its empty event buffer is intentional: no durable creation fact has
 * yet been defined by a persistence, audit, or outbox contract.
 *
 * @extends AggregateRoot<PrincipalId>
 */
final class Principal extends AggregateRoot
{
    private function __construct(
        PrincipalId $id,
        private readonly IdentityRealm $realm,
        private readonly ActorReference $actorReference,
    ) {
        parent::__construct($id);
    }

    /** Construct only after an approved external boundary verified the human subject. */
    public static function forAuthenticatedHuman(PrincipalId $id, IdentityRealm $realm): self
    {
        return new self($id, $realm, ActorReference::human($id));
    }

    public function realm(): IdentityRealm
    {
        return $this->realm;
    }

    public function actorReference(): ActorReference
    {
        return $this->actorReference;
    }
}
