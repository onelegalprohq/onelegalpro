<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Domain\Model;

use App\Foundation\Domain\Model\ValueObject;
use PHPUnit\Framework\TestCase;

/**
 * PF-042 — the published `ValueObject` contract.
 *
 * The reflection assertions pin the contract's exact shape, so a later
 * accidental widening — an extra method, a nullable parameter, a relaxed
 * return type, an inherited interface — fails here rather than silently
 * reaching every consumer.
 *
 * The behavioural assertions run against the two reference implementations
 * declared at the bottom of this file. **They demonstrate the documented
 * reference semantics; they do not prove that every implementation obeys
 * them.** A PHP interface can constrain a signature, never the comparison
 * algorithm behind it, and this test makes no such claim. Each concrete value
 * object's own story remains responsible for proving its own equality.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly and boots no
 * Laravel application.
 */
final class ValueObjectTest extends TestCase
{
    public function test_value_object_is_an_interface(): void
    {
        $this->assertTrue((new \ReflectionClass(ValueObject::class))->isInterface());
    }

    public function test_value_object_extends_no_interface(): void
    {
        $this->assertSame([], (new \ReflectionClass(ValueObject::class))->getInterfaceNames());
    }

    public function test_value_object_declares_exactly_one_method(): void
    {
        $this->assertCount(1, (new \ReflectionClass(ValueObject::class))->getMethods());
    }

    public function test_the_declared_method_is_named_equals(): void
    {
        $methods = (new \ReflectionClass(ValueObject::class))->getMethods();

        $this->assertSame('equals', $methods[0]->getName());
    }

    public function test_equals_is_public_and_not_static(): void
    {
        $method = new \ReflectionMethod(ValueObject::class, 'equals');

        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isStatic());
    }

    public function test_equals_takes_exactly_one_required_parameter(): void
    {
        $method = new \ReflectionMethod(ValueObject::class, 'equals');

        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame(1, $method->getNumberOfRequiredParameters());
    }

    public function test_the_parameter_is_required_by_value_and_not_variadic(): void
    {
        $parameter = (new \ReflectionMethod(ValueObject::class, 'equals'))->getParameters()[0];

        $this->assertFalse($parameter->isOptional());
        $this->assertFalse($parameter->isVariadic());
        $this->assertFalse($parameter->isPassedByReference());
        $this->assertFalse($parameter->isDefaultValueAvailable());
    }

    public function test_the_parameter_type_is_exactly_a_non_nullable_value_object(): void
    {
        $type = (new \ReflectionMethod(ValueObject::class, 'equals'))->getParameters()[0]->getType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(ValueObject::class, $type->getName());
        $this->assertFalse($type->allowsNull());
    }

    public function test_equals_returns_a_non_nullable_bool(): void
    {
        $returnType = (new \ReflectionMethod(ValueObject::class, 'equals'))->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('bool', $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }

    public function test_value_object_declares_no_constants(): void
    {
        $this->assertSame([], (new \ReflectionClass(ValueObject::class))->getConstants());
    }

    public function test_value_object_declares_no_properties(): void
    {
        $this->assertSame([], (new \ReflectionClass(ValueObject::class))->getProperties());
    }

    public function test_value_object_is_neither_stringable_nor_json_serializable(): void
    {
        $inherited = class_implements(ValueObject::class);

        $this->assertNotContains(\Stringable::class, $inherited);
        $this->assertNotContains(\JsonSerializable::class, $inherited);
    }

    /**
     * The property this contract exists to preserve.
     *
     * PHP's `readonly` inheritance is viral and bidirectionally exclusive: a
     * non-readonly class cannot extend a readonly class, and a readonly class
     * cannot extend a non-readonly one — even when the parent declares no
     * property at all. An abstract base would therefore have forced every
     * value object on the platform to be readonly, or forbidden every one of
     * them from being readonly, permanently. An interface forces neither, and
     * these two implementations existing side by side is the proof.
     */
    public function test_both_readonly_and_non_readonly_classes_may_implement_the_contract(): void
    {
        $readonly = new \ReflectionClass(ReadonlyExampleValue::class);
        $nonReadonly = new \ReflectionClass(NonReadonlyExampleValue::class);

        $this->assertContains(ValueObject::class, class_implements(ReadonlyExampleValue::class));
        $this->assertContains(ValueObject::class, class_implements(NonReadonlyExampleValue::class));

        $this->assertTrue($readonly->isReadOnly());
        $this->assertFalse($nonReadonly->isReadOnly());
    }

    public function test_equality_is_reflexive(): void
    {
        $value = new ReadonlyExampleValue('matter-reference');

        $this->assertTrue($value->equals($value));
    }

    public function test_distinct_instances_holding_the_same_value_are_equal_but_not_identical(): void
    {
        $first = new ReadonlyExampleValue('matter-reference');
        $second = new ReadonlyExampleValue('matter-reference');

        $this->assertTrue($first->equals($second));
        $this->assertTrue($second->equals($first));
        $this->assertNotSame($first, $second);
    }

    public function test_different_values_are_unequal(): void
    {
        $first = new ReadonlyExampleValue('matter-reference');
        $second = new ReadonlyExampleValue('another-reference');

        $this->assertFalse($first->equals($second));
        $this->assertFalse($second->equals($first));
    }

    /**
     * Differently typed value objects are unequal, not incomparable.
     *
     * Both directions are asserted, because an asymmetric result is exactly
     * the defect this rule prevents. Both fixtures are `final`, so
     * `instanceof self` is exact-runtime-class-correct **for them**; that is a
     * property of a leaf class, not a technique that generalises. An
     * inheritable base implementing `equals()` for its subclasses must compare
     * runtime classes explicitly instead.
     */
    public function test_cross_class_comparison_is_false_in_both_directions_and_does_not_throw(): void
    {
        $readonly = new ReadonlyExampleValue('shared-value');
        $nonReadonly = new NonReadonlyExampleValue('shared-value');

        $this->assertFalse($readonly->equals($nonReadonly));
        $this->assertFalse($nonReadonly->equals($readonly));
    }

    public function test_equality_is_strict_and_never_coercive(): void
    {
        $string = new ReadonlyExampleValue('1');
        $integer = new ReadonlyExampleValue(1);

        $this->assertFalse($string->equals($integer));
        $this->assertFalse($integer->equals($string));
    }

    public function test_writing_to_a_readonly_implementations_property_throws(): void
    {
        $value = new ReadonlyExampleValue('matter-reference');

        $this->expectException(\Error::class);

        $value->value = 'reassigned';
    }

    public function test_this_test_runs_without_booting_laravel(): void
    {
        $this->assertInstanceOf(TestCase::class, $this);
        $this->assertFalse(is_subclass_of($this, 'Illuminate\Foundation\Testing\TestCase'));
        $this->assertFalse(is_subclass_of($this, 'Tests\TestCase'));
        $this->assertFalse(property_exists($this, 'app'));
    }
}

/**
 * A reference implementation in the recommended `final readonly class` form.
 *
 * Test-only, declared here rather than in `app/Foundation` so PF-042 creates
 * no value object of its own. It is deliberately minimal: a real value object
 * validates its input on every approved creation path and throws the PF-049
 * exceptions when that input is unacceptable.
 */
final readonly class ReadonlyExampleValue implements ValueObject
{
    public function __construct(public string|int $value) {}

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }
}

/**
 * The same reference implementation without `readonly`.
 *
 * It exists only to prove the contract imposes no `readonly` requirement — not
 * as a form to copy. Its property is private rather than public, so the type
 * is still immutable in practice; without `readonly`, that is a convention the
 * language does not enforce, which is exactly why the recommended form above
 * is preferred wherever a concrete type's requirements permit it.
 */
final class NonReadonlyExampleValue implements ValueObject
{
    public function __construct(private string|int $value) {}

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }
}
