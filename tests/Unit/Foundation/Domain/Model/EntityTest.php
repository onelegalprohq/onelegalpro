<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Domain\Model;

use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Model\Entity;
use App\Foundation\Domain\Model\ValueObject;
use PHPUnit\Framework\TestCase;

/**
 * PF-041 — the published `Entity` base.
 *
 * The reflection assertions pin the contract's exact shape, so a later
 * accidental widening — an extra public method, a relaxed constructor, a lost
 * `final` — fails here rather than silently reaching every consumer. They
 * assert only what the language actually guarantees: **no claim is made here
 * about what every potential future subclass can or cannot do.**
 *
 * Deliberately small: `Entity` has no parsing, no rejection matrix, and no
 * generation path, unlike PF-044 `BusinessIdentifier`.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly and boots no
 * Laravel application. Every fixture is declared at the bottom of this file;
 * no separate fixture file exists, and no fixture name carries business
 * meaning.
 */
final class EntityTest extends TestCase
{
    private const VALID = '019fa14f-813e-702f-aa24-5b85bd74d75f';

    private const OTHER_VALID = '019fa154-ef35-73b7-99c9-7b422ec3172b';

    // ---------------------------------------------------------------- shape

    public function test_entity_is_abstract(): void
    {
        $this->assertTrue((new \ReflectionClass(Entity::class))->isAbstract());
    }

    public function test_entity_is_not_readonly(): void
    {
        $this->assertFalse((new \ReflectionClass(Entity::class))->isReadOnly());
    }

    public function test_entity_implements_no_interface(): void
    {
        $this->assertSame([], (new \ReflectionClass(Entity::class))->getInterfaceNames());
    }

    public function test_entity_is_not_a_value_object_stringable_or_json_serializable(): void
    {
        $interfaces = (new \ReflectionClass(Entity::class))->getInterfaceNames();

        $this->assertNotContains(ValueObject::class, $interfaces);
        $this->assertNotContains(\Stringable::class, $interfaces);
        $this->assertNotContains(\JsonSerializable::class, $interfaces);
    }

    public function test_the_constructor_is_protected_and_not_final(): void
    {
        $constructor = (new \ReflectionClass(Entity::class))->getConstructor();

        $this->assertInstanceOf(\ReflectionMethod::class, $constructor);
        $this->assertTrue($constructor->isProtected(), 'The constructor must be protected.');
        $this->assertFalse($constructor->isFinal(), 'The constructor must not be final.');
    }

    public function test_the_constructor_takes_exactly_one_required_business_identifier(): void
    {
        $constructor = (new \ReflectionClass(Entity::class))->getConstructor();

        $this->assertInstanceOf(\ReflectionMethod::class, $constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertSame(1, $constructor->getNumberOfRequiredParameters());

        $parameter = $constructor->getParameters()[0];
        $type = $parameter->getType();

        $this->assertSame('id', $parameter->getName());
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(BusinessIdentifier::class, $type->getName());
        $this->assertFalse($type->allowsNull());
    }

    public function test_entity_declares_exactly_one_property(): void
    {
        $this->assertCount(1, (new \ReflectionClass(Entity::class))->getProperties());
    }

    public function test_the_only_property_is_a_private_readonly_non_static_business_identifier(): void
    {
        $property = (new \ReflectionClass(Entity::class))->getProperties()[0];
        $type = $property->getType();

        $this->assertSame('id', $property->getName());
        $this->assertTrue($property->isPrivate());
        $this->assertTrue($property->isReadOnly());
        $this->assertFalse($property->isStatic());
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(BusinessIdentifier::class, $type->getName());
        $this->assertFalse($type->allowsNull());
    }

    public function test_entity_declares_no_static_property(): void
    {
        $this->assertSame([], (new \ReflectionClass(Entity::class))->getStaticProperties());
    }

    public function test_entity_declares_no_constant(): void
    {
        $this->assertSame([], (new \ReflectionClass(Entity::class))->getConstants());
    }

    public function test_entity_declares_exactly_the_approved_public_methods(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass(Entity::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        sort($methods);

        $this->assertSame(['id', 'sameIdentityAs'], $methods);
    }

    public function test_id_is_final_and_not_static(): void
    {
        $method = new \ReflectionMethod(Entity::class, 'id');

        $this->assertTrue($method->isFinal());
        $this->assertFalse($method->isStatic());
    }

    public function test_same_identity_as_is_final_and_not_static(): void
    {
        $method = new \ReflectionMethod(Entity::class, 'sameIdentityAs');

        $this->assertTrue($method->isFinal());
        $this->assertFalse($method->isStatic());
    }

    public function test_id_returns_a_non_nullable_business_identifier(): void
    {
        $returnType = (new \ReflectionMethod(Entity::class, 'id'))->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(BusinessIdentifier::class, $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }

    public function test_same_identity_as_matches_the_approved_signature(): void
    {
        $method = new \ReflectionMethod(Entity::class, 'sameIdentityAs');
        $parameter = $method->getParameters()[0];
        $parameterType = $parameter->getType();
        $returnType = $method->getReturnType();

        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame(1, $method->getNumberOfRequiredParameters());
        $this->assertInstanceOf(\ReflectionNamedType::class, $parameterType);
        $this->assertSame(Entity::class, $parameterType->getName());
        $this->assertFalse($parameterType->allowsNull());
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('bool', $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }

    /**
     * The deferred and prohibited API, named individually.
     *
     * Listing each one means a future addition fails with the member's own
     * name rather than an opaque count mismatch.
     */
    public function test_entity_declares_no_prohibited_or_deferred_member(): void
    {
        foreach ([
            'equals', '__toString', 'jsonSerialize', 'toArray', '__debugInfo',
            'recordEvent', 'releaseEvents', 'pullDomainEvents', 'clearEvents',
            'version', 'createdAt', 'updatedAt', 'deletedAt', 'isDeleted', 'delete',
            'withId', 'setId', 'hash', 'toPrimitives',
        ] as $member) {
            $this->assertFalse(
                method_exists(Entity::class, $member),
                $member.'() must not exist on the PF-041 contract.',
            );
        }
    }

    // ------------------------------------------------------- docblock pins

    /**
     * The class docblock declares the invariant template.
     *
     * A cheap string assertion that pins the decision most likely to be
     * "helpfully" refactored into the verified-broken `@template-covariant`
     * form — see the PF-041 backlog entry's PHPStan verification.
     *
     * The "not declared as a tag" check is deliberately **not** a raw
     * substring search: the class docblock's own prose explains, in words,
     * why `@template-covariant` was rejected, so a substring match against
     * the whole comment would false-positive on that explanation. The check
     * instead matches only an actual PHPDoc tag line — `@template-covariant`
     * as the first token after a docblock line's leading `*` — the same
     * "detect the construct, not the word" discipline the PF-049
     * framework-independence guard already uses for its helper-call check.
     */
    public function test_the_class_docblock_declares_the_invariant_template(): void
    {
        $docComment = (new \ReflectionClass(Entity::class))->getDocComment();

        $this->assertIsString($docComment);
        $this->assertStringContainsString('@template TIdentifier of BusinessIdentifier', $docComment);
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*\*\s*@template-covariant\b/m',
            $docComment,
            'The class docblock must not declare @template-covariant as a tag.',
        );
    }

    /**
     * `sameIdentityAs()`'s docblock declares the verified wildcard.
     *
     * `Entity<BusinessIdentifier>` is invariant and rejects every concrete
     * entity, including comparing an entity with itself — verified against
     * PHPStan 2.2.5 and recorded in the backlog entry. This pins the wildcard
     * against a regression back to that broken form.
     */
    public function test_same_identity_as_docblock_declares_the_wildcard_parameter(): void
    {
        $docComment = (new \ReflectionMethod(Entity::class, 'sameIdentityAs'))->getDocComment();

        $this->assertIsString($docComment);
        $this->assertStringContainsString('@param  Entity<*>  $other', $docComment);
    }

    // ------------------------------------------------------------ identity

    public function test_id_returns_the_exact_identifier_instance_supplied(): void
    {
        $identifier = AlphaTestIdentifier::fromString(self::VALID);
        $entity = new PrimaryTestEntity($identifier);

        $this->assertSame($identifier, $entity->id());
    }

    public function test_id_returns_the_concrete_identifier_type(): void
    {
        $entity = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));

        $this->assertInstanceOf(AlphaTestIdentifier::class, $entity->id());
    }

    /**
     * Scope: an *already-initialized* instance's identifier cannot be
     * reassigned through {@see \ReflectionProperty::setValue()}. This is a
     * within-instance stability guarantee only — it says nothing about
     * {@see \ReflectionClass::newInstanceWithoutConstructor()} or a crafted
     * `unserialize()` input, both of which bypass construction entirely to
     * produce a *different*, forged instance rather than reassigning this
     * one. See `Entity`'s constructor docblock for that distinction; PF-041
     * does not add production code to prevent construction bypass, because
     * PHP cannot make it impossible.
     */
    public function test_initialized_identity_cannot_be_reassigned_through_reflection(): void
    {
        $entity = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));
        $property = (new \ReflectionClass(Entity::class))->getProperty('id');

        $this->expectException(\Error::class);

        $property->setValue($entity, AlphaTestIdentifier::fromString(self::OTHER_VALID));
    }

    /**
     * Property shadowing is possible but harmless to the contract.
     *
     * The shadowing fixture declares its own `id` property. PHP keeps the two
     * separately (distinct mangled names), and the base's `id()` and
     * `sameIdentityAs()` still read `Entity::$id`, never the subclass's own.
     */
    public function test_subclass_property_shadowing_does_not_alter_base_identity(): void
    {
        $identifier = AlphaTestIdentifier::fromString(self::VALID);
        $entity = new ShadowingTestEntity($identifier, 'shadow-value');

        $this->assertSame($identifier, $entity->id());
        $this->assertSame('shadow-value', $entity->ownShadowedId());
    }

    /**
     * Discriminating proof that `Entity::$id` governs comparison and the
     * subclass's own shadowed `id` property does not.
     *
     * Comparing `ShadowingTestEntity` against `PrimaryTestEntity` would prove
     * nothing here: they are different concrete entity types, so
     * `sameIdentityAs()` returns `false` on the exact-runtime-class check
     * alone, regardless of what either identifier or shadow value holds. The
     * discriminating case holds the entity type constant and varies the base
     * identifier and the shadowed property independently instead.
     */
    public function test_shadowing_entities_with_the_same_base_identifier_are_the_same_identity_regardless_of_the_shadowed_value(): void
    {
        $identifier = AlphaTestIdentifier::fromString(self::VALID);
        $first = new ShadowingTestEntity($identifier, 'shadow-value-one');
        $second = new ShadowingTestEntity($identifier, 'shadow-value-two');

        $this->assertNotSame($first->ownShadowedId(), $second->ownShadowedId(), 'Fixture premise: the shadowed values differ.');
        $this->assertTrue($first->sameIdentityAs($second));
        $this->assertTrue($second->sameIdentityAs($first));
    }

    public function test_shadowing_entities_with_different_base_identifiers_are_not_the_same_identity_regardless_of_a_matching_shadowed_value(): void
    {
        $first = new ShadowingTestEntity(AlphaTestIdentifier::fromString(self::VALID), 'identical-shadow-value');
        $second = new ShadowingTestEntity(AlphaTestIdentifier::fromString(self::OTHER_VALID), 'identical-shadow-value');

        $this->assertSame($first->ownShadowedId(), $second->ownShadowedId(), 'Fixture premise: the shadowed values match.');
        $this->assertFalse($first->sameIdentityAs($second));
        $this->assertFalse($second->sameIdentityAs($first));
    }

    /**
     * The missed-parent-constructor failure is latent, not immediate.
     *
     * Construction itself succeeds and the object remains a valid, usable
     * instance; only reaching `id()` (or `sameIdentityAs()`, which reads `id`
     * internally) triggers PHP's uninitialised-readonly-property `\Error`.
     */
    public function test_omitting_parent_constructor_fails_only_when_id_is_reached(): void
    {
        $entity = new ForgetfulTestEntity;

        $this->assertInstanceOf(ForgetfulTestEntity::class, $entity, 'Construction itself must succeed.');

        $this->expectException(\Error::class);

        $entity->id();
    }

    /**
     * `sameIdentityAs()` reaches the same uninitialized property `id()`
     * raises on above — but only once its exact-runtime-class check has
     * already matched. Two `ForgetfulTestEntity` instances share a runtime
     * class, so the check passes and the uninitialized read is reached.
     */
    public function test_same_identity_as_between_two_same_class_forgetful_entities_raises_error(): void
    {
        $first = new ForgetfulTestEntity;
        $second = new ForgetfulTestEntity;

        $this->expectException(\Error::class);

        $first->sameIdentityAs($second);
    }

    /**
     * The exact-runtime-class check short-circuits before either identifier
     * is read, so comparing the forgetful fixture against a *differently*
     * typed, correctly constructed entity returns `false` in both
     * directions and never raises `\Error`. This short-circuit is expected
     * behaviour, not a workaround or an identity fallback.
     */
    public function test_same_identity_as_between_a_forgetful_entity_and_a_different_class_returns_false_without_error(): void
    {
        $forgetful = new ForgetfulTestEntity;
        $primary = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));

        $this->assertFalse($forgetful->sameIdentityAs($primary));
        $this->assertFalse($primary->sameIdentityAs($forgetful));
    }

    // ----------------------------------------------------------- equality

    public function test_same_type_and_same_identifier_are_the_same_identity(): void
    {
        $first = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));
        $second = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));

        $this->assertNotSame($first, $second);
        $this->assertTrue($first->sameIdentityAs($second));
        $this->assertTrue($second->sameIdentityAs($first));
    }

    public function test_same_type_and_different_identifiers_are_not_the_same_identity(): void
    {
        $first = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));
        $second = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::OTHER_VALID));

        $this->assertFalse($first->sameIdentityAs($second));
        $this->assertFalse($second->sameIdentityAs($first));
    }

    /**
     * The property the whole type exists for: two different entity types
     * sharing one identifier type and the identical identifier value are
     * still not the same entity, in either direction.
     */
    public function test_different_entity_types_with_the_same_identifier_are_unequal_in_both_directions(): void
    {
        $identifier = AlphaTestIdentifier::fromString(self::VALID);
        $primary = new PrimaryTestEntity($identifier);
        $secondary = new SecondaryTestEntity($identifier);

        $this->assertSame($primary->id()->toString(), $secondary->id()->toString());
        $this->assertFalse($primary->sameIdentityAs($secondary));
        $this->assertFalse($secondary->sameIdentityAs($primary));
    }

    /**
     * A differently typed identifier can never produce a false positive
     * through `sameIdentityAs()` itself — not merely through
     * `BusinessIdentifier::equals()` checked in isolation.
     *
     * `PrimaryTestEntity` carries an `AlphaTestIdentifier` and
     * `OtherIdentifierTestEntity` carries a `BetaTestIdentifier`, both built
     * from the **identical canonical UUID text**, so the only thing that could
     * make them compare equal is a bug that let identifier-type checking be
     * bypassed. It is not: the comparison returns `false` in both directions
     * with no `\TypeError`.
     */
    public function test_entities_with_differently_typed_identifiers_over_the_same_uuid_text_are_not_the_same_identity(): void
    {
        $primary = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));
        $other = new OtherIdentifierTestEntity(BetaTestIdentifier::fromString(self::VALID));

        $this->assertSame($primary->id()->toString(), $other->id()->toString(), 'Fixture premise: identical canonical text.');
        $this->assertFalse($primary->sameIdentityAs($other));
        $this->assertFalse($other->sameIdentityAs($primary));
    }

    /**
     * Supporting evidence only, at the identifier layer directly — this must
     * not be the only assertion for the acceptance criterion above, since it
     * does not exercise `Entity::sameIdentityAs()` at all.
     */
    public function test_supporting_evidence_differently_typed_identifiers_are_unequal_at_the_business_identifier_layer(): void
    {
        $alpha = AlphaTestIdentifier::fromString(self::VALID);
        $beta = BetaTestIdentifier::fromString(self::VALID);

        $this->assertFalse($alpha->equals($beta));
        $this->assertFalse($beta->equals($alpha));
    }

    public function test_identity_equality_is_reflexive(): void
    {
        $entity = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));

        $this->assertTrue($entity->sameIdentityAs($entity));
    }

    public function test_identity_equality_is_symmetric(): void
    {
        $first = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));
        $second = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));

        // Both directions are asserted true explicitly, rather than merely
        // asserting the two results are equal to each other — that weaker
        // form would also pass if sameIdentityAs() always returned false.
        $this->assertTrue($first->sameIdentityAs($second));
        $this->assertTrue($second->sameIdentityAs($first));
    }

    public function test_identity_equality_is_transitive(): void
    {
        $first = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));
        $second = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));
        $third = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));

        $this->assertTrue($first->sameIdentityAs($second));
        $this->assertTrue($second->sameIdentityAs($third));
        $this->assertTrue($first->sameIdentityAs($third));
    }

    public function test_changing_non_identity_state_does_not_affect_identity_comparison(): void
    {
        $identifier = AlphaTestIdentifier::fromString(self::VALID);
        $first = new PrimaryTestEntity($identifier);
        $second = new PrimaryTestEntity($identifier);

        $this->assertTrue($first->sameIdentityAs($second));

        $first->mutableState = 'changed-by-the-test';
        $second->mutableState = 'a-completely-different-value';

        $this->assertTrue($first->sameIdentityAs($second), 'Mutating non-identity state must not affect identity.');
    }

    // --------------------------------------------------------- type safety

    public function test_a_narrowed_concrete_constructor_rejects_the_wrong_identifier_type(): void
    {
        $this->expectException(\TypeError::class);

        new PrimaryTestEntity(BetaTestIdentifier::fromString(self::VALID));
    }

    // ------------------------------------------------------- relationship

    public function test_entity_is_not_an_instance_of_value_object(): void
    {
        $entity = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));

        $this->assertNotInstanceOf(ValueObject::class, $entity);
    }

    public function test_a_value_object_parameter_rejects_an_entity(): void
    {
        $entity = new PrimaryTestEntity(AlphaTestIdentifier::fromString(self::VALID));

        $this->expectException(\TypeError::class);

        acceptValueObjectOnly($entity);
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

    public function test_domain_model_contains_only_entity_and_value_object(): void
    {
        $files = glob(\dirname(__DIR__, 5).'/app/Foundation/Domain/Model/*.php');

        $this->assertIsArray($files);

        $names = array_map(
            static fn (string $path): string => basename($path, '.php'),
            $files,
        );

        sort($names);

        $this->assertSame(['Entity', 'ValueObject'], $names);
    }
}

/**
 * Requires a {@see ValueObject}; used to prove an `Entity` is rejected by a
 * `ValueObject`-typed parameter with a `\TypeError`, never silently accepted.
 */
function acceptValueObjectOnly(ValueObject $value): void {}

/**
 * A test-only identifier in the approved production form.
 *
 * An empty `final readonly` marker subclass of `BusinessIdentifier`, exactly
 * as PF-044 requires of every concrete identifier.
 */
final readonly class AlphaTestIdentifier extends BusinessIdentifier {}

/**
 * A second identifier type, identical in shape.
 *
 * Exists only so a wrong-identifier-type construction can be proven rejected,
 * and so identifier-level cross-type inequality can be demonstrated.
 */
final readonly class BetaTestIdentifier extends BusinessIdentifier {}

/**
 * A primary test entity: the approved production form, generic-typed over
 * `AlphaTestIdentifier`, with a narrowed native constructor parameter and one
 * mutable, non-identity property.
 *
 * @extends Entity<AlphaTestIdentifier>
 */
final class PrimaryTestEntity extends Entity
{
    public string $mutableState = 'initial';

    public function __construct(AlphaTestIdentifier $id)
    {
        parent::__construct($id);
    }
}

/**
 * A second entity type using the identical identifier type as
 * {@see PrimaryTestEntity}, so cross-entity-type inequality can be proven even
 * when the identifier type is held constant.
 *
 * @extends Entity<AlphaTestIdentifier>
 */
final class SecondaryTestEntity extends Entity
{
    public function __construct(AlphaTestIdentifier $id)
    {
        parent::__construct($id);
    }
}

/**
 * A test-only entity using a *different* identifier type than
 * {@see PrimaryTestEntity}, so `sameIdentityAs()` itself — not merely
 * `BusinessIdentifier::equals()` checked in isolation — can be proven to
 * reject a cross-identifier-type comparison without a `\TypeError`, even when
 * both identifiers are built from the identical canonical UUID text.
 *
 * @extends Entity<BetaTestIdentifier>
 */
final class OtherIdentifierTestEntity extends Entity
{
    public function __construct(BetaTestIdentifier $id)
    {
        parent::__construct($id);
    }
}

/**
 * A test-only fixture that omits `parent::__construct()`.
 *
 * **Deliberately broken, and not a form to copy.** It exists solely to prove
 * that the resulting failure is latent — construction succeeds, and only
 * `id()` raises PHP's uninitialised-readonly-property `\Error`.
 * `sameIdentityAs()` raises the identical `\Error` only when compared
 * against another instance of the *same* runtime class, because its
 * exact-class check runs first and short-circuits to `false` — without
 * reading either identifier — against a differently typed entity. Never a
 * pattern a real entity should follow.
 *
 * @extends Entity<AlphaTestIdentifier>
 */
final class ForgetfulTestEntity extends Entity
{
    public function __construct() {}
}

/**
 * A test-only fixture that declares its own property named `id`.
 *
 * **Deliberately confusing, and not a form to copy.** It exists solely to
 * prove that PHP keeps the subclass's own `id` property and the base's
 * `Entity::$id` separate, and that the base's property alone governs `id()`
 * and `sameIdentityAs()` regardless of what the subclass's own property holds.
 *
 * @extends Entity<AlphaTestIdentifier>
 */
final class ShadowingTestEntity extends Entity
{
    private string $id;

    public function __construct(AlphaTestIdentifier $id, string $shadowedValue)
    {
        parent::__construct($id);
        $this->id = $shadowedValue;
    }

    public function ownShadowedId(): string
    {
        return $this->id;
    }
}
