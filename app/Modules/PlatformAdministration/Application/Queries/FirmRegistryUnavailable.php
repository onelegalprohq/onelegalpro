<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\Queries;

final class FirmRegistryUnavailable extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Firm registry is unavailable.');
    }
}
