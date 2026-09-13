<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Domain\ValueObjects;

use App\Foundation\Domain\Model\ValueObject;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;

/** One disjoint Firm or platform-operator identity realm (IA-001). */
final readonly class IdentityRealm implements ValueObject
{
    private function __construct(private ?FirmId $firmId) {}

    public static function forFirm(FirmId $firmId): self
    {
        return new self($firmId);
    }

    public static function platformOperator(): self
    {
        return new self(null);
    }

    public function isFirmRealm(): bool
    {
        return $this->firmId !== null;
    }

    public function firmId(): ?FirmId
    {
        return $this->firmId;
    }

    public function equals(ValueObject $other): bool
    {
        if (! $other instanceof self || $this->isFirmRealm() !== $other->isFirmRealm()) {
            return false;
        }

        return $this->firmId === null || $this->firmId->equals($other->firmId);
    }
}
