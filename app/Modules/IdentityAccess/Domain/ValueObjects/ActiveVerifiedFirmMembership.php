<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Domain\ValueObjects;

use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;

final readonly class ActiveVerifiedFirmMembership
{
    public function __construct(
        private FirmMembershipId $membershipId,
        private PrincipalId $principalId,
        private FirmId $firmId,
        private MembershipVerification $verification,
        private \DateTimeImmutable $effectiveFrom,
        private ?\DateTimeImmutable $expiresAt,
        private \DateTimeImmutable $evaluatedAt,
    ) {}

    public function membershipId(): FirmMembershipId
    {
        return $this->membershipId;
    }

    public function principalId(): PrincipalId
    {
        return $this->principalId;
    }

    public function firmId(): FirmId
    {
        return $this->firmId;
    }

    public function verification(): MembershipVerification
    {
        return $this->verification;
    }

    public function effectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function evaluatedAt(): \DateTimeImmutable
    {
        return $this->evaluatedAt;
    }
}
