<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Infrastructure;

use App\Modules\PlatformAdministration\Application\Queries\FirmRegistryUnavailable;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\PostgreSqlFirmRegistry;
use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;

final class PostgreSqlFirmRegistryTest extends TestCase
{
    public function test_it_uses_a_bound_exact_identifier_and_returns_a_minimized_entry(): void
    {
        $firmId = FirmId::fromString('0198f42d-10b0-7000-8000-000000000001');
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('selectOne')->with(
            'select id, lifecycle_state from platform_administration_firm_registry where id = ?',
            [$firmId->toString()],
        )->willReturn((object) ['id' => $firmId->toString(), 'lifecycle_state' => 'Active']);

        $entry = (new PostgreSqlFirmRegistry($connection))->find($firmId);

        $this->assertNotNull($entry);
        $this->assertSame($firmId->toString(), $entry->firmId->toString());
        $this->assertSame('Active', $entry->lifecycleState->name);
    }

    public function test_it_fails_closed_without_disclosing_the_identifier(): void
    {
        $firmId = FirmId::fromString('0198f42d-10b0-7000-8000-000000000002');
        $connection = $this->createMock(Connection::class);
        $connection->method('selectOne')->willThrowException(new \RuntimeException('database failure'));

        try {
            (new PostgreSqlFirmRegistry($connection))->find($firmId);
            $this->fail('Expected registry exception.');
        } catch (FirmRegistryUnavailable $exception) {
            $this->assertSame('Firm registry is unavailable.', $exception->getMessage());
            $this->assertStringNotContainsString($firmId->toString(), $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_not_found_is_null_and_an_unknown_persisted_lifecycle_fails_closed(): void
    {
        $firmId = FirmId::fromString('0198f42d-10b0-7000-8000-000000000003');
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('selectOne')->willReturnOnConsecutiveCalls(
            null,
            (object) ['id' => $firmId->toString(), 'lifecycle_state' => 'Unknown'],
        );
        $registry = new PostgreSqlFirmRegistry($connection);

        $this->assertNull($registry->find($firmId));

        $this->expectException(FirmRegistryUnavailable::class);
        $this->expectExceptionMessage('Firm registry is unavailable.');
        $registry->find($firmId);
    }
}
