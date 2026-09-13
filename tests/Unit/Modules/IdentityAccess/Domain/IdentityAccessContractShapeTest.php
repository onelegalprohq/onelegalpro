<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\IdentityAccess\Domain;

use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Model\AggregateRoot;
use App\Modules\IdentityAccess\Domain\Aggregates\Principal;
use App\Modules\IdentityAccess\Domain\ValueObjects\ActorCategory;
use App\Modules\IdentityAccess\Domain\ValueObjects\ActorReference;
use App\Modules\IdentityAccess\Domain\ValueObjects\IdentityRealm;
use App\Modules\IdentityAccess\Domain\ValueObjects\PrincipalId;
use PHPUnit\Framework\TestCase;

/** IA-001 structural boundary and exact source-inventory guard. */
final class IdentityAccessContractShapeTest extends TestCase
{
    /** @var array<string, class-string> */
    private const APPROVED_SOURCE_FILES = [
        'IdentityAccess/Domain/Aggregates/Principal.php' => Principal::class,
        'IdentityAccess/Domain/ValueObjects/ActorCategory.php' => ActorCategory::class,
        'IdentityAccess/Domain/ValueObjects/ActorReference.php' => ActorReference::class,
        'IdentityAccess/Domain/ValueObjects/IdentityRealm.php' => IdentityRealm::class,
        'IdentityAccess/Domain/ValueObjects/PrincipalId.php' => PrincipalId::class,
    ];

    public function test_module_source_inventory_is_exact_and_readme_exists(): void
    {
        $approved = array_keys(self::APPROVED_SOURCE_FILES);
        sort($approved);

        $this->assertSame($approved, array_keys($this->moduleSourceFiles()));
        $this->assertFileExists($this->modulesRoot().'/IdentityAccess/README.md');
    }

    public function test_all_types_are_final_and_live_at_their_approved_paths(): void
    {
        foreach (self::APPROVED_SOURCE_FILES as $relativePath => $class) {
            $reflection = new \ReflectionClass($class);

            $this->assertTrue($reflection->isFinal(), $class.' must be final.');
            $this->assertSame(
                $this->modulesRoot().'/'.$relativePath,
                $reflection->getFileName(),
            );
        }

        $this->assertTrue((new \ReflectionClass(PrincipalId::class))->isReadOnly());
        $this->assertTrue((new \ReflectionClass(ActorReference::class))->isReadOnly());
        $this->assertTrue((new \ReflectionClass(IdentityRealm::class))->isReadOnly());
        $this->assertTrue(is_subclass_of(PrincipalId::class, BusinessIdentifier::class));
        $this->assertTrue(is_subclass_of(Principal::class, AggregateRoot::class));
    }

    public function test_public_api_is_the_approved_minimum(): void
    {
        $this->assertSame(['actorReference', 'forAuthenticatedHuman', 'realm'], $this->declaredPublicMethods(Principal::class));
        $this->assertSame(['ai', 'category', 'equals', 'human', 'identifier', 'service', 'system'], $this->declaredPublicMethods(ActorReference::class));
        $this->assertSame(['equals', 'firmId', 'forFirm', 'isFirmRealm', 'platformOperator'], $this->declaredPublicMethods(IdentityRealm::class));
        $this->assertSame([], $this->declaredPublicMethods(PrincipalId::class));
        $this->assertSame(['cases'], $this->declaredPublicMethods(ActorCategory::class));
    }

    public function test_source_is_framework_and_lifecycle_free(): void
    {
        foreach ($this->moduleSourceFiles() as $relativePath => $source) {
            $this->assertMatchesRegularExpression('/^<\?php\s+declare\(strict_types=1\);/', $source);

            foreach (['Illuminate\\', 'Laravel\\', 'Eloquent', 'Credential', 'Session', 'Membership', 'Authorization', 'Http\\', 'Carbon\\'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $this->withoutComments($source), $relativePath.' exceeds IA-001 scope.');
            }
        }
    }

    /** @return array<string, string> */
    private function moduleSourceFiles(): array
    {
        $root = $this->modulesRoot();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/IdentityAccess'));
        $files = [];

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($root) + 1);
            $contents = file_get_contents($file->getPathname());
            $this->assertIsString($contents);
            $files[$relativePath] = $contents;
        }

        ksort($files);

        return $files;
    }

    /** @param class-string $class @return list<string> */
    private function declaredPublicMethods(string $class): array
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            array_filter(
                (new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC),
                static fn (\ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class,
            ),
        );
        sort($methods);

        return $methods;
    }

    private function modulesRoot(): string
    {
        return dirname(__DIR__, 5).'/app/Modules';
    }

    private function withoutComments(string $source): string
    {
        $tokens = token_get_all($source);
        $withoutComments = '';

        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $withoutComments .= is_array($token) ? $token[1] : $token;
        }

        return $withoutComments;
    }
}
