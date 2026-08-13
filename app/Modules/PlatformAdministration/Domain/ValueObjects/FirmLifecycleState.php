<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\ValueObjects;

/**
 * The approved `Firm` lifecycle vocabulary (PA-001).
 *
 * `Requested → Provisioned → Active → Closed`, with `Closed` terminal. The
 * complete approved vocabulary is declared here so that `PA-002` cannot invent
 * a different one and does not have to modify a published type in order to
 * implement the transitions it owns.
 *
 * **Only `Requested` is reachable in PA-001, and no transition method of any
 * kind exists** — no `transitionTo()`, `canTransitionTo()`, `next()`, guard,
 * map, or `is*` helper. That is **absence, not a stub**: nothing here is
 * renamed, simulated, defaulted, or partially implemented, and the rule *"a
 * Firm never becomes usable as a side effect"* is preserved **structurally**
 * rather than by convention — with no transition anywhere, no code path can
 * leave `Requested`.
 *
 * Every transition, together with its authorization, its reason recording, and
 * its provisioning semantics, is **`PA-002`'s**. The standing discipline those
 * later stories inherit is that **a correction is a new recorded transition or
 * fact, never a mutation of history**.
 *
 * **There is no Firm-level suspended state, and none may be invented.**
 * Neither entitlement lapse nor membership revocation is a Firm-wide disable:
 * the first is a commercial fact that blocks new authentication while existing
 * valid sessions run to their normal expiry, and the second is an individual
 * security event terminating one principal's sessions immediately. A future
 * Firm-level suspension or emergency-disable capability requires its own
 * separately approved decision defining the authorizing authority, session
 * effects, recovery, notification, queued-and-in-flight-work behaviour, and
 * audit semantics that distinguish it from both.
 *
 * **A pure enum, deliberately not backed.** A backing value would be a
 * persistence and wire-format decision, and PA-001 delivers no persistence and
 * no serialization; choosing one here would silently fix `PA-007`'s storage
 * representation on its behalf.
 *
 * **Holding a state is not authorization.** Knowing that a Firm is `Active`
 * grants nothing inside it, and this enum decides no access of any kind.
 */
enum FirmLifecycleState
{
    /**
     * A Firm is intended, with the commercial arrangement agreed **outside the
     * application**. The only state PA-001 can produce.
     */
    case Requested;

    /**
     * The `Firm` record and its initial entitlement exist; the Firm is **not
     * yet usable**. Reached only by `PA-002`, which does not exist.
     */
    case Provisioned;

    /**
     * The Firm is operational. Reached only by `PA-002`, which does not exist.
     */
    case Active;

    /**
     * Terminal. Closing a Firm never destroys the audit fact that it existed.
     * Reached only by `PA-002`, which does not exist.
     */
    case Closed;
}
