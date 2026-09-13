<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Foundation\Domain\Identity\BusinessIdentifier;

interface AuthenticatedActor
{
    public function actorId(): BusinessIdentifier;
}
