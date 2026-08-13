<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Domain\Model;

use App\Foundation\Domain\Event\DomainEvent;
use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Domain\Model\AggregateRoot;
use App\Foundation\Domain\Model\Entity;
use PHPUnit\Framework\TestCase;

/**
 * PF-040 — the published `AggregateRoot` base.
 *
 * The reflection assertions pin the contract's exact shape, so a later
 * accidental widening — a public recording method, a separate peek or clear, a
 * lost `final`, a resurrected aggregate version — fails here rather than
 * silently reaching every consumer. They assert only what the language actually
 * guarantees: **no claim is made here about what every potential future
 * subclass can or cannot do.**
 *
 * Deliberately small: `AggregateRoot` has no parsing, no rejection matrix, no
 * generation path, and — by design — no validation to exercise.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly and boots no Laravel
 * application. Every fixture is declared at the bottom of this file; no
 * separate fixture file exists, and no fixture name carries business meaning.
 */
final class AggregateRootTest extends TestCase
{
    private const AGGREGATE_ID = '019fa15b-6c21-7b41-9d0e-3f7a1c62b8e4';

    private const OTHER_AGGREGATE_ID = '019fa15c-0f88-71d6-8c4a-6b2e9d05a713';

    private const EVENT_ID = '019fa15d-3a44-7c02-b7f1-5e8d24c9a061';

    private const OTHER_EVENT_ID = '019fa15e-9b17-73ad-9a52-08c6f31d7be2';

    // ---------------------------------------------------------------- shape

    public function test_aggregate_root_is_abstract(): void
    {
        $this->assertTrue((new \ReflectionClass(AggregateRoot::class))->isAbstract());
    }

    public function test_aggregate_root_extends_entity(): void
    {
        $parent = (new \ReflectionClass(AggregateRoot::class))->getParentClass();

        $this->assertInstanceOf(\ReflectionClass::class, $parent);
        $this->assertSame(Entity::class, $parent->getName());
    }

    /**
     * Not `readonly`, necessarily: the buffer is replaced on release, and PHP
     * additionally forbids a readonly class extending the non-readonly
     * `Entity`.
     */
    public function test_aggregate_root_is_not_readonly(): void
    {
        $this->assertFalse((new \ReflectionClass(AggregateRoot::class))->isReadOnly());
    }

    public function test_aggregate_root_implements_no_interface(): void
    {
        $this->assertSame([], (new \ReflectionClass(AggregateRoot::class))->getInterfaceNames());
    }

    public function test_aggregate_root_declares_no_constructor_of_its_own(): void
    {
        $constructor = (new \ReflectionClass(AggregateRoot::class))->getConstructor();

        $this->assertInstanceOf(\ReflectionMethod::class, $constructor);
        $this->assertSame(
            Entity::class,
            $constructor->getDeclaringClass()->getName(),
            'The inherited Entity constructor must be the only one.',
        );
    }

    public function test_aggregate_root_declares_no_constant(): void
    {
        $this->assertSame([], (new \ReflectionClass(AggregateRoot::class))->getConstants());
    }

    /**
     * Exactly one declared member, and it is the event buffer.
     *
     * `Entity::$id` is private, so reflection never reports it here; the filter
     * on declaring class makes that independent of PHP's inheritance
     * behaviour rather than reliant on it.
     */
    public function test_aggregate_root_declares_exactly_one_property(): void
    {
        $this->assertCount(1, $this->declaredProperties());
    }

    public function test_the_only_property_is_a_private_non_static_array_buffer(): void
    {
        $property = $this->declaredProperties()[0];
        $type = $property->getType();

        $this->assertTrue($property->isPrivate(), 'The event buffer must be private.');
        $this->assertFalse($property->isStatic(), 'The event buffer must not be static.');
        $this->assertFalse($property->isReadOnly(), 'The event buffer must be replaceable on release.');
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame('array', $type->getName());
        $this->assertFalse($type->allowsNull());
    }

    public function test_the_event_buffer_starts_empty(): void
    {
        $property = $this->declaredProperties()[0];

        $this->assertTrue($property->hasDefaultValue());
        $this->assertSame([], $property->getDefaultValue());
    }

    /**
     * No static event state anywhere on the type — a static buffer would make
     * every aggregate on the platform share one batch.
     */
    public function test_aggregate_root_holds_no_static_state(): void
    {
        $reflection = new \ReflectionClass(AggregateRoot::class);

        $this->assertSame([], $reflection->getStaticProperties());
        $this->assertSame(
            [],
            $reflection->getMethods(\ReflectionMethod::IS_STATIC),
            'AggregateRoot must declare no static method.',
        );
    }

    public function test_aggregate_root_declares_exactly_the_two_approved_members(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            array_filter(
                (new \ReflectionClass(AggregateRoot::class))->getMethods(),
                static fn (\ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === AggregateRoot::class,
            ),
        );

        sort($methods);

        $this->assertSame(['recordThat', 'releaseEvents'], $methods);
    }

    public function test_record_that_is_protected_and_final(): void
    {
        $method = new \ReflectionMethod(AggregateRoot::class, 'recordThat');

        $this->assertTrue($method->isProtected(), 'recordThat() must be protected.');
        $this->assertFalse($method->isPublic(), 'recordThat() must not be public.');
        $this->assertTrue($method->isFinal(), 'recordThat() must be final.');
        $this->assertFalse($method->isStatic());
    }

    public function test_record_that_takes_exactly_one_required_domain_event_and_returns_void(): void
    {
        $method = new \ReflectionMethod(AggregateRoot::class, 'recordThat');
        $parameterType = $method->getParameters()[0]->getType();
        $returnType = $method->getReturnType();

        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame(1, $method->getNumberOfRequiredParameters());
        $this->assertInstanceOf(\ReflectionNamedType::class, $parameterType);
        $this->assertSame(DomainEvent::class, $parameterType->getName());
        $this->assertFalse($parameterType->allowsNull());
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('void', $returnType->getName());
    }

    public function test_release_events_is_public_and_final(): void
    {
        $method = new \ReflectionMethod(AggregateRoot::class, 'releaseEvents');

        $this->assertTrue($method->isPublic(), 'releaseEvents() must be public.');
        $this->assertTrue($method->isFinal(), 'releaseEvents() must be final.');
        $this->assertFalse($method->isStatic());
    }

    public function test_release_events_takes_no_parameter_and_returns_a_non_nullable_array(): void
    {
        $method = new \ReflectionMethod(AggregateRoot::class, 'releaseEvents');
        $returnType = $method->getReturnType();

        $this->assertSame(0, $method->getNumberOfParameters());
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('array', $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }

    public function test_the_public_api_adds_exactly_release_events_to_entity(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass(AggregateRoot::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        sort($methods);

        $this->assertSame(['id', 'releaseEvents', 'sameIdentityAs'], $methods);
    }

    /**
     * The prohibited API, named individually.
     *
     * Listing each one means a future addition fails with the member's own name
     * rather than an opaque count mismatch. The `version` family is prohibited
     * by the approved decision that aggregate versioning is a persistence
     * concern outside Foundation, not merely deferred within it.
     */
    public function test_aggregate_root_declares_no_prohibited_member(): void
    {
        foreach ([
            'peekEvents', 'events', 'domainEvents', 'recordedEvents', 'pullDomainEvents',
            'clearEvents', 'flushEvents', 'discardEvents', 'hasEvents', 'eventCount',
            'record', 'recordEvent', 'raise', 'apply', 'replay', 'reconstitute',
            'dispatch', 'publish', 'handle',
            'version', 'aggregateVersion', 'expectedVersion', 'sequenceNumber', 'concurrencyToken',
            'equals', '__toString', 'jsonSerialize', 'toArray', 'toPrimitives', '__debugInfo',
        ] as $member) {
            $this->assertFalse(
                method_exists(AggregateRoot::class, $member),
                $member.'() must not exist on the PF-040 contract.',
            );
        }
    }

    // ------------------------------------------------------- docblock pins

    /**
     * The class docblock declares the invariant template and the generic
     * `@extends`, matching the rule `Entity` records for every subclass.
     */
    public function test_the_class_docblock_declares_the_generic_tags(): void
    {
        $docComment = (new \ReflectionClass(AggregateRoot::class))->getDocComment();

        $this->assertIsString($docComment);
        $this->assertStringContainsString('@template TIdentifier of BusinessIdentifier', $docComment);
        $this->assertStringContainsString('@extends Entity<TIdentifier>', $docComment);
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*\*\s*@template-covariant\b/m',
            $docComment,
            'The class docblock must not declare @template-covariant as a tag.',
        );
    }

    // -------------------------------------------------------------- release

    public function test_releasing_from_a_fresh_aggregate_returns_an_empty_list(): void
    {
        $aggregate = new PrimaryTestAggregate(AggregateTestIdentifier::fromString(self::AGGREGATE_ID));

        $this->assertSame([], $aggregate->releaseEvents());
    }

    public function test_release_returns_the_exact_event_instances_recorded(): void
    {
        $aggregate = new PrimaryTestAggregate(AggregateTestIdentifier::fromString(self::AGGREGATE_ID));
        $first = $this->event(self::EVENT_ID);
        $second = $this->event(self::OTHER_EVENT_ID);

        $aggregate->recordForTest($first);
        $aggregate->recordForTest($second);

        $released = $aggregate->releaseEvents();

        $this->assertCount(2, $released);
        $this->assertSame($first, $released[0]);
        $this->assertSame($second, $released[1]);
    }

    /**
     * Recording order, proven against timestamps that run *backwards*.
     *
     * The second event carries the earlier instant, so an implementation that
     * sorted by `occurredAt()` — a wall-clock reading that may be corrected
     * backwards, and never an ordering key — would reverse this list and fail
     * here.
     */
    public function test_release_preserves_recording_order_even_with_descending_timestamps(): void
    {
        $aggregate = new PrimaryTestAggregate(AggregateTestIdentifier::fromString(self::AGGREGATE_ID));
        $later = $this->event(self::EVENT_ID, '2026-03-01T12:00:00');
        $earlier = $this->event(self::OTHER_EVENT_ID, '2026-01-01T12:00:00');

        $aggregate->recordForTest($later);
        $aggregate->recordForTest($earlier);

        $released = $aggregate->releaseEvents();

        $this->assertGreaterThan(
            $released[1]->occurredAt(),
            $released[0]->occurredAt(),
            'Fixture premise: the first-recorded event carries the later instant.',
        );
        $this->assertSame($later, $released[0]);
        $this->assertSame($earlier, $released[1]);
    }

    public function test_release_clears_the_buffer_and_a_repeated_release_is_empty(): void
    {
        $aggregate = new PrimaryTestAggregate(AggregateTestIdentifier::fromString(self::AGGREGATE_ID));
        $aggregate->recordForTest($this->event(self::EVENT_ID));

        $this->assertCount(1, $aggregate->releaseEvents());
        $this->assertSame([], $aggregate->releaseEvents());
        $this->assertSame([], $aggregate->releaseEvents());
    }

    public function test_recording_after_a_release_starts_a_fresh_batch(): void
    {
        $aggregate = new PrimaryTestAggregate(AggregateTestIdentifier::fromString(self::AGGREGATE_ID));
        $first = $this->event(self::EVENT_ID);
        $second = $this->event(self::OTHER_EVENT_ID);

        $aggregate->recordForTest($first);
        $this->assertSame([$first], $aggregate->releaseEvents());

        $aggregate->recordForTest($second);

        $this->assertSame(
            [$second],
            $aggregate->releaseEvents(),
            'A fresh batch must owe nothing to the previous one.',
        );
    }

    /**
     * The returned array is the caller's own copy.
     *
     * Appending to it must not reach back into the aggregate, so the next
     * release sees only what was genuinely recorded afterwards.
     */
    public function test_mutating_the_returned_array_does_not_alter_the_aggregate(): void
    {
        $aggregate = new PrimaryTestAggregate(AggregateTestIdentifier::fromString(self::AGGREGATE_ID));
        $recorded = $this->event(self::EVENT_ID);
        $aggregate->recordForTest($recorded);

        $released = $aggregate->releaseEvents();
        $released[] = $this->event(self::OTHER_EVENT_ID);

        $this->assertCount(2, $released, 'Fixture premise: the caller\'s own copy grew.');
        $this->assertSame([], $aggregate->releaseEvents());

        $later = $this->event(self::OTHER_EVENT_ID);
        $aggregate->recordForTest($later);

        $this->assertSame([$later], $aggregate->releaseEvents());
    }

    public function test_two_aggregates_hold_independent_buffers(): void
    {
        $first = new PrimaryTestAggregate(AggregateTestIdentifier::fromString(self::AGGREGATE_ID));
        $second = new PrimaryTestAggregate(AggregateTestIdentifier::fromString(self::OTHER_AGGREGATE_ID));
        $event = $this->event(self::EVENT_ID);

        $first->recordForTest($event);

        $this->assertSame([], $second->releaseEvents(), 'One aggregate must never see another\'s events.');
        $this->assertSame([$event], $first->releaseEvents());
    }

    public function test_releasing_one_aggregate_does_not_empty_another(): void
    {
        $first = new PrimaryTestAggregate(AggregateTestIdentifier::fromString(self::AGGREGATE_ID));
        $second = new PrimaryTestAggregate(AggregateTestIdentifier::fromString(self::OTHER_AGGREGATE_ID));
        $event = $this->event(self::EVENT_ID);

        $first->recordForTest($this->event(self::EVENT_ID));
        $second->recordForTest($event);

        $first->releaseEvents();

        $this->assertSame([$event], $second->releaseEvents());
    }

    // ------------------------------------------------------ no inspection

    /**
     * Recording the identical instance twice records it twice.
     *
     * Whether that is meaningful belongs to the recording aggregate's own
     * business rules; this base deduplicates nothing.
     */
    public function test_recording_the_same_event_instance_twice_is_not_deduplicated(): void
    {
        $aggregate = new PrimaryTestAggregate(AggregateTestIdentifier::fromString(self::AGGREGATE_ID));
        $event = $this->event(self::EVENT_ID);

        $aggregate->recordForTest($event);
        $aggregate->recordForTest($event);

        $this->assertSame([$event, $event], $aggregate->releaseEvents());
    }

    public function test_two_events_sharing_an_identifier_and_instant_are_both_retained(): void
    {
        $aggregate = new PrimaryTestAggregate(AggregateTestIdentifier::fromString(self::AGGREGATE_ID));
        $first = $this->event(self::EVENT_ID);
        $second = $this->event(self::EVENT_ID);

        $aggregate->recordForTest($first);
        $aggregate->recordForTest($second);

        $released = $aggregate->releaseEvents();

        $this->assertNotSame($first, $second, 'Fixture premise: two distinct instances.');
        $this->assertSame([$first, $second], $released);
    }

    // ------------------------------------------------------------ identity

    public function test_recorded_events_do_not_affect_same_identity_as(): void
    {
        $identifier = AggregateTestIdentifier::fromString(self::AGGREGATE_ID);
        $first = new PrimaryTestAggregate($identifier);
        $second = new PrimaryTestAggregate($identifier);

        $this->assertTrue($first->sameIdentityAs($second));

        $first->recordForTest($this->event(self::EVENT_ID));

        $this->assertTrue($first->sameIdentityAs($second), 'A recorded event must not change identity.');
        $this->assertTrue($second->sameIdentityAs($first));

        $first->releaseEvents();

        $this->assertTrue($first->sameIdentityAs($second), 'Releasing must not change identity either.');
    }

    public function test_an_aggregate_keeps_its_identifier_across_recording_and_release(): void
    {
        $identifier = AggregateTestIdentifier::fromString(self::AGGREGATE_ID);
        $aggregate = new PrimaryTestAggregate($identifier);

        $aggregate->recordForTest($this->event(self::EVENT_ID));
        $aggregate->releaseEvents();

        $this->assertSame($identifier, $aggregate->id());
    }

    /**
     * Two different aggregate types carrying one identifier value are still not
     * the same aggregate — `Entity`'s exact-runtime-class check is inherited
     * unchanged, and an identical event history cannot make them match.
     */
    public function test_different_aggregate_types_with_the_same_identifier_are_not_the_same_identity(): void
    {
        $identifier = AggregateTestIdentifier::fromString(self::AGGREGATE_ID);
        $primary = new PrimaryTestAggregate($identifier);
        $secondary = new SecondaryTestAggregate($identifier);
        $event = $this->event(self::EVENT_ID);

        $primary->recordForTest($event);
        $secondary->recordForTest($event);

        $this->assertFalse($primary->sameIdentityAs($secondary));
        $this->assertFalse($secondary->sameIdentityAs($primary));
    }

    // ------------------------------------------------------------ hygiene

    public function test_this_test_runs_without_booting_laravel(): void
    {
        $this->assertInstanceOf(TestCase::class, $this);
        $this->assertFalse(is_subclass_of($this, 'Illuminate\Foundation\Testing\TestCase'));
        $this->assertFalse(is_subclass_of($this, 'Tests\TestCase'));
        $this->assertFalse(property_exists($this, 'app'));
    }

    /**
     * The dependency direction is one-way: modules consume Foundation, never
     * the reverse. This replaces an earlier guard that asserted `app/Modules`
     * did not exist at all — a scope check that was only ever true because no
     * approved business module had been implemented yet, and that would have
     * to be deleted rather than satisfied once one was.
     *
     * It matters especially for this contract: **every concrete aggregate
     * lives in the module that owns it**, so this base must be reachable from
     * a module without ever reaching back into one.
     *
     * Detection is token-aware, so only a **real** PHP reference counts: a
     * qualified or fully-qualified name appears as `T_NAME_QUALIFIED` or
     * `T_NAME_FULLY_QUALIFIED`, while a namespace mentioned in a docblock or a
     * string literal never does.
     */
    public function test_the_aggregate_root_contract_does_not_depend_on_business_modules(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 5).'/app/Foundation/Domain/Model/AggregateRoot.php');

        $this->assertIsString($source);

        $references = [];

        foreach (token_get_all($source) as $token) {
            if (! \is_array($token) || ! \in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $name = ltrim($token[1], '\\');

            if ($name === 'App\\Modules' || str_starts_with($name, 'App\\Modules\\')) {
                $references[] = $token[1];
            }
        }

        $this->assertSame([], $references, 'AggregateRoot must not depend on the App\Modules namespace.');
    }

    // ------------------------------------------------------------- helpers

    /**
     * The properties `AggregateRoot` itself declares.
     *
     * @return list<\ReflectionProperty>
     */
    private function declaredProperties(): array
    {
        return array_values(array_filter(
            (new \ReflectionClass(AggregateRoot::class))->getProperties(),
            static fn (\ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === AggregateRoot::class,
        ));
    }

    /**
     * A test event with an explicit identifier and an explicit UTC instant.
     *
     * Never ambient current time, and never `sleep()` — every instant this file
     * uses is a literal, so no assertion depends on real elapsed time.
     */
    private function event(string $eventId, string $instant = '2026-02-01T10:00:00'): RecordedTestEvent
    {
        return new RecordedTestEvent(
            UuidV7::fromString($eventId),
            new \DateTimeImmutable($instant, new \DateTimeZone('UTC')),
        );
    }
}

/**
 * A test-only identifier in the approved production form — an empty
 * `final readonly` marker subclass of `BusinessIdentifier`, exactly as PF-044
 * requires of every concrete identifier.
 */
final readonly class AggregateTestIdentifier extends BusinessIdentifier {}

/**
 * A reference domain event in the approved `final readonly class` form.
 *
 * Test-only, declared here rather than in `app/Foundation`, so PF-040 creates
 * no concrete domain event of its own. Both the identifier and the instant are
 * supplied as constructor data — never generated or read ambient.
 */
final readonly class RecordedTestEvent implements DomainEvent
{
    public function __construct(
        private UuidV7 $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): UuidV7
    {
        return $this->eventId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}

/**
 * A primary test aggregate in the approved production form: generic-typed over
 * its concrete identifier, with a narrowed native constructor parameter.
 *
 * `recordForTest()` exists because `recordThat()` is `protected` — the exact
 * property under test. A real aggregate exposes named behaviour that records as
 * a consequence; it never publishes a generic recording method like this one.
 *
 * @extends AggregateRoot<AggregateTestIdentifier>
 */
final class PrimaryTestAggregate extends AggregateRoot
{
    public function __construct(AggregateTestIdentifier $id)
    {
        parent::__construct($id);
    }

    public function recordForTest(DomainEvent $event): void
    {
        $this->recordThat($event);
    }
}

/**
 * A second aggregate type using the identical identifier type, so cross-type
 * inequality can be proven with the identifier type held constant.
 *
 * @extends AggregateRoot<AggregateTestIdentifier>
 */
final class SecondaryTestAggregate extends AggregateRoot
{
    public function __construct(AggregateTestIdentifier $id)
    {
        parent::__construct($id);
    }

    public function recordForTest(DomainEvent $event): void
    {
        $this->recordThat($event);
    }
}
