<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Domain\ValueObjects;

enum FirmMembershipLifecycleState
{
    case Invited;
    case Active;
    case Suspended;
    case Revoked;
}
