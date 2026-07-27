<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Identity;

use App\Foundation\Domain\Exception\InvariantViolation;
use Random\RandomException;

/**
 * The injectable source of new UUIDv7 values (PF-048).
 *
 * Domain code depends on this contract rather than calling a static factory, so
 * identifier creation becomes an explicit collaborator a caller supplies and a
 * test can substitute — the same discipline PF-047 established for time, and
 * for the same reason. Generation is deliberately **not** a static method on
 * {@see UuidV7}: a static factory would read ambient system time and ambient
 * randomness from inside a value object, which is untestable and contradicts
 * the Foundation rule that domain code never reads ambient system time.
 *
 * `generate()` takes no parameter, so an implementation is free to hold
 * whatever collaborators it needs — a PF-047 clock for
 * {@see SystemUuidV7Generator}, and potentially more for a future one. This
 * interface itself imports nothing for its own behaviour and names no time
 * source: the two symbols it does import appear only in the `@throws` contract
 * below.
 *
 * **This contract promises a valid, time-sortable identifier and nothing
 * further.** It does not promise monotonicity, same-millisecond ordering,
 * global or cross-process ordering, clock-rollback protection, or uniqueness as
 * anything stronger than a probabilistic property. Those non-guarantees belong
 * to {@see UuidV7} and are documented there in full; no implementation of this
 * interface may quietly strengthen them without its own approved story.
 *
 * Breaking changes to this published contract require explicit human approval.
 */
interface UuidV7Generator
{
    /**
     * A new UUIDv7.
     *
     * Each call returns a distinct value; nothing is cached, interned, or
     * reused.
     *
     * A `RandomException` means the platform's cryptographically secure random
     * source failed. It is deliberately **not** translated into the Foundation
     * taxonomy: a CSPRNG failure is a catastrophic environment failure, not a
     * domain condition, and it must not be swallowed by a handler that catches
     * `FoundationException` and carries on.
     *
     * An `InvariantViolation` means the implementation cannot honour this
     * contract — for example because its time source reported an instant a
     * version 7 timestamp field cannot represent.
     *
     * @throws RandomException
     * @throws InvariantViolation
     */
    public function generate(): UuidV7;
}
