<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Identity\UuidV7Generator;
use App\Foundation\Tenancy\FirmContext;

final class VerifiedFirmContextResolver
{
    public function __construct(
        private FirmMembershipVerifier $verifier,
        private UuidV7Generator $correlationIds,
    ) {}

    public function resolve(
        AuthenticatedActor $actor,
        BusinessIdentifier $firmId,
    ): FirmContext {
        $actorId = $actor->actorId();

        try {
            $assertion = $this->verifier->verifyActiveMembership($actor, $firmId);
        } catch (\Throwable) {
            throw FirmResolutionDenied::denied();
        }

        if (! $assertion->firmId()->equals($firmId)
            || ! $assertion->actorId()->equals($actorId)) {
            throw FirmResolutionDenied::denied();
        }

        return new FirmContext(
            firmId: $assertion->firmId(),
            actorId: $actorId,
            correlationId: $this->correlationIds->generate(),
        );
    }
}
