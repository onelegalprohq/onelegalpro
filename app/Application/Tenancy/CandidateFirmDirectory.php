<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Foundation\Domain\Identity\BusinessIdentifier;

interface CandidateFirmDirectory
{
    public function findCandidateFirmId(CandidateFirmReference $reference): ?BusinessIdentifier;
}
