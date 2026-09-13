<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Domain\ValueObjects;

/** Provenance only; an actor category grants neither access nor authority (IA-001). */
enum ActorCategory
{
    case Human;
    case Service;
    case System;
    case AI;
}
