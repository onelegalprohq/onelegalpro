<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Providers\ModuleMigrationServiceProvider;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ModuleMigrationServiceProviderPostgreSqlTest extends TestCase
{
    private const MODULE = 'Pf064EphemeralFixture';

    private const MIGRATION = '2099_12_31_235959_create_pf064_ephemeral_evidence_table';

    private const TABLE = 'pf064_ephemeral_evidence';

    /**
     * Migration commands must run as the owning migration role. Since PF-074
     * merged, the default `pgsql` connection is the non-owning runtime role,
     * which holds no privilege on `migrations` and cannot create a relation —
     * so an unqualified `migrate` here fails with SQLSTATE 42501.
     *
     * PF-064 landed second on the two stories' single shared surface, and the
     * accepted contract directs that "whichever story lands second adapts only
     * its own invocation". This constant is that adaptation, and it is confined
     * to the *invocation*: the discovery assertions below still read the
     * migrator's own resolved paths and assume no connection whatever.
     */
    private const MIGRATION_CONNECTION = 'pgsql_migration';

    private string $fixtureDirectory;

    private string $fixtureFile;

    private bool $armed = false;

    /**
     * Relation grants captured before this suite's first destructive command,
     * as ready-to-execute `GRANT` statements paired with their relation name.
     *
     * `migrate:fresh` drops and recreates every relation, and a recreated
     * relation carries no privileges — so running it discards the role grants
     * PF-074 provisions and leaves the runtime role able to reach nothing.
     * Restoring them is this suite's responsibility because it is this suite
     * that destroys them: the contract requires it to prove `migrate:fresh`
     * *and* to leave the database usable for whatever runs next.
     *
     * The snapshot is read from the catalogue rather than from a hardcoded
     * list, so it stays correct if PF-074's approved set ever changes.
     *
     * @var list<array{relname: string, statement: string}>
     */
    private array $grantSnapshot = [];

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->fixtureDirectory = $root.'/app/Modules/'.self::MODULE.'/Database/Migrations';
        $this->fixtureFile = $this->fixtureDirectory.'/'.self::MIGRATION.'.php';

        if (is_dir($root.'/app/Modules/'.self::MODULE)) {
            throw new \RuntimeException('The PF-064 ephemeral fixture leaked from an earlier run.');
        }

        mkdir($this->fixtureDirectory, 0700, true);
        file_put_contents($this->fixtureFile, $this->migrationSource());

        try {
            parent::setUp();
        } catch (\Throwable $exception) {
            $this->removeFixture();

            throw $exception;
        }

        $this->armed = filter_var(env('REQUIRE_POSTGRESQL_TEST_DATABASE', false), FILTER_VALIDATE_BOOL);

        if ($this->armed) {
            $this->grantSnapshot = $this->captureGrants();
        }
    }

    protected function tearDown(): void
    {
        $this->removeFixture();

        if ($this->armed) {
            Artisan::call('migrate:fresh', ['--force' => true, '--database' => self::MIGRATION_CONNECTION]);
            $this->replayGrants();
        }

        $this->assertFileDoesNotExist($this->fixtureFile);
        $this->assertDirectoryDoesNotExist(dirname($this->fixtureDirectory, 2));

        parent::tearDown();
    }

    public function test_ordinary_migration_commands_discover_the_module_path_and_leave_the_database_migrated(): void
    {
        if (! $this->armed) {
            $this->addToAssertionCount(1);

            return;
        }

        $migrator = $this->app->make(Migrator::class);
        $canonicalFixture = realpath($this->fixtureDirectory);

        $this->assertIsString($canonicalFixture);
        $this->assertContains($canonicalFixture, $migrator->paths());
        (new ModuleMigrationServiceProvider($this->app))->boot();
        (new ModuleMigrationServiceProvider($this->app))->boot();
        $this->assertSame(1, array_count_values($migrator->paths())[$canonicalFixture]);
        $this->assertArrayHasKey(self::MIGRATION, $migrator->getMigrationFiles($migrator->paths()));
        $this->assertGloballyUniqueMigrationBasenames($migrator);

        Artisan::call('migrate', ['--force' => true, '--database' => self::MIGRATION_CONNECTION]);
        $this->assertTrue(Schema::hasTable(self::TABLE));

        Artisan::call('migrate:status', ['--database' => self::MIGRATION_CONNECTION]);
        $status = Artisan::output();
        $this->assertStringContainsString(self::MIGRATION, $status);
        $this->assertMatchesRegularExpression('/'.preg_quote(self::MIGRATION, '/').'.*Ran/s', $status);

        Artisan::call('migrate:rollback', ['--force' => true, '--database' => self::MIGRATION_CONNECTION]);
        $this->assertFalse(Schema::hasTable(self::TABLE));

        Artisan::call('migrate:fresh', ['--force' => true, '--database' => self::MIGRATION_CONNECTION]);
        $this->assertTrue(Schema::hasTable(self::TABLE));

        $this->removeFixture();
        Artisan::call('migrate:fresh', ['--force' => true, '--database' => self::MIGRATION_CONNECTION]);

        $this->assertFalse(Schema::hasTable(self::TABLE));
        $this->assertTrue(Schema::hasTable('migrations'));
    }

    /**
     * PostgreSQL builds each `GRANT` statement itself via `format(... %I ...)`,
     * so identifiers are quoted by the server rather than assembled in PHP.
     * The relation owner is excluded — ownership survives `migrate:fresh`
     * differently and needs no grant — and so is `PUBLIC` (grantee 0), which
     * PF-074 revokes and which must never be re-granted here.
     *
     * @return list<array{relname: string, statement: string}>
     */
    private function captureGrants(): array
    {
        $rows = DB::connection(self::MIGRATION_CONNECTION)->select(
            "select c.relname as relname,
                    format('GRANT %s ON %s %I.%I TO %I',
                           string_agg(a.privilege_type, ', ' order by a.privilege_type),
                           case c.relkind when 'S' then 'SEQUENCE' else 'TABLE' end,
                           n.nspname, c.relname, pg_get_userbyid(a.grantee)) as statement
             from pg_class c
             join pg_namespace n on n.oid = c.relnamespace
             cross join lateral aclexplode(c.relacl) a
             where n.nspname = 'public'
               and c.relkind in ('r', 'p', 'S', 'v', 'm')
               and a.grantee <> 0
               and a.grantee <> c.relowner
             group by c.relkind, n.nspname, c.relname, a.grantee
             order by c.relname"
        );

        return array_map(
            static fn (object $row): array => [
                'relname' => (string) $row->relname,
                'statement' => (string) $row->statement,
            ],
            $rows,
        );
    }

    /**
     * Replays the captured grants, skipping any relation that no longer exists
     * — the ephemeral fixture's own relation is deliberately gone by this
     * point, and re-granting it would fail.
     */
    private function replayGrants(): void
    {
        $connection = DB::connection(self::MIGRATION_CONNECTION);

        foreach ($this->grantSnapshot as $grant) {
            $exists = $connection->scalar('select to_regclass(?) is not null', ['public.'.$grant['relname']]);

            if ((bool) $exists) {
                $connection->statement($grant['statement']);
            }
        }
    }

    private function assertGloballyUniqueMigrationBasenames(Migrator $migrator): void
    {
        $paths = array_merge([database_path('migrations')], $migrator->paths());
        $basenames = [];

        foreach ($paths as $path) {
            foreach (glob($path.'/*_*.php') ?: [] as $file) {
                $basenames[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        $this->assertNotEmpty($basenames);
        $this->assertSame($basenames, array_values(array_unique($basenames)));
    }

    private function migrationSource(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pf064_ephemeral_evidence', static function (Blueprint $table): void {
            $table->id();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pf064_ephemeral_evidence');
    }
};
PHP;
    }

    private function removeFixture(): void
    {
        if (is_file($this->fixtureFile)) {
            unlink($this->fixtureFile);
        }

        $path = $this->fixtureDirectory;

        while (str_starts_with($path, dirname($this->fixtureDirectory, 3)) && is_dir($path)) {
            rmdir($path);

            if (basename($path) === self::MODULE) {
                break;
            }

            $path = dirname($path);
        }
    }
}
