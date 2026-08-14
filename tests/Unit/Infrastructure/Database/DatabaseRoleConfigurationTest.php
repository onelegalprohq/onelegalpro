<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Database;

use PHPUnit\Framework\TestCase;

final class DatabaseRoleConfigurationTest extends TestCase
{
    public function test_the_three_postgresql_connections_use_distinct_credential_keys(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../config/database.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("'pgsql_migration' => [", $source);
        $this->assertStringContainsString("'pgsql_outbox' => [", $source);
        $this->assertMatchesRegularExpression("/'pgsql' => \[.*?'username' => env\('DB_USERNAME'.*?'password' => env\('DB_PASSWORD'/s", $source);
        $this->assertMatchesRegularExpression("/'pgsql_migration' => \[.*?'username' => env\('DB_MIGRATION_USERNAME'.*?'password' => env\('DB_MIGRATION_PASSWORD'/s", $source);
        $this->assertMatchesRegularExpression("/'pgsql_outbox' => \[.*?'username' => env\('DB_OUTBOX_USERNAME'.*?'password' => env\('DB_OUTBOX_PASSWORD'/s", $source);
    }

    public function test_compose_routes_application_and_queue_through_the_runtime_role(): void
    {
        $compose = file_get_contents(__DIR__.'/../../../../compose.yaml');

        $this->assertIsString($compose);
        $this->assertSame(2, substr_count($compose, 'DB_USERNAME: onelegalpro_app'));
        $this->assertSame(2, substr_count($compose, 'DB_PASSWORD: onelegalpro_app_dev_only'));
        $this->assertStringContainsString('./docker/postgres/provision-roles.sh:/docker-entrypoint-initdb.d/10-provision-roles.sh:ro', $compose);
        $this->assertStringNotContainsString('DB_USERNAME: onelegalpro_migration', $compose);
    }

    public function test_migrations_explicitly_use_the_migration_connection(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $workflow = file_get_contents(__DIR__.'/../../../../.github/workflows/ci.yml');

        $this->assertContains('@php artisan migrate --force --database=pgsql_migration', $composer['scripts']['setup']);
        $this->assertIsString($workflow);
        $this->assertStringContainsString('php artisan migrate --force --no-interaction --database=pgsql_migration', $workflow);
        $this->assertStringContainsString('sh docker/postgres/provision-roles.sh bootstrap', $workflow);
        $this->assertStringContainsString('sh docker/postgres/provision-roles.sh grants', $workflow);
        $this->assertStringContainsString("REQUIRE_POSTGRESQL_TEST_DATABASE: 'true'", $workflow);
    }

    public function test_only_the_four_protected_check_names_are_declared(): void
    {
        $ci = (string) file_get_contents(__DIR__.'/../../../../.github/workflows/ci.yml');
        $security = (string) file_get_contents(__DIR__.'/../../../../.github/workflows/security.yml');
        preg_match_all('/^\s+name: (PHP Code Quality|Frontend Build|Application Tests|Dependency Audit)$/m', $ci."\n".$security, $matches);

        $names = array_values(array_unique(array_values($matches[1])));
        sort($names);

        $this->assertSame(
            ['Application Tests', 'Dependency Audit', 'Frontend Build', 'PHP Code Quality'],
            $names,
        );
    }

    public function test_tracked_database_passwords_are_disposable_and_the_bootstrap_has_no_literal(): void
    {
        $files = ['.env.example', 'compose.yaml', '.github/workflows/ci.yml'];

        foreach ($files as $file) {
            $source = (string) file_get_contents(__DIR__.'/../../../../'.$file);
            preg_match_all('/(?:PASSWORD|password):?\s*[= ]\s*[\'\"]?([A-Za-z0-9_]+_(?:dev|test)_only)/', $source, $matches);
            $this->assertNotEmpty($matches[1], $file.' must contain only visibly disposable passwords.');
        }

        $script = (string) file_get_contents(__DIR__.'/../../../../docker/postgres/provision-roles.sh');
        $this->assertStringNotContainsString('_dev_only', $script);
        $this->assertStringNotContainsString('_test_only', $script);
        $this->assertStringContainsString('.env', (string) file_get_contents(__DIR__.'/../../../../.gitignore'));
    }
}
