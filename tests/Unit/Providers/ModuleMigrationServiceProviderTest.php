<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\ModuleMigrationServiceProvider;
use PHPUnit\Framework\TestCase;

final class ModuleMigrationServiceProviderTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = sys_get_temp_dir().'/onelegalpro-pf064-'.bin2hex(random_bytes(8));
        mkdir($this->fixtureRoot, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->fixtureRoot);

        parent::tearDown();
    }

    public function test_discovery_is_glob_based_canonical_deduplicated_and_sorted(): void
    {
        $second = $this->migrationDirectory('Zulu');
        $first = $this->migrationDirectory('Alpha');
        mkdir($this->fixtureRoot.'/NoMigrations', 0700, true);

        $this->assertSame(
            [realpath($first), realpath($second)],
            ModuleMigrationServiceProvider::discoverMigrationPaths($this->fixtureRoot),
        );

        $this->assertSame(
            ModuleMigrationServiceProvider::discoverMigrationPaths($this->fixtureRoot),
            ModuleMigrationServiceProvider::discoverMigrationPaths($this->fixtureRoot),
        );
    }

    public function test_absent_migration_directories_are_silently_skipped(): void
    {
        mkdir($this->fixtureRoot.'/DomainOnly', 0700, true);

        $this->assertSame([], ModuleMigrationServiceProvider::discoverMigrationPaths($this->fixtureRoot));
        $this->assertSame([], ModuleMigrationServiceProvider::discoverMigrationPaths($this->fixtureRoot.'/missing'));
    }

    public function test_provider_source_contains_no_business_module_name_or_registration_surface(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/app/Providers/ModuleMigrationServiceProvider.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('glob(rtrim($modulesRoot', $source);
        $this->assertStringContainsString('loadMigrationsFrom($paths)', $source);
        $this->assertStringNotContainsString('PlatformAdministration', $source);
        $this->assertStringNotContainsString('ModuleServiceProvider.php', $source);
        $this->assertStringNotContainsString('loadRoutesFrom', $source);
        $this->assertStringNotContainsString('mergeConfigFrom', $source);
    }

    private function migrationDirectory(string $module): string
    {
        $path = $this->fixtureRoot.'/'.$module.'/Database/Migrations';
        mkdir($path, 0700, true);

        return $path;
    }

    private function removeTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path.'/'.$entry;

            if (is_dir($child) && ! is_link($child)) {
                $this->removeTree($child);
            } else {
                unlink($child);
            }
        }

        rmdir($path);
    }
}
