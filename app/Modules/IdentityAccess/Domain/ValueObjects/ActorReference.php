<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Domain\ValueObjects;

use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Model\ValueObject;

/** An immutable, audit-safe actor identity and provenance category (IA-001). */
final readonly class ActorReference implements ValueObject
{
    private function __construct(
        private BusinessIdentifier $identifier,
        private ActorCategory $category,
    ) {}

    public static function human(PrincipalId $id): self
    {
        return new self($id, ActorCategory::Human);
    }

    public static function service(BusinessIdentifier $id): self
    {
        return new self($id, ActorCategory::Service);
    }

    public static function system(BusinessIdentifier $id): self
    {
        return new self($id, ActorCategory::System);
    }

    public static function ai(BusinessIdentifier $id): self
    {
        return new self($id, ActorCategory::AI);
    }

    public function identifier(): BusinessIdentifier
    {
        return $this->identifier;
    }

    public function category(): ActorCategory
    {
        return $this->category;
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $this->category === $other->category
            && $this->identifier->equals($other->identifier);
    }
}
