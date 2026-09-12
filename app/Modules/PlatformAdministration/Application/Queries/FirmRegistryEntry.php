<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\Queries;

use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmLifecycleState;

final readonly class FirmRegistryEntry
{
    public function __construct(
        public FirmId $firmId,
        public FirmLifecycleState $lifecycleState,
    ) {}
}
