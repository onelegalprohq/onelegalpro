<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Providers\ModuleMigrationServiceProvider;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ModuleMigrationServiceProviderPostgreSqlTest extends TestCase
{
    private const MODULE = 'Pf064EphemeralFixture';

    private const MIGRATION = '2099_12_31_235959_create_pf064_ephemeral_evidence_table';

    private const TABLE = 'pf064_ephemeral_evidence';

    private string $fixtureDirectory;

    private string $fixtureFile;

    private bool $armed = false;

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
    }

    protected function tearDown(): void
    {
        $this->removeFixture();

        if ($this->armed) {
            Artisan::call('migrate:fresh', ['--force' => true]);
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

        Artisan::call('migrate', ['--force' => true]);
        $this->assertTrue(Schema::hasTable(self::TABLE));

        Artisan::call('migrate:status');
        $status = Artisan::output();
        $this->assertStringContainsString(self::MIGRATION, $status);
        $this->assertMatchesRegularExpression('/'.preg_quote(self::MIGRATION, '/').'.*Ran/s', $status);

        Artisan::call('migrate:rollback', ['--force' => true]);
        $this->assertFalse(Schema::hasTable(self::TABLE));

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->assertTrue(Schema::hasTable(self::TABLE));

        $this->removeFixture();
        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertFalse(Schema::hasTable(self::TABLE));
        $this->assertTrue(Schema::hasTable('migrations'));
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
