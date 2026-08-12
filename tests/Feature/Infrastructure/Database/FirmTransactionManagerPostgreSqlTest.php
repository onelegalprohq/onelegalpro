<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Database;

use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Tenancy\FirmContext;
use App\Infrastructure\Database\FirmTransactionManager;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PF-073 — the PostgreSQL proofs for the Firm transaction boundary.
 *
 * These behaviours exist only on PostgreSQL: transaction-local settings, role
 * attributes, and the reversion of `set_config(..., true)` at transaction end
 * are all untestable on SQLite. This class therefore uses the existing PF-033
 * `REQUIRE_POSTGRESQL_TEST_DATABASE` gate exactly as
 * `tests/Feature/PostgreSqlTestDatabaseGuardTest.php` does: an ordinary local
 * SQLite run records the guard assertion and returns without PostgreSQL-only
 * work, while CI separately asserts the flag is exactly `true` before the suite
 * starts, so the local no-op cannot silently disarm the authoritative CI guard.
 * No workflow and no `phpunit.xml` change is involved.
 *
 * No ambient-transaction trait is used here or in any helper below: the contract
 * requires every manager call to begin at transaction level zero.
 *
 * Evidence is test-owned and temporary — a `temp` relation created by the test
 * itself, dropped with the session. **No production schema, migration, policy,
 * role, grant, or Firm-scoped relation is created**, and nothing here proves
 * Row-Level Security, which PF-073 neither creates nor claims.
 */
final class FirmTransactionManagerPostgreSqlTest extends TestCase
{
    private const FIRM_ID = '019fa14f-813e-702f-aa24-5b85bd74d75f';

    private const OTHER_FIRM_ID = '019fb2c8-5d41-7a02-9c3e-6f0c4a1b83d2';

    private const ACTOR_ID = '019fa154-ef35-73b7-99c9-7b422ec3172b';

    private const CORRELATION_ID = '019fa15b-252c-7216-8f16-1af932c30b4e';

    /** The test's own reader for the canonical setting, independent of the adapter's. */
    private const PROBE = "select nullif(current_setting(?, true), '')";

    public function test_the_runtime_test_role_passes_the_preflight_and_runs_a_transaction(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $connection = $this->connection();

        $this->assertSame('pgsql', $connection->getDriverName());

        $role = $connection->selectOne(
            'select rolsuper, rolbypassrls from pg_roles where rolname = current_user',
        );

        $this->assertNotNull($role);
        $this->assertFalse($role->rolsuper);
        $this->assertFalse($role->rolbypassrls);

        $result = $this->manager()->run($this->context(), static fn (Connection $managed): string => 'ran');

        $this->assertSame('ran', $result);
    }

    public function test_a_seeded_session_setting_is_detected_the_connection_discarded_and_never_reused(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $connection = $this->connection();

        $connection->scalar(
            'select set_config(?, ?, false)',
            [FirmTransactionManager::FIRM_SETTING, self::OTHER_FIRM_ID],
            false,
        );

        $this->assertSame(self::OTHER_FIRM_ID, $this->settingOn($connection));

        $invoked = false;

        try {
            $this->manager()->run($this->context(), static function () use (&$invoked): string {
                $invoked = true;

                return 'must not run';
            });
            $this->fail('A contaminated connection must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('already carried a Firm setting', $exception->getMessage());
        }

        $this->assertFalse($invoked, 'The callback must not run on a contaminated connection.');
        $this->assertNull($connection->getRawPdo(), 'The contaminated connection must be discarded.');

        DB::reconnect();

        $this->assertNull(
            $this->settingOn($this->connection()),
            'No leaked Firm value may survive onto the replacement connection.',
        );
    }

    public function test_the_exact_firm_setting_is_visible_inside_the_managed_callback(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $connection = $this->connection();

        $observed = $this->manager()->run(
            $this->context(),
            fn (Connection $managed): ?string => $this->settingOn($managed),
        );

        $this->assertSame(self::FIRM_ID, $observed);
        $this->assertNotSame(self::OTHER_FIRM_ID, $observed);
        $this->assertNull($this->settingOn($connection));
    }

    public function test_the_callback_receives_the_exact_managed_connection(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $connection = $this->connection();
        $received = null;

        $this->manager()->run($this->context(), static function (Connection $managed) use (&$received): bool {
            $received = $managed;

            return true;
        });

        $this->assertSame($connection, $received);
    }

    public function test_a_read_callback_receives_the_same_boundary_as_a_write(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $connection = $this->connection();

        $connection->statement('create temp table pf073_read_evidence (note text not null)');
        $connection->statement("insert into pf073_read_evidence (note) values ('seeded')");

        $read = $this->manager()->run($this->context(), fn (Connection $managed): array => [
            'setting' => $this->settingOn($managed),
            'rows' => $managed->scalar('select count(*) from pf073_read_evidence where note = ?', ['seeded'], false),
            'level' => $managed->transactionLevel(),
        ]);

        $this->assertSame(self::FIRM_ID, $read['setting']);
        $this->assertSame(1, (int) $read['rows']);
        $this->assertSame(1, $read['level'], 'A read runs inside the same explicit transaction as a write.');
    }

    public function test_another_manager_entry_cannot_change_the_established_setting(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $manager = $this->manager();
        $rejected = null;

        $observed = $manager->run($this->context(), function (Connection $managed) use ($manager, &$rejected): ?string {
            try {
                $manager->run(
                    $this->context(self::OTHER_FIRM_ID),
                    static fn (Connection $inner): string => 'must not run',
                );
                $this->fail('A nested manager entry must be rejected.');
            } catch (\LogicException $exception) {
                $rejected = $exception;
            }

            return $this->settingOn($managed);
        });

        $this->assertInstanceOf(\LogicException::class, $rejected);
        $this->assertStringContainsString('transaction level zero', $rejected->getMessage());
        $this->assertSame(self::FIRM_ID, $observed);
    }

    public function test_an_un_restored_setting_change_inside_the_callback_is_detected_before_commit(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $connection = $this->connection();

        try {
            $this->manager()->run($this->context(), static function (Connection $managed): string {
                $managed->scalar(
                    'select set_config(?, ?, true)',
                    [FirmTransactionManager::FIRM_SETTING, self::OTHER_FIRM_ID],
                    false,
                );

                return 'switched';
            });
            $this->fail('An un-restored Firm setting change must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('no longer matched the supplied Firm', $exception->getMessage());
            $this->assertStringContainsString('did not commit', $exception->getMessage());
        }

        $this->assertNull($connection->getRawPdo(), 'A contaminated connection must be discarded.');

        DB::reconnect();

        $this->assertNull($this->settingOn($this->connection()));
    }

    public function test_the_setting_is_absent_after_commit_on_the_same_reused_connection(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $connection = $this->connection();

        $this->manager()->run($this->context(), static fn (Connection $managed): string => 'committed');

        $this->assertSame($connection, $this->connection(), 'The connection is reused, not replaced.');
        $this->assertNotNull($connection->getRawPdo(), 'A clean connection is not discarded.');
        $this->assertNull($this->settingOn($connection));
        $this->assertSame(0, $connection->transactionLevel());
    }

    public function test_the_setting_is_absent_after_rollback_on_the_same_reused_connection(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $connection = $this->connection();
        $failure = new \DomainException('callback failed');

        try {
            $this->manager()->run($this->context(), static function () use ($failure): never {
                throw $failure;
            });
            $this->fail('The callback failure must propagate.');
        } catch (\Throwable $caught) {
            $this->assertSame($failure, $caught, 'The exact original throwable is rethrown.');
        }

        $this->assertNotNull($connection->getRawPdo());
        $this->assertNull($this->settingOn($connection));
        $this->assertSame(0, $connection->transactionLevel());
    }

    public function test_callback_work_commits_on_success_and_rolls_back_on_failure(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $connection = $this->connection();

        $connection->statement('create temp table pf073_evidence (note text not null)');

        $this->manager()->run($this->context(), static function (Connection $managed): void {
            $managed->insert('insert into pf073_evidence (note) values (?)', ['committed']);
        });

        $this->assertSame(1, $this->evidenceCount($connection, 'committed'));

        try {
            $this->manager()->run($this->context(), static function (Connection $managed): never {
                $managed->insert('insert into pf073_evidence (note) values (?)', ['rolled-back']);

                throw new \DomainException('callback failed after writing');
            });
            $this->fail('The callback failure must propagate.');
        } catch (\DomainException) {
            // the exact-instance rethrow is proved above
        }

        $this->assertSame(0, $this->evidenceCount($connection, 'rolled-back'));
        $this->assertSame(1, $this->evidenceCount($connection, 'committed'));
    }

    public function test_an_ambient_transaction_fails_before_any_callback_work(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $connection = $this->connection();

        $connection->statement('create temp table pf073_ambient_evidence (note text not null)');
        $connection->beginTransaction();

        $invoked = false;

        try {
            $this->manager()->run($this->context(), static function (Connection $managed) use (&$invoked): string {
                $invoked = true;

                $managed->insert('insert into pf073_ambient_evidence (note) values (?)', ['must not run']);

                return 'must not run';
            });
            $this->fail('An ambient transaction must be rejected.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('transaction level zero', $exception->getMessage());
        } finally {
            $connection->rollBack();
        }

        $this->assertFalse($invoked);
        $this->assertSame(0, $this->evidenceCount($connection, 'must not run', 'pf073_ambient_evidence'));
        $this->assertNull($this->settingOn($connection));
    }

    public function test_this_suite_applies_no_ambient_transaction_trait(): void
    {
        $this->assertSame([], class_uses(self::class));

        $source = file_get_contents(__FILE__);

        $this->assertIsString($source);

        $code = '';

        foreach (token_get_all($source) as $token) {
            if (\is_array($token) && \in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= \is_array($token) ? $token[1] : $token;
        }

        // Assembled from fragments so this assertion cannot satisfy itself.
        foreach (['Refresh'.'Database', 'Database'.'Transactions', 'Database'.'Migrations'] as $trait) {
            $this->assertSame(0, substr_count($code, $trait), 'This suite must not use '.$trait.'.');
        }
    }

    /**
     * The PF-033 gate, applied exactly as `PostgreSqlTestDatabaseGuardTest` does.
     */
    private function postgreSqlRequired(): bool
    {
        if (! filter_var(env('REQUIRE_POSTGRESQL_TEST_DATABASE', false), FILTER_VALIDATE_BOOL)) {
            // Ordinary local SQLite runs remain supported. CI separately asserts
            // that this flag is exactly true before the suite starts, so this
            // local no-op cannot silently disarm the authoritative CI guard.
            $this->addToAssertionCount(1);

            return false;
        }

        return true;
    }

    private function connection(): Connection
    {
        return DB::connection();
    }

    private function manager(): FirmTransactionManager
    {
        return new FirmTransactionManager($this->connection());
    }

    private function context(string $firmId = self::FIRM_ID): FirmContext
    {
        return new FirmContext(
            firmId: PostgreSqlTestFirmIdentifier::fromString($firmId),
            actorId: PostgreSqlTestActorIdentifier::fromString(self::ACTOR_ID),
            correlationId: UuidV7::fromString(self::CORRELATION_ID),
        );
    }

    private function settingOn(Connection $connection): ?string
    {
        $value = $connection->scalar(self::PROBE, [FirmTransactionManager::FIRM_SETTING], false);

        return $value === null ? null : (string) $value;
    }

    private function evidenceCount(Connection $connection, string $note, string $table = 'pf073_evidence'): int
    {
        return (int) $connection->scalar('select count(*) from '.$table.' where note = ?', [$note], false);
    }
}

final readonly class PostgreSqlTestFirmIdentifier extends BusinessIdentifier {}

final readonly class PostgreSqlTestActorIdentifier extends BusinessIdentifier {}
