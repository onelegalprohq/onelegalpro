<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\IdentityAccess\Domain;

use App\Foundation\Domain\Exception\InvariantViolation;
use App\Foundation\Domain\Time\Clock;
use App\Modules\IdentityAccess\Domain\Aggregates\FirmMembership;
use App\Modules\IdentityAccess\Domain\Aggregates\Principal;
use App\Modules\IdentityAccess\Domain\ValueObjects\FirmMembershipId;
use App\Modules\IdentityAccess\Domain\ValueObjects\IdentityRealm;
use App\Modules\IdentityAccess\Domain\ValueObjects\MembershipVerification;
use App\Modules\IdentityAccess\Domain\ValueObjects\PrincipalId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use PHPUnit\Framework\TestCase;

final class FirmMembershipTest extends TestCase
{
    private const PRINCIPAL = '01920000-0000-7000-8000-000000000011';

    private const FIRM = '01920000-0000-7000-8000-000000000012';

    private const MEMBERSHIP = '01920000-0000-7000-8000-000000000013';

    public function test_only_a_matching_firm_realm_can_create_and_activate_a_membership(): void
    {
        $firmId = FirmId::fromString(self::FIRM);
        $principal = Principal::forAuthenticatedHuman(PrincipalId::fromString(self::PRINCIPAL), IdentityRealm::forFirm($firmId));
        $membership = FirmMembership::invite(FirmMembershipId::fromString(self::MEMBERSHIP), $principal, $firmId, $this->utc('2026-09-13T00:00:00'), null);
        $verification = MembershipVerification::recordedAt($this->utc('2026-09-13T00:01:00'));
        $membership->activate($verification);
        $result = $membership->activeVerifiedAt(new FixedClock($this->utc('2026-09-13T00:02:00')));
        $this->assertNotNull($result);
        $this->assertSame($firmId, $result->firmId());
        $this->assertSame($verification, $result->verification());
        $this->assertSame([], $membership->releaseEvents());
    }

    public function test_wrong_realm_and_terminal_revocation_fail_closed(): void
    {
        $firmId = FirmId::fromString(self::FIRM);
        $principal = Principal::forAuthenticatedHuman(PrincipalId::fromString(self::PRINCIPAL), IdentityRealm::forFirm(FirmId::fromString('01920000-0000-7000-8000-000000000014')));
        $this->expectException(InvariantViolation::class);
        FirmMembership::invite(FirmMembershipId::fromString(self::MEMBERSHIP), $principal, $firmId, $this->utc('2026-09-13T00:00:00'), null);
    }

    private function utc(string $instant): \DateTimeImmutable
    {
        return new \DateTimeImmutable($instant, new \DateTimeZone('UTC'));
    }
}

final readonly class FixedClock implements Clock
{
    public function __construct(private \DateTimeImmutable $instant) {}

    public function now(): \DateTimeImmutable
    {
        return $this->instant;
    }
}
