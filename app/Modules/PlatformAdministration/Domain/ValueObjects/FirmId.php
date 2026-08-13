<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\ValueObjects;

use App\Foundation\Domain\Identity\BusinessIdentifier;

/**
 * The identity of a `Firm` (PA-001).
 *
 * **An empty `final readonly` marker subclass, and deliberately nothing more.**
 * PF-044 records the standing rule this class follows: a concrete business
 * identifier adds **no state, no invariant, no constructor parameter, no
 * factory alias, and no behaviour**. Everything this type does — construction,
 * validation, canonicalization, equality, and canonical text — is
 * {@see BusinessIdentifier}'s, inherited whole and never extended. The class
 * body is empty because there is nothing correct to put in it.
 *
 * A `FirmId` is a UUIDv7 *with a type*, obtained only through the existing
 * Foundation contract — `FirmId::generate(UuidV7Generator $generator)` or
 * `FirmId::fromString(string $value)`. Validation is Foundation's `UuidV7`
 * validation and is not narrowed, widened, or supplemented here: no nil
 * rejection, no timestamp policy, no tenant policy, and no
 * PlatformAdministration-specific invariant.
 *
 * **This is *the* Firm identity every other bounded context means.** Because
 * {@see BusinessIdentifier::equals()} compares the exact runtime class before
 * it compares any value, a `FirmId` is never equal to another identifier type
 * wrapping the identical UUID, in either direction and without a
 * `\TypeError` — which is the whole reason a typed identifier exists.
 *
 * **It remains distinct from `FirmContext`'s use of an identifier.** PF-080
 * declares its Firm parameter as the abstract `BusinessIdentifier`, and
 * **narrowing that declaration to this type is a separate breaking-contract
 * follow-up requiring explicit human approval** — PA-001 neither performs,
 * requests, nor pre-approves it, and it migrates no existing test fixture.
 *
 * **A `FirmId` is not a secret and not a capability.** Possessing one proves
 * nothing and grants nothing; it is never a session token, credential, magic
 * link, recovery code, or proof of identity or membership. It does disclose
 * its own creation time to millisecond precision by construction, so it is
 * sensitive technical metadata: never place one in a URL parameter, a query
 * string, or any position routinely logged by intermediaries.
 *
 * **This type defines no persistence, serialization, or transport mapping.**
 * No database column type, cast, DTO, event payload shape, route binding, or
 * wire format is authorized here; those belong to `PA-007` and to later
 * approved stories.
 */
final readonly class FirmId extends BusinessIdentifier {}
