<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Domain\ValueObjects;

use App\Foundation\Domain\Identity\BusinessIdentifier;

/** The typed identifier of one authenticated human principal (IA-001). */
final readonly class PrincipalId extends BusinessIdentifier {}
