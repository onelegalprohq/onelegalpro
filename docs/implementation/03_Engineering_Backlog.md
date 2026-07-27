# IMP-003 — Engineering Backlog

**Status:** Active  
**Current Epic:** EPIC-001 Platform Foundation

## Story lifecycle

Backlog → Ready → In Progress → Code Review → Architecture Review → QA → Approved → Done

## Definition of Ready

Clear goal, identified owner, resolved dependencies, acceptance criteria, security implications, tests, and no architecture blocker.

# EPIC-001 — Platform Foundation

## Repository Bootstrap
- PF-001 Repository Structure — verify repository state
- PF-002 Git & Repository Standards — Done
- PF-003 Repository Documentation — Done

## Development Environment
- PF-010 Docker Development Environment — Done
- PF-011 Local Environment & Configuration — Done
- PF-012 Development Tooling — Done

## Code Quality
- PF-020 Laravel Pint — Done
- PF-021 PHPStan — Done
- PF-022 Rector, optional — Done (reviewed and deferred; not configured)
- PF-023 Git Hooks — Done

## CI/CD
- PF-030 GitHub Actions — Done
- PF-031 Quality Gates — Done
- PF-032 Security Scanning — Done

Repository-foundation tooling (PF-020 through PF-032) is complete. The `Protect main` ruleset now requires four GitHub Actions checks: `PHP Code Quality`, `Frontend Build`, `Application Tests`, and `Dependency Audit`.

## Foundation Library

The Foundation Library track has started. `PF-049`, `PF-047`, `PF-042`, and `PF-048` are **Done**; `PF-044` is **Next**. Every other story below remains Backlog — none is Ready, In Progress, or Done, and each still requires its own approved entry with a Definition of Ready before implementation begins.

- PF-040 AggregateRoot — Backlog
- PF-041 Entity — Backlog
- PF-042 ValueObject — **Done**
- PF-043 DomainEvent — Backlog
- PF-044 BusinessIdentifier — **Next** (follows PF-048 in the approved order)
- PF-045 Money — Backlog
- PF-046 Result — Backlog
- PF-047 Clock — **Done**
- PF-048 UUIDv7 — **Done**
- PF-049 Exception hierarchy — **Done**

### Approved Foundation execution order

The numeric `PF-040`–`PF-049` list is a **story catalogue, not an execution order**. The human-approved implementation order is:

**PF-049 → PF-047 → PF-042 → PF-048 → PF-044 → PF-041 → PF-043 → PF-040 → PF-045 → PF-046**

Nothing was renamed, renumbered, merged, split, or deleted. See `docs/implementation/01_Implementation_Sprint_Plan.md` for the dependency reasoning and `app/Foundation/README.md` for the standing Foundation conventions.

### Prohibited dependency directions (Foundation Library)

These hold for every story in this track and may not be reversed without explicit human approval:

- **Foundation exceptions must not depend on `BusinessIdentifier`.** The PF-049 taxonomy stays free of identifier types; PF-044 depends on PF-049, never the reverse.
- **`Result` must not extend `ValueObject`.** PF-046 is an outcome wrapper, not a domain value; it does not inherit PF-042.
- **Foundation primitives must not return `Result` instead of throwing for invariant violations.** A broken invariant throws a PF-049 exception. `Result` models expected, recoverable outcomes a caller is meant to branch on.

### PF-049 — Foundation Exception Hierarchy — Done

**Objective.** Establish the single framework-independent exception taxonomy used by Foundation primitives and extended by future module-domain exceptions, so a caller can catch every OneLegalPro domain failure through one contract without coupling the domain to Laravel, HTTP, or persistence.

**Dependencies.** None inside the Foundation Library — PF-049 is the first story in the approved order and depends on no other `PF-04x` story. It depends only on the completed repository-foundation tooling track (PF-020 through PF-032) for Pint, PHPStan, tests, and the required CI checks.

**Deliverables.**

- `App\Foundation\Domain\Exception\FoundationException` — interface, extends `\Throwable`, no members of its own.
- `App\Foundation\Domain\Exception\DomainException` — abstract class, extends `\RuntimeException`, implements `FoundationException`. Name deliberately retained (not `FoundationDomainException`); no custom constructor.
- `App\Foundation\Domain\Exception\InvariantViolation` — final, extends `DomainException`, no additional behavior.
- `App\Foundation\Domain\Exception\InvalidArgument` — final, extends `DomainException`, no additional behavior.
- `app/Foundation/README.md` — the standing Foundation convention record, created by this story as the first Foundation story.
- Two pure unit tests under `tests/Unit/Foundation/`.

**Allowed files.**

- Created: `app/Foundation/Domain/Exception/FoundationException.php`, `app/Foundation/Domain/Exception/DomainException.php`, `app/Foundation/Domain/Exception/InvariantViolation.php`, `app/Foundation/Domain/Exception/InvalidArgument.php`, `app/Foundation/README.md`, `tests/Unit/Foundation/Domain/Exception/FoundationExceptionHierarchyTest.php`, `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php`.
- Modified, for status and sequencing only: `docs/implementation/01_Implementation_Sprint_Plan.md`, `docs/implementation/03_Engineering_Backlog.md`, `docs/PROJECT_STATUS.md`.

**Forbidden files.** No dependency or lock file (`composer.json`, `composer.lock`, `package.json`, `package-lock.json`); no tooling configuration (`phpunit.xml`, `phpstan.neon.dist`, `pint.json`); no `.github/`, `.githooks/`, Docker, environment, or deployment file; no existing PHP source file; no `app/Modules`; no Dependabot branch.

**Acceptance criteria.**

- Exactly the four approved source types exist, with the approved inheritance, finality, and abstractness.
- Both concrete types are catchable through `FoundationException`.
- Nothing under `App\Foundation\Domain` references Laravel, Illuminate, Eloquent, HTTP, queues, the service container, facades, configuration helpers, or a vendor SDK.
- Every Foundation source file declares `strict_types=1` and sits in the namespace matching its path.
- PHP global types are referenced with a leading backslash.
- No static named constructor (for example `because()`), no serialization API, no error-code catalogue, and no `Result` integration.
- Pint, PHPStan (level 5, no baseline or suppression), and the test suite all pass.

**Tests.**

- `tests/Unit/Foundation/Domain/Exception/FoundationExceptionHierarchyTest.php` — proves `FoundationException` is an interface extending `\Throwable` and declares no members of its own; `DomainException` is abstract, extends `\RuntimeException`, and implements `FoundationException`; both concrete types are final, extend `DomainException`, and are catchable through `FoundationException`; constructor messages are preserved exactly; previous-exception chaining is preserved; neither concrete type extends the global `\DomainException`; no type implements `\JsonSerializable`; and the test itself boots no Laravel application.
- `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php` — recursively inspects every PHP source file under `app/Foundation` and proves each declares strict types, each namespace agrees with its path under `App\Foundation`, and none references a Laravel/Illuminate/Eloquent namespace or calls a Laravel global helper. It is a **denylist, not an allowlist**, so a future explicitly approved, domain-safe direct dependency may be permitted by its own story without rewriting the guard. It adds no test dependency and boots no Laravel application.

**Security requirements.**

- No exception type carries a tenant identifier, actor identity, credential, session data, client or matter content, or privileged narrative.
- Exception messages are documented as developer-facing and must never be exposed verbatim to an external caller.
- No authentication, authorization, tenancy, or Ethical Wall decision is introduced — no `FirmId`, `FirmContext`, actor, principal, or session semantics.
- No `AccessDenied`, `Unauthorized`, `Forbidden`, `NotFound`, `ConcurrencyConflict`, or `StaleAggregate` type is created.
- No existing security control or required check was weakened, renamed, bypassed, or removed. The four required `Protect main` checks retain their exact names: `PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit`.

**Documentation impact.** `app/Foundation/README.md` created; `docs/implementation/01_Implementation_Sprint_Plan.md`, `docs/implementation/03_Engineering_Backlog.md`, and `docs/PROJECT_STATUS.md` updated for status and sequencing only.

**Definition of Ready (met).** Goal clear and narrowly bounded to one taxonomy; owner identified (repository owner); dependencies resolved (none inside the Foundation Library); acceptance criteria stated above; security implications identified (developer-facing messages only, no tenancy or authorization semantics); tests specified; no architecture blocker — `app/Foundation` is the approved home for shared technical primitives per `docs/domain/06_Laravel_Module_Blueprint.md`.

**Definition of Done (met).** Acceptance criteria met; `composer pint`, `composer phpstan`, and `composer test` all pass with no baseline, suppression, or ignored error; security and architecture reviewed; documentation updated; no critical defect; human approval recorded on the pull request before merge.

**Explicitly not implemented by PF-049.** PF-040 through PF-048 remain unimplemented. No business module, no `app/Modules`, no production deployment, no new dependency, no exception handler/renderer/service provider/bootstrap registration, no logging, telemetry, audit, metrics, or reporting.

### PF-047 — Clock — Done

**Objective.** Establish the single framework-independent time abstraction Foundation primitives and future modules depend on, so domain code never reads ambient system time directly and every timestamp originates from one injectable contract that always returns an explicit UTC instant.

**Scope.** Foundation Domain time abstraction only. This story owns the `Clock` contract, the native UTC `SystemClock` implementation, its isolated unit tests, and the Foundation documentation recording both. Nothing else.

**Dependencies.** None inside the Foundation Library — `PF-047` is the second story in the approved order and is standalone; it depends on no other `PF-04x` story and references no PF-049 exception type. It depends only on the completed repository-foundation tooling track (PF-020 through PF-032) for Pint, PHPStan, tests, and the required CI checks, and on the standing conventions PF-049 recorded in `app/Foundation/README.md`.

**Deliverables.**

- `App\Foundation\Domain\Time\Clock` — interface. Exactly one public method, `now(): \DateTimeImmutable`. No constants, properties, constructor, trait, or additional method.
- `App\Foundation\Domain\Time\SystemClock` — final class, implements `Clock`, returns a newly created `\DateTimeImmutable` for the current instant in explicit UTC. No constructor argument, configuration, mutable property, or dependency.
- Two pure unit tests under `tests/Unit/Foundation/Domain/Time/`.
- `app/Foundation/README.md` updated for the newly implemented `App\Foundation\Domain\Time` namespace and the Clock conventions.

**Exact published contract.**

```php
interface Clock
{
    public function now(): \DateTimeImmutable;
}
```

`\DateTimeImmutable` deliberately rather than `\DateTimeInterface`: the wider interface is satisfied by the mutable `\DateTime`, which would let an implementation hand back an object a caller can mutate in place, contradicting the Foundation immutability convention.

**Allowed files.**

- Created: `app/Foundation/Domain/Time/Clock.php`, `app/Foundation/Domain/Time/SystemClock.php`, `tests/Unit/Foundation/Domain/Time/ClockTest.php`, `tests/Unit/Foundation/Domain/Time/SystemClockTest.php`.
- Modified: `app/Foundation/README.md` (Foundation convention record), and — for status and sequencing only — `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md`.

**Forbidden files.** No dependency or lock file (`composer.json`, `composer.lock`, `package.json`, `package-lock.json`); no tooling configuration (`phpunit.xml`, `phpstan.neon.dist`, `pint.json`); no `.github/`, `.githooks/`, Docker, environment, or deployment file; no Laravel configuration, bootstrap file, or service provider; no migration; no existing PHP source file, including the PF-049 exception classes; **no change to `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php`** — the PF-049 guard must pass unchanged; no `app/Modules`; no Dependabot branch. `docs/implementation/01_Implementation_Sprint_Plan.md` is not modified: its catalogue and approved order already record PF-047 correctly and it carries no per-story status field.

**Acceptance criteria.**

- Exactly the two approved source types exist, with the approved namespace, finality, and method signature.
- `Clock::now()` is declared exactly `public function now(): \DateTimeImmutable;` — not `\DateTimeInterface`, not nullable, with no parameter.
- `SystemClock::now()` returns a value whose timezone name is `UTC` and whose offset is `0`, and each call returns a fresh instance.
- The returned timezone and offset are unaffected by PHP's ambient default timezone.
- Native PHP standard library only. No Laravel global helper, Carbon, PSR-20, package, vendor SDK, configuration read, service-provider registration, or container binding.
- Nothing under `App\Foundation\Domain\Time` references Laravel, Illuminate, Eloquent, HTTP, queues, the service container, facades, or configuration helpers.
- Every new Foundation source file declares `strict_types=1` and sits in the namespace matching its path; PHP global types are referenced with a leading backslash.
- No scheduling, timer, timeout, deadline, recurrence, business-calendar, timezone-conversion, distributed-ordering, or monotonic-time behavior is added, and no such claim is documented.
- No tenant, Firm, actor, authentication, authorization, or session semantics are introduced.
- No production `FrozenClock`, `MutableClock`, or other test double is created.
- The PF-049 architecture guard passes **unchanged**.
- Pint, PHPStan (level 5, no baseline or suppression), the full test suite, `composer validate --strict`, and both dependency audits pass, and all four required `Protect main` checks (`PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit`) are green.

**Tests.**

- `tests/Unit/Foundation/Domain/Time/ClockTest.php` — proves by reflection that `Clock` is an interface declaring exactly one method; that the method is named `now`, is public, and takes no parameter; that its return type is exactly non-nullable `\DateTimeImmutable`; that the interface declares no constant; and that the test boots no Laravel application.
- `tests/Unit/Foundation/Domain/Time/SystemClockTest.php` — proves `SystemClock` is final and implements `Clock`; that `now()` returns a `\DateTimeImmutable` whose timezone name is `UTC` and whose offset is `0`; that separate calls return separate instances; that the returned instant is bracketed by native UTC readings taken immediately before and after the call; that changing PHP's ambient default timezone changes neither the returned timezone nor the offset, with the original restored in a `finally` block; and that the test boots no Laravel application.
- Both extend `PHPUnit\Framework\TestCase` directly, boot no Laravel application, and add no test dependency. Neither uses `sleep()`/`usleep()`, a fixed elapsed-time tolerance, a strict-increase assertion, a non-decreasing-successive-reading assertion, a nonzero-microseconds assertion, or a Laravel helper. **`Clock` is a wall clock, not a monotonic clock** — the host clock may be corrected backwards, so no test asserts that successive readings cannot decrease.

**Security requirements.**

- No tenant identifier, `FirmId`, `FirmContext`, actor, principal, or session semantics; no authentication, authorization, or Ethical Wall decision.
- No credential, secret, client or matter content, or privileged narrative is created, read, stored, or logged.
- Clock output is a source of the current time only. It is **not** trusted, authenticated, or tamper-evident input, and is never evidence, a non-repudiation artifact, or proof of ordering. Bounded clock-skew tolerance for session and token expiry remains IdentityAccess's concern per `docs/architecture/16_Identity_Security_Access_Control_Architecture.md`, not this primitive's.
- Explicit UTC output removes the daylight-saving ambiguity a server-local wall clock would introduce, consistent with the UTC transport rule in `docs/architecture/07_API_Standards.md`.
- Firm office timezones, court-deadline timezones, and any other business-timezone meaning stay with the owning business modules and are never added to this contract.
- No existing security control or required check is weakened, renamed, bypassed, or removed. The four required `Protect main` checks retain their exact names.

**Documentation impact.** `app/Foundation/README.md` updated for the implemented `App\Foundation\Domain\Time` namespace and the Clock conventions; `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md` updated for status and sequencing only.

**Definition of Ready (met).** Goal clear and narrowly bounded to one time abstraction; owner identified (repository owner); dependencies resolved (none — the story is standalone); exact contract approved and recorded above; acceptance criteria stated; security implications identified (no tenancy, authorization, or trusted-time semantics); tests specified; no architecture blocker — `App\Foundation\Domain\Time` is the namespace `app/Foundation/README.md` already reserves for PF-047, and the PF-049 guard already permits a `Clock::now()` method declaration while continuing to prohibit the `now()` global helper.

**Definition of Done (met).** Acceptance criteria met; `composer validate --strict`, `composer pint:test` (36 files), `composer phpstan` (level 5, no errors), `php artisan test` (41 passed, 193 assertions), `composer audit`, and `npm audit --audit-level=high` all passed with no baseline, suppression, or ignored error; the PF-049 architecture guard passed unchanged; security and architecture reviewed; documentation updated; no critical defect; human approval recorded on the pull request before merge.

**Explicitly not implemented by PF-047.** PF-040 through PF-046 and PF-048 remain unimplemented. No `FrozenClock`, `MutableClock`, or production test double. No container binding, service provider, or bootstrap registration — a future Laravel binding of `Clock` to `SystemClock` belongs to an approved Platform Runtime story (Sprint 0.4), not to this one. No PSR-20 adoption: `psr/clock` is present only transitively, and a transitive package authorizes nothing. No business module, no `app/Modules`, no production deployment, no new dependency, no logging, telemetry, audit, metrics, or reporting.

### PF-042 — ValueObject — Done

**Objective.** Establish the single framework-independent contract every OneLegalPro value object satisfies, so identifiers, monetary amounts, and module-owned values share one equality-by-value semantic instead of each type inventing its own — without imposing an inheritance, immutability, serialization, or transport obligation on any of them.

**Scope.** The Foundation Domain value-object contract only. This story owns the `ValueObject` interface, its isolated unit test, and the Foundation documentation recording the conventions. Nothing else. **It implements no value object.**

**Dependencies.** None inside the Foundation Library that block it — `PF-042` is the third story in the approved order. It references the PF-049 exception taxonomy in **documentation only**: no import, no code dependency, no `use` statement. It does not depend on PF-047. It depends on the completed repository-foundation tooling track (PF-020 through PF-032) for Pint, PHPStan, tests, and the required CI checks, and on the standing conventions PF-049 recorded in `app/Foundation/README.md`.

**Deliverables.**

- `App\Foundation\Domain\Model\ValueObject` — interface. Exactly one public method, `equals(ValueObject $other): bool`. No constant, property, constructor, trait, parent interface, or additional method.
- One pure unit test under `tests/Unit/Foundation/Domain/Model/`, with its two reference fixtures declared inside the test file itself.
- `app/Foundation/README.md` updated for the partially implemented `App\Foundation\Domain\Model` namespace and the value-object conventions.

**Exact published contract.**

```php
interface ValueObject
{
    public function equals(ValueObject $other): bool;
}
```

`ValueObject $other` deliberately rather than `self $other`: `self` inside an interface resolves to the interface, and because PHP parameter types are contravariant, an implementation that writes `self` is a fatal error. Naming the interface explicitly makes the signature copy-pasteable verbatim into every implementation.

**An interface deliberately rather than an abstract class.** PHP's `readonly` inheritance is viral and bidirectionally exclusive — a non-readonly class cannot extend a readonly class, and a readonly class cannot extend a non-readonly class **even when the parent declares no properties**. Any abstract-class base would therefore force one permanent, platform-wide choice: every value object must be `readonly`, or none ever may be. An `abstract readonly class` would additionally forbid static properties in every value object, foreclosing an interned-instance `Currency` for PF-045. An interface imposes neither constraint and leaves each value object's single `extends` slot free, which PF-044 `BusinessIdentifier` needs for its own abstract identifier base.

**Equality semantics the contract documents.** Equality is by value, never by identity, and is total, reflexive, symmetric, transitive, strict, and non-coercive. **Differently typed value objects are unequal, and a cross-class comparison returns `false` rather than throwing a `TypeError`.** Implementations must use **exact runtime-type** semantics. `$other instanceof self` achieves that for a **final leaf implementation**, because such a class cannot have subclasses — it is **not** a universally sufficient technique. Any future inheritable base that implements `equals()` on behalf of its subclasses — such as PF-044's possible `BusinessIdentifier` base — must compare the runtime classes explicitly, for example `$other::class === $this::class`, before comparing value state. Canonicalization belongs to the concrete value-object story, not to this contract, and caches and derived properties never participate in equality. **PF-042 supplies no production equality implementation.**

**Construction and invariants the contract documents.** Every approved creation or reconstitution path must enforce the concrete type's invariants, and a value object must never be observable in an invalid state. **Whether creation uses a public constructor, a named constructor, a factory, or another explicit mechanism is the concrete story's decision** — this contract requires none of them. Implementations report failure through the PF-049 taxonomy: `InvalidArgument` for an unacceptable supplied argument, `InvariantViolation` for a broken domain guarantee. **PF-042's interface itself throws nothing, and PF-042 adds no factory, `fromPrimitives()`, or reconstitution API.**

**Allowed files.**

- Created: `app/Foundation/Domain/Model/ValueObject.php`, `tests/Unit/Foundation/Domain/Model/ValueObjectTest.php`.
- Modified: `app/Foundation/README.md` (Foundation convention record), and — for status and sequencing only — `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md`.

**Forbidden files.** No dependency or lock file (`composer.json`, `composer.lock`, `package.json`, `package-lock.json`); no tooling configuration (`phpunit.xml`, `phpstan.neon.dist`, `pint.json`); no `.github/`, `.githooks/`, Docker, environment, or deployment file; no Laravel configuration, bootstrap file, or service provider; no migration; no existing PHP source file, including the PF-049 exception classes and the PF-047 `Clock`/`SystemClock`; **no change to `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php`** — the PF-049 guard must pass unchanged; no separate test-fixture file; no `README.md`, `AGENTS.md`, or `CONTRIBUTING.md`; no `app/Modules`; no Dependabot branch. `docs/implementation/01_Implementation_Sprint_Plan.md` is not modified: its catalogue and approved order already record PF-042 correctly and it carries no per-story status field — the same reasoning PF-047 applied.

**Acceptance criteria.**

- Exactly one approved source type exists, with the approved namespace, form, and method signature.
- `ValueObject::equals()` is declared exactly `public function equals(ValueObject $other): bool;` — one required, non-nullable, non-variadic, not-by-reference parameter with no default, and a non-nullable `bool` return.
- The interface declares no constant, no property, and no second method, and **extends nothing** — in particular neither `\Stringable` nor `\JsonSerializable`.
- The interface imposes no `readonly` constraint: a `final readonly class` and a plain `final class` can each implement it.
- No `notEquals`, `toPrimitives`, `fromPrimitives`, `toArray`, `jsonSerialize`, `__toString`, `hash`, `copy`, `with*`, `equalityComponents`, or validation helper is added.
- No abstract `ValueObject` class, no shared equality trait, no static factory, and no test double is created under `app/Foundation`.
- **No comparison logic is implemented in production code by this story**, and no reflection-based, serialization-based, or hash-based equality mechanism exists anywhere.
- Native PHP only. No Laravel global helper, Carbon, Symfony, Ramsey, PSR interface, package, vendor SDK, configuration read, service-provider registration, or container binding. No `composer.json` or lock-file change.
- Nothing under `App\Foundation\Domain\Model` references Laravel, Illuminate, Eloquent, HTTP, queues, the service container, facades, or configuration helpers.
- The new source file declares `strict_types=1` and sits in the namespace matching its path; PHP global types are referenced with a leading backslash.
- No tenant, `FirmId`, `FirmContext`, actor, principal, session, authentication, authorization, or Ethical Wall semantics are introduced.
- No serialization, persistence, mapping, transport, ordering, hashing, localization, or constant-time-comparison behavior is added, and no such claim is documented.
- The published documentation does not force a public constructor and does not forbid a future approved named constructor or factory.
- The PF-049 architecture guard passes **unchanged**.
- Pint, PHPStan (level 5, no baseline or suppression), the full test suite, `composer validate --strict`, and both dependency audits pass, and all four required `Protect main` checks (`PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit`) are green.

**Tests.**

- `tests/Unit/Foundation/Domain/Model/ValueObjectTest.php` — proves by reflection that `ValueObject` is an interface that extends no interface and declares exactly one method; that the method is named `equals`, is public and non-static, and takes exactly one parameter; that the parameter is required, non-nullable, not variadic, not passed by reference, has no default, and is typed exactly `App\Foundation\Domain\Model\ValueObject`; that the return type is exactly non-nullable `bool`; and that the interface declares no constant or property and extends neither `\Stringable` nor `\JsonSerializable`.
- **Two minimal reference implementations are declared inside the test file itself** — one `final readonly class` and one plain `final class` — proving both forms may implement the contract, which is the property the interface exists to preserve. No separate fixture file is created.
- Behavioral assertions against those reference implementations cover reflexivity; distinct instances holding the same value comparing equal while not being identical; different values comparing unequal; cross-class comparison returning `false` in **both** directions with no `TypeError`; string `'1'` not equalling integer `1`; and a write to the readonly fixture's property throwing `\Error`.
- **These behavioral assertions demonstrate the documented reference semantics.** A PHP interface cannot mechanically enforce each implementation's internal comparison algorithm, and this test makes no such claim. The fixtures are `final`, so `instanceof self` is exact-class-correct **for them**; that technique is not represented as universally sufficient for an inheritable class.
- The test extends `PHPUnit\Framework\TestCase` directly, boots no Laravel application, and adds no test dependency.

**Security requirements.**

- No tenant identifier, `FirmId`, `FirmContext`, actor, principal, or session semantics; no authentication, authorization, or Ethical Wall decision. A value object is never a capability or authorization token.
- No credential, secret, client or matter content, or privileged narrative is created, read, stored, or logged, and no confidential value appears in any exception message.
- **No `__toString`, `\Stringable`, `jsonSerialize`, `toArray`, `toPrimitives`, or `hash` on the contract**, so no value object becomes implicitly stringable, serializable, loggable, or safe for external transport. Whether a given type is any of those is decided by the type that owns it; external representation remains `Integrations`' concern per `docs/architecture/07_API_Standards.md` §2 and §19, and `app/Foundation/README.md` continues to prohibit a serialization API in Foundation Domain.
- **`equals()` is documented as not constant-time, and no timing guarantee is made.** Constant-time comparison remains type-specific: a value object holding secret or privileged material implements `equals()` with `hash_equals()` or an equivalent under its own explicitly approved story, and preferably does not retain retrievable secret material at all, consistent with `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §49.
- No reflection-, serialization-, or hash-based comparison is introduced, so no comparison reads private state, materializes secret values into strings, or leaks object-graph size through timing.
- Untrusted external input reaches a value object only through the owning module's Application layer; every approved creation path enforces the type's invariants, and this story adds no permissive factory, `fromPrimitives()`, or reconstitution path that could bypass them.
- No existing security control or required check is weakened, renamed, bypassed, or removed. The four required `Protect main` checks retain their exact names: `PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit`.

**Documentation impact.** `app/Foundation/README.md` updated: the namespace table and its accompanying note now record `Exception` and `Time` as implemented and `Model` as **partially** implemented — `ValueObject` only, with `Entity` (PF-041) and `AggregateRoot` (PF-040) still reservations — plus a new `Model` section recording the value-object conventions and exclusions. `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md` updated for status and sequencing only.

**Definition of Ready (met).** Goal clear and narrowly bounded to one contract; owner identified (repository owner); dependencies resolved (no blocking Foundation dependency — PF-049 is referenced in documentation only); exact contract approved and recorded above; acceptance criteria stated; security implications identified (no tenancy, authorization, serialization, stringification, or constant-time-comparison semantics); tests specified; no architecture blocker — `App\Foundation\Domain\Model` is the namespace `app/Foundation/README.md` already reserves for PF-042, and the PF-049 guard already permits an `equals()` method declaration.

**Definition of Done (met).** Acceptance criteria met; `composer validate --strict`, `composer pint:test`, `composer phpstan` (level 5, no errors), the full test suite, `composer audit`, and `npm audit --audit-level=high` all pass with no baseline, suppression, or ignored error; the PF-049 architecture guard passed unchanged; security and architecture reviewed; documentation updated; no critical defect; human approval recorded on the pull request before merge.

**Explicitly not implemented by PF-042.** PF-040, PF-041, PF-043, PF-044, PF-045, PF-046, and PF-048 remain unimplemented — **no `Entity`, `AggregateRoot`, `DomainEvent`, `BusinessIdentifier`, `Money`, `Currency`, `Result`, or `UuidV7`**, and no stub, placeholder, or empty directory for any of them. No abstract `ValueObject` class, no shared equality trait, no `equalityComponents()`, and no shared equality implementation — **that is deferred until real consumers demonstrate an identical reusable requirement**, and adding one then is additive rather than breaking. No serialization, persistence mapping, Eloquent cast, API DTO, ordering, hashing, or localization. No container binding, service provider, or bootstrap registration. No test double under `app/Foundation`. No business module, no `app/Modules`, no production deployment, no new dependency, no logging, telemetry, audit, metrics, or reporting.

### PF-048 — UUIDv7 — Done

**Objective.** Establish the single framework-independent UUIDv7 identifier primitive every OneLegalPro identifier is built from, so PF-044 `BusinessIdentifier` and every future module identifier share one validated, canonical, time-sortable value type and one injectable generation contract — instead of each inventing its own format, validation, and time source.

**Scope.** The Foundation Domain UUIDv7 primitive only: the `UuidV7` value object, the `UuidV7Generator` contract, the native `SystemUuidV7Generator` implementation, their isolated unit tests, and the Foundation documentation recording all of it. Nothing else. **It implements no `BusinessIdentifier` and no module identifier.**

**Dependencies.** Fourth in the approved order. Depends in **code** on **PF-042** (`UuidV7 implements ValueObject`), **PF-047** (`SystemUuidV7Generator` receives a `Clock`), and **PF-049** (`InvalidArgument`, `InvariantViolation`) — all Done. PF-048 is the first Foundation story to depend on an earlier one in code rather than only in documentation, which is why PF-042 and PF-047 were sequenced ahead of it. It further depends on the completed repository-foundation tooling track (PF-020 through PF-032) and on the standing conventions in `app/Foundation/README.md`. **No new dependency of any kind.**

**Deliverables.**

- `App\Foundation\Domain\Identity\UuidV7` — `final readonly class`, implements `ValueObject`. Private constructor; one static named constructor `fromString()`; `toString()`; `equals()`. No other public member.
- `App\Foundation\Domain\Identity\UuidV7Generator` — interface. Exactly one public method, `generate(): UuidV7`. No constant, property, constructor, trait, parent interface, or additional method.
- `App\Foundation\Domain\Identity\SystemUuidV7Generator` — `final class`, implements `UuidV7Generator`. One constructor parameter, `Clock $clock`. PHP standard library only.
- Three pure unit tests under `tests/Unit/Foundation/Domain/Identity/`, with all fixtures declared inside their own test files.
- `app/Foundation/README.md` updated for the partially implemented `App\Foundation\Domain\Identity` namespace and the identifier conventions.

**Exact published contracts.**

```php
final readonly class UuidV7 implements ValueObject
{
    private function __construct(private string $value) {}

    /** @throws \App\Foundation\Domain\Exception\InvalidArgument */
    public static function fromString(string $uuid): self;

    public function toString(): string;

    public function equals(ValueObject $other): bool;
}

interface UuidV7Generator
{
    /**
     * @throws \Random\RandomException
     * @throws \App\Foundation\Domain\Exception\InvariantViolation
     */
    public function generate(): UuidV7;
}

final class SystemUuidV7Generator implements UuidV7Generator
{
    public function __construct(private readonly Clock $clock) {}

    public function generate(): UuidV7;
}
```

**A native implementation deliberately, rather than a library.** `ramsey/uuid 4.9.3` and `symfony/uid v8.1.0` both support UUIDv7 and are both installed, but **only transitively via `laravel/framework`**, and a transitive dependency authorizes nothing — the same rule PF-047 applied when declining PSR-20 and Carbon. Their one real advantage, same-millisecond monotonic increment, holds only within a single process and could not be promised platform-wide anyway; and `ramsey/uuid`'s parser is lenient (it accepts brace-wrapped, `urn:uuid:`, unhyphenated, nil, wrong-version, and wrong-variant input), so a wrapper would implement strict validation regardless. Because generation sits behind `UuidV7Generator`, adopting a library later is **additive**, not breaking. `Illuminate\Support\Str::uuid7()` is prohibited: `Illuminate\` is on the PF-049 guard's denylist, and it honours the global mutable `Str::$uuidFactory` hook, which would let unrelated framework test state change a Foundation primitive's output.

**Generation is a separate injectable contract, deliberately.** Not a static on the value object: `UuidV7::generate()` would read ambient time and ambient randomness inside a value object, contradicting the PF-047 rule that domain code never reads ambient system time, and making the timestamp bits untestable. The generator receives a `Clock` by constructor injection so `generate()` stays parameterless, keeping a future stateful or monotonic implementation unconstrained. **No separate randomness abstraction was introduced** — a substitutable randomness source is a security footgun, and every property that matters is provable without controlling the random bytes.

**Time semantics — the published wording.** A UUIDv7 is **time-sortable**, and that is the only term this story's code and documentation use for it. Specifically:

- Values whose encoded millisecond timestamps differ **sort according to those timestamps**, both lexically as canonical strings and bytewise.
- **Values created within the same millisecond have no defined relative order.**
- **No monotonicity is promised**, in any scope — the generator holds no counter, sequence, or last-seen timestamp.
- **Clock rollback is permitted and unguarded.** `Clock` is a wall clock that may be corrected backwards, so a value created later may sort before one created earlier; nothing detects, corrects, or reports that.
- **No global, cross-process, or cross-host ordering is promised.** There is no coordination, no shared state, and no node identifier.

**Timestamp range.** RFC 9562 §5.7 defines `unix_ts_ms` as a **48-bit unsigned** count of Unix milliseconds, so the representable range is `0` through `281474976710655` inclusive. `SystemUuidV7Generator` **explicitly verifies the injected clock's instant against that inclusive range and throws `InvariantViolation` when it falls outside.** Range checking and timestamp assembly use **integer arithmetic only** — `((int) $instant->format('U')) * 1000 + ((int) $instant->format('v'))` — with **no floating-point timestamp arithmetic anywhere**, because a float cannot hold the whole range exactly and invites a boundary off-by-one. An out-of-range instant is **never truncated, wrapped, clamped, or reinterpreted**: each of those would return a syntactically valid UUID carrying a silently wrong timestamp, which is the worst available outcome. The six timestamp bytes are produced with `pack('J', …)`, which states big-endian explicitly, so assembly never depends on host byte order.

**Allowed files.**

- Created: `app/Foundation/Domain/Identity/UuidV7.php`, `app/Foundation/Domain/Identity/UuidV7Generator.php`, `app/Foundation/Domain/Identity/SystemUuidV7Generator.php`, `tests/Unit/Foundation/Domain/Identity/UuidV7Test.php`, `tests/Unit/Foundation/Domain/Identity/UuidV7GeneratorTest.php`, `tests/Unit/Foundation/Domain/Identity/SystemUuidV7GeneratorTest.php`.
- Modified: `app/Foundation/README.md` (Foundation convention record), and — for status and sequencing only — `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md`.

**Forbidden files.** No dependency or lock file (`composer.json`, `composer.lock`, `package.json`, `package-lock.json`); no tooling configuration (`phpunit.xml`, `phpstan.neon.dist`, `pint.json`); no `.github/`, `.githooks/`, Docker, environment, or deployment file; no Laravel configuration, bootstrap file, service provider, or container binding; no migration; no existing PHP source file, including the PF-049 exception classes, the PF-047 `Clock`/`SystemClock`, and the PF-042 `ValueObject`; **no change to `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php`** — the PF-049 guard must pass unchanged; no change to any other existing Foundation test; no separate test-fixture file; no `README.md`, `AGENTS.md`, or `CONTRIBUTING.md`; no architecture or ADR file; no `app/Modules`; no Dependabot branch. `docs/implementation/01_Implementation_Sprint_Plan.md` is not modified: its catalogue and approved order already record PF-048 correctly and it carries no per-story status field — the same reasoning PF-047 and PF-042 applied.

**Acceptance criteria.**

- Exactly the three approved source types exist, with the approved namespace, finality, readonly-ness, and signatures.
- `UuidV7` is `final readonly`, implements `ValueObject`, and implements nothing else — in particular neither `\Stringable` nor `\JsonSerializable`.
- Its constructor is **private**; `fromString()` is the only path from text.
- Every reachable instance is a valid RFC 9562 version 7 UUID with variant `0b10`; `toString()` always returns canonical lowercase hyphenated form.
- `fromString()` rejects malformed text, non-hexadecimal characters, wrong length, misplaced or absent hyphens, brace-wrapped and `urn:uuid:` forms, surrounding whitespace, the nil and max UUIDs, every non-7 version, and every non-RFC variant — each with `InvalidArgument`. Hexadecimal is accepted in either case and normalised to lowercase on the creation path.
- No exception message contains the rejected input or the offending instant.
- `equals()` matches the PF-042 signature exactly and uses exact runtime type; cross-class comparison returns `false` rather than throwing.
- `UuidV7Generator` declares exactly one method, `generate(): UuidV7`, and nothing else.
- `SystemUuidV7Generator` is final, takes exactly one `Clock` constructor parameter, reads no ambient time, and declares no static property and no mutable state.
- The 48-bit timestamp range is verified with integer arithmetic and violations raise `InvariantViolation`; no value is produced for an unencodable instant.
- The random bits come from `random_bytes()`. No `mt_rand`, `rand`, `uniqid`, `str_shuffle`, `array_rand`, or seedable engine appears anywhere.
- No `__toString`, `\Stringable`, `jsonSerialize`, `toArray`, `toBytes`, `fromBytes`, `timestamp`, `compareTo`, `isBefore`, `nil`, `max`, `hash`, `with*`, or `notEquals` is added.
- No monotonicity, exactly-once generation, collision-impossibility, global-ordering, or index-performance behaviour is added, and **no such claim is documented**. **"Time-sortable" is the term used throughout**, in source, tests, and documentation alike; the stronger ordering wording it replaces appears nowhere.
- Native PHP only. No Laravel global helper, Carbon, Symfony, Ramsey, PSR interface, package, or vendor SDK. No vendor object appears in any published signature. No `composer.json` or lock-file change.
- Nothing under `App\Foundation\Domain\Identity` references Laravel, Illuminate, Eloquent, HTTP, queues, the service container, facades, or configuration helpers.
- Every new source file declares `strict_types=1` and sits in the namespace matching its path; PHP global types take a leading backslash.
- No tenant, `FirmId`, `FirmContext`, actor, principal, session, authentication, authorization, or Ethical Wall semantics are introduced.
- No serialization, persistence mapping, Eloquent cast, API DTO, or localization behaviour is added.
- No `BusinessIdentifier`, module identifier, container binding, service provider, or test double is created under `app/Foundation`.
- The PF-049 architecture guard passes **unchanged**.
- Pint, PHPStan (level 5, no baseline or suppression), the full test suite, `composer validate --strict`, and both dependency audits pass, and all four required `Protect main` checks (`PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit`) retain their exact names.

**Tests.**

- `tests/Unit/Foundation/Domain/Identity/UuidV7Test.php` — proves by reflection that the class is `final` and `readonly`, implements `ValueObject` and nothing else, is neither `\Stringable` nor `\JsonSerializable`, has a **private** constructor, declares exactly `equals`, `fromString`, and `toString` as public methods, and declares none of nineteen individually named prohibited or deferred members. Pins each signature exactly. Behavioural coverage: canonical round trip; canonical output pattern; version nibble `7`; every RFC variant nibble accepted; uppercase and mixed-case input normalised to lowercase; case never affecting equality, proving canonicalisation is on the creation path; a **39-case rejection matrix** covering empty and whitespace-only input, leading/trailing/internal whitespace, a trailing newline, arbitrary text, off-by-one lengths, a non-hexadecimal character, misplaced and absent hyphens, brace-wrapped and `urn:uuid:` forms, the nil and max UUIDs, versions 0–6, 8 and f, and all twelve non-RFC variant nibbles; rejection catchable through `InvalidArgument`, `DomainException`, `FoundationException`, and `\RuntimeException`; the rejected input absent from the message for four distinct inputs; reflexive, symmetric equality; distinct-but-equal instances; cross-class comparison `false` in both directions with no `\TypeError`; and reassignment refused even through reflection, isolating `readonly` from mere privacy.
- `tests/Unit/Foundation/Domain/Identity/UuidV7GeneratorTest.php` — proves by reflection that `UuidV7Generator` is an interface extending no interface, declaring exactly one method named `generate` that is public, non-static, takes no parameter, and returns exactly non-nullable `UuidV7`, with no constant and no property.
- `tests/Unit/Foundation/Domain/Identity/SystemUuidV7GeneratorTest.php` — proves the class is final, implements the contract, takes exactly one required non-nullable `Clock`, and **declares no static property**. Behavioural coverage: canonical output with version and variant bits; a generated value round-tripping through `fromString()`; the leading 48 bits equalling the clock instant at seven points including **minimum `0`** (`000000000000`) and **maximum `281474976710655`** (`ffffffffffff`), each expected prefix computed independently of the implementation's arithmetic; milliseconds rather than seconds encoded; **five out-of-range instants rejected with `InvariantViolation`**, including one below the minimum, one above the maximum, and far-out values in both directions; that rejection catchable through the Foundation taxonomy; the offending instant absent from the message; **no value produced at all for an unencodable instant**, proving nothing is truncated, wrapped, or clamped; the injected clock used rather than ambient time; ambient default timezone irrelevant, with the original restored in `finally`; 1000 generations at one fixed instant all distinct, **documented as demonstrating that the random bits vary and explicitly not as a collision-impossibility proof**; values at one instant sharing a timestamp while differing in their random bits; four values from four different milliseconds sorting by their timestamps; **clock rollback permitted, with the later-generated value asserted to sort earlier**, pinning the non-guarantee so a future silent introduction of monotonic state fails here; and two generators sharing one clock producing independent values.
- All three extend `PHPUnit\Framework\TestCase` directly, boot no Laravel application, and add no test dependency. The fixed-time `FixedClock` and sequence-driven `ScriptedClock` fixtures are declared inside `SystemUuidV7GeneratorTest.php`, and the foreign `ValueObject` fixture inside `UuidV7Test.php`; no separate fixture file exists. **No test uses `sleep()`, `usleep()`, `hrtime()`, a real elapsed-time tolerance, a statistical randomness claim, or an ordering assertion between two values generated in the same millisecond.**

**Security requirements.**

- **UUIDs are identifiers, not secrets**, and never an authorization decision. Possession grants nothing.
- **A UUID never replaces Firm isolation, Ethical Walls, or access checks.** `CheckEthicalWallAccess` remains Practice Management's sole authority; `FirmContext` derives only from verified identity and membership.
- **A UUIDv7 exposes its approximate creation timestamp** to anyone holding it. Owning modules and `Integrations` decide deliberately what to expose externally; `docs/architecture/07_API_Standards.md` §3 and §5 already require identifiers to be opaque externally and never a basis for enumeration or ordering assumptions.
- **No Firm, actor, client, matter, or privileged content is encoded** — timestamp bits and CSPRNG bits only. UUIDv7 has **no node field**, so no host identity leaks.
- **Cryptographically secure randomness is mandatory** for the random portion; `random_bytes()` is the only source used.
- **Never a session token, API credential, magic link, recovery code, webhook secret, capability, or proof of identity.** The 48-bit prefix is fully predictable by construction.
- Exception messages are developer-facing diagnostics and never echo the rejected input or the offending instant, and never carry a tenant identifier, actor identity, or client or matter content.
- No existing security control or required check was weakened, renamed, bypassed, or removed. The four required `Protect main` checks retain their exact names.

**Documentation impact.** `app/Foundation/README.md`: the namespace table and its accompanying note now record `Identity` as **partially implemented** — `UuidV7`, `UuidV7Generator`, and `SystemUuidV7Generator` only, with **`BusinessIdentifier` (PF-044) still a reservation that does not exist** — plus a new `Identity` section recording the UUIDv7 conventions, the time-sortability wording, the timestamp-range rule, the security rules, and the exclusions. The `External dependencies` section now records that PF-048 introduced no dependency and deliberately declined `ramsey/uuid` and `symfony/uid` as transitive-only. `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md` updated for status and sequencing only.

**Definition of Ready (met).** Goal clear and narrowly bounded to one identifier primitive; owner identified (repository owner); dependencies resolved (PF-042, PF-047, PF-049 all Done); dependency strategy analysed and approved with no new dependency; exact contracts approved and recorded above; acceptance criteria stated; semantic guarantees **and** explicit non-guarantees stated, including the mandated time-sortable wording and the 48-bit range rule; security implications identified; exception mapping onto the existing PF-049 taxonomy settled with no new exception type; tests specified; allowed and forbidden files enumerated; no architecture blocker — `App\Foundation\Domain\Identity` is the namespace `app/Foundation/README.md` already reserves, and the PF-049 guard is a denylist that permits these three files unchanged.

**Definition of Done (met).** Acceptance criteria met; `composer validate --strict`, `composer pint:test` (47 files), `composer phpstan` (level 5, no errors), the full test suite (192 passed, 530 assertions), `composer audit --locked --abandoned=report`, and `npm audit --audit-level=high` all pass with no baseline, suppression, or ignored error; the PF-049 architecture guard passed unchanged; security and architecture reviewed; documentation updated; no critical defect; human approval recorded on the pull request before merge.

**Explicitly not implemented by PF-048.** **PF-044 `BusinessIdentifier` remains unimplemented and becomes next only after PF-048 is approved and merged.** PF-040, PF-041, PF-043, PF-045, and PF-046 also remain unimplemented — no `Entity`, `AggregateRoot`, `DomainEvent`, `BusinessIdentifier`, `Money`, `Currency`, or `Result`, and no stub, placeholder, or empty directory for any of them. No monotonic generator, no clock-rollback guard, no counter, sequence, or node identifier. No timestamp extraction, ordering, comparison, hashing, byte-level accessor, nil/max factory, serialization, persistence mapping, Eloquent cast, or API DTO. No `__toString` or `\Stringable`. No container binding, service provider, or bootstrap registration — a future binding of `UuidV7Generator` to `SystemUuidV7Generator` belongs to an approved Platform Runtime story (Sprint 0.4). No test double under `app/Foundation`. No ULID or alternative identifier format. No database column type, index strategy, or storage decision. No business module, no `app/Modules`, no production deployment, no new dependency, no logging, telemetry, audit, metrics, or reporting.

## Module Infrastructure
- PF-060 through PF-063

## Application Framework
- PF-070 through PF-073

## Multi-Tenant Foundation
- PF-080 through PF-082

## Event Infrastructure
- PF-090 through PF-093

## Testing Infrastructure
- PF-100 through PF-104

## Standard story requirements

Objective, dependencies, deliverables, acceptance criteria, allowed and forbidden files, tests, security, documentation, and Definition of Done.

## Architecture track (parallel to EPIC-001, does not renumber PF-* stories)

- **ARCH-001 — Thailand-First Legal Intelligence Architecture — Done (architecture approved).** Populated `docs/architecture/01_OneLegalPro_Constitution.md`, `docs/architecture/05_AI_Architecture.md`, `docs/architecture/08_Roadmap.md`; created `docs/adr/ADR-002-Thailand-First-Legal-Intelligence.md` (Accepted) and `docs/architecture/09_Legal_Intelligence_Architecture.md`.
- **ARCH-002 — White-Label Platform & Multi-Tenant Branding Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 10–11), `docs/architecture/08_Roadmap.md` (EPIC-003); created `docs/adr/ADR-003-White-Label-Platform.md` (Accepted) and `docs/architecture/10_White_Label_Platform_Architecture.md`. `docs/architecture/02_Product_Requirements.md` and `docs/architecture/04_Security_Architecture.md` remain empty placeholders, consistent with the ARCH-001 precedent.
- **ARCH-003 — Communications Hub Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 12–13), `docs/architecture/05_AI_Architecture.md` (Communications Hub AI), `docs/architecture/08_Roadmap.md` (EPIC-004); created `docs/adr/ADR-004-Communications-Hub.md` (Accepted) and `docs/architecture/11_Communications_Hub_Architecture.md`. `docs/architecture/02_Product_Requirements.md` and `docs/architecture/04_Security_Architecture.md` remain empty placeholders, consistent with the ARCH-001 precedent.
- **ARCH-004 — Website & Client Portal (Digital Presence Platform) Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 14–15), `docs/architecture/08_Roadmap.md` (EPIC-005); created `docs/adr/ADR-005-Website-Client-Portal.md` (Accepted) and `docs/architecture/12_Website_Client_Portal_Architecture.md`. Consolidates the previously unarchitected "Digital Presence" and "Client Portal" later-phase names into one bounded context. `docs/architecture/02_Product_Requirements.md`, `docs/architecture/04_Security_Architecture.md`, and `docs/architecture/05_AI_Architecture.md` were not modified — the embedded AI receptionist reuses ARCH-003's AI governance unchanged.
- **ARCH-005 — Practice Management Core Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 16–17), `docs/architecture/08_Roadmap.md` (EPIC-006); created `docs/adr/ADR-006-Practice-Management-Core.md` (Accepted) and `docs/architecture/13_Practice_Management_Architecture.md`. Resolves the "future Practice Management module" dependency named by ARCH-003 and ARCH-004, and consolidates the previously unarchitected "Legal Practice Core" later-phase name into one bounded context. `docs/architecture/02_Product_Requirements.md`, `docs/architecture/04_Security_Architecture.md`, and `docs/architecture/05_AI_Architecture.md` remain untouched, same precedent as ARCH-004.
- **ARCH-006 — Document & Knowledge Management Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 18–21), `docs/architecture/05_AI_Architecture.md` (Document Intelligence AI, Knowledge Intelligence and RAG), `docs/architecture/08_Roadmap.md` (EPIC-007); created `docs/adr/ADR-007-Document-Knowledge-Management.md` (Accepted) and `docs/architecture/14_Document_Knowledge_Management_Architecture.md`. Resolves the "future Documents bounded context" dependency named by ARCH-003 (attachment extraction), ARCH-004 (Client Portal Documents surface, Document Upload Widget), and ARCH-005 (Matter Timeline, Matter Dashboard), and consolidates the previously unarchitected "Documents" later-phase name into one bounded context. `docs/architecture/02_Product_Requirements.md`, `docs/architecture/03_Database_Design.md`, `docs/architecture/04_Security_Architecture.md`, `docs/architecture/06_Marketplace.md`, and `docs/architecture/07_API_Standards.md` remain empty placeholders, consistent with the ARCH-001 precedent. The story ID is ARCH-006; the architecture document is numbered 14 (`ARCH-014`), continuing the existing document-number sequence.
- **ARCH-007 — Billing, Trust Accounting & Finance Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 22–25), `docs/architecture/05_AI_Architecture.md` (Financial AI), `docs/architecture/08_Roadmap.md` (EPIC-008); created `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md` (Accepted) and `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md`. Defines one bounded context with three explicitly separated financial domain areas — Billing/Accounts Receivable, Client Money/Trust Accounting, and Firm Finance/Accounting. Resolves the "future Billing bounded context" dependency named by ARCH-003 (Invoice linking), ARCH-004 (Client Portal Invoices/Payments, Payment Widget), ARCH-005 (Matter Timeline, Matter Dashboard billing panels), and ARCH-006 (rendered invoice artifact ownership), and consolidates the previously unarchitected "Commercial Operations" later-phase name into one bounded context. `docs/architecture/02_Product_Requirements.md`, `docs/architecture/03_Database_Design.md`, `docs/architecture/04_Security_Architecture.md`, `docs/architecture/06_Marketplace.md`, and `docs/architecture/07_API_Standards.md` remain empty placeholders, consistent with the ARCH-001 precedent. The story ID is ARCH-007; the architecture document is numbered 15 (`ARCH-015`), continuing the existing document-number sequence.
- **ARCH-008 — Identity, Security & Access Control Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 26–30), `docs/architecture/08_Roadmap.md` (EPIC-009); created `docs/adr/ADR-009-Identity-Security-Access-Control.md` (Accepted) and `docs/architecture/16_Identity_Security_Access_Control_Architecture.md`; **populated `docs/architecture/04_Security_Architecture.md`** as the platform-wide security baseline. Establishes `IdentityAccess` as a separate bounded context while keeping security cross-cutting, and resolves the "future Identity/Security" dependency named by ARCH-004 (Client Portal authentication), ARCH-005 (Practice Management actor references), ARCH-006 (Documents/Knowledge actor references), and ARCH-007 (Billing actor identity, permissions, and segregation of duties). Surgically corrects the Digital Presence authentication-ownership boundary in `docs/adr/ADR-005-Website-Client-Portal.md` and `docs/architecture/12_Website_Client_Portal_Architecture.md` without removing any approved Client Portal capability. `docs/architecture/05_AI_Architecture.md` was **not** modified — existing AI-governance rules are preserved and no new AI capability is introduced. `docs/architecture/02_Product_Requirements.md`, `docs/architecture/03_Database_Design.md`, `docs/architecture/06_Marketplace.md`, and `docs/architecture/07_API_Standards.md` remain empty placeholders (API standards reserved for ARCH-009). The story ID is ARCH-008; the architecture document is numbered 16 (`ARCH-016`), continuing the existing document-number sequence.
- **ARCH-009 — API & Integration Platform Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 31–37), `docs/architecture/08_Roadmap.md` (EPIC-010); created `docs/adr/ADR-010-API-Integration-Platform.md` (Accepted) and `docs/architecture/17_API_Integration_Platform_Architecture.md`; **populated `docs/architecture/07_API_Standards.md`** as the platform-wide normative API standard. Establishes `Integrations` as a supporting bounded context that translates stable external contracts into owning modules' commands/queries without absorbing domain business rules, and resolves the "reserved for ARCH-009" Public API and Integration Platform dependency named by ARCH-004 (Digital Presence Public/Embedded APIs) and ARCH-008 (IdentityAccess service-principal API-authentication foundation), alongside Communications' provider adapters, Billing's payment-provider webhook boundaries, and Documents' secure file delegation. IdentityAccess remains the sole owner of authentication and service-principal credentials; Practice Management remains the sole Ethical Wall authority. Workflow orchestration is now governed by ARCH-010/ARCH-018 and the proposed EPIC-011 (below) — `Workflow` consumes Integrations' verified events and delivery contracts without moving any integration ownership out of `Integrations`; Marketplace distribution remains separately governed against `docs/architecture/06_Marketplace.md`. `docs/architecture/05_AI_Architecture.md` was **not** modified — existing AI-governance rules are preserved and no new AI capability is introduced. `docs/architecture/02_Product_Requirements.md`, `docs/architecture/03_Database_Design.md`, and `docs/architecture/06_Marketplace.md` remain empty placeholders. The story ID is ARCH-009; the architecture document is numbered 17 (`ARCH-017`), continuing the existing document-number sequence.
- **ARCH-010 — AI Copilot & Workflow Automation Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 38–44), `docs/architecture/05_AI_Architecture.md` (AI Copilot and Workflow Automation), `docs/architecture/08_Roadmap.md` (EPIC-011); created `docs/adr/ADR-011-AI-Copilot-Workflow-Automation.md` (Accepted) and `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md`. Establishes `Workflow` as a supporting bounded context with two explicitly separated domain areas — Workflow Orchestration and AI Copilot — owning orchestration and AI-run state only, and resolves the "reserved for a future ARCH-010"/"future Workflow" dependency named by Constitution Article 37, ARCH-005 (Practice Management `Task`/`RecurrenceRule` extensibility), ARCH-006 (Documents & Knowledge Management playbook consumption), ARCH-007 (Billing process orchestration), ARCH-008 (IdentityAccess reserved "Workflow state"), and ARCH-009 (Integrations' reserved Workflow orchestration). Every domain action a workflow step invokes still passes through the owning module's published command/query and current authorization; IdentityAccess remains the sole owner of principals and sessions; Practice Management remains the sole Ethical Wall authority; the AI Copilot is assistive only, never an authorization or approval authority, and a defined, absolute list of non-delegable actions can never be performed autonomously by AI regardless of Firm configuration. `docs/architecture/02_Product_Requirements.md`, `docs/architecture/03_Database_Design.md`, and `docs/architecture/06_Marketplace.md` remain empty placeholders; Marketplace distribution and a future Reporting bounded context remain unarchitected. The story ID is ARCH-010; the architecture document is numbered 18 (`ARCH-018`), continuing the existing document-number sequence.

# EPIC-002 — Legal Intelligence (proposed, not scheduled)

Architecture is approved (ARCH-001). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (jurisdiction foundation, legal-source aggregate and metadata, ingestion pipeline, translation linking, citation engine, disclaimer enforcement, Thai legal search, AI retrieval, court-decision ingestion, firm annotations and saved research, amendment tracking and legal graph) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories.

# EPIC-003 — White-Label Platform (proposed, not scheduled)

Architecture is approved (ARCH-002). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (theme token schema and Branding Resolver foundation, `BrandProfile` aggregate and asset management, email and PDF branding integration, AI assistant branding, custom domain registration and verification, automated SSL provisioning, accessibility guardrails, theme marketplace integration) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories. The Digital Presence and Client Portal epics depend on this epic reaching at least its token-schema and `BrandProfile` foundation stages.

# EPIC-004 — Communications Hub (proposed, not scheduled)

Architecture is approved (ARCH-003). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (channel-neutral thread/message foundation, email adapters, messaging-platform adapters, website/portal chat with AI receptionist, human handoff, business-object linking, AI assistance and provenance, Communication Inbox, privacy/retention/legal hold, analytics) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories. The Digital Presence and Client Portal epics depend on this epic reaching at least its channel-neutral thread/message foundation and website/portal chat adapter stages.

# EPIC-005 — Digital Presence Platform (proposed, not scheduled)

Architecture is approved (ARCH-004). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (`DigitalPresenceProfile` and deployment-model foundation, Content Management and Website Builder, Client Portal authentication, Practice Management read-surfaces, Booking System, AI Receptionist and widget integration, Embedded Component Framework, Knowledge Publishing, SEO and accessibility hardening, enterprise API/CMS integration) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories. This epic consolidates the previously separate "Digital Presence" and "Client Portal" names elsewhere in this backlog and depends on EPIC-003 reaching its Branding Resolver foundation and EPIC-004 reaching its channel-neutral thread/message foundation and website/portal chat adapter stages, and on EPIC-006 reaching its `Client`/`Matter` foundation stages.

# EPIC-006 — Practice Management Core (proposed, not scheduled)

Architecture is approved (ARCH-005). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (`Client`/`Organization`/`Contact` foundation, `Matter` aggregate/lifecycle/`MatterNumber`, `PracticeArea` taxonomy, `MatterTeam` and role-based permissions, `Task`/`Appointment`/`Note` aggregates, Ethical Walls, Conflict Checking, Matter Timeline read-model, Matter Dashboard, integration hardening with Communications and Digital Presence) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories. This epic consolidates the previously unarchitected "Legal Practice Core" name elsewhere in this backlog and resolves the "future Practice Management module" dependency EPIC-004 and EPIC-005 already named. As the platform's Core Domain, its `Client`/`Matter` foundation stages are a practical prerequisite for EPIC-004's business-object-linking stage, EPIC-005's Practice Management read-surfaces and Booking System stages, and EPIC-007's document access-control stages, regardless of this backlog's listing order.

# EPIC-007 — Documents & Knowledge Management (proposed, not scheduled)

Architecture is approved (ARCH-006). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (document record/immutable versioning/provenance/Firm isolation; private storage, secure delivery, upload validation, scanning and quarantine; Matter associations, Ethical Walls, document access policy and co-client portal audience; document metadata, search, OCR and document intelligence; Knowledge Item, immutable Knowledge version, taxonomy and collection foundation; precedent, clause, template, playbook, practice-note and research-note management; Matter-to-knowledge curation, confidentiality review, de-identification and approval; Knowledge review, supersession, retirement, ownership and staleness management; permission-aware Knowledge search and governed AI/RAG; retention, legal hold, audit, Digital Presence publication and integration hardening) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories.

This epic covers **two explicitly separated domain areas** — Document Management (the source artifact) and Knowledge Management (curated, approved, reusable Firm know-how) — within one bounded context whose proposed module remains `Documents`. It consolidates the previously unarchitected "Documents" name elsewhere in this backlog and resolves the "future Documents bounded context" dependency EPIC-004 (attachment extraction), EPIC-005 (Client Portal Documents surface, Document Upload Widget), and EPIC-006 (Matter Timeline, Matter Dashboard) already named. Its access-control and curation stages depend on EPIC-006 reaching its `Client`/`Matter`/`MatterClient` and Ethical Wall stages, since both areas derive Matter-linked access from Practice Management's published contracts rather than implementing their own. Legal Intelligence retains ownership of official law, and Digital Presence retains ownership of public editorial content and publication.

E-signature, court e-filing, advanced document automation, collaborative editing, knowledge analytics, cross-matter precedent comparison, automated clause-conflict detection, multilingual knowledge, expertise location, Workflow consumption of playbooks, and Marketplace distribution of knowledge or template packages are named as future capabilities in `docs/architecture/14_Document_Knowledge_Management_Architecture.md` and are neither architected in detail nor proposed as stages here. `docs/architecture/06_Marketplace.md` remains an empty placeholder, and cross-Firm knowledge distribution remains prohibited.

# EPIC-008 — Billing, Trust Accounting & Finance (proposed, not scheduled)

Architecture is approved (ARCH-007). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (adoption of the PF-045 Foundation `Money`/`Currency` contract, Billing-specific `ExchangeRate` provenance, Firm accounting configuration and financial-policy versioning; billing arrangements, rates, time, fees, expenses and approval; invoices, tax documents, credit/debit notes and accounts receivable; payments, allocations, refunds, chargebacks and provider reconciliation; client-money/trust account, ledger and subledger foundation; trust transactions, authorization, segregation of duties and three-way reconciliation; general ledger, chart of accounts, journal posting and accounting periods; bank reconciliation, multi-currency, tax configuration and financial reporting; Practice Management, Documents, Communications and Client Portal integration; Financial AI, analytics, audit, security and operational hardening) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. **None of these stages carry approved story IDs yet, none is assigned a `PF-*` number, and none displace or renumber existing `PF-*` stories.**

This epic covers **three explicitly separated financial domain areas** — Billing/Accounts Receivable, Client Money/Trust Accounting, and Firm Finance/Accounting — within one bounded context whose proposed module is `Billing`. It consolidates the previously unarchitected "Commercial Operations" name elsewhere in this backlog and resolves the "future Billing bounded context" dependency EPIC-004 (Invoice linking and delivery), EPIC-005 (Client Portal Invoices/Payments surfaces and Payment Widget), EPIC-006 (Matter Timeline and Matter Dashboard billing panels), and EPIC-007 (rendered invoice artifact ownership) already named. Its stages depend on **`PF-045 — Money` in this epic's own Foundation Library section above**, which supplies the platform-wide exact-decimal `Money`/`Currency` contract Billing consumes and must never duplicate — EPIC-008 depends on PF-045 without scheduling, duplicating, renumbering, or marking it complete, and PF-045 remains an unscheduled Sprint 0.3 backlog item. It further depends on EPIC-006 reaching its `Client`/`Matter`/`MatterClient` and Ethical Wall stages, EPIC-007's rendered-artifact foundation, EPIC-005's Client Portal and Payment Widget, EPIC-004's Invoice linking and delivery, and a future Identity/Security capability for actor identity and segregation-of-duties assignments. Practice Management retains ownership of Client/Matter/Ethical Walls, Documents retains ownership of rendered artifacts, Digital Presence retains ownership of portal presentation, Communications retains ownership of delivery, and Legal Intelligence retains ownership of official law.

Accounts payable, procurement, payroll, fixed assets, budgeting, advanced treasury, an external accountant/auditor portal, banking feeds and open banking, e-tax/e-invoice provider integrations, additional jurisdiction packages, financial analytics and profitability analysis, advanced collections, and mobile payment experiences are named as future capabilities in `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` and are neither architected in detail nor proposed as stages here. No tax rate is hardcoded, no compliance is claimed, no payment provider is selected, no cryptocurrency is contemplated, OneLegalPro takes no custody of funds, and AI holds no financial authority.

# EPIC-009 — Identity, Security & Access Control (proposed, not scheduled)

Architecture is approved (ARCH-008). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (principal, actor-reference and Firm security-realm foundation; Firm membership, invitations, provisioning and lifecycle; authentication policy, credentials and authenticator foundation; MFA, passkeys, recovery, session rotation and revocation; roles, capabilities, permission sets and assignments; authorization-decision composition and Ethical Wall integration; Client Portal identity-boundary migration and integration; service principals, workload credentials and API-authentication foundation; federation, SSO and SCIM foundations; delegation, segregation of duties, privileged access and break-glass; security events, monitoring, risk controls and operational hardening) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. **None of these stages carry approved story IDs yet, none is assigned a `PF-*` number, and none displace or renumber existing `PF-*` stories.**

This epic establishes the `IdentityAccess` bounded context — Firm-scoped principals, membership, credentials, authenticators, sessions, roles, permission grants, delegations, service principals, privileged access, and security events — while **keeping security cross-cutting rather than creating a catch-all Security module**. `docs/architecture/04_Security_Architecture.md` is the platform-wide baseline; `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` is the bounded context.

It resolves the identity dependency every prior epic already named: **EPIC-005** (Client Portal authentication — `ClientPortalIdentity`/`PortalAuthPolicy` ownership corrected, with no approved capability removed), **EPIC-006** (Actor references and `MatterTeam` assignments), **EPIC-007** (author, owner, and approver Actor references), and **EPIC-008** (actor identity, permissions, and segregation-of-duties assignments). Every module's Firm-bound authorization depends on Platform Foundation tenancy (`PF-080` through `PF-082`, listed under Multi-Tenant Foundation above and unscheduled) **plus** EPIC-009 identity results — Foundation consumes verified identity and membership and never resolves identity itself.

Ownership is preserved elsewhere: **Practice Management alone owns Ethical Wall decisions**; each domain module owns its own resource rules and may narrow, never widen, what IdentityAccess grants; Digital Presence owns portal presentation, not authentication authority; and **AI holds no authorization authority**. Full OIDC/SAML federation, SCIM at scale, adaptive authentication, cross-Firm identity linking, partner/court-portal identities, and professional-qualification verification are named as future capabilities and are neither architected in detail nor proposed as stages here. **No identity vendor, provider, protocol, or package is selected, and no certification or compliance claim is made.** The Public API and Integration Platform is reserved for **ARCH-009**.

# EPIC-010 — API & Integration Platform (proposed, not scheduled)

Architecture is approved (ARCH-009). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (API contract and standards foundation; integration application registration and Firm-scoped installation; IdentityAccess service-principal, delegated-access and scope integration; domain API adapters and stable external representations; idempotency, concurrency, pagination, errors and async-operation foundation; outbound integration events, webhook subscriptions and delivery; inbound webhook verification, replay protection and command intake; connector configuration, synchronization and reconciliation; permission-aware import, export and secure file delivery; developer documentation, sandbox and contract-derived SDK foundations; rate limiting, observability, deprecation and operational hardening) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. **None of these stages carry approved story IDs yet, none is assigned a `PF-*` number, and none displace or renumber existing `PF-*` stories.**

This epic establishes the `Integrations` supporting bounded context — external API contract registration, integration applications and Firm-scoped installations, webhook subscriptions and delivery, inbound webhook intake, connector configuration and synchronization, and import/export job coordination — and populates `docs/architecture/07_API_Standards.md` as the platform-wide normative API standard. `Integrations` translates a stable external contract into an owning domain's published command or query; it never writes another module's tables and never recreates domain business rules.

It resolves the Public API dependency every prior epic already named: **EPIC-005** (Digital Presence's Public/Embedded APIs, explicitly reserved for this epic), **EPIC-008** (Billing's payment-provider webhook and reconciliation boundaries, whose provider-specific semantics remain Billing's), and **EPIC-009** (IdentityAccess's service-principal API-authentication foundation, explicitly reserved for this epic). Every external request depends on **EPIC-009 for authentication and service-principal identity** and on Platform Foundation's `FirmContext` primitive (`PF-080` through `PF-082`, listed under Multi-Tenant Foundation above and unscheduled) for Firm-bound tenancy — Integrations resolves no identity or tenancy of its own.

Ownership is preserved elsewhere: **IdentityAccess alone owns authentication and service-principal credentials**; **Practice Management alone owns Ethical Wall decisions**, consumed but never reimplemented; each domain module retains its own business rules and provider-specific semantics (payment with Billing, messaging with Communications, files with Documents); and **AI holds no API or integration authorization authority**. No API scope ever widens domain authorization or overrides an Ethical Wall. Workflow orchestration is now governed by **ARCH-010/ARCH-018** and the proposed **EPIC-011** (below) — `Workflow` consumes Integrations' verified events and delivery contracts, and Integrations retains ownership of external API contracts, integration installations, webhooks, connectors, provider ingress, and external delivery. Marketplace publication/monetization remains separately governed, unarchitected, against `docs/architecture/06_Marketplace.md`. **No API gateway, identity vendor, message broker, or hosting product is selected, no exactly-once delivery or global event ordering is claimed, and no certification or compliance claim is made.**

# EPIC-011 — AI Copilot & Workflow Automation (proposed, not scheduled)

Architecture is approved (ARCH-010). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (workflow definition/version and execution-state foundation; trigger, timer, wait-condition and idempotency foundation; domain command/query action adapters; human approval, segregation-of-duties and automation-policy foundation; Copilot session, AI-run provenance and context-manifest foundation; permission-filtered retrieval across Legal Intelligence, Knowledge and Documents; allowlisted tool proposals and approval-gated execution; Practice Management workflow templates and Task integration; Communications drafting/handoff and governed outbound-action integration; Document/Knowledge playbook consumption and drafting integration; Billing analysis and strictly non-autonomous finance integration; Integrations-triggered workflows and external delivery coordination; audit, observability, resilience, emergency disablement and operational hardening) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. **None of these stages carry approved story IDs yet, none is assigned a `PF-*` number, and none displace or renumber existing `PF-*` stories.**

This epic establishes `Workflow` as a supporting bounded context with **two explicitly separated domain areas** — Workflow Orchestration (workflow definitions, immutable published versions, runs, step-execution state, triggers, approvals, automation policy, compensation) and AI Copilot (governed sessions, AI-run provenance, context manifests, tool-call proposals, human-review decisions) — within one bounded context whose proposed module is `Workflow`, the same "one bounded context, explicitly separated domain areas" pattern EPIC-007 (Documents & Knowledge Management) and EPIC-008 (Billing) already established.

It resolves the "reserved for a future ARCH-010"/"future Workflow" dependency every prior epic already named: **Constitution Article 37**'s deferred Workflow orchestration scope; **EPIC-006** (Practice Management's `Task`/`RecurrenceRule` workflow-automation extensibility); **EPIC-007** (Documents & Knowledge Management's playbook-consumption dependency — a playbook remains guidance until an explicit, governed human decision converts it into an executable workflow); **EPIC-008** (Billing's process-orchestration dependency — Workflow may orchestrate a billing process but owns no financial state and can perform no action Billing's own authorization would refuse); **EPIC-009** (Identity, Security & Access Control's reserved "Workflow state"); and **EPIC-010** (API & Integration Platform's reservation of Workflow orchestration for a future ARCH-010, resolved here without moving any integration ownership). Every external trigger and delivery depends on **EPIC-010** for verified events and outbound delivery, on **EPIC-009** for identity and authorization, and on Platform Foundation's `FirmContext` primitive (`PF-080` through `PF-082`, listed under Multi-Tenant Foundation above and unscheduled) for Firm-bound tenancy — Workflow resolves no identity or tenancy of its own.

Ownership is preserved elsewhere: **IdentityAccess alone owns authentication, sessions, and service-principal credentials**; **Practice Management alone owns Ethical Wall decisions and the `Task` model** — a workflow step needing a `Task` calls Practice Management's published commands rather than defining a competing model; each domain module retains its own business rules and provider-specific semantics; and **the AI Copilot is assistive only, never an authorization or approval authority** — it may propose an action, but that proposal has no effect until it passes the same approval and authorization composition any other action would. A defined, absolute list of non-delegable actions (Matter status, Ethical Walls, identity/permissions, invoicing, client money, document finalization, substantive client communication, and more) can never be performed autonomously by AI regardless of Firm configuration, and client-money movement remains categorically non-delegable to AI. Human approval is the default for consequential action; a Firm-configured automation policy may pre-authorize only explicitly eligible, bounded, low-risk actions and can never become a blanket grant. Published workflow versions are immutable, and a running workflow stays bound to its starting version. **No AI/model provider, workflow-engine product, queue/event-broker product, vector database, orchestration framework, agent framework, cloud provider, or observability vendor is selected**, no exactly-once delivery is claimed, and Marketplace publication/monetization and cross-Firm workflow-template distribution remain separately governed, unarchitected, against `docs/architecture/06_Marketplace.md`.
