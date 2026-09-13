<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Domain\Aggregates;

use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Exception\InvariantViolation;
use App\Foundation\Domain\Model\AggregateRoot;
use App\Foundation\Domain\Time\Clock;
use App\Modules\IdentityAccess\Domain\ValueObjects\ActiveVerifiedFirmMembership;
use App\Modules\IdentityAccess\Domain\ValueObjects\FirmMembershipId;
use App\Modules\IdentityAccess\Domain\ValueObjects\FirmMembershipLifecycleState;
use App\Modules\IdentityAccess\Domain\ValueObjects\MembershipVerification;
use App\Modules\IdentityAccess\Domain\ValueObjects\PrincipalId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;

/** @extends AggregateRoot<FirmMembershipId> */
final class FirmMembership extends AggregateRoot
{
    private function __construct(
        FirmMembershipId $id,
        private readonly PrincipalId $principalId,
        private readonly FirmId $firmId,
        private FirmMembershipLifecycleState $lifecycleState,
        private ?MembershipVerification $verification,
        private readonly \DateTimeImmutable $effectiveFrom,
        private readonly ?\DateTimeImmutable $expiresAt,
    ) {
        parent::__construct($id);
    }

    public static function invite(FirmMembershipId $id, Principal $principal, FirmId $firmId, \DateTimeImmutable $effectiveFrom, ?\DateTimeImmutable $expiresAt): self
    {
        self::assertUtc($effectiveFrom);
        if ($expiresAt !== null) {
            self::assertUtc($expiresAt);
            if ($expiresAt <= $effectiveFrom) {
                throw new InvalidArgument('Membership expiry must be after its effective instant.');
            }
        }
        $realmFirmId = $principal->realm()->firmId();
        if ($realmFirmId === null || ! $realmFirmId->equals($firmId)) {
            throw new InvariantViolation('A membership Firm must match the principal realm.');
        }
        /** @var PrincipalId $principalId */
        $principalId = $principal->id();

        return new self($id, $principalId, $firmId, FirmMembershipLifecycleState::Invited, null, $effectiveFrom, $expiresAt);
    }

    public function activate(MembershipVerification $verification): void
    {
        if (! in_array($this->lifecycleState, [FirmMembershipLifecycleState::Invited, FirmMembershipLifecycleState::Suspended], true)) {
            throw new InvariantViolation('Membership cannot be activated from its current state.');
        }
        $this->verification = $verification;
        $this->lifecycleState = FirmMembershipLifecycleState::Active;
    }

    public function suspend(): void
    {
        if ($this->lifecycleState !== FirmMembershipLifecycleState::Active) {
            throw new InvariantViolation('Only an active membership can be suspended.');
        } $this->lifecycleState = FirmMembershipLifecycleState::Suspended;
    }

    public function revoke(): void
    {
        if ($this->lifecycleState === FirmMembershipLifecycleState::Revoked) {
            throw new InvariantViolation('A revoked membership is terminal.');
        } $this->lifecycleState = FirmMembershipLifecycleState::Revoked;
    }

    public function activeVerifiedAt(Clock $clock): ?ActiveVerifiedFirmMembership
    {
        $now = $clock->now();
        self::assertUtc($now);
        if ($this->lifecycleState !== FirmMembershipLifecycleState::Active || $this->verification === null || $now < $this->effectiveFrom || ($this->expiresAt !== null && $now >= $this->expiresAt)) {
            return null;
        }

        return new ActiveVerifiedFirmMembership($this->id(), $this->principalId, $this->firmId, $this->verification, $this->effectiveFrom, $this->expiresAt, $now);
    }

    public function principalId(): PrincipalId
    {
        return $this->principalId;
    }

    public function firmId(): FirmId
    {
        return $this->firmId;
    }

    public function lifecycleState(): FirmMembershipLifecycleState
    {
        return $this->lifecycleState;
    }

    public function verification(): ?MembershipVerification
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

    private static function assertUtc(\DateTimeImmutable $instant): void
    {
        if ($instant->getTimezone()->getName() !== 'UTC') {
            throw new InvalidArgument('Membership instants must use UTC.');
        }
    }
}
