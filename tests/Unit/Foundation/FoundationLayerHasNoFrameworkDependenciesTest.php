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
            $this->assertSame(
                [],
                $this->globalHelperCalls($source),
                $relativePath.' must not call a Laravel global helper.',
            );
        }
    }

    /**
     * Regression cover for the detector itself — the calls it must report.
     *
     * A leading backslash makes a call resolve to the *global* function, so
     * `\config(...)` is exactly as prohibited as `config(...)` and must never
     * become a way around the guard.
     */
    public function test_the_helper_detector_reports_qualified_and_unqualified_global_calls(): void
    {
        $prohibited = [
            "config('app.name');" => 'config',
            "\\config('app.name');" => 'config',
            'now();' => 'now',
            '\now();' => 'now',
            'view($template);' => 'view',
            '\view($template);' => 'view',
            'return now();' => 'now',
            'CONFIG();' => 'config',
        ];

        foreach ($prohibited as $snippet => $expected) {
            $this->assertSame(
                [$expected],
                $this->globalHelperCalls('<?php '.$snippet),
                $snippet.' is a Laravel global helper call and must be reported.',
            );
        }
    }

    /**
     * Regression cover for the detector itself — what it must never report.
     *
     * Declaring a method named `now()` is not calling Laravel's `now()`. PF-047
     * (Clock) legitimately declares exactly that, so a declaration, an object
     * or static method, a namespaced function, a comment, a string literal, a
     * longer identifier, and a variable must all stay silent.
     */
    public function test_the_helper_detector_ignores_declarations_methods_and_literals(): void
    {
        $allowed = [
            '$service->config();',
            '$service?->config();',
            'Service::config();',
            'self::now();',
            'Vendor\config();',
            'Vendor\Nested\now();',
            "interface Clock\n{\n    public function now(): \DateTimeImmutable;\n}",
            "final class Example\n{\n    public function config(): array\n    {\n        return [];\n    }\n}",
            "function config(): void\n{\n}",
            "abstract class Base\n{\n    abstract public function view(string \$template): string;\n}",
            '// config(\'app.name\') named only in a comment',
            '/** Mentions now() and view() only in a docblock. */',
            '$sql = "config(1)";',
            "\$literal = 'now()';",
            'reconfigure();',
            '$config = 1;',
            '$now = 1;',
        ];

        foreach ($allowed as $snippet) {
            $this->assertSame(
                [],
                $this->globalHelperCalls('<?php '.$snippet),
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
     * Every prohibited Laravel global-helper call in a PHP source string.
     *
     * Both the guard above and its regression tests use this one method, so the
     * detection can never drift between what is enforced and what is proven.
     *
     * Detection is token-aware rather than textual, because a helper name in
     * source text is not by itself a call to that helper. `token_get_all()`
     * (PHP's own tokenizer — no parser library, no dependency) does the reading,
     * and a name counts as a prohibited call only when all three hold:
     *
     * 1. **The name resolves to the global function.** `T_STRING` is an
     *    unqualified name, and `T_NAME_FULLY_QUALIFIED` is a leading-backslash
     *    name — but only when it has no further separator, so `\config` counts
     *    while `\App\Support\config` does not. `T_NAME_QUALIFIED`
     *    (`Vendor\config`) and `T_NAME_RELATIVE` (`namespace\config`) name
     *    different functions and are never considered. Comparison is
     *    lowercased, since PHP function names are case-insensitive.
     * 2. **An argument list follows**, so a bare mention is not a call.
     * 3. **The preceding significant token does not make it something else** —
     *    `function` (a declaration, including PF-047's `Clock::now()`), `->` or
     *    `?->` (an object method), `::` (a static method), or `new` (an
     *    instantiation).
     *
     * Comments, docblocks, and quoted strings never produce these token types,
     * so they are ignored for free; `reconfigure` is a single distinct
     * `T_STRING`; and `$config` is a `T_VARIABLE`.
     *
     * @return list<string> the helper names called, in source order
     */
    private function globalHelperCalls(string $source): array
    {
        $tokens = array_values(array_filter(
            token_get_all($source),
            static fn (array|string $token): bool => ! \is_array($token)
                || ! \in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true),
        ));

        $calls = [];

        foreach ($tokens as $index => $token) {
            if (! \is_array($token)) {
                continue;
            }

            $name = $this->globalFunctionName($token);

            if ($name === null || ! \in_array($name, self::FORBIDDEN_GLOBAL_HELPERS, true)) {
                continue;
            }

            if (($tokens[$index + 1] ?? null) !== '(') {
                continue;
            }

            $previous = $tokens[$index - 1] ?? null;

            if (\is_array($previous) && \in_array($previous[0], [
                T_FUNCTION,
                T_OBJECT_OPERATOR,
                T_NULLSAFE_OBJECT_OPERATOR,
                T_DOUBLE_COLON,
                T_NEW,
            ], true)) {
                continue;
            }

            $calls[] = $name;
        }

        return $calls;
    }

    /**
     * The global function a name token refers to, or null if it names something
     * other than a global function.
     *
     * @param  array{0: int, 1: string, 2: int}  $token
     */
    private function globalFunctionName(array $token): ?string
    {
        if ($token[0] === T_STRING) {
            return strtolower($token[1]);
        }

        if ($token[0] === T_NAME_FULLY_QUALIFIED) {
            $name = ltrim($token[1], '\\');

            return str_contains($name, '\\') ? null : strtolower($name);
        }

        return null;
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
