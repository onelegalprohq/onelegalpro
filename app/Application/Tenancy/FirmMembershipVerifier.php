<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Foundation\Domain\Identity\BusinessIdentifier;

interface FirmMembershipVerifier
{
    public function verifyActiveMembership(
        AuthenticatedActor $actor,
        BusinessIdentifier $firmId,
    ): ActiveFirmMembershipAssertion;
}
