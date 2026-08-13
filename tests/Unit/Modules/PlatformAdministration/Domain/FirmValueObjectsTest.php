<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Domain;

use App\Foundation\Domain\Exception\DomainException;
use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Domain\Identity\UuidV7Generator;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmJurisdiction;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PA-001 — behavioural cover for `FirmId`, `FirmName`, and `FirmJurisdiction`.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly, boots no Laravel
 * application, and touches no database. Its fixtures are declared at the foot
 * of this file, per the standing rule that no test double belongs under
 * `app/`.
 */
final class FirmValueObjectsTest extends TestCase
{
    private const FIRM_ID = '01920000-0000-7000-8000-000000000001';

    // ---------------------------------------------------------------- FirmId

    /**
     * Generation goes through the injected PF-048 generator and nothing else —
     * no ambient randomness, and the generator is not retained anywhere.
     */
    public function test_generation_uses_the_supplied_generator_exactly_once(): void
    {
        $generator = new ScriptedUuidV7Generator([UuidV7::fromString(self::FIRM_ID)]);

        $id = FirmId::generate($generator);

        $this->assertSame(1, $generator->calls);
        $this->assertSame(self::FIRM_ID, $id->toString());
    }

    public function test_a_firm_id_round_trips_through_its_canonical_text(): void
    {
        $id = FirmId::fromString(self::FIRM_ID);

        $this->assertSame(self::FIRM_ID, $id->toString());
        $this->assertTrue($id->equals(FirmId::fromString($id->toString())));
    }

    public function test_a_firm_id_canonicalizes_mixed_case_input(): void
    {
        $upper = FirmId::fromString('01920000-0000-7000-8000-00000000ABCD');
        $lower = FirmId::fromString('01920000-0000-7000-8000-00000000abcd');

        $this->assertSame('01920000-0000-7000-8000-00000000abcd', $upper->toString());
        $this->assertTrue($upper->equals($lower));
        $this->assertTrue($lower->equals($upper));
    }

    /**
     * The whole reason a typed identifier exists: another identifier type
     * wrapping the identical UUID is never equal, in either direction, and the
     * comparison never raises a `\TypeError`.
     */
    public function test_a_firm_id_never_equals_another_identifier_type_wrapping_the_same_uuid(): void
    {
        $firmId = FirmId::fromString(self::FIRM_ID);
        $foreign = ForeignValueObjectTestIdentifier::fromString(self::FIRM_ID);

        $this->assertFalse($firmId->equals($foreign));
        $this->assertFalse($foreign->equals($firmId));
        $this->assertFalse($firmId->equals(FirmName::fromString('Sathorn Legal')));
        $this->assertFalse($firmId->equals(FirmJurisdiction::fromString('TH')));
    }

    public function test_an_invalid_firm_id_is_rejected_without_echoing_the_input(): void
    {
        $rejected = 'not-a-uuid-at-all';

        try {
            FirmId::fromString($rejected);

            $this->fail('A malformed identifier must be rejected.');
        } catch (InvalidArgument $exception) {
            $this->assertStringNotContainsString($rejected, $exception->getMessage());
        }
    }

    // -------------------------------------------------------------- FirmName

    public function test_a_firm_name_preserves_its_canonical_value(): void
    {
        $this->assertSame('Sathorn Legal', FirmName::fromString('Sathorn Legal')->value());
    }

    public function test_a_firm_name_trims_surrounding_whitespace(): void
    {
        foreach (['  Sathorn Legal  ', "\tSathorn Legal\n", "\u{00A0}Sathorn Legal\u{3000}"] as $input) {
            $this->assertSame('Sathorn Legal', FirmName::fromString($input)->value(), 'Surrounding whitespace is trimmed.');
        }
    }

    public function test_a_firm_name_preserves_interior_spacing(): void
    {
        $this->assertSame('Sathorn  Legal', FirmName::fromString('  Sathorn  Legal  ')->value());
    }

    /**
     * NFC normalization: a decomposed sequence and its composed equivalent
     * denote the same name and must therefore compare equal.
     */
    public function test_a_firm_name_is_normalized_to_unicode_nfc(): void
    {
        $decomposed = FirmName::fromString("A\u{030A}ngstro\u{0308}m Legal");
        $composed = FirmName::fromString("\u{00C5}ngstr\u{00F6}m Legal");

        $this->assertSame($composed->value(), $decomposed->value());
        $this->assertTrue($decomposed->equals($composed));
        $this->assertTrue($composed->equals($decomposed));
    }

    public function test_a_firm_name_accepts_thai_script(): void
    {
        $name = FirmName::fromString('สำนักงานกฎหมายสาทร');

        $this->assertSame('สำนักงานกฎหมายสาทร', $name->value());
    }

    /**
     * The bound is code points, never bytes. A Thai name of exactly the
     * maximum length is many times that in bytes, and a byte count would
     * wrongly reject it.
     */
    public function test_the_length_bound_counts_code_points_and_not_bytes(): void
    {
        $thai = str_repeat('ก', FirmName::MAXIMUM_LENGTH);

        $this->assertSame(FirmName::MAXIMUM_LENGTH, mb_strlen($thai, 'UTF-8'));
        $this->assertGreaterThan(FirmName::MAXIMUM_LENGTH, \strlen($thai), 'The fixture must be longer in bytes than in code points.');
        $this->assertSame($thai, FirmName::fromString($thai)->value());
    }

    public function test_a_firm_name_accepts_both_published_length_boundaries(): void
    {
        $this->assertSame('A', FirmName::fromString(str_repeat('A', FirmName::MINIMUM_LENGTH))->value());

        $longest = str_repeat('A', FirmName::MAXIMUM_LENGTH);

        $this->assertSame($longest, FirmName::fromString($longest)->value());
    }

    public function test_a_firm_name_equality_compares_the_canonical_value_only(): void
    {
        $this->assertTrue(FirmName::fromString('  Sathorn Legal ')->equals(FirmName::fromString('Sathorn Legal')));
        $this->assertFalse(FirmName::fromString('Sathorn Legal')->equals(FirmName::fromString('sathorn legal')));
        $this->assertFalse(FirmName::fromString('Sathorn Legal')->equals(FirmJurisdiction::fromString('TH')));
        $this->assertFalse(FirmJurisdiction::fromString('TH')->equals(FirmName::fromString('TH')));
    }

    /**
     * Case is preserved: a Firm name is not case-folded, because collation and
     * locale-aware comparison belong to a later story.
     */
    public function test_a_firm_name_is_never_case_folded(): void
    {
        $this->assertSame('Sathorn Legal', FirmName::fromString('Sathorn Legal')->value());
        $this->assertSame('SATHORN LEGAL', FirmName::fromString('SATHORN LEGAL')->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedFirmNames(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
        yield 'tab and newline only' => ["\t\n"];
        yield 'ideographic space only' => ["\u{3000}"];
        yield 'interior null byte' => ["Sathorn\0Legal"];
        yield 'interior newline' => ["Sathorn\nLegal"];
        yield 'interior carriage return' => ["Sathorn\rLegal"];
        yield 'interior escape' => ["Sathorn\u{001B}Legal"];
        yield 'interior delete control' => ["Sathorn\u{007F}Legal"];
        yield 'one code point over the maximum' => [str_repeat('A', FirmName::MAXIMUM_LENGTH + 1)];
        yield 'one Thai code point over the maximum' => [str_repeat('ก', FirmName::MAXIMUM_LENGTH + 1)];
        yield 'invalid utf-8' => ["Sathorn \xC3\x28 Legal"];
    }

    /**
     * @param  string  $input  a value the canonical shape must refuse
     */
    #[DataProvider('rejectedFirmNames')]
    public function test_a_malformed_firm_name_is_rejected(string $input): void
    {
        $this->expectException(DomainException::class);

        FirmName::fromString($input);
    }

    /**
     * @param  string  $input  a value the canonical shape must refuse
     */
    #[DataProvider('rejectedFirmNames')]
    public function test_no_firm_name_rejection_message_contains_its_rejected_input(string $input): void
    {
        try {
            FirmName::fromString($input);

            $this->fail('The value must be rejected.');
        } catch (DomainException $exception) {
            $this->assertMessageWithholds($input, $exception);
        }
    }

    // ------------------------------------------------------ FirmJurisdiction

    /**
     * Acceptance is asserted shape by shape, deliberately, so that a future
     * reintroduction of a two-character or ISO country-code restriction fails
     * this suite rather than passing unnoticed.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function acceptedJurisdictions(): iterable
    {
        yield 'the seeded Thailand example' => ['TH', 'TH'];
        yield 'the two-character minimum' => ['SG', 'SG'];
        yield 'a hyphenated subnational code' => ['TH-BKK', 'TH-BKK'];
        yield 'a longer multi-segment code' => ['TH-BKK-CIVIL-COURT-01', 'TH-BKK-CIVIL-COURT-01'];
        yield 'a digit-bearing code' => ['TH10', 'TH10'];
        yield 'an all-digit code' => ['1234', '1234'];
        yield 'the thirty-two character maximum' => [str_repeat('A', 32), str_repeat('A', 32)];
        yield 'a thirty-two character hyphenated code' => ['ABCDEFGH-IJKLMNOP-QRSTUVWX-YZ012', 'ABCDEFGH-IJKLMNOP-QRSTUVWX-YZ012'];
        yield 'lowercase input' => ['th', 'TH'];
        yield 'mixed-case hyphenated input' => ['tH-bKk', 'TH-BKK'];
        yield 'surrounding whitespace' => ['  th-bkk  ', 'TH-BKK'];
        yield 'surrounding non-ascii whitespace' => ["\u{00A0}th\u{3000}", 'TH'];
    }

    /**
     * @param  string  $input  the supplied text
     * @param  string  $canonical  the canonical value it must produce
     */
    #[DataProvider('acceptedJurisdictions')]
    public function test_an_accepted_jurisdiction_canonicalizes_as_published(string $input, string $canonical): void
    {
        $this->assertSame($canonical, FirmJurisdiction::fromString($input)->value());
    }

    /**
     * `TH` is a valid example of the shape, never the universal shape — a
     * two-character value has no privileged status here.
     */
    public function test_the_jurisdiction_reference_is_not_a_country_code_type(): void
    {
        $this->assertSame('TH-BKK', FirmJurisdiction::fromString('TH-BKK')->value());
        $this->assertSame(32, \strlen(FirmJurisdiction::fromString(str_repeat('A', 32))->value()));
        $this->assertGreaterThan(2, FirmJurisdiction::MAXIMUM_LENGTH, 'The shape is not constrained to two characters.');
    }

    /**
     * Canonicalization happens on creation only. Values written differently
     * therefore compare equal, and the relation stays transitive.
     */
    public function test_jurisdiction_equality_compares_the_canonical_value_only(): void
    {
        $lower = FirmJurisdiction::fromString(' th-bkk ');
        $upper = FirmJurisdiction::fromString('TH-BKK');
        $mixed = FirmJurisdiction::fromString('Th-Bkk');

        $this->assertTrue($lower->equals($upper));
        $this->assertTrue($upper->equals($lower));
        $this->assertTrue($upper->equals($mixed));
        $this->assertTrue($lower->equals($mixed), 'Equality must stay transitive.');

        $this->assertFalse($upper->equals(FirmJurisdiction::fromString('TH')));
    }

    public function test_a_jurisdiction_is_never_equal_to_another_value_object_type(): void
    {
        $jurisdiction = FirmJurisdiction::fromString('TH');

        $this->assertFalse($jurisdiction->equals(FirmName::fromString('TH')));
        $this->assertFalse(FirmName::fromString('TH')->equals($jurisdiction));
        $this->assertFalse($jurisdiction->equals(FirmId::fromString(self::FIRM_ID)));
        $this->assertFalse(FirmId::fromString(self::FIRM_ID)->equals($jurisdiction));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedJurisdictions(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
        yield 'a single character' => ['T'];
        yield 'a single digit' => ['1'];
        yield 'thirty-three characters' => [str_repeat('A', 33)];
        yield 'a leading hyphen' => ['-TH'];
        yield 'a trailing hyphen' => ['TH-'];
        yield 'consecutive hyphens' => ['TH--BKK'];
        yield 'a hyphen only' => ['-'];
        yield 'hyphens only' => ['--'];
        yield 'interior whitespace' => ['TH BKK'];
        yield 'a full stop' => ['TH.BKK'];
        yield 'an underscore' => ['TH_BKK'];
        yield 'a slash' => ['TH/BKK'];
        yield 'a colon' => ['TH:BKK'];
        yield 'a non-ascii letter' => ['ไทย'];
        yield 'an accented latin letter' => ['TÜ'];
        yield 'a full-width latin letter' => ["\u{FF34}\u{FF28}"];
        yield 'invalid utf-8' => ["T\xC3\x28H"];
    }

    /**
     * @param  string  $input  a value the canonical shape must refuse
     */
    #[DataProvider('rejectedJurisdictions')]
    public function test_a_malformed_jurisdiction_is_rejected(string $input): void
    {
        $this->expectException(DomainException::class);

        FirmJurisdiction::fromString($input);
    }

    /**
     * @param  string  $input  a value the canonical shape must refuse
     */
    #[DataProvider('rejectedJurisdictions')]
    public function test_no_jurisdiction_rejection_message_contains_its_rejected_input(string $input): void
    {
        try {
            FirmJurisdiction::fromString($input);

            $this->fail('The value must be rejected.');
        } catch (DomainException $exception) {
            $this->assertMessageWithholds($input, $exception);
        }
    }

    /**
     * Whichever rule refused the value, the message must not carry it.
     *
     * Trivially short inputs are skipped, because a one-character fixture such
     * as `-` occurs incidentally in ordinary prose and asserting its absence
     * would test the wording rather than the disclosure rule.
     */
    private function assertMessageWithholds(string $input, DomainException $exception): void
    {
        $trimmed = trim($input);

        if (mb_strlen($trimmed, 'UTF-8') < 2) {
            $this->assertNotSame('', $exception->getMessage(), 'A rejection must still be diagnosable.');

            return;
        }

        $this->assertStringNotContainsString($trimmed, $exception->getMessage());
    }
}

/**
 * A business identifier belonging to no real context, used to prove that two
 * identifier types wrapping the same UUID are never equal.
 */
final readonly class ForeignValueObjectTestIdentifier extends BusinessIdentifier {}

/**
 * A `UuidV7Generator` returning a scripted sequence and counting its calls, so
 * generation is provably driven by the injected contract rather than by
 * ambient randomness.
 */
final class ScriptedUuidV7Generator implements UuidV7Generator
{
    public int $calls = 0;

    /**
     * @param  list<UuidV7>  $scripted  the values to return, in order
     */
    public function __construct(private array $scripted) {}

    public function generate(): UuidV7
    {
        $next = $this->scripted[$this->calls] ?? null;

        if ($next === null) {
            throw new \LogicException('The scripted generator was called more times than it was scripted for.');
        }

        $this->calls++;

        return $next;
    }
}
