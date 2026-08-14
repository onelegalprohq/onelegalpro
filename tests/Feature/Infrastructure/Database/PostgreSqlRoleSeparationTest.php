<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgreSqlRoleSeparationTest extends TestCase
{
    public function test_roles_are_distinct_non_privileged_logins_without_membership_edges(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $names = $this->roleNames();
        $this->assertCount(3, array_unique($names));

        $roles = DB::connection('pgsql_migration')->select(
            'select rolname, rolsuper, rolbypassrls, rolcanlogin, rolcreatedb, rolcreaterole, rolreplication from pg_roles where rolname in (?, ?, ?) order by rolname',
            $names,
        );
        $this->assertCount(3, $roles);
        foreach ($roles as $role) {
            $this->assertFalse($role->rolsuper);
            $this->assertFalse($role->rolbypassrls);
            $this->assertTrue($role->rolcanlogin);
            $this->assertFalse($role->rolcreatedb);
            $this->assertFalse($role->rolcreaterole);
            $this->assertFalse($role->rolreplication);
        }

        $memberships = DB::connection('pgsql_migration')->scalar(
            'select count(*) from pg_auth_members where roleid in (select oid from pg_roles where rolname in (?, ?, ?)) and member in (select oid from pg_roles where rolname in (?, ?, ?))',
            [...$names, ...$names],
        );
        $this->assertSame(0, (int) $memberships);
    }

    public function test_migration_role_owns_the_database_schema_and_every_relation(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $migration = $this->roleNames()[1];
        $connection = DB::connection('pgsql_migration');
        $this->assertSame($migration, $connection->scalar('select current_user'));
        $this->assertSame($migration, $connection->scalar('select pg_get_userbyid(datdba) from pg_database where datname = current_database()'));
        $this->assertSame($migration, $connection->scalar("select pg_get_userbyid(nspowner) from pg_namespace where nspname = 'public'"));
        $this->assertSame(0, (int) $connection->scalar("select count(*) from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = 'public' and c.relkind in ('r','p','S','v','m') and pg_get_userbyid(c.relowner) <> ?", [$migration]));
    }

    public function test_runtime_and_outbox_connections_are_least_privileged(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        [$runtime, , $outbox] = $this->roleNames();
        $runtimeConnection = DB::connection('pgsql');
        $outboxConnection = DB::connection('pgsql_outbox');
        $this->assertSame($runtime, $runtimeConnection->scalar('select current_user'));
        $this->assertSame($outbox, $outboxConnection->scalar('select current_user'));
        $this->assertTrue((bool) $runtimeConnection->scalar("select has_schema_privilege(current_user, 'public', 'USAGE')"));
        $this->assertFalse((bool) $runtimeConnection->scalar("select has_schema_privilege(current_user, 'public', 'CREATE')"));
        $this->assertFalse((bool) $runtimeConnection->scalar("select has_table_privilege(current_user, 'public.migrations', 'SELECT')"));
        $catalogue = DB::connection('pgsql_migration');
        $this->assertSame(0, (int) $catalogue->scalar("select coalesce(sum(case when c.relkind in ('r','p','v','m') and has_table_privilege(?, c.oid, 'SELECT,INSERT,UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER') then 1 else 0 end), 0) from pg_class c join pg_namespace n on n.oid=c.relnamespace where n.nspname='public'", [$outbox]));
        $this->assertSame(0, (int) $catalogue->scalar("select coalesce(sum(case when c.relkind='S' and has_sequence_privilege(?, c.oid, 'USAGE,SELECT,UPDATE') then 1 else 0 end), 0) from pg_class c join pg_namespace n on n.oid=c.relnamespace where n.nspname='public'", [$outbox]));
        $this->assertSame(0, (int) $catalogue->scalar("select coalesce(sum(case when c.relkind in ('r','p','v','m') and has_table_privilege(?, c.oid, 'TRUNCATE,REFERENCES,TRIGGER') then 1 else 0 end), 0) from pg_class c join pg_namespace n on n.oid=c.relnamespace where n.nspname='public'", [$runtime]));
    }

    public function test_public_and_default_privileges_are_revoked(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        [$runtime, $migration, $outbox] = $this->roleNames();
        $connection = DB::connection('pgsql_migration');
        $this->assertSame(0, (int) $connection->scalar("select count(*) from pg_database d cross join lateral aclexplode(coalesce(d.datacl, acldefault('d', d.datdba))) a where d.datname=current_database() and a.grantee=0"));
        $this->assertSame(0, (int) $connection->scalar("select count(*) from pg_namespace n cross join lateral aclexplode(coalesce(n.nspacl, acldefault('n', n.nspowner))) a where n.nspname='public' and a.grantee=0"));
        $this->assertSame(0, (int) $connection->scalar('select count(*) from pg_default_acl d cross join lateral aclexplode(d.defaclacl) a where d.defaclrole=(select oid from pg_roles where rolname=?) and (a.grantee=0 or a.grantee in (select oid from pg_roles where rolname in (?, ?)))', [$migration, $runtime, $outbox]));
    }

    public function test_runtime_role_guard_is_non_vacuous_against_migration_connection(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $this->assertRuntimeConnection(DB::connection('pgsql'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection is not using the runtime role.');
        $this->assertRuntimeConnection(DB::connection('pgsql_migration'));
    }

    public function test_migration_connection_can_do_ddl_while_runtime_cannot(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $migration = DB::connection('pgsql_migration');
        $migration->beginTransaction();
        try {
            $migration->statement('create table pf074_rollback_probe (id integer)');
            $this->assertTrue(true);
        } finally {
            $migration->rollBack();
        }

        $this->expectException(\Throwable::class);
        DB::connection('pgsql')->statement('create table pf074_forbidden_probe (id integer)');
    }

    /** @return array{string, string, string} */
    private function roleNames(): array
    {
        return [
            (string) config('database.connections.pgsql.username'),
            (string) config('database.connections.pgsql_migration.username'),
            (string) config('database.connections.pgsql_outbox.username'),
        ];
    }

    private function postgreSqlRequired(): bool
    {
        if (filter_var(env('REQUIRE_POSTGRESQL_TEST_DATABASE', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        $this->addToAssertionCount(1);

        return false;
    }

    private function assertRuntimeConnection(Connection $connection): void
    {
        if ($connection->scalar('select current_user') !== $this->roleNames()[0]) {
            throw new \RuntimeException('Database connection is not using the runtime role.');
        }

        $this->assertTrue(true);
    }
}
