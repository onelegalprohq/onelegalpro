<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class ModuleMigrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $paths = self::discoverMigrationPaths(app_path('Modules'));

        if ($paths !== []) {
            $this->loadMigrationsFrom($paths);
        }
    }

    /**
     * @return list<string>
     */
    public static function discoverMigrationPaths(string $modulesRoot): array
    {
        $matches = glob(rtrim($modulesRoot, DIRECTORY_SEPARATOR).'/*/Database/Migrations', GLOB_ONLYDIR);

        if ($matches === false) {
            return [];
        }

        $paths = [];

        foreach ($matches as $match) {
            $canonical = realpath($match);

            if ($canonical !== false) {
                $paths[$canonical] = $canonical;
            }
        }

        $paths = array_values($paths);
        sort($paths, SORT_STRING);

        return $paths;
    }
}
