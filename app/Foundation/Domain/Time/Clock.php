<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Time;

/**
 * The injectable source of the current instant (PF-047).
 *
 * Domain code depends on this contract rather than reading ambient system
 * time, so time becomes an explicit collaborator a caller supplies and a test
 * can substitute — without coupling the domain to Laravel, HTTP, or
 * persistence.
 *
 * **Every implementation must return UTC.** The returned value carries an
 * explicit UTC timezone, never server-local time, so an instant is never
 * ambiguous across a daylight-saving transition. A Firm's office timezone, a
 * court-deadline timezone, and every other business-timezone meaning belong to
 * the owning business module, never to this contract.
 *
 * This is **wall-clock time**: the host's civil time, subject to correction,
 * which may therefore move backwards between two readings. It is not a
 * monotonic source and must never be used to measure elapsed duration. It is
 * also not evidence — the value is a reading of the current time, not
 * authenticated, tamper-evident, or proof of ordering.
 *
 * `Clock` is not a scheduler, timer, timeout or deadline system, recurrence
 * rule, business calendar, timezone-conversion service, or distributed
 * ordering guarantee.
 *
 * Breaking changes to this published contract require explicit human approval.
 */
interface Clock
{
    /**
     * The current instant, in UTC.
     *
     * Each call returns a new immutable value read at the moment of the call.
     */
    public function now(): \DateTimeImmutable;
}
