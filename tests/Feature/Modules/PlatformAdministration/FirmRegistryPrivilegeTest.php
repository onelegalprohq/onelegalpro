<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\PlatformAdministration;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FirmRegistryPrivilegeTest extends TestCase
{
    private const TABLE = 'public.platform_administration_firm_registry';

    public function test_runtime_role_has_only_the_narrow_registry_read_privilege(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $runtime = DB::connection('pgsql');
        $migration = DB::connection('pgsql_migration');
        $this->assertTrue((bool) $runtime->scalar('select has_column_privilege(current_user::name, ?::text, ?::text, ?::text)', [self::TABLE, 'id', 'SELECT']));
        $this->assertTrue((bool) $runtime->scalar('select has_column_privilege(current_user::name, ?::text, ?::text, ?::text)', [self::TABLE, 'lifecycle_state', 'SELECT']));
        foreach (['INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'REFERENCES'] as $privilege) {
            $this->assertFalse((bool) $runtime->scalar('select has_table_privilege(current_user::name, ?::text, ?::text)', [self::TABLE, $privilege]));
        }
        $this->assertNotSame($runtime->scalar('select current_user'), $migration->scalar('select current_user'));
        $this->assertFalse((bool) $migration->scalar('select relowner = (select oid from pg_roles where rolname = ?) from pg_class where oid = ?::regclass', [$runtime->scalar('select current_user'), self::TABLE]));
        $this->assertSame(0, (int) $migration->scalar(
            'select count(*) from pg_roles where rolname in (?, ?, ?) and (rolsuper or rolbypassrls)',
            [env('DB_USERNAME'), env('DB_MIGRATION_USERNAME'), env('DB_OUTBOX_USERNAME')],
        ));
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
