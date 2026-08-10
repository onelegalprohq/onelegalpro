<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgreSqlTestDatabaseGuardTest extends TestCase
{
    public function test_required_postgresql_test_database_is_postgresql_16_and_non_privileged(): void
    {
        if (! filter_var(env('REQUIRE_POSTGRESQL_TEST_DATABASE', false), FILTER_VALIDATE_BOOL)) {
            // Ordinary local SQLite runs remain supported. CI separately asserts
            // that this flag is exactly true before the suite starts, so this
            // local no-op cannot silently disarm the authoritative CI guard.
            $this->addToAssertionCount(1);

            return;
        }

        $connection = DB::connection();

        $this->assertSame('pgsql', $connection->getDriverName());

        $server = $connection->selectOne(
            "SELECT current_setting('server_version_num')::integer AS version_num",
        );

        $this->assertNotNull($server);
        $this->assertGreaterThanOrEqual(160000, $server->version_num);
        $this->assertLessThan(170000, $server->version_num);

        $role = $connection->selectOne(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user',
        );

        $this->assertNotNull($role);
        $this->assertFalse($role->rolsuper);
        $this->assertFalse($role->rolbypassrls);
    }
}
