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
- PF-033 PostgreSQL Continuous Integration — Done (PR #44 merged as `40e7b0d` on 10 August 2026)

Repository-foundation tooling through PF-033 is complete. PF-033 is the
approved Release 0.1 PostgreSQL-test requirement from ADR-012. Its implementation
is complete within the recorded file boundary; independent implementation-review corrections were applied, the branch was reconciled with current `main` and the dependency-security hotfix, all four required checks passed, the required human approval comment was recorded, and PR #44 merged as `40e7b0d` on 10 August 2026. Its implementation branch and worktree were deleted locally and remotely. The `Protect
main` ruleset requires four GitHub Actions checks: `PHP Code Quality`,
`Frontend Build`, `Application Tests`, and `Dependency Audit`.

### PF-033 — PostgreSQL Continuous Integration — Done

**Objective.** Make PostgreSQL 16 the database exercised by the required
`Application Tests` GitHub check, so the authoritative CI path can prove
PostgreSQL behavior before Firm isolation work begins. Replace the test suite's
SQLite `:memory:` assumption with one fresh, ephemeral PostgreSQL database per
job while preserving the existing job graph and all four required check names.
This is test infrastructure only: it defines no production database, schema,
tenant policy, backup, deployment, or hosting choice.

**Dependencies.** PF-030 GitHub Actions, PF-031 Quality Gates, and PF-032
Security Scanning are Done. ADR-012 Decision 9 already makes PostgreSQL CI a
hard prerequisite of PF-080. ARCH-012 was approved on PR #34 on 1 August 2026
and now supplies the authority for PostgreSQL persistence and test obligations;
that architecture approval does not itself start this story. PF-080,
PF-081, PF-082, and every database-policy isolation test are future dependents,
not dependencies. No business module, migration, RLS policy, or production
environment is a dependency.

**Implementation contract.**

- Keep the GitHub job identifier `tests` and displayed check name
  **`Application Tests`** exactly unchanged. Keep **`PHP Code Quality`**,
  **`Frontend Build`**, and **`Dependency Audit`** unchanged as well.
- Add one `postgres:16-alpine` service to the `tests` job only, with a
  health-check that must succeed before tests run. Because `tests` runs directly
  on the GitHub-hosted `ubuntu-24.04` runner rather than inside a job container,
  publish PostgreSQL's port to that runner for this job only and configure
  Laravel with `DB_HOST=127.0.0.1`; the Docker-service hostname `postgres` is
  not valid on that path. The mapping creates no production or persistent
  listener and disappears with the runner.
- Use fixed, job-local, non-secret bootstrap-administrator credentials solely
  for service health and one bootstrap step. That step creates a separate,
  fixed, non-superuser application/test login and an empty disposable database
  for that login. Laravel, migrations, the engine guard, and the suite receive
  only the non-superuser login; they never receive or use the bootstrap
  administrator credential. No production or repository secret is read.
- Install `pdo_pgsql` for the `tests` job and configure Laravel through
  job-scoped environment variables, which take precedence over the existing
  `phpunit.xml` SQLite fallback without changing that file. The canonical local
  Docker test path uses the existing `postgres` service and supplies its host
  through environment; committed defaults must never contain a production
  credential.
- Fail before the suite if the active Laravel connection is not `pgsql`, the
  server is unavailable, or the database is not PostgreSQL 16. A silently
  selected SQLite connection is a failed check.
- Run the repository migrations against the fresh test database before the
  suite. Run `composer test` unchanged afterward.
- Add one narrowly scoped feature guard, activated by an explicit
  CI/canonical-Docker flag, proving that the suite is connected through Laravel
  to PostgreSQL 16 rather than SQLite. It proves engine selection only; it must
  not assert a production topology, extension, collation, RLS policy, backup,
  or compliance claim. A requested PostgreSQL guard that skips or silently
  falls back is a failure.
- Preserve the frontend build artifact dependency required by the existing
  feature test. Do not add Redis, a queue worker, an external service, a
  deployment step, or a long-lived artifact.
- Do not run Laravel, migrations, or tests as a PostgreSQL superuser, a role
  with `BYPASSRLS`, or a migration/owner role intended for later environments.
  The non-superuser CI login may own its disposable job database solely so it
  can migrate the fresh schema; that job-local convenience is not a runtime-role
  design and must not be represented as one.
- Keep workflow permissions read-only, every action pinned to a full commit
  SHA, and `pull_request` rather than `pull_request_target`.

**Allowed implementation files.**

- `.github/workflows/ci.yml`
- One new focused PostgreSQL CI guard under `tests/Feature/`
- `CONTRIBUTING.md`, only for canonical local and CI test commands
- `docs/implementation/01_Implementation_Sprint_Plan.md`
- `docs/implementation/03_Engineering_Backlog.md`
- `docs/PROJECT_STATUS.md`

An implementation may use fewer files. Any additional file requires a revised
story contract and review before it changes.

**Forbidden files and actions.** No `phpunit.xml`, `config/database.php`,
`.github/workflows/security.yml`, ruleset, required-check, Dependabot, Git hook,
Docker image, `compose.yaml`, Dockerfile, dependency or lock file, application
source, configuration file outside the allowlist, migration, seeder, schema,
RLS policy, role/grant script, module, route, controller, production
environment, deployment configuration, secret, credential store, backup,
restore, monitoring, or incident procedure. No
required check is renamed, removed, made conditional, allowed to pass after a
database failure, or replaced with a non-required check. No SQLite-versus-
PostgreSQL compatibility layer is introduced into business code.

**Acceptance criteria.**

- The `Application Tests` check provisions a healthy PostgreSQL 16 service and
  runs the complete Laravel test suite against it.
- CI fails if PostgreSQL is unavailable, the configured driver is not `pgsql`,
  the server is not version 16, migrations fail, or any test fails.
- No test in the authoritative CI path connects to SQLite or `:memory:`.
- A fresh job starts from an empty disposable database; no database state,
  database volume, or database credential survives the job. The existing
  short-lived frontend-build artifact remains the sole unrelated artifact
  exception required by the current feature-test dependency.
- All four required `Protect main` check names remain exact and all four pass.
- Workflow permissions and triggers remain no broader than before.
- The documented canonical local Docker invocation explicitly supplies the
  PostgreSQL connection overrides and activates the fail-closed engine guard —
  for example with `docker compose exec -T -e REQUIRE_POSTGRESQL_TEST_DATABASE=true`
  plus explicit `DB_CONNECTION=pgsql`, `DB_HOST=postgres`, port, database,
  username, and local-development-only password values. A plain local
  `composer test` that legitimately follows `phpunit.xml`'s SQLite fallback is
  not PF-033 completion evidence.
- Local canonical Docker validation under that explicit invocation, Pint,
  PHPStan, the full PostgreSQL-backed suite, Composer validation, dependency
  audits, and `git diff --check` pass.

**Security requirements.** CI credentials are non-secret and disposable; they
authenticate only to the ephemeral service instance and confer no authority in
any other environment. Logs never print connection URLs or passwords. No
production endpoint, data, backup, token, credential, or secret is configured
or supplied to the job. The engine guard proves configuration, never
authorization. PF-033 introduces
no Firm, actor, membership, entitlement, Ethical Wall, audit, or RLS decision.
Future isolation tests must run as the future approved runtime role; PF-033
must not pre-approve or simulate that role.

**Definition of Ready (met).** Goal, identifier, owner (Platform
Foundation/tooling), dependencies, file boundary, acceptance criteria, security
constraints, and tests are specified. Independent review, repository-owner
approval of the story entry, and confirmation of the four current required
check names were completed through PR #33. **ARCH-012 was Accepted** through
PR #34. Implementation and independent correction review completed within the
recorded file boundary. The branch was reconciled with current `main` and the
dependency-security hotfix; all four required checks passed; the required
human approval comment was recorded; PR #44 merged as `40e7b0d` on 10 August
2026; and its branch and worktree were deleted locally and remotely. PF-033 is
**Done**.

**Definition of Done (met).** Implementation merged through PR #44 as `40e7b0d`;
the complete suite demonstrably runs on PostgreSQL 16 in the required
`Application Tests` check; the engine guard and all acceptance criteria pass;
all four required checks pass under their unchanged names; security and
architecture review find no unresolved defect; human approval is recorded;
documentation and project status are updated; and the branch was removed after
merge. This evidence satisfies PF-080's PostgreSQL CI prerequisite.

## Foundation Library

The Foundation Library track has started. `PF-049`, `PF-047`, `PF-042`, `PF-048`, `PF-044`, `PF-041`, and `PF-043` are **Done**. `PF-043` was merged to `main` through pull request #28, following the repository owner's approval of the PF-043 pre-implementation analysis and the Candidate C contract; canonical Docker PHP 8.4 validation, independent review and closure verification, all four required `Protect main` checks, and the required human review comment are all complete, and the implementation branch was deleted locally and remotely after merge. **`PF-040` is now Done — implementation commit `f02b077` and correction commit `7fbb928` passed the protected workflow; independent review and correction confirmation completed; all four required `Protect main` checks passed on the final head; the required human approval comment was recorded; PR #31 merged to `main` as `5ea91dc`; and the implementation branch was deleted locally and remotely.** See its entry below. Every other story below remains Backlog — none is Ready, In Progress, or Done, and each still requires its own approved entry with a Definition of Ready before implementation begins.

- PF-040 AggregateRoot — **Done**
- PF-041 Entity — **Done**
- PF-042 ValueObject — **Done**
- PF-043 DomainEvent — **Done**
- PF-044 BusinessIdentifier — **Done**
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

**Timestamp range.** RFC 9562 §5.7 defines `unix_ts_ms` as a **48-bit unsigned** count of Unix milliseconds, so the representable range is `0` through `281474976710655` inclusive. `SystemUuidV7Generator` **explicitly verifies the injected clock's instant against that inclusive range and throws `InvariantViolation` when it falls outside.** Range checking and timestamp assembly use **integer arithmetic only, with no floating-point timestamp arithmetic anywhere — on the accepting and the rejecting path alike**, because a float cannot hold the whole range exactly and invites a boundary off-by-one. **The bounds are checked before the multiplication, never after.** `\DateTimeImmutable` can represent instants whose Unix seconds exceed `intdiv(PHP_INT_MAX, 1000)` — `new \DateTimeImmutable('@9223372036854776')` is constructible — and for those, a `seconds * 1000` product overflows the integer domain and silently becomes a float, so multiplying first would leave the guarantee holding only for inputs that were already in range. The generator therefore reads whole seconds and the millisecond remainder as separate integers and compares them before combining: seconds against `intdiv(281474976710655, 1000)` (`281474976710`), and — only on that final representable second — the remainder against `281474976710655 % 1000` (`655`). Negative seconds are rejected outright, `unix_ts_ms` being unsigned. Only once both checks pass is `seconds * 1000 + remainder` evaluated, which by construction cannot overflow. An out-of-range instant is **never truncated, wrapped, clamped, or reinterpreted**: each of those would return a syntactically valid UUID carrying a silently wrong timestamp, which is the worst available outcome. The six timestamp bytes are produced with `pack('J', …)`, which states big-endian explicitly, so assembly never depends on host byte order.

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
- `tests/Unit/Foundation/Domain/Identity/SystemUuidV7GeneratorTest.php` — proves the class is final, implements the contract, takes exactly one required non-nullable `Clock`, and **declares no static property**. Behavioural coverage: canonical output with version and variant bits; a generated value round-tripping through `fromString()`; the leading 48 bits equalling the clock instant at seven points including **minimum `0`** (`000000000000`) and **maximum `281474976710655`** (`ffffffffffff`), each expected prefix computed independently of the implementation's arithmetic; milliseconds rather than seconds encoded; **five out-of-range instants rejected with `InvariantViolation`**, including one below the minimum, one above the maximum, and far-out values in both directions; **three instants so distant that `seconds * 1000` overflows the integer domain rejected**, each with premise assertions proving the fixture really is in the overflowing region; the largest non-overflowing instant still rejected, pinning that the seconds comparison rather than the overflow does the rejecting; the final representable second bounded by its millisecond remainder, with `655` accepted and `656` refused; that rejection catchable through the Foundation taxonomy; the offending instant absent from the message; **no value produced at all for an unencodable instant**, proving nothing is truncated, wrapped, or clamped; the injected clock used rather than ambient time; ambient default timezone irrelevant, with the original restored in `finally`; 1000 generations at one fixed instant all distinct, **documented as demonstrating that the random bits vary and explicitly not as a collision-impossibility proof**; values at one instant sharing a timestamp while differing in their random bits; four values from four different milliseconds sorting by their timestamps; **clock rollback permitted, with the later-generated value asserted to sort earlier**, pinning the non-guarantee so a future silent introduction of monotonic state fails here; and two generators sharing one clock producing independent values.
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

**Definition of Done (met).** Acceptance criteria met; `composer validate --strict`, `composer pint:test` (47 files), `composer phpstan` (level 5, no errors), the full test suite (197 passed, 547 assertions), `composer audit --locked --abandoned=report`, and `npm audit --audit-level=high` all pass with no baseline, suppression, or ignored error; the PF-049 architecture guard passed unchanged; security and architecture reviewed; documentation updated; no critical defect; human approval recorded on the pull request before merge.

**Explicitly not implemented by PF-048.** **PF-044 `BusinessIdentifier` remains unimplemented and becomes next only after PF-048 is approved and merged.** PF-040, PF-041, PF-043, PF-045, and PF-046 also remain unimplemented — no `Entity`, `AggregateRoot`, `DomainEvent`, `BusinessIdentifier`, `Money`, `Currency`, or `Result`, and no stub, placeholder, or empty directory for any of them. No monotonic generator, no clock-rollback guard, no counter, sequence, or node identifier. No timestamp extraction, ordering, comparison, hashing, byte-level accessor, nil/max factory, serialization, persistence mapping, Eloquent cast, or API DTO. No `__toString` or `\Stringable`. No container binding, service provider, or bootstrap registration — a future binding of `UuidV7Generator` to `SystemUuidV7Generator` belongs to an approved Platform Runtime story (Sprint 0.4). No test double under `app/Foundation`. No ULID or alternative identifier format. No database column type, index strategy, or storage decision. No business module, no `app/Modules`, no production deployment, no new dependency, no logging, telemetry, audit, metrics, or reporting.

### PF-044 — BusinessIdentifier — Done

**Objective.** Establish the single framework-independent base every OneLegalPro business identifier extends, so a module identifier is a UUIDv7 *with a type* — two different concrete identifier types never equal, even when both wrap the same UUID — instead of every module re-inventing storage, validation, and equality on a bare `UuidV7`. It gives PF-041 `Entity` one real type to express identity against.

**Scope.** The Foundation Domain identifier base only: the `BusinessIdentifier` abstract class, its isolated unit test, and the Foundation documentation recording it. Nothing else. **It implements no concrete identifier and names no business identifier.**

**Dependencies.** Fifth in the approved order. Depends in **code** on **PF-042** (`implements ValueObject`), **PF-048** (`UuidV7` composition and validation, `UuidV7Generator` for creation), and **PF-049** (`InvalidArgument`, `InvariantViolation`, propagated) — all Done. PF-047 is transitive only, reached through `UuidV7Generator`'s implementations. **No new dependency of any kind.**

**Exact contract implemented.**

```php
abstract readonly class BusinessIdentifier implements ValueObject
{
    final private function __construct(private UuidV7 $value) {}

    final protected static function fromUuid(UuidV7 $value): static;

    /** @throws \App\Foundation\Domain\Exception\InvalidArgument */
    final public static function fromString(string $value): static;

    /**
     * @throws \Random\RandomException
     * @throws \App\Foundation\Domain\Exception\InvariantViolation
     */
    final public static function generate(UuidV7Generator $generator): static;

    final public function toString(): string;

    final public function __toString(): string;

    /** @return list<string> */
    final protected function equalityComponents(): array;

    final public function equals(ValueObject $other): bool;
}
```

**`implements ValueObject`, not `extends`.** `ValueObject` is an interface (PF-042), so a class satisfies it by implementation; `extends` would be a fatal error. `equals()` is implemented here rather than left to subclasses, because a concrete leaf is an **empty** marker subclass and could not supply it — and because a shared, `final`, exact-runtime-type comparison is the whole reason this base exists.

**Equality semantics.** Exact runtime class, then value: `$other instanceof self && $other::class === $this::class && $other->equalityComponents() === $this->equalityComponents()`. `instanceof self` alone would be wrong on an inheritable base, exactly as PF-042 records. Cross-type comparison returns `false` in both directions and never throws a `\TypeError`. `equalityComponents()` returns the canonical text and nothing else — no cache, no derived value, no object identity. Equality is total, reflexive, symmetric, transitive, strict, and non-coercive; nothing is canonicalized at comparison time. Not constant-time; no timing guarantee.

**Creation and reconstitution rules.** `generate(UuidV7Generator $generator)` for creation — the generator is a **parameter, never a held collaborator**, so nothing reads ambient time or randomness, and it is called exactly once per identifier. `fromString(string $value)` for reconstitution: it accepts **any textual UUIDv7 representation `UuidV7::fromString()` accepts, including uppercase and mixed-case hexadecimal**, and stores the canonical lowercase value that method returns; **stored and emitted output is always canonical lowercase text**. `fromUuid()` is the **protected construction seam**. The constructor is `final private`: `final` because `new static()` must never resolve to a replaced signature — a subclass declaring its own constructor would otherwise break it at runtime — and `private` because the named constructors are the only paths to an instance.

**Immutability and the subclass rule.** The base is `abstract readonly`, so every concrete identifier is necessarily `readonly` (PHP rejects a non-readonly subclass) and no identifier may declare a static property (PHP rejects one in a readonly class). Every base member is `final`. **PHP enforces the private constructor, the protected seam, the immutable stored `UuidV7`, and the final factories — it does not make a subclass factory alias or an additional subclass property technically impossible.** The architectural rule, recorded in `app/Foundation/README.md` and enforced by review, is that a future production leaf is an **empty `final readonly` marker subclass** adding **no state, no invariant, no constructor parameter, no factory alias, and no behaviour**. **PF-044 creates no production leaf.**

**Exception mapping.** PF-049 only; **no new exception type**. `fromString()` propagates `InvalidArgument` from `UuidV7` untouched, so the rejected input cannot reach a message constructed here — none is. `generate()` propagates `\Random\RandomException` untranslated and `InvariantViolation` from the supplied generator. `fromUuid()`, `toString()`, `__toString()`, `equalityComponents()`, and `equals()` throw nothing.

**Type-safety and security requirements.** A typed identifier **prevents accidental interchange** between concrete identifier types and prevents a bare `UuidV7` standing in for one. **It does not prevent deliberate reconstruction** across types via `fromString()` on another type's canonical text, and nothing claims otherwise. **`BusinessIdentifier` is not an authorization boundary, not a security boundary, and not an ownership or referential-integrity control** — those remain the responsibility of future owning module stories and the platform's separate controls; `CheckEthicalWallAccess` remains Practice Management's alone and `FirmContext` derives only from verified identity and membership. A business identifier is **not a secret**; possession proves neither identity nor authorization. Every value **discloses its approximate creation time**, and encodes **no Firm, actor, client, matter, or privileged content**. Never a session token, API credential, magic link, recovery code, webhook secret, capability, or proof of identity. Exception messages never echo rejected input. Identifiers stay opaque externally. **The absence of `toUuid()` is an encapsulation and minimal-API decision, never a security control.**

**`__toString()` — a deliberate, approved departure.** `UuidV7` declares none and is not `\Stringable`, and **that rule is unchanged**: a raw UUID must not reach a log line or a URL without someone deciding to put it there. A business identifier is a named domain type whose rendering carries its meaning with it, so string context is approved for it alone. `toString()` remains the explicit form and is preferred wherever the call site can name it. `\Stringable` appears on the type only because PHP adds it automatically to any class declaring `__toString()`.

**Persistence and transport boundary.** `toString()` and `__toString()` expose canonical text; `fromString()` constructs from canonical text. **None of them defines or authorizes** a database mapping or column type, an Eloquent cast, an API DTO, an event payload field, route-model binding, an index strategy, or any serialization format. Those remain the responsibility of future repositories, adapters, and owning module stories; external representation remains `Integrations`' concern.

**Allowed files.**

- Created: `app/Foundation/Domain/Identity/BusinessIdentifier.php`, `tests/Unit/Foundation/Domain/Identity/BusinessIdentifierTest.php`.
- Modified: `app/Foundation/README.md` (Foundation convention record), and — for status and sequencing only — `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md`.

**Forbidden files.** No dependency or lock file (`composer.json`, `composer.lock`, `package.json`, `package-lock.json`); no tooling configuration (`phpunit.xml`, `phpstan.neon.dist`, `pint.json`); no `.github/`, `.githooks/`, Docker, environment, or deployment file; no Laravel configuration, bootstrap file, service provider, container binding, route, or controller; no migration; no existing PHP source file, including the PF-049 exception classes, the PF-047 `Clock`/`SystemClock`, the PF-042 `ValueObject`, and all three PF-048 Identity types; **no change to `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php`** — the PF-049 guard passed unchanged; no change to any other existing test; no separate test-fixture file; no `README.md`, `AGENTS.md`, or `CONTRIBUTING.md`; no architecture or ADR file; no `app/Modules`; no Dependabot branch. `docs/implementation/01_Implementation_Sprint_Plan.md` is not modified: its catalogue and approved order already record PF-044 correctly and it carries no per-story status field — the same reasoning PF-047, PF-042, and PF-048 applied.

**Acceptance criteria.**

- Exactly one new source type exists, in `App\Foundation\Domain\Identity`, `abstract readonly`, implementing `ValueObject` and — apart from the automatic `\Stringable` — nothing else.
- The constructor is `final private` and takes exactly one required non-nullable `UuidV7`; the sole stored property is a private, readonly, non-static `UuidV7 $value`.
- Public members are exactly `fromString`, `generate`, `toString`, `__toString`, `equals`; protected members are exactly `fromUuid` and `equalityComponents`; **every declared member is `final`**.
- No constant and no static property is declared.
- `fromString()` accepts every textual form `UuidV7` accepts, uppercase and mixed case included, and always stores and emits canonical lowercase text.
- Malformed text, non-version-7 UUIDs, and non-RFC variants are rejected with `InvalidArgument`, catchable through `DomainException`, `FoundationException`, and `\RuntimeException`; no message contains the rejected input.
- `generate()` invokes the supplied generator exactly once, retains it nowhere, and returns the concrete class called.
- Equality compares exact runtime class before value; two different concrete identifier types wrapping the same UUID are unequal in both directions with no `\TypeError`.
- No `toUuid()` or other UUID-object accessor; no `jsonSerialize`, `toArray`, `toPrimitives`, `fromPrimitives`, `hash`, `compareTo`, `with*`, or `\JsonSerializable`.
- No nil rejection, timestamp policy, tenant policy, parsing policy, or domain-specific invariant beyond the UUIDv7 validation inherited from `UuidV7`.
- No Laravel, Illuminate, Eloquent, Symfony, Carbon, PSR, service-container, persistence, serialization, or mutable-state dependency.
- **No concrete identifier subclass exists in production code**; `app/Modules` was not created.
- Strict types declared; namespace matches path; PHP global types take a leading backslash.
- The PF-049 architecture guard passes **unchanged**, as does every other pre-existing test.
- Pint, PHPStan (level 5, no baseline or suppression), the full test suite, `composer validate --strict`, and both dependency audits pass, and all four required `Protect main` checks (`PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit`) retain their exact names.

**Tests.**

- `tests/Unit/Foundation/Domain/Identity/BusinessIdentifierTest.php` — 118 tests, 171 assertions. Reflection shape: abstract, readonly, implements exactly `ValueObject` and `\Stringable`, not `\JsonSerializable`; constructor final and private with one required `UuidV7`; the approved public and protected member lists exactly; **every declared member final**; static versus instance members; the `equals()` signature pinned against the `ValueObject` contract; `toString()`/`__toString()` returning non-nullable `string`; twenty-five individually named prohibited or deferred members absent; exactly one stored property, private, readonly, non-static, typed `UuidV7`, named `value`; no static property and no constant. Behavioural: valid lowercase parsing; the concrete class returned by each named constructor; uppercase and mixed-case canonicalization; case never affecting equality; the protected construction seam exercised through the approved test-only fixture; canonical round trip; a fourteen-case rejection matrix; nine wrong-version UUIDs and a non-RFC variant rejected; rejection catchable through four supertypes; the rejected text absent from the message for four distinct inputs; generation through a deterministic scripted generator; the generator invoked **exactly once** and consuming one value per call; no generator retained on the instance; same type and same UUID equal on distinct instances; same type and different UUID unequal; reflexivity and transitivity; **two different identifier types wrapping the same UUID unequal in both directions**; a foreign `ValueObject` and a bare `UuidV7` unequal in both directions; equality components being the canonical text only; string conversion, cast, and interpolation; reassignment refused **even through reflection**; no Laravel booted; `app/Modules` absent; and `App\Foundation\Domain\Identity` containing exactly the four expected files and no concrete identifier.
- Fixtures are declared **inside the test file**: `AlphaTestIdentifier` and `BetaTestIdentifier` (empty `final readonly` markers, the approved production form), `SeamTestIdentifier` (deliberately non-final, documented as not a form to copy, exposing the protected seam — an anonymous class cannot serve, because `new class extends BusinessIdentifier` would invoke the private constructor at its declaration site), `ForeignTestValue`, and `ScriptedUuidV7Generator`. **No separate fixture file exists, and no fixture name carries business meaning.** No test asserts anything about what every potential future subclass can or cannot do.
- It extends `PHPUnit\Framework\TestCase` directly, boots no Laravel application, and adds no test dependency.

**Documentation impact.** `app/Foundation/README.md`: the namespace table and its accompanying note now record `App\Foundation\Domain\Identity` as **Implemented** — the three PF-048 types plus `BusinessIdentifier`, and no concrete identifier — plus a new **`Business identifiers`** subsection within the existing `Identity` section recording the contract, the inherited-validation and canonical-lowercase rule, the creation and seam rules, the empty-marker-subclass architectural rule and exactly what PHP does and does not enforce, the corrected type-safety position, the `__toString()` departure and the unchanged `UuidV7` rule, the persistence and transport boundary, and the exclusions. The approved-order line now records PF-044 as implemented and PF-041 as next, and the `External dependencies` section records that PF-044 introduced none. `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md` updated for status and sequencing only.

**Definition of Ready (met).** Goal clear and narrowly bounded to one base type; owner identified (repository owner); dependencies resolved (PF-042, PF-048, PF-049 all Done); exact contract approved and recorded above; equality, construction, immutability, and exception semantics settled with no new exception type; the four open design decisions resolved by the repository owner (final private constructor; a documented non-final test-only fixture for the construction seam; a new `Business identifiers` subsection in the Foundation README; no `toUuid()`); security implications identified and corrected — accidental interchange only, never an authorization or security boundary; tests specified; allowed and forbidden files enumerated; no architecture blocker — `App\Foundation\Domain\Identity` is the namespace `app/Foundation/README.md` already reserved for PF-044, PF-042 explicitly reserved the `extends` slot for this base, and the PF-049 guard is a denylist that permits the new file unchanged.

**Definition of Done (met).** Acceptance criteria met; validated in the canonical Docker PHP 8.4 application container — `composer validate --strict`, `composer pint:test` (47 files), `composer phpstan` (level 5, 33 files, no errors), the focused PF-044 suite (118 passed, 171 assertions), the full test suite (315 passed, 735 assertions), `composer audit --locked --abandoned=report`, and `npm audit --audit-level=high` all pass with no baseline, suppression, or ignored error; the PF-049 architecture guard passed unchanged; security and architecture reviewed; documentation updated; no critical defect; human approval recorded on the pull request before merge.

**Explicitly not implemented by PF-044.** **No concrete identifier subclass in production code, of any kind, and no business identifier name invented** — the only leaves that exist are test-only fixtures inside the PF-044 test file. PF-041, PF-043, PF-040, PF-045, and PF-046 remain unimplemented — no `Entity`, `AggregateRoot`, `DomainEvent`, `Money`, `Currency`, or `Result`, and no stub, placeholder, or empty directory for any of them. No `toUuid()` or other UUID-object accessor. No ordering, comparison, hashing, or timestamp extraction. No serialization, persistence mapping, database column type, migration, Eloquent cast, API DTO, event payload contract, or route-model binding. No container binding, service provider, or bootstrap registration. No test double under `app/Foundation`. No authorization, ownership, or referential-integrity check. No nil rejection, timestamp policy, tenant policy, or parsing policy of its own. No business module, no `app/Modules`, no production deployment, no new dependency, no logging, telemetry, audit, metrics, or reporting.

### PF-041 — Entity — Done

**Implementation status.** Implemented and merged to `main`: `Entity.php` and `EntityTest.php` exist in `App\Foundation\Domain\Model` and its test namespace. All validation — `composer pint:test`, `composer phpstan` (level 5, no errors), an informational level-6 dry run (no errors, no configuration change), the focused PF-041 suite, the full test suite, `composer validate --strict`, and both dependency audits — passed in the canonical Docker PHP 8.4 container, with real counts recorded in the Definition of Done below; the PF-049 architecture guard passed unchanged. Security and architecture were reviewed, documentation was updated, and the pull request received the required human review comment and all four required `Protect main` checks before merge. **PF-041 is Done, and its Definition of Done below is met.**

**Objective.** Establish the single framework-independent base every OneLegalPro domain entity extends, so identity-based equality is defined once — same entity type, same identifier — instead of each entity re-inventing it or borrowing value-object semantics that do not apply. It completes the distinction PF-042 opened, which already names PF-041 as the story that defines identity-based equality, and gives PF-040 `AggregateRoot` a base to extend.

**Scope.** The Foundation Domain entity base only: the `Entity` abstract class, its isolated unit test, and the Foundation documentation recording it. Nothing else. **It implements no concrete entity, no aggregate, no domain event, and no concrete identifier.**

**Dependencies.** Sixth in the approved order. **PF-044** (`BusinessIdentifier`) is `Entity`'s only direct code dependency and its only import — Done. **PF-042** (`ValueObject`) is reached two ways: **transitively in code**, because `BusinessIdentifier implements ValueObject`, and **directly in documentation**, as the semantic contrast this story's class docblock draws — `Entity` itself never imports or implements `ValueObject` — Done. **PF-048** (`UuidV7`, `UuidV7Generator`) is a **transitive** code dependency through `BusinessIdentifier`'s own composition and generation path — Done. **PF-047** (`Clock`) is reached only **transitively**, through `SystemUuidV7Generator`'s use of `Clock` during identifier generation that happens before an `Entity` ever receives its identifier — Done. **PF-049 is reached only before construction**: identifier parsing (`BusinessIdentifier::fromString()`) or generation (`BusinessIdentifier::generate()`) can throw `InvalidArgument`, `InvariantViolation`, or `\Random\RandomException` while producing the `BusinessIdentifier` an `Entity` is later constructed with — **`Entity` itself imports, throws, and translates no PF-049 exception**. All five are Done. **No new dependency of any kind.**

**Deliverables.**

- `App\Foundation\Domain\Model\Entity` — `abstract class`, not `readonly`, generic over `TIdentifier of BusinessIdentifier`; one `protected` non-final constructor; one `private readonly BusinessIdentifier` property; two `final public` methods.
- One pure unit test under `tests/Unit/Foundation/Domain/Model/`, with all fixtures declared inside it.
- `app/Foundation/README.md` updated for the `Model` namespace and a new `Entities` subsection.

**Exact published contract.**

```php
/**
 * @template TIdentifier of BusinessIdentifier
 */
abstract class Entity
{
    /** @param TIdentifier $id */
    protected function __construct(private readonly BusinessIdentifier $id) {}

    /** @return TIdentifier */
    final public function id(): BusinessIdentifier;

    /** @param Entity<*> $other */
    final public function sameIdentityAs(Entity $other): bool;
}
```

Two public members, both `final`. No constant, no static member, no `equals()`, no `__toString()`, no serialization.

**Identity semantics.** Supplied once at construction through the `protected` constructor and stored `private readonly` on the base, so **for an already-initialized instance, that identity can never be reassigned** — not by a subclass, not by this class, and not through `ReflectionProperty::setValue()` against the initialized property. This is a **within-instance stability guarantee only** — `ReflectionClass::newInstanceWithoutConstructor()` followed by reflection assignment, or a crafted `unserialize()` input, can each still produce a *different*, forged instance carrying an arbitrary identifier by bypassing normal construction entirely; neither mechanism reassigns an already-initialized instance's identity, and a forged instance grants no authorization, ownership, entitlement, or proof of provenance. PHP cannot make construction itself unforgeable, and PF-041 does not add production code — no factory, guard, or serialization hook — to simulate that it can. The constructor is deliberately **not** `final`: an entity legitimately carries its own state, so a subclass declares its own constructor and calls `parent::__construct($id)`. This diverges from PF-044's `final private` constructor, and deliberately so — an identifier is a closed value, an entity is not.

**Two limits of the language, documented rather than claimed away.**

- **Latent initialization failure.** A subclass that omits `parent::__construct()` constructs successfully and remains usable; the failure surfaces only when `id()` is first reached, as `\Error: Typed property must not be accessed before initialization`. `sameIdentityAs()` reaches the same uninitialized property and raises the identical `\Error` **only when its exact-runtime-class check has already matched** — comparing against an `Entity` of a different runtime class returns `false` at that check without ever reading either identifier, so it never raises; this short-circuit is expected behaviour, not a workaround or an identity fallback. The underlying failure is **latent, not immediate**, and may sit far from the mistake. No guard, null check, or nullable type may soften it — each would trade a loud late failure for a silent wrong one. **Mitigation, binding on every later story: every concrete entity must have a test that constructs the entity and then reads `id()`.**
- **Property shadowing is possible.** A subclass **may** declare its own property named `id`; PHP keeps the two separately (distinct mangled names) and the base's property still governs `id()` and `sameIdentityAs()`. The subclass can neither access nor replace `Entity::$id`. Shadowing is therefore harmless to the contract but confusing in `var_dump` and array casts, and is **prohibited by convention, not by the language**.

**Identity is stable, not unforgeable.** Once a correctly constructed instance's identifier is initialized, it cannot be reassigned — the within-instance guarantee described above, not a claim that reflection or deserialization cannot bypass normal construction to create a different, forged instance. `BusinessIdentifier::fromString()` permits reconstructing an *equal identifier value* from its canonical text — it says nothing about whether an *entity* carrying that identifier can be constructed: that remains the owning module's own construction rule, not something this base grants or withholds. **"Same identity" is never proof of provenance, authenticity, ownership, authorization, or entitlement.**

**Equality semantics.** `sameIdentityAs()` — named for what it means, and deliberately **not** `equals()`, which belongs to `ValueObject` and means something else. Exact runtime **entity** class first (`$other::class === $this::class`), then identifier equality through `BusinessIdentifier::equals()`, which itself compares exact **identifier** class before canonical value. All three of matching entity type, matching identifier type, and matching identifier value are therefore required, in two checks. **The entity-class check is not redundant**: nothing prevents two entity types from using the same identifier type, and identifier equality alone would conflate them. **Because the class check runs first, a comparison involving an instance whose identifier was never initialized reaches — and raises `\Error` on — that uninitialized identifier only when the two runtime classes already match; a mismatched-class comparison returns `false` first and never reads either identifier. This short-circuit is expected behaviour, not a workaround.** **Entity state never participates** — two instances of one type with one identifier are the same entity however far their attributes have drifted. An unrelated or differently typed entity returns `false` in both directions and never throws a `\TypeError`. The relation is total, reflexive, symmetric, transitive, strict, and non-coercive. It is **not** constant-time, carries no timing guarantee, and is **not** an authorization check.

**Mutability — an explicit, permanent architectural trade-off.** The base is **not** `readonly`. `readonly` is viral in PHP, so a readonly base would make every entity on the platform permanently immutable. A non-readonly base instead **permits** mutable lifecycle state and **permanently prohibits `readonly` entity subclasses**, because PHP forbids a readonly class extending a non-readonly one. **Entities are not required to be mutable** — an immutable entity is expressed with private properties and no mutators rather than with the `readonly` modifier. Reversing this later would be a breaking change requiring explicit human approval.

**Generics — verified, not assumed.** Empirically checked with **PHPStan 2.2.5 on PHP 8.4.23** in the canonical Docker `app` container, with the same Larastan and Carbon extensions `phpstan.neon.dist` includes, against the real `BusinessIdentifier`:

- **`@template TIdentifier of BusinessIdentifier` is invariant, and stays invariant.** `@template-covariant` is **prohibited**. It would also pass — PHPStan exempts constructors from its variance check, confirmed by observing `generics.variance` fire at level 5 for a non-constructor method consuming the template and not fire for the constructor — but it would permanently forbid any future method that consumes the template in a parameter, for no gain over the wildcard below.
- **`@param Entity<*> $other` on `sameIdentityAs()` is mandatory.** The wildcard is supported and verified clean at levels 5 and 6 while accepting an entity of any template argument.
- **`@param Entity<BusinessIdentifier>` is prohibited.** Because the template is invariant, it fails at **level 5** with `argument.type` and rejects **every** concrete entity — including comparing an entity with itself. `Entity<mixed>` is likewise prohibited: it fails with `generics.notSubtype`, `mixed` not being a subtype of the `of BusinessIdentifier` bound.
- **`self $other` is not an alternative.** PHPStan infers `self` inside the generic base as the bare, unparametrized `Entity`, producing an identical `missingType.generics` diagnostic at level 6.
- **Level-5 and level-6 results.** The approved contract is clean at **both**. `id()` narrows to the concrete identifier type. Without the `Entity<*>` annotation the contract is still clean at level 5 but carries two `missingType.generics` errors at level 6 — one for the unparametrized parameter, one for any subclass missing `@extends`.
- **`@extends Entity<ConcreteIdentifier>` is mandatory on every concrete entity.** It also delivers construction-site checking: a subclass declaring `@extends Entity<AlphaId>` while leaving its native constructor parameter as `BusinessIdentifier` still had a wrong-subtype construction reported at level 5 (`Parameter #1 $id ... expects AlphaId, BetaId given`). **Known level-5 debt:** a missing `@extends` is reported only from level 6, so at the project's level 5 an omission silently costs the narrowing instead of failing the build. It is therefore a standing **review obligation** for every later entity story.
- **A narrowed native constructor parameter is recommended**, not load-bearing: `__construct(SomeIdentifier $id) { parent::__construct($id); }` adds **runtime** enforcement on top of the static guarantee above. PHP has no runtime generics, so the template alone is a static-analysis guarantee.
- **Before PF-041, the repository had no `@template`, `@extends`, or `@implements` generic annotation anywhere in `app/` or `tests/`.** PF-041 introduces the repository's **first generic Foundation contract** (`@template TIdentifier of BusinessIdentifier` on `Entity`), and its test fixtures introduce the **first `@extends` usages** (`@extends Entity<ConcreteIdentifier>` on each concrete test entity). PF-041 adds **no `@phpstan-ignore`, baseline, suppression, or ignored error** of any kind — the two runtime `\TypeError` tests that deliberately construct with a wrong identifier subtype need none, because `tests/` is outside `phpstan.neon.dist`'s analysed `paths`.

**Exceptions.** **PF-041 throws nothing, and invents no validation in order to use the taxonomy.** The constructor's parameter type makes an invalid identifier unrepresentable, and neither `id()` nor `sameIdentityAs()` has a failure path. No PF-049 exception is imported, raised, or documented as thrown, and **no new exception type is created**. The only reachable runtime error is PHP's own uninitialised-property `\Error` described above, which is a programming mistake rather than a domain condition.

**Allowed files.**

- Created: `app/Foundation/Domain/Model/Entity.php`, `tests/Unit/Foundation/Domain/Model/EntityTest.php`.
- Modified: `app/Foundation/README.md` (Foundation convention record), and — for status and sequencing only — `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md`.

**Forbidden files.** No dependency or lock file (`composer.json`, `composer.lock`, `package.json`, `package-lock.json`); no tooling configuration (`phpunit.xml`, `phpstan.neon.dist`, `pint.json`) — **no PHPStan level change, baseline, suppression, or ignored error, and no level raise as part of this story**; no `.github/`, `.githooks/`, Docker, environment, or deployment file; no Laravel configuration, bootstrap file, service provider, container binding, route, or controller; no migration; **no existing PHP source file**, including the PF-042 `ValueObject`, the PF-044 `BusinessIdentifier`, all three PF-048 Identity types, the PF-049 exception classes, and the PF-047 `Clock`/`SystemClock`; **no change to `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php`** — the PF-049 guard must pass unchanged; no change to any other existing test; no separate test-fixture file; no `README.md`, `AGENTS.md`, or `CONTRIBUTING.md`; no architecture or ADR file; no `app/Modules`; no Dependabot branch. `docs/implementation/01_Implementation_Sprint_Plan.md` is not modified: its catalogue and approved order already record PF-041 correctly and it carries no per-story status field — the same reasoning PF-047, PF-042, PF-048, and PF-044 applied.

**Acceptance criteria.**

- Exactly one new source type exists, in `App\Foundation\Domain\Model`, `abstract`, **not** `readonly`, implementing **no** interface — in particular not `ValueObject`, `\Stringable`, or `\JsonSerializable`.
- The constructor is `protected` and **not** final, with exactly one required non-nullable `BusinessIdentifier`; the sole property is private, readonly, non-static, typed `BusinessIdentifier`.
- Public members are exactly `id()` and `sameIdentityAs()`, both `final` and non-static; no constant and no static property is declared.
- The class declares `@template TIdentifier of BusinessIdentifier`; `id()` declares `@return TIdentifier`; the constructor declares `@param TIdentifier $id`.
- `sameIdentityAs()` declares **`@param Entity<*> $other`**. **`Entity` does not declare `@template-covariant` as a PHPDoc tag, and `sameIdentityAs()` does not use `Entity<BusinessIdentifier>` or `Entity<mixed>` as its PHPDoc parameter type.** Explanatory documentation and tests may still mention these spellings when documenting or guarding against the rejected alternatives.
- `id()` returns the exact identifier instance supplied; for an already-initialized instance, identity cannot be reassigned, including through `ReflectionProperty::setValue()` — a within-instance guarantee only, distinct from construction-bypass techniques (`ReflectionClass::newInstanceWithoutConstructor()` plus reflection assignment, or crafted `unserialize()` input) that instead produce a different, forged instance and grant it no authorization, ownership, entitlement, or proof of provenance.
- Comparison requires exact runtime entity class **and** `BusinessIdentifier` equality; two different entity types over the same identifier type and value are unequal in **both** directions with no `\TypeError`; two entities whose identifiers are **different `BusinessIdentifier` subtypes**, built from the identical canonical UUID text, are likewise unequal in **both** directions with no `\TypeError`, proven through `sameIdentityAs()` itself rather than through `BusinessIdentifier::equals()` checked in isolation; mutating non-identity state changes no result.
- No `equals()`, `__toString()`, `jsonSerialize`, `toArray`, `__debugInfo`, event recording/release/clearing, version, timestamp, soft-delete, or persistence member exists.
- No Laravel, Illuminate, Eloquent, Symfony, Carbon, PSR, service-container, persistence, serialization, tenancy, or authorization dependency.
- **No concrete entity, aggregate, domain event, or identifier subclass exists in production code**; `app/Modules` was not created.
- `strict_types=1` declared; namespace matches path; PHP global types take a leading backslash.
- **The PF-049 architecture-guard file is byte-identical and passes unchanged**, automatically inspecting `Entity.php` along with every other Foundation source file it already covers. Its assertion count grows as a natural consequence of iterating one more file; this criterion does not pin a permanent assertion count, though the isolated count observed at implementation time may be reported as evidence. Every other pre-existing test also passes unchanged.
- **No PHP source or test file declares or uses an `@phpstan-ignore` or `@phpstan-ignore-next-line` suppression tag, and no PHPStan baseline, suppressed error, ignored error, or configuration ignore was added.** The two tests that deliberately construct with a wrong identifier subtype to prove a `\TypeError` need no such annotation, because `tests/` is outside `phpstan.neon.dist`'s analysed `paths` and PHPStan never sees them. Documentation elsewhere in this entry names these annotations only to record that they are prohibited, not to declare or use one.
- Validated in the canonical Docker PHP 8.4 application container: Pint clean; **PHPStan level 5 clean with no baseline, suppression, or ignored error**; a **level-6 dry run recorded as informational only** — no configuration change and no level raise; the focused PF-041 tests pass; the full suite passes (baseline before this story: 315 passed, 735 assertions); `composer validate --strict` and both dependency audits pass; all four required `Protect main` checks (`PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit`) pass and retain their exact names.

**Tests.** One file, `tests/Unit/Foundation/Domain/Model/EntityTest.php`, extending `PHPUnit\Framework\TestCase` directly, booting no Laravel application and adding no test dependency. Deliberately small — `Entity` has no parsing, no rejection matrix, and no generation path.

- **Shape, by reflection:** abstract; **not** `readonly`; implements no interface; constructor `protected`, **not** final, exactly one required non-nullable `BusinessIdentifier`; public methods exactly `['id', 'sameIdentityAs']`, both `final` and non-static; `id()` returns non-nullable `BusinessIdentifier`; `sameIdentityAs()` takes one required non-nullable `Entity` and returns non-nullable `bool`; exactly one property — private, readonly, non-static, typed `BusinessIdentifier`; no constant; no static property. Named-absence list: `equals`, `__toString`, `jsonSerialize`, `toArray`, `__debugInfo`, `recordEvent`, `releaseEvents`, `clearEvents`, `version`, `createdAt`, `updatedAt`, `deletedAt`, `withId`, `setId`, `hash`, `toPrimitives`.
- **Docblock pins:** the class docblock declares `@template TIdentifier of BusinessIdentifier`, and `sameIdentityAs()`'s declares `@param Entity<*>`. Cheap string assertions that pin the two decisions most likely to be "helpfully" refactored into the verified-broken forms.
- **Identity:** `id()` returns the exact instance supplied (`assertSame`) and the concrete identifier type; reflection reassignment against an **already-initialized** instance raises `\Error` (`test_initialized_identity_cannot_be_reassigned_through_reflection`, scoped by name to that case only); the forgetful fixture **constructs successfully and only fails when `id()` is reached** — asserted in that order, so the *latency* is what is pinned. Two further, discriminating tests cover `sameIdentityAs()` against the same fixture: comparing two same-class forgetful instances raises `\Error` (the exact-class check matches, so the uninitialized read is reached), while comparing a forgetful instance against a differently typed, correctly constructed entity returns `false` in both directions and never raises (the exact-class check fails first, so neither identifier is read) — proving the short-circuit is real and symmetric, not merely a happy-path assumption.
- **Property shadowing — discriminating, not confounded by entity type.** Two `ShadowingTestEntity` instances sharing the identical base `BusinessIdentifier` but carrying **different** shadowed `id` values are the same identity in both directions; two instances with **different** base identifiers but the identical shadowed value are **not** the same identity in either direction. Holding the entity type constant across both cases is what proves `Entity::$id` alone governs comparison — a same-type-vs-different-type comparison would prove nothing here, since the exact-runtime-class check alone would already force `false` or leave the shadowed value untested.
- **Equality:** same type and same identifier equal on distinct instances; same type and different identifiers unequal; **two entity types over the same identifier type and value unequal in both directions**; reflexive; symmetric asserted in both orders; transitive across three instances; **state mutated between assertions changes nothing**.
- **Cross-identifier-type comparison, through `sameIdentityAs()` itself.** A `PrimaryTestEntity` carrying an `AlphaTestIdentifier` and an `OtherIdentifierTestEntity` carrying a `BetaTestIdentifier`, both built from the **identical canonical UUID text**, compare unequal in both directions with no `\TypeError` — asserted by calling `sameIdentityAs()` directly, not by checking `BusinessIdentifier::equals()` alone. A direct `BusinessIdentifier::equals()` check between the two identifier types is also included as **supporting evidence only** and is not the sole assertion for this criterion.
- **Type safety:** a concrete entity's narrowed native constructor rejects a wrong identifier subtype with a `\TypeError`; a `ValueObject`-typed parameter rejects an `Entity` with a `\TypeError`. **Neither test carries a `@phpstan-ignore` annotation** — unnecessary, since `tests/` is outside `phpstan.neon.dist`'s analysed `paths`.
- **Relationship:** `Entity` is not an instance of `ValueObject`, and a `ValueObject` parameter rejects an entity with a `\TypeError`.
- **Hygiene:** no Laravel booted; `app/Modules` absent; `App\Foundation\Domain\Model` contains exactly `Entity.php` and `ValueObject.php`.
- **Fixtures are declared inside the test file** — two empty `final readonly` identifier markers (`AlphaTestIdentifier`, `BetaTestIdentifier`), a primary entity with mutable state, a second entity type over the **same** identifier type, a third entity type over the **differently typed** identifier, a fixture omitting `parent::__construct()`, and a fixture shadowing `id`; the last two documented as pinning the two language limits and explicitly **not** forms to copy. **No separate fixture file, and no fixture name carries business meaning.** No test asserts anything about what every potential future subclass can or cannot do.

**Security requirements.**

- **An identifier is not an authentication or authorization credential**, and possession of one grants no access.
- **Entity comparison performs no tenant, ownership, or Ethical Wall authorization.** `sameIdentityAs()` answers "is this the same record" and nothing more. `CheckEthicalWallAccess` remains Practice Management's sole authority, and `FirmContext` still derives only from verified identity and membership.
- **Identity is stable but not unforgeable.** `BusinessIdentifier::fromString()` permits reconstructing an *equal identifier value* from its canonical text at any time — it says nothing about whether an *entity* carrying that identifier can be constructed, which remains the owning module's own construction rule. "Same identity" proves nothing about provenance, authenticity, ownership, authorization, or entitlement. **Reflection-based construction bypass** (`ReflectionClass::newInstanceWithoutConstructor()` plus property assignment) **or a crafted `unserialize()` input can produce a forged instance carrying an arbitrary identifier** by skipping normal construction entirely; such a forged instance is likewise not proof of provenance and grants no authorization, ownership, or entitlement. This is a language limit PF-041 documents rather than attempts to close — no factory, guard, or serialization hook was added to prevent it.
- **`Entity` adds no custom stringification, serialization, or debugging API** — no `__toString()`, `\Stringable`, `\JsonSerializable`, `__debugInfo()`, `toArray()`, or dump helper, a **deliberate divergence from PF-044**'s approved `BusinessIdentifier::__toString()`. **The absence of these members is not a confidentiality control**: standard PHP debugging, `var_dump()`, reflection, or careless logging can still expose an entity's internal state regardless of what this base declares, and an entity must never be dumped, interpolated, serialized, or logged as though the base made that safe. `BusinessIdentifier` is not a secret, but an identifier can still be sensitive metadata and is not automatically safe to log or disclose; its approved `__toString()` does not make identifier text safe to log, disclose, or use for authorization.
- **No serialization, logging, telemetry, or external error rendering** is introduced.
- No tenant, `FirmId`, `FirmContext`, actor, principal, or session semantics enter Foundation.
- `sameIdentityAs()` is not constant-time and carries no timing guarantee.
- No existing security control or required check is weakened, renamed, bypassed, or removed.

**Documentation impact.** `app/Foundation/README.md`: the namespace table and its accompanying note record `App\Foundation\Domain\Model` as **partially implemented** — `ValueObject` and `Entity`, with `AggregateRoot` (PF-040) still a reservation that does not exist; the approved-order line records PF-041 implemented and PF-043 next; the `External dependencies` section records that PF-041 introduced none; and a new **`Entities`** subsection under `## Model` records the contract, the non-readonly trade-off and its permanent consequence, identity stability **and its limits**, the two documented language limits, the equality rule and its naming, the verified generics rules (invariant `@template`, mandatory `@extends`, `@param Entity<*>`, the prohibition on `Entity<BusinessIdentifier>` and `@template-covariant`, and the recommended narrowed constructor), the deliberate absence of `ValueObject`, the security position, and the exclusions. `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md` updated for status and sequencing only.

**Definition of Ready (met).** Goal clear and narrowly bounded to one base type; owner identified (repository owner); dependencies resolved (PF-044 and PF-042 both Done, no new dependency of any kind); exact contract approved and recorded above; identity, equality, mutability, and type-safety semantics settled, **including the explicit records that the generic guarantee is static-only, that the readonly prohibition is permanent, that a missed `parent::__construct()` fails latently, that property shadowing is possible, and that identity is stable but not unforgeable**; **generics behaviour empirically verified against PHPStan 2.2.5 on PHP 8.4.23 in the canonical container — level 5 clean, level 6 clean, `id()` narrowing confirmed, the invariance failure mode confirmed and avoided, and the `Entity<*>` wildcard confirmed supported**; exception position settled (throws nothing, invents no validation, adds no exception type); security implications identified, including the deliberate `__toString()` divergence from PF-044; tests specified and deliberately minimal; allowed and forbidden files enumerated; naming resolved by the repository owner (`id()`, `sameIdentityAs()`); no architecture blocker — `App\Foundation\Domain\Model` is the namespace `app/Foundation/README.md` already reserves for PF-041, PF-042 explicitly names PF-041 as the story that defines identity-based equality, and the PF-049 guard is a denylist that discovers the new file automatically and permits it unchanged.

**Definition of Done (met).** Acceptance criteria met; validated in the canonical Docker PHP 8.4 application container — `composer validate --strict`, `composer pint:test` (48 files), `composer phpstan` (level 5, 34 files, no errors, no baseline, suppression, or ignored error), the focused PF-041 suite (42 passed, 113 assertions), the full test suite (357 passed, 865 assertions), `composer audit --locked --abandoned=report`, and `npm audit --audit-level=high` all pass; the informational level-6 dry run recorded no errors with no configuration change; the PF-049 architecture guard passed unchanged (8 passed, 236 assertions); security and architecture reviewed; documentation updated; no critical defect; human approval recorded on the pull request before merge.

**Explicitly not implemented by PF-041.** **No concrete entity of any kind** — no Client, Matter, User, Firm, Task, Document, or any other business entity, and no name invented for one. PF-043 `DomainEvent`, PF-040 `AggregateRoot`, PF-045 `Money`, and PF-046 `Result` remain unimplemented, and no stub, placeholder, or empty directory exists for any of them. **No event recording, release, clearing, or dispatch**; no aggregate versioning or optimistic concurrency. No auditing or activity history. No timestamps, `createdAt`, `updatedAt`, or `deletedAt`; no soft deletion. No persistence, repository, ORM mapping, Eloquent model, migration, cast, API DTO, or route-model binding. No tenancy, `FirmId`, `FirmContext`, authorization, roles, permissions, ownership, or Ethical Wall decision. No `__toString`, `\Stringable`, serialization, hashing, ordering, or comparison beyond identity. No container binding, service provider, or bootstrap registration. No test double under `app/Foundation`. No concrete identifier subclass. No PHPStan level raise, baseline, or suppression. No business module, no `app/Modules`, no production deployment, no new dependency, no logging, telemetry, audit, metrics, or reporting.

### PF-043 — DomainEvent — Done

**Implementation status.** **Done — implemented and merged to `main`.** `app/Foundation/Domain/Event/DomainEvent.php` and `tests/Unit/Foundation/Domain/Event/DomainEventTest.php` exist, alongside this documentation. **Canonical Docker PHP 8.4 validation passed** — Pint (50 files), PHPStan level 5 (no errors, 35 files), an informational level-6 dry run (no errors), the focused PF-043 suite (24 tests, 73 assertions), the PF-049 architecture guard (8 tests, 253 assertions, unchanged), the full test suite (381 tests, 955 assertions), `composer validate --strict`, and both dependency audits, all clean. **Independent Opus 5 review and closure verification found no remaining P0, P1, P2, or P3 finding.** Security and architecture were reviewed; all four required `Protect main` checks (`PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit`) passed; the required human review comment was recorded on pull request #28; the pull request was merged to `main` through the protected-main workflow; local `main` was synchronized; and the implementation branch was deleted locally and remotely after merge. This entry records the repository owner's approval of the PF-043 pre-implementation analysis, the Candidate C contract below, and the Definition of Ready at the end of this entry, which authorized this implementation. **That authorization gate was not a pull-request gate, and no separate backlog-only pull request was required**: `CONTRIBUTING.md` requires **one approved story per pull request**, and this documentation and PF-043's implementation were the same approved story — so both were included in the same PF-043 pull request.

**Objective.** Establish the single framework-independent contract every OneLegalPro domain event satisfies, so "something happened" has one shared shape — a distinct, stable occurrence identity and a UTC occurrence instant — instead of each module inventing its own. It gives PF-040 `AggregateRoot` one type to record and release, and gives PF-091/PF-092 and `Integrations` one origin-established occurrence identity to correlate against, without exposing any internal event as a public contract.

**Scope.** The Foundation Domain event contract only: the `DomainEvent` interface, its isolated unit test, and the Foundation documentation recording it. Nothing else. **It implements no concrete domain event, no aggregate, no dispatcher, no outbox, and no envelope, and it names no business event.**

**Dependencies.** Seventh in the approved order. **PF-048 `UuidV7` is PF-043's only direct code dependency and its only import** — Done. **PF-042 `ValueObject`** is a **transitive code dependency**, reached because `UuidV7 implements ValueObject`, and additionally a **documentation contrast** — `DomainEvent` neither imports nor implements it, because occurrence identity and value equality are different relations — Done. **PF-047 `Clock`** is a **semantic and creation-path documentation reference only**: it is the sole approved origin of the instant a caller supplies to a concrete event's constructor. **`DomainEvent` neither imports `Clock` nor reaches it transitively in code** — `UuidV7`'s own imports are `InvalidArgument` and `ValueObject`, and nothing in that closure references `App\Foundation\Domain\Time` — Done. **PF-048 `UuidV7Generator` and `SystemUuidV7Generator`** are likewise **semantic and creation-path documentation references only** — the sole approved origin of the `UuidV7` a caller supplies — and are **neither direct nor transitive code dependencies** of `DomainEvent` — Done. **PF-049 is a documentation dependency only for the `DomainEvent` contract**: concrete events report failure through the taxonomy, while `DomainEvent` imports nothing from it, raises nothing, and declares no `@throws`. For review completeness, `InvalidArgument` does appear among `UuidV7`'s own imports, on its `fromString()` parsing path — a path `DomainEvent` never reaches, since it declares no parsing, reconstitution, or failing operation. **PF-041 `Entity` and PF-044 `BusinessIdentifier` are not dependencies of any kind** — both are named in documentation only, to record why an event is neither an entity nor a business identifier. **PF-040 `AggregateRoot` is a future *dependent* of PF-043, never a PF-043 dependency**; PF-043 contains no forward reference to it. It further depends on the completed repository-foundation tooling track (PF-020 through PF-032) and on the standing conventions in `app/Foundation/README.md`. **No new dependency of any kind.**

**Deliverables.**

- `App\Foundation\Domain\Event\DomainEvent` — `interface`; exactly two public methods; no constant, property, constructor, trait, or parent interface; no generic annotation.
- One pure unit test under `tests/Unit/Foundation/Domain/Event/`, with all fixtures declared inside it.
- `app/Foundation/README.md` updated for the `Event` namespace and a new `Events` section.

**Exact published contract.**

```php
namespace App\Foundation\Domain\Event;

use App\Foundation\Domain\Identity\UuidV7;

interface DomainEvent
{
    public function eventId(): UuidV7;

    public function occurredAt(): \DateTimeImmutable;
}
```

Two public members. No constant, no property, no constructor, no trait, no parent interface, no `@template`, no `equals()`, no `__toString()`, no serialization.

**An interface, not an abstract class, and the reasoning is permanent.** PHP's `readonly` inheritance is viral and bidirectionally exclusive — the reasoning `app/Foundation/README.md` already records as PF-042's permanent grounds for rejecting an abstract base. An abstract base would have forced *every* platform event to be `readonly`, or forbidden *every* one of them from being `readonly`, permanently; it would additionally have consumed each module event's single `extends` slot and imposed one base constructor shape on every event, where the approved convention leaves each concrete type's creation path to its own story. **`DomainEvent` must also never extend `Entity`:** `Entity` is deliberately **not** `readonly`, and PHP forbids a `readonly` class extending a non-`readonly` one, so an event derived from `Entity` could never be `final readonly` — a decisive technical disqualification, not a preference.

**Why exactly these two members.** They are the only two properties true of *every* domain event in this architecture — that a distinct occurrence happened, and when. Everything else varies per event or belongs to a later layer, and is enumerated in the exclusions below. **Interface widening is a breaking change for every implementor**, so unlike PF-042, PF-044, and PF-048 — which deferred members on final classes, where addition is purely additive — PF-043's members are settled now rather than deferred.

**Event-identity semantics.** `eventId()` supplies **stable occurrence identity**: identity, not value equality, distinguishes occurrences, so two events carrying identical payload data are different occurrences. **Within one in-memory event object, `eventId()` returns the exact `UuidV7` instance supplied at construction** — nothing regenerates, re-parses, or substitutes it — so `assertSame()` is the correct assertion against a reference fixture. **Across outbox persistence, serialization, reconstitution, publication, translation, and retries, what is preserved is the same canonical UUID *value*, not the same PHP object instance.** A reconstituted `UuidV7` is legitimately a different PHP object representing the same stable occurrence identity; equality at those boundaries is established by canonical text through `UuidV7::equals()` or `UuidV7::toString()`, never by object identity. A retry redelivers the same occurrence identity under a new delivery attempt and never mints a new event identity (`docs/architecture/07_API_Standards.md` §12, `docs/architecture/17_API_Integration_Platform_Architecture.md` §26). **PF-043 ships no persistence, serialization, or reconstitution path, so its own tests can demonstrate the in-memory instance property only** — cross-boundary value preservation is an obligation on PF-091, PF-092, and `Integrations`, not a property any PF-043 test can prove.

**Deduplication — a source of keys, not a key.** `DomainEvent::eventId()` is the **stable occurrence identifier from which Workflow, outbox, publication, or integration layers may derive their own correlation or deduplication keys.** **PF-043 itself performs no deduplication, defines no deduplication store, defines no consumer-idempotency contract, defines no delivery mechanism, and does not require any downstream layer to use the raw internal identifier verbatim.** Consumer idempotency belongs to PF-093; the outbox record and its atomicity to PF-091; delivery attempts, their separate delivery identifiers, and the consumer-facing stable identifier to `Integrations`. **Internal-to-external mapping remains deferred to `Integrations`**, and an internal identifier is never exposed externally merely for convenience — `docs/architecture/01_OneLegalPro_Constitution.md`, Article 31. Identity enables at-least-once delivery to be made safe by consumers; it creates **no exactly-once guarantee** (Articles 34 and 43) and **no ordering guarantee** of any kind.

**Identifier type — the existing `UuidV7` primitive, and no new identifier type.** `eventId()` returns **the existing `UuidV7` technical identifier primitive directly**. `UuidV7` (PF-048) **is an existing concrete technical identifier primitive** that already supplies strict validation, canonical text, and exact-runtime-type equality, so **PF-043 introduces no new identifier type of any kind**. What PF-043 must not create is narrower and stricter than "a concrete identifier": **it must not create `DomainEventId`, and it must not create any concrete `BusinessIdentifier` subclass.** The standing rule in `app/Foundation/README.md` — that Foundation holds no concrete identifier "and none ever will" — is about **concrete business-identifier leaves**, the empty `final readonly` subclasses of PF-044's `BusinessIdentifier` base that belong to owning modules. **It does not deny that `UuidV7` is an existing concrete technical identifier primitive**, and it does not prohibit consuming it. The distinction is the reason `UuidV7` is correct here: **an event occurrence identifier is technical occurrence identity, not a business identifier** — it identifies a technical occurrence record, not a business thing, so wrapping it in a typed `BusinessIdentifier` leaf would misclassify it as well as create a type PF-043 is not authorized to create.

**Occurrence-time semantics.** `occurredAt()` returns the business instant in **UTC**, typed `\DateTimeImmutable` — deliberately not `\DateTimeInterface`, which is satisfied by the mutable `\DateTime` and would let a caller mutate the value in place, the reasoning PF-047 already recorded. The instant originates from the injected PF-047 `Clock`, read **once per logical operation** and reused across every event, audit entry, and outbox row that operation writes; a concrete event **receives it as constructor data and never reads ambient time or holds a `Clock`**. Within one in-memory event object, `occurredAt()` returns the exact `\DateTimeImmutable` instance supplied; across persistence and reconstitution, the same **instant value** is preserved, not the same PHP object. **Five distinct times are never conflated:** occurred-at (the only one on this contract) → recorded-at → committed-at → published-at → delivered-at. **Microsecond precision is permitted but deliberately unspecified.** A timestamp promises **no ordering, monotonicity, authenticity, trusted time, or non-repudiation** — `Clock` is a wall clock that may be corrected backwards, so two events may share an instant and a later event may carry an earlier one. `occurredAt()` is never a sort key, deduplication key, or idempotency key. Timezone conversion, Firm office timezones, and court-deadline timezones all belong to the owning business module.

**The plain-native-`\DateTimeImmutable` rule.** Concrete domain events return an instance whose **exact runtime class is `\DateTimeImmutable`** — never `Carbon\CarbonImmutable` or any other framework-specific or vendor subclass. **PHP return-type covariance permits a subclass to satisfy the declared return type, so the interface cannot enforce this mechanically.** It is a **review obligation on every concrete event story and a per-concrete-event test obligation**: each concrete event's own test asserts the exact runtime class, not merely `instanceof`. The PF-049 architecture guard inspects only `app/Foundation` and cannot catch a violation inside a module.

**Immutability and payload rules.** **Concrete module-owned events are `final readonly` classes by architectural convention**, consistent with the standing rule that value objects and domain events are immutable by default. **An interface can enforce none of this** — `final readonly`, UTC, the plain-`\DateTimeImmutable` rule, and payload safety are all **review and per-concrete-event-test obligations**, the same class of rule as PF-044's empty-marker-subclass rule and PF-041's mandatory `@extends`. `readonly` is shallow, so an event holds immutable values only: identifiers, `\DateTimeImmutable`, and immutable value objects are permitted; **`Entity` and aggregate references are prohibited**, because a non-`readonly` entity carrying mutable lifecycle state would let an event's observed content drift after the fact and would leak a mutable aggregate across a module boundary. **Events carry identifiers and immutable snapshots, never live domain objects.** Under the platform safe-payload rule (`docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §81), an event payload **never** carries confidential, privileged, credential, secret, document-byte, payment-secret, or cross-Firm content; consumers needing detail issue an authorized query. **Satisfying this contract makes no event safe to log, dump, serialize, expose, or publish**, and the absence of stringification and serialization members is **not** a confidentiality control.

**Exceptions.** **PF-043 throws nothing and invents no validation in order to use the taxonomy.** Two accessors returning stored values have no failure path. No PF-049 exception is imported, raised, or documented as thrown, and **no new exception type is created**. Invalid event construction belongs to each concrete module event, which throws `InvalidArgument` or `InvariantViolation` from its own creation path. Publication and delivery failures are outside this contract entirely — PF-090, PF-091, PF-092, and `Integrations`. **`DomainEvent` returns `Result` for nothing**, per the standing prohibited-dependency-direction rule above.

**PF-040 compatibility, stated minimally.** PF-043 gives PF-040 one nominal type to collect, so PF-040 can declare `list<DomainEvent>` with no generics, bound, or template propagation; declares no constructor, state, or template, so it constrains nothing about how PF-040 stores, appends, releases, or clears; declares **no dispatch member of any kind**; and imports nothing from a framework or an outbox. Recording order is a property of PF-040's own insertion-ordered list — **PF-043 makes no ordering promise and must not be read as making one**, and PF-040 must never claim global or exactly-once ordering. **PF-040's own API is deliberately not designed here.**

**Allowed files.**

- Created: `app/Foundation/Domain/Event/DomainEvent.php`, `tests/Unit/Foundation/Domain/Event/DomainEventTest.php`.
- Modified: `app/Foundation/README.md` (Foundation convention record), and — for status and sequencing only — `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md`.

**Forbidden files.** No dependency or lock file (`composer.json`, `composer.lock`, `package.json`, `package-lock.json`); no tooling configuration (`phpunit.xml`, `phpstan.neon.dist`, `pint.json`) — **no PHPStan level change, baseline, suppression, or ignored error**; no `.github/`, `.githooks/`, Docker, environment, or deployment file; no Laravel configuration, bootstrap file, service provider, container binding, route, or controller; no migration; **no existing PHP source file**, including the PF-042 `ValueObject`, the PF-041 `Entity`, the PF-044 `BusinessIdentifier`, all three PF-048 Identity types, the PF-049 exception classes, and the PF-047 `Clock`/`SystemClock`; **no change to `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php`** — the PF-049 guard must pass unchanged; **no change to `tests/Unit/Foundation/Domain/Identity/UuidV7Test.php`**, whose PHP 8.5 reflection behaviour is separate work explicitly outside PF-043; no change to any other existing test; no separate test-fixture file; no `README.md`, `AGENTS.md`, or `CONTRIBUTING.md`; no architecture or ADR file; no `app/Modules`; no Dependabot branch. `docs/implementation/01_Implementation_Sprint_Plan.md` is not modified: its catalogue and approved order already record PF-043 correctly and it carries no per-story status field — the same reasoning PF-047, PF-042, PF-048, PF-044, and PF-041 applied.

**Acceptance criteria.**

- Exactly one new source type exists, in `App\Foundation\Domain\Event`, an **interface**, extending nothing and implementing nothing.
- Exactly two public, non-static, zero-parameter methods: `eventId()` returning non-nullable `UuidV7`, and `occurredAt()` returning non-nullable `\DateTimeImmutable`.
- No constant, property, constructor, trait, parent interface, or `@template` annotation is declared.
- Not `\Stringable`, `\JsonSerializable`, `\ArrayAccess`, `\Traversable`, or `\Serializable`; does not extend or implement `ValueObject` or `Entity`.
- None of the named-absence members exists: `equals`, `__toString`, `jsonSerialize`, `toArray`, `toPrimitives`, `fromPrimitives`, `hash`, `__debugInfo`, `eventName`, `eventType`, `eventVersion`, `payload`, `aggregateId`, `subjectId`, `firmId`, `actorId`, `correlationId`, `causationId`, `recordedAt`, `publishedAt`, `deliveredAt`, `deliveryId`, `attemptNumber`, `dispatch`, `handle`, `publish`.
- **PF-043's only import is `App\Foundation\Domain\Identity\UuidV7`.** No `Clock`, `UuidV7Generator`, `SystemUuidV7Generator`, `Entity`, `BusinessIdentifier`, `ValueObject`, or PF-049 exception is imported; `ValueObject` is reached only transitively through `UuidV7`.
- A `final readonly class` can implement the contract, proven by the reference fixture.
- **Within one in-memory event instance**, `eventId()` returns the exact `UuidV7` instance supplied and `occurredAt()` the exact `\DateTimeImmutable` instance supplied (`assertSame`). **No acceptance criterion asserts object-instance preservation across persistence, serialization, reconstitution, publication, or retry** — PF-043 ships none of those paths; cross-boundary preservation is of the canonical UUID value and the same instant, and is an obligation on PF-091, PF-092, and `Integrations`.
- The reference fixture's instant carries timezone name `UTC` and offset `0`, and **its exact runtime class is `\DateTimeImmutable`**, asserted by exact class comparison rather than `instanceof`, with the documented record that covariance makes this a review obligation rather than an enforced property.
- Two events built from identical payload data but different `eventId` values are distinct occurrences, and their identifiers are unequal.
- The contract performs **no deduplication** and defines **no deduplication store, consumer-idempotency contract, correlation key, delivery mechanism, or delivery identifier**, and **imposes no requirement that any downstream layer reuse the internal identifier verbatim**; internal-to-external mapping is `Integrations`' decision under Constitution Article 31.
- No Laravel, Illuminate, Eloquent, **Carbon**, Symfony, PSR, service-container, persistence, serialization, tenancy, or authorization dependency.
- **`eventId()` returns the existing `UuidV7` primitive and PF-043 introduces no new identifier type**: no `DomainEventId` exists, and **no concrete `BusinessIdentifier` subclass** exists, in production code or in the test file.
- **No concrete domain event, aggregate, dispatcher, outbox, or envelope exists in production code**; `app/Modules` was not created.
- `strict_types=1` declared; namespace matches path; PHP global types take a leading backslash.
- **The PF-049 architecture-guard file is byte-identical and passes unchanged**, automatically inspecting `DomainEvent.php` along with every other Foundation source file it already covers. Its assertion count grows as a natural consequence of iterating one more file; this criterion does not pin a permanent assertion count. Every other pre-existing test also passes unchanged, and **`tests/Unit/Foundation/Domain/Identity/UuidV7Test.php` is not modified by this story**.
- **No PHP source or test file declares or uses an `@phpstan-ignore` or `@phpstan-ignore-next-line` suppression tag, and no PHPStan baseline, suppressed error, ignored error, or configuration ignore was added.**
- Validated in the canonical Docker PHP 8.4 application container: Pint clean; **PHPStan level 5 clean with no baseline, suppression, or ignored error**; a **level-6 dry run recorded as informational only** — no configuration change and no level raise; the focused PF-043 tests pass; the full suite passes (baseline before this story, in the canonical container: 357 passed, 865 assertions); `composer validate --strict` and both dependency audits pass; all four required `Protect main` checks (`PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit`) pass and retain their exact names.

**Tests.** One file, `tests/Unit/Foundation/Domain/Event/DomainEventTest.php`, extending `PHPUnit\Framework\TestCase` directly, booting no Laravel application and adding no test dependency. Deliberately small — the contract has no parsing, no rejection matrix, and no generation path. All fixtures are declared **inside the test file**, per the standing rule that no test double belongs in `app/Foundation`; **no separate fixture file, and no fixture name carries business meaning.**

- **Shape, by reflection:** an interface; extends no interface; exactly two methods; method names exactly `['eventId', 'occurredAt']`; both public, non-static, zero-parameter; `eventId()` returns non-nullable `UuidV7`; `occurredAt()` returns non-nullable `\DateTimeImmutable`; no constant; no property; neither `\Stringable`, `\JsonSerializable`, `\ArrayAccess`, `\Traversable`, nor `\Serializable` inherited; the named-absence list above absent. **Reflection assertions use fully-qualified class names, never `'self'` or `'static'`.**
- **Docblock pin:** the class docblock declares no `@template` tag — a cheap assertion pinning the decision that this contract consumes no template parameter.
- **Implementability:** a `final readonly class` may implement the contract — the property an interface exists to preserve, and the one an abstract base would have destroyed.
- **In-memory instance preservation:** `eventId()` returns the exact `UuidV7` instance supplied and `occurredAt()` the exact `\DateTimeImmutable` instance supplied (`assertSame`), catching an implementation that regenerates or re-parses **within one object**. Documented as demonstrating reference semantics; **retry and cross-boundary stability are not provable here**, since PF-043 ships no persistence or reconstitution path.
- **Occurrence time:** the fixture's instant carries timezone name `UTC` and offset `0`, and its **exact runtime class is `\DateTimeImmutable`** — catching a Carbon or other vendor subclass being returned. Documented as demonstrating the standing rule, which the interface cannot enforce.
- **Distinct occurrences:** two events built from identical payload data but different identifiers are distinct occurrences and their identifiers are unequal — pinning the decision that events never acquire value equality.
- **Immutability demonstration:** writing to the readonly fixture's property throws `\Error`.
- **Relationships:** the fixture is not an instance of `ValueObject` and not an instance of `Entity`.
- **Hygiene:** no Laravel booted; `app/Modules` absent; `app/Foundation/Domain/Event` contains exactly `DomainEvent.php`.
- **Deliberately avoided:** tautological assertions; fragile total test or assertion counts; re-testing PHPUnit or PHP itself; `sleep()`, `usleep()`, or `hrtime()`; ambient-time assertions; elapsed-time tolerances; nonzero-microsecond assertions; strict-increase or ordering assertions; randomness or collision-impossibility claims; and any assertion pretending an interface can enforce concrete implementation behaviour it cannot enforce — UTC, `final readonly`, the plain-`\DateTimeImmutable` rule, and payload safety are all documented as review obligations, never as enforced properties.

**Security requirements.**

- No tenancy, `FirmId`, `FirmContext`, actor, principal, or session semantics enter Foundation.
- No authentication, authorization, or Ethical Wall decision is introduced. `CheckEthicalWallAccess` remains Practice Management's sole authority, and **receiving an event is never authorization to read its subject.**
- **`eventId()` is an identifier, never a secret**, capability, session token, or proof of provenance, authenticity, ownership, non-repudiation, or entitlement. It is **not an authorization input**, and possession of an event or its identifier grants nothing.
- **A `UuidV7` exposes its generation time to millisecond precision to anyone holding it**, by construction — its 48-bit timestamp prefix is fully predictable. That generation timestamp is **additional technical metadata carried alongside `occurredAt()`, not a duplicate of it**: **the presence of `occurredAt()` neither cancels nor erases the UUIDv7 disclosure**, and the two must never be conflated. **Generation time and occurrence time may differ and must never be assumed equal** — the identifier may be generated before, during, or after the instant the event describes. Like any identifier, an event identifier **may be sensitive metadata and is never automatically safe to log, dump, disclose, or expose**; rendering or recording it is a deliberate decision by the calling code, never a licence granted by this contract.
- **`eventId()` is never a time source, never an ordering key, never an authorization input, never proof of provenance, and never proof of authenticity.**
- **Event payloads carry identifiers and safe metadata only** — never credentials, secrets, payment tokens or secrets, bank details, document bytes, knowledge body text, privileged narratives, cross-client content, or another Firm's data.
- **Satisfying this contract makes no event safe to log, dump, serialize, expose, or publish**, and **the absence of stringification and serialization members is not a confidentiality control**: `var_dump()`, reflection, stack traces, and careless logging still expose everything.
- **Internal domain events are never the permanent public contract** (Constitution Article 31). The versioned `IntegrationEventEnvelope` is authored deliberately by `Integrations`, and confidential fields never cross into it merely because they exist internally.
- **Delivery is at-least-once, with no exactly-once claim and no global-ordering claim** (Articles 34 and 43).
- **Actor provenance for human, service, integration, and AI actors** (Articles 35 and 41) is recorded by the owning module's payload and by IdentityAccess audit records. **PF-043 must never be described as satisfying that requirement.**
- No existing security control or required check is weakened, renamed, bypassed, or removed.

**Documentation impact.** `app/Foundation/README.md`: the namespace table records `App\Foundation\Domain\Event` as **Implemented**; the accompanying note and the approved-order line record PF-043 implemented and PF-040 next; the `External dependencies` section records that PF-043 introduced none; and a new **`## Events`** section records the contract, the interface-not-abstract-class reasoning and its permanence, why extending `Entity` would make `final readonly` events impossible, occurrence identity rather than value equality, the in-memory-instance versus cross-boundary-value distinction, the deduplication position, the UTC obligation and the honest statement that an interface cannot enforce it, the plain-native-`\DateTimeImmutable` rule, the five distinct times, every non-guarantee, the `final readonly` convention as a review obligation, the payload rules, the security position, and the full named-absence list. `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md` updated for status and sequencing only.

**Definition of Ready (met, approved by the repository owner).** Goal clear and narrowly bounded to one contract type; owner identified (repository owner); **dependencies resolved and correctly classified** — PF-048 `UuidV7` is the only direct code dependency and the only import, PF-042 `ValueObject` is transitive in code through `UuidV7` and a documentation contrast, **PF-047 `Clock`, PF-048 `UuidV7Generator`, and `SystemUuidV7Generator` are semantic and creation-path documentation references only and are neither direct nor transitive code dependencies**, PF-049 is documentation only for this contract, PF-041 `Entity` and PF-044 `BusinessIdentifier` are documentation contrasts rather than dependencies, PF-040 is a future dependent and never a dependency, and there is no new dependency of any kind; exact contract approved and recorded above; the interface-not-abstract-class decision settled on PF-042's already-recorded permanent reasoning, with the additional finding that extending `Entity` would make `final readonly` events technically impossible; **event-identity semantics settled** — identity on the originating event rather than introduced by an outbox or envelope, the **existing `UuidV7` technical identifier primitive used directly, introducing no new identifier type**, with **`DomainEventId` prohibited and any concrete `BusinessIdentifier` subclass prohibited** — the standing `app/Foundation/README.md` rule concerning concrete business-identifier leaves being no bar to consuming `UuidV7`, because an event occurrence identifier is technical occurrence identity rather than a business identifier — and the explicit distinction between **in-memory instance preservation** (assertable here) and **cross-boundary canonical-value preservation** (an obligation on PF-091, PF-092, and `Integrations` that no PF-043 test can prove); **deduplication semantics settled** — `eventId()` is the stable occurrence identifier **from which** later layers may derive their own correlation or deduplication keys, with PF-043 performing no deduplication, defining no store, idempotency contract, or delivery mechanism, and requiring no verbatim downstream reuse, and internal-to-external mapping deferred to `Integrations` under Constitution Article 31; **occurrence-time semantics settled** — `\DateTimeImmutable`, UTC mandatory by documented contract, the clock read once per logical operation and supplied as constructor data, distinct from the four other times, promising no ordering, monotonicity, or authenticity, and precision deliberately unspecified; **the `\DateTimeImmutable` covariance question resolved rather than left open** — concrete events return a plain native `\DateTimeImmutable`, never Carbon or another framework or vendor subclass, with the honest record that covariance makes this a review and per-concrete-event-test obligation rather than an enforced property; immutability and payload rules settled, including the explicit record that a PHP interface can enforce neither `final readonly`, nor UTC, nor the plain-`\DateTimeImmutable` rule, nor payload safety; exception position settled (throws nothing, adds no exception type, returns no `Result`, invents no validation); method naming approved by the repository owner (`eventId()`, `occurredAt()`); PF-040 compatibility stated minimally and PF-040 otherwise deferred entirely; security implications identified; tests specified and deliberately minimal; allowed and forbidden files enumerated; and **no architecture blocker** — `App\Foundation\Domain\Event` is the namespace `app/Foundation/README.md` already reserves for PF-043, the PF-049 guard is a denylist that discovers the new file automatically and permits it unchanged, and PF-090, PF-091, PF-092, and PF-093 already own dispatch, outbox, publication, and consumption as separate approved Sprint 0.4 stories. **The pre-existing PHP 8.5 reflection behaviour in `tests/Unit/Foundation/Domain/Identity/UuidV7Test.php` is explicitly outside PF-043's scope and requires its own separately approved story; PF-043's "no existing test may change" constraint stands unamended.**

**Definition of Done (met).** Acceptance criteria met. Validated in the canonical Docker PHP 8.4 application container: `composer validate --strict` valid; `composer pint:test` passed (50 files); `composer phpstan` (level 5, no errors, 35 files, no baseline, suppression, or ignored error); an informational level-6 dry run recorded no errors, with no configuration change; the focused PF-043 suite passed (24 tests, 73 assertions); the full test suite passed (381 tests, 955 assertions); the PF-049 architecture guard passed unchanged (8 tests, 253 assertions); `composer audit --locked --abandoned=report` and `npm audit --audit-level=high` both clean. Security and architecture reviewed; independent Opus 5 review and closure verification complete, with no actionable P0, P1, P2, or P3 finding remaining. Documentation updated; no critical defect. All four required `Protect main` checks (`PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit`) passed; the required human review comment was recorded on pull request #28; human approval was recorded before merge; the pull request was merged to `main`; and the implementation branch was deleted locally and remotely after merge.

**Explicitly not implemented by PF-043.** **No concrete domain event of any kind** — no `MatterOpened`, `InvoiceIssued`, `DocumentUploaded`, or any other, and no name invented for one. PF-040 `AggregateRoot`, PF-045 `Money`, and PF-046 `Result` remain unimplemented, and no stub, placeholder, or empty directory exists for any of them. **No event recording, release, clearing, or dispatch**; no dispatcher, listener, subscriber, handler, bus, queue, job, broadcaster, or `ShouldQueue`. No transactional outbox, publisher, consumer, dead-letter, retry, backoff, or deduplication store. No `IntegrationEventEnvelope`, delivery attempt, delivery identifier, webhook, signature, or external contract version. No serialization, persistence mapping, migration, Eloquent cast, DTO, route-model binding, or wire format. No event name, type, or version member. **No new identifier type of any kind — no `DomainEventId`, and no concrete `BusinessIdentifier` subclass.** `eventId()` consumes the existing `UuidV7` primitive (PF-048) and PF-043 creates no identifier type of its own. No correlation, causation, actor, Firm, or tenancy member. No auditing, logging, telemetry, metrics, or reporting. No container binding, service provider, or bootstrap registration. No test double under `app/Foundation`. No PHPStan level raise, baseline, or suppression. No change to `UuidV7Test` or any other existing test. No business module, no `app/Modules`, no production deployment, no new dependency.

### PF-040 — AggregateRoot — Done

**Implementation status. Done.** `app/Foundation/Domain/Model/AggregateRoot.php` and `tests/Unit/Foundation/Domain/Model/AggregateRootTest.php` were delivered through PR #31, alongside the approved docblock-only correction to `Entity.php` and the authorized documentation. Implementation commit `f02b077` and correction commit `7fbb928` passed the protected workflow; independent review and focused correction confirmation completed; all four required `Protect main` checks passed on the final head; the required human approval comment was recorded; PR #31 merged to `main` as `5ea91dc`; and the implementation branch was deleted locally and remotely. This entry records the repository owner's approval of the PF-040 pre-implementation analysis and decisions D1, D2, and D3.

**Objective.** Establish the single framework-independent base every OneLegalPro aggregate root extends, so an aggregate collects the domain events its own behaviour produced through one buffer, one protected append, and one public take-and-clear — instead of each module inventing its own recording API, its own release semantics, and its own answer to who may attribute an event to an aggregate. It completes `App\Foundation\Domain\Model` and gives PF-091's outbox and the future `Firm`, `Matter`, and other module aggregates one contract to record against.

**Scope.** The Foundation Domain aggregate-root base only: the `AggregateRoot` class, its isolated unit test, a docblock-only correction to `Entity`, and the Foundation documentation recording it. Nothing else. **It implements no concrete aggregate, no concrete domain event, no dispatcher, no outbox, no repository, and no persistence path, and it names no business aggregate.**

**Dependencies.** Eighth in the approved order. **PF-041 `Entity` is the parent class** — Done. **PF-043 `DomainEvent` is the recorded type and PF-040's only behavioural import** — Done. **PF-044 `BusinessIdentifier` is imported solely as the `@template` bound**, reached in code only transitively through `Entity`'s constructor parameter — Done. **PF-047 `Clock` and PF-048 `UuidV7`/`UuidV7Generator` are neither direct nor transitive code dependencies of `AggregateRoot`** — they are the approved origin of a concrete event's supplied instant and identifier, a creation-path documentation reference belonging to the recording event, not to this base. **PF-049 is a documentation dependency only**: PF-040 imports nothing from the taxonomy, raises nothing, and declares no `@throws`. **PF-042 `ValueObject` is not a dependency of any kind.** **PF-090, PF-091, PF-092, and PF-093 are future *dependents* of PF-040, never dependencies**; PF-040 contains no dispatch, outbox, publication, or consumption code and no forward implementation of any of them. It further depends on the completed repository-foundation tooling track (PF-020 through PF-032) and on the standing conventions in `app/Foundation/README.md`. **No new dependency of any kind.**

**Approved decisions.**

- **D1 — no aggregate version.** `AggregateRoot` contains no aggregate version, and none may be added by a Foundation story. Optimistic-concurrency versioning exists to reconcile a write against what a store already holds, so it is a **persistence concern outside Foundation**, owned by a later, separately approved persistence story. **`Entity`'s docblock was stale on exactly this point** — it assigned "aggregate versioning and event release to PF-040" — and PF-040 corrects it: event recording and release are PF-040's; versioning is neither PF-040's nor Foundation's. This is the sole authorized modification to an existing production file, and it is **docblock-only**: no signature, member, modifier, or line of executable code in `Entity` changed.
- **D2 — `recordThat()`, protected and final.** `protected final function recordThat(DomainEvent $event): void`. Recording is the aggregate's own account of what it did, so no caller outside the aggregate may attribute an event to it; `final` prevents a subclass widening that visibility or slipping behaviour into the append.
- **D3 — `releaseEvents()`, one combined operation.** `public final function releaseEvents(): array`, returning `list<DomainEvent>`. **No separate peek, read, count, clear, flush, discard, or public recording method exists.** A reader that could look without taking would invite two callers to publish the same batch; a clear that discarded without returning would silently destroy recorded history.

**Keyword order — the one recorded deviation from D2 and D3 as literally written.** The approved signatures were written `protected final` and `public final`. **PSR-12 requires `abstract`/`final` to precede the visibility keyword, and Pint's stock Laravel preset enforces it** through its `modifier_keywords` fixer — verified empirically against Pint in the canonical container, where the literal order fails the check. The implementation therefore declares **`final protected function recordThat(...)`** and **`final public function releaseEvents(): array`**. **Visibility and finality are exactly as approved**; only the keyword order differs, it is semantically identical, and it matches `Entity`'s existing `final public function id()`. **No Pint rule, exclusion, or preset change was made** to accommodate the approved wording, and none may be.

**Deliverables.**

- `App\Foundation\Domain\Model\AggregateRoot` — `abstract class extends Entity`; one private, non-static, non-readonly `list<DomainEvent>` buffer defaulting to `[]`; exactly two declared members, `final protected recordThat()` and `final public releaseEvents()`; no constructor, constant, static property, or static method; `@template TIdentifier of BusinessIdentifier` and `@extends Entity<TIdentifier>`.
- One pure unit test under `tests/Unit/Foundation/Domain/Model/`, with all fixtures declared inside it.
- `app/Foundation/Domain/Model/Entity.php` — **docblock correction only**, per D1.
- `app/Foundation/README.md` updated for the `Model` namespace and a new `Aggregate roots` subsection.

**Exact published contract.**

```php
namespace App\Foundation\Domain\Model;

use App\Foundation\Domain\Event\DomainEvent;
use App\Foundation\Domain\Identity\BusinessIdentifier;

/**
 * @template TIdentifier of BusinessIdentifier
 *
 * @extends Entity<TIdentifier>
 */
abstract class AggregateRoot extends Entity
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    final protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /** @return list<DomainEvent> */
    final public function releaseEvents(): array
    {
        $released = $this->recordedEvents;

        $this->recordedEvents = [];

        return $released;
    }
}
```

Two declared members. No constructor, no constant, no static member, no interface, no `readonly`, no version, no dispatch.

**Recording semantics.** `recordThat()` **appends and inspects nothing** — no validation, filtering, sorting, reordering, deduplication, merging, capping, or rejection of any kind — and retains the exact instance supplied. Recording the identical instance twice records it twice; two distinct events carrying identical identifier and instant data are two entries. Whether either is meaningful is the recording aggregate's own business rule, never this base's guess. **Nothing is dispatched, persisted, published, or logged by the call.**

**Release semantics.** Release returns the batch in **recording order** and empties the buffer within the same operation, so **no partial or half-released state is observable**: a repeated release returns `[]`, and anything recorded afterwards is a **fresh batch** owing nothing to the previous one. Releasing from an aggregate that recorded nothing returns `[]` rather than failing — "no events" is a normal state, not a broken invariant. The returned value is a plain PHP array, so **a caller mutating it changes only its own copy** and can never reach back into the aggregate.

**Ordering, stated as a non-guarantee.** Recording order is a property of an insertion-ordered list and **nothing more**. It is **not a chronological, causal, global, cross-aggregate, cross-process, or delivery ordering guarantee, and must never be read as one.** `DomainEvent::occurredAt()` originates from PF-047's wall clock, which may be corrected backwards, so a later-recorded event may carry an earlier instant — `AggregateRoot` neither detects, corrects, nor reports that, and **never sorts by it**. **No exactly-once, at-least-once, ordering, or delivery claim is made**, consistent with Constitution Articles 34 and 43; this base delivers nothing.

**Identity is untouched.** The buffer is **never part of identity**. `sameIdentityAs()` is inherited unchanged and still compares exact runtime entity class then identifier only, so recording, releasing, and re-recording change nothing about whether two aggregates are the same aggregate, and an identical event history never makes two different aggregate types match. The buffer is **per instance and private**, and **no static property holds event state**, so two aggregates never share, see, or affect each other's events.

**Reconstitution, stated honestly.** **Reconstituting an aggregate from storage must never route through `recordThat()`** — replaying history is not new behaviour, and a reconstitution path that recorded would re-publish the past. How reconstitution avoids that is the owning persistence story's problem. **PF-040 cannot enforce it**, and shipping no reconstitution path is not a guarantee that a later one will be written correctly.

**Exceptions.** **PF-040 throws nothing and invents no validation in order to use the taxonomy.** Appending to a list and swapping it for an empty one have no failure path. No PF-049 exception is imported, raised, or documented as thrown, and **no new exception type is created**. `AggregateRoot` returns `Result` for nothing, per the standing prohibited-dependency-direction rule above.

**Allowed files.**

- Created: `app/Foundation/Domain/Model/AggregateRoot.php`, `tests/Unit/Foundation/Domain/Model/AggregateRootTest.php`.
- Modified: `app/Foundation/Domain/Model/Entity.php` (**docblock correction only**, per D1), `app/Foundation/README.md` (Foundation convention record), `tests/Unit/Foundation/Domain/Model/EntityTest.php` (**the stale directory-inventory expectation only**, added to this list by explicit owner authorization during implementation — see the paragraph below), and — for status and sequencing only — `docs/implementation/01_Implementation_Sprint_Plan.md`, `docs/implementation/03_Engineering_Backlog.md`, and `docs/PROJECT_STATUS.md`.

**Forbidden files.** No dependency or lock file (`composer.json`, `composer.lock`, `package.json`, `package-lock.json`); no tooling configuration (`phpunit.xml`, `phpstan.neon.dist`, `pint.json`) — **no PHPStan level change, baseline, suppression, or ignored error, and no Pint rule, exclusion, or preset change**; no `.github/`, `.githooks/`, Docker, environment, or deployment file; no Laravel configuration, bootstrap file, service provider, container binding, route, or controller; no migration; **no existing PHP source file other than the docblock-only `Entity.php` correction**, and specifically not the PF-042 `ValueObject`, the PF-043 `DomainEvent`, the PF-044 `BusinessIdentifier`, all three PF-048 Identity types, the PF-049 exception classes, or the PF-047 `Clock`/`SystemClock`; **no change to `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php`** — the PF-049 guard must pass unchanged; **no change to any existing test other than the owner-authorized inventory expectation in `EntityTest.php` described below, and no existing test or assertion removed or weakened anywhere**; no `README.md`, `AGENTS.md`, or `CONTRIBUTING.md`; no architecture or ADR file; no `app/Modules`; no Dependabot branch. `docs/implementation/01_Implementation_Sprint_Plan.md` may change only to correct its stale historical PF-040 status; no catalogue, approved order, architecture decision, or implementation scope changes.

**One existing test's stale inventory assertion, resolved by explicit owner authorization.** `tests/Unit/Foundation/Domain/Model/EntityTest.php` asserted that `app/Foundation/Domain/Model` contains exactly `Entity` and `ValueObject`. **Creating `AggregateRoot.php` makes that factual inventory assertion false by design.** It was the **only** existing test affected — the PF-049 guard, `ValueObjectTest`, `DomainEventTest`, and every Identity, Time, and Exception test pass unchanged — and the failure was a stale inventory expectation, not a defect in `Entity` or in `AggregateRoot`. That file was **not** in PF-040's originally approved allowed-file list; **the repository owner explicitly extended the list by that one file for that one change during implementation.** The expected inventory is now `['AggregateRoot', 'Entity', 'ValueObject']` and the method renamed `test_domain_model_contains_only_the_three_approved_model_contracts()`. **Nothing else in `EntityTest.php` changed: no assertion was deleted or weakened, no test removed, and no fixture altered** — the directory guard is preserved at full strength, not relaxed. **`tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php` remains byte-identical.**

**Acceptance criteria.**

- Exactly one new source type exists, in `App\Foundation\Domain\Model`: an `abstract class` extending `Entity` and implementing nothing.
- Not `readonly`; declares no constructor of its own (the inherited `Entity` constructor is the only one), no constant, no static property, and no static method.
- **Exactly one declared property**: private, non-static, **not** `readonly`, natively typed non-nullable `array`, annotated `list<DomainEvent>`, defaulting to `[]`.
- **Exactly two declared methods**: `recordThat()` — `protected`, `final`, non-static, exactly one required non-nullable `DomainEvent` parameter, returning `void`; and `releaseEvents()` — `public`, `final`, non-static, zero parameters, returning non-nullable `array`.
- The public surface is exactly `id()`, `sameIdentityAs()`, and `releaseEvents()`.
- None of the prohibited members exists: `peekEvents`, `events`, `domainEvents`, `recordedEvents`, `pullDomainEvents`, `clearEvents`, `flushEvents`, `discardEvents`, `hasEvents`, `eventCount`, `record`, `recordEvent`, `raise`, `apply`, `replay`, `reconstitute`, `dispatch`, `publish`, `handle`, `version`, `aggregateVersion`, `expectedVersion`, `sequenceNumber`, `concurrencyToken`, `equals`, `__toString`, `jsonSerialize`, `toArray`, `toPrimitives`, `__debugInfo`.
- `@template TIdentifier of BusinessIdentifier` and `@extends Entity<TIdentifier>` are declared; **`@template-covariant` is not**.
- Releasing a fresh aggregate returns `[]`; release returns the **exact instances** recorded, in **recording order**, proven against **descending `occurredAt()` timestamps**; release clears the buffer; a repeated release returns `[]`; recording after release yields a fresh batch; mutating the returned array does not alter the aggregate; two aggregates hold independent buffers and releasing one does not empty another.
- **No deduplication**: the same instance recorded twice appears twice, and two distinct events sharing an identifier and instant are both retained.
- Recorded and released events **never affect `sameIdentityAs()`** or the identifier `id()` returns.
- **`Entity.php`'s change is docblock-only** — no signature, member, modifier, or executable line changed — and the corrected text no longer assigns aggregate versioning to PF-040.
- No Laravel, Illuminate, Eloquent, Carbon, Symfony, PSR-dispatcher, service-container, persistence, serialization, tenancy, or authorization dependency; **PF-040's only imports are `DomainEvent` and `BusinessIdentifier`**.
- **No concrete aggregate, concrete domain event, dispatcher, outbox, or envelope exists in production code**; `app/Modules` was not created.
- `strict_types=1` declared; namespace matches path; PHP global types take a leading backslash.
- **The PF-049 architecture-guard file is byte-identical and passes unchanged**, automatically inspecting `AggregateRoot.php` along with every other Foundation source file. Its assertion count grows as a natural consequence of iterating one more file; this criterion pins no permanent assertion count.
- **No PHP source or test file declares an `@phpstan-ignore` or `@phpstan-ignore-next-line` suppression, and no PHPStan baseline, suppressed error, ignored error, or configuration ignore was added.**
- Validated in the canonical Docker PHP 8.4 application container: Pint clean; **PHPStan level 5 clean with no baseline, suppression, or ignored error**; a **level-6 dry run recorded as informational only**; the focused PF-040 tests pass; **the complete suite passes**; `composer validate --strict` and both dependency audits pass.

**Tests.** One file, `tests/Unit/Foundation/Domain/Model/AggregateRootTest.php`, extending `PHPUnit\Framework\TestCase` directly, booting no Laravel application and adding no test dependency. Deliberately small — the contract has no parsing, no rejection matrix, no generation path, and by design no validation to exercise. All fixtures are declared **inside the test file**, per the standing rule that no test double belongs in `app/Foundation`; **no separate fixture file, and no fixture name carries business meaning.** Fixture names are distinct from `EntityTest.php`'s, because both files share the `Tests\Unit\Foundation\Domain\Model` namespace and are loaded in the same suite run.

- **Shape, by reflection:** abstract; parent class exactly `Entity`; not `readonly`; implements no interface; the inherited `Entity` constructor is the only constructor; no constant; no static property and no static method; exactly one declared property, filtered by declaring class rather than relying on PHP's private-inheritance behaviour, asserted private, non-static, non-readonly, non-nullable `array`, defaulting to `[]`; exactly two declared methods, `recordThat` and `releaseEvents`; each one's visibility, finality, non-staticness, parameters, and return type asserted individually; the public surface exactly `['id', 'releaseEvents', 'sameIdentityAs']`; and the prohibited-member list above absent, **named individually so a future addition fails with the member's own name rather than an opaque count mismatch**. **Reflection assertions use fully-qualified class names, never `'self'` or `'static'`.**
- **Docblock pins:** the class docblock declares `@template TIdentifier of BusinessIdentifier` and `@extends Entity<TIdentifier>`, and declares no `@template-covariant` **tag** — matched as an actual PHPDoc tag line rather than as a raw substring, the same "detect the construct, not the word" discipline `EntityTest` and the PF-049 guard already use.
- **Release behaviour:** empty initial release; exact-instance retention via `assertSame`; recording order **proven against descending `occurredAt()` timestamps**, with a premise assertion that the first-recorded event really does carry the later instant, so an implementation that sorted by occurrence time would fail here; release clears; two further releases return `[]`; recording after release yields exactly the new batch; mutating the returned array leaves the aggregate empty and a subsequently recorded event releases alone; two aggregates hold independent buffers; releasing one does not empty another.
- **No inspection:** the identical instance recorded twice releases twice; two distinct events sharing identifier and instant are both retained, with a premise assertion that they really are distinct instances.
- **Identity:** recording and releasing never change `sameIdentityAs()` in either direction; the aggregate returns the exact identifier instance after a record-and-release cycle; two different aggregate types over one identifier with the identical recorded event are still not the same aggregate.
- **Hygiene:** no Laravel booted; `app/Modules` absent.
- **Deliberately avoided:** tautological assertions; fragile total test or assertion counts; a directory-inventory assertion of the kind PF-040 has just invalidated in `EntityTest`; re-testing PHPUnit or PHP itself; `sleep()`, `usleep()`, or `hrtime()`; ambient-time assertions; elapsed-time tolerances; any assertion about what every potential future subclass can or cannot do; and any assertion pretending this base can enforce what it cannot — reconstitution discipline, payload safety, dispatch correctness, and the mandatory `@extends` tag on concrete aggregates are all documented as review obligations, never as enforced properties.

**Security requirements.**

- No tenancy, `FirmId`, `FirmContext`, actor, principal, or session semantics enter Foundation.
- No authentication, authorization, or Ethical Wall decision is introduced. `CheckEthicalWallAccess` remains Practice Management's sole authority, and **releasing an event is never authorization to read its subject.**
- **`recordThat()` is `protected` as an API-integrity constraint, not a security control:** the public API gives callers outside the aggregate no way to attribute an event to it. There is no public recording method and none may be added; authorization remains the responsibility of the owning module.
- **Possessing a released batch grants nothing** — no capability, entitlement, ownership, provenance, or authenticity.
- **Event payloads carry identifiers and safe metadata only** — never credentials, secrets, payment tokens, bank details, document bytes, knowledge body text, privileged narratives, cross-client content, or another Firm's data. `AggregateRoot` cannot enforce that; it is the recording event's and the owning module's obligation.
- **Satisfying this contract makes no event safe to log, dump, serialize, expose, or publish**, and **the absence of stringification and serialization members is not a confidentiality control**: `var_dump()`, reflection, stack traces, and careless logging still expose a buffered event's full contents.
- **Internal domain events are never the permanent public contract** (Constitution Article 31); the versioned `IntegrationEventEnvelope` remains `Integrations`' own.
- **Delivery is at-least-once with no exactly-once and no global-ordering claim** (Articles 34 and 43), and PF-040 delivers nothing at all.
- **AI holds no authority here** — nothing in this base grants an AI actor the ability to record, release, or suppress an aggregate's events.
- No existing security control or required check is weakened, renamed, bypassed, or removed.

**Documentation impact.** `app/Foundation/README.md`: the namespace table records `App\Foundation\Domain\Model` as **Implemented**; the accompanying note records `Model` as containing exactly `ValueObject`, `Entity`, and `AggregateRoot`; the intro sentence records PF-040 as the eighth story and the approved-order line records PF-045 as next; the `Entities` subsection records that event recording and release belong to `AggregateRoot` while **aggregate versioning belongs to neither and to no Foundation story**; the `External dependencies` section records that PF-040 introduced none and declined every dispatcher; and a new **`Aggregate roots`** subsection records the contract, the two-member rationale, the protected-recording API-integrity boundary (never an authorization or security boundary), the append-without-inspection rule, the single combined release and the named absence of every alternative, the fresh-batch and caller-copy properties, the ordering non-guarantees, the identity separation, D1's no-version decision, the no-constructor decision, the mandatory `@extends`, the full no-dispatch exclusion list, the reconstitution warning, the security position, and the test-double and concrete-aggregate prohibitions. `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md` updated for status and sequencing only.

**Definition of Ready (met, approved by the repository owner).** Goal clear and narrowly bounded to one base class; owner identified (repository owner); **dependencies resolved and correctly classified** — `Entity` is the parent, `DomainEvent` the recorded type and only behavioural import, `BusinessIdentifier` the `@template` bound, `Clock`/`UuidV7`/`UuidV7Generator` neither direct nor transitive code dependencies, PF-049 documentation only, `ValueObject` not a dependency at all, PF-090 through PF-093 future dependents and never dependencies, and no new dependency of any kind; **exact contract approved and recorded above**; **D1 settled** — no aggregate version, versioning being a persistence concern outside Foundation, with `Entity`'s stale docblock corrected as the sole authorized existing-source change and that change docblock-only; **D2 settled** — `recordThat()` protected and final, recording being the aggregate's own account of what it did; **D3 settled** — one combined `releaseEvents()`, with peek, read, count, clear, flush, discard, and any public recording method all prohibited rather than deferred; append-without-inspection settled (no validation, filtering, sorting, deduplication, merging, or capping); release semantics settled (recording order, atomic clear, empty repeat release, fresh subsequent batch, caller-owned returned array); ordering non-guarantees settled and recorded as non-guarantees; identity separation settled; no-constructor decision settled; generics settled on `Entity`'s already-verified invariant-template rules; exception position settled (throws nothing, adds no exception type, returns no `Result`, invents no validation); security implications identified; tests specified and deliberately proportionate; allowed and forbidden files enumerated; and **no architecture blocker** — `App\Foundation\Domain\Model` is the namespace `app/Foundation/README.md` already reserves for PF-040, the PF-049 guard is a denylist that discovers the new file automatically and permits it unchanged, and PF-090 through PF-093 already own dispatch, outbox, publication, and consumption as separate approved Sprint 0.4 stories. **The `EntityTest` directory-inventory conflict identified during implementation was resolved by explicit repository-owner authorization: the expected inventory was updated for `AggregateRoot`, with no test removed or weakened.**

**Definition of Done (met).** Acceptance criteria met. Validated in the canonical Docker PHP 8.4 application container: `composer validate --strict` valid; `composer pint:test` passed (52 files); `composer phpstan` (level 5, no errors, 36 files, **no baseline, suppression, or ignored error**, and a repository-wide scan finding zero `@phpstan-ignore` tags); an informational level-6 dry run recorded no errors, with no configuration change; the focused PF-040 suite passed (33 tests, 106 assertions); `EntityTest` passed after the owner-authorized inventory update (42 tests, 113 assertions); the PF-049 architecture guard passed **byte-identical and unchanged** (8 tests, 270 assertions); **the full test suite passed (414 tests, 1078 assertions**, up from the 381/955 baseline before this story**)**; `composer audit --locked --abandoned=report` and `npm audit --audit-level=high` both clean; `git diff --check` clean. Security and architecture were independently reviewed against this entry's requirements with no code-level P0 or P1 finding. **Independent review and correction confirmation completed; correction commit `7fbb928` passed all four required `Protect main` checks; the required human approval comment was recorded; PR #31 merged to `main` as `5ea91dc`; and the implementation branch was deleted locally and remotely.**

**Explicitly not implemented by PF-040.** **No concrete aggregate of any kind** — no `Firm`, `Matter`, `Client`, `Invoice`, `Document`, `Task`, or any other, and no name invented for one. **No concrete domain event**, in production code or otherwise; the reference event is a fixture inside the test file. PF-045 `Money` and PF-046 `Result` remain unimplemented, and no stub, placeholder, or empty directory exists for either. **No aggregate version, optimistic concurrency, expected-version check, sequence number, or concurrency token**, per D1. **No separate peek, read, count, clear, flush, discard, or public recording method**, per D3. **No event dispatch of any kind** — no dispatcher, listener, subscriber, handler, bus, queue, job, broadcaster, `ShouldQueue`, or PSR event dispatcher. No transactional outbox, publisher, consumer, dead-letter, retry, backoff, or deduplication store. No `IntegrationEventEnvelope`, delivery attempt, delivery identifier, webhook, signature, or external contract version. No persistence, repository, reconstitution, snapshotting, event sourcing, ORM mapping, Eloquent model, migration, cast, DTO, route-model binding, serialization, or wire format. No auditing, activity history, logging, telemetry, metrics, or reporting. No timestamps, `createdAt`, `updatedAt`, `deletedAt`, or soft deletion. No tenancy, `FirmId`, `FirmContext`, authorization, roles, permissions, ownership, or Ethical Wall decision. No `__toString`, `\Stringable`, serialization, hashing, or ordering member. No container binding, service provider, or bootstrap registration. No test double under `app/Foundation`. No concrete identifier subclass. No new exception type. No PHPStan level raise, baseline, or suppression, and no Pint rule, exclusion, or preset change. **No change to any existing test file other than the owner-authorized directory-inventory expectation in `EntityTest.php`, and no existing test, assertion, or security control removed or weakened anywhere.** No business module, no `app/Modules`, no production deployment, no new dependency.

## Module Infrastructure
- PF-060 through PF-063

## Application Framework
- PF-070 through PF-073

### PF-073 — Transaction Manager — Done

**Objective.** Provide the single application transaction boundary that consumes an already verified `FirmContext`, opens one explicit PostgreSQL transaction, establishes the Firm setting transaction-locally before protected database work, executes one callback, and commits or rolls back as a unit. PF-073 is a technical Platform Foundation runtime adapter; it is not a business bounded context and does not itself complete tenant isolation.

**Owner.** Platform Foundation.

**Dependencies.** PF-033 PostgreSQL Continuous Integration and PF-080 Firm Context are Done. ARCH-012 is Approved; `docs/architecture/03_Database_Design.md` and ADR-016 are authoritative for transaction-scoped Firm context. PF-073 does not depend on PF-081 or PF-082: those later stories supply and adapt verified context to this boundary. PF-091 depends on PF-073 where state, audit, idempotency, and outbox facts must commit atomically, but PF-091 is not a PF-073 dependency.

**Approved technical decisions — independently reviewed and technically approved on 11 August 2026.**

1. The implementation is `App\Infrastructure\Database\FirmTransactionManager`, outside `app/Foundation` because it depends on Laravel's concrete database connection adapter. This is the approved PF-073 reconciliation of the older `app/Foundation/Persistence` placement in `docs/architecture/03_Database_Design.md` §24: the source root is new, but already covered by the existing `App\` PSR-4 mapping, and no Composer change is required. The framework-independent `FirmContext` remains under `App\Foundation\Tenancy`; no Laravel dependency is introduced into Foundation. “Platform Foundation owns” describes technical ownership and governance, not a requirement that framework-coupled code live in the `app/Foundation` directory.
2. The one platform-wide PostgreSQL setting is `onelegalpro.firm_id`, declared once as `public const FIRM_SETTING = 'onelegalpro.firm_id'` on `FirmTransactionManager`. The dot is required for a PostgreSQL custom setting; the qualified OneLegalPro prefix avoids collision with PostgreSQL and third-party settings. No module repeats or replaces this string.
3. The public operation is `run(FirmContext $context, callable $callback): mixed`, with the exact PHPStan contract `@template TResult`, `@param callable(Connection): TResult $callback`, and `@return TResult`, where `Connection` is imported from `Illuminate\Database\Connection`. This Pint-compatible spelling denotes the same approved concrete Laravel type; it does not weaken or abstract the callback contract. PF-073 invokes the callback with the exact managed connection, binding callback database work to the connection carrying the local setting. The callback receives no scalar Firm identifier and no mutable context; it executes only after the exact supplied context has established the transaction setting.
4. The adapter uses parameter-bound `SELECT set_config(?, ?, true)` rather than interpolated `SET LOCAL`. The third argument `true` gives the transaction-local semantics equivalent to `SET LOCAL`; parameter binding prevents a setting value from becoming SQL text. `set_config(..., false)` and session-level `SET` are prohibited.
5. PF-073 rejects every ambient transaction before opening its own, whether managed or unmanaged and whether it would use the same or a different Firm. Same-Firm nesting, savepoint nesting, joining an existing transaction, different-Firm re-entry, and manager-mediated context replacement all fail closed. This deliberately keeps one manager call equal to one outer transaction, makes setting establishment occur exactly once, and avoids Laravel savepoints turning an inner rollback into a still-live outer transaction with ambiguous ownership.
6. On success, PF-073 commits and returns the callback's exact result. On a callback or setting failure, it attempts rollback and cleanup verification and, when both succeed, rethrows the exact original instance. A rollback or cleanup-verification failure is not hidden in order to preserve the callback throwable: it raises `\RuntimeException` with the original failure chained as evidence, because connection state is then unknown. A cleanup-verification failure after a successful commit must use a message that explicitly states that the transaction **committed durably but connection cleanup could not be verified**; callers must not retry it as if commit had failed. A rollback-path infrastructure failure must explicitly state that commit did not complete. `\RuntimeException` is deliberately not a machine-readable retry discriminator because PF-073 permits no automatic retry; any future retry facility must introduce separately approved typed outcome classification rather than parsing messages or treating every `\RuntimeException` alike. Commit, rollback, cleanup, or connection failures are never converted into a success or a domain result.
7. Read callbacks use the same explicit transaction boundary as writes. Autocommit reads of Firm-scoped relations are prohibited.
8. Automatic retry is disabled. A later separately approved facility may retry a serialization failure or deadlock only for a command proved idempotent or protected by ADR-021 idempotency persistence.
9. The transaction contains no external HTTP, provider, email, messaging, object-storage, search-index, AI/model, queue-push, user wait, sleep, or polling operation. PF-073 cannot infer callback contents; this is a caller contract reinforced by review and owning-story tests, not an overstated runtime guarantee.
10. PF-073 establishes only the transaction-local Firm identifier. The Actor and correlation identifiers remain available through the exact `FirmContext` supplied to the caller's application layer; they are not additional PostgreSQL settings in this story.
11. Before opening the transaction, PF-073 verifies that the checked-out connection is not a superuser, has no `BYPASSRLS`, and carries no non-empty pre-existing `onelegalpro.firm_id` session value. Every scalar query used for preflight, setting establishment, in-transaction verification, and post-transaction cleanup passes `false` as `Connection::scalar()`'s third argument so Laravel always uses the write PDO; the adapter never permits a read-PDO checkout to verify state different from the connection that owns the transaction. A failing role check or leaked session value causes the connection to be disconnected and the operation to fail before callback execution. This is connection quarantine, not a `RESET`-based recovery claim. Relation ownership and grants remain schema/deployment controls tested by their owning stories; database ownership is not used as their proxy, and PF-033's current test role may own its disposable test database while remaining non-superuser and without `BYPASSRLS`.
12. After callback execution and again after transaction end, PF-073 verifies the expected setting state. A callback that leaves a changed Firm value is rejected before commit. Direct `SET`, `SET LOCAL`, `set_config`, or equivalent Firm-setting writes outside this adapter are prohibited and guarded by a production-source scan. Because arbitrary dynamically assembled SQL cannot be made impossible by a PHP adapter, the contract does not claim PF-073 can detect a transient direct switch that a malicious callback restores before returning; review, the single-connection callback contract, least-privilege code ownership, and the source guard enforce that prohibition alongside the runtime checks.

**Proposed implementation contract.** Create one final adapter at `app/Infrastructure/Database/FirmTransactionManager.php`. It receives one `Illuminate\Database\Connection` through its constructor and exposes only the setting-name constant and `run()`. The concrete Laravel connection is intentional: PF-073 must pass the exact managed connection into the callback and must be able to quarantine a contaminated checkout through `disconnect()`, neither of which is an optional concern hidden behind a narrower contract. Before beginning, `run()` verifies that the connection transaction level is zero; otherwise it throws `\LogicException` before invoking the callback or issuing the setting statement — an ambient transaction is a programming/adapter contract violation, not a domain failure, so PF-049's domain exceptions are not misused.

Still before the managed transaction, PF-073 uses parameter-bound scalar queries with `$useReadPdo = false` to verify the current PostgreSQL role is not superuser, has no `BYPASSRLS`, and that `nullif(current_setting(?, true), '')` is `NULL` for the canonical Firm setting. It never trusts a checkout merely because the application created it. A failed role check, an indeterminate result, or a non-empty pre-existing setting disconnects the connection and throws before callback execution; PF-073 never attempts to normalize contamination with session-level `SET`, `set_config(..., false)`, or a best-effort `RESET`.

It then begins the transaction, binds the canonical setting name and `$context->firmId()->toString()` to `SELECT set_config(?, ?, true)` through `Connection::scalar(..., false)`, and verifies that the returned scalar exactly equals the supplied canonical Firm identifier before invoking `$callback($connection)`. The callback and every repository it invokes must use that passed connection; reaching for a facade, another named connection, or an independently resolved connection inside the callback is prohibited and must be covered by each consuming story's integration tests. PF-073 does not falsely claim PHP can make arbitrary global connection access unrepresentable. Immediately after the callback, the current transaction-local value must still equal the original Firm identifier or PF-073 rolls back, disconnects, and raises. After successful commit or rollback, the same write connection must report the setting as absent or unusably empty through `Connection::scalar(..., false)`; otherwise it is disconnected and a `\RuntimeException` is raised with the committed/not-committed outcome stated explicitly. A failure to establish the setting rolls back and raises; because `FirmContext` already requires typed, non-null identifiers, PF-073 has no separate “missing context” input path, while an empty, malformed, unconfirmed, or contaminated database setting still fails before protected callback execution.

The exact supplied `FirmContext` is not reconstructed, mutated, cached, serialized, logged, or treated as authorization. Possessing it proves no membership, entitlement, role, permission, Ethical Wall outcome, or resource access. IdentityAccess remains the authority for verified identity and membership; PF-081/PF-082 own their later verified handoff and ingress composition; each business domain still owns current resource authorization and Practice Management alone owns Ethical Wall decisions.

**Acceptance criteria.** One explicit transaction is opened only from transaction level zero; connection role and pre-existing Firm session state are verified before it begins; contaminated or privileged connections are disconnected and rejected; the canonical Firm setting is established transaction-locally and parameter-bound before the callback; the callback receives the exact managed connection; its exact result is returned after commit; the exact original throwable is rethrown when rollback and cleanup verification succeed, while rollback or cleanup failure is surfaced with the original failure preserved; the setting is absent or unusably empty on the reused connection after both commit and rollback; an un-restored context change is rejected before commit; reads receive the same boundary as writes; every ambient or nested manager transaction is rejected before callback execution; production code outside the adapter contains no direct Firm-setting statement; no automatic retry or external call is introduced; and no Foundation, schema, RLS, role, grant, migration, resolver, middleware, module, repository, outbox, audit, or idempotency implementation is added.

**Allowed implementation files.** A future PF-073 implementation may change only:

- `app/Infrastructure/Database/FirmTransactionManager.php`;
- `tests/Unit/Infrastructure/Database/FirmTransactionManagerTest.php`;
- `tests/Feature/Infrastructure/Database/FirmTransactionManagerPostgreSqlTest.php`, guarded by the existing PF-033 `REQUIRE_POSTGRESQL_TEST_DATABASE` mechanism so ordinary SQLite runs skip it while CI fails closed if PostgreSQL coverage is not executed;
- `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md` for verified lifecycle/evidence updates;
- `app/Foundation/README.md` only to record the implemented adapter boundary without placing database code under Foundation.

Any additional source, test, provider, configuration, workflow, or database file requires a separately reviewed contract correction and explicit approval before editing.

**Forbidden files and changes.** No change to `app/Foundation/Tenancy/FirmContext.php`, any existing Foundation source/test, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `phpunit.xml`, `phpstan.neon.dist`, `pint.json`, `.github/`, `.githooks/`, Docker/environment files, Laravel bootstrap/configuration, service providers, routes, controllers, middleware, jobs, migrations, schema, RLS policies, PostgreSQL roles/grants/functions, modules, repositories, outbox, audit, or idempotency persistence. No required check, assertion, static-analysis level, formatting rule, security control, or PostgreSQL CI guard may be removed, weakened, renamed, skipped, or suppressed.

**Required tests.** The focused unit suite extends plain `PHPUnit\Framework\TestCase`; it must not use `RefreshDatabase`, `DatabaseTransactions`, or any ambient-transaction trait because Decision 5 requires every manager call to start at transaction level zero. It must prove the exact final class, namespace, concrete `Connection` constructor dependency, constant name/value, literal PHPStan generic callback contract, single public operation, no extra API, transaction-level-zero guard, role/session preflight, contaminated-connection disconnect, begin/setting/callback/commit order, the exact connection passed to the callback, exact result preservation, successful rollback with exact original throwable propagation, rollback-failure `\RuntimeException` reporting with the original failure preserved, post-commit cleanup failure explicitly reporting that durable commit occurred, setting-establishment failure before callback, post-callback value verification, post-commit/post-rollback cleanup verification, same-Firm and different-Firm nesting rejection through the universal ambient-transaction rule, no join/savepoint path, and no automatic retry. It must prove every SQL call uses `Connection::scalar()` with bound parameters and `$useReadPdo = false`, uses `set_config(..., true)`, and never interpolates the Firm value or uses session-level `SET` or `set_config(..., false)`. A source guard scans production PHP and fails if the canonical setting literal or a Firm-setting `SET`/`set_config` statement exists outside `FirmTransactionManager.php`; the guard is a review control and makes no claim to solve arbitrary dynamically assembled SQL. The suite also proves the adapter itself imports and invokes no external client; arbitrary callback behavior remains an owning-story review and integration-test obligation because PF-073 cannot reliably infer it at runtime.

The PostgreSQL feature suite must use the existing PF-033 `REQUIRE_POSTGRESQL_TEST_DATABASE` gate exactly as `PostgreSqlTestDatabaseGuardTest.php` does: ordinary local SQLite execution records the guard assertion and returns without PostgreSQL-only work, while the existing CI guard fails closed if CI does not use PostgreSQL, so no workflow or `phpunit.xml` change is needed. Neither this feature suite nor any helper it uses may apply `RefreshDatabase`, `DatabaseTransactions`, or another ambient-transaction trait. Under the PF-033 non-superuser, `NOBYPASSRLS` PostgreSQL 16 test role it proves: role preflight passes for that runtime test role; a seeded non-empty session-level Firm setting is detected before callback, the connection is disconnected, and no leaked value is reused; the setting is visible inside the managed callback; the callback receives the exact connection; a read callback receives the same boundary; the exact setting value matches the supplied Firm identifier; another manager entry cannot change it; a direct un-restored callback change is detected before commit; it is absent or unusably empty after commit on the same reused connection; it is absent or unusably empty after rollback on the same reused connection; callback work commits on success and rolls back on failure using test-owned temporary evidence without adding production schema; and an ambient transaction fails before callback work. Privileged-role rejection and disconnect behavior are proved in the focused suite with a controlled connection test double; PF-073 does not authorize CI role creation or workflow changes. Empty/populated RLS-relation proofs remain mandatory for the later schema/RLS story and PF-082 integration but are not falsely claimed by PF-073, which creates no policy or Firm-scoped relation.

The full suite, focused suites, unchanged Foundation guard, PF-033 PostgreSQL engine/role guard, Pint, PHPStan level 5, `composer validate --strict`, Composer audit, npm audit, and `git diff --check` must pass. The four required `Protect main` check names remain exactly `PHP Code Quality`, `Frontend Build`, `Application Tests`, and `Dependency Audit`.

**Security.** A client-supplied Firm or Actor identifier never establishes context. No header, route, parameter, body, cookie, hostname, custom domain, or email is accepted. The canonical setting is transaction-local and must not survive commit or rollback. Manager-mediated switching is impossible; direct setting writes outside the adapter are prohibited, guarded, and not falsely claimed to be unrepresentable in arbitrary SQL. Connection role and session state are verified on the write PDO rather than trusted; a privileged or contaminated checkout is disconnected and fails before protected callback work. Consistent with ADR-016 Decision 9 and `docs/architecture/03_Database_Design.md` §3.4, PF-073's pre-statement application enforcement is where the fail-closed guarantee lives; PostgreSQL row policy is the independent defence-in-depth layer. PF-073 is not authentication, membership verification, authorization, repository scoping, RLS, or an Ethical Wall.

**Definition of Ready (met).** The objective, dependencies, responsibility boundary, namespace/API, canonical setting, parameter-binding rule, nesting/ambient-transaction rule, retry rule, allowlist, exclusions, tests, and security implications are approved above. Independent review and two focused confirmation reviews resolved every reported finding, leaving no P0, P1, P2, or P3. The technical-owner approval comment was recorded on PR #51; all four required protected checks passed on exact final head `9f6ffbf`; and the contract PR merged to `main` as `c2b7549` on 11 August 2026. Readiness was met before implementation began; implementation runs on its separately authorized implementation branch and remains confined to the approved allowlist.

**Implementation status (Done).** Implementation began on 12 August 2026 on `feature/pf-073-transaction-manager`, branched from `main` at `a2b1ee9`. Three files were created and nothing else was touched: `app/Infrastructure/Database/FirmTransactionManager.php`, `tests/Unit/Infrastructure/Database/FirmTransactionManagerTest.php`, and `tests/Feature/Infrastructure/Database/FirmTransactionManagerPostgreSqlTest.php`. No existing file was modified apart from this tracking entry, `docs/PROJECT_STATUS.md`, and the allowlisted `app/Foundation/README.md`; no existing test, assertion, static-analysis level, formatting rule, security control, or PostgreSQL CI guard was removed, weakened, renamed, skipped, or suppressed. The technical owner approved the imported `Connection` spelling on 12 August 2026 after Pint demonstrated that it normalizes the fully qualified docblock spelling; the imported form denotes the identical concrete type and requires no formatter exception.

Validated in the canonical Docker PHP 8.4 / PostgreSQL 16 environment against a disposable non-superuser `NOBYPASSRLS` role and database, created by the documented `CONTRIBUTING.md` recipe: `git diff --check` clean; `composer validate --strict` valid; Pint passed across 58 files; PHPStan level 5 clean across 38 files with no baseline, suppression, or ignored error; the focused unit suite passed (42 tests, 335 assertions); the focused PostgreSQL feature suite passed under the armed `REQUIRE_POSTGRESQL_TEST_DATABASE` gate (12 tests, 44 assertions) and recorded the guard assertion without PostgreSQL-only work on the ordinary SQLite path (12 tests, 16 assertions); the complete PostgreSQL-backed suite passed (**480 tests, 1,581 assertions**, up from the 426/1,202 baseline measured on `a2b1ee9` in the same environment); the PF-049 Foundation dependency guard passed byte-identical and unchanged (8 tests, 287 assertions); the PF-033 PostgreSQL engine/role guard passed unchanged (1 test, 8 assertions); and `composer audit --locked --abandoned=report` and `npm audit --audit-level=high` were both clean. Independent review additionally corrected four hardening gaps before staging: a failed `beginTransaction()` now disconnects and reports that no commit occurred; rollback failure while abandoning a setting/context-verification failure is no longer hidden; Laravel's silent reconnect during `BEGIN` is detected by comparing the verified and transactional raw PDO handles and rejected before callback work; and the exact superuser/BYPASSRLS probe SQL is pinned by focused tests.

**Approved implementation-time contract correction.** The stock Laravel Pint preset's `fully_qualified_strict_types` rule normalizes the fully qualified callback docblock type to the imported spelling `@param callable(Connection): TResult $callback`. On 12 August 2026 the technical owner approved that spelling together with `use Illuminate\Database\Connection;`: it denotes the identical concrete Laravel type, preserves PHPStan's contract, changes no runtime behavior, and avoids weakening `pint.json` or any required check. The adapter and exact contract test use that approved spelling.

**Completion evidence.** Final independent confirmation reviewed exact implementation head `d8d7a6d3264d63cca4d6ceda3d73ff8d337e576e`, found no P0, P1, or P2 finding, and required no correction. All four required protected checks — `PHP Code Quality`, `Frontend Build`, `Application Tests`, and `Dependency Audit` — passed on that exact head. Technical approval was recorded on PR #53, which merged to `main` as `bd124657502715d843b1ffa464f87857323d1ab2` on 12 August 2026. After synchronization, the isolated Docker stack, implementation worktree, and local and remote implementation branches were removed. PF-073 is Done; PF-081 and PF-082 remain Backlog and are not claimed to have started.

**Definition of Done.** The approved contract is implemented only within its final allowlist; every acceptance criterion and focused/PostgreSQL test passes; the complete canonical PostgreSQL suite and all static/format/dependency gates pass; no P0/P1 remains after independent review; all four protected checks pass on the exact final implementation head; required human approval is recorded; the implementation PR merges; tracking records only verified evidence; and its branch/worktree are removed locally and remotely. Done makes no claim that PF-081, PF-082, Firm schema, application-runtime roles, RLS policies, raising policy helpers, repositories, audit, idempotency, outbox, or a business module is implemented.

**Accepted cost.** The fail-closed checks add approximately five small PostgreSQL round trips to each managed transaction, including read-only transactions. Release 0.1 accepts that cost in exchange for verified role, setting establishment, unchanged context, and cleanup; optimization may occur only through a separately reviewed change that preserves those guarantees.

**Explicitly excluded.** Firm discovery/resolution; FirmContext construction; identity, membership, entitlement, session, role, permission, capability, authorization, Ethical Wall, or support-access decisions; HTTP/queue/CLI middleware; context switching; nested transactions or savepoints; retry/backoff; isolation-level selection; row/advisory locking; external calls; events/outbox; audit/idempotency writes; persistence repositories; Eloquent models/casts; schema/migrations; RLS policies/functions; database roles/grants; connection-pool configuration; logging/telemetry; business modules; production deployment.

## Multi-Tenant Foundation
- PF-080 through PF-082

### PF-080 — Firm Context — Done

**Objective.** Introduce the framework-independent runtime carrier that makes one verified Firm and Actor context explicit throughout a request or job. `FirmContext` carries identifiers only; it does not discover, authenticate, authorize, persist, resolve, or switch them.

**Owner.** Platform Foundation.

**Scope and deliverable.** One immutable, framework-independent carrier and one focused pure-unit-test suite. The carrier preserves three already-established identifiers across technical boundaries and owns no business fact or decision.

**Dependencies.** PF-033 PostgreSQL Continuous Integration is Done through PR #44. PF-044 BusinessIdentifier and PF-048 UUIDv7 are Done. ARCH-012 is Approved and ADR-016 is Accepted. PF-080 does not depend on a concrete `FirmId`, Actor identifier, `Firm`, `FirmMembership`, session, entitlement, tenant resolver, middleware, transaction manager, or database policy. Those are future consumers or collaborators. Concrete Firm and Actor identifiers remain owned by PlatformAdministration and IdentityAccess respectively and may be supplied later as `BusinessIdentifier` subclasses without changing this carrier's ownership or three-value semantics. Narrowing the declared parameter types after those owned types exist is a breaking contract follow-up requiring explicit human approval; this story neither pre-approves nor prohibits it.

**Approved reconciliation decisions.** The repository owner approved these decisions on 10 August 2026 (decision 5 was refined during contract review to name PF-073 and PF-082 consistently with ADR-016 Decision 9):

1. PF-080 lives in `App\Foundation\Tenancy`, a sibling of `App\Foundation\Domain`, because it is Platform Foundation runtime infrastructure rather than a domain primitive or business bounded context.
2. Firm and Actor identifiers are accepted through the existing `BusinessIdentifier` abstraction; PF-080 creates no concrete identifier and therefore introduces no circular dependency on PlatformAdministration or IdentityAccess.
3. Active, verified membership is a mandatory construction precondition supplied by IdentityAccess, not a `FirmContext` field or Foundation-owned decision.
4. `FirmContext` carries exactly a Firm identifier, an Actor identifier, and a correlation identifier. It performs no authentication, membership, entitlement, session, authorization, tenant resolution, persistence, or database work.
5. The PostgreSQL `SET LOCAL` setting-name constant belongs to PF-073 Transaction Manager, with PF-082 consuming that transaction boundary at HTTP ingress; it does not belong to PF-080.
6. The `firm_id` foreign-key decision remains with PlatformAdministration's Firm schema story; PF-080 creates no schema or migration.

**Implementation contract.** Create one `final readonly FirmContext` under `app/Foundation/Tenancy`. Its constructor requires exactly three non-null values in this order: `BusinessIdentifier $firmId`, `BusinessIdentifier $actorId`, and `UuidV7 $correlationId`. It exposes exactly `firmId(): BusinessIdentifier`, `actorId(): BusinessIdentifier`, and `correlationId(): UuidV7`, returning the exact instances supplied. It contains no mutable state, nullable or default value, array or scalar identifier, membership snapshot, role, permission, entitlement, session, hostname, request, header, route, container, configuration, database connection, transaction state, logger, or clock. It has no factory, resolver, switch, serialization, stringification, equality, authorization, or validation API.

The abstract return types are intentional. Concrete consumers narrow their own construction inputs and retain ownership of the concrete identifier types; `FirmContext` is a carrier across those boundaries, not their owner. Because both first parameters share the abstract `BusinessIdentifier` declaration, the constructor alone cannot detect Firm/Actor transposition. Every construction site therefore uses named arguments, is restricted to an IdentityAccess-verified path mediated by the future PF-081/PF-082 contracts, and carries its own boundary test proving that its concrete Firm and Actor identifiers cannot be reversed. Possessing or constructing an instance grants no membership, authorization, entitlement, session, provenance, or authenticity. A caller may construct it only after authoritative identity and membership verification, and every protected action still performs its owning domain's current authorization checks.

**Acceptance criteria.** The class exists at the exact approved path and namespace; is `final readonly`; stores exactly the three required identifier instances; exposes exactly the three approved accessors; preserves object identity; accepts no scalar, request, membership, session, or authorization input; performs no work beyond construction and access; imports only `BusinessIdentifier` and `UuidV7`; and leaves the existing Foundation framework-dependency guard unchanged and green.

**Allowed files.** Implementation may change only:

- `app/Foundation/Tenancy/FirmContext.php`;
- `tests/Unit/Foundation/Tenancy/FirmContextTest.php`;
- `app/Foundation/README.md` for implemented-contract documentation;
- `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md` for story status and verified delivery evidence.

Any required change to an existing test inventory must be reported before editing and requires explicit authorization. No Sprint Plan, architecture, ADR, workflow, dependency, configuration, migration, schema, module, route, middleware, resolver, service provider, or other source/test file is authorized by the implementation story.

**Forbidden files.** No change to `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `phpunit.xml`, `phpstan.neon.dist`, `pint.json`, `.github/`, `.githooks/`, Docker or environment files, Laravel configuration/bootstrap, service providers, routes, controllers, migrations, schema, modules, or any existing Foundation source/test file. No required check, test, assertion, static-analysis level, formatting rule, or security control may be removed, weakened, renamed, skipped, or suppressed.

**Tests.** The focused unit test extends `PHPUnit\Framework\TestCase` directly and must prove: final and readonly shape; exact namespace and file location; exactly three private readonly instance properties; constructor parameter order, names, exact declared types, and absence of defaults/nullability/variadics; named-argument construction; exactly three public non-static accessors with exact declared return types; instance preservation for all three values; no additional public, protected, or magic API; no framework/vendor dependency; no concrete Firm or Actor identifier in production Foundation; no Laravel boot; and no business module introduced. Test-local final `BusinessIdentifier` subclasses are permitted solely as fixtures. Future PF-081/PF-082 construction-site tests, not this carrier test, must prove their concrete Firm and Actor types cannot be transposed.

The existing Foundation framework-dependency guard must pass unchanged. The full suite, Pint, PHPStan level 5, `composer validate --strict`, Composer audit, npm audit, and `git diff --check` must pass. The four required `Protect main` check names remain exactly `PHP Code Quality`, `Frontend Build`, `Application Tests`, and `Dependency Audit`.

**Security.** A request-supplied Firm or Actor identifier is never trusted. A hostname, domain, email address, header, cookie, route, parameter, or body may identify a candidate Firm but never proves membership. PF-080 contains no resolver and accepts no request object. No context may be switched or mutated after construction. Identifiers must never be treated as secrets or capabilities, and their presence grants nothing. `actorId()` is an identifier only: it carries no actor category or acting-as/acting-on-behalf-of relationship and is never sufficient audit attribution on its own; dual attribution under a `PrivilegedAccessGrant` remains IdentityAccess's and the owning audit stream's responsibility. The correlation identifier is the sole correlation representation accepted by this carrier: a `UuidV7` produced through `UuidV7Generator`, never adopted from a header, parameter, cookie, route, or body, and never proof of trust, provenance, or authenticity. No stale membership, role, permission, entitlement, session, or Ethical Wall result is cached in this value.

**Documentation impact.** On implementation, `app/Foundation/README.md` records `FirmContext` as implemented without assigning PF-081/PF-082 placement; this backlog entry and `docs/PROJECT_STATUS.md` record verified status and delivery evidence. No architecture, ADR, Sprint Plan, or module documentation changes are authorized by implementation.

**Definition of Ready (met).** Objective, owner, dependencies, namespace, identifier representation, membership boundary, exact API, acceptance criteria, exclusions, allowed and forbidden files, tests, security implications, PostgreSQL prerequisite, setting-name ownership, and Firm foreign-key ownership were resolved. The reconciliation decisions were explicitly approved, independently reviewed, corrected, and merged through PR #48 as `25e8b31` on 10 August 2026. The 10 August amendment to DOM-006's “Tenancy and security” paragraph records that approval. ADR-012's Alternatives considered bullet rejecting deferral of PF-080/PF-081/PF-082 quotes DOM-006 as it stood on 29 July 2026; that rejection is unaffected because verified membership remains mandatory as a construction precondition. ADR-012 Decision 3's own DOM-006 clause — tenant isolation in application logic, repositories, and database policy — is unchanged by the amendment.

**Implementation evidence.** The exact carrier and focused test were implemented within the five-file allowlist. Independent review found no unresolved P0, P1, or P2. Pint passed across 55 files; PHPStan level 5 passed across 37 files; the focused PF-080 and unchanged Foundation guard suites passed with 19 tests and 386 assertions; and the final head's protected PostgreSQL-backed CI suite passed with 426 tests and 1,202 assertions. All four required protected checks passed, the required human approval was recorded, and PR #49 merged as `959a047` on 10 August 2026. The implementation branch, isolated Docker stack, and worktree were deleted locally and remotely after `main` was synchronized. PF-080 is Done. PF-073 was Backlog and awaiting its own approved Definition of Ready when this paragraph was first written; it has since been approved, implemented, and merged as `bd12465` on 12 August 2026 and is **Done** under its own entry above. PF-081 and PF-082 remain **Backlog**.

**Definition of Done.** The exact carrier contract is implemented only in the allowed files; focused, guard, and full tests pass in canonical Docker PHP 8.4; static analysis, formatting, validation, and audits pass without weakening configuration; independent code and architecture review reports no unresolved P0 or P1; all four protected checks pass on the final head; the required human approval comment is recorded; the PR merges to `main`; tracking records verified evidence and status; and the implementation branch/worktree are deleted locally and remotely. No claim is made that PF-081 tenant resolution, PF-082 middleware, transaction-scoped `SET LOCAL`, Row-Level Security, IdentityAccess, PlatformAdministration, or any business module is implemented.

**Explicitly excluded.** Concrete `FirmId` or Actor ID classes; `Firm`, `FirmMembership`, principal, credential, invitation, session, role, capability, permission, entitlement, Ethical Wall, or authorization result; tenant discovery or resolution; request or job middleware; Firm switching; transaction management; PostgreSQL setting names or statements; RLS; persistence, migrations, schema, repositories, Eloquent, casts, DTOs, controllers, routes, service providers, container bindings, logging, audit, telemetry, serialization, caching, queues, events, outbox, and all business modules.

### PF-081 — Tenant Resolver — Backlog (Definition of Ready **not** met)

**Status.** `Backlog`. **Not** Ready, In Progress, Code Review, Architecture Review, QA, Approved, or Done. **No PF-081 implementation has started**, no PF-081 production file exists, and nothing is deployed. This entry is the story **contract** only; recording a contract never schedules or starts a story, and approving this contract would not by itself make PF-081 Ready — the Definition of Ready below is **not met** and names exactly why.

**Objective.** Define the single transport-neutral application boundary that turns an **already authenticated** actor plus an **authoritatively verified, currently active** Firm membership into one `App\Foundation\Tenancy\FirmContext`. PF-081 orchestrates that resolution and owns the application-layer ports it requires. It authenticates nobody, decides no membership, reads no registry itself, adapts no transport, authorizes no resource, and opens no transaction.

**Owner.** Platform Foundation — technical runtime orchestration only. Platform Foundation does not own or decide `Firm`, membership, authentication, entitlement, session, authorization, or any business lifecycle.

**Dependencies.**

*Done and consumed:*

- **PF-080 Firm Context — Done.** `App\Foundation\Tenancy\FirmContext` is PF-081's sole output type and is consumed **unchanged**.
- **PF-044 BusinessIdentifier, PF-048 UUIDv7 — Done.** Firm and Actor identifiers stay `BusinessIdentifier`; the correlation identifier is a `UuidV7` obtained from `UuidV7Generator`.
- **PF-033 PostgreSQL Continuous Integration — Done.** The required `Application Tests` check runs on PostgreSQL 16 as a non-superuser `NOBYPASSRLS` role.

*Done and deliberately **not** invoked:*

- **PF-073 Transaction Manager — Done.** `App\Infrastructure\Database\FirmTransactionManager` consumes a `FirmContext`; **PF-081 never calls it.** Composing resolved context with the transaction boundary belongs to PF-082 or a later application boundary.

*Approved architecture:*

- ARCH-012 Approved; `docs/adr/ADR-016-Tenant-Isolation-Model.md` Accepted (Decisions 4, 4a, 8, 9 are directly load-bearing here); `docs/architecture/03_Database_Design.md` §2.4, §2.5, §3.4 Approved; `docs/adr/ADR-009-Identity-Security-Access-Control.md` and `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §6, §8, §24, §42, §45 Accepted/Approved; `docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md` Decision 3 and `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` Accepted; Constitution Articles 26–30 and 45–48.

*Absent, mandatory, and therefore blocking — see the blocker table below:*

- **PlatformAdministration story 1** — the `Firm` aggregate, the PlatformAdministration-owned `FirmId` type, the Firm registry relation, and its narrow query/adapter contract. **Backlog.**
- **IdentityAccess EPIC-009 stage 1** — `Principal`, the actor reference, and the Firm security-realm foundation. **Backlog.**
- **IdentityAccess EPIC-009 stage 2** — `FirmMembership` with suspension and revocation, and the authoritative **active, verified membership result** PF-081 requires. **Backlog.**
- **The concrete PlatformAdministration adapter** implementing PF-081's `CandidateFirmDirectory` port. **Does not exist; not created by PF-081.**

*Future dependents, never dependencies:* PF-082 Tenant Middleware; every Firm-scoped repository, RLS policy, and business module.

**Blockers — honest dependency table.**

| # | Missing dependency | Owning context / story | Status | Why PF-081 cannot be Ready without it |
|---|---|---|---|---|
| B1 | `Firm` aggregate, owned `FirmId` type, Firm registry relation, narrow registry query/adapter contract | `PlatformAdministration`, EPIC-012 story 1 | `Backlog` | Nothing can implement `CandidateFirmDirectory`, and no concrete `FirmId` exists to narrow PF-081's abstract parameter types to. |
| B2 | `Principal`, actor reference, Firm security-realm foundation | IdentityAccess, EPIC-009 stage 1 | `Backlog` | Nothing can implement `AuthenticatedActor`; there is no authenticated actor for PF-081 to resolve for. |
| B3 | `FirmMembership` with suspension/revocation, and the authoritative **active verified membership** result | IdentityAccess, EPIC-009 stage 2 | `Backlog` | Nothing can implement `FirmMembershipVerifier`. This is PF-081's **mandatory** input; without it PF-081 has no authority to construct a `FirmContext` from, and a substitute would be a fabricated authorization. |
| B4 | Concrete PlatformAdministration adapter for `CandidateFirmDirectory` | `PlatformAdministration`, follows B1 | Does not exist | Candidate discovery has no implementation. PF-081 defines the port only and **must not** supply the adapter. |

**These blockers are recorded as dependencies, not worked around.** PF-081 must not paper over B1–B4 with a temporary scalar, an array, a framework object, a session bag, an Eloquent model, a stub, a fake, a null object, a default, or a "resolve from configuration" path. Per `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`, an approximated control is more dangerous than an absent one.

**Approved reconciliation decisions.** The technical owner approved decisions 1–11 on 12 August 2026 after independent review. Approval settles the contract but **does not make PF-081 Ready**: B1–B4 remain mandatory implementation blockers.

1. **Namespace: `App\Application\Tenancy`** (owner-confirmed). PF-081 is a transport-neutral **application** boundary: neither a reusable Foundation domain primitive nor a framework/database adapter. It is **not** placed under `app/Foundation` for two independent reasons — the standing framework-dependency guard in `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php` applies to every Foundation PHP file, and, decisively, `app/Foundation/README.md` records that Foundation owns no `Firm`, `FirmId`, actor, principal, **membership**, credential, entitlement, session, authentication, authorization, or tenant-resolution lifecycle, whereas PF-081's required input contract is membership-shaped by necessity. The new root sits under the existing `App\` PSR-4 mapping, so **no `composer.json` change is required** — the same reconciliation PF-073 Decision 1 established for `App\Infrastructure`, and the same reading of "Platform Foundation owns" as technical ownership and governance rather than a directory requirement. This also reconciles the conceptual `app/Foundation/Persistence` "tenancy context contract" sketch in `docs/architecture/03_Database_Design.md` §24, which is explicitly conceptual and creates no directory.
2. **Candidate discovery is a port only** (owner-confirmed). PF-081 declares `CandidateFirmReference` and the `CandidateFirmDirectory` port and **implements no registry adapter, repository, query, or schema**. `docs/adr/ADR-016-Tenant-Isolation-Model.md` Decision 4 and `docs/architecture/03_Database_Design.md` §2.4/§2.5 name "the tenant resolver" as one of only two pre-context readers of the Firm-identifying registry; that architectural role is satisfied by PF-081's port **plus** a later PlatformAdministration-owned adapter (B4), so the registry stays owned, privileged, and narrowly contracted by `PlatformAdministration`. **Registry readability is not membership and grants no access to anything inside any Firm.**
3. **Candidate discovery and membership verification are separate facts, separated structurally rather than by convention.** The resolver's constructor takes **no** `CandidateFirmDirectory`, so a candidate value cannot reach the resolution path at all; a focused test asserts that absence.
4. **The output is exactly the existing `FirmContext`, unchanged.** PF-081 introduces no second carrier, no resolution-result wrapper, no envelope, and no subclass, and proposes no change to `FirmContext`'s shape, constructor, or accessors. Altering `FirmContext` would be a breaking Foundation contract change requiring its own explicit human approval; PF-081 neither requests nor pre-approves one.
5. **Firm and Actor identifiers remain the abstract `BusinessIdentifier`.** PF-081 invents no `FirmId`, `ActorId`, `PrincipalId`, or other concrete identifier, in Foundation or anywhere. Narrowing these declared types once B1 and B2 exist is a separate breaking-contract follow-up requiring explicit approval.
6. **PF-081 discharges the transposition obligation PF-080 delegated to it.** Because both identifier parameters share the abstract `BusinessIdentifier` declaration, PF-080's contract requires every construction site to use named arguments and to carry its own boundary test proving Firm and Actor cannot be reversed. PF-081 is that construction site and owns that test.
7. **PF-081 never calls `FirmTransactionManager`** and takes no connection, no `Illuminate\Database\Connection`, and no transaction state. PF-082 or a later application boundary composes resolved context with PF-073.
8. **One uniform, deny-by-default failure.** Every internal cause produces one externally indistinguishable outcome (see *Failure semantics*).
9. **PF-081 caches nothing.** No positive membership, assertion, resolved context, correlation identifier, or candidate lookup is retained between calls. There is no cache, memo, static property, registry, or singleton state of any kind, so no stale positive can survive membership removal, suspension, role revocation, policy change, or session invalidation.
10. **PF-081 logs nothing** and emits no event, audit record, metric, or telemetry. Security-event recording is IdentityAccess's (`docs/adr/ADR-018-Audit-Persistence-and-Append-Only-Enforcement.md` Decision 5a); a resolution failure with no verified Firm context must never borrow a candidate Firm's identity as `firm_id`.
11. **PF-081 is transport-neutral and therefore serves synchronous and queued execution identically.** It defines no queue, job, serializer, middleware, or container binding.

**Exact responsibility boundary.**

| Concern | Owner | PF-081 |
|---|---|---|
| Candidate-Firm **discovery** (hostname, custom domain, route, parameter, header, cookie, body, submitted email domain → a candidate identifier) | `PlatformAdministration` registry behind PF-081's port; extraction itself is PF-082's | **Declares the port and the unverified-reference type. Performs no extraction, holds no adapter, and excludes the port from the resolution path.** |
| Authenticated **realm selection** for authentication | IdentityAccess | **No.** PF-081 runs after authentication, never to enable it. |
| **Authentication** — credentials, MFA, passkeys, magic links, session issuance/rotation/revocation | IdentityAccess | **No.** |
| **Membership verification** — active, verified `FirmMembership` | **IdentityAccess** | **Consumes it as a mandatory input through a port. Never decides, derives, infers, or approximates it.** |
| **`FirmContext` construction** | **PF-081** | **Yes — its single purpose**, and only after Firm identity, Actor identity, and active verified membership agree. |
| **HTTP adaptation** — request extraction, middleware ordering, route/host adaptation, session adaptation, request attributes, container binding | **PF-082** | **No.** |
| **Background-job adaptation** — job payloads, provenance carriage, serialization, queue wiring | A later approved transport story | **No.** PF-081 supplies the transport-neutral contract such an adapter calls. |
| **Authorization** — capabilities, roles, permissions, deny rules, Ethical Walls | Each owning domain; Practice Management alone for walls via `CheckEthicalWallAccess` | **No.** Resolution is not authorization. |
| **Entitlement** | `PlatformAdministration` state, evaluated by IdentityAccess at exactly two gates | **No.** Never a per-request input, never in PF-081. |
| **Transaction establishment** and the transaction-local Firm setting | **PF-073** | **No.** PF-081 never invokes it. |

**Exact proposed namespace and API.** All of the following are **proposed**, in `App\Application\Tenancy`, at `app/Application/Tenancy/`. Every import is either a Foundation type or a PHP global type; **no Laravel, Illuminate, Eloquent, HTTP, queue, container, facade, configuration, vendor SDK, or database reference appears anywhere.**

`AuthenticatedActor`, `ActiveFirmMembershipAssertion`, `FirmMembershipVerifier`, and `CandidateFirmDirectory` are **ports PF-081 declares and requires** — the narrow contracts PF-081 needs from future bounded contexts. **They are not, and must not be described as, IdentityAccess or PlatformAdministration domain types**, and PF-081 implements none of them. Each is satisfied later by its owning context's own type under that context's own approved story (B1–B4).

```php
namespace App\Application\Tenancy;

use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Identity\UuidV7Generator;
use App\Foundation\Tenancy\FirmContext;

// ---------- Ports required from IdentityAccess (B2, B3) ----------

/** An actor whose identity IdentityAccess has already authenticated. */
interface AuthenticatedActor
{
    public function actorId(): BusinessIdentifier;
}

/**
 * IdentityAccess's authoritative statement that a named actor holds an active,
 * verified membership in a named Firm, as at the moment it was verified.
 * It is a transported assertion from the authority, never a capability: holding
 * one grants nothing, and a retained instance is never re-used as authority.
 */
interface ActiveFirmMembershipAssertion
{
    public function firmId(): BusinessIdentifier;

    public function actorId(): BusinessIdentifier;
}

/** The authority. It may throw any failure; PF-081 normalizes that failure at its boundary. */
interface FirmMembershipVerifier
{
    public function verifyActiveMembership(
        AuthenticatedActor $actor,
        BusinessIdentifier $firmId,
    ): ActiveFirmMembershipAssertion;
}

// ---------- Candidate discovery: separate, unverified, outside the resolution path (B1, B4) ----------

/** Where an unverified candidate value came from. A provenance label, never an extractor. */
enum CandidateFirmSourceKind
{
    case Hostname;
    case CustomDomain;
    case RoutePath;
    case RequestParameter;
    case RequestHeader;
    case Cookie;
    case RequestBody;
    case SubmittedEmailDomain;
}

/** An untrusted candidate value. Structurally not a resolution input and never proof of anything. */
final readonly class CandidateFirmReference
{
    public function __construct(
        private CandidateFirmSourceKind $sourceKind,
        private string $unverifiedValue,
    ) {}

    public function sourceKind(): CandidateFirmSourceKind;

    public function unverifiedValue(): string;
}

/**
 * The narrow pre-context Firm-registry read ADR-016 Decision 4a permits, implemented
 * later by PlatformAdministration (B4). Returns a *candidate* identifier or null.
 * A returned identifier is not membership and grants no access to any Firm.
 */
interface CandidateFirmDirectory
{
    public function findCandidateFirmId(CandidateFirmReference $reference): ?BusinessIdentifier;
}

// ---------- The resolution boundary PF-081 owns ----------

final class VerifiedFirmContextResolver
{
    /** Deliberately takes no CandidateFirmDirectory, no connection, and no transaction manager. */
    public function __construct(
        private FirmMembershipVerifier $verifier,
        private UuidV7Generator $correlationIds,
    ) {}

    /**
     * Verify authoritatively, then construct. The supplied Firm identifier is a
     * proposal until the verifier asserts this actor's active membership in
     * exactly that Firm and the assertion's own identifiers agree with the inputs.
     *
     * @throws FirmResolutionDenied on every failure, with one indistinguishable outcome
     */
    public function resolve(
        AuthenticatedActor $actor,
        BusinessIdentifier $firmId,
    ): FirmContext;
}

/** The single uniform failure. Carries no discriminating detail. */
final class FirmResolutionDenied extends \RuntimeException
{
    private const MESSAGE = 'Firm context could not be resolved.';

    private function __construct()
    {
        parent::__construct(self::MESSAGE);
    }

    public static function denied(): self
    {
        return new self();
    }
}
```

**Input trust model.**

- **Only two things confer authority: the authenticated actor (`AuthenticatedActor`, from IdentityAccess) and the active-membership assertion (`ActiveFirmMembershipAssertion`, from `FirmMembershipVerifier`).** Nothing else does.
- **The supplied `$firmId` is a proposal, not authority.** It may legitimately originate from a verified session or from candidate discovery; PF-081 does not and cannot know which, so it treats it as unverified in either case. Authority is conferred **solely** by PF-081's own mandatory verification call. A caller-supplied Firm identifier that the verifier does not confirm produces no `FirmContext`.
- **No hostname, custom domain, email address, header, cookie, route value, request parameter, body value, or caller-supplied Firm identifier is ever accepted as proof of Firm membership.** `CandidateFirmReference` exists precisely so such a value is typed as unverified and can never be mistaken for an identifier.
- **PF-081 accepts no request, response, session, container, connection, configuration, array, or scalar identifier**, and no membership snapshot, role, permission, capability, entitlement, or authorization result.
- **Email is never a global identity key**, and PF-081 performs no cross-Firm identity linking, matching, merging, or lookup of any kind.
- **The correlation identifier is generated, never adopted.** It comes from `UuidV7Generator` and is never taken from a header, parameter, cookie, route, or body — the rule PF-080's contract already fixes.

**Output model.** Exactly one `App\Foundation\Tenancy\FirmContext`, unchanged, constructed **only** when all three agree: the verified Firm identity, the authenticated Actor identity, and the active verified membership binding them. Concretely: the assertion's `firmId()` must equal the supplied `$firmId`, and its `actorId()` must equal the actor's `actorId()`, both by `BusinessIdentifier::equals()`; a disagreement is a denial, never a reconciliation, a preference for one side, or a warning. Construction uses **named arguments**. On failure, no `FirmContext` is constructed, returned, cached, or partially built. Possessing the returned context proves no membership, entitlement, role, permission, Ethical Wall outcome, or resource access, and every protected action still performs its owning domain's own current authorization.

**Failure semantics — deny by default, fail closed.**

- **Every failure path throws `FirmResolutionDenied` and returns nothing.** There is no null return, no false, no empty context, no default Firm, no anonymous or system context, no partial result, and no silent success.
- **Absent, invited-only, suspended, revoked, and expired membership, an unavailable or indeterminate verifier, a disagreeing assertion, and a verifier that throws are all one outcome.** Availability never outranks Firm isolation (Constitution Article 30): an unreachable authority is a denial, never an assumption.
- **Only the verifier call is wrapped** in `catch (\Throwable)`, which is replaced by a fresh, unchained `FirmResolutionDenied::denied()`. Assertion comparison follows that block. Correlation-ID generation occurs only after verification and comparison succeed and **outside** that catch, so `\Random\RandomException` is never normalized into a membership denial.
- **No retry, backoff, fallback authority, degraded mode, or cached previous answer.** A second attempt is a fresh verification.
- **`\Random\RandomException` from correlation-identifier generation propagates untranslated**, consistent with `app/Foundation/README.md`: a CSPRNG failure is a catastrophic environment failure, not a domain condition, and must not be swallowed as a denial.

**Information-disclosure constraints.**

- **Different internal causes are externally indistinguishable.** The single factory `FirmResolutionDenied::denied()` produces one fixed message; there is no reason code, error code, subclass, cause enum, or public property from which a caller could distinguish "no such Firm" from "not a member" from "suspended" from "verifier unavailable".
- **No failure enumerates Firms, memberships, identities, or cross-Firm relationships**, and no message, exception property, or chained previous exception contains a Firm identifier, actor identifier, candidate value, hostname, domain, email address, membership state, or verifier detail. PF-081 constructs no message from input.
- **A verifier exception is deliberately not chained** into `FirmResolutionDenied`, so provider-specific detail cannot reach a caller through `getPrevious()`. `FirmResolutionDenied` has a private constructor and is created only by its fixed-message `denied()` factory, preventing verifier or caller code from attaching a discriminating message, code, or previous exception.
- **PF-081 messages are developer-facing diagnostics and must never be exposed verbatim to an external caller.** External response shape, text, and practicable timing normalization are PF-082's obligation at the transport boundary; PF-081 makes no timing-uniformity claim of its own and must not be described as providing one.
- **A `BusinessIdentifier` is not a secret but is sensitive metadata**; PF-081 renders none to a log, message, or output.

**Dependency direction.**

```text
PF-082 Tenant Middleware / a later background-transport adapter   (transport)
        │  calls
        ▼
PF-081 App\Application\Tenancy                                    (application contract)
        │  requires ports              │  produces
        ▼                              ▼
IdentityAccess (B2, B3)          PF-080 App\Foundation\Tenancy\FirmContext
PlatformAdministration (B1, B4)  PF-044 BusinessIdentifier / PF-048 UuidV7Generator
```

PF-081 depends on Foundation and on its own ports, and on nothing else. **Foundation does not depend on PF-081**; no Foundation file is added, edited, or referenced by PF-081's implementation, and the framework-dependency guard is untouched. PF-081 depends on **no** Laravel class, HTTP object, connection, container, module, repository, or business module, and on no other `PF-*` implementation except the Foundation types listed. **Nothing under `app/Foundation` may ever depend on `App\Application\Tenancy`.**

**Interaction with adjacent stories and contexts.**

- **PF-080 Firm Context (Done).** Consumed unchanged as the sole output type. PF-081 discharges the named-argument and transposition-test obligation PF-080's contract delegated to it. No `FirmContext` change is proposed.
- **PF-073 Transaction Manager (Done).** **Never invoked by PF-081.** PF-073 consumes a `FirmContext`; deciding when to open a transaction with one is PF-082's or a later application boundary's. PF-081 introduces no PostgreSQL setting, statement, connection, or transaction state, and the `onelegalpro.firm_id` constant remains PF-073's alone.
- **PF-082 Tenant Middleware (Backlog, unchanged).** Owns HTTP request extraction, candidate extraction from host/route/header, middleware registration and ordering, session adaptation, request attributes, container binding, external response shape and timing normalization, and composition of resolved context with PF-073. **PF-081 must not implement any of it**, and this contract does not make PF-082 Ready.
- **IdentityAccess.** Owns principals, membership, authentication, sessions, and membership verification. PF-081 consumes verified results through ports and **never** decides, derives, infers, caches, or approximates any of them. Identity ownership does not move into Platform Foundation.
- **`PlatformAdministration`.** Owns `Firm`, `FirmId`, the Firm registry, and its lifecycle. PF-081 reads no registry, owns no `Firm`, and creates no registry adapter, repository, query, or schema. Registry readability is not membership.
- **Practice Management.** Sole Ethical Wall authority via `CheckEthicalWallAccess`. PF-081 contains no wall logic, wall-shaped flag, or per-actor visibility rule; Release 0.1 has no wall to compose, and its absence is never stubbed.

**Synchronous and queued/background considerations.**

- **One contract serves both.** PF-081 is invoked identically on an interactive request and inside a queued job; queued and background work receives **no weaker treatment** than an interactive request (Constitution Article 28; `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §45).
- **A queued job preserves initiating identity provenance and re-verifies current membership at execution time.** The transport adapter carries the initiating actor's identity and provenance into the job and calls `resolve()` **afresh when the job runs**.
- **An old `FirmContext` is never an indefinite grant** and must not be serialized into a job payload, cached, stored, or replayed as authority. Because PF-081 holds no state and caches nothing, a job that re-resolves cannot obtain a stale positive: membership removed, suspended, revoked, or policy-changed between enqueue and execution yields a denial.
- **PF-081 itself defines no queue, job, worker, payload, serializer, scheduler, or provenance record.** Which transport carries provenance, and how, is a later approved story's contract.
- **A long-running or resumed operation re-resolves before each protected step** rather than holding one context open indefinitely. PF-081 makes this possible by being cheap and stateless; enforcing it is the calling story's obligation.

**Acceptance criteria.** A future PF-081 implementation is correct only if all of the following hold:

1. The types exist at exactly `app/Application/Tenancy/` in namespace `App\Application\Tenancy`, matching the complete API sketch above member for member; where the sketch includes a method body, that body is normative.
2. `VerifiedFirmContextResolver` is `final`, holds exactly the two declared constructor dependencies, and exposes exactly one public method, `resolve()`.
3. Its constructor accepts **no** `CandidateFirmDirectory`, connection, transaction manager, request, session, container, configuration, logger, clock, or cache, and the class declares no static or mutable property.
4. `resolve()` calls `verifyActiveMembership()` **exactly once, before** any `FirmContext` construction, and constructs nothing when it throws.
5. The returned assertion's `firmId()` and `actorId()` are compared by `BusinessIdentifier::equals()` against the supplied Firm identifier and the actor's identifier; **any disagreement denies**.
6. `FirmContext` is constructed with **named arguments**, from the verified Firm identifier, the actor identifier, and a correlation identifier obtained from `UuidV7Generator`; the exact instances are preserved.
7. **Firm and Actor cannot be transposed** — proved by a test in which the two identifiers are distinct concrete `BusinessIdentifier` subtypes, discharging PF-080's delegated obligation.
8. Every failure throws `FirmResolutionDenied`; no path returns null, false, a default, an empty, or a partial context.
9. All internal causes yield one indistinguishable failure: one fixed message, no reason code or cause discriminator, no chained verifier exception, and no identifier, candidate value, or membership state in any message or property.
10. A verifier that throws, returns a disagreeing assertion, or is unavailable produces the same denial as absent membership, with no retry, fallback, or degraded mode. The implementation catches `\Throwable` around **only** the verifier call, creates a fresh unchained denial, and performs correlation-ID generation after that catch scope.
11. Nothing is cached, memoized, stored statically, logged, or emitted as an event, metric, or audit record; two calls perform two verifications.
12. `\Random\RandomException` propagates untranslated.
13. `CandidateFirmReference` is `final readonly`, carries exactly a source kind and an unverified string, and is reachable from no resolution path.
14. `CandidateFirmDirectory` and `FirmMembershipVerifier` have **no production implementation** anywhere in the repository.
15. No production file imports Laravel, Illuminate, Eloquent, HTTP, queue, container, facade, configuration, vendor SDK, or database symbols; no Laravel global helper is called.
16. No Foundation production source or Foundation test is added or edited; the documentation-only `app/Foundation/README.md` status update is the sole allowed Foundation-tree change, and `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php` passes **byte-identical and unchanged**.
17. `App\Infrastructure\Database\FirmTransactionManager` is neither imported nor invoked, and `app/Infrastructure/Database/FirmTransactionManager.php` is unchanged.
18. No concrete `FirmId`, `ActorId`, `PrincipalId`, `Firm`, `FirmMembership`, principal, session, entitlement, role, capability, or authorization type is created.
19. No middleware, controller, route, service provider, container binding, migration, schema, RLS policy, repository, Eloquent model, module, or `app/Modules` directory is created.
20. `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `phpunit.xml`, `phpstan.neon.dist`, `pint.json`, `.github/`, `.githooks/`, and Docker/environment files are unchanged, and no dependency is added.

**Definition of Ready — NOT met.**

*Resolved:* objective; owner; responsibility boundary; namespace (owner-confirmed decision 1); candidate-discovery scope (owner-confirmed decision 2); exact API; input trust model; output model; failure semantics; disclosure constraints; dependency direction; PF-080/PF-073/PF-082 interaction; synchronous and queued treatment; acceptance criteria; security implications; allowlist and forbidden files; test plan; validation plan.

*Not resolved — why PF-081 is not Ready:* **its mandatory dependencies do not exist.** B3 is decisive: PF-081's entire purpose is to construct a `FirmContext` from an authoritatively verified active membership, and **no IdentityAccess membership verification contract exists** (EPIC-009 stage 2, `Backlog`). B2 leaves `AuthenticatedActor` unimplementable (EPIC-009 stage 1, `Backlog`). B1 leaves no `Firm`, no owned `FirmId`, and no registry (EPIC-012 story 1, `Backlog`), and B4 leaves `CandidateFirmDirectory` without an adapter. This dependency order does not contradict the roadmap's statement that PF-080/PF-081/PF-082 are mandatory for the Release 0.1 business slice: the pre-context class-(b) Firm registry exists precisely before `FirmContext` and does not require PF-081, while Firm-scoped business records remain blocked on the runtime sequence. Implementing PF-081 today would produce ports no context can satisfy and a resolver with no authority to consult — and the only ways to make it executable would each be prohibited: a temporary scalar, a framework object, a session bag, a stub, a fake, or a null-object verifier.

**Therefore PF-081 remains `Backlog`.** The technical owner has decided to hold PF-081 implementation until B1–B4 exist; **approving this contract does not make PF-081 Ready, and nothing here schedules, starts, or authorizes implementation.**

**Definition of Done.** PF-081 is Done only when: the approved contract is implemented strictly within its final allowlist; every acceptance criterion above passes; the focused suite passes; the unchanged PF-049 Foundation guard and PF-033 PostgreSQL engine/role guard pass byte-identical; the complete canonical PostgreSQL-backed suite passes with no removed, weakened, renamed, skipped, or suppressed test, assertion, guard, static-analysis level, formatting rule, security control, or approval gate; Pint, PHPStan level 5 (no baseline, suppression, or ignored error), `composer validate --strict`, both dependency audits, and `git diff --check` pass; independent review reports no unresolved P0, P1, or P2; **all four required protected checks pass on the exact final implementation head**; the required human approval comment is recorded; the PR merges to `main`; tracking records only verified evidence; and the implementation branch and worktree are removed locally and remotely. **Done would make no claim** that PF-082 middleware, HTTP tenant enforcement, tenant isolation as a whole, Row-Level Security, Firm schema, application-runtime roles, repositories, IdentityAccess, `PlatformAdministration`, audit, or any business module is implemented, or that Release 0.1 is production-ready.

**Security implications.**

- **Never treats a hostname, custom domain, email address, header, cookie, route value, request parameter, body value, or caller-supplied Firm identifier as proof of Firm membership** (Constitution Article 27; ADR-016 alternatives). Authority comes only from IdentityAccess's assertion.
- **Candidate-Firm discovery and verified membership remain separate facts**, separated by type and by the resolver's constructor rather than by convention.
- **`FirmContext` is constructed only from verified identity and active verified membership**, and only when all three facts agree.
- **Resolution is not authorization.** A resolved context is not a capability, role, permission, entitlement, session, Ethical Wall outcome, or proof of provenance; every protected action still composes its owning domain's current authorization, and Practice Management alone decides walls.
- **Deny by default, fail closed, enumeration-resistant**, with availability never outranking Firm isolation.
- **No stale positive can survive** membership removal, suspension, role revocation, policy change, or session invalidation, because nothing is cached and every call re-verifies.
- **PF-081 deliberately does not record security events.** The calling transport/application boundary must submit an enumeration-safe platform-realm security event when PF-081 denies resolution, including an assertion/input identity disagreement, through the future IdentityAccess recording contract required by ADR-018 Decision 5a. Until that contract exists, the calling path is not Ready; it must never log identifiers or candidate values locally, attribute an unverified event to a Firm, or suppress the recording obligation.
- **Queued work preserves initiating provenance and re-verifies**; an old context is never an indefinite grant.
- **No cross-Firm enumeration, linking, matching, or email-as-global-identity behavior**, and no Firm switching — a Firm change is an explicit authorized transition owned by IdentityAccess, carrying no authorization across.
- **No secret, credential, session token, recovery material, identifier, candidate value, or privileged content is logged, chained, serialized, or placed in any message** — PF-081 logs nothing at all.
- **This is an authorization- and Firm-isolation-adjacent change and therefore hits the `AGENTS.md` approval gate** before implementation, independently of this contract's approval.
- **No security or production-readiness claim is made beyond what executable acceptance criteria can prove.** PF-081 does not complete tenant isolation: application-layer resolution is one of the four independent layers ADR-016 Decision 5 requires, alongside Firm-scoped repositories, forced Row-Level Security, and executed PostgreSQL tests — **none of the other three exists**.

**Documentation impact.** On implementation: this entry and `docs/PROJECT_STATUS.md` record verified status and delivery evidence; `docs/implementation/01_Implementation_Sprint_Plan.md` records the lifecycle note; `app/Foundation/README.md` may record only that PF-081 landed **outside** Foundation, without placing any code under Foundation and without a namespace-table change. **No architecture or ADR file is edited by implementation.** No architectural contradiction requiring an ADR amendment was found in preparing this contract: the ADR-016 Decision 4a "tenant resolver" registry role is reconciled by decision 2, and the `docs/architecture/03_Database_Design.md` §24 `app/Foundation/Persistence` sketch — explicitly conceptual, creating no directory — is reconciled by decision 1 on the pattern PF-073 Decision 1 established.

**Allowed implementation files.** A future approved PF-081 implementation may create or change **only**:

- `app/Application/Tenancy/AuthenticatedActor.php`;
- `app/Application/Tenancy/ActiveFirmMembershipAssertion.php`;
- `app/Application/Tenancy/FirmMembershipVerifier.php`;
- `app/Application/Tenancy/CandidateFirmSourceKind.php`;
- `app/Application/Tenancy/CandidateFirmReference.php`;
- `app/Application/Tenancy/CandidateFirmDirectory.php`;
- `app/Application/Tenancy/VerifiedFirmContextResolver.php`;
- `app/Application/Tenancy/FirmResolutionDenied.php`;
- `tests/Unit/Application/Tenancy/VerifiedFirmContextResolverTest.php`;
- `tests/Unit/Application/Tenancy/TenancyContractShapeTest.php`;
- `docs/implementation/03_Engineering_Backlog.md` and `docs/PROJECT_STATUS.md`, for verified lifecycle and evidence updates;
- `docs/implementation/01_Implementation_Sprint_Plan.md`, for the lifecycle note only;
- `app/Foundation/README.md`, **only** to record that the resolver landed outside Foundation.

Any additional source, test, provider, configuration, workflow, or database file requires a separately reviewed contract correction and explicit approval before editing.

**Forbidden files and changes.** No change to `app/Foundation/Tenancy/FirmContext.php`, `app/Infrastructure/Database/FirmTransactionManager.php`, or **any** existing Foundation or Infrastructure source or test file. No change to `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `phpunit.xml`, `phpstan.neon.dist`, `pint.json`, `.github/`, `.githooks/`, Docker or environment files, Laravel bootstrap or configuration, service providers, routes, controllers, middleware, jobs, commands, migrations, schema, RLS policies, PostgreSQL roles/grants/functions, modules, `app/Modules`, repositories, outbox, audit, or idempotency persistence. **No architecture or ADR file may be edited by the implementation story.** **No required check, test, assertion, guard, static-analysis level, formatting rule, security control, or approval gate may be removed, weakened, renamed, skipped, or suppressed.**

**Explicit exclusions.** PF-081 delivers none of, and must not stub, approximate, simulate, partially implement, or rename any of: authentication, credentials, password hashing, MFA, passkeys, magic links, invitations, recovery, lockout; session issuance, storage, rotation, revocation, or adaptation; principals, actor categories, service principals, delegation, acting-on-behalf-of, privileged-access or break-glass grants; `Firm`, `FirmId`, `FirmProvisioning`, `SubscriptionEntitlement`, the Firm registry, its schema, repository, query, or adapter; `FirmMembership` and its lifecycle; entitlement or seat-limit evaluation; authorization, roles, capabilities, permissions, deny rules, authorization decisions or their caching; **Ethical Walls of any shape** — no wall, flag, allow-list, restricted-matter marker, or per-actor visibility rule; Firm switching or realm transition; HTTP middleware, controllers, routes, request or response handling, request attributes, container bindings, service providers; queues, jobs, workers, schedulers, payload serialization, provenance records; database schema, migrations, RLS policies or functions, roles, grants, connections, transactions, or the transaction-local Firm setting; repositories, Eloquent models, casts, DTOs, persistence, or serialization; caches of any kind; events, outbox, audit, security-event recording, logging, telemetry, or metrics; rate limiting, timing normalization, or response shaping; concrete identifier types; test doubles under `app/`; and every business module. **PF-082 Tenant Middleware remains `Backlog` and is not silently implemented, in whole or in part, by PF-081.**

**Focused test plan.** Two pure unit suites under `tests/Unit/Application/Tenancy/`, extending `PHPUnit\Framework\TestCase` directly, booting no Laravel application and touching no database. Test-local fixtures — concrete `BusinessIdentifier` marker subtypes, a scripted `AuthenticatedActor`, a scripted `ActiveFirmMembershipAssertion`, scripted verifiers (agreeing, disagreeing, denying, throwing, and call-counting), and a scripted `UuidV7Generator` — are declared **inside the test files**, per the standing rule that no test double belongs under `app/`.

*`TenancyContractShapeTest`* proves by reflection: exact namespaces and file locations; `VerifiedFirmContextResolver` final with exactly two constructor parameters of the exact declared types and exactly one public method; the absence, by name, of a `CandidateFirmDirectory`, connection, transaction-manager, request, session, container, configuration, logger, clock, and cache parameter or property; no static or mutable property; `CandidateFirmReference` final and readonly with exactly its two values; each port's exact method set and signatures; `FirmResolutionDenied` final, extending `\RuntimeException`, with a private constructor, one private fixed-message constant, exactly one public factory `denied()`, and no reason code, cause discriminator, public property, or caller-controlled previous exception; that no production file under `app/Application` references a forbidden framework namespace or Laravel global helper; that `FirmTransactionManager` and the `onelegalpro.firm_id` literal appear nowhere under `app/Application`; and that **no production implementation of `FirmMembershipVerifier`, `AuthenticatedActor`, `ActiveFirmMembershipAssertion`, or `CandidateFirmDirectory` exists anywhere under `app/`**. The three candidate-discovery types are intentionally unused until B4 implements the port; that is the approved consequence of decision 2, not dead production behavior claimed as executable.

*`VerifiedFirmContextResolverTest`* proves behaviourally: a `FirmContext` is returned only on the fully agreeing path, carrying the exact supplied identifier instances and a generator-produced correlation identifier; verification is invoked exactly once and strictly before construction; **Firm and Actor cannot be transposed**, using two distinct concrete identifier subtypes; a Firm-identifier disagreement, an Actor-identifier disagreement, a denying verifier, and a throwing verifier each raise `FirmResolutionDenied` and construct nothing; all four failures produce an **identical** message with no identifier, candidate value, or membership detail, and `getPrevious()` is `null` on the throwing-verifier path; two calls perform two verifications, proving nothing is cached; a candidate value cannot enter the resolution path; and `\Random\RandomException` from the generator propagates untranslated.

**Full validation plan.** In the canonical Docker PHP 8.4 / PostgreSQL 16 environment, against a disposable non-superuser `NOBYPASSRLS` role and database per the documented `CONTRIBUTING.md` recipe: `git diff --check`; `composer validate --strict`; `composer pint:test`; `composer phpstan` at level 5 with no baseline, suppression, or ignored error; the two focused suites; `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php` passing **byte-identical and unchanged**; `tests/Feature/PostgreSqlTestDatabaseGuardTest.php` passing unchanged; the existing PF-080 `tests/Unit/Foundation/Tenancy/FirmContextTest.php` and PF-073 `tests/Unit/Infrastructure/Database/FirmTransactionManagerTest.php` and `tests/Feature/Infrastructure/Database/FirmTransactionManagerPostgreSqlTest.php` passing unchanged; the complete PostgreSQL-backed suite passing with a recorded test and assertion count strictly above the measured pre-implementation baseline; and `composer audit --locked --abandoned=report` and `npm audit --audit-level=high` both clean. Independent review and architecture review record no unresolved P0, P1, or P2.

**The four required `Protect main` checks remain exactly:**

- `PHP Code Quality`
- `Frontend Build`
- `Application Tests`
- `Dependency Audit`

**Implementation sequencing decision (approved 12 August 2026).** Hold PF-081 implementation until B1–B4 exist. Implementing the ports and resolver ahead of their authoritative production implementers would create unusable security-shaped abstractions and would not advance an executable tenant-resolution path. The next work is the dependency chain: PlatformAdministration EPIC-012 story 1, IdentityAccess EPIC-009 stage 1, IdentityAccess EPIC-009 stage 2, and the concrete PlatformAdministration `CandidateFirmDirectory` adapter. After those land, PF-081 readiness is reassessed under the `AGENTS.md` authorization gate.

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
- **ARCH-011 — Release 0.1 Rescope, Platform Administration Ownership, and Deferred-Control Decisions — Completed (architecture Approved).** Explicit owner approval was recorded on PR #30 on 29 July 2026. Constitution Articles 45–48 are Approved; the release-scoped product requirements and Platform Administration architecture are Approved; and ADR-012 through ADR-015 are Accepted. The decision establishes PlatformAdministration ownership, the Release 0.1 Matter Desk pilot scope, entitlement/session separation, operator-access constraints, and bounded deferred controls. **Architecture approval schedules no implementation and authorizes no deployment or production access.** No code, schema, migration, test, CI, dependency, Docker, deployment, or GitHub-settings change; the four required checks are unchanged; PF-040 remains next and Backlog; and no story is In Progress.
- **ARCH-012 — Data & Persistence Architecture — Completed (architecture Approved).** Explicit repository-owner approval was recorded on PR #34 on 1 August 2026. `docs/architecture/03_Database_Design.md` is Approved and ADR-016 through ADR-021 are Accepted; the eight Thai legal-review decisions remain recorded separately in `docs/legal/ARCH-012-Thai-Legal-Review.md`. Approval schedules no implementation and authorizes no deployment or production access. PF-033 was subsequently implemented under its own approved story contract and is Done.
- **ARCH-013 — Deployment & Operations Architecture — Completed (architecture Approved).** Explicit repository-owner approval was recorded on PR #35 on 1 August 2026 after independent review and all four required `Protect main` checks passed on commit `a163fce`. `docs/architecture/20_Deployment_Operations_Architecture.md` is Approved and ADR-022 through ADR-026 are Accepted. Approval satisfies the deployment-architecture evidence item only; it schedules no implementation, selects no provider, authorizes no expenditure, deployment, credential, or production access, and leaves restore-test, monitoring, incident-procedure, legal-review, and applicable approval-gate evidence unsatisfied.

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

# EPIC-012 — Platform Administration & Release 0.1 Matter Desk (proposed, not scheduled)

Architecture is **Approved** (ARCH-011 — Completed through explicit owner approval recorded on PR #30 on 29 July 2026). Implementation is **not scheduled**. The staged story list in `docs/architecture/08_Roadmap.md` (EPIC-012) remains **proposed only**; **none of these stories is approved, scheduled, assigned a story ID, or Ready**, and **every one of them is `Backlog`**. Each requires its own approved entry here, with a Definition of Ready, before implementation begins.

**`PF-040` — AggregateRoot is Done, unchanged in scope by this epic. No story in this epic is `Ready`, `In Progress`, or `Code Review`.** Planning is not scheduling, and architecture approval never schedules implementation.

This epic establishes `PlatformAdministration` as a **narrowly bounded supporting context owning exactly three concepts — `Firm`, `FirmProvisioning`, and `SubscriptionEntitlement`** — and records **Release 0.1, the OneLegalPro Matter Desk**: a founding-firm pilot slice across Platform Foundation, `PlatformAdministration`, IdentityAccess, and Practice Management. It resolves the **`Firm` ownership gap** — a concept referenced by `FirmMembership`, `FirmContext`, per-Firm `BrandProfile`/`TenantDomain`, and every Firm-scoped aggregate, and owned by no approved context.

Ownership is preserved elsewhere: **IdentityAccess alone owns credentials, authentication, sessions, membership, MFA, recovery, invitations, and privileged-access grants**, and evaluates entitlement and seat limits at the **authentication / session-issuance** and **membership-activation** gates only — never as a per-request resource-authorization input; **Practice Management alone owns `Client`, `Matter`, `MatterClient` and `MatterTeam` as Matter-owned entities, `Task` as an independent aggregate referencing `Matter`, and — when built — Ethical Walls and conflict checking**; **Billing alone owns Firm-to-client commercial and financial records**; **Branding alone owns `BrandProfile` and custom domains**; **Digital Presence alone owns Firm tenant websites and the Client Portal**; and **AI holds no authority anywhere — Release 0.1 contains no AI capability at all.** `PlatformAdministration` authenticates nothing, authorizes nothing, and never reads Firm business data.

## Release 0.1 — dependency-ordered story outline (all `Backlog`)

**Prerequisites outside this epic, in order:**

- **`PF-040` AggregateRoot — Done.** Completed under its own approved entry above through PR #31. Unchanged by this epic, which neither scheduled nor authorized it.
- **`PF-033` PostgreSQL Continuous Integration — `Done`; its story contract is defined under EPIC-001 and PR #44 merged as `40e7b0d` on 10 August 2026, satisfying the prerequisite that it land before `PF-080` begins.** Its implementation moves the required `Application Tests` job to an ephemeral PostgreSQL 16 service with a disposable non-superuser test role and fail-closed engine/role guard while retaining `phpunit.xml`'s SQLite `:memory:` fallback for ordinary local runs. **The four required `Protect main` check names (`PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit`) remain unchanged.** **ARCH-011 implemented no CI change; PF-033 proceeded later under its own approved contract.**
- **Minimum Platform Runtime (Sprint 0.4 subset) — `Backlog`.** `PF-080` Firm Context, `PF-081` Tenant Resolver, and `PF-082` Tenant Middleware are **mandatory**. `PF-091` Transactional Outbox is included where a committed audit or event fact must be durable with the state change that produced it.

  **Honest prerequisite analysis for `PF-091`, recorded as required by `docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md` Decision 3:** `PF-091` plainly requires `PF-043` DomainEvent (**Done**) and `PF-040` AggregateRoot (**Done**) for aggregates to record events, and `PF-073` Transaction Manager for the outbox record and the state change to commit atomically as `docs/domain/06_Laravel_Module_Blueprint.md` requires. Whether it additionally requires `PF-090` Event Dispatcher, `PF-092` Event Publisher, or any part of `PF-060`–`PF-063` and `PF-070`–`PF-072` **has not been determined and is not asserted here** — that is `PF-091`'s own approved pre-implementation analysis to perform. **This list is deliberately incomplete rather than speculatively complete.** `PF-093` Consumer foundation is **not** required by Release 0.1, which has no consumer. Module-generation tooling (`PF-062`) is **not** required. **Nothing here schedules, renumbers, reorders, or renames any `PF-*` story.**

**`PlatformAdministration` stories — all `Backlog`:**

1. **`Firm` aggregate foundation** — identity, canonical name, jurisdiction reference, lifecycle state, audited transitions. Depends on `PF-040`, `PF-080`.
2. **`FirmProvisioning` lifecycle** — requested → provisioned → active → closed; every transition explicitly human-authorized and audited; **a Firm never becomes usable as a side effect**, and there is no self-service signup. **No Firm-level suspended state and no Firm-wide disable** — a future Firm-level suspension or emergency-disable capability is its own separately approved decision, defining authorizing authority, session effects, recovery, notification, queued-and-in-flight-work behaviour, and audit semantics. Depends on 1.
3. **`SubscriptionEntitlement`** — term, status, seat limit, **with the monetary prohibition enforced by the model itself**: no amount, price, currency, `Money`, `Currency`, invoice, payment, ledger entry, balance, discount, proration, tax rate, or tax treatment, and no field from which one could be reconstructed. Depends on 1.
4. **Entitlement lapse semantics and the published entitlement query** — the **fail-closed** contract IdentityAccess consumes; expired or suspended entitlement is a **commercial/administrative** fact that **blocks new authentication** while existing valid sessions continue only to normal expiry, whereas membership suspension or revocation is an **individual security event** terminating that principal's sessions immediately; **the two never share code, audit meaning, or policy semantics, and neither is a Firm-wide disable**. Depends on 3.
5. **Seat-limit contract** — deterministic, evaluated at membership activation, **never retroactive**; a limit reduced below the active count **never silently revokes anyone**, and the over-limit condition is surfaced for an authorized human. Depends on 3.
6. **Administrative audit stream** — `PlatformAdministration`'s **own** append-only Firm, provisioning, entitlement, seat-limit, and authorization-refusal facts, **distinct from IdentityAccess's security event stream** and **never a copy of another context's audit records** (Practice Management business/activity history, IdentityAccess security-event content, or privileged narratives). **Firm-visible support-access history remains IdentityAccess's** (story 16). Payloads carry **safe metadata only** — never credentials, session secrets, recovery material, privileged content, Client or Matter content, or cross-Firm information. Correction is a new record, never a rewrite; closing a Firm never destroys audit history. Depends on 1–5.

**IdentityAccess Release 0.1 stories — all `Backlog`.** Every one of these is an **authentication or authorization change** and therefore additionally requires the explicit human approval gate in `AGENTS.md` before merge:

7. **Principal, actor-reference, and Firm security-realm foundation.** Depends on `PF-080`, and on `PlatformAdministration` story 1 for `FirmId`.
8. **Firm membership, with suspension and revocation** — `FirmMembership` as the access authority; suspension or revocation is an **individual security event terminating that principal's sessions immediately** with cached positive decisions invalidated. **This is a per-principal control, never a Firm-wide disable.** Depends on 7.
9. **Secure invitation** — Firm-bound, purpose- and role-scoped, time-limited, single-use; **an invitation is an offer, not proof of identity or entitlement**. Depends on 8.
10. **Password authentication** — approved adaptive hashing; credentials never recoverable and never in logs, events, or audit payloads. Depends on 7.
11. **Mandatory MFA and recovery** — recovery is a privileged, audited workflow that **never silently bypasses MFA or Firm policy**. Depends on 10.
12. **Firm-bound sessions** — one Firm context per session, identifier rotation after authentication and elevation, prompt revocation. Depends on 10.
13. **Firm Admin and Member capability bundles** — domain-published capability vocabulary; **hiding a button is never authorization**. Depends on 8.
14. **Authorization-decision composition** — deny-by-default, server-side, **authorize before retrieval, search, filtering, aggregation, and export**; a denied caller receives no content, metadata, count, aggregate, search hit, or existence confirmation. Depends on 8, 12, 13. **Release 0.1 has no Ethical Wall result to compose — not a wall result defaulting to allow** (see story 21).
15. **Entitlement and seat-limit enforcement** — consumes `PlatformAdministration` stories 4 and 5 at **exactly two gates**: the **authentication / session-issuance** gate, where entitlement is evaluated **only after successful credential and any required MFA verification and before a session is issued**, and the **membership-activation** gate for the seat limit. **Never evaluated earlier** (an unauthenticated caller must not learn whether a Firm exists or whether its subscription is active) and **never as a per-request resource-authorization input for an already-issued valid session** (the approved lapse policy lets that session run to normal expiry). **Session renewal, reauthentication, and issuance of any new Firm-bound session are new authentication decisions and re-check entitlement.** **Failure responses are enumeration-resistant in text, response shape, and practicable timing**; a **verified** user may receive the minimum safe operational instruction, revealing nothing about another Firm or account. **Entitlement is not a term in the story-14 authorization composition and never grants resource access**; it **fails closed** — no session is issued when entitlement state cannot be authoritatively determined. Depends on 4, 5, 8, 10, 11, 12, 14.
16. **Operator-assisted onboarding and support access** — IdentityAccess `PrivilegedAccessGrant` only, purpose-bound, time-limited, step-up authenticated, **dually attributed with no silent impersonation**, and recorded in a **Firm-visible, append-only support-access history** carrying operator identity, purpose/justification, start and expiry, authorization relied on, and actions performed. **Break-glass is excluded from Release 0.1 as a capability; Constitution Article 29 remains fully in force and unamended.** Depends on 7, 12, 14.

**Practice Management Release 0.1 stories — all `Backlog`:**

**Sequencing note.** The approved `Opened` invariants (`docs/architecture/13_Practice_Management_Architecture.md` §3, `docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md` Decision 4) make **`MatterClient`, manual conflict attestation, and responsible-lawyer assignment all preconditions of opening a `Matter`** — a `Matter` cannot reach `Opened` without a recorded attestation outcome, exactly one Primary `MatterClient`, and at least a Responsible Lawyer on the `MatterTeam`. The `Matter` aggregate is therefore split into a **foundation story supporting `Prospective` creation and cancellation only** (story 18) and a separate **opening story** (story 22) that assigns `MatterNumber` and delivers the reduced lifecycle once its three preconditions exist. `MatterNumber` is assigned **only** at `Opened`, so nothing about it belongs to story 18.

17. **`Client` foundation** — the party aggregate only. `Organization` and `Contact` are **out of scope**. Depends on `PF-080`, 14.
18. **`Matter` aggregate foundation — `Prospective` creation and cancellation only** — the aggregate, its Firm scoping, creation into the `Prospective` state, and the terminal `Prospective → Cancelled` path required when a candidate Matter never proceeds. **No `MatterNumber` is assigned, no `Opened` transition exists, and no other lifecycle transition is delivered by this story.** Depends on 17.
19. **Plural `MatterClient`** — a Matter-owned entity referencing `Client` by identifier; "Primary" is administrative only and implies no lesser status for a co-client. Establishes the model and the Primary designation; **the `Opened`-time invariant is enforced by story 22**. Depends on 17, 18.
20. **Manual conflict attestation** — an **actor-attributed, timestamped, append-only** record of a **human determination**: attesting actor, timestamp, Matter, parties as recorded at that time, outcome (clear, or proceeding with recorded justification), and mandatory justification where not clear. An unrecorded checkbox, default-true field, nullable flag, or silent-pass path is **prohibited**, and no surface may describe it as a conflict check performed by the system. **A `Matter` cannot reach `Opened` without a recorded attestation; that gate is enforced by story 22.** Depends on 18, 19.
21. **`MatterTeam` and responsible-lawyer assignment** — a Matter-owned entity; assignment is an explicit human command. **This must be available before opening**, because reaching `Opened` requires at least a Responsible Lawyer assigned to the `MatterTeam`. **No `EthicalWall` is delivered**; matter-level access restriction is absent from Release 0.1 and is **never stubbed or approximated** (`docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`). Depends on 18.
22. **Matter opening, `MatterNumber` assignment, and the reduced lifecycle** — adds `Prospective → Opened → Active → Closed` and `Opened → Cancelled`, while preserving story 18's terminal `Prospective → Cancelled` path. **`Paused`, `Awaiting Client`, `Awaiting Court`, and `Archived` are deferred, not removed.** **One Firm-wide numbering scheme**, because `PracticeArea` is out of scope; Firm-configurability, per-Firm uniqueness at assignment, **concurrency-safe assignment**, and **immutability from `Opened`** are all preserved. **Enforces every `Opened` invariant**: a recorded manual conflict attestation outcome exists; exactly one Primary `MatterClient` and never absent afterwards, with removal of the sole Primary designating a replacement in the same operation; at least a Responsible Lawyer assigned; a `MatterNumber` assigned and immutable from this point. Every transition is an **explicit, human-authorized command emitting `MatterStatusChanged`**, never inferred from other activity. Depends on 18, 19, 20, 21.
23. **`Task` and deadline management** — an **independent aggregate referencing `Matter`**, Matter-wide by default with an optional specific-`MatterClient` reference. **No reminders, notifications, escalation, or alerting of any kind.** Follows the usable Matter lifecycle. Depends on 19, 22.
24. **Firm Worklist and filtering** — **authorize before retrieval**, never after. Because no Ethical Wall exists, **every Firm member with Worklist access sees every Matter in the Firm**; the in-product disclosure required by **story 25** attaches here. Depends on 14, 22, 23.
25. **In-product pilot limitation disclosures** — the owning story for the **in-product** half of the disclosure obligation in `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` Decision 6 and 7. It must surface, **where reliance would occur**, that Release 0.1 has: **no Ethical Walls** (no matter-level access restriction; every Firm member with Worklist access sees every Matter); **no automated conflict checking** (the product searches for, detects, screens for, and clears nothing — the attestation is a human determination); **no automated reminders or notifications** (a recorded deadline is not a reminder, and a missed one is not detected); and **no document storage or upload** (nothing may be stored in the product, and no Matter record is a document repository). Required placements at minimum: **Matter opening** (story 22), the **Firm Worklist** (story 24), **Task and deadline surfaces** (story 23), and **any surface where a user could reasonably expect document upload or storage**. Disclosure is not buried in a settings page or a footnote. **This story must be complete before pilot readiness.** **The contractual half — the pilot agreement, Privacy Notice, and Terms — is separately owned by story 33** and is not satisfied by this story, nor this by that. Wording is **draft until Thai-qualified human legal review and owner approval are recorded** (`docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md` Decision 8). Depends on 22, 23, 24.

**Cross-cutting Release 0.1 stories — all `Backlog`:**

26. **Actor-attributed audit / activity history** — **designed before the IdentityAccess slice, not retrofitted after it**; append-only, correction by new record, not editable by the actor being audited; human, system, and operator actors remain distinguishable. Depends on `PF-091` and its determined prerequisites; consumed by stories 6–25.
27. **Firm-scoped export** — **authorize before retrieval, aggregation, and export**; no cross-Firm content, count, aggregate, or existence signal. A **destructive-operations and data-egress approval gate** applies. Depends on 14, 26.
28. **Thai text correctness** — encoding, collation, normalization, sorting, and rendering across `Client`, `Matter`, `Task`, and export. **Release 0.1 renders no legal source text and no translated legal provision**, so it creates no mandatory-disclaimer surface and makes no legal-content claim; Constitution Articles 1–4 remain fully in force. Depends on `PF-080`, 17, 18, 22.

**Operations stories — `Backlog`, not application code:**

29. **Backups and an executed, recorded restore test.** Evidence of an **actually executed** restore is a production-access precondition; a documented backup plan alone is not.
30. **Operational monitoring.**
31. **Documented incident procedure.**

**Operator surface stories — `Backlog`, outside `app/Modules` and outside Digital Presence:**

32. **Public OneLegalPro marketing website and inquiry form** — the **operator's own surface**, categorically **not a Firm tenant website and not the Client Portal** (both EPIC-005, out of scope). Creates **no Client Portal principal, no `ClientPortalAccessProfile`, and no Firm membership**, and does not weaken the Firm-facing white-label rules. Personal data in the inquiry form is never placed in URL parameters or query strings.
33. **Privacy Notice, Terms, and pilot agreement** — the **contractual** half of the disclosure obligation, covering the **four mandatory disclosures**: absent Ethical Walls, absent automated conflict checking, absent automated reminders and notifications, and absent document storage. **This is distinct from, and does not substitute for, the in-product disclosures owned by story 25** — the person opening a Matter is often not the person who signed the agreement, and the person who signed it is deciding whether to adopt the product at all. **External Thai-qualified legal review is mandatory**, and **all of this copy — plus all marketing and security copy — remains draft until that review and the owner's approval are recorded.** No reviewer is engaged and no review has occurred.

## Standing constraints for this epic

- **Every story above is `Backlog`.** None is Ready, In Progress, Code Review, Architecture Review, QA, Approved, or Done.
- **Pull requests remain serialized — one active implementation PR at a time**, one approved story per PR, per `CONTRIBUTING.md`.
- **Authentication, authorization, database-design, security-control, and destructive-operation changes each require the explicit human approval gate** in `AGENTS.md` before merge, regardless of this epic's planning status.
- **No control may be weakened to meet a date.** Firm isolation, authorize-before-retrieval, denied-existence confidentiality, actor attribution, human approval, immutable audit, fail-closed behaviour, white-label presentation, and the Thai-language legal-authority rules are not schedule variables.
- **No absent control may be stubbed, approximated, simulated, partially implemented, or renamed**, and the absence of a normally assumed capability is disclosed in-product and in the pilot agreement.
- **`PF-045` — Money and `PF-046` — Result remain `Backlog` and deferred from Release 0.1.** Any proposal to record a monetary value in `SubscriptionEntitlement` reopens `PF-045` as a hard prerequisite and requires a new approved ADR.
- **No production-readiness, certification, or compliance claim is made.** **15 September 2026 is a target, not a contractual commitment**, and production access requires all evidence listed in `docs/architecture/02_Product_Requirements.md` §8. The database-design and deployment-architecture evidence items are approved; the executed restore test, operational monitoring, documented incident procedure, separate Thai-qualified legal review required by ADR-012 Decision 8, and every applicable `AGENTS.md` implementation approval remain outstanding.
