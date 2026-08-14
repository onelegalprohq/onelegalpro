# PlatformAdministration

**Standing convention record for the `PlatformAdministration` bounded context.**

Created by **PA-001 — Firm Aggregate Foundation**, the first `PlatformAdministration` story and the first code under `app/Modules`. It states what this context owns, what it may never contain, exactly what PA-001 delivered, and what remains `PA-002`–`PA-008`.

**It is a convention record, not the authoritative workflow-status source** — [`docs/PROJECT_STATUS.md`](../../../docs/PROJECT_STATUS.md) and [`docs/implementation/03_Engineering_Backlog.md`](../../../docs/implementation/03_Engineering_Backlog.md) remain authoritative for whether a given story is Ready, In Progress, Code Review, or Done.

## Ownership boundary

`PlatformAdministration` is a **narrowly bounded supporting context owning exactly three concepts — `Firm`, `FirmProvisioning`, and `SubscriptionEntitlement` — and nothing else.** It closes the ownership gap beneath the platform's tenancy model: the `Firm` record every other context references and none previously owned.

**Its narrowness is the rule, not an accident.** It never becomes a platform back office, support console, administrative impersonation feature, feature-flag system, reseller or partner hierarchy, usage-metering capability, or self-service signup — **each of those requires its own separately approved architecture, and adding a fourth concept to this context requires its own approved ADR.**

### What this context never does

- **It performs no authentication and makes no authorization decision.** It holds no credential, session, authenticator, recovery material, or invitation, and defines no operator access path. **Owning the `Firm` record is never access to what is inside the Firm.**
- **It never stores, reads, derives, aggregates, or exports a Firm's business data** — no `Client`, `Matter`, `MatterClient`, `MatterTeam`, `Task`, document, communication, or financial content.
- **It never performs cross-Firm reporting, analytics, benchmarking, or comparison**, and **no `PlatformAdministration` record, query, cache, projection, index, or event spans two Firms.** Every record concerns exactly one Firm.
- **It owns its own administrative audit facts and only those.** It never duplicates Practice Management's business history, IdentityAccess's security-event content, or any other context's audit records. Firm-visible support-access history is IdentityAccess's.
- **It never decides an Ethical Wall.** `CheckEthicalWallAccess` remains Practice Management's alone.
- **AI holds no authority here.** AI never creates, activates, or closes a `Firm`, never creates, changes, suspends, or restores an entitlement, and is never an authorization authority.

### Boundaries owned elsewhere

| Concern | Owner |
|---|---|
| Principals, memberships, authentication, sessions, credentials, invitations, recovery, roles, permissions, privileged access, security events | **IdentityAccess** |
| `FirmContext`, tenant resolution, tenant middleware | **Platform Foundation** (`PF-080`/`PF-081`/`PF-082`) |
| `Client`, `Matter`, `MatterClient`, `MatterTeam`, `Task`, Ethical Walls | **Practice Management** |
| Invoices, payments, ledgers, client money, `Money`, `Currency` | **Billing**, over Foundation `PF-045` |
| `BrandProfile`, `TenantDomain`, theme tokens | **Branding** |
| `Jurisdiction` as an authoritative record — its identifier contract, name, official languages, scope, and policy | **Legal Intelligence** |

`PlatformAdministration` reaches other modules only through their published contracts, and they reach it only through its own. No other module imports a `PlatformAdministration` persistence record, and this context imports none of theirs.

## What PA-001 delivered

Six framework-independent domain source files under `App\Modules\PlatformAdministration\Domain`, and this README. **Nothing else.**

| File | What it is |
|---|---|
| `Domain/ValueObjects/FirmId.php` | An empty `final readonly` marker subclass of Foundation's `BusinessIdentifier` — a UUIDv7 with a type. Adds no state, invariant, constructor parameter, factory alias, or behaviour; validation is inherited whole and never extended. |
| `Domain/ValueObjects/FirmName.php` | The canonical name. Trims, rejects any Unicode control character, applies Unicode NFC normalization, rejects an empty canonical result, and bounds length at 1–200 **code points**, so a Thai-script name is measured correctly. **Carries no uniqueness of any kind.** |
| `Domain/ValueObjects/FirmJurisdiction.php` | The **opaque** jurisdiction reference: uppercase ASCII `A`–`Z`, digits `0`–`9`, and non-leading, non-trailing, non-consecutive ASCII hyphens; length 2–32. **Not a country-code type and not restricted to two characters.** |
| `Domain/ValueObjects/FirmLifecycleState.php` | A pure enum with exactly the four approved cases `Requested`, `Provisioned`, `Active`, `Closed`. **Only `Requested` is reachable, and no transition method of any kind exists.** |
| `Domain/Aggregates/Firm.php` | `final class Firm extends AggregateRoot`, holding exactly name, jurisdiction, and lifecycle state beyond its inherited identity. One named creation constructor produces a `Requested` Firm and records exactly one `FirmCreated`. |
| `Domain/Events/FirmCreated.php` | `final readonly`, implementing exactly Foundation's `DomainEvent`. Receives its `UuidV7` and its plain native UTC `\DateTimeImmutable` as constructor data and generates neither. Payload is identifiers and immutable values only. |

Foundation primitives are **consumed unchanged, never duplicated**: `PF-040` `AggregateRoot`, `PF-041` `Entity`, `PF-042` `ValueObject`, `PF-043` `DomainEvent`, `PF-044` `BusinessIdentifier`, `PF-048` `UuidV7`, and the `PF-049` exception taxonomy. **No Foundation source or test was added or edited.** `PF-080` `FirmContext` and `PF-073` `FirmTransactionManager` are Done and deliberately **not** consumed.

### What PA-001 explicitly does not claim exists

**None of the following exists in this repository, and none of it is stubbed, approximated, simulated, partially implemented, or renamed:**

- **No registry.** The Firm registry relation — its schema, migration, privileges, access posture, and narrow query — does not exist. It is `PA-007`, whose contract is recorded but whose Definition of Ready is not met. The concrete `CandidateFirmDirectory` adapter is neither `PA-001`'s nor `PA-007`'s — it is **`PA-008`**, which follows `PF-081`.
- **No persistence of any kind.** No schema, migration, Eloquent record, repository contract, query contract, adapter, cast, DTO, or serialization.
- **No authorization.** No role, capability, permission, deny rule, authorization cache, or composed decision.
- **No provisioning.** No `FirmProvisioning`, no lifecycle transition, no activation path, and no self-service signup.
- **No audit persistence.** Recording a domain event into an in-memory buffer **is not an audit record and is not durable.** Durable administrative audit needs `PA-006` and `PF-091`.
- **No actor attribution.** No authoritative actor exists yet, and none is fabricated on the `Firm` or on `FirmCreated`.
- **No tenant isolation.** Application-layer resolution, Firm-scoped repositories, forced Row-Level Security, and executed PostgreSQL isolation tests are four independent required layers; **PA-001 delivers none of them.**
- **No entitlement.** No `SubscriptionEntitlement`, term, status, seat limit, or lapse semantics.
- **No service provider, container binding, route, controller, middleware, job, queue, cache, logger, metric, or trace**, and **no `ModuleServiceProvider.php`**.
- **Nothing is deployed, and no production-readiness claim is made.**

### Module structure — what exists and what does not

The standard module structure in [`docs/domain/06_Laravel_Module_Blueprint.md`](../../../docs/domain/06_Laravel_Module_Blueprint.md) is a **template, not an instruction to create empty directories.** Only `Domain/` exists today. `Application/`, `Infrastructure/`, `Interface/`, `Database/`, `Routes/`, `Config/`, an in-module `Tests/`, and `ModuleServiceProvider.php` are deliberately **absent** — `PF-060` Module Loader, `PF-061` Module Service Provider, and `PF-063` Module Registration are all `Backlog`, and creating any of them would pre-create another story's type.

## Conventions

- **`declare(strict_types=1);` is mandatory** in every source file and every test fixture.
- **The Domain layer is framework-independent.** Nothing under `App\Modules\PlatformAdministration\Domain` may reference Laravel, Illuminate, Eloquent, HTTP, queues, the service container, facades, configuration helpers, or vendor SDKs, and no Laravel global helper may be called. This is enforced by `tests/Unit/Modules/PlatformAdministration/Domain/PlatformAdministrationContractShapeTest.php`.
- **PHP global types take a leading backslash.**
- **Value objects and domain events are immutable**, `final readonly`, and canonicalize on the creation path only — never during comparison, so equality stays transitive.
- **Domain-rule violations throw through the `PF-049` taxonomy** — `InvalidArgument` for unacceptable supplied input, `InvariantViolation` for a broken domain guarantee.
- **Failures fail closed.** Nothing is defaulted, coerced, truncated, or approximated into validity, and no partially constructed object is ever observable.
- **No exception message, code, or property contains a rejected input value, a Firm identifier, a Firm name, a jurisdiction value, or any candidate value**, and no message is built from input. Messages are developer-facing diagnostics and are never exposed verbatim to an external caller.
- **Nothing is logged here**, and no metric, telemetry, or trace is emitted.
- **No test double belongs under `app/`.** Test fixtures are declared inside the test files that use them.
- **Pure unit tests live under `tests/Unit/Modules/PlatformAdministration/`**, mirror the source namespaces, and extend `PHPUnit\Framework\TestCase` directly, booting no Laravel application and touching no database.
- **PHPStan stays at level 5 with no baseline or suppression**, and **Pint stays the stock Laravel preset**.
- **Breaking changes to a published type here require explicit human approval.** Widening a published shape's bounds is additive; narrowing one is breaking.

## Standing rules this context inherits

- **A `FirmId` is not a secret and not a capability.** Possessing one proves nothing and grants nothing. It discloses its own creation time to millisecond precision by construction, so it is sensitive technical metadata and is never placed in a URL parameter, a query string, or any position routinely logged by intermediaries.
- **Uniqueness on a canonical Firm name is prohibited, permanently.** Unique-constraint checks run with row security suspended, so a global unique constraint on a law firm's name would return a violation to a caller who cannot see the conflicting row — an existence disclosure across a conflicts boundary. **The defence is the constraint's absence, not the caller's visibility.**
- **Registry readability is never membership**, and a candidate Firm reference grants nothing. A hostname, custom domain, route value, request parameter, header, cookie, request body, or submitted email domain identifies a **candidate** and nothing more — never an identifier, never proof.
- **There is no Firm-level suspended state**, and none may be invented. Entitlement lapse and membership revocation are different facts with different semantics, and neither is a Firm-wide disable.
- **Entitlement is not authorization** and is never a per-request authorization input.
- **A correction is a new recorded transition or fact, never a mutation of history.**

## Remaining stages

**`PA-001` and `PA-007` have contracts; `PA-008` has a scope outline; `PA-002` through `PA-006` have nothing.** `PA-001` is `Done`. `PA-007`'s contract is recorded in `docs/implementation/03_Engineering_Backlog.md` with its **Definition of Ready not met**, so it stays `Backlog`. `PA-002` through `PA-006` carry reserved identifiers and **no contract, no Definition of Ready, and no authorization** — assigning an identifier, or recording a contract or an outline, is a tracking record and never a schedule.

| Story | Scope | Status |
|---|---|---|
| `PA-002` | `Firm` lifecycle transitions — `Requested → Provisioned → Active → Closed` — with authorization, reason recording, and provisioning semantics. **It also owns the aggregate version and its conflict semantics**, which `PA-007` deliberately does not deliver | Reserved, no contract |
| `PA-003`–`PA-006` | The remaining approved `PlatformAdministration` stages, including `FirmProvisioning`, `SubscriptionEntitlement`, seat limits, and the append-only administrative audit relation (`PA-006`) | Reserved, no contract |
| `PA-007` | The Firm registry relation, its access posture and dedicated privileges, and its narrow named query — **read-only**, with no writer, no version column, and no idempotency record | Contract recorded; `Backlog`, Definition of Ready **not** met |
| `PA-008` | A thin adapter from `PA-007`'s narrow registry query to `PF-081`'s `CandidateFirmDirectory` port, sequenced **after** `PF-081` publishes it | Scope outline; `Backlog`, no full contract |

**`PA-007` and `PA-008` are deliberately not among the six approved stages, and neither adds a fourth concept** — this context still owns exactly `Firm`, `FirmProvisioning`, and `SubscriptionEntitlement`. `PA-007` is a database-design and security change requiring PostgreSQL role separation and dedicated privileges that do not exist in this repository, and it must never introduce a permissive `USING (true)` Row-Level Security policy — that would present as a control while permitting everything. Its Definition of Ready stays unmet until **`PF-074`** (PostgreSQL Runtime Role Separation) and **`PF-064`** (Module Migration Registration) are both `Done` — both are Platform Foundation's, not this context's, and they are **independent of each other and may proceed in parallel**. `PF-064` is one narrow capability, not three stories: registering each approved module's migration directory with Laravel's migrator. Its contract is recorded in `docs/implementation/03_Engineering_Backlog.md`.

**The `CandidateFirmDirectory` adapter is `PA-008`, not `PA-007`.** An adapter cannot precede the port it implements. Per the technical owner's decision of 13 August 2026, `PF-081` blocker **B4 gates operational completeness and integration, not `PF-081`'s implementation start**: `PF-081` may begin once B1, B2, and B3 are cleared, and must not be declared operationally complete or integrated until `PA-008` exists. `PF-081` acceptance criterion 14 is unchanged.

The named sequence is **`PA-001` (Done) → `PF-074` and `PF-064`, in parallel → `PA-007` → IdentityAccess EPIC-009 stages 1 and 2 → `PF-081` → `PA-008` → `PF-082`.**
