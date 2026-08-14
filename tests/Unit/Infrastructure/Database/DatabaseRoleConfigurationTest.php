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

        // Capture *every* job-level `name:` value, not only the four expected
        // ones. An alternation listing the expected names can never match
        // anything else, so a fifth job would have left the assertion green.
        // Job-level keys sit at exactly four spaces of indentation; step-level
        // `- name:` entries and other keys are excluded by the anchor.
        preg_match_all('/^ {4}name: (.+)$/m', $ci."\n".$security, $matches);

        $names = array_values(array_unique(array_map(
            static fn (string $name): string => trim($name, " \t\"'"),
            $matches[1],
        )));
        sort($names);

        $this->assertSame(
            ['Application Tests', 'Dependency Audit', 'Frontend Build', 'PHP Code Quality'],
            $names,
        );
    }

    public function test_every_tracked_database_password_literal_is_visibly_disposable(): void
    {
        $files = ['.env.example', 'compose.yaml', '.github/workflows/ci.yml'];
        $checked = 0;

        foreach ($files as $file) {
            $source = (string) file_get_contents(__DIR__.'/../../../../'.$file);

            // Every database password key, whatever its value — so a
            // non-disposable literal fails instead of simply not matching.
            preg_match_all(
                '/^\s*(?:-\s*)?((?:DB|POSTGRES|PG|PF074)[A-Z0-9_]*PASSWORD)\s*[:=]\s*[\'"]?([^\s\'"#]*)/m',
                $source,
                $matches,
                PREG_SET_ORDER,
            );

            $this->assertNotEmpty($matches, $file.' should declare at least one database password key.');

            foreach ($matches as [, $key, $value]) {
                $checked++;
                $this->assertMatchesRegularExpression(
                    '/_(?:dev|test)_only$/',
                    $value,
                    $file.': '.$key.' must end in _dev_only or _test_only so it is visibly disposable.',
                );
            }
        }

        $this->assertGreaterThanOrEqual(18, $checked, 'the password scan should cover every tracked database credential.');

        $script = (string) file_get_contents(__DIR__.'/../../../../docker/postgres/provision-roles.sh');
        $this->assertStringNotContainsString('_dev_only', $script);
        $this->assertStringNotContainsString('_test_only', $script);
    }

    public function test_the_local_env_file_is_gitignored(): void
    {
        $gitignore = (string) file_get_contents(__DIR__.'/../../../../.gitignore');
        $lines = array_map('trim', explode("\n", $gitignore));

        // `.env.example` is un-ignored with a `!` prefix, so a plain
        // "contains .env" check passes on that negation alone. Require a real
        // ignore rule for `.env` itself.
        $this->assertContains(
            true,
            array_map(
                static fn (string $line): bool => in_array($line, ['.env', '/.env', '.env*', '/.env*'], true),
                $lines,
            ),
            '.gitignore must ignore .env itself, not only mention it.',
        );
    }
}
