<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Domain\ValueObjects;

use App\Foundation\Domain\Exception\InvalidArgument;
use App\Foundation\Domain\Model\ValueObject;

final readonly class MembershipVerification implements ValueObject
{
    private function __construct(private \DateTimeImmutable $verifiedAt) {}

    public static function recordedAt(\DateTimeImmutable $verifiedAt): self
    {
        if ($verifiedAt->getTimezone()->getName() !== 'UTC') {
            throw new InvalidArgument('Membership verification must use UTC.');
        }

        return new self($verifiedAt);
    }

    public function verifiedAt(): \DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->verifiedAt == $other->verifiedAt;
    }
}
