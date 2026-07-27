<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Domain\Identity;

use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Domain\Identity\UuidV7Generator;
use PHPUnit\Framework\TestCase;

/**
 * PF-048 — the published `UuidV7Generator` contract.
 *
 * Reflection only: this file pins the interface's exact shape so a later
 * accidental widening — a second method, a parameter on `generate()`, a
 * loosened return type — fails here rather than reaching every consumer. The
 * behaviour of the one production implementation is proved in
 * `SystemUuidV7GeneratorTest`.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly and boots no
 * Laravel application.
 */
final class UuidV7GeneratorTest extends TestCase
{
    public function test_uuid_v7_generator_is_an_interface(): void
    {
        $this->assertTrue((new \ReflectionClass(UuidV7Generator::class))->isInterface());
    }

    public function test_uuid_v7_generator_extends_no_interface(): void
    {
        $this->assertSame([], (new \ReflectionClass(UuidV7Generator::class))->getInterfaceNames());
    }

    public function test_uuid_v7_generator_declares_exactly_one_method(): void
    {
        $this->assertCount(1, (new \ReflectionClass(UuidV7Generator::class))->getMethods());
    }

    public function test_the_declared_method_is_named_generate(): void
    {
        $methods = (new \ReflectionClass(UuidV7Generator::class))->getMethods();

        $this->assertSame('generate', $methods[0]->getName());
    }

    public function test_generate_is_public_and_not_static(): void
    {
        $method = new \ReflectionMethod(UuidV7Generator::class, 'generate');

        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isStatic());
    }

    public function test_generate_takes_no_parameter(): void
    {
        $this->assertSame(0, (new \ReflectionMethod(UuidV7Generator::class, 'generate'))->getNumberOfParameters());
    }

    public function test_generate_returns_a_non_nullable_uuid_v7(): void
    {
        $returnType = (new \ReflectionMethod(UuidV7Generator::class, 'generate'))->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(UuidV7::class, $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }

    public function test_uuid_v7_generator_declares_no_constant_or_property(): void
    {
        $reflection = new \ReflectionClass(UuidV7Generator::class);

        $this->assertSame([], $reflection->getConstants());
        $this->assertSame([], $reflection->getProperties());
    }

    public function test_this_test_runs_without_booting_laravel(): void
    {
        $this->assertInstanceOf(TestCase::class, $this);
        $this->assertFalse(is_subclass_of($this, 'Illuminate\Foundation\Testing\TestCase'));
        $this->assertFalse(is_subclass_of($this, 'Tests\TestCase'));
        $this->assertFalse(property_exists($this, 'app'));
    }
}
