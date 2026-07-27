# Foundation Library

**Sprint 0.3 — Foundation Library (PF-040 through PF-049).**

This document is the standing convention record for `app/Foundation`. It was created by **PF-049 — Foundation Exception Hierarchy**, the first Foundation story, and extended by **PF-047 — Clock**, the second. It states what Foundation is, what it may never contain, and the rules every later Foundation story must obey. It is not a status page — `docs/PROJECT_STATUS.md` and `docs/implementation/03_Engineering_Backlog.md` remain authoritative for story status.

## What Foundation is

`app/Foundation` contains **reusable technical domain primitives** — the small, shared building blocks every module needs in order to express a domain at all. It is **never a place for business capabilities**, and it is **never a utility dumping ground**: a type belongs here only because it is a genuine, reusable domain primitive, not because there was nowhere else convenient to put it.

**Business capabilities belong under `app/Modules`** and require their own separately approved stories. Nothing in this document authorizes a business module. See [`docs/domain/06_Laravel_Module_Blueprint.md`](../../docs/domain/06_Laravel_Module_Blueprint.md) and [`AGENTS.md`](../../AGENTS.md).

**Foundation depends on no business module.** The dependency direction is one-way: modules consume Foundation. A module must never duplicate, fork, or shadow a Foundation primitive with its own incompatible version — a second money type, a second identifier type, a second domain-exception root, and so on are all prohibited.

## Approved domain root and concern namespaces

`App\Foundation\Domain` is the approved Foundation domain root.

The approved concern namespaces, and the PF story that owns each, are:

| Namespace | Owning story |
| --- | --- |
| `App\Foundation\Domain\Exception` | PF-049 — Exception hierarchy |
| `App\Foundation\Domain\Time` | PF-047 — Clock |
| `App\Foundation\Domain\Model` | PF-040 — AggregateRoot, PF-041 — Entity, PF-042 — ValueObject |
| `App\Foundation\Domain\Identity` | PF-044 — BusinessIdentifier, PF-048 — UUIDv7 |
| `App\Foundation\Domain\Event` | PF-043 — DomainEvent |
| `App\Foundation\Domain\Money` | PF-045 — Money |
| `App\Foundation\Domain\Result` | PF-046 — Result |

**Only `App\Foundation\Domain\Exception` and `App\Foundation\Domain\Time` are implemented.** Every other namespace in this table is an approved *reservation* for a story that has not been implemented yet — listing it here is not a claim that any type inside it exists.

**Each story creates only its own files.** Never pre-create another story's type, stub, placeholder, or empty directory. A namespace comes into existence when its owning story implements it.

## Approved implementation order

The `PF-040`–`PF-049` numbers are a **story catalogue, not an execution order**. Nothing has been renamed, renumbered, merged, split, or deleted. The human-approved serial order is:

**PF-049 → PF-047 → PF-042 → PF-048 → PF-044 → PF-041 → PF-043 → PF-040 → PF-045 → PF-046**

The order follows dependency direction: the exception taxonomy comes first because every later primitive throws through it; `Clock` is standalone; `ValueObject` precedes the identifier work that builds on it; identifiers precede `Entity`, which precedes `DomainEvent` and `AggregateRoot`; `Money` builds on `ValueObject`; and `Result` comes last, because it must be designed against primitives that already exist rather than shaping them.

## Conventions

- **`declare(strict_types=1);` is mandatory** in every Foundation source file and in every Foundation test fixture.
- **Domain code is framework-independent.** Nothing under `App\Foundation\Domain` may depend on Laravel, Illuminate, Eloquent, HTTP, queues, the service container, facades, configuration helpers, or vendor SDKs. This is enforced by `tests/Unit/Foundation/FoundationLayerHasNoFrameworkDependenciesTest.php`.
- **PHP global types are referenced with a leading backslash** — `\Throwable`, `\RuntimeException`, `\DateTimeImmutable` — inside Foundation source, so a global reference is never mistaken for a namespaced one.
- **Value objects and domain events are immutable by default.** Mutation produces a new instance; it never edits an existing one.
- **Foundation primitives throw the PF-049 exceptions for domain-rule violations.** They do **not** return `Result` for that purpose. `Result` (PF-046) models expected, recoverable outcomes a caller is meant to branch on — not a broken invariant.
- **Breaking changes to a published Foundation type require explicit human approval**, per the approval gates in [`AGENTS.md`](../../AGENTS.md) and [`CONTRIBUTING.md`](../../CONTRIBUTING.md).

## Time

`App\Foundation\Domain\Time` holds the platform's single time abstraction (PF-047):

- **`Clock` is the injectable wall-clock contract.** It declares exactly one method, `now(): \DateTimeImmutable`. Domain code depends on `Clock`; it never reads ambient system time. A Foundation primitive or module that needs the current instant receives a `Clock`, and a test substitutes its own implementation rather than manipulating global state.
- **`SystemClock` is the native UTC implementation.** Final, stateless, PHP standard library only — it passes UTC to the constructor explicitly, so its result never depends on PHP's ambient default timezone or on framework configuration.
- **UTC is part of the contract, not an implementation detail.** Every `Clock` implementation returns a value carrying an explicit UTC timezone. This keeps an instant unambiguous across a daylight-saving transition and matches the UTC transport rule in [`docs/architecture/07_API_Standards.md`](../../docs/architecture/07_API_Standards.md).
- **Read the clock once per logical operation** and reuse that instant wherever consistency matters, so the records a single operation writes — events, audit entries, outbox rows — carry one timestamp rather than several near-but-unequal ones.
- **`Clock` returns wall-clock time**, the host's civil time, which is subject to correction and may move backwards between readings. It is **not** a monotonic source and must never be used to measure elapsed duration.
- **`Clock` is not** a scheduler, timer, timeout or deadline system, recurrence rule, business calendar, timezone-conversion service, evidence or non-repudiation source, or distributed ordering guarantee. Its output is a reading of the current time — never trusted, authenticated, or tamper-evident input.
- **Tenant and Firm timezone behavior belongs to the owning business module**, never here. A Firm's office timezone, a court-deadline timezone, and every other business-timezone meaning are domain concerns; `Clock` carries no Firm, actor, or session semantics and must never gain a per-Firm or per-timezone method.
- **Breaking changes to the published `Clock` contract require explicit human approval**, as for every published Foundation type.
- **No `FrozenClock` or other test double belongs in `app/Foundation`.** A fixed-time fixture, when a consumer story first needs one, lives under `tests/`.

## External dependencies

- **A domain-safe external library requires explicit human approval and must be declared as a direct dependency** in `composer.json` before any Foundation code uses it.
- **A transitive dependency authorizes nothing.** A package that happens to be installed because something else requires it is not an approved dependency, and Foundation must never reach for it.
- PF-049 and PF-047 each introduced no dependency of any kind. PF-047 deliberately did **not** adopt PSR-20 (`Psr\Clock\ClockInterface`) or Carbon: both are present only transitively, and PSR-20 additionally makes no UTC guarantee. Adopting either later would require its own approved story and a direct `composer.json` declaration.

## What never belongs in Foundation Domain

No tenancy, `FirmContext`, `FirmId`, actor, principal, or session semantics. No authorization, Ethical Wall, or access-control decisions. No audit, logging, telemetry, metrics, or reporting. No AI. No persistence, ORM, repositories, aggregate versioning, or reconstitution. No serialization API. No event dispatch. No HTTP status codes, error-code catalogue, translations, or user-facing message rendering. No exception handler, renderer, service provider, or bootstrap registration.

**Exception messages are developer-facing diagnostics.** They must never be exposed verbatim to an external caller, and must never contain tenant identifiers, actor identities, credentials, session data, client or matter content, or privileged narrative.

## Tests

- **Pure Foundation tests live under `tests/Unit/Foundation/`**, mirroring the source namespaces beneath it.
- They **extend `PHPUnit\Framework\TestCase` directly** and **must not boot Laravel** — no `Tests\TestCase`, no application container, no database.
- Foundation test fixtures follow the same `declare(strict_types=1);` rule as Foundation source.

## Tooling

- **PHPStan remains at level 5**, with **no baseline, suppression, or ignored error**. See [`phpstan.neon.dist`](../../phpstan.neon.dist).
- **Pint remains the stock Laravel preset**, with no custom rules or exclusions. See [`pint.json`](../../pint.json).
- Neither may be weakened to accommodate a Foundation change.
