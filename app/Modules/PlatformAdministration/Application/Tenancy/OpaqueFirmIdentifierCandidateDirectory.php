<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\Tenancy;

use App\Application\Tenancy\CandidateFirmDirectory;
use App\Application\Tenancy\CandidateFirmReference;
use App\Application\Tenancy\CandidateFirmSourceKind;
use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Modules\PlatformAdministration\Application\Queries\FindFirmRegistryEntry;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;

/**
 * Resolves only a submitted, canonical opaque Firm identifier into an
 * unverified candidate. Membership verification remains outside this adapter.
 */
final readonly class OpaqueFirmIdentifierCandidateDirectory implements CandidateFirmDirectory
{
    public function __construct(
        private FindFirmRegistryEntry $registry,
    ) {}

    public function findCandidateFirmId(CandidateFirmReference $reference): ?BusinessIdentifier
    {
        if ($reference->sourceKind() !== CandidateFirmSourceKind::SubmittedOpaqueFirmIdentifier) {
            return null;
        }

        try {
            $firmId = FirmId::fromString($reference->unverifiedValue());
        } catch (InvalidArgument) {
            return null;
        }

        if ($reference->unverifiedValue() !== $firmId->toString()) {
            return null;
        }

        return $this->registry->find($firmId)?->firmId;
    }
}
