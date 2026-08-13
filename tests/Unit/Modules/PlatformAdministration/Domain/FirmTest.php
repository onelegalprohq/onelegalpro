<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Domain;

use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Domain\Model\AggregateRoot;
use App\Foundation\Domain\Model\Entity;
use App\Foundation\Domain\Time\Clock;
use App\Modules\PlatformAdministration\Domain\Aggregates\Firm;
use App\Modules\PlatformAdministration\Domain\Events\FirmCreated;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmJurisdiction;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmLifecycleState;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PA-001 — behavioural cover for the `Firm` aggregate and `FirmCreated`.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly, boots no Laravel
 * application, and touches no database. Its fixtures are declared at the foot
 * of this file, per the standing rule that no test double belongs under
 * `app/`.
 */
final class FirmTest extends TestCase
{
    private const FIRM_ID = '01920000-0000-7000-8000-000000000001';

    private const EVENT_ID = '01920000-0000-7000-8000-0000000000e1';

    // ------------------------------------------------------------- creation

    public function test_creation_yields_a_requested_firm(): void
    {
        $firm = $this->firm();

        $this->assertSame(FirmLifecycleState::Requested, $firm->lifecycleState());
    }

    /**
     * The supplied instances are preserved exactly — nothing is copied,
     * re-derived, re-canonicalized, or replaced.
     */
    public function test_creation_preserves_the_exact_supplied_values(): void
    {
        $id = FirmId::fromString(self::FIRM_ID);
        $name = FirmName::fromString('Sathorn Legal');
        $jurisdiction = FirmJurisdiction::fromString('TH');

        $firm = Firm::create($id, $name, $jurisdiction, UuidV7::fromString(self::EVENT_ID), $this->instant());

        $this->assertSame($id, $firm->id());
        $this->assertSame($name, $firm->name());
        $this->assertSame($jurisdiction, $firm->jurisdiction());
    }

    public function test_a_firm_is_an_aggregate_root_and_an_entity(): void
    {
        $firm = $this->firm();

        $this->assertInstanceOf(AggregateRoot::class, $firm);
        $this->assertInstanceOf(Entity::class, $firm);
    }

    /**
     * `Entity` fails latently when a subclass omits `parent::__construct()`:
     * the object constructs and only reading `id()` raises. Every concrete
     * entity therefore needs a test that constructs it and then reads `id()`.
     */
    public function test_identity_is_readable_immediately_after_construction(): void
    {
        $this->assertSame(self::FIRM_ID, $this->firm()->id()->toString());
    }

    public function test_a_firm_reads_no_ambient_time_or_randomness(): void
    {
        $instant = $this->instant();

        $firm = Firm::create(
            FirmId::fromString(self::FIRM_ID),
            FirmName::fromString('Sathorn Legal'),
            FirmJurisdiction::fromString('TH'),
            UuidV7::fromString(self::EVENT_ID),
            $instant,
        );

        $events = $firm->releaseEvents();

        $this->assertInstanceOf(FirmCreated::class, $events[0]);
        $this->assertSame($instant, $events[0]->occurredAt());
        $this->assertSame(self::EVENT_ID, $events[0]->eventId()->toString());
    }

    // --------------------------------------------------------------- events

    public function test_creation_records_exactly_one_firm_created_event(): void
    {
        $events = $this->firm()->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(FirmCreated::class, $events[0]);
    }

    public function test_the_recorded_event_carries_the_same_values_as_the_firm(): void
    {
        $id = FirmId::fromString(self::FIRM_ID);
        $name = FirmName::fromString('สำนักงานกฎหมายสาทร');
        $jurisdiction = FirmJurisdiction::fromString('TH-BKK');

        $firm = Firm::create($id, $name, $jurisdiction, UuidV7::fromString(self::EVENT_ID), $this->instant());
        $event = $firm->releaseEvents()[0];

        $this->assertInstanceOf(FirmCreated::class, $event);
        $this->assertSame($id, $event->firmId());
        $this->assertSame($name, $event->firmName());
        $this->assertSame($jurisdiction, $event->firmJurisdiction());
        $this->assertSame(FirmLifecycleState::Requested, $event->lifecycleState());
    }

    /**
     * Release is a transfer of the whole batch, not a copy of it.
     */
    public function test_releasing_events_twice_returns_the_batch_once_and_then_nothing(): void
    {
        $firm = $this->firm();

        $this->assertCount(1, $firm->releaseEvents());
        $this->assertSame([], $firm->releaseEvents());
        $this->assertSame([], $firm->releaseEvents());
    }

    public function test_two_firms_never_share_an_event_buffer(): void
    {
        $first = $this->firm();
        $second = $this->firm();

        $this->assertCount(1, $first->releaseEvents());
        $this->assertCount(1, $second->releaseEvents(), 'Releasing one aggregate\'s batch must not empty another\'s.');
    }

    // ----------------------------------------------------------- identity

    public function test_two_firms_are_the_same_aggregate_only_when_their_identifiers_match(): void
    {
        $first = $this->firm();
        $same = Firm::create(
            FirmId::fromString(self::FIRM_ID),
            FirmName::fromString('A Completely Different Name'),
            FirmJurisdiction::fromString('SG'),
            UuidV7::fromString(self::EVENT_ID),
            $this->instant(),
        );
        $other = Firm::create(
            FirmId::fromString('01920000-0000-7000-8000-000000000002'),
            FirmName::fromString('Sathorn Legal'),
            FirmJurisdiction::fromString('TH'),
            UuidV7::fromString(self::EVENT_ID),
            $this->instant(),
        );

        $this->assertTrue($first->sameIdentityAs($same), 'Attribute drift never changes which aggregate this is.');
        $this->assertTrue($same->sameIdentityAs($first));
        $this->assertFalse($first->sameIdentityAs($other));
        $this->assertFalse($other->sameIdentityAs($first));
    }

    /**
     * A differently typed entity is not the same entity, and comparing them
     * never raises a `\TypeError`.
     */
    public function test_a_firm_is_never_the_same_entity_as_a_foreign_entity_type(): void
    {
        $firm = $this->firm();
        $foreign = ForeignTestEntity::withIdentity(ForeignTestIdentifier::fromString(self::FIRM_ID));

        $this->assertFalse($firm->sameIdentityAs($foreign));
        $this->assertFalse($foreign->sameIdentityAs($firm));
    }

    // -------------------------------------------------------- FirmCreated

    public function test_the_event_instant_is_a_plain_native_date_time_immutable_in_utc(): void
    {
        $event = $this->firm()->releaseEvents()[0];

        $this->assertInstanceOf(FirmCreated::class, $event);
        $this->assertSame(\DateTimeImmutable::class, $event->occurredAt()::class);
        $this->assertSame(0, $event->occurredAt()->getOffset());

        // The offset assertion above is necessary but not sufficient: a zero
        // offset is a property of an instant, not of a zone. The timezone
        // *identity* is what the contract requires.
        $timezone = $event->occurredAt()->getTimezone();

        $this->assertInstanceOf(\DateTimeZone::class, $timezone);
        $this->assertSame('UTC', $timezone->getName());
    }

    public function test_the_canonical_utc_timezone_identity_is_accepted(): void
    {
        $event = $this->event(new \DateTimeImmutable('2026-08-12T09:00:00', new \DateTimeZone('UTC')));

        $timezone = $event->occurredAt()->getTimezone();

        $this->assertInstanceOf(\DateTimeZone::class, $timezone);
        $this->assertSame('UTC', $timezone->getName());
    }

    /**
     * Foundation's PF-047 `SystemClock` builds its instant with
     * `new \DateTimeZone('UTC')`, so the rule below must accept exactly what
     * that clock produces. This asserts the timezone identity only and reads
     * no instant value, so it cannot depend on the wall clock.
     */
    public function test_the_instant_shape_the_foundation_system_clock_produces_is_accepted(): void
    {
        $event = $this->event(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $timezone = $event->occurredAt()->getTimezone();

        $this->assertInstanceOf(\DateTimeZone::class, $timezone);
        $this->assertSame('UTC', $timezone->getName());
    }

    /**
     * Zones that are **not** UTC, including several whose offset is zero.
     *
     * A zero offset is not UTC. `Europe/London` carries a zero offset every
     * winter and `+01:00` every summer, so an offset-based rule would accept
     * the same caller's zone in January and refuse it in July; the two entries
     * below assert that neither is accepted. `GMT`, `Z`, `Etc/UTC`, and a fixed
     * `+00:00` denote the same instant but are distinct timezone identities,
     * and the accepted contract names UTC alone.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedEventTimezones(): iterable
    {
        yield 'Europe/London in winter, when its offset is zero' => ['Europe/London', '2026-01-15T09:00:00'];
        yield 'Europe/London in summer' => ['Europe/London', '2026-08-12T09:00:00'];
        yield 'Atlantic/Reykjavik, zero offset all year' => ['Atlantic/Reykjavik', '2026-08-12T09:00:00'];
        yield 'Africa/Abidjan, zero offset all year' => ['Africa/Abidjan', '2026-08-12T09:00:00'];
        yield 'the GMT identity' => ['GMT', '2026-08-12T09:00:00'];
        yield 'the Z identity' => ['Z', '2026-08-12T09:00:00'];
        yield 'a fixed +00:00 offset identity' => ['+00:00', '2026-08-12T09:00:00'];
        yield 'the Etc/UTC link identity' => ['Etc/UTC', '2026-08-12T09:00:00'];
        yield 'a non-zero-offset zone' => ['Asia/Bangkok', '2026-08-12T16:00:00'];
    }

    /**
     * @param  string  $zone  a timezone identity the event must refuse
     * @param  string  $when  a local time in that zone
     */
    #[DataProvider('rejectedEventTimezones')]
    public function test_only_the_utc_timezone_identity_is_accepted(string $zone, string $when): void
    {
        $this->expectException(InvalidArgument::class);

        $this->event(new \DateTimeImmutable($when, new \DateTimeZone($zone)));
    }

    /**
     * Rejection never converts. Nothing is normalized into UTC, and no message
     * carries the refused zone or any payload value.
     *
     * @param  string  $zone  a timezone identity the event must refuse
     * @param  string  $when  a local time in that zone
     */
    #[DataProvider('rejectedEventTimezones')]
    public function test_a_refused_timezone_is_rejected_without_conversion_or_disclosure(string $zone, string $when): void
    {
        try {
            $this->event(new \DateTimeImmutable($when, new \DateTimeZone($zone)));

            $this->fail('Only the UTC timezone identity may be accepted.');
        } catch (InvalidArgument $exception) {
            $message = $exception->getMessage();

            // A one-character identity such as `Z` occurs incidentally in
            // ordinary prose, so asserting its absence would test the wording
            // rather than the disclosure rule — the same discipline the value
            // object suite records.
            if (mb_strlen($zone, 'UTF-8') > 1) {
                $this->assertStringNotContainsString($zone, $message);
            }

            $this->assertStringNotContainsString($when, $message);
            $this->assertStringNotContainsString('Sathorn', $message);
            $this->assertStringNotContainsString(self::FIRM_ID, $message);
        }
    }

    /**
     * The interface cannot enforce this — return-type covariance would let a
     * framework subclass satisfy it — so the concrete event rejects one.
     */
    public function test_a_framework_date_time_subclass_is_rejected_rather_than_converted(): void
    {
        $this->expectException(InvalidArgument::class);

        new FirmCreated(
            eventId: UuidV7::fromString(self::EVENT_ID),
            occurredAt: new TestDateTimeSubclass('2026-08-12T09:00:00', new \DateTimeZone('UTC')),
            firmId: FirmId::fromString(self::FIRM_ID),
            firmName: FirmName::fromString('Sathorn Legal'),
            firmJurisdiction: FirmJurisdiction::fromString('TH'),
            lifecycleState: FirmLifecycleState::Requested,
        );
    }

    public function test_a_non_utc_instant_is_rejected_rather_than_converted(): void
    {
        $this->expectException(InvalidArgument::class);

        new FirmCreated(
            eventId: UuidV7::fromString(self::EVENT_ID),
            occurredAt: new \DateTimeImmutable('2026-08-12T16:00:00', new \DateTimeZone('Asia/Bangkok')),
            firmId: FirmId::fromString(self::FIRM_ID),
            firmName: FirmName::fromString('Sathorn Legal'),
            firmJurisdiction: FirmJurisdiction::fromString('TH'),
            lifecycleState: FirmLifecycleState::Requested,
        );
    }

    /**
     * No rejected instant, name, identifier, or jurisdiction value leaks
     * through a message.
     */
    public function test_no_event_rejection_message_contains_the_rejected_input(): void
    {
        try {
            new FirmCreated(
                eventId: UuidV7::fromString(self::EVENT_ID),
                occurredAt: new \DateTimeImmutable('2026-08-12T16:00:00', new \DateTimeZone('Asia/Bangkok')),
                firmId: FirmId::fromString(self::FIRM_ID),
                firmName: FirmName::fromString('Sathorn Legal'),
                firmJurisdiction: FirmJurisdiction::fromString('TH'),
                lifecycleState: FirmLifecycleState::Requested,
            );

            $this->fail('A non-UTC instant must be rejected.');
        } catch (InvalidArgument $exception) {
            $message = $exception->getMessage();

            $this->assertStringNotContainsString('Bangkok', $message);
            $this->assertStringNotContainsString('Sathorn', $message);
            $this->assertStringNotContainsString(self::FIRM_ID, $message);
            $this->assertStringNotContainsString('2026-08-12', $message);
        }
    }

    // ------------------------------------------------------------ fixtures

    /**
     * A `FirmCreated` differing from the fixture only in its instant, so a
     * timezone assertion isolates the one variable it is about.
     */
    private function event(\DateTimeImmutable $occurredAt): FirmCreated
    {
        return new FirmCreated(
            eventId: UuidV7::fromString(self::EVENT_ID),
            occurredAt: $occurredAt,
            firmId: FirmId::fromString(self::FIRM_ID),
            firmName: FirmName::fromString('Sathorn Legal'),
            firmJurisdiction: FirmJurisdiction::fromString('TH'),
            lifecycleState: FirmLifecycleState::Requested,
        );
    }

    private function firm(): Firm
    {
        return Firm::create(
            FirmId::fromString(self::FIRM_ID),
            FirmName::fromString('Sathorn Legal'),
            FirmJurisdiction::fromString('TH'),
            UuidV7::fromString(self::EVENT_ID),
            $this->instant(),
        );
    }

    /**
     * A fixed instant from a fixed clock, so nothing in this suite depends on
     * the wall clock.
     */
    private function instant(): \DateTimeImmutable
    {
        return (new FixedTestClock(new \DateTimeImmutable('2026-08-12T09:00:00', new \DateTimeZone('UTC'))))->now();
    }
}

/**
 * A `Clock` returning one fixed UTC instant, so no test reads ambient time.
 */
final readonly class FixedTestClock implements Clock
{
    public function __construct(private \DateTimeImmutable $instant) {}

    public function now(): \DateTimeImmutable
    {
        return $this->instant;
    }
}

/**
 * A business identifier belonging to no real context, used to prove that a
 * `Firm` is never the same entity as something else carrying the same UUID.
 */
final readonly class ForeignTestIdentifier extends BusinessIdentifier {}

/**
 * An entity belonging to no real context, for the cross-type identity check.
 *
 * @extends Entity<ForeignTestIdentifier>
 */
final class ForeignTestEntity extends Entity
{
    public static function withIdentity(ForeignTestIdentifier $id): self
    {
        return new self($id);
    }
}

/**
 * A `\DateTimeImmutable` subclass standing in for `Carbon\CarbonImmutable`,
 * proving the event rejects a subclass rather than accepting it through
 * `instanceof`.
 */
final class TestDateTimeSubclass extends \DateTimeImmutable {}
