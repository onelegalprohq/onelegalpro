<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use PHPUnit\Framework\TestCase;

/**
 * PF-049 — architectural guard for `app/Foundation`.
 *
 * Foundation is framework-independent: nothing under `App\Foundation` may
 * depend on Laravel, Illuminate, Eloquent, or the Laravel global helpers.
 *
 * This guard is a **denylist, not an allowlist**, and deliberately so: it
 * rejects the specific framework surfaces Foundation must never touch, without
 * banning every third-party namespace forever. A future, explicitly approved,
 * domain-safe library declared as a direct Composer dependency is permitted by
 * its own story — see `app/Foundation/README.md`. A transitive package is never
 * an approved dependency.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly, boots no Laravel
 * application, reads only tracked source files, and adds no test dependency.
 */
final class FoundationLayerHasNoFrameworkDependenciesTest extends TestCase
{
    /**
     * Namespace prefixes Foundation may never reference.
     *
     * @var list<string>
     */
    private const FORBIDDEN_NAMESPACE_PREFIXES = [
        'Illuminate\\',
        'Laravel\\',
        'Livewire\\',
        'Inertia\\',
        'Orchestra\\',
        'Facade\\',
    ];

    /**
     * Laravel global helper functions Foundation may never call.
     *
     * @var list<string>
     */
    private const FORBIDDEN_GLOBAL_HELPERS = [
        'abort', 'abort_if', 'abort_unless', 'app', 'auth', 'back', 'base_path',
        'bcrypt', 'broadcast', 'cache', 'config', 'config_path', 'cookie',
        'csrf_token', 'database_path', 'dispatch', 'encrypt', 'decrypt', 'env',
        'event', 'info', 'logger', 'now', 'old', 'policy', 'public_path',
        'redirect', 'report', 'request', 'rescue', 'resolve', 'resource_path',
        'response', 'route', 'session', 'storage_path', 'today', 'to_route',
        'trans', 'url', 'validator', 'view',
    ];

    public function test_foundation_has_php_source_to_inspect(): void
    {
        $this->assertNotEmpty($this->foundationSourceFiles(), 'No PHP source found under app/Foundation.');
    }

    public function test_every_foundation_source_file_declares_strict_types(): void
    {
        foreach ($this->foundationSourceFiles() as $relativePath => $source) {
            $this->assertMatchesRegularExpression(
                '/^<\?php\s+declare\(strict_types=1\);/',
                $source,
                $relativePath.' must declare strict types.',
            );
        }
    }

    public function test_every_foundation_namespace_agrees_with_its_path(): void
    {
        foreach ($this->foundationSourceFiles() as $relativePath => $source) {
            $matched = preg_match('/^namespace\s+([^;]+);/m', $source, $matches);

            $this->assertSame(1, $matched, $relativePath.' must declare a namespace.');

            $expected = 'App\\Foundation';
            $directory = \dirname($relativePath);

            if ($directory !== '.') {
                $expected .= '\\'.str_replace('/', '\\', $directory);
            }

            $this->assertSame(
                $expected,
                trim($matches[1]),
                $relativePath.' must live in the namespace matching its path.',
            );
        }
    }

    public function test_no_foundation_source_file_references_a_framework_namespace(): void
    {
        foreach ($this->foundationSourceFiles() as $relativePath => $source) {
            foreach (self::FORBIDDEN_NAMESPACE_PREFIXES as $prefix) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(?<![A-Za-z0-9_])\\\\?'.preg_quote($prefix, '/').'/',
                    $this->withoutComments($source),
                    $relativePath.' must not depend on the '.rtrim($prefix, '\\').' namespace.',
                );
            }
        }
    }

    public function test_no_foundation_source_file_calls_a_laravel_global_helper(): void
    {
        foreach ($this->foundationSourceFiles() as $relativePath => $source) {
            $this->assertDoesNotMatchRegularExpression(
                $this->forbiddenGlobalHelperPattern(),
                $this->withoutComments($source),
                $relativePath.' must not call a Laravel global helper.',
            );
        }
    }

    /**
     * Regression cover for the helper pattern itself.
     *
     * A leading backslash makes a call resolve to the *global* function, so
     * `\config(...)` is exactly as prohibited as `config(...)` and must never
     * become a way around the guard. An object method, a static method, and a
     * namespaced function that merely shares the name are all different
     * functions and must not be reported.
     */
    public function test_the_helper_pattern_detects_qualified_and_unqualified_global_calls(): void
    {
        $prohibited = [
            "config('app.name');",
            '\config(\'app.name\');',
            'now();',
            '\now();',
            'return view($template);',
            'return \view($template);',
        ];

        foreach ($prohibited as $snippet) {
            $this->assertMatchesRegularExpression(
                $this->forbiddenGlobalHelperPattern(),
                $snippet,
                $snippet.' is a Laravel global helper call and must be detected.',
            );
        }
    }

    public function test_the_helper_pattern_ignores_methods_and_namespaced_functions(): void
    {
        $allowed = [
            '$service->config();',
            'Service::config();',
            'Vendor\config();',
            'Vendor\Nested\now();',
            '$this->view($template);',
            'self::now();',
            '$configured = true;',
            'reconfigure();',
        ];

        foreach ($allowed as $snippet) {
            $this->assertDoesNotMatchRegularExpression(
                $this->forbiddenGlobalHelperPattern(),
                $snippet,
                $snippet.' is not a Laravel global helper call and must not be reported.',
            );
        }
    }

    public function test_no_foundation_source_file_references_eloquent(): void
    {
        foreach ($this->foundationSourceFiles() as $relativePath => $source) {
            $this->assertStringNotContainsStringIgnoringCase(
                'Eloquent',
                $this->withoutComments($source),
                $relativePath.' must not depend on Eloquent.',
            );
        }
    }

    /**
     * The single pattern matching a call to a prohibited Laravel global helper.
     *
     * Both the guard above and its regression tests use this one method, so the
     * pattern can never drift between what is enforced and what is proven.
     *
     * It matches an optional leading backslash — `\config(...)` resolves to the
     * global function just as `config(...)` does — while the lookbehinds reject
     * the three lookalikes that are different functions entirely: an object
     * method (`->config()`), a static method (`::config()`), and a namespaced
     * function (`Vendor\config()`, where the backslash follows an identifier
     * character rather than opening a fully qualified global name). A variable
     * function (`$config()`) and a longer identifier ending in a helper name
     * (`reconfigure()`) are rejected for the same reason.
     */
    private function forbiddenGlobalHelperPattern(): string
    {
        $helpers = implode('|', array_map(
            static fn (string $helper): string => preg_quote($helper, '/'),
            self::FORBIDDEN_GLOBAL_HELPERS,
        ));

        return '/(?<![A-Za-z0-9_$\\\\])(?<!->)(?<!::)\\\\?(?:'.$helpers.')\s*\(/';
    }

    /**
     * Every PHP source file under app/Foundation, keyed by its path relative to
     * that directory, mapped to its contents.
     *
     * @return array<string, string>
     */
    private function foundationSourceFiles(): array
    {
        $root = \dirname(__DIR__, 3).'/app/Foundation';

        $this->assertDirectoryExists($root);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $contents = file_get_contents($path);

            $this->assertIsString($contents, 'Unable to read '.$path.'.');

            $files[ltrim(str_replace($root, '', $path), '/')] = $contents;
        }

        ksort($files);

        return $files;
    }

    /**
     * Strip comments and docblocks, so these guards inspect actual code
     * dependencies and documentation prose can never trip them.
     */
    private function withoutComments(string $source): string
    {
        $stripped = '';

        foreach (token_get_all($source) as $token) {
            if (\is_array($token) && \in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $stripped .= \is_array($token) ? $token[1] : $token;
        }

        return $stripped;
    }
}
