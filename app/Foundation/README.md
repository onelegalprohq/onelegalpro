# Foundation Library

**Sprint 0.3 — Foundation Library (PF-040 through PF-049).**

This document is the standing convention record for `app/Foundation`. It was created by **PF-049 — Foundation Exception Hierarchy**, the first Foundation story, and extended by **PF-047 — Clock**, the second, **PF-042 — ValueObject**, the third, **PF-048 — UUIDv7**, the fourth, and **PF-044 — BusinessIdentifier**, the fifth. It states what Foundation is, what it may never contain, and the rules every later Foundation story must obey. It is not a status page — `docs/PROJECT_STATUS.md` and `docs/implementation/03_Engineering_Backlog.md` remain authoritative for story status.

## What Foundation is

`app/Foundation` contains **reusable technical domain primitives** — the small, shared building blocks every module needs in order to express a domain at all. It is **never a place for business capabilities**, and it is **never a utility dumping ground**: a type belongs here only because it is a genuine, reusable domain primitive, not because there was nowhere else convenient to put it.

**Business capabilities belong under `app/Modules`** and require their own separately approved stories. Nothing in this document authorizes a business module. See [`docs/domain/06_Laravel_Module_Blueprint.md`](../../docs/domain/06_Laravel_Module_Blueprint.md) and [`AGENTS.md`](../../AGENTS.md).

**Foundation depends on no business module.** The dependency direction is one-way: modules consume Foundation. A module must never duplicate, fork, or shadow a Foundation primitive with its own incompatible version — a second money type, a second identifier type, a second domain-exception root, and so on are all prohibited.

## Approved domain root and concern namespaces

`App\Foundation\Domain` is the approved Foundation domain root.

The approved concern namespaces, and the PF story that owns each, are:

| Namespace | Owning story | Status |
| --- | --- | --- |
| `App\Foundation\Domain\Exception` | PF-049 — Exception hierarchy | Implemented |
| `App\Foundation\Domain\Time` | PF-047 — Clock | Implemented |
| `App\Foundation\Domain\Model` | PF-040 — AggregateRoot, PF-041 — Entity, PF-042 — ValueObject | **Partially implemented** — `ValueObject` (PF-042) only |
| `App\Foundation\Domain\Identity` | PF-044 — BusinessIdentifier, PF-048 — UUIDv7 | Implemented |
| `App\Foundation\Domain\Event` | PF-043 — DomainEvent | Reserved |
| `App\Foundation\Domain\Money` | PF-045 — Money | Reserved |
| `App\Foundation\Domain\Result` | PF-046 — Result | Reserved |

**`App\Foundation\Domain\Exception`, `App\Foundation\Domain\Time`, and `App\Foundation\Domain\Identity` are implemented — the last containing `UuidV7`, `UuidV7Generator`, and `SystemUuidV7Generator` (PF-048) plus `BusinessIdentifier` (PF-044), and no concrete identifier of any kind. `App\Foundation\Domain\Model` is only partially implemented: it contains `ValueObject` (PF-042) and nothing else — `Entity` (PF-041) and `AggregateRoot` (PF-040) remain reservations and do not exist.** Every namespace marked *Reserved* is an approved reservation for a story that has not been implemented yet — listing it here is not a claim that any type inside it exists.

**Each story creates only its own files.** Never pre-create another story's type, stub, placeholder, or empty directory. A namespace comes into existence when its owning story implements it.

## Approved implementation order

The `PF-040`–`PF-049` numbers are a **story catalogue, not an execution order**. Nothing has been renamed, renumbered, merged, split, or deleted. The human-approved serial order is:

**PF-049 → PF-047 → PF-042 → PF-048 → PF-044 → PF-041 → PF-043 → PF-040 → PF-045 → PF-046**

`PF-049`, `PF-047`, `PF-042`, `PF-048`, and `PF-044` are implemented; `PF-041` is next.

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

## Model

`App\Foundation\Domain\Model` holds the platform's domain-model contracts. **Only `ValueObject` (PF-042) exists.** `Entity` (PF-041) and `AggregateRoot` (PF-040) are reservations in the same namespace and have not been implemented.

### Value objects

- **`ValueObject` is the contract every value object satisfies.** It declares exactly one method, `equals(ValueObject $other): bool`, and extends nothing. A value object has **value equality, not entity identity**: two instances carrying the same value are interchangeable. That distinction — against the identity-based equality PF-041 will define — is the whole of the contract.
- **It is an interface, deliberately, and this is a permanent decision.** PHP's `readonly` inheritance is viral and bidirectionally exclusive: a non-readonly class cannot extend a readonly class, and a readonly class cannot extend a non-readonly one **even when the parent declares no property**. An abstract base would therefore have forced *every* value object on the platform to be readonly, or forbidden *every* one of them from being readonly, permanently; an `abstract readonly class` would additionally have forbidden static properties everywhere, foreclosing an interned-instance `Currency` for PF-045. An interface imposes neither constraint and leaves each value object's single `extends` slot free — which PF-044 `BusinessIdentifier` needs for its own abstract identifier base.
- **Implementations are immutable, and mutation returns a new value.** Declare an implementation `final readonly class` wherever the concrete type's own requirements permit; it is recommended, never imposed. `readonly` is shallow, so a value object holds immutable values only.
- **Every approved creation or reconstitution path enforces the concrete type's invariants, and a value object is never observable in an invalid state.** Whether creation uses a public constructor, a named constructor, a factory, or another explicit mechanism is the concrete story's decision — the contract requires none and forbids none.
- **Equality uses exact runtime type.** Differently typed value objects are unequal, and a cross-class comparison returns `false` rather than throwing. `$other instanceof self` achieves that in a **`final` leaf implementation**, because such a class can have no subclasses — **it is not universally sufficient**. An inheritable base that implements `equals()` on behalf of its subclasses must compare runtime classes explicitly, for example `$other::class === $this::class`, before comparing value state.
- **Equality is total, reflexive, symmetric, transitive, strict, and non-coercive.** The string `'1'` never equals the integer `1`. **Canonicalization belongs to the concrete value object's own story** and happens on the creation path, never during comparison — a comparison-time fix-up is what breaks transitivity. Caches and derived properties never participate.
- **`equals()` is not constant-time and carries no timing guarantee.** A type holding secret or privileged material needs its own explicitly approved comparison discipline — `hash_equals()` or equivalent — and preferably should not retain retrievable secret material at all.
- **`ValueObject` is not** a serialization, persistence, transport, stringification, hashing, ordering, or localization API. It declares no `__toString`, `\Stringable`, `jsonSerialize`, `toArray`, `toPrimitives`, `fromPrimitives`, `hash`, `copy`, `with*`, `notEquals`, or validation helper. **No value object is stringable, serializable, loggable, or safe for external transport merely because it satisfies this contract** — each of those is the owning type's own decision.
- **No shared equality implementation exists**, by design: no abstract `ValueObject` class, no equality trait, no `equalityComponents()`. **PF-042 supplies no production comparison logic at all.** One may be proposed by a later story once real consumers demonstrate an identical reusable requirement — adding one then is additive, whereas removing one would be breaking.
- **Implementations report failure through the PF-049 taxonomy** — `InvalidArgument` for an unacceptable supplied argument, `InvariantViolation` for a broken domain guarantee. The interface itself throws nothing.
- **Breaking changes to the published `ValueObject` contract require explicit human approval**, as for every published Foundation type.
- **No value object belongs in `app/Foundation` without its own approved story.** PF-042 created the contract and no implementation of it; the reference implementations used to prove the contract's shape live in its test file, not here.

## Identity

`App\Foundation\Domain\Identity` holds the platform's identifier primitives: the PF-048 UUIDv7 types and the PF-044 `BusinessIdentifier` base. **No concrete identifier exists here, and none ever will** — a concrete business identifier belongs to the module that owns it, under that module's own approved story.

### UUIDv7

- **`UuidV7` is the validated identifier value object.** A `final readonly class` implementing `ValueObject` (PF-042) and nothing else. It declares exactly three public methods — `fromString()`, `toString()`, `equals()` — with a **private constructor**, so `fromString()` is the only path from text and an instance is never observable in an invalid state.
- **`UuidV7Generator` is the injectable creation contract**, declaring exactly one method, `generate(): UuidV7`. **`SystemUuidV7Generator` is the native implementation**, receiving a `Clock` by constructor injection. Domain code depends on the contract; it never calls a static factory and never reads ambient system time.
- **Generation is deliberately not a static method on `UuidV7`.** A static factory would read ambient time and ambient randomness from inside a value object — untestable, unsubstitutable, and contrary to the Clock rule above.
- **Native PHP standard library only** — `random_bytes()`, `pack()`, `bin2hex()`. `ramsey/uuid` and `symfony/uid` both support UUIDv7 and are both installed, but **only transitively via the framework, and a transitive dependency authorizes nothing**. `Illuminate\Support\Str::uuid7()` is prohibited outright: it is a framework dependency, and it honours the global mutable `Str::$uuidFactory` hook, which would let unrelated framework test state change a Foundation primitive's output.
- **Validation is strict and total.** One canonical pattern enforces exact length, canonical hyphen positions, hexadecimal-only content, the version nibble `7`, and an RFC variant nibble (`8`, `9`, `a`, `b`). Surrounding whitespace, brace-wrapped and `urn:uuid:` forms, the unhyphenated form, the nil and max UUIDs, every other version, and every reserved variant are all rejected.
- **Hexadecimal is accepted in either case and normalised to lowercase on the creation path**, as RFC 9562 §4 requires — never during comparison, because a comparison-time fix-up is what breaks transitivity.
- **A UUIDv7 is time-sortable, and that is the only term to use for it.** Values whose encoded millisecond timestamps differ sort by those timestamps, both lexically and bytewise. **Values created within the same millisecond have no defined relative order. No monotonicity is promised in any scope. Clock rollback is permitted and unguarded** — the generating clock is a wall clock that may be corrected backwards, so a value created later may sort before one created earlier, and nothing detects, corrects, or reports that. **No global, cross-process, or cross-host ordering is promised**; there is no coordination, no shared state, and no node identifier.
- **The generator is stateless between calls, deliberately** — no counter, no sequence, no last-seen timestamp, no static property. Two instances sharing one clock are fully independent. Same-millisecond monotonic increment would be a **new implementation behind the same contract** under its own approved story, never a change to this one.
- **The timestamp field is a 48-bit unsigned count of Unix milliseconds** (RFC 9562 §5.7), so the representable range is `0` through `281474976710655` inclusive. `SystemUuidV7Generator` **verifies the clock's instant against that range using integer arithmetic only** and throws `InvariantViolation` when it falls outside. It never truncates, wraps, clamps, or reinterprets an unencodable instant — each of those would yield a syntactically valid UUID carrying a silently wrong timestamp. **The bounds are checked before the multiplication, never after**, and no floating-point timestamp arithmetic is used anywhere, on either path: `\DateTimeImmutable` can represent instants whose Unix seconds exceed `intdiv(PHP_INT_MAX, 1000)`, and for those a `seconds * 1000` product would overflow the integer domain and silently become a float. Whole seconds and the millisecond remainder are therefore read as separate integers and compared before being combined — seconds against `intdiv(281474976710655, 1000)`, and, only on that final representable second, the remainder against `281474976710655 % 1000`, with negative seconds rejected outright since `unix_ts_ms` is unsigned.
- **Uniqueness is probabilistic, not proven.** Seventy-four bits of cryptographically secure randomness per millisecond make a collision negligibly unlikely, not impossible. **No exactly-once generation and no collision-impossibility claim is made.**
- **Exceptions follow the PF-049 taxonomy, and no new exception type was added.** `InvalidArgument` for caller-supplied text that is not a canonical version 7 UUID; `InvariantViolation` for an unencodable clock instant or for generated data that somehow fails validation, retaining the original as the previous exception. **`\Random\RandomException` propagates untranslated**: a CSPRNG failure is a catastrophic environment failure, not a domain condition, and must not be swallowed by a handler catching `FoundationException`.
- **No exception message ever contains the rejected input or the offending instant.** Input arrives from outside the domain and may be attacker-controlled; the fixed range bounds are literals and leak nothing.
- **A UUID is an identifier, never a secret.** Its 48-bit timestamp prefix is fully predictable by construction, and every value discloses its creation time to millisecond precision to anyone holding it. **Possessing one is never an authorization decision**, and it never substitutes for Firm isolation, an Ethical Wall check, or any access control — `CheckEthicalWallAccess` remains Practice Management's alone. It is never a session token, API credential, magic link, recovery code, webhook secret, capability, or proof of identity. It encodes no Firm, actor, client, matter, or privileged content, and UUIDv7 has **no node field**, so no host identity leaks.
- **No `__toString()` and no `\Stringable`.** Implicit stringification is how an identifier reaches a log line, an exception message, a URL, or a concatenated query with nobody having decided to put it there. Rendering is an explicit act through `toString()`.
- **`UuidV7` is not** a serialization, persistence, transport, hashing, ordering, or timestamp-extraction API. Timestamp extraction, comparison and ordering methods, byte-level accessors, nil/max factories, and serialization interfaces are all **deferred** — each is additive later, whereas removing one would be breaking. External representation remains `Integrations`' concern, and identifiers are opaque externally per [`docs/architecture/07_API_Standards.md`](../../docs/architecture/07_API_Standards.md) §3 and §5.
- **No container binding or service provider was registered.** A future Laravel binding of `UuidV7Generator` to `SystemUuidV7Generator` belongs to an approved Platform Runtime story (Sprint 0.4), exactly as for `Clock`.
- **No test double belongs in `app/Foundation`.** PF-048's fixed-time and scripted `Clock` fixtures live inside its own test files under `tests/`, per the Time rule above.
- **Breaking changes to the published `UuidV7`, `UuidV7Generator`, and `SystemUuidV7Generator` contracts require explicit human approval**, as for every published Foundation type.

### Business identifiers

- **`BusinessIdentifier` is the base every business identifier extends (PF-044).** An `abstract readonly class` implementing `ValueObject` (PF-042) and nothing else, storing exactly one private `UuidV7`. It declares five public members — `fromString()`, `generate()`, `toString()`, `__toString()`, `equals()` — plus two protected ones, `fromUuid()` and `equalityComponents()`. Every one of them is `final`, and so is the **private** constructor.
- **A business identifier is a UUIDv7 with a type.** Two different concrete identifier types wrapping the identical UUID are never equal, because `equals()` compares the exact runtime class — `$other::class === $this::class` — before it compares any value. `instanceof self` would be wrong on an inheritable base, exactly as the `ValueObject` contract warns.
- **It prevents accidental interchange — nothing more.** Deliberate reconstruction across types, by passing one identifier's canonical text to another type's `fromString()`, remains possible and is not prevented. Any type with a reconstitution path has that property necessarily. **`BusinessIdentifier` is not an authorization boundary, not a security boundary, and not an ownership or referential-integrity control.** Authorization, aggregate ownership, and referential integrity remain the responsibility of the owning module's own approved stories and the platform's separate controls; `CheckEthicalWallAccess` remains Practice Management's alone, and `FirmContext` still derives only from verified identity and membership.
- **Validation is inherited whole from `UuidV7` and is never extended.** `fromString()` accepts **any textual UUIDv7 representation `UuidV7::fromString()` accepts, including uppercase and mixed-case hexadecimal**, and stores the canonical lowercase value that method returns; **stored and emitted output is always canonical lowercase text**. `BusinessIdentifier` adds no nil rejection, no timestamp policy, no tenant policy, no parsing policy, and no domain-specific invariant of its own. It constructs no exception message, so the rejected input cannot reach one.
- **Creation is `generate(UuidV7Generator $generator)`, and the generator is a parameter, never a held collaborator.** Nothing here reads ambient time or ambient randomness, and no static factory, clock, or entropy source exists anywhere on the type. `generate()` calls the supplied generator exactly once. `\Random\RandomException` propagates untranslated, as it does from `UuidV7Generator` itself.
- **`fromUuid()` is the protected construction seam**, so a subclass can build itself from an already-valid `UuidV7` without construction from an arbitrary object ever becoming public.
- **Concrete identifiers are empty `final readonly` marker subclasses, and that is an architectural rule the language only partly enforces.** PHP does enforce the private base constructor, the protected construction seam, the immutability of the stored `UuidV7` (`readonly` is viral, so no subclass may be mutable, and a `readonly` class may declare no static property), and the finality of every base member. **PHP does not make a subclass factory alias or an additional subclass property technically impossible.** A future production leaf must therefore, by rule and by review, **add no state, no invariant, no constructor parameter, no factory alias, and no behaviour**. Nothing in this document authorizes such a leaf: each one needs its own approved module story.
- **`toString()` is the explicit rendering, and `__toString()` renders the same canonical text in string context.** This is a deliberate, approved departure from the `UuidV7` rule above, and it changes nothing about `UuidV7`, which still declares no `__toString()` and is still not `\Stringable`: a raw UUID must not reach a log line or a URL without someone deciding to put it there, whereas a business identifier is a named domain type whose rendering carries its meaning with it. `toString()` remains preferred wherever the call site can name it. `\Stringable` appears on the type only because PHP adds it automatically to any class declaring `__toString()`; it is not separately declared.
- **`toString()` and `__toString()` expose canonical text and authorize no mapping.** Neither they nor `fromString()` define or authorize a database column type or mapping, an Eloquent cast, an API DTO, an event payload field, route-model binding, an index strategy, or any serialization format. **Those remain the responsibility of future repositories, adapters, and owning module stories**, and external representation remains `Integrations`' concern. Accepting text is a reconstitution path, not ownership of a persistence or transport contract.
- **No `toUuid()` or other UUID-object accessor.** The composed `UuidV7` is an encapsulated implementation detail, and the public API is deliberately minimal. One may be added later only if an approved consumer demonstrates a real requirement — additive then, whereas removal would be breaking. **Its absence is not a security control and must never be described as one.**
- **`equals()` is `final` and delegates its state to `equalityComponents()`**, which returns the canonical text and nothing else — no cache, no derived value, no object identity. Comparison never canonicalises; the stored value was canonicalised on the creation path, which is what keeps equality transitive. **Not constant-time, and no timing guarantee is made** — an identifier is not secret material.
- **A business identifier is not a secret.** Possession proves neither identity nor authorization. Every value discloses its approximate creation time to millisecond precision, and encodes no Firm, actor, client, matter, or privileged content. It is never a session token, API credential, magic link, recovery code, webhook secret, capability, or proof of identity, and identifiers stay opaque externally per [`docs/architecture/07_API_Standards.md`](../../docs/architecture/07_API_Standards.md) §3 and §5.
- **`BusinessIdentifier` is not** a serialization, persistence, transport, hashing, ordering, or timestamp-extraction API. It declares no `jsonSerialize`, `toArray`, `toPrimitives`, `fromPrimitives`, `hash`, `compareTo`, or `with*`, and implements no `\JsonSerializable`.
- **No test double belongs in `app/Foundation`.** PF-044's marker, construction-seam, foreign-value, and scripted-generator fixtures live inside its own test file under `tests/`, per the same rule PF-047 and PF-048 followed.
- **Breaking changes to the published `BusinessIdentifier` contract require explicit human approval**, as for every published Foundation type.

## External dependencies

- **A domain-safe external library requires explicit human approval and must be declared as a direct dependency** in `composer.json` before any Foundation code uses it.
- **A transitive dependency authorizes nothing.** A package that happens to be installed because something else requires it is not an approved dependency, and Foundation must never reach for it.
- PF-049, PF-047, PF-042, PF-048, and PF-044 each introduced no dependency of any kind. PF-047 deliberately did **not** adopt PSR-20 (`Psr\Clock\ClockInterface`) or Carbon: both are present only transitively, and PSR-20 additionally makes no UTC guarantee. PF-048 deliberately did **not** adopt `ramsey/uuid` or `symfony/uid`: both support UUIDv7 and both are installed, but **only transitively via `laravel/framework`**. Adopting any of them later would require its own approved story and a direct `composer.json` declaration.

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
