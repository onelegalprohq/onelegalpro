<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Tenancy;

use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Tenancy\FirmContext;
use PHPUnit\Framework\TestCase;

/**
 * PF-080 — the framework-independent FirmContext carrier.
 *
 * This is a pure unit test. Its concrete identifier leaves are test fixtures
 * only and carry no business meaning.
 */
final class FirmContextTest extends TestCase
{
    private const FIRM_ID = '019fa14f-813e-702f-aa24-5b85bd74d75f';

    private const ACTOR_ID = '019fa154-ef35-73b7-99c9-7b422ec3172b';

    private const CORRELATION_ID = '019fa15b-252c-7216-8f16-1af932c30b4e';

    public function test_it_is_final_and_readonly(): void
    {
        $reflection = new \ReflectionClass(FirmContext::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
    }

    public function test_it_lives_at_the_approved_path_and_namespace(): void
    {
        $reflection = new \ReflectionClass(FirmContext::class);

        $this->assertSame('App\\Foundation\\Tenancy', $reflection->getNamespaceName());
        $this->assertSame(
            realpath(__DIR__.'/../../../../app/Foundation/Tenancy/FirmContext.php'),
            $reflection->getFileName(),
        );
    }

    public function test_it_declares_exactly_three_private_readonly_instance_properties(): void
    {
        $properties = (new \ReflectionClass(FirmContext::class))->getProperties();

        $this->assertCount(3, $properties);
        $this->assertSame(['firmId', 'actorId', 'correlationId'], array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            $properties,
        ));

        foreach ($properties as $property) {
            $this->assertTrue($property->isPrivate());
            $this->assertTrue($property->isReadOnly());
            $this->assertFalse($property->isStatic());
        }
    }

    public function test_constructor_contract_is_exact(): void
    {
        $constructor = (new \ReflectionClass(FirmContext::class))->getConstructor();

        $this->assertInstanceOf(\ReflectionMethod::class, $constructor);
        $this->assertTrue($constructor->isPublic());
        $this->assertSame(3, $constructor->getNumberOfParameters());
        $this->assertSame(3, $constructor->getNumberOfRequiredParameters());

        $expected = [
            'firmId' => BusinessIdentifier::class,
            'actorId' => BusinessIdentifier::class,
            'correlationId' => UuidV7::class,
        ];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            $this->assertArrayHasKey($parameter->getName(), $expected);
            $this->assertInstanceOf(\ReflectionNamedType::class, $type);
            $this->assertSame($expected[$parameter->getName()], $type->getName());
            $this->assertFalse($type->allowsNull());
            $this->assertFalse($parameter->isDefaultValueAvailable());
            $this->assertFalse($parameter->isVariadic());
            $this->assertFalse($parameter->isPassedByReference());
        }
    }

    public function test_named_argument_construction_preserves_the_exact_instances(): void
    {
        $firmId = TestFirmIdentifier::fromString(self::FIRM_ID);
        $actorId = TestActorIdentifier::fromString(self::ACTOR_ID);
        $correlationId = UuidV7::fromString(self::CORRELATION_ID);

        $context = new FirmContext(
            firmId: $firmId,
            actorId: $actorId,
            correlationId: $correlationId,
        );

        $this->assertSame($firmId, $context->firmId());
        $this->assertSame($actorId, $context->actorId());
        $this->assertSame($correlationId, $context->correlationId());
    }

    public function test_it_declares_exactly_the_three_approved_public_accessors(): void
    {
        $methods = array_values(array_filter(
            (new \ReflectionClass(FirmContext::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
            static fn (\ReflectionMethod $method): bool => ! $method->isConstructor(),
        ));

        $this->assertSame(['firmId', 'actorId', 'correlationId'], array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $methods,
        ));

        $expected = [
            'firmId' => BusinessIdentifier::class,
            'actorId' => BusinessIdentifier::class,
            'correlationId' => UuidV7::class,
        ];

        foreach ($methods as $method) {
            $returnType = $method->getReturnType();

            $this->assertFalse($method->isStatic());
            $this->assertSame(0, $method->getNumberOfParameters());
            $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
            $this->assertSame($expected[$method->getName()], $returnType->getName());
            $this->assertFalse($returnType->allowsNull());
        }
    }

    public function test_it_declares_no_protected_magic_or_additional_api(): void
    {
        $reflection = new \ReflectionClass(FirmContext::class);

        $this->assertSame([], $reflection->getMethods(\ReflectionMethod::IS_PROTECTED));
        $this->assertSame(
            ['__construct', 'firmId', 'actorId', 'correlationId'],
            array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(),
            ),
        );
    }

    public function test_production_foundation_contains_no_concrete_business_identifier(): void
    {
        foreach (get_declared_classes() as $class) {
            if (! str_starts_with($class, 'App\\Foundation\\')) {
                continue;
            }

            $this->assertFalse(is_subclass_of($class, BusinessIdentifier::class), $class);
        }
    }

    public function test_it_has_no_framework_or_vendor_dependency(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../app/Foundation/Tenancy/FirmContext.php');

        $this->assertIsString($source);
        $this->assertSame(2, substr_count($source, "\nuse "));
        $this->assertStringNotContainsString('Illuminate\\', $source);
        $this->assertStringNotContainsString('Laravel\\', $source);
        $this->assertStringNotContainsString('Symfony\\', $source);
    }

    public function test_this_is_a_pure_phpunit_test(): void
    {
        $reflection = new \ReflectionClass(self::class);

        $this->assertSame(TestCase::class, $reflection->getParentClass()?->getName());
        $this->assertFalse($reflection->isSubclassOf(\Illuminate\Foundation\Testing\TestCase::class));
    }

    public function test_no_business_module_was_introduced(): void
    {
        $appDirectories = glob(__DIR__.'/../../../../app/*', GLOB_ONLYDIR);

        $this->assertIsArray($appDirectories);
        $this->assertSame(['Foundation', 'Http', 'Models', 'Providers'], array_map(
            static fn (string $directory): string => basename($directory),
            $appDirectories,
        ));
    }
}

final readonly class TestFirmIdentifier extends BusinessIdentifier {}

final readonly class TestActorIdentifier extends BusinessIdentifier {}
