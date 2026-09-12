<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence;

use App\Modules\PlatformAdministration\Application\Queries\FindFirmRegistryEntry;
use App\Modules\PlatformAdministration\Application\Queries\FirmRegistryEntry;
use App\Modules\PlatformAdministration\Application\Queries\FirmRegistryUnavailable;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmLifecycleState;
use Illuminate\Database\Connection;

final readonly class PostgreSqlFirmRegistry implements FindFirmRegistryEntry
{
    public function __construct(private Connection $connection) {}

    public function find(FirmId $firmId): ?FirmRegistryEntry
    {
        try {
            $row = $this->connection->selectOne(
                'select id, lifecycle_state from platform_administration_firm_registry where id = ?',
                [$firmId->toString()],
            );
        } catch (\Throwable) {
            throw new FirmRegistryUnavailable;
        }

        if ($row === null) {
            return null;
        }

        return new FirmRegistryEntry(
            FirmId::fromString((string) $row->id),
            match ((string) $row->lifecycle_state) {
                'Requested' => FirmLifecycleState::Requested,
                'Provisioned' => FirmLifecycleState::Provisioned,
                'Active' => FirmLifecycleState::Active,
                'Closed' => FirmLifecycleState::Closed,
                default => throw new FirmRegistryUnavailable,
            },
        );
    }
}
