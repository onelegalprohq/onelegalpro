<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Application;

use App\Application\Tenancy\CandidateFirmReference;
use App\Application\Tenancy\CandidateFirmSourceKind;
use App\Modules\PlatformAdministration\Application\Queries\FindFirmRegistryEntry;
use App\Modules\PlatformAdministration\Application\Queries\FirmRegistryEntry;
use App\Modules\PlatformAdministration\Application\Queries\FirmRegistryUnavailable;
use App\Modules\PlatformAdministration\Application\Tenancy\OpaqueFirmIdentifierCandidateDirectory;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmLifecycleState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpaqueFirmIdentifierCandidateDirectoryTest extends TestCase
{
    private const CANONICAL_FIRM_ID = '019c92a6-3c5f-7abc-8def-0123456789ab';

    public function test_it_resolves_a_canonical_submitted_opaque_identifier_with_one_registry_query(): void
    {
        $firmId = FirmId::fromString(self::CANONICAL_FIRM_ID);
        $registry = new RecordingFirmRegistry(new FirmRegistryEntry($firmId, FirmLifecycleState::Active));
        $directory = new OpaqueFirmIdentifierCandidateDirectory($registry);

        $resolved = $directory->findCandidateFirmId(new CandidateFirmReference(
            CandidateFirmSourceKind::SubmittedOpaqueFirmIdentifier,
            self::CANONICAL_FIRM_ID,
        ));

        self::assertSame($firmId, $resolved);
        self::assertSame([self::CANONICAL_FIRM_ID], array_map(
            static fn (FirmId $query): string => $query->toString(),
            $registry->queries,
        ));
    }

    public function test_it_returns_null_for_an_unregistered_canonical_identifier_after_one_query(): void
    {
        $firmId = FirmId::fromString(self::CANONICAL_FIRM_ID);
        $registry = new RecordingFirmRegistry(null);
        $directory = new OpaqueFirmIdentifierCandidateDirectory($registry);

        self::assertNull($directory->findCandidateFirmId(new CandidateFirmReference(
            CandidateFirmSourceKind::SubmittedOpaqueFirmIdentifier,
            self::CANONICAL_FIRM_ID,
        )));
        self::assertSame([self::CANONICAL_FIRM_ID], array_map(
            static fn (FirmId $query): string => $query->toString(),
            $registry->queries,
        ));
    }

    public function test_it_does_not_query_for_an_unsupported_source_kind(): void
    {
        $registry = new RecordingFirmRegistry(null);
        $directory = new OpaqueFirmIdentifierCandidateDirectory($registry);

        self::assertNull($directory->findCandidateFirmId(new CandidateFirmReference(
            CandidateFirmSourceKind::Hostname,
            self::CANONICAL_FIRM_ID,
        )));
        self::assertSame([], $registry->queries);
    }

    #[DataProvider('invalidSubmittedIdentifiers')]
    public function test_it_does_not_query_for_a_malformed_or_noncanonical_submitted_identifier(string $identifier): void
    {
        $registry = new RecordingFirmRegistry(null);
        $directory = new OpaqueFirmIdentifierCandidateDirectory($registry);

        self::assertNull($directory->findCandidateFirmId(new CandidateFirmReference(
            CandidateFirmSourceKind::SubmittedOpaqueFirmIdentifier,
            $identifier,
        )));
        self::assertSame([], $registry->queries);
    }

    /** @return array<string, array{string}> */
    public static function invalidSubmittedIdentifiers(): array
    {
        return [
            'empty' => [''],
            'malformed' => ['not-a-uuid'],
            'uppercase' => [strtoupper(self::CANONICAL_FIRM_ID)],
        ];
    }

    public function test_it_propagates_registry_unavailability_unchanged(): void
    {
        $registry = new RecordingFirmRegistry(null, new FirmRegistryUnavailable);
        $directory = new OpaqueFirmIdentifierCandidateDirectory($registry);

        $this->expectExceptionObject(new FirmRegistryUnavailable);

        $directory->findCandidateFirmId(new CandidateFirmReference(
            CandidateFirmSourceKind::SubmittedOpaqueFirmIdentifier,
            self::CANONICAL_FIRM_ID,
        ));
    }
}

final class RecordingFirmRegistry implements FindFirmRegistryEntry
{
    /** @var list<FirmId> */
    public array $queries = [];

    public function __construct(
        private readonly ?FirmRegistryEntry $entry,
        private readonly ?FirmRegistryUnavailable $failure = null,
    ) {}

    public function find(FirmId $firmId): ?FirmRegistryEntry
    {
        $this->queries[] = $firmId;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->entry;
    }
}
