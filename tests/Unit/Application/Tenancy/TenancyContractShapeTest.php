<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tenancy;

use App\Application\Tenancy\ActiveFirmMembershipAssertion;
use App\Application\Tenancy\AuthenticatedActor;
use App\Application\Tenancy\CandidateFirmDirectory;
use App\Application\Tenancy\CandidateFirmReference;
use App\Application\Tenancy\FirmMembershipVerifier;
use App\Application\Tenancy\FirmResolutionDenied;
use App\Application\Tenancy\VerifiedFirmContextResolver;
use PHPUnit\Framework\TestCase;

final class TenancyContractShapeTest extends TestCase
{
    public function test_the_resolver_has_only_its_two_approved_dependencies_and_one_public_operation(): void
    {
        $reflection = new \ReflectionClass(VerifiedFirmContextResolver::class);
        $constructor = $reflection->getConstructor();

        self::assertTrue($reflection->isFinal());
        self::assertCount(2, $constructor?->getParameters() ?? []);
        self::assertSame(FirmMembershipVerifier::class, $constructor?->getParameters()[0]->getType()?->getName());
        self::assertSame('App\\Foundation\\Domain\\Identity\\UuidV7Generator', $constructor?->getParameters()[1]->getType()?->getName());
        self::assertSame(['resolve'], array_values(array_map(static fn (\ReflectionMethod $method): string => $method->getName(), array_filter($reflection->getMethods(\ReflectionMethod::IS_PUBLIC), static fn (\ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === VerifiedFirmContextResolver::class && ! $method->isConstructor()))));
        self::assertCount(2, $reflection->getProperties());
        self::assertFalse($reflection->getProperty('verifier')->isStatic());
        self::assertFalse($reflection->getProperty('correlationIds')->isStatic());
    }

    public function test_ports_have_their_exact_minimal_method_sets(): void
    {
        self::assertSame(['actorId'], $this->declaredPublicMethodNames(AuthenticatedActor::class));
        self::assertSame(['actorId', 'firmId'], $this->declaredPublicMethodNames(ActiveFirmMembershipAssertion::class));
        self::assertSame(['verifyActiveMembership'], $this->declaredPublicMethodNames(FirmMembershipVerifier::class));
        self::assertSame(['findCandidateFirmId'], $this->declaredPublicMethodNames(CandidateFirmDirectory::class));
    }

    public function test_candidate_reference_and_denial_have_the_approved_shape(): void
    {
        $candidate = new \ReflectionClass(CandidateFirmReference::class);
        $denial = new \ReflectionClass(FirmResolutionDenied::class);

        self::assertTrue($candidate->isFinal());
        self::assertTrue($candidate->isReadOnly());
        self::assertSame(['sourceKind', 'unverifiedValue'], array_map(static fn (\ReflectionProperty $property): string => $property->getName(), $candidate->getProperties()));
        self::assertTrue($denial->isFinal());
        self::assertSame(\RuntimeException::class, $denial->getParentClass()?->getName());
        self::assertTrue($denial->getConstructor()?->isPrivate());
        self::assertSame(['denied'], $this->declaredPublicMethodNames(FirmResolutionDenied::class));
    }

    public function test_no_port_has_a_production_implementation(): void
    {
        $root = dirname(__DIR__, 4).'/app';
        $source = implode("\n", array_map(static fn (string $path): string => file_get_contents($path) ?: '', $this->phpFiles($root)));

        self::assertStringNotContainsString('implements FirmMembershipVerifier', $source);
        self::assertStringNotContainsString('implements AuthenticatedActor', $source);
        self::assertStringNotContainsString('implements ActiveFirmMembershipAssertion', $source);
        self::assertStringNotContainsString('implements CandidateFirmDirectory', $source);
    }

    /** @return list<string> */
    private function declaredPublicMethodNames(string $class): array
    {
        $methods = array_filter(
            (new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC),
            static fn (\ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class,
        );

        $names = array_map(static fn (\ReflectionMethod $method): string => $method->getName(), $methods);
        sort($names);

        return $names;
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $files = [];

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
