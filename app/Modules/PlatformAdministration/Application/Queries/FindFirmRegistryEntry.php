<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\Queries;

use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;

interface FindFirmRegistryEntry
{
    public function find(FirmId $firmId): ?FirmRegistryEntry;
}
