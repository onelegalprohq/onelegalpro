<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Domain\Identity;

use App\Foundation\Domain\Exception\DomainException;
use App\Foundation\Domain\Exception\FoundationException;
use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Domain\Identity\UuidV7Generator;
use App\Foundation\Domain\Model\ValueObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PF-044 — the published `BusinessIdentifier` base.
 *
 * The reflection assertions pin the contract's exact shape, so a later
 * accidental widening — an extra public method, a relaxed constructor, a lost
 * `final` — fails here rather than silently reaching every consumer. They
 * assert what the language actually guarantees and nothing more: **no claim is
 * made here about what every potential future subclass can or cannot do.**
 * That concrete leaves must stay empty `final readonly` marker subclasses is an
 * architectural rule recorded in `app/Foundation/README.md` and enforced by
 * review, not by PHP.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly and boots no Laravel
 * application. Every fixture is declared at the bottom of this file; no
 * separate fixture file exists, and no fixture name carries business meaning.
 */
final class BusinessIdentifierTest extends TestCase
{
    /**
     * A canonical version 7 UUID, used wherever a valid value is needed.
     *
     * Version nibble `7` at position 14, RFC variant nibble `a` at position 19.
     */
    private const VALID = '019fa14f-813e-702f-aa24-5b85bd74d75f';

    private const OTHER_VALID = '019fa154-ef35-73b7-99c9-7b422ec3172b';

    // ---------------------------------------------------------------- shape

    public function test_business_identifier_is_abstract(): void
    {
        $this->assertTrue((new \ReflectionClass(BusinessIdentifier::class))->isAbstract());
    }

    public function test_business_identifier_is_readonly(): void
    {
        $this->assertTrue((new \ReflectionClass(BusinessIdentifier::class))->isReadOnly());
    }

    public function test_business_identifier_implements_the_value_object_contract(): void
    {
        $this->assertContains(ValueObject::class, class_implements(BusinessIdentifier::class));
    }

    /**
     * `\Stringable` is present because PHP adds it automatically to any class
     * declaring `__toString()`; it is not separately declared. Nothing beyond
     * those two is implemented.
     */
    public function test_business_identifier_implements_only_value_object_and_stringable(): void
    {
        $implemented = array_values(class_implements(BusinessIdentifier::class));

        sort($implemented);

        $this->assertSame([ValueObject::class, \Stringable::class], $implemented);
    }

    public function test_business_identifier_is_not_json_serializable(): void
    {
        $this->assertNotContains(\JsonSerializable::class, class_implements(BusinessIdentifier::class));
    }

    public function test_the_constructor_is_final_and_private(): void
    {
        $constructor = (new \ReflectionClass(BusinessIdentifier::class))->getConstructor();

        $this->assertInstanceOf(\ReflectionMethod::class, $constructor);
        $this->assertTrue($constructor->isPrivate(), 'The constructor must be private.');
        $this->assertTrue($constructor->isFinal(), 'The constructor must be final.');
    }

    public function test_the_constructor_takes_exactly_one_required_uuid_v7(): void
    {
        $constructor = (new \ReflectionClass(BusinessIdentifier::class))->getConstructor();

        $this->assertInstanceOf(\ReflectionMethod::class, $constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertSame(1, $constructor->getNumberOfRequiredParameters());

        $parameter = $constructor->getParameters()[0];
        $type = $parameter->getType();

        $this->assertSame('value', $parameter->getName());
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(UuidV7::class, $type->getName());
        $this->assertFalse($type->allowsNull());
    }

    public function test_business_identifier_declares_exactly_the_approved_public_methods(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass(BusinessIdentifier::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        sort($methods);

        $this->assertSame(
            ['__toString', 'equals', 'fromString', 'generate', 'toString'],
            $methods,
        );
    }

    public function test_business_identifier_declares_exactly_the_approved_protected_methods(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass(BusinessIdentifier::class))->getMethods(\ReflectionMethod::IS_PROTECTED),
        );

        sort($methods);

        $this->assertSame(['equalityComponents', 'fromUuid'], $methods);
    }

    /**
     * Every declared method is `final`.
     *
     * This is the language guarantee the design leans on: a subclass can
     * replace none of them, so construction, canonical rendering, and equality
     * behave identically for every identifier type.
     */
    #[DataProvider('declaredMethods')]
    public function test_every_declared_method_is_final(string $method): void
    {
        $this->assertTrue(
            (new \ReflectionMethod(BusinessIdentifier::class, $method))->isFinal(),
            $method.'() must be final.',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function declaredMethods(): iterable
    {
        foreach ([
            '__construct', 'fromUuid', 'fromString', 'generate',
            'toString', '__toString', 'equalityComponents', 'equals',
        ] as $method) {
            yield $method => [$method];
        }
    }

    #[DataProvider('staticMethods')]
    public function test_the_named_constructors_are_static(string $method): void
    {
        $this->assertTrue((new \ReflectionMethod(BusinessIdentifier::class, $method))->isStatic());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function staticMethods(): iterable
    {
        foreach (['fromUuid', 'fromString', 'generate'] as $method) {
            yield $method => [$method];
        }
    }

    #[DataProvider('instanceMethods')]
    public function test_the_instance_methods_are_not_static(string $method): void
    {
        $this->assertFalse((new \ReflectionMethod(BusinessIdentifier::class, $method))->isStatic());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function instanceMethods(): iterable
    {
        foreach (['toString', '__toString', 'equalityComponents', 'equals'] as $method) {
            yield $method => [$method];
        }
    }

    public function test_from_uuid_is_protected(): void
    {
        $this->assertTrue((new \ReflectionMethod(BusinessIdentifier::class, 'fromUuid'))->isProtected());
    }

    public function test_equality_components_is_protected(): void
    {
        $this->assertTrue(
            (new \ReflectionMethod(BusinessIdentifier::class, 'equalityComponents'))->isProtected(),
        );
    }

    public function test_equals_matches_the_value_object_signature_exactly(): void
    {
        $method = new \ReflectionMethod(BusinessIdentifier::class, 'equals');
        $parameter = $method->getParameters()[0];
        $parameterType = $parameter->getType();
        $returnType = $method->getReturnType();

        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame(1, $method->getNumberOfRequiredParameters());
        $this->assertInstanceOf(\ReflectionNamedType::class, $parameterType);
        $this->assertSame(ValueObject::class, $parameterType->getName());
        $this->assertFalse($parameterType->allowsNull());
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('bool', $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }

    #[DataProvider('stringReturningMethods')]
    public function test_the_rendering_methods_return_a_non_nullable_string(string $method): void
    {
        $returnType = (new \ReflectionMethod(BusinessIdentifier::class, $method))->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('string', $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function stringReturningMethods(): iterable
    {
        foreach (['toString', '__toString'] as $method) {
            yield $method => [$method];
        }
    }

    /**
     * The deferred and prohibited API, named individually.
     *
     * Listing each one means a future addition fails with the member's own name
     * rather than an opaque count mismatch.
     */
    #[DataProvider('prohibitedMembers')]
    public function test_business_identifier_declares_no_prohibited_or_deferred_member(string $member): void
    {
        $this->assertFalse(
            method_exists(BusinessIdentifier::class, $member),
            $member.'() must not exist on the PF-044 contract.',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function prohibitedMembers(): iterable
    {
        foreach ([
            'toUuid', 'uuid', 'getUuid', 'value', 'getValue', 'jsonSerialize',
            'serialize', 'unserialize', '__serialize', '__unserialize', 'toArray',
            'toPrimitives', 'fromPrimitives', 'toBytes', 'fromBytes', 'timestamp',
            'compareTo', 'isBefore', 'isAfter', 'nil', 'max', 'hash', 'copy',
            'notEquals', 'with',
        ] as $member) {
            yield $member => [$member];
        }
    }

    // -------------------------------------------------------- stored state

    public function test_the_base_declares_exactly_one_stored_property(): void
    {
        $properties = (new \ReflectionClass(BusinessIdentifier::class))->getProperties();

        $this->assertCount(1, $properties);
    }

    public function test_the_only_stored_property_is_a_private_uuid_v7_named_value(): void
    {
        $property = (new \ReflectionClass(BusinessIdentifier::class))->getProperties()[0];
        $type = $property->getType();

        $this->assertSame('value', $property->getName());
        $this->assertTrue($property->isPrivate());
        $this->assertTrue($property->isReadOnly());
        $this->assertFalse($property->isStatic());
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(UuidV7::class, $type->getName());
        $this->assertFalse($type->allowsNull());
    }

    public function test_the_base_declares_no_static_property(): void
    {
        $this->assertSame([], (new \ReflectionClass(BusinessIdentifier::class))->getStaticProperties());
    }

    public function test_the_base_declares_no_constant(): void
    {
        $this->assertSame([], (new \ReflectionClass(BusinessIdentifier::class))->getConstants());
    }

    /**
     * No collaborator that could supply ambient time or entropy is held.
     *
     * The generator arrives as a `generate()` parameter and is never retained,
     * so the type has no clock, no randomness source, and no hidden factory.
     */
    public function test_the_base_holds_no_clock_generator_or_other_collaborator(): void
    {
        $property = (new \ReflectionClass(BusinessIdentifier::class))->getProperties()[0];
        $type = $property->getType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertNotSame(UuidV7Generator::class, $type->getName());
        $this->assertSame(UuidV7::class, $type->getName());
    }

    // ------------------------------------------------------- construction

    public function test_a_valid_lowercase_uuid_is_parsed(): void
    {
        $identifier = AlphaTestIdentifier::fromString(self::VALID);

        $this->assertInstanceOf(AlphaTestIdentifier::class, $identifier);
        $this->assertSame(self::VALID, $identifier->toString());
    }

    public function test_the_named_constructor_returns_the_concrete_class_called(): void
    {
        $this->assertSame(
            AlphaTestIdentifier::class,
            AlphaTestIdentifier::fromString(self::VALID)::class,
        );

        $this->assertSame(
            BetaTestIdentifier::class,
            BetaTestIdentifier::fromString(self::VALID)::class,
        );
    }

    public function test_uppercase_input_is_canonicalized_to_lowercase(): void
    {
        $identifier = AlphaTestIdentifier::fromString(strtoupper(self::VALID));

        $this->assertSame(self::VALID, $identifier->toString());
    }

    public function test_mixed_case_input_is_canonicalized_to_lowercase(): void
    {
        $mixed = '019FA14f-813E-702f-AA24-5b85BD74d75F';

        $this->assertSame(self::VALID, strtolower($mixed), 'Fixture premise: same value, mixed case.');

        $this->assertSame(self::VALID, AlphaTestIdentifier::fromString($mixed)->toString());
    }

    public function test_case_never_affects_equality(): void
    {
        $lower = AlphaTestIdentifier::fromString(self::VALID);
        $upper = AlphaTestIdentifier::fromString(strtoupper(self::VALID));

        $this->assertTrue($lower->equals($upper));
        $this->assertTrue($upper->equals($lower));
    }

    public function test_the_protected_construction_seam_builds_from_a_valid_uuid(): void
    {
        $uuid = UuidV7::fromString(self::VALID);
        $identifier = SeamTestIdentifier::throughSeam($uuid);

        $this->assertInstanceOf(SeamTestIdentifier::class, $identifier);
        $this->assertSame(self::VALID, $identifier->toString());
    }

    public function test_construction_round_trips_through_canonical_text(): void
    {
        $this->assertSame(
            self::VALID,
            AlphaTestIdentifier::fromString(AlphaTestIdentifier::fromString(self::VALID)->toString())->toString(),
        );
    }

    // ---------------------------------------------------------- rejection

    #[DataProvider('rejectedText')]
    public function test_invalid_text_is_rejected(string $text): void
    {
        $this->expectException(InvalidArgument::class);

        AlphaTestIdentifier::fromString($text);
    }

    /**
     * Representative rejection cover only.
     *
     * PF-048 already proves the full 39-case matrix against `UuidV7` itself.
     * These cases prove PF-044 delegates rather than re-validating, and add no
     * acceptance of their own.
     *
     * @return iterable<string, array{string}>
     */
    public static function rejectedText(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => ['   '];
        yield 'leading whitespace' => [' '.self::VALID];
        yield 'trailing whitespace' => [self::VALID.' '];
        yield 'trailing newline' => [self::VALID."\n"];
        yield 'arbitrary text' => ['not-a-uuid'];
        yield 'non-hexadecimal character' => ['019fa14f-813e-702f-aa24-5b85bd74d75g'];
        yield 'hyphens removed' => [str_replace('-', '', self::VALID)];
        yield 'brace wrapped' => ['{'.self::VALID.'}'];
        yield 'urn prefixed' => ['urn:uuid:'.self::VALID];
        yield 'too short' => [substr(self::VALID, 0, -1)];
        yield 'too long' => [self::VALID.'0'];
        yield 'nil uuid' => ['00000000-0000-0000-0000-000000000000'];
        yield 'max uuid' => ['ffffffff-ffff-ffff-ffff-ffffffffffff'];
    }

    #[DataProvider('nonVersionSevenText')]
    public function test_a_uuid_of_another_version_is_rejected(string $text): void
    {
        $this->expectException(InvalidArgument::class);

        AlphaTestIdentifier::fromString($text);
    }

    /**
     * The same value with only the version nibble changed.
     *
     * Every one of these is a well-formed UUID of the wrong version, so it can
     * fail for no reason other than the version check.
     *
     * @return iterable<string, array{string}>
     */
    public static function nonVersionSevenText(): iterable
    {
        foreach (['0', '1', '2', '3', '4', '5', '6', '8', 'f'] as $version) {
            yield 'version '.$version => [substr_replace(self::VALID, $version, 14, 1)];
        }
    }

    public function test_a_non_rfc_variant_is_rejected(): void
    {
        $this->expectException(InvalidArgument::class);

        AlphaTestIdentifier::fromString(substr_replace(self::VALID, 'c', 19, 1));
    }

    #[DataProvider('rejectionSuperTypes')]
    public function test_rejection_is_catchable_through_the_foundation_taxonomy(string $exception): void
    {
        $this->expectException($exception);

        AlphaTestIdentifier::fromString('not-a-uuid');
    }

    /**
     * @return iterable<string, array{class-string<\Throwable>}>
     */
    public static function rejectionSuperTypes(): iterable
    {
        yield 'InvalidArgument' => [InvalidArgument::class];
        yield 'DomainException' => [DomainException::class];
        yield 'FoundationException' => [FoundationException::class];
        yield 'RuntimeException' => [\RuntimeException::class];
    }

    #[DataProvider('rejectedTextForMessageCheck')]
    public function test_the_rejected_text_never_reaches_the_exception_message(string $text): void
    {
        try {
            AlphaTestIdentifier::fromString($text);
        } catch (InvalidArgument $rejected) {
            $this->assertStringNotContainsString($text, $rejected->getMessage());

            return;
        }

        $this->fail('The supplied text should have been rejected.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedTextForMessageCheck(): iterable
    {
        yield 'arbitrary text' => ['a-secret-looking-value'];
        yield 'sql fragment' => ["' OR 1=1 --"];
        yield 'near miss' => [self::VALID.'0'];
        yield 'wrong version' => [substr_replace(self::VALID, '4', 14, 1)];
    }

    // --------------------------------------------------------- generation

    public function test_an_identifier_is_generated_through_the_supplied_generator(): void
    {
        $generator = new ScriptedUuidV7Generator([UuidV7::fromString(self::VALID)]);

        $identifier = AlphaTestIdentifier::generate($generator);

        $this->assertInstanceOf(AlphaTestIdentifier::class, $identifier);
        $this->assertSame(self::VALID, $identifier->toString());
    }

    public function test_generation_returns_the_concrete_class_called(): void
    {
        $generator = new ScriptedUuidV7Generator([
            UuidV7::fromString(self::VALID),
            UuidV7::fromString(self::VALID),
        ]);

        $this->assertSame(AlphaTestIdentifier::class, AlphaTestIdentifier::generate($generator)::class);
        $this->assertSame(BetaTestIdentifier::class, BetaTestIdentifier::generate($generator)::class);
    }

    public function test_the_generator_is_invoked_exactly_once_per_identifier(): void
    {
        $generator = new ScriptedUuidV7Generator([UuidV7::fromString(self::VALID)]);

        AlphaTestIdentifier::generate($generator);

        $this->assertSame(1, $generator->calls);
    }

    public function test_generation_consumes_one_value_per_call(): void
    {
        $generator = new ScriptedUuidV7Generator([
            UuidV7::fromString(self::VALID),
            UuidV7::fromString(self::OTHER_VALID),
        ]);

        $first = AlphaTestIdentifier::generate($generator);
        $second = AlphaTestIdentifier::generate($generator);

        $this->assertSame(2, $generator->calls);
        $this->assertSame(self::VALID, $first->toString());
        $this->assertSame(self::OTHER_VALID, $second->toString());
        $this->assertFalse($first->equals($second));
    }

    public function test_the_generator_is_not_retained_after_generation(): void
    {
        $generator = new ScriptedUuidV7Generator([UuidV7::fromString(self::VALID)]);

        $identifier = AlphaTestIdentifier::generate($generator);

        // The stored state is read from the declaring class: a private parent
        // property is not reported by reflection over the subclass instance.
        $properties = (new \ReflectionClass(BusinessIdentifier::class))->getProperties();

        $this->assertCount(1, $properties, 'A generated identifier stores one value and nothing else.');

        $stored = $properties[0]->getValue($identifier);

        $this->assertInstanceOf(UuidV7::class, $stored);
        $this->assertNotInstanceOf(
            UuidV7Generator::class,
            $stored,
            'No generator may be retained on the instance.',
        );
    }

    // ----------------------------------------------------------- equality

    public function test_the_same_type_with_the_same_uuid_is_equal(): void
    {
        $first = AlphaTestIdentifier::fromString(self::VALID);
        $second = AlphaTestIdentifier::fromString(self::VALID);

        $this->assertNotSame($first, $second);
        $this->assertTrue($first->equals($second));
        $this->assertTrue($second->equals($first));
    }

    public function test_the_same_type_with_a_different_uuid_is_unequal(): void
    {
        $first = AlphaTestIdentifier::fromString(self::VALID);
        $second = AlphaTestIdentifier::fromString(self::OTHER_VALID);

        $this->assertFalse($first->equals($second));
        $this->assertFalse($second->equals($first));
    }

    public function test_equality_is_reflexive(): void
    {
        $identifier = AlphaTestIdentifier::fromString(self::VALID);

        $this->assertTrue($identifier->equals($identifier));
    }

    public function test_equality_is_transitive(): void
    {
        $first = AlphaTestIdentifier::fromString(self::VALID);
        $second = AlphaTestIdentifier::fromString(self::VALID);
        $third = AlphaTestIdentifier::fromString(strtoupper(self::VALID));

        $this->assertTrue($first->equals($second));
        $this->assertTrue($second->equals($third));
        $this->assertTrue($first->equals($third));
    }

    /**
     * The property the whole type exists for.
     *
     * Two different concrete identifier types wrapping the identical UUID are
     * unequal, in both directions, with no `\TypeError`.
     */
    public function test_different_identifier_types_wrapping_the_same_uuid_are_unequal(): void
    {
        $alpha = AlphaTestIdentifier::fromString(self::VALID);
        $beta = BetaTestIdentifier::fromString(self::VALID);

        $this->assertSame($alpha->toString(), $beta->toString());
        $this->assertFalse($alpha->equals($beta));
        $this->assertFalse($beta->equals($alpha));
    }

    public function test_a_foreign_value_object_is_unequal_in_both_directions(): void
    {
        $identifier = AlphaTestIdentifier::fromString(self::VALID);
        $foreign = new ForeignTestValue(self::VALID);

        $this->assertFalse($identifier->equals($foreign));
        $this->assertFalse($foreign->equals($identifier));
    }

    public function test_a_bare_uuid_is_unequal_in_both_directions(): void
    {
        $identifier = AlphaTestIdentifier::fromString(self::VALID);
        $uuid = UuidV7::fromString(self::VALID);

        $this->assertFalse($identifier->equals($uuid));
        $this->assertFalse($uuid->equals($identifier));
    }

    public function test_equality_components_are_the_canonical_text_only(): void
    {
        $identifier = AlphaTestIdentifier::fromString(strtoupper(self::VALID));

        $components = (new \ReflectionMethod(BusinessIdentifier::class, 'equalityComponents'))
            ->invoke($identifier);

        $this->assertSame([self::VALID], $components);
    }

    // ------------------------------------------------- string conversion

    public function test_string_conversion_returns_the_canonical_text(): void
    {
        $identifier = AlphaTestIdentifier::fromString(self::VALID);

        $this->assertSame(self::VALID, (string) $identifier);
        $this->assertSame($identifier->toString(), (string) $identifier);
    }

    public function test_string_conversion_canonicalizes_uppercase_input(): void
    {
        $this->assertSame(
            self::VALID,
            (string) AlphaTestIdentifier::fromString(strtoupper(self::VALID)),
        );
    }

    public function test_string_interpolation_uses_the_canonical_text(): void
    {
        $identifier = AlphaTestIdentifier::fromString(self::VALID);

        $this->assertSame('id:'.self::VALID, "id:{$identifier}");
    }

    // -------------------------------------------------------- immutability

    public function test_the_stored_value_cannot_be_reassigned_even_through_reflection(): void
    {
        $identifier = AlphaTestIdentifier::fromString(self::VALID);
        $property = (new \ReflectionClass(BusinessIdentifier::class))->getProperty('value');

        $this->expectException(\Error::class);

        $property->setValue($identifier, UuidV7::fromString(self::OTHER_VALID));
    }

    // ------------------------------------------------------------ hygiene

    public function test_this_test_runs_without_booting_laravel(): void
    {
        $this->assertInstanceOf(TestCase::class, $this);
        $this->assertFalse(is_subclass_of($this, 'Illuminate\Foundation\Testing\TestCase'));
        $this->assertFalse(is_subclass_of($this, 'Tests\TestCase'));
        $this->assertFalse(property_exists($this, 'app'));
    }

    public function test_no_business_module_was_introduced(): void
    {
        $this->assertDirectoryDoesNotExist(\dirname(__DIR__, 5).'/app/Modules');
    }

    public function test_foundation_ships_no_concrete_identifier(): void
    {
        $files = glob(\dirname(__DIR__, 5).'/app/Foundation/Domain/Identity/*.php');

        $this->assertIsArray($files);

        $names = array_map(
            static fn (string $path): string => basename($path, '.php'),
            $files,
        );

        sort($names);

        $this->assertSame(
            ['BusinessIdentifier', 'SystemUuidV7Generator', 'UuidV7', 'UuidV7Generator'],
            $names,
        );
    }
}

/**
 * A test-only identifier in the approved production form.
 *
 * An **empty `final readonly` marker subclass**: no state, no invariant, no
 * constructor parameter, no factory alias, no behaviour. This is the shape
 * every future production leaf must take, per `app/Foundation/README.md`.
 */
final readonly class AlphaTestIdentifier extends BusinessIdentifier {}

/**
 * A second identifier type, identical in shape.
 *
 * It exists only so cross-type inequality can be proven against a type that
 * differs in nothing but its name.
 */
final readonly class BetaTestIdentifier extends BusinessIdentifier {}

/**
 * A test-only subclass that exposes the protected construction seam.
 *
 * **Deliberately not an empty marker subclass, and deliberately not `final` —
 * it is not a form to copy.** It exists for one purpose: to call the protected
 * `fromUuid()` from inside the hierarchy, which is the only way to exercise
 * that seam from a test. A production identifier never does this.
 *
 * An anonymous class cannot serve here: `new class extends BusinessIdentifier`
 * would invoke the private constructor at its declaration site, which PHP
 * refuses. A named subclass calling the static seam is the only available form.
 */
readonly class SeamTestIdentifier extends BusinessIdentifier
{
    public static function throughSeam(UuidV7 $value): static
    {
        return static::fromUuid($value);
    }
}

/**
 * A value object that is not an identifier.
 *
 * Used to prove a cross-class comparison returns `false` in both directions
 * rather than throwing.
 */
final readonly class ForeignTestValue implements ValueObject
{
    public function __construct(private string $value) {}

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }
}

/**
 * A deterministic {@see UuidV7Generator} returning scripted values in order.
 *
 * It counts its calls, so a test can prove `generate()` consumes exactly one
 * value per identifier. Declared here rather than in `app/Foundation`, per the
 * standing rule that no test double belongs in Foundation.
 */
final class ScriptedUuidV7Generator implements UuidV7Generator
{
    public int $calls = 0;

    /**
     * @param  list<UuidV7>  $values
     */
    public function __construct(private array $values) {}

    public function generate(): UuidV7
    {
        $value = array_shift($this->values);

        if (! $value instanceof UuidV7) {
            throw new \LogicException('The scripted generator ran out of values.');
        }

        $this->calls++;

        return $value;
    }
}
