<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Time;

/**
 * The production {@see Clock}, reading the host's current time (PF-047).
 *
 * Uses the PHP standard library only — no Laravel helper, no Carbon, no
 * package. The UTC timezone is passed explicitly to the constructor, so the
 * result never depends on PHP's ambient default timezone or on any framework
 * configuration having been applied first.
 *
 * Stateless and final: no constructor argument, no configuration, and no
 * mutable property, because a configurable timezone would create a supported
 * path to non-UTC output and defeat the one guarantee this type exists to
 * provide.
 */
final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(
            'now',
            new \DateTimeZone('UTC'),
        );
    }
}
