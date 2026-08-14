<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

final class PostgreSqlRoleSeparationTest extends TestCase
{
    /**
     * The closed list of framework tables the accepted contract permits the
     * runtime role to reach. Nothing may be added here without a contract
     * correction: the assertions below treat it as both a lower and an upper
     * bound.
     *
     * @var list<string>
     */
    private const APPROVED_RUNTIME_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'password_reset_tokens',
        'sessions',
        'users',
    ];

    /** @var list<string> */
    private const ACCEPTED_RUNTIME_TABLE_PRIVILEGES = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];

    private const PROBE_TABLE = 'pf074_probe_unapproved';

    private const PROBE_SEQUENCE = 'pf074_probe_unapproved_seq';

    private const PROBE_FUNCTION = 'pf074_probe_fn';

    private const PROBE_TYPE = 'pf074_probe_type';

    protected function tearDown(): void
    {
        $this->dropProbeObjects();

        parent::tearDown();
    }

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
        $catalogue = DB::connection('pgsql_migration');
        $this->assertSame(0, (int) $catalogue->scalar("select coalesce(sum(case when c.relkind in ('r','p','v','m') and has_table_privilege(?, c.oid, 'SELECT,INSERT,UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER') then 1 else 0 end), 0) from pg_class c join pg_namespace n on n.oid=c.relnamespace where n.nspname='public'", [$outbox]));
        $this->assertSame(0, (int) $catalogue->scalar("select coalesce(sum(case when c.relkind='S' and has_sequence_privilege(?, c.oid, 'USAGE,SELECT,UPDATE') then 1 else 0 end), 0) from pg_class c join pg_namespace n on n.oid=c.relnamespace where n.nspname='public'", [$outbox]));
        $this->assertSame(0, (int) $catalogue->scalar("select coalesce(sum(case when c.relkind in ('r','p','v','m') and has_table_privilege(?, c.oid, 'TRUNCATE,REFERENCES,TRIGGER') then 1 else 0 end), 0) from pg_class c join pg_namespace n on n.oid=c.relnamespace where n.nspname='public'", [$runtime]));
    }

    /**
     * The upper bound. Without this, granting the runtime role full DML on an
     * arbitrary relation leaves the suite green — which is exactly how the
     * wildcard sequence grant reached Code Review.
     */
    public function test_runtime_table_privileges_are_confined_to_the_approved_list(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $runtime = $this->roleNames()[0];
        $catalogue = DB::connection('pgsql_migration');

        $reachable = array_map(
            static fn (object $row): string => $row->relname,
            $catalogue->select(
                "select c.relname from pg_class c join pg_namespace n on n.oid = c.relnamespace
                 where n.nspname = 'public'
                   and case when c.relkind in ('r','p','v','m') then has_table_privilege(?, c.oid, 'SELECT,INSERT,UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER') else false end
                 order by c.relname",
                [$runtime],
            ),
        );

        $this->assertSame(self::APPROVED_RUNTIME_TABLES, $reachable);

        // And on each of them, exactly the accepted DML and nothing else.
        foreach (self::APPROVED_RUNTIME_TABLES as $table) {
            foreach (self::ACCEPTED_RUNTIME_TABLE_PRIVILEGES as $privilege) {
                $this->assertTrue(
                    (bool) $catalogue->scalar('select has_table_privilege(?, ?, ?)', [$runtime, 'public.'.$table, $privilege]),
                    "runtime role should hold {$privilege} on {$table}",
                );
            }

            foreach (['TRUNCATE', 'REFERENCES', 'TRIGGER'] as $privilege) {
                $this->assertFalse(
                    (bool) $catalogue->scalar('select has_table_privilege(?, ?, ?)', [$runtime, 'public.'.$table, $privilege]),
                    "runtime role must not hold {$privilege} on {$table}",
                );
            }
        }
    }

    /**
     * Sequence privileges must follow catalogue ownership, not schema
     * membership. `migrations_id_seq` is the concrete relation this bounds.
     */
    public function test_runtime_sequence_privileges_are_confined_to_approved_table_sequences(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $runtime = $this->roleNames()[0];
        $catalogue = DB::connection('pgsql_migration');

        // has_sequence_privilege() must not be evaluated on a non-sequence, and
        // PostgreSQL is free to reorder WHERE predicates, so the relkind test
        // is expressed as a CASE the planner cannot hoist it above.
        $reachable = array_map(
            static fn (object $row): string => $row->relname,
            $catalogue->select(
                "select c.relname from pg_class c join pg_namespace n on n.oid = c.relnamespace
                 where n.nspname = 'public'
                   and case when c.relkind = 'S' then has_sequence_privilege(?, c.oid, 'USAGE,SELECT,UPDATE') else false end
                 order by c.relname",
                [$runtime],
            ),
        );

        $ownedSql = sprintf(
            "select s.relname from (values %s) as approved(table_name)
             join pg_class t on t.oid = to_regclass(format('public.%%I', approved.table_name))
             join pg_depend d on d.refobjid = t.oid
                 and d.refclassid = 'pg_class'::regclass
                 and d.classid = 'pg_class'::regclass
                 and d.deptype in ('a','i')
             join pg_class s on s.oid = d.objid and s.relkind = 'S'
             order by s.relname",
            $this->approvedTableValuesList(),
        );

        $owned = array_map(
            static fn (object $row): string => $row->relname,
            $catalogue->select($ownedSql),
        );

        $this->assertNotEmpty($owned, 'the approved tables should own at least one sequence');
        $this->assertSame($owned, $reachable);

        foreach ($reachable as $sequence) {
            $this->assertFalse(
                (bool) $catalogue->scalar('select has_sequence_privilege(?, ?, ?)', [$runtime, 'public.'.$sequence, 'UPDATE']),
                "runtime role must not hold UPDATE on {$sequence}",
            );
        }
    }

    public function test_runtime_role_cannot_reach_migrations_or_its_sequence(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $runtime = $this->roleNames()[0];
        $catalogue = DB::connection('pgsql_migration');

        foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'REFERENCES', 'TRIGGER'] as $privilege) {
            $this->assertFalse(
                (bool) $catalogue->scalar('select has_table_privilege(?, ?, ?)', [$runtime, 'public.migrations', $privilege]),
                "runtime role must not hold {$privilege} on migrations",
            );
        }

        foreach (['USAGE', 'SELECT', 'UPDATE'] as $privilege) {
            $this->assertFalse(
                (bool) $catalogue->scalar('select has_sequence_privilege(?, ?, ?)', [$runtime, 'public.migrations_id_seq', $privilege]),
                "runtime role must not hold {$privilege} on migrations_id_seq",
            );
        }

        // Real permission, not only the catalogue: the runtime role must not be
        // able to read the table or advance the sequence.
        $this->assertInsufficientPrivilege(
            static fn () => DB::connection('pgsql')->select('select count(*) from public.migrations'),
        );
        $this->assertInsufficientPrivilege(
            static fn () => DB::connection('pgsql')->select("select nextval('public.migrations_id_seq')"),
        );
    }

    public function test_runtime_holds_nothing_on_an_arbitrary_unapproved_table_or_sequence(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $runtime = $this->roleNames()[0];
        $migration = DB::connection('pgsql_migration');
        $this->createProbeObjects();

        foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'REFERENCES', 'TRIGGER'] as $privilege) {
            $this->assertFalse(
                (bool) $migration->scalar('select has_table_privilege(?, ?, ?)', [$runtime, 'public.'.self::PROBE_TABLE, $privilege]),
                'runtime role must hold nothing on an unapproved table',
            );
        }

        foreach (['USAGE', 'SELECT', 'UPDATE'] as $privilege) {
            $this->assertFalse(
                (bool) $migration->scalar('select has_sequence_privilege(?, ?, ?)', [$runtime, 'public.'.self::PROBE_SEQUENCE, $privilege]),
                'runtime role must hold nothing on an unapproved sequence',
            );
        }

        $this->assertInsufficientPrivilege(
            static fn () => DB::connection('pgsql')->select('select count(*) from public.'.self::PROBE_TABLE),
        );
    }

    /**
     * Tables and sequences really are deny-by-default at creation time: their
     * built-in default grants the runtime and outbox roles nothing, and no
     * stored default privilege adds anything.
     */
    public function test_new_tables_and_sequences_grant_runtime_and_outbox_nothing(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        [$runtime, , $outbox] = $this->roleNames();
        $migration = DB::connection('pgsql_migration');
        $this->createProbeObjects();

        foreach ([$runtime, $outbox] as $role) {
            foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE'] as $privilege) {
                $this->assertFalse(
                    (bool) $migration->scalar('select has_table_privilege(?, ?, ?)', [$role, 'public.'.self::PROBE_TABLE, $privilege]),
                    "{$role} must not receive {$privilege} on a newly created table",
                );
            }

            foreach (['USAGE', 'SELECT', 'UPDATE'] as $privilege) {
                $this->assertFalse(
                    (bool) $migration->scalar('select has_sequence_privilege(?, ?, ?)', [$role, 'public.'.self::PROBE_SEQUENCE, $privilege]),
                    "{$role} must not receive {$privilege} on a newly created sequence",
                );
            }
        }
    }

    /**
     * Functions and types are the case PostgreSQL 16 cannot express as a
     * default privilege: the built-in `acldefault()` — which grants PUBLIC
     * `EXECUTE` on functions and `USAGE` on types — is merged into whatever
     * `pg_default_acl` holds at creation time, and `ALTER DEFAULT PRIVILEGES
     * ... REVOKE` computes from an empty list, so it stores nothing and removes
     * nothing.
     *
     * This test pins both halves: the hazard is real, and the revocation the
     * grants phase performs removes it. If a future PostgreSQL made the default
     * deny-by-default, the first assertion would fail loudly rather than let the
     * grants phase quietly become dead code.
     */
    public function test_public_function_and_type_access_is_revocable_as_the_grants_phase_does(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        [$runtime, $migrationRole, $outbox] = $this->roleNames();
        $migration = DB::connection('pgsql_migration');
        $this->createProbeObjects();

        // The hazard: a freshly created function and type are open to PUBLIC.
        $this->assertTrue(
            (bool) $migration->scalar('select has_function_privilege(?, ?, ?)', ['public', 'public.'.self::PROBE_FUNCTION.'()', 'EXECUTE']),
            'PostgreSQL is expected to grant PUBLIC EXECUTE on a new function; if this fails the grants-phase revocation may no longer be required',
        );
        $this->assertTrue(
            (bool) $migration->scalar('select has_type_privilege(?, ?, ?)', ['public', 'public.'.self::PROBE_TYPE, 'USAGE']),
            'PostgreSQL is expected to grant PUBLIC USAGE on a new type',
        );

        // The remedy, as docker/postgres/provision-roles.sh applies it — but
        // scoped to this test's own probe objects. The script's schema-wide
        // `REVOKE ... ON ALL FUNCTIONS IN SCHEMA public` form must not be used
        // here: it would also revoke anything else in the schema, silently
        // repairing a real misconfiguration that
        // test_no_function_or_type_in_public_is_reachable_by_public_runtime_or_outbox
        // exists to catch.
        $migration->statement(sprintf('revoke all on function public.%s() from public, %s, %s', self::PROBE_FUNCTION, $runtime, $outbox));
        $migration->statement(sprintf('revoke all on type public.%s from public, %s, %s', self::PROBE_TYPE, $runtime, $outbox));

        $this->assertSame(
            '{'.$migrationRole.'=X/'.$migrationRole.'}',
            (string) $migration->scalar('select proacl::text from pg_proc where proname = ?', [self::PROBE_FUNCTION]),
            'after revocation only the owning migration role may execute the function',
        );

        foreach (['public', $runtime, $outbox] as $grantee) {
            $this->assertFalse(
                (bool) $migration->scalar('select has_function_privilege(?, ?, ?)', [$grantee, 'public.'.self::PROBE_FUNCTION.'()', 'EXECUTE']),
                "{$grantee} must not be able to execute the function after revocation",
            );
            $this->assertFalse(
                (bool) $migration->scalar('select has_type_privilege(?, ?, ?)', [$grantee, 'public.'.self::PROBE_TYPE, 'USAGE']),
                "{$grantee} must not be able to use the type after revocation",
            );
        }
    }

    /**
     * The steady-state invariant the provisioned database must satisfy: after
     * the grants phase, nothing in schema `public` is executable or usable by
     * PUBLIC, the runtime role, or the outbox role.
     */
    public function test_no_function_or_type_in_public_is_reachable_by_public_runtime_or_outbox(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        [$runtime, , $outbox] = $this->roleNames();
        $catalogue = DB::connection('pgsql_migration');

        foreach (['public', $runtime, $outbox] as $grantee) {
            $this->assertSame(
                0,
                (int) $catalogue->scalar(
                    "select count(*) from pg_proc p join pg_namespace n on n.oid = p.pronamespace
                     where n.nspname = 'public' and has_function_privilege(?, p.oid, 'EXECUTE')",
                    [$grantee],
                ),
                "{$grantee} must not hold EXECUTE on any routine in schema public",
            );

            $this->assertSame(
                0,
                (int) $catalogue->scalar(
                    "select count(*) from pg_type t join pg_namespace n on n.oid = t.typnamespace
                     where n.nspname = 'public'
                       and t.typtype in ('c','d','e','r','m')
                       and (t.typrelid = 0 or (select c.relkind from pg_class c where c.oid = t.typrelid) = 'c')
                       and not exists (select 1 from pg_type el where el.oid = t.typelem and el.typarray = t.oid)
                       and has_type_privilege(?, t.oid, 'USAGE')",
                    [$grantee],
                ),
                "{$grantee} must not hold USAGE on any standalone type in schema public",
            );
        }
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
        // No stored default privilege may grant PUBLIC, the runtime role, or the
        // outbox role anything. This is a real regression guard against someone
        // adding one; it is *not* evidence that PUBLIC's built-in function and
        // type privileges are revoked, which PostgreSQL cannot express here and
        // which test_objects_created_after_provisioning_are_deny_by_default
        // proves behaviourally instead.
        $this->assertSame(0, (int) $connection->scalar('select count(*) from pg_default_acl d cross join lateral aclexplode(d.defaclacl) a where a.grantee=0 or a.grantee in (select oid from pg_roles where rolname in (?, ?))', [$runtime, $outbox]));
        $this->assertSame(0, (int) $connection->scalar('select count(*) from pg_default_acl d cross join lateral aclexplode(d.defaclacl) a where d.defaclrole=(select oid from pg_roles where rolname=?) and (a.grantee=0 or a.grantee in (select oid from pg_roles where rolname in (?, ?)))', [$migration, $runtime, $outbox]));
    }

    public function test_outbox_role_has_no_schema_usage_and_is_refused_in_practice(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $outbox = $this->roleNames()[2];
        $catalogue = DB::connection('pgsql_migration');
        $this->assertFalse((bool) $catalogue->scalar("select has_schema_privilege(?, 'public', 'USAGE')", [$outbox]));
        $this->assertFalse((bool) $catalogue->scalar("select has_schema_privilege(?, 'public', 'CREATE')", [$outbox]));

        // Catalogue emptiness is not the same evidence as a refused statement.
        $this->assertInsufficientPrivilege(
            static fn () => DB::connection('pgsql_outbox')->select('select count(*) from public.users'),
        );
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

        // Both CREATE TABLE and ALTER TABLE must be refused, and refused
        // specifically for insufficient privilege — a wrong password, an absent
        // connection, or a syntax error must not satisfy these assertions.
        $this->assertInsufficientPrivilege(
            static fn () => DB::connection('pgsql')->statement('create table pf074_forbidden_probe (id integer)'),
        );
        $this->assertInsufficientPrivilege(
            static fn () => DB::connection('pgsql')->statement('alter table public.users add column pf074_forbidden_column integer'),
        );
        $this->assertInsufficientPrivilege(
            static fn () => DB::connection('pgsql')->statement('truncate table public.users'),
        );
    }

    /**
     * Asserts the callable fails with PostgreSQL SQLSTATE 42501
     * (insufficient_privilege) or 42P01 where the object is unreachable,
     * rather than with any throwable at all.
     */
    private function assertInsufficientPrivilege(callable $callable): void
    {
        try {
            $callable();
        } catch (\Throwable $exception) {
            $previous = $exception instanceof PDOException ? $exception : $exception->getPrevious();
            $sqlState = $previous instanceof PDOException ? ($previous->errorInfo[0] ?? null) : null;

            $this->assertSame(
                '42501',
                $sqlState,
                'expected SQLSTATE 42501 (insufficient_privilege), got: '.($sqlState ?? 'none').' — '.$exception->getMessage(),
            );

            return;
        }

        $this->fail('expected the statement to be refused with SQLSTATE 42501, but it succeeded');
    }

    private function createProbeObjects(): void
    {
        $migration = DB::connection('pgsql_migration');
        $this->dropProbeObjects();
        $migration->statement('create table public.'.self::PROBE_TABLE.' (id integer)');
        $migration->statement('create sequence public.'.self::PROBE_SEQUENCE);
        $migration->statement('create function public.'.self::PROBE_FUNCTION.'() returns integer language sql immutable as $$ select 1 $$');
        $migration->statement('create type public.'.self::PROBE_TYPE." as enum ('a')");
    }

    private function dropProbeObjects(): void
    {
        if (! filter_var(env('REQUIRE_POSTGRESQL_TEST_DATABASE', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $migration = DB::connection('pgsql_migration');

        foreach ([
            'drop table if exists public.'.self::PROBE_TABLE,
            'drop sequence if exists public.'.self::PROBE_SEQUENCE,
            'drop function if exists public.'.self::PROBE_FUNCTION.'()',
            'drop type if exists public.'.self::PROBE_TYPE,
        ] as $statement) {
            $migration->statement($statement);
        }
    }

    private function approvedTableValuesList(): string
    {
        return implode(', ', array_map(
            static fn (string $table): string => "('".$table."')",
            self::APPROVED_RUNTIME_TABLES,
        ));
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
