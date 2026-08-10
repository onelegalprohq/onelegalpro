<?php

declare(strict_types=1);

namespace App\Foundation\Tenancy;

use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Identity\UuidV7;

/**
 * The verified Firm, Actor, and correlation identifiers for one execution.
 *
 * This immutable carrier performs no discovery, authentication, membership,
 * authorization, entitlement, session, tenant-resolution, persistence, or
 * database work. Possessing an instance grants no access or authority.
 */
final readonly class FirmContext
{
    public function __construct(
        private BusinessIdentifier $firmId,
        private BusinessIdentifier $actorId,
        private UuidV7 $correlationId,
    ) {}

    public function firmId(): BusinessIdentifier
    {
        return $this->firmId;
    }

    public function actorId(): BusinessIdentifier
    {
        return $this->actorId;
    }

    public function correlationId(): UuidV7
    {
        return $this->correlationId;
    }
}
