<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

final readonly class CandidateFirmReference
{
    public function __construct(
        private CandidateFirmSourceKind $sourceKind,
        private string $unverifiedValue,
    ) {}

    public function sourceKind(): CandidateFirmSourceKind
    {
        return $this->sourceKind;
    }

    public function unverifiedValue(): string
    {
        return $this->unverifiedValue;
    }
}
