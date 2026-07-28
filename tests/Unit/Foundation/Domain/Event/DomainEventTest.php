<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Domain\Event;

use App\Foundation\Domain\Event\DomainEvent;
use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Domain\Model\Entity;
use App\Foundation\Domain\Model\ValueObject;
use PHPUnit\Framework\TestCase;

/**
 * PF-043 — the published `DomainEvent` contract.
 *
 * The reflection assertions pin the contract's exact shape, so a later
 * accidental widening — an extra method, a nullable return type, an inherited
 * interface — fails here rather than silently reaching every consumer.
 * Reflection assertions use fully qualified class names throughout, never
 * `'self'` or `'static'`.
 *
 * The behavioural assertions run against the one reference fixture declared
 * at the bottom of this file. **They demonstrate the approved reference
 * implementation form; they do not prove that every possible interface
 * implementation obeys UTC, `final readonly`, the exact `\DateTimeImmutable`
 * runtime class, or payload safety** — a PHP interface cannot enforce any of
 * those, and each remains a review and per-concrete-event-test obligation on
 * the module that ships a real event.
 *
 * Deliberately small — the contract has no parsing, no rejection matrix, and
 * no generation path. All fixtures are declared inside this file; no separate
 * fixture file exists, and no fixture name carries business meaning.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly and boots no
 * Laravel application.
 */
final class DomainEventTest extends TestCase
{
    private const EVENT_ID = '019fa14f-813e-702f-aa24-5b85bd74d75f';

    private const OTHER_EVENT_ID = '019fa154-ef35-73b7-99c9-7b422ec3172b';

    // ---------------------------------------------------------------- shape

    public function test_domain_event_is_an_interface(): void
    {
        $this->assertTrue((new \ReflectionClass(DomainEvent::class))->isInterface());
    }

    public function test_domain_event_extends_no_interface(): void
    {
        $this->assertSame([], (new \ReflectionClass(DomainEvent::class))->getInterfaceNames());
    }

    public function test_domain_event_declares_exactly_two_methods(): void
    {
        $this->assertCount(2, (new \ReflectionClass(DomainEvent::class))->getMethods());
    }

    public function test_the_declared_method_names_are_exactly_event_id_and_occurred_at(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass(DomainEvent::class))->getMethods(),
        );

        sort($methods);

        $this->assertSame(['eventId', 'occurredAt'], $methods);
    }

    public function test_both_methods_are_public_non_static_and_zero_parameter(): void
    {
        foreach (['eventId', 'occurredAt'] as $name) {
            $method = new \ReflectionMethod(DomainEvent::class, $name);

            $this->assertTrue($method->isPublic(), $name.'() must be public.');
            $this->assertFalse($method->isStatic(), $name.'() must not be static.');
            $this->assertSame(0, $method->getNumberOfParameters(), $name.'() must take zero parameters.');
        }
    }

    public function test_event_id_returns_exactly_a_non_nullable_uuid_v7(): void
    {
        $returnType = (new \ReflectionMethod(DomainEvent::class, 'eventId'))->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(UuidV7::class, $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }

    public function test_occurred_at_returns_exactly_a_non_nullable_date_time_immutable(): void
    {
        $returnType = (new \ReflectionMethod(DomainEvent::class, 'occurredAt'))->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(\DateTimeImmutable::class, $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }

    public function test_domain_event_declares_no_constant(): void
    {
        $this->assertSame([], (new \ReflectionClass(DomainEvent::class))->getConstants());
    }

    public function test_domain_event_declares_no_property(): void
    {
        $this->assertSame([], (new \ReflectionClass(DomainEvent::class))->getProperties());
    }

    public function test_domain_event_does_not_extend_value_object(): void
    {
        $interfaces = (new \ReflectionClass(DomainEvent::class))->getInterfaceNames();

        $this->assertNotContains(ValueObject::class, $interfaces);
    }

    public function test_domain_event_extends_or_implements_no_vendor_interface(): void
    {
        $interfaces = (new \ReflectionClass(DomainEvent::class))->getInterfaceNames();

        foreach ([
            \Stringable::class,
            \JsonSerializable::class,
            \ArrayAccess::class,
            \Traversable::class,
            \Serializable::class,
        ] as $vendorInterface) {
            $this->assertNotContains($vendorInterface, $interfaces, $vendorInterface.' must not be inherited.');
        }
    }

    /**
     * The complete approved named-absence member list, named individually.
     *
     * Listing each one means a future addition fails with the member's own
     * name rather than an opaque count mismatch.
     */
    public function test_domain_event_declares_no_prohibited_or_deferred_member(): void
    {
        foreach ([
            'equals', '__toString', 'jsonSerialize', 'toArray', 'toPrimitives',
            'fromPrimitives', 'hash', '__debugInfo', 'eventName', 'eventType',
            'eventVersion', 'payload', 'aggregateId', 'subjectId', 'firmId',
            'actorId', 'correlationId', 'causationId', 'recordedAt', 'publishedAt',
            'deliveredAt', 'deliveryId', 'attemptNumber', 'dispatch', 'handle', 'publish',
        ] as $member) {
            $this->assertFalse(
                method_exists(DomainEvent::class, $member),
                $member.'() must not exist on the PF-043 contract.',
            );
        }
    }

    // ------------------------------------------------------- docblock pins

    public function test_the_class_docblock_declares_no_template_tag(): void
    {
        $docComment = (new \ReflectionClass(DomainEvent::class))->getDocComment();

        $this->assertIsString($docComment);
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*\*\s*@template\b/m',
            $docComment,
            'The class docblock must not declare @template.',
        );
    }

    // ------------------------------------------------------ implementability

    public function test_a_final_readonly_class_may_implement_the_contract(): void
    {
        $reflection = new \ReflectionClass(ReferenceTestEvent::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertContains(DomainEvent::class, class_implements(ReferenceTestEvent::class));
    }

    // ------------------------------------------------- in-memory preservation

    public function test_event_id_returns_the_exact_uuid_v7_instance_supplied(): void
    {
        $eventId = UuidV7::fromString(self::EVENT_ID);
        $event = new ReferenceTestEvent($eventId, referenceInstant(), 'matter-reference');

        $this->assertSame($eventId, $event->eventId());
    }

    public function test_occurred_at_returns_the_exact_date_time_immutable_instance_supplied(): void
    {
        $instant = referenceInstant();
        $event = new ReferenceTestEvent(UuidV7::fromString(self::EVENT_ID), $instant, 'matter-reference');

        $this->assertSame($instant, $event->occurredAt());
    }

    // ------------------------------------------------------ occurrence time

    public function test_the_reference_instant_carries_utc_timezone_name_and_zero_offset(): void
    {
        $instant = referenceInstant();

        $this->assertSame('UTC', $instant->getTimezone()->getName());
        $this->assertSame(0, $instant->getOffset());
    }

    /**
     * Exact runtime class, not merely `instanceof` — catching a Carbon or
     * other vendor subclass being returned, per the PF-043 plain-native-
     * `\DateTimeImmutable` rule. The interface cannot enforce this
     * mechanically; this test demonstrates only the reference fixture's
     * own, correct, obligation-satisfying behaviour.
     */
    public function test_the_reference_instants_exact_runtime_class_is_date_time_immutable(): void
    {
        $instant = referenceInstant();

        $this->assertSame(\DateTimeImmutable::class, $instant::class);
    }

    // -------------------------------------------------------- occurrence identity

    /**
     * Identity, not value equality, distinguishes occurrences: two events
     * carrying identical non-identity payload data but different event IDs
     * are distinct occurrences.
     */
    public function test_events_with_identical_payload_but_different_event_ids_are_distinct_occurrences(): void
    {
        $instant = referenceInstant();
        $first = new ReferenceTestEvent(UuidV7::fromString(self::EVENT_ID), $instant, 'identical-payload');
        $second = new ReferenceTestEvent(UuidV7::fromString(self::OTHER_EVENT_ID), $instant, 'identical-payload');

        $this->assertSame($first->payload, $second->payload, 'Fixture premise: the payloads are identical.');
        $this->assertFalse($first->eventId()->equals($second->eventId()));
        $this->assertFalse($second->eventId()->equals($first->eventId()));
    }

    // -------------------------------------------------------------- immutability

    public function test_reassigning_a_readonly_fixture_property_throws_error(): void
    {
        $event = new ReferenceTestEvent(UuidV7::fromString(self::EVENT_ID), referenceInstant(), 'matter-reference');

        $this->expectException(\Error::class);

        $event->payload = 'reassigned';
    }

    // ------------------------------------------------------------- relationship

    public function test_the_fixture_is_neither_a_value_object_nor_an_entity(): void
    {
        $event = new ReferenceTestEvent(UuidV7::fromString(self::EVENT_ID), referenceInstant(), 'matter-reference');

        $this->assertNotInstanceOf(ValueObject::class, $event);
        $this->assertNotInstanceOf(Entity::class, $event);
    }

    // ------------------------------------------------------------------ hygiene

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

    public function test_domain_event_directory_contains_exactly_domain_event_php(): void
    {
        $files = glob(\dirname(__DIR__, 5).'/app/Foundation/Domain/Event/*.php');

        $this->assertIsArray($files);

        $names = array_map(
            static fn (string $path): string => basename($path),
            $files,
        );

        $this->assertSame(['DomainEvent.php'], $names);
    }
}

/**
 * The reference instant every test in this file compares against.
 *
 * A plain native `\DateTimeImmutable` constructed explicitly in UTC — never
 * ambient current time, per the PF-043 test-strategy exclusions.
 */
function referenceInstant(): \DateTimeImmutable
{
    return new \DateTimeImmutable('2026-01-15T09:30:00', new \DateTimeZone('UTC'));
}

/**
 * A reference implementation in the approved `final readonly class` form.
 *
 * Test-only, declared here rather than in `app/Foundation`, so PF-043 creates
 * no concrete domain event of its own. Both the identifier and the instant
 * are supplied as constructor data — never generated or read ambient — and
 * `$payload` is an ordinary immutable value carried alongside them, used only
 * to demonstrate that identical non-identity payload data never makes two
 * events the same occurrence.
 */
final readonly class ReferenceTestEvent implements DomainEvent
{
    public function __construct(
        private UuidV7 $eventId,
        private \DateTimeImmutable $occurredAt,
        public string $payload,
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
