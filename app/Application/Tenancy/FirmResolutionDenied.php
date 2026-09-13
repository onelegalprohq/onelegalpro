<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

final class FirmResolutionDenied extends \RuntimeException
{
    private const MESSAGE = 'Firm context could not be resolved.';

    private function __construct()
    {
        parent::__construct(self::MESSAGE);
    }

    public static function denied(): self
    {
        return new self;
    }
}
