<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Domain\Identity;

use App\Foundation\Domain\Exception\DomainException;
use App\Foundation\Domain\Exception\FoundationException;
use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Domain\Model\ValueObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PF-048 — the published `UuidV7` value object.
 *
 * The reflection assertions pin the contract's exact shape, so a later
 * accidental widening — an extra method, `\Stringable`, a public constructor,
 * a relaxed return type — fails here rather than silently reaching every
 * consumer.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly and boots no
 * Laravel application.
 */
final class UuidV7Test extends TestCase
{
    /**
     * A canonical version 7 UUID, used wherever a valid value is needed.
     *
     * Version nibble `7` at position 14, RFC variant nibble `a` at position 19.
     */
    private const VALID = '019fa14f-813e-702f-aa24-5b85bd74d75f';

    private const OTHER_VALID = '019fa154-ef35-73b7-99c9-7b422ec3172b';

    // ---------------------------------------------------------------- shape

    public function test_uuid_v7_is_a_final_class(): void
    {
        $this->assertTrue((new \ReflectionClass(UuidV7::class))->isFinal());
    }

    public function test_uuid_v7_is_readonly(): void
    {
        $this->assertTrue((new \ReflectionClass(UuidV7::class))->isReadOnly());
    }

    public function test_uuid_v7_implements_the_value_object_contract(): void
    {
        $this->assertContains(ValueObject::class, class_implements(UuidV7::class));
    }

    public function test_uuid_v7_implements_nothing_beyond_value_object(): void
    {
        $this->assertSame(
            [ValueObject::class],
            array_values(class_implements(UuidV7::class)),
        );
    }

    public function test_uuid_v7_is_neither_stringable_nor_json_serializable(): void
    {
        $implemented = class_implements(UuidV7::class);

        $this->assertNotContains(\Stringable::class, $implemented);
        $this->assertNotContains(\JsonSerializable::class, $implemented);
    }

    public function test_the_constructor_is_private(): void
    {
        $constructor = (new \ReflectionClass(UuidV7::class))->getConstructor();

        $this->assertInstanceOf(\ReflectionMethod::class, $constructor);
        $this->assertTrue($constructor->isPrivate());
    }

    public function test_uuid_v7_declares_exactly_the_three_approved_public_methods(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass(UuidV7::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        sort($methods);

        $this->assertSame(['equals', 'fromString', 'toString'], $methods);
    }

    /**
     * The deferred and prohibited API, named individually.
     *
     * Listing each one means a future addition fails with the member's own
     * name rather than an opaque count mismatch. `__toString` and the
     * serialization members are prohibited; the rest are deferred to a later
     * approved story, where adding them is additive.
     */
    #[DataProvider('prohibitedMembers')]
    public function test_uuid_v7_declares_no_prohibited_or_deferred_member(string $member): void
    {
        $this->assertFalse(
            method_exists(UuidV7::class, $member),
            $member.'() must not exist on the PF-048 contract.',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function prohibitedMembers(): iterable
    {
        foreach ([
            '__toString', 'jsonSerialize', 'toArray', 'toPrimitives', 'fromPrimitives',
            'toBytes', 'fromBytes', 'bytes', 'timestamp', 'getDateTime', 'compareTo',
            'isBefore', 'isAfter', 'nil', 'max', 'hash', 'copy', 'notEquals', 'generate',
        ] as $member) {
            yield $member => [$member];
        }
    }

    public function test_from_string_is_public_static_and_takes_one_required_string(): void
    {
        $method = new \ReflectionMethod(UuidV7::class, 'fromString');

        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame(1, $method->getNumberOfRequiredParameters());

        $parameter = $method->getParameters()[0];

        $this->assertFalse($parameter->isOptional());
        $this->assertFalse($parameter->isVariadic());
        $this->assertFalse($parameter->isPassedByReference());
        $this->assertFalse($parameter->isDefaultValueAvailable());

        $type = $parameter->getType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame('string', $type->getName());
        $this->assertFalse($type->allowsNull());
    }

    public function test_from_string_returns_a_non_nullable_uuid_v7(): void
    {
        $method = new \ReflectionMethod(UuidV7::class, 'fromString');
        $returnType = $method->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(
            UuidV7::class,
            $returnType->getName() === 'self'
                ? $method->getDeclaringClass()->getName()
                : $returnType->getName(),
        );
        $this->assertFalse($returnType->allowsNull());
    }

    public function test_to_string_is_public_takes_no_parameter_and_returns_a_non_nullable_string(): void
    {
        $method = new \ReflectionMethod(UuidV7::class, 'toString');

        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isStatic());
        $this->assertSame(0, $method->getNumberOfParameters());

        $returnType = $method->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('string', $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }

    public function test_equals_matches_the_published_value_object_signature(): void
    {
        $method = new \ReflectionMethod(UuidV7::class, 'equals');

        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isStatic());
        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame(1, $method->getNumberOfRequiredParameters());

        $type = $method->getParameters()[0]->getType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(ValueObject::class, $type->getName());
        $this->assertFalse($type->allowsNull());

        $returnType = $method->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('bool', $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }

    public function test_uuid_v7_declares_no_public_property_or_public_constant(): void
    {
        $reflection = new \ReflectionClass(UuidV7::class);

        $this->assertSame([], $reflection->getProperties(\ReflectionProperty::IS_PUBLIC));
        $this->assertSame([], $reflection->getConstants(\ReflectionClassConstant::IS_PUBLIC));
    }

    // ------------------------------------------------- parsing and rendering

    public function test_a_canonical_value_round_trips_unchanged(): void
    {
        $this->assertSame(self::VALID, UuidV7::fromString(self::VALID)->toString());
    }

    public function test_the_rendered_form_is_always_canonical(): void
    {
        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
            UuidV7::fromString(self::VALID)->toString(),
        );
    }

    public function test_the_version_nibble_is_seven(): void
    {
        $this->assertSame('7', UuidV7::fromString(self::VALID)->toString()[14]);
    }

    public function test_the_variant_nibble_is_an_rfc_variant(): void
    {
        $this->assertContains(UuidV7::fromString(self::VALID)->toString()[19], ['8', '9', 'a', 'b']);
    }

    /**
     * Every RFC variant nibble is accepted, not merely the one in the fixture.
     */
    #[DataProvider('rfcVariantNibbles')]
    public function test_every_rfc_variant_nibble_is_accepted(string $nibble): void
    {
        $uuid = '019fa14f-813e-702f-'.$nibble.'a24-5b85bd74d75f';

        $this->assertSame($uuid, UuidV7::fromString($uuid)->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rfcVariantNibbles(): iterable
    {
        foreach (['8', '9', 'a', 'b'] as $nibble) {
            yield $nibble => [$nibble];
        }
    }

    // ------------------------------------------------- uppercase normalization

    public function test_canonical_uppercase_input_is_accepted_and_normalized_to_lowercase(): void
    {
        $this->assertSame(
            self::VALID,
            UuidV7::fromString(strtoupper(self::VALID))->toString(),
        );
    }

    /**
     * Mixed case is accepted too, and this is deliberate rather than incidental.
     *
     * RFC 9562 §4 requires implementations to accept both cases on input while
     * emitting lowercase. Case-insensitive hexadecimal is the whole of that
     * rule; accepting `AAAA` and `aaaa` while rejecting `AaAa` would be an
     * arbitrary distinction the RFC does not draw.
     */
    public function test_mixed_case_input_is_accepted_and_normalized_to_lowercase(): void
    {
        $this->assertSame(
            self::VALID,
            UuidV7::fromString('019FA14f-813E-702f-AA24-5b85BD74d75F')->toString(),
        );
    }

    public function test_normalization_happens_on_creation_so_case_never_affects_equality(): void
    {
        $lower = UuidV7::fromString(self::VALID);
        $upper = UuidV7::fromString(strtoupper(self::VALID));

        $this->assertTrue($lower->equals($upper));
        $this->assertTrue($upper->equals($lower));
    }

    // ------------------------------------------------------------- rejection

    #[DataProvider('rejectedText')]
    public function test_non_canonical_version_7_text_is_rejected(string $uuid): void
    {
        $this->expectException(InvalidArgument::class);

        UuidV7::fromString($uuid);
    }

    /**
     * The complete rejection matrix.
     *
     * @return iterable<string, array{string}>
     */
    public static function rejectedText(): iterable
    {
        $cases = [
            'empty string' => '',
            'whitespace only' => '   ',
            'leading whitespace' => ' 019fa14f-813e-702f-aa24-5b85bd74d75f',
            'trailing whitespace' => '019fa14f-813e-702f-aa24-5b85bd74d75f ',
            'trailing newline' => "019fa14f-813e-702f-aa24-5b85bd74d75f\n",
            'internal whitespace' => '019fa14f-813e-702f-aa24 5b85bd74d75f',
            'arbitrary text' => 'not-a-uuid',
            'one character short' => '019fa14f-813e-702f-aa24-5b85bd74d75',
            'one character long' => '019fa14f-813e-702f-aa24-5b85bd74d75ff',
            'non-hexadecimal character' => '019fa14f-813e-702f-aa24-5b85bd74d75g',
            'hyphens in the wrong positions' => '019fa14f8-13e-702f-aa24-5b85bd74d75f',
            'no hyphens' => '019fa14f813e702faa245b85bd74d75f',
            'brace wrapped' => '{019fa14f-813e-702f-aa24-5b85bd74d75f}',
            'urn prefixed' => 'urn:uuid:019fa14f-813e-702f-aa24-5b85bd74d75f',
            'nil uuid' => '00000000-0000-0000-0000-000000000000',
            'max uuid' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            'version 1' => '019fa14f-813e-102f-aa24-5b85bd74d75f',
            'version 2' => '019fa14f-813e-202f-aa24-5b85bd74d75f',
            'version 3' => '019fa14f-813e-302f-aa24-5b85bd74d75f',
            'version 4' => '019fa14f-813e-402f-aa24-5b85bd74d75f',
            'version 5' => '019fa14f-813e-502f-aa24-5b85bd74d75f',
            'version 6' => '019fa14f-813e-602f-aa24-5b85bd74d75f',
            'version 8' => '019fa14f-813e-802f-aa24-5b85bd74d75f',
            'version 0' => '019fa14f-813e-002f-aa24-5b85bd74d75f',
            'version f' => '019fa14f-813e-f02f-aa24-5b85bd74d75f',
        ];

        // Every non-RFC variant nibble: 0-7 is the NCS legacy range, c and d
        // are Microsoft, e and f are reserved. Only 8, 9, a and b are RFC.
        foreach (['0', '1', '2', '3', '4', '5', '6', '7', 'c', 'd', 'e', 'f'] as $nibble) {
            $cases['variant nibble '.$nibble] = '019fa14f-813e-702f-'.$nibble.'a24-5b85bd74d75f';
        }

        foreach ($cases as $description => $uuid) {
            yield $description => [$uuid];
        }
    }

    public function test_a_rejection_is_catchable_through_the_foundation_taxonomy(): void
    {
        try {
            UuidV7::fromString('not-a-uuid');
        } catch (InvalidArgument $rejected) {
            $this->assertInstanceOf(DomainException::class, $rejected);
            $this->assertInstanceOf(FoundationException::class, $rejected);
            $this->assertInstanceOf(\RuntimeException::class, $rejected);

            return;
        }

        $this->fail('fromString() must reject malformed text.');
    }

    /**
     * The rejected text never reaches the exception message.
     *
     * Input arrives from outside the domain and may be attacker-controlled, so
     * echoing it into a developer-facing diagnostic risks carrying it into a
     * log. A distinctive marker makes the assertion unambiguous.
     */
    #[DataProvider('rejectedInputMarkers')]
    public function test_the_rejected_input_never_appears_in_the_exception_message(string $uuid, string $marker): void
    {
        try {
            UuidV7::fromString($uuid);
        } catch (InvalidArgument $rejected) {
            $this->assertStringNotContainsString($marker, $rejected->getMessage());

            return;
        }

        $this->fail('fromString() must reject this input.');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedInputMarkers(): iterable
    {
        yield 'distinctive text' => ['SENSITIVE-MATTER-REFERENCE', 'SENSITIVE-MATTER-REFERENCE'];
        yield 'wrong version' => ['019fa14f-813e-402f-aa24-5b85bd74d75f', '019fa14f-813e-402f-aa24-5b85bd74d75f'];
        yield 'wrong variant' => ['019fa14f-813e-702f-ca24-5b85bd74d75f', '019fa14f-813e-702f-ca24-5b85bd74d75f'];
        yield 'nil uuid' => ['00000000-0000-0000-0000-000000000000', '00000000-0000-0000-0000-000000000000'];
    }

    // -------------------------------------------------- equality and immutability

    public function test_equality_is_reflexive(): void
    {
        $uuid = UuidV7::fromString(self::VALID);

        $this->assertTrue($uuid->equals($uuid));
    }

    public function test_distinct_instances_holding_the_same_value_are_equal_but_not_identical(): void
    {
        $first = UuidV7::fromString(self::VALID);
        $second = UuidV7::fromString(self::VALID);

        $this->assertTrue($first->equals($second));
        $this->assertTrue($second->equals($first));
        $this->assertNotSame($first, $second);
    }

    public function test_different_values_are_unequal_in_both_directions(): void
    {
        $first = UuidV7::fromString(self::VALID);
        $second = UuidV7::fromString(self::OTHER_VALID);

        $this->assertFalse($first->equals($second));
        $this->assertFalse($second->equals($first));
    }

    public function test_cross_class_comparison_is_false_in_both_directions_and_does_not_throw(): void
    {
        $uuid = UuidV7::fromString(self::VALID);
        $foreign = new ForeignValue(self::VALID);

        $this->assertFalse($uuid->equals($foreign));
        $this->assertFalse($foreign->equals($uuid));
    }

    /**
     * The value cannot be reassigned even with reflection.
     *
     * The property is private, so an ordinary external write would throw for
     * encapsulation reasons alone and would prove nothing about `readonly`.
     * Reaching past the visibility check with reflection isolates the
     * immutability guarantee itself: the write still fails, because the class
     * is `readonly` and the property is already initialised.
     */
    public function test_the_value_cannot_be_reassigned_even_through_reflection(): void
    {
        $uuid = UuidV7::fromString(self::VALID);
        $property = new \ReflectionProperty(UuidV7::class, 'value');

        $this->assertTrue($property->isReadOnly());

        try {
            $property->setValue($uuid, self::OTHER_VALID);
        } catch (\Error $refused) {
            $this->assertStringContainsString('readonly', $refused->getMessage());
            $this->assertSame(self::VALID, $uuid->toString());

            return;
        }

        $this->fail('A readonly property must not be reassignable.');
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
 * A differently typed value object holding an identical string.
 *
 * It exists only to prove that `UuidV7::equals()` compares exact runtime type
 * before value, so a foreign value object carrying the same text is unequal
 * rather than incomparable. Test-only, declared here rather than in
 * `app/Foundation` so PF-048 creates no value object beyond its own.
 */
final readonly class ForeignValue implements ValueObject
{
    public function __construct(private string $value) {}

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }
}
