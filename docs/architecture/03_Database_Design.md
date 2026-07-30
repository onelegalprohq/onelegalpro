# ARCH-012 — Data & Persistence Architecture

**Status:** **Proposed.** Not approved. This document and `docs/adr/ADR-016-Tenant-Isolation-Model.md` through `docs/adr/ADR-020-Migration-Rollback-and-Schema-Evolution.md` remain **Proposed until explicit owner approval is recorded**. Nothing here is scheduled, implemented, or authorized. **`PF-040` — AggregateRoot remains the next repository implementation story and remains Backlog.** No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled by this document.

**Document numbering.** The story ID is **ARCH-012**. Unlike ARCH-006 through ARCH-011, which each created the next sequential architecture document, this story populates the **pre-reserved placeholder `docs/architecture/03_Database_Design.md`**; no new document number is allocated. The file's name is retained unchanged; its subject is the platform's data and persistence architecture, of which database design is the largest part.

**No capability, control, or property described in this document is claimed to be implemented.** No production-readiness, certification, compliance, or legal claim is made anywhere in it.

---

## 1. Scope, authority, and relationship to existing architecture

### 1.1 What this document is

This is the **platform-wide data and persistence baseline**, binding every bounded context, on the same footing as `docs/architecture/04_Security_Architecture.md` (security) and `docs/architecture/07_API_Standards.md` (external contracts). It owns no domain data and defines no bounded context. It defines **how** approved domain models are persisted in PostgreSQL, and **which persistence patterns are required, permitted, and prohibited**.

**In scope:** the shared-schema tenancy model; mandatory `firm_id` scoping and PostgreSQL Row-Level Security as defence in depth; connection roles and transaction-scoped Firm context; module schema and migration ownership; identifier persistence and UUIDv7 rules; baseline columns, time representation, and concurrency control; referential integrity and cross-bounded-context reference rules; indexes, Firm-scoped uniqueness, text encoding and collation; destructive-cascade policy; transaction boundaries and locking; append-only audit persistence; transactional-outbox persistence; idempotency persistence; migration and schema-evolution policy; PostgreSQL testing obligations; the backup and restore properties that constrain persistence design; prohibited patterns; non-goals; and the security and professional-responsibility consequences of all of the above.

**Out of scope:** everything in §19, and specifically — **which** aggregates, attributes, states, or events exist (owned by each context's own approved architecture); hosting, deployment, infrastructure, connection pooling, backup, monitoring, and secret-management **products and providers**; capacity, sizing, and performance targets; the physical data model of any specific module; search, analytics, and vector storage; and any Reporting capability, which Constitution Article 44 reserves for a future bounded context this document neither approves nor prohibits.

**This document defines conceptual and normative persistence rules. It creates no migration, no table, no schema, no source file, no configuration, and no test.**

### 1.2 Authority and precedence

`docs/architecture/01_OneLegalPro_Constitution.md` prevails over this document in every case. Where this document appears to conflict with an approved per-context architecture, that context's architecture governs **what** it owns and **what invariants apply**; this document governs **how** persistence expresses them. Apparent conflicts are resolved explicitly in §21 rather than left to a reader's judgement.

### 1.3 The approved architecture this document rests on

| Source | What it establishes that this document implements |
|---|---|
| Constitution Article 5 | Platform-global legal reference data must **not** use `FirmContext` as its ownership boundary; Firm-owned data must be strictly isolated by it |
| Constitution Articles 8, 18, 20, 23 | Immutable published versions, immutable document versions, immutable approved knowledge versions, immutable posted financial history — correction is a new record, never a rewrite |
| Constitution Articles 27–28 | `FirmContext` is built only from verified identity and membership; every repository and query is Firm-scoped; authorization precedes retrieval |
| Constitution Article 30 | Append-only security audit; operational logs are never the sole authoritative security record; fail closed |
| Constitution Articles 34, 43 | At-least-once delivery, no exactly-once and no global-ordering claim; external calls never inside a database transaction |
| Constitution Articles 45–48 | `PlatformAdministration` owns exactly three concepts; no record, query, cache, projection, index, or event spans Firms |
| `docs/domain/06_Laravel_Module_Blueprint.md` | Tenant isolation enforced in application logic, repositories, **database policy**, and tests — **global scopes alone are insufficient**; state changes and outbox records commit atomically; unapproved destructive cascades are a prohibited pattern |
| `docs/architecture/04_Security_Architecture.md` §5, §7, §8 | Firm isolation, encryption, environment separation, data classification, backup integrity, fail-closed behaviour |
| `docs/architecture/07_API_Standards.md` §10, §12 | Idempotency scoping and re-authorization on replay; at-least-once delivery |
| `AGENTS.md` | PostgreSQL and UUIDv7; never edit historical migrations; approval gates for database redesign, authorization changes, and destructive operations |
| `docs/architecture/02_Product_Requirements.md` §8, §9 | Firm isolation enforced in database policy; Thai text correctness; an **executed** restore test as production-access evidence; PostgreSQL CI before `PF-080` |
| `app/Foundation` (PF-042, PF-043, PF-044, PF-047, PF-048, PF-049 — Done) | `ValueObject`, `DomainEvent`, `BusinessIdentifier`, `Clock`, `UuidV7`, exception taxonomy — consumed, never duplicated |

### 1.4 What this document does not unblock

Approving this document would satisfy **one** of the production-access evidence items listed in `docs/architecture/02_Product_Requirements.md` §8 — "an approved database design". It would not satisfy any other. An approved deployment architecture, an **executed and recorded** restore test, operational monitoring, a documented incident procedure, completed Thai-qualified legal review, and every applicable `AGENTS.md` approval gate all remain outstanding and are not addressed here.

---

## 2. Shared-schema tenancy model

### 2.1 The decision

**One logical PostgreSQL database, one shared set of relations, with every Firm's rows co-resident and separated by a mandatory `firm_id` column.** There is no schema-per-Firm and no database-per-Firm. See `docs/adr/ADR-016-Tenant-Isolation-Model.md`.

### 2.2 Why

- **Platform-global data exists and cannot be partitioned by Firm.** Constitution Article 5 requires official legislation, regulations, official publications, reference translations, and licensed court decisions to exist **once, for every Firm**. A schema- or database-per-Firm model forces that data either to be duplicated per Firm — which Article 5 forbids in substance, since it would make a platform-global source a Firm-owned artefact — or to live in a shared location that every per-Firm connection must additionally reach, reintroducing the shared-schema problem alongside the per-Firm one.
- **Migration fan-out multiplies the highest-risk operation on the platform.** Expand/contract migration (§15) applied across N schemas produces N partial-failure states and N drift surfaces. A single schema has one migration state, verifiable in one place.
- **Connection and cache multiplication.** Per-Firm schemas or databases multiply connections, prepared-statement caches, and pooler slots by tenant count, and make a shared query planner cache impossible.
- **Restore is a whole-database operation regardless.** Physical recovery does not become per-Firm merely because schemas are separate; per-Firm recovery requires logical export/import in either model (§17).

### 2.3 The cost, stated plainly

**In a shared schema, a single missing `firm_id` predicate is a cross-Firm disclosure.** For a legal-practice platform, that is not a data-quality defect; it is a confidentiality and conflicts incident affecting parties who never consented to it, and it may be unremediable once it has occurred.

That cost is the entire justification for the four-layer isolation model in §3: **application logic, repositories, PostgreSQL policy, and tests, each independently sufficient to stop the same mistake.** A schema-per-Firm model would make the same mistake harder to make but would not remove it — a query executed in the wrong schema search path is the same failure — and would replace it with a migration and operations surface that is harder to verify.

**Revisiting this decision is a database redesign** under the `AGENTS.md` approval gate, not an implementation choice.

### 2.4 Two relation classes, explicitly classified

Every relation is **exactly one** of the following, declared explicitly in the migration that creates it and in the owning module's documentation. **There is no third, "sometimes-scoped", class.**

| Class | Definition | `firm_id` | Row-Level Security |
|---|---|---|---|
| **Firm-scoped** | Holds data belonging to exactly one Firm | `uuid NOT NULL`, immutable | **Enabled and forced** (§3) |
| **Platform-global** | Holds data that exists once for every Firm — Constitution Article 5 reference data, and static configuration such as seeded taxonomies and scheme templates | **Absent — never nullable, never a sentinel** | Not applicable; read-only to the application runtime role |

**Rules**

- A platform-global relation **never** carries a nullable `firm_id`, a zero/sentinel UUID, or a "shared Firm" row. A nullable tenancy column is the single most common way a shared-schema isolation model fails, because every policy and predicate then needs an `OR firm_id IS NULL` branch that is trivially wrong.
- A platform-global relation is **written only by an authorized platform data-management path** and is read-only to the application runtime role. Firm-owned annotations, bookmarks, notes, and saved research **over** platform-global data are Firm-scoped relations that reference the global row by identifier (Article 5).
- A join between a Firm-scoped relation and a platform-global relation is permitted; a join that would place another Firm's row on either side is not, and no query may use a platform-global relation as a bridge between two Firms.
- Reclassifying a relation from one class to the other is a **database redesign and a security change**, requiring both approval gates.

---

## 3. Firm-scoping and Row-Level Security policy

### 3.1 Four layers, each independently sufficient

`docs/domain/06_Laravel_Module_Blueprint.md` requires tenant isolation in application logic, repositories, database policy, and tests, and states that **global scopes alone are insufficient**. This document makes each layer explicit and assigns it a distinct failure mode:

| Layer | Obligation | The mistake it catches |
|---|---|---|
| **1. Application logic** | Every command and query carries an explicit `FirmContext` built only from verified identity and membership (Article 27) | A caller-supplied Firm identifier, a hostname, a header, or an email domain being treated as authority |
| **2. Repositories** | Every repository method scopes by Firm explicitly, as a parameter of the query, not as ambient state | A query written without a tenancy predicate |
| **3. PostgreSQL policy** | Row-Level Security, **enabled and forced**, on every Firm-scoped relation | A query that reached the database despite layers 1 and 2 failing — including one written by hand, by a future maintainer, or by an ad-hoc script running as the application role |
| **4. Tests** | Executed PostgreSQL tests that assert each of the above, running as the **application runtime role** | A relation added without a policy; a policy that permits what it appears to forbid |

**Row-Level Security is defence in depth. It is never the primary control, and its presence never licenses a weaker repository.** A repository that omits its Firm predicate "because RLS will catch it" is a defect, not an optimization: it removes a layer, makes the remaining one load-bearing, and produces a query whose correctness depends entirely on session state.

Equally: **application scoping never licenses omitting the policy.** Both statements hold at once. That is what defence in depth means here.

### 3.2 The `firm_id` column

- `firm_id uuid NOT NULL` on every Firm-scoped relation. No exceptions, no nullable variant, no sentinel.
- `firm_id` is **immutable after insert**. A row never changes Firm. Enforced by the row policy's `WITH CHECK` clause and, additionally, by a trigger that rejects any statement changing `firm_id` — because a policy protects against writing into *another* Firm's context, while immutability additionally protects against a same-context statement rewriting the column.
- `firm_id` references the `Firm` identity owned by `PlatformAdministration` (`docs/architecture/19_Platform_Administration_Architecture.md` §6) **by identifier**. Whether a database-level foreign key to the `Firm` relation exists is decided in §8.3.
- **A `firm_id` value is not a secret and not a capability.** Holding one grants nothing (ARCH-019 §16). It is nevertheless never placed in a URL parameter, query string, or any position routinely logged by intermediaries (ARCH-019 §18).

### 3.3 Row-Level Security requirements

For every Firm-scoped relation:

1. **`ROW LEVEL SECURITY` is enabled.**
2. **`FORCE ROW LEVEL SECURITY` is applied.** Without it, the relation's owner — which is the migration role — bypasses every policy, so a migration, a backfill, or any statement run under ownership silently sees and writes every Firm's rows. Forcing it is what makes the policy a real boundary rather than a boundary that applies only to the role least likely to make a mistake. Its consequences for data maintenance are resolved in §15.5, not evaded.
3. **A policy with both `USING` and `WITH CHECK`.** `USING` constrains what rows are visible and modifiable; `WITH CHECK` constrains what rows may be written. A policy with only `USING` permits inserting a row into another Firm.
4. **The predicate compares `firm_id` to the current transaction's Firm context and nothing else** (§4.3).
5. **The application runtime role is neither the relation's owner, a superuser, nor a `BYPASSRLS` role** (§4.1). A policy applied to a role that can bypass it is decoration.
6. **A default-deny posture for new relations.** Enabling Row-Level Security with no policy denies all access, which is the correct default; the testing obligation in §16 additionally asserts that no Firm-scoped relation exists without both RLS forced and a policy, so a new table cannot silently ship unprotected.

### 3.4 Unset Firm context fails closed

**When the Firm context is unset, empty, or unparseable, every access to a Firm-scoped relation fails.** It does not return all rows, and it does not return zero rows silently.

- The policy predicate resolves Firm context through a function that **raises** when the setting is absent or empty, rather than through a bare `current_setting(..., true)` that returns `NULL`. A `NULL` comparison yields no rows — which is safe for reads but hides a real defect, and would let a write attempt fail with a confusing policy violation rather than the true cause.
- A silent empty result is specifically rejected as a design: it converts a missing-context bug into a plausible-looking "no records found" screen, which in a legal practice is a professionally dangerous outcome — a lawyer told a Matter has no tasks behaves differently from a lawyer told the system failed.
- **Reads are not exempt.** A read outside a transaction with Firm context set has no context to fail on, so §4.4 requires that every statement touching a Firm-scoped relation run inside a transaction with the context established.
- Fail-closed here is the same discipline Constitution Article 30 and `docs/architecture/04_Security_Architecture.md` §8 establish for authorization: **availability never outranks Firm isolation.**

### 3.5 What Row-Level Security must never be used for

- **Never to express authorization.** Authorization is composed in the application under Constitution Article 28 — principal, membership, session, capability, domain semantics, Ethical Wall result, narrower domain restrictions, step-up, and explicit denies. A row policy carries **Firm identity only**. Encoding a capability, role, or ownership rule in a policy would place part of the authorization composition in a layer that cannot see the request, cannot record a decision, and cannot be tested as authorization.
- **Never to express entitlement.** `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` Decision 8 forbids entitlement from being a per-request resource-authorization input, precisely so that an already-issued valid session runs to its normal expiry after a lapse. A policy predicate referencing entitlement status would make entitlement a per-request input on every single statement — silently reversing an approved decision. **Entitlement never appears in a row policy.** (§21.7.)
- **Never to simulate an Ethical Wall.** `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` requires that an absent control never be stubbed, approximated, simulated, partially implemented, or renamed. A row policy that restricted Matter visibility per user would be exactly that — an approximated wall, implemented in the one layer where nobody would look for it. **No wall-shaped column, flag, or policy exists.** When walls are built, Practice Management remains their sole authority (Constitution Article 17).
- **Never to hide a soft-deleted row as an access control** (§7.6).

---

## 4. Connection roles and `SET LOCAL` rules

### 4.1 Roles

Logical roles and privilege sets, **not products, providers, or account names**. Every one of them is subject to Row-Level Security; none holds `BYPASSRLS` and none is a superuser.

| Role | Privileges | Constraints |
|---|---|---|
| **Migration role** | DDL on the relations it owns; DML for backfills | Owns the relations; **subject to forced RLS like everyone else** (§3.3.2, §15.5). Never used to serve a request. Its use is authorized and recorded. |
| **Application runtime role** | `SELECT`, `INSERT`, `UPDATE`, `DELETE` as each relation's rules permit | **Not the owner, not a superuser, no `BYPASSRLS`, no DDL.** Holds no `UPDATE` or `DELETE` on append-only relations (§12.2). Read-only on platform-global relations (§2.4). |
| **Outbox publication role** | `SELECT` and bookkeeping `UPDATE` on the outbox relation only | **No access to any business relation.** Scope and cross-Firm behaviour resolved in §13.6 and §21.3. |
| **Reporting / analytics role** | **Does not exist.** | No Reporting bounded context is approved (Constitution Article 44). Creating a cross-Firm read role would be that context arriving through a privilege grant. |

**A single database account used for migrations and for serving requests is prohibited.** It makes forced RLS ineffective for the request path, gives request-time code the ability to alter schema, and removes the audit distinction between a data change and a schema change.

### 4.2 Establishing Firm context

- Firm context is established **once, at the start of a transaction**, from the `FirmContext` primitive (`PF-080`), which is itself built only from verified identity and membership (Constitution Article 27).
- **A client-supplied value never establishes Firm context** — not a header, parameter, route segment, body field, cookie, hostname, custom domain, or email domain.
- **Firm context is never changed mid-transaction, and one transaction never spans two Firms** (§11.2).
- A background job, queued job, migration backfill, or maintenance task establishes context explicitly and per-Firm; inheriting whatever context a pooled connection last held is prohibited.

### 4.3 `SET LOCAL` only — never a session-level `SET`

**Firm context is set with a transaction-scoped `SET LOCAL` (equivalently, `set_config(..., true)`) inside an explicit transaction. A session-level `SET` or `set_config(..., false)` is prohibited, without exception.**

Why this is a hard rule rather than a preference:

- **Connections are pooled and reused across requests, Firms, and actors.** Session-level state survives the request that set it. A connection returned to the pool carrying Firm A's context and handed to a request acting for Firm B produces a cross-Firm read that every application-layer check passes and no test that uses a fresh connection will ever reproduce.
- **The failure is silent, intermittent, and load-dependent.** It appears under concurrency, in production, and not in development.
- **`SET LOCAL` reverts at transaction end** — commit or rollback — which makes the safe behaviour the automatic one rather than something a `RESET` in a `finally` block has to remember.
- The same reasoning applies to **advisory locks**: transaction-scoped advisory locks only (§11.5).

**Additional rules**

- `SET LOCAL` outside a transaction block silently does nothing useful. Every use is inside an explicit transaction.
- The setting name is a single, platform-wide constant owned by Platform Foundation; a module never invents its own.
- The value is written as text and compared after an explicit cast; a malformed value raises rather than comparing as `NULL` (§3.4).
- **Connection state is verified, not assumed.** A checked-out connection's role and the absence of leftover session state are treated as things to establish, not things to trust — a pooler, a failover, or a connection reset can change either.
- **Any connection pooler that multiplexes below the transaction boundary is incompatible with this design.** Transaction-level and session-level multiplexing preserve `SET LOCAL` within a transaction; statement-level multiplexing does not. **This is a recorded constraint on the future deployment architecture, not a product selection** — no pooler, provider, or configuration is chosen here (§19).

### 4.4 Every statement runs in a transaction with context set

Every statement touching a Firm-scoped relation — **including reads** — runs inside a transaction in which Firm context has been established. Autocommit reads have no transaction to carry `SET LOCAL`, so they would run with no context and, under §3.4, fail. That is the intended outcome, and it is why the tenancy middleware and transaction manager (`PF-082`, `PF-073`) establish context for read paths as well as write paths.

---

## 5. Module schema and migration ownership

### 5.1 Ownership

- **Each module owns its own relations, and only its own.** Its migrations live in the module's `Database/` directory (`docs/domain/06_Laravel_Module_Blueprint.md`).
- **No module migrates, alters, reads, or writes another module's relations.** Cross-module access is published commands, queries, and events only. This is the existing rule in `AGENTS.md` and the Blueprint, restated here because a migration is the easiest way to break it accidentally.
- **Platform Foundation owns platform infrastructure relations** — the transactional outbox (§13), idempotency records (§14), and framework-operational tables. Foundation is `app/Foundation`: a layer of shared technical primitives, **not a bounded context**. A module writing an outbox row through Foundation's published contract, inside its own transaction, is **not** a cross-module table write (§21.2).
- **The existing Laravel skeleton migrations** (`users`, `cache`, `jobs`) are framework starter artefacts, **not approved domain schema**. IdentityAccess owns principals, credentials, and sessions (Constitution Article 26); a stock `users` table is not the principal model. Whether those tables are retained, replaced, or removed is IdentityAccess's own approved story's decision and is **not decided here**.

### 5.2 One PostgreSQL schema, ownership by convention and review

The shared-schema decision (§2) means module ownership is **not** expressed as a PostgreSQL schema wall. It is expressed by:

- **Migration ownership** — only the owning module's migrations touch its relations.
- **A module-specific relation-name prefix**, so ownership is legible in a query, a slow-query log, and a `psql` session. The prefix convention is a naming rule, not a security boundary.
- **Review** — the `AGENTS.md` gates for database redesign and authorization changes.

**Stated honestly: a naming convention does not prevent a cross-module write the way a revoked privilege would.** Per-module PostgreSQL schemas with per-module grants would. That option is deliberately not taken now — it multiplies search-path handling, complicates the shared-relation cases in §13 and §14, and adds a second axis of migration ordering — and adopting it later is **additive and separately approvable**, not foreclosed.

### 5.3 Migration ordering across modules

Because cross-bounded-context foreign keys are prohibited (§8.2), most cross-module ordering coupling does not exist. What remains:

- A module's own migrations are ordered within the module.
- A dependency on a Platform Foundation relation (outbox, idempotency) is a real ordering dependency and is recorded explicitly by the depending story.
- **A migration never depends on another module's relation existing**, because it never references one.
- **Historical migrations are never edited** (`AGENTS.md`). A mistake in a shipped migration is corrected by a new migration.

---

## 6. Identifier and UUIDv7 persistence

### 6.1 Column type and generation

- **Native PostgreSQL `uuid` columns.** Not `char(36)`, not `varchar`, not `text`, not `bytea`. Sixteen bytes rather than thirty-six, type-checked by the database, and materially smaller and faster in every index that contains it.
- **Application-generated UUIDv7, produced by the Foundation primitive `PF-048`** (`UuidV7`, `UuidV7Generator`).
- **No database-generated identifier default of any kind** — no `gen_random_uuid()`, no server-side UUIDv7 function, no `DEFAULT`, no trigger, no sequence, no `bigserial` surrogate for a domain relation.

Why generation is the application's:

- **A domain object has identity before it is persisted.** An aggregate is constructed, records events, and is referenced by those events and by its outbox row — all before any `INSERT`. A database default would mean the identity in the event and the identity in the row are assigned by different authorities at different times.
- **Retry safety.** A command retried after a failure that may or may not have committed must be able to write the same identifier. A database default makes every retry a new identity and every partial failure a duplicate.
- **Testability.** `PF-048`'s generator is injectable precisely so identity is deterministic under test. A database default is not.
- **One format, one validator.** `PF-048` already owns strict UUIDv7 validation and canonical lowercase representation; a second generation path would be a second format authority.

### 6.2 UUIDv7 is not an ordering guarantee

`PF-048` records that a UUIDv7 is **time-sortable** and that this is the only term used for it. This document states the persistence consequence directly:

**A UUIDv7 is never an ordering key.** Specifically:

- Identifiers generated within the same millisecond have **arbitrary relative order**. `PF-048` deliberately declined library monotonic-counter behaviour, which in any case holds only within a single process.
- The generating clock is a wall clock that may be corrected **backwards** (`PF-047`), so a later identifier may sort earlier.
- Multiple application processes generate concurrently with no coordination.
- Commit order is not generation order. A row with a lower identifier can become visible after a row with a higher one (§13.5).

Therefore: **no `ORDER BY id` for business or delivery ordering; no identifier used as a pagination cursor that implies time order; no ordering, deduplication, or idempotency key derived from an identifier's timestamp bits.** Where ordering matters, it is explicit and per-subject (§13.5).

What is retained is the **index-locality benefit**: a time-prefixed identifier gives B-tree inserts far better locality than a random v4, reducing page splits and index bloat. That is a physical property, not a semantic promise, and it is the only reason UUIDv7 rather than UUIDv4 is used.

### 6.3 Primary keys and Firm-scoped referential identity

- **The primary key of a Firm-scoped relation is `id uuid` alone.** Identifiers are globally unique by construction, so a composite primary key buys nothing for identity.
- **Every Firm-scoped relation additionally carries `UNIQUE (firm_id, id)`.**
- **Every intra-context foreign key is composite**, referencing `(firm_id, id)` and carrying the referencing row's own `firm_id` as the first column.

The reason for the composite foreign key is worth stating: it makes **"the row I reference belongs to my Firm"** a fact the database enforces, rather than a convention the application maintains. A single-column foreign key to `id` permits a row in Firm A to reference a row in Firm B — a cross-Firm link that is invisible to Row-Level Security, because each row individually satisfies its own policy. That is exactly the class of defect a legal-practice platform cannot absorb.

**The cost, stated:** one additional unique index per Firm-scoped relation, and wider foreign-key columns and indexes. That is accepted deliberately.

- **No natural or business key is ever a primary key.** A human-readable business identifier — `MatterNumber` being the canonical example — is a Firm-scoped unique **attribute** (§9.2), never identity. It is Firm-configurable, human-meaningful, and immutable only from a defined lifecycle point, all of which make it unfit as a key.
- **Monotonic sequence columns are permitted where they are ordering, not identity** — specifically the outbox sequence (§13.5). Such a column is never a primary key, never externally exposed, and never a domain identifier.

### 6.4 Identifier disclosure

**A UUIDv7 discloses its approximate generation time, to millisecond precision, to anyone holding it** — its timestamp prefix is fully predictable. This is recorded in `PF-048` and `PF-043` and repeated here because persistence is where identifiers become durable and widely copied.

- An internal identifier is **never** exposed externally merely because it is convenient (Constitution Article 31; `docs/architecture/07_API_Standards.md` §3 and §5 require external identifiers to be opaque and never a basis for enumeration or ordering).
- Generation time and business occurrence time are **different facts** and are never conflated: an identifier may be generated before, during, or after the instant it describes (§7.3).
- An identifier is not automatically safe to log, dump, or render; that is a decision the calling code makes.

---

## 7. Baseline columns, timestamps, and concurrency

### 7.1 Baseline columns

Every **Firm-scoped mutable** relation carries at least:

| Column | Type | Rule |
|---|---|---|
| `id` | `uuid` | Primary key; application-generated UUIDv7 (§6) |
| `firm_id` | `uuid NOT NULL` | Immutable; RLS predicate column (§3.2) |
| `created_at` | `timestamptz NOT NULL` | Supplied by the application from `PF-047` `Clock` |
| `updated_at` | `timestamptz NOT NULL` | Supplied by the application from `PF-047` `Clock` |

Every **append-only** relation (audit, ledger entries, event records) carries `id`, `firm_id`, and its own recorded-at column, and **has no `updated_at`**, because it is never updated (§12).

**Actor attribution** — the accountable actor identifier, and where applicable the distinct human, system, integration, and AI actors — is required by Constitution Articles 26 and 35 and by `docs/architecture/02_Product_Requirements.md` §9 on every recorded action. Which columns express it is each owning context's decision; that it is present and non-inferred is not.

### 7.2 Time representation

- **`timestamptz` everywhere. `timestamp without time zone` is prohibited.** A naive timestamp is a value whose meaning depends on the session that reads it, which is indefensible for court deadlines, retention periods, and audit.
- **Stored instants are UTC**, matching `PF-043`'s contract that `occurredAt()` is UTC.
- **Timestamps come from the injected `PF-047` `Clock`, not from `now()`, `CURRENT_TIMESTAMP`, or a column default.** Domain time must be injectable and testable; a database default is neither, and it silently substitutes the database server's clock for the application's single time source. The clock is read **once per logical operation** and reused across every row that operation writes, so an aggregate, its audit row, and its outbox row share one instant rather than three near-misses.
- A **database-side default is permitted only for an infrastructure-internal column with no domain meaning** — for example a physically-recorded-at column on an audit or outbox row — and even then it never replaces the domain instant, which is a separate column.
- **Five distinct times are never collapsed into one column** (`PF-043`): `occurred_at` (the business instant) → `recorded_at` (persisted) → `committed_at` (durable) → `published_at` (dispatched) → `delivered_at` (acknowledged externally). Storing one and inferring the rest destroys the ability to explain what happened.
- **A timestamp promises no ordering, monotonicity, authenticity, trusted time, or non-repudiation.** Two rows may share an instant; a later row may carry an earlier one. `occurred_at` is never a sort key, deduplication key, or idempotency key.
- Timezone conversion, Firm office timezones, and court-deadline timezones are the owning business module's concern, computed from the stored UTC instant.

### 7.3 Types and nullability

- **Nullable only where absence is a genuine domain state.** A nullable column that means "we did not get around to filling this in" is a defect. A nullable tenancy, authorization, or attribution column is prohibited outright.
- **Value sets are `text` with a `CHECK` constraint, or a lookup relation — not a PostgreSQL `enum` type.** `ALTER TYPE ... ADD VALUE` cannot run in a transaction with other changes in every supported configuration, removing a value is impractical, and reordering is impossible — all of which fight expand/contract (§15). The authoritative value set lives in the domain; the constraint is a guard, not the definition.
- **`jsonb`, not `json`**, and only for **non-authoritative, non-authorization-bearing** content: an event payload of safe metadata, a provenance record, a structured note. **Never** for Firm identity, an authorization input, an invariant-bearing domain attribute, or anything a policy or constraint must evaluate. `jsonb` is not a schemaless escape hatch, and a domain attribute hidden inside a document cannot be constrained, indexed for uniqueness, or migrated under expand/contract with any confidence.
- **No document bytes, file contents, or attachments in the database.** Documents owns canonical content and its storage (Constitution Article 18); the database holds a `StorageObjectReference`, checksum, media type, and byte size — never the bytes. Release 0.1 has no document capability at all.
- **No monetary column exists.** `PF-045` `Money` is Backlog and deferred, and `docs/architecture/02_Product_Requirements.md` §3 states the application never collects, calculates, stores, transmits, or displays an amount. As a **forward constraint**: when monetary persistence exists, it is exact decimal (`numeric`) with an explicit currency, owned by `PF-045` and Billing; **floating-point money is prohibited platform-wide** (Constitution Article 23), and no second money type may be introduced.
- **Text encoding and collation** are treated in §9.4, because they determine index and uniqueness behaviour.

### 7.4 Concurrency control

- **Optimistic concurrency by default** for aggregates whose invariants span a request: a version column incremented on each write, with the update predicated on the expected version. A mismatch is a **conflict that fails**, surfaced to the caller — never a silent last-write-wins overwrite. In a legal-practice product, silently discarding a lawyer's concurrent edit to a Matter is a professional-responsibility failure, not a UX inconvenience.
- **Pessimistic locking where a decision reads before it writes** — `SELECT ... FOR UPDATE` on the aggregate row (§11.4).
- **Check-then-act is never trusted under `READ COMMITTED`.** Uniqueness, counting, and sequence assignment are enforced by a constraint plus retry, or under an explicit lock — never by reading and then inserting.
- **`MatterNumber`-style assignment** is concurrency-safe by construction: a Firm-scoped unique constraint (§9.2) plus a transaction-scoped advisory lock or a bounded retry. A collision is **rejected**, never silently resolved by appending a suffix (`docs/architecture/13_Practice_Management_Architecture.md`). Reading the current maximum and inserting one higher is prohibited.

### 7.5 Derived state is never stored as authoritative

- **A balance is never a column.** All financial balances derive from append-only ledger entries, and direct balance mutation is prohibited platform-wide (Constitution Article 23). This applies to any derived aggregate — counts, totals, statuses computed from other rows — where an approved architecture says the entries are authoritative.
- A **materialized projection** of derived state is permitted as a read model, clearly marked as derived, rebuildable from its source, never written to directly by a domain command, and never the answer to an authorization or fiduciary question. It is eventually consistent and never described otherwise.
- **No cross-Firm view or materialized view exists** (Constitution Article 45; §18).

### 7.6 Soft deletion

- A `deleted_at`-style column is permitted **only** where the owning architecture defines a domain lifecycle state that it represents — archival, retirement, supersession.
- **It is never an access control.** Hiding a row by a soft-delete flag is not authorization, and a global Eloquent scope over it is not isolation.
- **Archival is not deletion** (Constitution Article 19). A legal hold blocks deletion, purge, retention expiry, and destructive redaction until an authorized human releases it.
- **Deleting content never destroys the audit fact that the record existed** (Articles 19, 23; §12.6).

---

## 8. Referential integrity and cross-bounded-context reference rules

### 8.1 Inside one bounded context

**Real foreign keys, composite, carrying `firm_id`** (§6.3). Within a context, referential integrity is the database's job and doing it in application code instead is strictly worse.

Default referential action is **`ON DELETE RESTRICT`** (or `NO ACTION`); see §10.

### 8.2 Across bounded contexts — identifier only

**A cross-bounded-context reference is an identifier column with no foreign key constraint.** No database-level relationship, no join in a query owned by one context against another's relation, no shared Eloquent model, no cross-context write.

Why:

- **A foreign key is ownership.** It makes one context's schema a dependency of another's, couples their migration order permanently, and lets the referencing context's constraints govern the referenced context's deletion behaviour — inverting ownership the Constitution assigns explicitly (Articles 16, 18, 22, 26, 33).
- **It encodes knowledge that must be requested, not assumed.** Whether a `Matter` exists, whether the caller may know it exists, and whether an Ethical Wall permits the association are questions only Practice Management may answer, through `CheckEthicalWallAccess` and its published queries. A join answers a different question — "is there a row" — and answers it without authorization.
- **It would make denied-existence confidentiality impossible**: a foreign-key violation message discloses whether a row exists.

**The cost, stated plainly: there is no database-level guarantee that a cross-context identifier still resolves.** A dangling reference is possible. The consequences are assigned rather than wished away:

- The **owning context validates the reference at command time**, through the published contract, before the referencing state change is accepted.
- Where authorization depends on the reference, **an unavailable owning context fails closed** — the operation is rejected or left explicitly pending, never approximated and never resolved from a stale cache. This is already the approved rule for a Practice Management outage in `AGENTS.md`.
- **Both sides' `firm_id` must match.** A cross-context reference never carries another Firm's identifier; the owning context's validation includes the Firm check, and no cross-Firm reference is ever valid.
- Where a reference may legitimately become unresolvable over time — a closed Firm, a purged record — the referencing context defines what it displays and records, and **never fabricates** the referenced fact.

### 8.3 The `firm_id` reference itself

`firm_id` references `PlatformAdministration`'s `Firm`, which is by definition **cross-context** for every other module. Under §8.2 it is therefore an identifier-only reference with no foreign key.

This is a genuine trade-off and is recorded as such: a foreign key to `Firm` would guarantee that no row references a non-existent Firm, at the price of making every module's schema depend on `PlatformAdministration`'s, and of giving one context's referential actions authority over every other's rows. **The identifier-only rule wins**, because `firm_id`'s integrity is already protected by four independent mechanisms — it is `NOT NULL`, immutable, constrained by the row policy's `WITH CHECK`, and only ever populated from a `FirmContext` built from verified identity and membership. A `firm_id` that names no Firm cannot be produced by any authorized path.

**Whether a deliberate, narrowly justified exception is made for this one reference is an open item for the implementing story** (§22), which must record its decision either way rather than leaving it to a migration author.

### 8.4 Platform-global references

A reference to platform-global data — a `LegalSource` version, a seeded taxonomy entry — is an identifier reference to a relation that carries no `firm_id`. A foreign key is permissible here in principle, since the referenced relation is not another Firm's data and not another Firm-scoped context's; whether one is used is the referencing context's decision, and it never makes a Firm-scoped row's visibility depend on a global row.

---

## 9. Indexes and Firm-scoped uniqueness

### 9.1 Indexes lead with `firm_id`

Every index on a Firm-scoped relation **includes `firm_id`, and leads with it** unless the owning story records a specific reason otherwise. Every real query and every row policy filters by Firm; an index that does not include it forces the planner to filter after retrieval, which is both slow and — more importantly — a shape that invites someone to write the query without the Firm predicate because it "works".

**No index exists whose only purpose is to make a cross-Firm query efficient.** Such an index is a cross-Firm capability, arriving as a performance change.

### 9.2 Uniqueness is Firm-scoped

**Every uniqueness constraint on Firm-scoped data is `UNIQUE (firm_id, ...)`.** A globally unique constraint on Firm data is prohibited.

The reason is not only correctness — two Firms obviously may both have a client named the same thing, and both may run a `MAT-2026-001` — but **confidentiality**:

**A global unique constraint discloses the existence of another Firm's row.** A Firm creating a record and receiving a uniqueness violation for a value it cannot see has just learned that another Firm holds it. For a client name, a matter reference, or an email address in a legal-practice platform, that is an existence disclosure across a conflicts boundary, delivered by a constraint error. Denied-existence confidentiality (Constitution Article 28; `docs/architecture/02_Product_Requirements.md` §9) forbids it.

**Genuinely platform-global uniqueness** — an official citation on a platform-global `LegalSource`, a seeded taxonomy code — is unique on a platform-global relation (§2.4), where there is no Firm to disclose.

### 9.3 Partial and conditional uniqueness

Partial unique indexes express Firm-scoped domain invariants that a plain constraint cannot. Concretely, and as an illustration only:

- **Exactly one Primary `MatterClient`** — a partial unique index on `(firm_id, matter_id)` where the role is primary. This enforces "never more than one".
- **"and never absent afterwards"** is **not** expressible as a constraint, because it is a condition on a state transition rather than on a row set. It is a domain invariant enforced in the aggregate and covered by tests. **Deferred constraints are not relied upon** to paper over this: a deferred check that fires at commit produces a failure detached from the command that caused it.

Stating the boundary explicitly matters more than the example: **a database constraint enforces what is expressible as a predicate over rows. Every other invariant is the domain's, and pretending otherwise leaves it unenforced.**

### 9.4 Text encoding, collation, and Thai correctness

Thai text correctness — encoding, collation, normalization, sorting, and rendering — is an approved Release 0.1 requirement (`docs/architecture/02_Product_Requirements.md` §2, item 21). Persistence constrains it directly:

- **Database encoding is UTF-8. Mandatory, no alternative.**
- **Collation determines sort order, comparison, and index usability.** A byte-order collation sorts Thai text incorrectly for a Thai reader. A locale- or ICU-aware collation sorts it correctly but changes which indexes can serve which comparisons, and a **non-deterministic** collation cannot be used in every index and constraint context PostgreSQL supports. The specific collation choice is the Thai-text-correctness story's own approved decision; **that it is an explicit, recorded decision rather than an inherited default is this document's requirement.**
- **Unicode normalization is an application-level obligation performed before comparison, uniqueness, and storage.** Two Thai strings that are visually identical in different normal forms are different byte sequences: they will pass a unique constraint and produce two records a Firm perceives as one. Normalizing at the boundary — consistently, in one place — is the only reliable fix; a database constraint cannot do it.
- **Case- and accent-insensitive uniqueness, where a domain requires it, is expressed by an index over a normalized expression, consistently applied**, never by ad-hoc `lower()` calls at some call sites.
- **Changing a collation is a reindexing event**, and under expand/contract it is a migration with a plan (§15.4), never an in-place setting change.

### 9.5 Search, full-text, and vector indexes

**None is approved here.** Full-text indexes, trigram indexes, and vector/embedding storage are the owning contexts' decisions, and each inherits its source's Firm and access boundary in full: index entries and embeddings are derivatives (Constitution Articles 21, 42), Firm-then-permission filtering happens **before** retrieval, an access change removes them from future retrieval, and **cross-Firm retrieval, embeddings, caching, and evaluation data are prohibited without exception**. Release 0.1 has no search index, no embedding, and no AI capability at all.

---

## 10. Destructive-cascade policy

**Destructive cascades are denied by default. `ON DELETE CASCADE` requires explicit recorded approval under the `AGENTS.md` destructive-operations gate.** Unapproved destructive cascades are already a named prohibited pattern in `docs/domain/06_Laravel_Module_Blueprint.md`.

**Rules**

- **Default referential action is `ON DELETE RESTRICT` or `NO ACTION`.** A delete that would orphan a row fails loudly.
- **`ON DELETE CASCADE` is permitted only** where every one of the following holds, and the approval records that they do: the parent and child are inside **one aggregate boundary in one bounded context**; the child has **no independent identity, lifecycle, or audit meaning**; the child is **not** an audit record, ledger entry, event record, outbox row, version record, or attestation; and no **legal hold, retention rule, or professional-responsibility obligation** could attach to the child independently.
- **`ON DELETE SET NULL` is prohibited on any column in a tenancy, attribution, or authorization path.** Nulling `firm_id` or an actor reference converts a referential problem into an isolation or accountability problem.
- **Cascades never cross a bounded-context boundary**, which follows automatically from §8.2 — there is no cross-context foreign key to cascade along.
- **`TRUNCATE` is prohibited in production**, and prohibited in any environment against a relation holding audit or posted history. It bypasses row-level rules and triggers, and it is not a delete.
- **Dropping a relation that holds audit, posted, or version history requires its own explicit approval** and is never a routine contraction step (§15.4).
- **Deleting content never deletes the audit fact.** Constitution Articles 19 and 23 require that the record of a record's existence survive its content's removal; a cascade that would remove the audit row is prohibited regardless of approval, because the approval gate cannot authorize a constitutional violation.
- **A legal hold blocks deletion.** The database is not the authority on hold state, and precisely for that reason **no physical delete path may exist that can run without the owning domain consulting hold, retention, and archival state.** A cascade is such a path, which is most of why cascades are denied by default.
- **Retention purge, where an approved architecture defines one, is an explicit, authorized, audited domain operation** — never a side effect of deleting a parent.

---

## 11. Transaction boundaries and locking

### 11.1 One command, one transaction

A single application command executes in a single database transaction. The transaction is opened by the transaction manager (`PF-073`), Firm context is established at its start (§4), and it commits or rolls back as a unit.

### 11.2 What commits together

**Atomically, in the same transaction:**

- the aggregate's state change;
- the **outbox row** for each domain event it recorded (§13) — required by `docs/domain/06_Laravel_Module_Blueprint.md`;
- the **audit row** for the action (§12);
- any idempotency record that records the command as performed (§14).

**If any of these cannot be written, the state change does not commit.** An event that was not durably recorded alongside its state change is an event that will be lost, and an audit row written in a separate transaction is an audit trail with holes exactly where a failure occurred.

**One transaction never spans two Firms.** Firm context is transaction-scoped and never changed mid-transaction (§4.2).

**One transaction normally spans one aggregate.** Where an approved architecture genuinely requires two aggregates to change together, that is a recorded decision in that architecture, not a convenience taken in a handler.

### 11.3 What must never be inside a transaction

**No external call, ever** (Constitution Articles 34, 43): no HTTP request, no provider or payment-processor call, no email or message dispatch, no object-storage write, no search-index update, no AI/model call, no queue push other than the outbox row itself. An external call inside a transaction holds locks for an unbounded remote duration and creates a state where the remote side has acted and the local transaction has rolled back.

**No waiting of any kind** — no user interaction, no approval, no sleep, no poll. A transaction that waits is a lock held for a business duration.

### 11.4 Isolation and locking

- **`READ COMMITTED` is the default**, and it is **never** relied upon for a check-then-act.
- Where an invariant depends on a value the transaction read — assigning a `MatterNumber`, counting active memberships against a seat limit, deriving a balance before posting — the transaction either **locks the row it depends on** (`SELECT ... FOR UPDATE` on the aggregate), or **relies on a unique constraint and retries** on violation, or runs at **`SERIALIZABLE` with an explicit retry policy** where the dependency genuinely cannot be expressed as a lock or a constraint.
- **`SERIALIZABLE` is a deliberate choice with a retry policy, never a default applied hopefully.** Serialization failures are normal at that level and must be handled, not logged.
- **Lock ordering is consistent** — aggregates are locked in a defined order to keep deadlocks a bounded, retryable event rather than a mystery.
- **Retry on a serialization failure or deadlock is permitted only for a command that is idempotent** or protected by an idempotency record (§14). Retrying a non-idempotent command is a duplicate side effect wearing a resilience costume.

### 11.5 Advisory locks

Where a lock is needed on something that is not a row — a Firm-scoped numbering sequence, a per-Firm maintenance operation — **transaction-scoped advisory locks only** (`pg_advisory_xact_lock`). Session-scoped advisory locks survive the transaction and leak across pooled connections for exactly the reason session-level `SET` does (§4.3), and a leaked lock is a platform-wide stall.

### 11.6 What is not claimed

**No exactly-once execution or delivery guarantee, and no global-ordering guarantee, anywhere** (Constitution Articles 34, 43). Read models and projections are **eventually consistent** and are described that way in every surface that presents them.

---

## 12. Authoritative append-only audit persistence

### 12.1 Audit is persisted data, not logs

**The authoritative audit record is a persisted, append-only relation set.** Operational logs are **never** the sole authoritative security or business record (Constitution Article 30). A log is unindexed, unqueryable under authorization, and, being a stream someone can rotate or lose, is not a record a Firm can rely on for professional accountability.

### 12.2 Append-only is enforced by the database, not by convention

Two independent mechanisms, deliberately redundant:

1. **Privileges.** The application runtime role holds `INSERT` and `SELECT` on audit relations and **is not granted `UPDATE` or `DELETE`.** A privilege that was never granted cannot be used by a bug.
2. **A trigger or rule that rejects `UPDATE` and `DELETE` outright.** Privileges can be misgranted by a future migration; a rejecting trigger fails the statement regardless of who runs it, including the owner.

Belt and braces is proportionate here. Audit is the record that explains every other failure; if it is editable, nothing else in this document is verifiable after the fact.

### 12.3 Audit must be writable when the subject is being denied

An audit row is frequently written **at the moment its subject is being refused, suspended, or destroyed** — a denied access, a revoked membership, a rejected transition. `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` models `SecurityEventStream` as its own boundary for exactly this reason.

Persistence consequence: **the audit write path never depends on the subject's own authorization outcome, and never on the subject aggregate's continued existence.** A denial audit row is written under the Firm context of the attempted access, which is available because context is established before the authorization decision (§4).

### 12.4 Audit is not editable by the actor being audited

No application path exposes `UPDATE` or `DELETE` on an audit relation to any actor, at any privilege level, including a Firm administrator and a platform operator. Role separation means that even an authorized administrative surface has no mechanism to rewrite it. **Correction is a new row referencing the corrected one** (Constitution Articles 8, 18, 23).

### 12.5 Distinct streams, never merged

Each context owns its own audit relations, and they are never consolidated into one platform audit table:

| Stream | Owner |
|---|---|
| Administrative audit — Firm lifecycle, provisioning, entitlement, seat-limit decisions, refusals within that context | `PlatformAdministration` (ARCH-019 §19) |
| Security events — authentication, authorization, membership, privileged access, support-access history | IdentityAccess (ARCH-016) |
| Business activity history | Practice Management and each owning domain |
| Posted ledger entries | Billing (Constitution Articles 23–24) |
| Document and knowledge version and access history | Documents |

Merging them would make "this Firm's subscription lapsed" and "this person's access was revoked" the same kind of record — materially different facts about materially different subjects (ARCH-019 §9). It would also give every context read access to every other's audit content, which no context is entitled to.

### 12.6 Content rules

- **Safe metadata only**: actor, Firm, event, result, timestamp, correlation and causation, authorization provenance, and the identifiers of affected records.
- **Never**: a credential, password hash, session token, MFA secret, recovery material, API secret, signing key, payment credential, privileged narrative, document bytes, knowledge body text, embedding, or **any cross-Firm information** (Constitution Articles 29, 30; ARCH-019 §19).
- **Never a blind dump of a request or response body.** What is recorded is chosen, not captured.
- Human, system, integration, and AI actors remain **distinguishable** (Constitution Articles 26, 35). An AI-assisted operation records the initiating human, the AI or system actor, the authorization relied on, and any required approval.
- Audit rows carry `firm_id` and are read under the same Row-Level Security as any other Firm-scoped relation.

### 12.7 Retention

- **Audit retention outlives business-content retention.** Deleting content never deletes the audit fact that the content existed (§7.6, §10).
- **Purging audit rows is not designed here and requires its own separately approved decision.** Where a jurisdictional obligation to erase collides with an obligation to retain audit, that conflict requires Thai-qualified legal review and its own approved policy; **this document asserts no legal conclusion and resolves no such conflict** (§17.5, §20.9).
- **No partitioning or archival scheme is selected.** If audit partitioning is adopted later, **detaching a partition must not become a silent deletion path** — a detached partition is still audit history until an authorized, recorded decision says otherwise.

---

## 13. Transactional outbox persistence

### 13.1 What it is and where it lives

**One Platform Foundation-owned outbox relation** (`PF-091`). It is not a module relation, and it is not per-module: a publisher must read one place to publish, and N module tables would mean N publishers or a union view that becomes a de-facto shared relation with none of the guarantees.

Foundation is a shared technical-primitive layer, not a bounded context. A module writing an outbox row through Foundation's published contract, inside its own transaction, is **not** a cross-module table write (§21.2).

### 13.2 Atomicity

**The state change and the outbox row commit atomically** (`docs/domain/06_Laravel_Module_Blueprint.md`; §11.2). This is the entire point of the pattern: it removes the window in which state changed and the event did not, or the event was published and the state was rolled back. If the outbox row cannot be written, the command fails.

### 13.3 The row, conceptually

**Conceptual content only — no column names, types, or migration are defined here.** `PF-091` owns the actual shape.

- **Event identity** — the `PF-043` `eventId()` value, a UUIDv7, carried as a native `uuid`. The **same canonical value** as the in-memory event; across persistence and reconstitution what is preserved is the value, never the PHP object.
- **`firm_id`** — `NOT NULL`. The outbox is Firm-scoped like everything else.
- **Subject identifiers** — the aggregate type and identifier the event concerns, which is what "per-subject ordering" is per (§13.5).
- **Event type and event version** — an explicit, versioned internal event name. An internal event is **not** an external contract; `IntegrationEventEnvelope` authoring stays with `Integrations` (Constitution Article 31).
- **Payload** — `jsonb`, **safe content only**: identifiers and safe metadata. **Never** document bytes, knowledge body text, privileged content, embeddings, credentials, secrets, payment data, or cross-Firm information (`AGENTS.md`, per-module rules).
- **Times** — `occurred_at` from the domain (`PF-043`), and a separate infrastructure `recorded_at`. **Never conflated** (§7.2).
- **Ordering sequence** — an explicit monotonic sequence assigned at insert (§13.5).
- **Delivery bookkeeping** — status, attempt count, next-attempt time, last error (safe metadata only), `published_at`.
- **Provenance** — correlation and causation identifiers, initiating actor, effective actor, and where applicable the AI or system actor and the authorization relied on (Constitution Articles 35, 41).

### 13.4 The outbox is not the audit record

They are separate relations with separate rules and separate lifecycles. The outbox is a **delivery mechanism** whose rows may legitimately be pruned after publication under an authorized operational policy. Audit is a **record** that is not pruned (§12.7). Using one as the other loses either durability of the record or the ability to ever clean up delivery state.

### 13.5 Ordering — and why UUIDv7 cannot provide it

**Outbox ordering never relies on UUIDv7, and never on a wall-clock timestamp.** §6.2 and §7.2 give the reasons: same-millisecond order is arbitrary, clocks move backwards, and processes generate concurrently.

Ordering, **where it is offered at all**, is:

- **per-subject only** — never global. `docs/architecture/07_API_Standards.md` §12 and Constitution Article 34 make no global-ordering claim, and neither does this document;
- derived from an **explicit monotonic sequence** assigned at insert — a bigint identity or sequence column, which is ordering metadata and never an identifier (§6.3).

**A subtlety that must not be papered over: a PostgreSQL sequence is monotonic in *assignment*, not in *commit visibility*.** A row assigned sequence 100 can become visible **after** a row assigned 101, because the transaction holding 100 committed later. A publisher that tracks a high-water mark and reads "rows with sequence greater than my cursor" will therefore **skip** rows permanently. This is a real, well-known, silent data-loss mode, and it is recorded here so `PF-091` and `PF-092` cannot design past it by accident.

**Safe approaches** — the choice, with its recorded reasoning, belongs to `PF-091` and `PF-092`:

- **Claim by status, not by cursor** — select pending rows with `FOR UPDATE SKIP LOCKED`, publish, then mark them. Correct under concurrency and immune to the visibility gap, at the cost of an update per row.
- **Per-subject gating** — publish a subject's next row only when its predecessor is published, which is what makes per-subject ordering meaningful at all.
- A high-water-mark cursor is **acceptable only** with an explicit mechanism that closes the visibility gap, and never on its own.

### 13.6 Publication path and Firm context

- The publisher **never mutates domain state.** It reads outbox rows and writes its own bookkeeping.
- **Preferred: per-Firm claiming.** The publisher establishes Firm context and claims that Firm's pending rows, so the outbox is read under the same Row-Level Security as everything else.
- **Where a cross-Firm scan of pending work is genuinely required**, it is a **bounded, named exception**: a dedicated publication role (§4.1) with access to **the outbox relation only and no business relation**, joining nothing, producing no cross-Firm report or aggregate, and **re-establishing the row's own Firm context before any domain-side work occurs**. See §21.3 for why this does not contradict Constitution Article 45.
- **`PF-091` and `PF-092` must choose between these and record the choice.** This document does not pretend the question is settled.

### 13.7 Delivery semantics

- **At-least-once. No exactly-once claim, ever** (Constitution Articles 34, 43).
- Consumers must be idempotent. `PF-093` (consumer foundation) is not required by Release 0.1, which has no consumer.
- A **retry redelivers the same event identity under a new delivery attempt** and never mints a new event identity (`PF-043`; `docs/architecture/07_API_Standards.md` §12).
- A **permanently failed row is retained and surfaced**, never silently deleted. Dead-letter handling is `PF-091`'s design; discarding an undeliverable committed business fact without a human decision is not.
- **Pruning published rows is an authorized, recorded operational policy**, and it is safe only because the outbox is not the audit record (§13.4).
- **A restore can replay already-delivered events** (§17.4). That is acknowledged, not claimed away.

---

## 14. Idempotency persistence

### 14.1 Scope and record

An idempotency record is scoped by, at minimum: **the Firm; the integration installation where one exists; the API contract and version, or the internal command type; the operation; the target resource where applicable; and the idempotency key itself** (`docs/architecture/07_API_Standards.md` §10).

- **The scoped key carries a unique constraint**, so a duplicate is rejected by the database rather than by a read-then-write race that fails precisely under the concurrency it exists to handle (§7.4).
- The record stores a **fingerprint of the request's material inputs**.
- **Same scoped key, different fingerprint → an idempotency-conflict failure.** It must **never** return the first request's result as though the second, different request had been accepted.
- The record is written **in the same transaction as the side effect it protects** (§11.2). Written before, it can record work that never happened; written after, it can miss work that did.

### 14.2 Replay never bypasses authorization

**A replayed request re-authenticates and passes current Firm, membership, capability, and domain authorization before anything is returned** (`docs/architecture/07_API_Standards.md` §10). Idempotency short-circuits the **side effect**, never the authorization composition.

Persistence consequence: **a stored response is not a licence to disclose.** Because a recorded response must not be served to a caller whose access has since been revoked, stored response content is **minimal** and is re-authorized before return. Where a response would contain protected content, storing the content verbatim is avoided in favour of storing enough to reconstruct it under current authorization.

### 14.3 Retention and its honest tension

- Idempotency records expire under a **defined, bounded window**.
- **The window must be at least as long as the period in which a client may still retry.** Expiring earlier silently re-enables the duplicate side effect the record existed to prevent — and it does so quietly, which is worse than refusing.
- Choosing that window is the implementing story's decision, and it is a **trade-off between storage and duplicate-suppression coverage that cannot be resolved by asserting a number here.**
- **A restore to a point before an idempotency record existed can permit a duplicate side effect** (§17.4). Acknowledged.

### 14.4 Content and scope limits

- **No credential, secret, token, or privileged content** in an idempotency record.
- The idempotency relation is **not** the outbox and **not** the audit record. Three distinct concerns, three relations.
- **Release 0.1 has no public API, no webhooks, no service principals, and no connectors**, so external idempotency has no consumer yet. **Internal command-retry idempotency still applies** wherever a command may be retried after an ambiguous failure, and §11.4's retry rule depends on it.

---

## 15. Migration and schema-evolution policy

### 15.1 Forward-only in production

**Production migrations are forward-only.** No down-migration is relied upon in production, ever.

- A `down()` method may exist for local development convenience. **It is never the production recovery plan**, and a story that documents "roll back with `migrate:rollback`" as its recovery path has no recovery path.
- **Production recovery from a bad migration is: stop, assess, and roll forward with a corrective migration** — or, where data was lost, restore and roll forward (§17). A reverse migration on live data routinely cannot restore what a forward migration destroyed, and it runs untested code against a state it has never seen.
- **Historical migrations are never edited** (`AGENTS.md`).

### 15.2 Expand / contract, in three separate deployments

| Phase | Content | Property |
|---|---|---|
| **Expand** | Additive only — new nullable column, new relation, new index, new constraint added `NOT VALID` | Old and new code both work against it |
| **Migrate** | Backfill and dual-write; validate the constraint; switch reads | Batched, resumable, reversible by doing nothing |
| **Contract** | Remove the old column, relation, index, or constraint | Runs only once **no code path references it** |

Each phase is a **separate migration and a separate deployment**. Collapsing them produces a window in which running code and live schema disagree.

### 15.3 Backfills

- **Batched, idempotent, restartable, and interruptible.** A backfill that must run to completion in one statement is an outage.
- **Firm-aware** (§15.5).
- Never inside the same transaction as the schema change that enabled it.
- **A backfill that computes a domain value applies the domain's own rules**, not a hand-written SQL approximation of them. Where that is impractical, the story records what approximation was made and why — it does not leave the divergence undocumented.

### 15.4 Locking hazards — prohibited without an approved plan

Each of the following takes a long or blocking lock, or rewrites a relation, and is prohibited without an approved plan recorded in the story:

- Adding a column with a **volatile** default, or adding `NOT NULL` to a populated column in one step.
- **Changing a column's type** on a populated relation.
- **Adding a constraint that validates immediately** — instead: add `NOT VALID`, then `VALIDATE CONSTRAINT` separately.
- **Creating or dropping an index without `CONCURRENTLY`** on a populated relation. `CREATE INDEX CONCURRENTLY` cannot run inside a transaction, which means it is its own migration.
- **Renaming** a relation or column that running code references.
- **Changing a collation** (§9.4) — a reindexing event with a plan.
- Any statement holding `ACCESS EXCLUSIVE` for an unbounded period.

**No zero-downtime claim is made** by this document, for any migration.

### 15.5 Row-Level Security, roles, and privileges are schema, and forced RLS constrains maintenance

- **Row policies, roles, grants, and `FORCE ROW LEVEL SECURITY` state change only through migrations**, under the same review as any other schema change.
- **A policy or privilege change is a security and authorization change** and hits that `AGENTS.md` approval gate, not merely the database one.

**The consequence of §3.3.2, resolved rather than evaded:** because `FORCE ROW LEVEL SECURITY` subjects the relation's **owner** to its policies, the migration role cannot see or modify all Firms' rows by default. That is intentional. Data maintenance spanning Firms is therefore performed **either**:

- **(a) per-Firm, iterating Firms and establishing each one's context** with `SET LOCAL` — the **preferred** approach, because it keeps the isolation boundary intact and makes progress resumable per Firm; **or**
- **(b) through a narrowly scoped, explicitly authorized, recorded maintenance path** whose use is audited and time-bounded.

**Never** by disabling Row-Level Security, dropping a policy, or granting `BYPASSRLS` — least of all to the application runtime role. A migration that turns the isolation boundary off to get its work done has removed the control for the duration of the riskiest operation on the platform.

### 15.6 Destructive schema changes

Dropping a relation or column, narrowing a type, adding a cascade, or removing a constraint requires **explicit approval** under the `AGENTS.md` destructive-operations gate. Dropping anything holding audit, posted, or version history requires its own approval and is never a routine contraction (§10, §12).

### 15.7 Execution and drift

- **No automatic migration on deploy** without an approved operational procedure. The existing Docker development environment already establishes "no automatic migrations" as the precedent (`PF-010`).
- **Migration state is authoritative and verified.** A hand-applied schema change is a defect, not a shortcut; drift between recorded migration state and actual schema is detected, not discovered later.
- **A migration never runs as the application runtime role** (§4.1).

---

## 16. PostgreSQL testing obligations

### 16.1 Why the current test configuration cannot verify any of this

`phpunit.xml` runs the suite on SQLite `:memory:`, while `AGENTS.md` makes PostgreSQL authoritative. **On SQLite, none of the following is testable at all:** Row-Level Security, `FORCE ROW LEVEL SECURITY`, `SET LOCAL` and transaction-scoped settings, native `uuid` semantics, `timestamptz` semantics, partial unique indexes, deferrable constraints, `FOR UPDATE SKIP LOCKED`, database roles and privileges, triggers rejecting `UPDATE`/`DELETE`, and real foreign-key referential actions.

**Database-policy-level Firm isolation cannot be honestly tested on a different engine.** Testing it on SQLite would produce a green suite that proves nothing about the control it names — the most dangerous possible outcome for an isolation control in a legal-practice platform.

**Therefore: the PostgreSQL continuous-integration story must land before `PF-080` begins.** `docs/implementation/03_Engineering_Backlog.md` already records it as a Backlog story with that ordering constraint, and `docs/architecture/02_Product_Requirements.md` §8 records the same. The approved working direction for ARCH-012 refers to it as **`PF-033`**; see §21.10 and §22 for the honest status of that identifier. **This document changes no CI configuration, no `phpunit.xml`, and no test.**

**The four required `Protect main` check names — `PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit` — are preserved exactly.** A rename without a matching human-reviewed ruleset update makes the repository fail closed.

### 16.2 Tests must run as the application runtime role

**A Row-Level Security test executed as the relation's owner or as a superuser proves nothing.** This is the single most common way an RLS test suite is worthless: it passes, and it would pass with the policy deleted.

Every isolation test therefore runs **as the application runtime role** (§4.1), with fixtures created through an appropriately privileged path that is not the role under test.

### 16.3 Required test obligations

Each is an obligation on the story that introduces the relation or mechanism, not a test written by this document:

**Tenancy and Row-Level Security**

- `firm_id` is `NOT NULL` and rejects a null insert.
- `firm_id` is immutable — an `UPDATE` changing it is rejected.
- Row-Level Security is **enabled and forced** on every Firm-scoped relation, and every such relation has a policy — asserted as a **schema-level guard test that enumerates relations**, so a newly added table cannot ship unprotected.
- A read with Firm A's context returns **none** of Firm B's rows.
- A write attempting to place a row in another Firm is **rejected by `WITH CHECK`**.
- **Unset, empty, and malformed Firm context fail closed** — the statement raises; it does not return all rows and does not silently return zero (§3.4).
- A transaction cannot change Firm context mid-flight.
- Firm context does **not** survive the transaction — the same connection, in a new transaction with no context, fails (§4.3).
- The application runtime role is not a superuser, does not own the relations, and holds no `BYPASSRLS`.

**Identity and integrity**

- A composite foreign key **rejects** a reference to a row in another Firm.
- A Firm-scoped unique constraint **permits** the same value in two Firms and **rejects** a duplicate within one.
- No relation carries a database-generated identifier default.
- Ordering tests **do not assume** identifier or timestamp ordering (§6.2).

**Audit**

- The application runtime role's `UPDATE` and `DELETE` on an audit relation are **rejected at the privilege level**.
- They are **also rejected by the trigger**, independently.
- An audit row is written for a **denied** access, and the write does not depend on the subject's authorization outcome (§12.3).

**Outbox and idempotency**

- The state change and the outbox row commit together; a forced failure of either rolls back both.
- Publication is safe under concurrency and does not skip rows under interleaved commits (§13.5).
- A duplicate scoped idempotency key is rejected by the constraint.
- Same key with a different fingerprint yields a conflict, not the first result.
- A replay re-evaluates authorization before returning anything.

**Migrations**

- A forward-only migration run from an empty database succeeds and produces the expected schema.
- No test depends on a down-migration.
- A backfill is restartable and produces the same result when re-run.

### 16.4 Test data and environments

**Synthetic data only.** Non-production never holds unprotected production data (`docs/architecture/04_Security_Architecture.md` §5). A restore of production into a test environment is a data-classification event, not a convenience.

---

## 17. Backup and restore requirements that constrain persistence

**No hosting provider, managed-database service, connection pooler, backup product, secret-management product, key-management service, region, or infrastructure provider is selected here or anywhere in this document** (§19). What follows are **properties the persistence design must have, and consequences it must acknowledge**, so that a future deployment architecture can satisfy them rather than discovering them.

### 17.1 Requirements on the persistence design

- **All authoritative data is durably logged.** **`UNLOGGED` relations are prohibited for anything authoritative** — a relation that survives a crash empty cannot hold a business record, an audit row, an outbox row, or an idempotency record.
- **Point-in-time recovery must remain possible.** The persistence design introduces nothing that would make it impossible; whether it is configured, and how, is the deployment architecture's decision.
- **Restore must preserve append-only audit continuity.** A restore that silently drops posted entries or audit records is a **defect, not an acceptable recovery outcome** (`docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §925).
- **Encryption at rest is required**, and **backups are protected as production data** (`docs/architecture/04_Security_Architecture.md` §5). No key-management product is selected.
- **A restore must not resurrect one Firm's data into another Firm's context.** Physical recovery is Firm-agnostic; Firm isolation is re-established by the same `firm_id`, policy, and context rules the running system uses (§3, §4). A restore never relaxes them, and a restored database is not exempt from forced Row-Level Security.

### 17.2 A consequence of the shared schema, stated plainly

**Per-Firm point-in-time restore is not claimed to be supported.** One shared schema means physical recovery is a **whole-database** operation: restoring Firm A to an earlier point would restore every Firm to that point.

Restoring a single Firm's data would require a **logical, application-level, authorized export and import**, which is **not designed here**. Release 0.1 includes a Firm-scoped export (`docs/architecture/02_Product_Requirements.md` §2, item 20); that is an export capability, **not a restore capability**, and this document does not represent it as one.

This is a real limitation of the §2 decision and is recorded as such rather than left for an incident to reveal.

### 17.3 The executed restore test

`docs/architecture/02_Product_Requirements.md` §8 requires an **executed and recorded** restore test as production-access evidence. **No restore test has been executed, and this document does not claim, schedule, or substitute for one.**

### 17.4 Recovery interacts with delivery and idempotency — honestly

- **A restore that rewinds the database can replay already-delivered events.** Outbox rows marked published may return to a pending state. Delivery is at-least-once and consumers must be idempotent; **recovery is one of the concrete reasons that is not a formality** (§13.7).
- **A restore to a point before an idempotency record existed can permit a duplicate side effect** (§14.3).
- Neither is claimed to be prevented. Both are consequences of recovery that the design acknowledges and that operational procedure must account for.

### 17.5 Retention, deletion, and backups

A backup retains content after the live database has deleted it. Where a jurisdictional obligation to erase collides with retention in backups, with audit-retention obligations, or with a legal hold, **that conflict requires Thai-qualified legal review and its own separately approved policy. This document asserts no legal conclusion and resolves no such conflict** (Constitution Articles 1–4; `docs/architecture/04_Security_Architecture.md` §9).

---

## 18. Prohibited patterns

Each is prohibited. Where a pattern could be permitted under an explicit approval, that is stated.

**Tenancy**

- A nullable `firm_id`, a sentinel or zero-UUID Firm, or a "shared Firm" row on a Firm-scoped relation.
- A Firm-scoped relation without `FORCE ROW LEVEL SECURITY` and a policy.
- A policy with `USING` but no `WITH CHECK`.
- A policy predicate that reads a caller-supplied value, an entitlement status, a capability, a role, or anything other than Firm identity (§3.5).
- A row policy used to simulate an Ethical Wall, or any wall-shaped column, flag, or policy (`docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`).
- Disabling Row-Level Security, dropping a policy, or granting `BYPASSRLS` to make a query, migration, backfill, or test work.
- Running the application as a superuser, as a relation owner, or with `BYPASSRLS`.
- Relying on Row-Level Security as the only isolation control — or on application scoping as the only one.
- An Eloquent global scope as the isolation mechanism (`docs/domain/06_Laravel_Module_Blueprint.md`).
- A session-level `SET`, `set_config(..., false)`, or a session-scoped advisory lock for Firm context or Firm-scoped coordination.
- `SET LOCAL` outside an explicit transaction; a transaction that changes Firm context mid-flight; a transaction spanning two Firms.
- Establishing Firm context from a header, parameter, route, body, cookie, hostname, custom domain, or email address.
- Trusting a pooled connection's role or residual session state.
- A cross-Firm view, materialized view, index, projection, cache, or report.
- A read-only cross-Firm reporting role (Constitution Article 44).

**Identity and integrity**

- A database-generated identifier default — `gen_random_uuid()`, a server-side UUIDv7 function, a trigger, or a sequence — on a domain relation.
- `bigserial` or any auto-increment surrogate as a domain primary key.
- Storing a UUID as `char`, `varchar`, `text`, or `bytea`.
- `ORDER BY` an identifier, or an identifier used as a cursor implying time order.
- Deriving an ordering, deduplication, or idempotency key from an identifier's timestamp bits.
- A natural or business key — `MatterNumber` included — as a primary key.
- A single-column foreign key where a composite Firm-carrying one is required (§6.3).
- A foreign key, join, or shared Eloquent model across a bounded-context boundary.
- A module writing, altering, or migrating another module's relations.

**Columns and types**

- `timestamp without time zone`.
- `now()`, `CURRENT_TIMESTAMP`, or a column default as the source of a domain timestamp.
- Collapsing `occurred_at`, `recorded_at`, `committed_at`, `published_at`, and `delivered_at`.
- Floating-point money; a second money or currency type (Constitution Article 23).
- A stored, directly mutable balance or other authoritative derived value (Constitution Article 23).
- A PostgreSQL `enum` type for an evolving domain value set.
- `jsonb` holding Firm identity, an authorization input, or invariant-bearing domain state; an unbounded dump of a request or response body.
- Document bytes, file contents, knowledge body text, or embeddings stored in the database.
- Credentials, password hashes in a non-credential relation, session tokens, MFA secrets, recovery material, API secrets, signing keys, or payment credentials in any business, audit, event, outbox, or idempotency record.
- `UNLOGGED` relations for authoritative data.
- `deleted_at` — or any flag — treated as an access control.

**Destruction**

- `ON DELETE CASCADE` without explicit recorded approval; a cascade that would remove an audit, ledger, version, attestation, outbox, or event record — which no approval can authorize.
- `ON DELETE SET NULL` on a tenancy, attribution, or authorization column.
- `TRUNCATE` in production, or against any relation holding audit or posted history.
- A physical delete path that can run without the owning domain consulting legal hold, retention, and archival state.
- Deleting content in a way that destroys the audit fact that it existed.

**Transactions**

- Any external call — HTTP, provider, payment, email, message, object storage, search index, AI/model, or queue push other than the outbox row — inside a database transaction.
- Waiting for a human, an approval, a timer, or a poll inside a transaction.
- Check-then-act under `READ COMMITTED` without a lock or a constraint.
- Read-max-then-insert for sequence or number assignment.
- Silent last-write-wins on a contended aggregate.
- Retrying a non-idempotent command after an ambiguous failure.

**Audit, outbox, idempotency**

- Treating application logs as the authoritative audit record.
- Granting `UPDATE` or `DELETE` on an audit relation to any application actor.
- One consolidated platform-wide audit table merging distinct streams.
- Using the outbox as the audit record, or the audit record as the outbox.
- Outbox ordering from a UUIDv7, a wall-clock timestamp, or a bare high-water-mark cursor over a sequence (§13.5).
- Claiming exactly-once delivery or global ordering.
- Silently deleting an undeliverable outbox row.
- Returning a stored idempotent result without re-evaluating current authorization.
- Returning the first result for a reused key with a different fingerprint.
- Checking entitlement on a per-request basis in a policy, predicate, or query (`docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` Decision 8).

**Schema evolution and operations**

- Editing a historical migration.
- Relying on a down-migration in production.
- Automatic migration on deploy without an approved procedure.
- Running a migration as the application runtime role.
- Adding a validated constraint, a volatile-default column, a non-concurrent index, or a type change on a populated relation without an approved plan.
- Hand-applied schema changes; unreconciled schema drift.
- Business logic in triggers, rules, stored procedures, or functions — these are permitted for **append-only enforcement and integrity only**, never for domain invariants, which belong to the Domain layer (`docs/domain/06_Laravel_Module_Blueprint.md`).
- Production data in a non-production environment without an approved data-classification decision.
- Testing isolation on an engine other than PostgreSQL, or as a role that bypasses the control under test.

---

## 19. Explicit non-goals

This architecture does **not**: implement anything; create or modify any migration, schema, table, column, index, policy, role, grant, source file, test, configuration file, Docker file, CI workflow, dependency, environment, or GitHub setting; define the physical data model, table names, or column names of any module; define **which** aggregates, attributes, states, events, or invariants exist in any bounded context, all of which remain owned by that context's approved architecture; select, endorse, or evaluate a hosting provider, managed database service, cloud platform, region, connection pooler, backup product, replication or high-availability product, secret-management or key-management product, monitoring or observability product, search engine, vector database, queue or event-broker product, ORM alternative, migration tool, or any other vendor, provider, product, or package; define deployment, infrastructure, environment topology, network design, capacity, sizing, scaling, sharding, partitioning, replication, failover, or disaster-recovery procedure; define a PostgreSQL minimum version, extension set, or server configuration, each of which belongs to the deployment architecture and must satisfy the capability requirements recorded here; execute, schedule, or claim a backup or restore test; define a Reporting bounded context, cross-Firm reporting, analytics, benchmarking, comparison, data warehouse, or read-only analytics role — which Constitution Article 44 reserves for a future context this document **neither approves nor permanently prohibits**; define search, full-text, trigram, or vector/embedding storage or retrieval, which remain the owning contexts' decisions under Constitution Articles 21 and 42; introduce `Money`, `Currency`, or any monetary column, or schedule or unblock `PF-045`; introduce an AI capability or modify `docs/architecture/05_AI_Architecture.md`; create a second authentication, authorization, entitlement, or privileged-access path; define or approve a Firm-level suspension or emergency-disable capability; define an Ethical Wall, conflict-checking, or per-user Matter-visibility mechanism of any kind, in any layer (`docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`); define an audit-purge or audit-partition-detachment path; resolve any conflict between deletion obligations, retention obligations, legal hold, and backup retention; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; claim that any described control is implemented, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

**No capability, control, or property described in this document is claimed to be implemented.**

---

## 20. Security and professional-responsibility consequences

1. **A shared schema makes a missing predicate a privilege incident.** In a legal-practice platform, a cross-Firm read is not a data-quality defect. It is a confidentiality breach affecting clients who never consented, potentially a conflicts exposure between adverse parties, and potentially unremediable once it has happened. **The four-layer isolation model in §3 exists because of that asymmetry**, and no layer may be removed on the grounds that another one is present.

2. **Row-Level Security is the layer that catches what review missed** — a hand-written query, an ad-hoc script, a future maintainer's repository method, a migration-time convenience. It is defence in depth, never the primary control, and never a licence for a weaker repository (§3.1).

3. **`FORCE ROW LEVEL SECURITY` is what makes the boundary real.** Without it, the role most likely to be running the riskiest operations — the owner — is exempt. Its cost is the maintenance constraint in §15.5, which is paid rather than avoided.

4. **Unset Firm context fails closed, and fails loudly.** A silent empty result is specifically rejected: a lawyer shown an empty Matter list behaves very differently from one shown an error, and the empty list is the outcome that causes a missed deadline (§3.4).

5. **Session-level Firm context on a pooled connection is a cross-Firm read that passes every application check.** Transaction-scoped `SET LOCAL` is not a style preference; it is the difference between an isolation control and an intermittent, load-dependent disclosure (§4.3).

6. **A global unique constraint on Firm data discloses another Firm's rows through a constraint error.** Firm-scoped uniqueness is a confidentiality control as much as a correctness one (§9.2).

7. **A cross-Firm foreign key is invisible to Row-Level Security**, because each row satisfies its own policy independently. Composite, Firm-carrying foreign keys make same-Firm referencing a database-enforced fact (§6.3).

8. **Entitlement never enters a row policy.** Doing so would silently convert entitlement into a per-request authorization input, reversing an approved decision that deliberately protects an already-issued session after a commercial lapse (§3.5, §21.7).

9. **No Ethical Wall exists in any layer, including this one.** An approximated wall implemented as a row policy would be the most dangerous form of the approximation `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` prohibits, because it would look like a control while being untested, undisclosed, and located where no reviewer would check. **Every Firm member with Worklist access can see every Matter in the Firm, and that absence is disclosed** — it is not softened by a database feature.

10. **Append-only audit enforced by revoked privileges and a rejecting trigger** is what makes professional accountability verifiable after the fact. Audit must be writable at the moment its subject is being denied or destroyed, and never writable by the actor it records (§12.2–§12.4).

11. **Audit streams stay separate** so the security record remains truthful about what kind of event occurred and to whom (§12.5).

12. **A UUIDv7 discloses its generation time** to anyone holding it, and identifiers propagate widely once persisted. Internal identifiers are never exposed externally by convenience (§6.4).

13. **Deleting content never deletes the audit fact; a legal hold blocks deletion; archival is not deletion.** Cascades are denied by default because a cascade is precisely a delete path that runs without consulting any of that (§10).

14. **The database enforces predicates over rows. Every other invariant is the domain's**, and treating a constraint as though it enforced a transition invariant leaves that invariant unenforced (§9.3).

15. **Per-Firm point-in-time restore is not supported**, and a Firm-scoped export is not a restore capability. Stated now rather than during an incident (§17.2).

16. **Recovery can cause redelivery and can re-enable a duplicate side effect.** At-least-once delivery and idempotency-record windows are the reason that is survivable, and neither is claimed to be prevented (§17.4).

17. **Isolation tested on SQLite, or tested as a role that bypasses the control, is worse than untested** — it produces evidence of a control that does not exist. PostgreSQL CI before `PF-080`, and tests as the application runtime role, are both load-bearing (§16).

18. **Backup retention versus deletion obligations, audit retention, and legal hold is an unresolved legal question**, requiring Thai-qualified review and its own approved policy. **No legal conclusion is asserted here** (§17.5).

19. **AI holds no authority over anything in this document.** AI never grants access, never authors or approves a migration, policy, role, grant, or destructive operation, never writes or alters an audit record, and is never an authorization authority (Constitution Articles 6, 26, 28, 39, 40). Release 0.1 contains no AI capability.

20. **No certification, compliance, or production-readiness claim is made**, and nothing here is claimed to be implemented or effective.

---

## 21. Resolved conflicts and apparent contradictions

Each is resolved explicitly rather than left to a reader's inference.

**21.1 "Enforced in database policy" versus "global scopes alone are insufficient."** `docs/domain/06_Laravel_Module_Blueprint.md` requires both, and they are not in tension. **No conflict.** Database policy is added as a fourth layer (§3.1); application logic and repositories remain the primary enforcement; a global Eloquent scope is not an isolation control in any layer.

**21.2 "Module-owned migrations" versus a single Foundation-owned outbox and idempotency relation.** `app/Foundation` is a layer of **shared technical primitives, not a bounded context** (`AGENTS.md`, `docs/domain/06_Laravel_Module_Blueprint.md`). The rule that no module writes another module's tables is a rule about **bounded contexts**. A module writing an outbox row through Foundation's published contract, inside its own transaction, is **not** a cross-module write — it is a module using a platform primitive, exactly as it uses `Clock`, `UuidV7`, and the transaction manager. **Resolved:** Foundation owns platform infrastructure relations; modules own domain relations; nobody writes another **context's** tables.

**21.3 "No record, query, cache, projection, index, or event spans Firms" versus an outbox publisher scanning pending rows.** Constitution Articles 45 and 48 and `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` Decision 11 bind **`PlatformAdministration`**, and the broader prohibition is on cross-Firm **reads, reports, aggregates, and comparisons of Firm data**. The outbox publisher is Platform Foundation infrastructure that dispatches already-committed events. **Resolved:** the preferred design is **per-Firm claiming** under normal Firm context; where a cross-Firm scan of pending work is genuinely required, it is a bounded, named exception limited to the outbox relation, under a dedicated role with **no access to any business relation**, joining nothing, producing no cross-Firm report or aggregate, and re-establishing each row's own Firm context before any domain-side work (§13.6). **`PF-091` and `PF-092` must choose and record which.** No cross-Firm business read is created either way.

**21.4 "UUIDv7 is time-sortable" (`PF-048`) versus "UUIDv7 is not a monotonic ordering guarantee."** Both are true and they describe different things. **Resolved:** time-sortability is a **physical index-locality property** — a time-prefixed key clusters inserts — and it is the reason UUIDv7 is used rather than UUIDv4. It is **not** an ordering semantic: same-millisecond order is arbitrary, clocks move backwards, generation is concurrent, and commit order is not generation order. §6.2 keeps the benefit and denies the semantic.

**21.5 `FORCE ROW LEVEL SECURITY` versus the need to run migrations and backfills across Firms.** Forcing RLS subjects the owner to policies, so the migration role cannot see all Firms by default — which is intentional, and which would otherwise be "solved" by the worst possible means. **Resolved in §15.5:** per-Firm iteration with `SET LOCAL` (preferred), or a narrowly scoped, explicitly authorized, recorded maintenance path — **never** by disabling RLS, dropping a policy, or granting `BYPASSRLS`.

**21.6 "Firm-scoped unique constraints" versus genuinely platform-global uniqueness.** Constitution Article 5 requires platform-global legal reference data to exist once for every Firm, and such data legitimately has global uniqueness (an official citation). **Resolved in §2.4:** two explicitly declared relation classes, no third; Firm-scoped uniqueness applies to Firm-scoped relations, global uniqueness only to platform-global relations, where there is no Firm whose existence a constraint error could disclose.

**21.7 "Entitlement is enforced" versus "entitlement is never a per-request authorization input."** A row policy is the most natural-looking place to enforce entitlement and the most damaging. `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` Decision 8 and Constitution Article 46 evaluate entitlement at exactly two gates — authentication/session issuance and membership activation — precisely so that an already-issued valid session runs to its normal expiry after a commercial lapse. **Resolved:** **entitlement never appears in a row policy, a query predicate, or any per-statement check.** A policy predicate carries Firm identity only (§3.5).

**21.8 "Exact decimal with explicit currency" versus `PF-045` `Money` being deferred.** **Resolved:** no monetary column exists in Release 0.1, and the application never stores an amount (`docs/architecture/02_Product_Requirements.md` §3). §7.3 records the decimal/currency rule as a **forward constraint** owned by `PF-045` and Billing, not as a schema this document defines. Nothing here schedules or unblocks `PF-045`.

**21.9 "Firm isolation enforced in database policy" versus "Ethical Walls are absent and never approximated."** Both are approved and they are easily confused, because both are "restrict what a query returns." **Resolved:** Firm isolation is a **tenancy** boundary and belongs in a row policy. An Ethical Wall is a **per-actor, per-Matter professional-responsibility** control that Release 0.1 does not have and that `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` forbids approximating. **No wall-shaped column, flag, or policy exists in any layer**, and a row policy is never used to produce per-user Matter visibility (§3.5, §20.9).

**21.10 The PostgreSQL CI story's identifier.** The approved working direction for ARCH-012 refers to it as **`PF-033`**. `docs/implementation/03_Engineering_Backlog.md` records the story — "PostgreSQL CI — `Backlog`, its own approved story, must land before `PF-080` begins" — **without a story identifier**, and the string `PF-033` does not currently appear anywhere in the repository; Sprint 0.2 in `docs/implementation/01_Implementation_Sprint_Plan.md` ends at `PF-032`. **Resolved as far as this story may resolve it:** the **substantive requirement** is already approved and recorded — PostgreSQL CI is its own story, is Backlog, and must land before `PF-080`. **Assigning it the identifier `PF-033` is a tracking-file change**, and this story is explicitly barred from modifying `docs/implementation/01_Implementation_Sprint_Plan.md` and `docs/implementation/03_Engineering_Backlog.md`. It is recorded as an open item in §22 for the deferred tracking-file synchronization. **This document does not create, rename, or renumber the story.**

**21.11 `phpunit.xml` runs SQLite versus PostgreSQL being authoritative.** **No conflict to resolve here — it is an obligation, not a contradiction.** §16 records why the current configuration cannot verify any isolation control, and the approved ordering constraint that PostgreSQL CI lands before `PF-080`. **This document changes no test configuration.**

**21.12 The Laravel skeleton `users` table versus IdentityAccess owning principals.** **Resolved:** `users`, `cache`, and `jobs` are framework starter artefacts, not approved domain schema, and a stock `users` table is not the principal model (Constitution Article 26). Their fate is IdentityAccess's own approved story's decision and is **not decided here** (§5.1).

**21.13 "Documents owns canonical content" versus storing anything document-shaped.** **Resolved:** the database stores a storage reference, checksum, media type, and byte size — **never bytes** (§7.3). Release 0.1 has no document capability at all, so no such relation exists yet.

**21.14 Backup retention versus deletion, retention, and legal hold.** **Not resolved, and deliberately so.** It is a legal question requiring Thai-qualified review and its own approved policy. §17.5 and §20.18 record it as open; **no legal conclusion is asserted** (§22).

---

## 22. Unresolved and deferred items

Recorded openly rather than presented as settled. Each requires its own decision by the named owner.

| Item | Owner | Note |
|---|---|---|
| **PostgreSQL CI story identifier** (`PF-033`) | Tracking-file synchronization after `PF-040` merges | The requirement is approved; the identifier is not recorded in the repository (§21.10) |
| **Whether `firm_id` carries a foreign key to `Firm`** | The implementing story for `PF-080` / `PlatformAdministration` | §8.3 states the reasoning both ways and requires an explicit recorded decision |
| **Outbox publication strategy — per-Firm claiming or the bounded cross-Firm exception** | `PF-091`, `PF-092` | §13.6, §21.3; the sequence-visibility hazard in §13.5 constrains the choice |
| **Idempotency retention window** | The implementing story | §14.3; a storage-versus-coverage trade-off that cannot be asserted here |
| **Thai collation and normalization decisions** | The Thai-text-correctness story | §9.4; that it is explicit is required, which value is chosen is not decided here |
| **PostgreSQL minimum version, extension set, and server configuration** | Deployment architecture | §19; must satisfy the capability requirements recorded here (forced RLS, transaction-scoped settings, native `uuid`, `timestamptz`, partial unique indexes, `SKIP LOCKED`, locale/ICU collation) |
| **Connection pooling topology** | Deployment architecture | §4.3 records the incompatibility with statement-level multiplexing as a constraint; no product is selected |
| **Backup, PITR, encryption-at-rest, and key-management mechanisms** | Deployment architecture | §17; no product or provider selected; the restore test is unexecuted |
| **Audit purge and partition-detachment policy** | Its own separately approved decision | §12.7; not designed here |
| **Backup retention versus deletion obligations and legal hold** | Thai-qualified legal review and an approved policy | §17.5, §21.14; **no legal conclusion asserted** |
| **Whether per-module PostgreSQL schemas are adopted later** | Its own separately approved decision | §5.2; additive, not foreclosed |
| **Stale cross-references to this file as an "empty placeholder"** | Tracking-file synchronization after `PF-040` merges | `docs/README.md`, `docs/architecture/02_Product_Requirements.md` §8 and §10, `docs/architecture/08_Roadmap.md`, `docs/implementation/03_Engineering_Backlog.md`, and `docs/adr/ADR-012-…` describe this file as empty. Statements of the form "not populated by ARCH-011" remain literally true; the bare "empty placeholder" descriptions become stale on approval. This story is barred from editing those files. |

---

## 23. Invariants

- Every relation is explicitly either Firm-scoped or platform-global. There is no third class.
- Every Firm-scoped relation carries `firm_id uuid NOT NULL`, immutable after insert.
- No Firm-scoped relation has a nullable `firm_id`, a sentinel Firm, or a shared-Firm row.
- Every Firm-scoped relation has Row-Level Security **enabled and forced**, with a policy carrying both `USING` and `WITH CHECK`.
- A row policy predicate carries Firm identity only — never entitlement, capability, role, ownership, or wall state.
- Unset, empty, or malformed Firm context fails closed and fails loudly.
- Firm context is transaction-scoped, set only from a `FirmContext` built from verified identity and membership, and never session-level.
- One transaction never spans two Firms, and Firm context never changes mid-transaction.
- The application runtime role is not a superuser, not a relation owner, and holds no `BYPASSRLS`.
- Row-Level Security is defence in depth and never the primary control; application and repository scoping are never omitted because it exists.
- Identifiers are application-generated UUIDv7 in native `uuid` columns; no database-generated default exists.
- A UUIDv7 is never an ordering, deduplication, or idempotency key.
- Primary keys are `id` alone; every Firm-scoped relation also carries `UNIQUE (firm_id, id)`; intra-context foreign keys are composite and carry `firm_id`.
- No business or natural key is a primary key.
- Cross-bounded-context references are identifier-only, with no foreign key, no join, and no cross-context write.
- Every uniqueness constraint on Firm-scoped data is Firm-scoped.
- Every index on a Firm-scoped relation includes `firm_id`, and no index exists to serve a cross-Firm query.
- All timestamps are `timestamptz` in UTC, sourced from the injected `Clock`; the five distinct times are never collapsed.
- Destructive cascades are denied by default and require explicit recorded approval; no approval can authorize a cascade that removes audit, ledger, version, attestation, outbox, or event history.
- Deleting content never destroys the audit fact that it existed.
- The state change, its outbox row, and its audit row commit atomically or not at all.
- No external call and no waiting occurs inside a database transaction.
- Authoritative audit is persisted, append-only, enforced by both revoked privileges and a rejecting trigger, writable while its subject is being denied, and never editable by the actor it records.
- Audit streams stay distinct and are never merged into one platform table.
- Outbox ordering, where offered, is per-subject and derived from an explicit sequence — never from an identifier or a wall clock — and never claims global ordering or exactly-once delivery.
- An idempotency record is enforced by a unique constraint on its scoped key, stores a fingerprint, and never returns a stored result without re-evaluating current authorization.
- Entitlement is never a per-request database check.
- No Ethical Wall, conflict-checking, or per-user Matter-visibility mechanism exists in any persistence layer.
- Production migrations are forward-only, expand/contract, never edited after shipping, and never run as the application runtime role.
- Row policies, roles, and grants change only through reviewed migrations and hit the authorization approval gate.
- Firm-spanning data maintenance never disables Row-Level Security or grants `BYPASSRLS`.
- No authoritative data lives in an `UNLOGGED` relation.
- Isolation tests run on PostgreSQL, as the application runtime role, on synthetic data only.
- No cross-Firm view, materialized view, projection, index, cache, report, or read role exists.
- AI holds no authority over any schema, policy, role, grant, migration, audit record, or destructive operation.

---

## 24. Conceptual placement

Conceptual only. **No directory, file, migration, or configuration is created by this story.**

```text
app/Foundation/                     (platform technical primitives — not a bounded context)
├── Domain/                         (PF-041..PF-049: Entity, ValueObject, DomainEvent,
│                                    BusinessIdentifier, Clock, UuidV7, exceptions — Done)
└── Persistence/                    (conceptual: tenancy context contract, transaction
                                     manager PF-073, outbox contract PF-091,
                                     idempotency contract — none implemented)

app/Modules/<Context>/
├── Database/                       (this context's migrations — and only this context's)
├── Infrastructure/                 (Eloquent persistence records, repository adapters,
│                                    outbox integration through Foundation's contract)
└── Domain/                         (aggregates and invariants — never Eloquent, never SQL)

Platform infrastructure relations (Platform Foundation-owned): outbox, idempotency.
Domain relations (module-owned): everything else.
Platform-global relations (Constitution Article 5): reference data, no firm_id.
```

Unchanged from `docs/domain/06_Laravel_Module_Blueprint.md`: dependency direction is `Interface → Application → Domain`; Infrastructure depends on Application and Domain contracts; **the Domain layer never depends on Laravel, Eloquent, SQL, queues, HTTP, or SDKs**; Eloquent records handle mapping, casts, relationships, and scopes only, and are persistence models rather than domain aggregates.

---

## 25. Proposed implementation stages

**Proposed only. None of these is approved, scheduled, or assigned a story identifier**, and each requires its own entry in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`, with its own Definition of Ready, before implementation begins. **`PF-040` remains the next code story and remains Backlog.**

1. **PostgreSQL continuous integration** — the suite running against PostgreSQL, as the application runtime role, with the four required check names preserved exactly. **Must land before `PF-080`** (§16.1). Already an approved Backlog story; not created or renumbered here.
2. **Tenancy persistence conventions** — the baseline column set, the two relation classes, `timestamptz`/`Clock` discipline, and the schema-level guard test asserting forced Row-Level Security and a policy on every Firm-scoped relation.
3. **Firm context and roles** — the transaction-scoped `SET LOCAL` contract, the fail-closed context function, the role and privilege separation, and their tests. Depends on `PF-080`.
4. **Append-only audit persistence** — the privilege and trigger enforcement, and its tests.
5. **Transactional outbox persistence** — `PF-091`, including the ordering and publication-strategy decision recorded in §13.5, §13.6, and §22.
6. **Idempotency persistence** — the scoped unique constraint, fingerprint, re-authorization-on-replay behaviour, and retention window.
7. **Migration and evolution tooling discipline** — the forward-only, expand/contract, and drift-detection practices, plus the per-Firm maintenance path in §15.5.

---

## 26. Relationship to other documents

| Document | Relationship |
|---|---|
| `docs/architecture/01_OneLegalPro_Constitution.md` | Constitutional; prevails over this document in every case |
| `docs/adr/ADR-016-Tenant-Isolation-Model.md` | The tenancy decision this document implements (§2, §3, §4) |
| `docs/adr/ADR-017-Identifier-Persistence-Strategy.md` | The identifier decision this document implements (§6) |
| `docs/adr/ADR-018-Audit-Persistence-and-Append-Only-Enforcement.md` | The audit decision this document implements (§12) |
| `docs/adr/ADR-019-Transactional-Outbox-Persistence.md` | The outbox decision this document implements (§13) |
| `docs/adr/ADR-020-Migration-Rollback-and-Schema-Evolution.md` | The evolution decision this document implements (§15) |
| `docs/architecture/04_Security_Architecture.md` | Platform-wide security baseline; this document is its persistence expression |
| `docs/architecture/07_API_Standards.md` | Idempotency scoping (§10) and delivery semantics (§12) this document persists |
| `docs/domain/06_Laravel_Module_Blueprint.md` | Module structure, tenancy discipline, atomic outbox commit, prohibited patterns |
| `docs/architecture/02_Product_Requirements.md` | Release 0.1 scope, non-negotiables, and the production-access evidence list |
| `docs/architecture/19_Platform_Administration_Architecture.md` | Owns `Firm`, whose identity `firm_id` carries |
| `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` | Owns principals, membership, sessions, and the security event stream |
| `docs/architecture/13_Practice_Management_Architecture.md` | Owns `Matter`, `MatterNumber`, and — when built — Ethical Walls |
| `docs/architecture/14_Document_Knowledge_Management_Architecture.md` | Owns canonical document content; the database holds references, never bytes |
| `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` | Owns ledgers, posted immutability, and derived balances |
| `docs/architecture/17_API_Integration_Platform_Architecture.md` | Owns external contracts and `IntegrationEventEnvelope`; internal events are not external contracts |
| `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md` | Owns orchestration state; AI holds no authority here |
| `docs/architecture/05_AI_Architecture.md` | **Unmodified by this story** |
| `docs/adr/ADR-012-…` through `ADR-015-…` | Release 0.1 scope, `Firm` ownership, operator access, and deferred controls — all unchanged |
| `AGENTS.md` | PostgreSQL, UUIDv7, never edit historical migrations, and the approval gates this document repeatedly invokes |
| `docs/implementation/01_Implementation_Sprint_Plan.md`, `docs/implementation/03_Engineering_Backlog.md`, `docs/PROJECT_STATUS.md`, `docs/README.md` | **Deliberately unmodified by this story**; their synchronization is deferred (§22) |
