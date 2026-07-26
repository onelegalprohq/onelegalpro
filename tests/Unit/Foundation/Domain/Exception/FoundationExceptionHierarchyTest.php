<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Domain\Exception;

use App\Foundation\Domain\Exception\DomainException;
use App\Foundation\Domain\Exception\FoundationException;
use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Exception\InvariantViolation;
use PHPUnit\Framework\TestCase;

/**
 * PF-049 — the Foundation exception taxonomy.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly and boots no
 * Laravel application.
 */
final class FoundationExceptionHierarchyTest extends TestCase
{
    /** @var list<class-string<DomainException>> */
    private const CONCRETE_TYPES = [
        InvariantViolation::class,
        InvalidArgument::class,
    ];

    public function test_foundation_exception_is_an_interface_extending_throwable(): void
    {
        $reflection = new \ReflectionClass(FoundationException::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertContains(\Throwable::class, class_implements(FoundationException::class));
    }

    public function test_foundation_exception_declares_no_members_of_its_own(): void
    {
        $reflection = new \ReflectionClass(FoundationException::class);

        $this->assertSame([], $reflection->getConstants());

        $declaredHere = array_filter(
            $reflection->getMethods(),
            static fn (\ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === FoundationException::class,
        );

        $this->assertSame([], array_values($declaredHere));
    }

    public function test_domain_exception_is_abstract(): void
    {
        $this->assertTrue((new \ReflectionClass(DomainException::class))->isAbstract());
    }

    public function test_domain_exception_extends_the_global_runtime_exception(): void
    {
        $this->assertSame(\RuntimeException::class, get_parent_class(DomainException::class));
    }

    public function test_domain_exception_implements_foundation_exception(): void
    {
        $this->assertContains(FoundationException::class, class_implements(DomainException::class));
    }

    public function test_concrete_types_are_final(): void
    {
        foreach (self::CONCRETE_TYPES as $type) {
            $this->assertTrue((new \ReflectionClass($type))->isFinal(), $type.' must be final.');
        }
    }

    public function test_concrete_types_extend_domain_exception(): void
    {
        foreach (self::CONCRETE_TYPES as $type) {
            $this->assertSame(DomainException::class, get_parent_class($type), $type.' must extend DomainException.');
        }
    }

    public function test_concrete_types_are_catchable_through_foundation_exception(): void
    {
        foreach (self::CONCRETE_TYPES as $type) {
            $caught = null;

            try {
                throw new $type('domain failure');
            } catch (FoundationException $exception) {
                $caught = $exception;
            }

            $this->assertInstanceOf($type, $caught);
        }
    }

    public function test_constructor_messages_are_preserved_exactly(): void
    {
        $message = 'Matter number must not be blank.';

        foreach (self::CONCRETE_TYPES as $type) {
            $this->assertSame($message, (new $type($message))->getMessage());
        }
    }

    public function test_previous_exception_chaining_is_preserved(): void
    {
        $previous = new \RuntimeException('original cause');

        foreach (self::CONCRETE_TYPES as $type) {
            $exception = new $type('wrapping failure', 0, $previous);

            $this->assertSame($previous, $exception->getPrevious());
        }
    }

    public function test_concrete_types_do_not_extend_the_global_domain_exception(): void
    {
        foreach (self::CONCRETE_TYPES as $type) {
            $this->assertNotInstanceOf(\DomainException::class, new $type('domain failure'));
        }
    }

    public function test_no_type_implements_json_serializable(): void
    {
        $types = array_merge([FoundationException::class, DomainException::class], self::CONCRETE_TYPES);

        foreach ($types as $type) {
            $this->assertNotContains(
                \JsonSerializable::class,
                class_implements($type),
                $type.' must not expose a serialization API.',
            );
        }
    }

    public function test_this_test_runs_without_booting_laravel(): void
    {
        $this->assertInstanceOf(TestCase::class, $this);
        $this->assertFalse(is_subclass_of($this, 'Illuminate\Foundation\Testing\TestCase'));
        $this->assertFalse(is_subclass_of($this, 'Tests\TestCase'));
        $this->assertFalse(property_exists($this, 'app'));
    }
}
