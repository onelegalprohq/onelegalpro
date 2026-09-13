<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\IdentityAccess\Domain;

use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Modules\IdentityAccess\Domain\Aggregates\Principal;
use App\Modules\IdentityAccess\Domain\ValueObjects\ActorCategory;
use App\Modules\IdentityAccess\Domain\ValueObjects\ActorReference;
use App\Modules\IdentityAccess\Domain\ValueObjects\IdentityRealm;
use App\Modules\IdentityAccess\Domain\ValueObjects\PrincipalId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use PHPUnit\Framework\TestCase;

/** IA-001 behavioural evidence; this suite boots neither Laravel nor a database. */
final class PrincipalTest extends TestCase
{
    private const PRINCIPAL_ID = '01920000-0000-7000-8000-000000000001';

    private const FIRM_ID = '01920000-0000-7000-8000-000000000002';

    public function test_an_authenticated_human_principal_preserves_its_exact_identity_realm_and_actor_instances(): void
    {
        $id = PrincipalId::fromString(self::PRINCIPAL_ID);
        $realm = IdentityRealm::forFirm(FirmId::fromString(self::FIRM_ID));

        $principal = Principal::forAuthenticatedHuman($id, $realm);

        $this->assertSame($id, $principal->id());
        $this->assertSame($realm, $principal->realm());
        $this->assertSame($id, $principal->actorReference()->identifier());
        $this->assertSame(ActorCategory::Human, $principal->actorReference()->category());
        $this->assertSame([], $principal->releaseEvents());
    }

    public function test_actor_reference_has_only_category_specific_construction_paths_and_preserves_each_category(): void
    {
        $principalId = PrincipalId::fromString(self::PRINCIPAL_ID);
        $otherId = IdentityAccessTestIdentifier::fromString(self::FIRM_ID);

        foreach ([
            ActorReference::human($principalId),
            ActorReference::service($otherId),
            ActorReference::system($otherId),
            ActorReference::ai($otherId),
        ] as $actor) {
            $this->assertInstanceOf(BusinessIdentifier::class, $actor->identifier());
        }

        $this->assertSame(ActorCategory::Human, ActorReference::human($principalId)->category());
        $this->assertSame(ActorCategory::Service, ActorReference::service($otherId)->category());
        $this->assertSame(ActorCategory::System, ActorReference::system($otherId)->category());
        $this->assertSame(ActorCategory::AI, ActorReference::ai($otherId)->category());
        $this->assertFalse(ActorReference::human($principalId)->equals(ActorReference::service($principalId)));
    }

    public function test_identity_realms_are_disjoint_and_compare_by_their_exact_variant_and_firm_identifier(): void
    {
        $firmId = FirmId::fromString(self::FIRM_ID);
        $firmRealm = IdentityRealm::forFirm($firmId);
        $platformRealm = IdentityRealm::platformOperator();

        $this->assertTrue($firmRealm->isFirmRealm());
        $this->assertSame($firmId, $firmRealm->firmId());
        $this->assertFalse($platformRealm->isFirmRealm());
        $this->assertNull($platformRealm->firmId());
        $this->assertTrue($firmRealm->equals(IdentityRealm::forFirm(FirmId::fromString(self::FIRM_ID))));
        $this->assertTrue($platformRealm->equals(IdentityRealm::platformOperator()));
        $this->assertFalse($firmRealm->equals($platformRealm));
    }

    public function test_typed_identifiers_cannot_be_interchanged(): void
    {
        $principalId = PrincipalId::fromString(self::PRINCIPAL_ID);
        $firmId = FirmId::fromString(self::PRINCIPAL_ID);

        $this->assertFalse($principalId->equals($firmId));
        $this->assertFalse($firmId->equals($principalId));
    }
}

final readonly class IdentityAccessTestIdentifier extends BusinessIdentifier {}
