<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

enum CandidateFirmSourceKind
{
    case Hostname;
    case CustomDomain;
    case RoutePath;
    case RequestParameter;
    case RequestHeader;
    case Cookie;
    case RequestBody;
    case SubmittedEmailDomain;
}
