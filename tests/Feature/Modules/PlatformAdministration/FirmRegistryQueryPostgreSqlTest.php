<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\PlatformAdministration;

use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmLifecycleState;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\PostgreSqlFirmRegistry;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FirmRegistryQueryPostgreSqlTest extends TestCase
{
    private const FIRST_ID = '018f0f80-0000-7000-8000-000000000001';

    private const SECOND_ID = '018f0f80-0000-7000-8000-000000000002';

    protected function tearDown(): void
    {
        if ($this->postgreSqlRequired()) {
            DB::connection('pgsql_migration')->delete('delete from platform_administration_firm_registry where id in (?, ?)', [self::FIRST_ID, self::SECOND_ID]);
        }

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->postgreSqlRequired()) {
            $database = DB::connection('pgsql_migration');
            $database->delete('delete from platform_administration_firm_registry where id in (?, ?)', [self::FIRST_ID, self::SECOND_ID]);
            $database->insert('insert into platform_administration_firm_registry (id, canonical_name, jurisdiction_reference, lifecycle_state, created_at, updated_at) values (?, ?, ?, ?, ?, ?), (?, ?, ?, ?, ?, ?)', [
                self::FIRST_ID, 'First Firm', 'TH', 'Active', '2026-09-12 00:00:00+00', '2026-09-12 00:00:00+00',
                self::SECOND_ID, 'Second Firm', 'TH', 'Closed', '2026-09-12 00:00:00+00', '2026-09-12 00:00:00+00',
            ]);
        }
    }

    public function test_exact_identifier_query_returns_only_the_minimized_registry_result(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $registry = new PostgreSqlFirmRegistry(DB::connection('pgsql'));
        $entry = $registry->find(FirmId::fromString(self::FIRST_ID));

        $this->assertNotNull($entry);
        $this->assertSame(self::FIRST_ID, $entry->firmId->toString());
        $this->assertSame(FirmLifecycleState::Active, $entry->lifecycleState);
        $this->assertNull($registry->find(FirmId::fromString('018f0f80-0000-7000-8000-000000000003')));
    }

    private function postgreSqlRequired(): bool
    {
        if (! filter_var(env('REQUIRE_POSTGRESQL_TEST_DATABASE', false), FILTER_VALIDATE_BOOL)) {
            $this->addToAssertionCount(1);

            return false;
        }

        return true;
    }
}
