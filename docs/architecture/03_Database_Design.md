# ARCH-012 — Data & Persistence Architecture

**Status:** **Approved.** Explicit repository-owner approval, including approval of the separately recorded Thai legal-review decisions, was recorded on PR #34 on 1 August 2026. This approval accepts the architectural decisions only: nothing here is thereby scheduled, implemented, deployed, production-ready, certified, or authorized for production access. No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled by this document, and **no story's status is asserted here — `docs/PROJECT_STATUS.md` is the authoritative record of what is current, next, and complete.**

**Document numbering.** The story ID is **ARCH-012**. Unlike ARCH-006 through ARCH-011, which each created the next sequential architecture document, this story populates the **pre-reserved placeholder `docs/architecture/03_Database_Design.md`**; no new document number is allocated. The file's name is retained unchanged; its subject is the platform's data and persistence architecture, of which database design is the largest part.

**No capability, control, or property described in this document is claimed to be implemented.** No production-readiness, certification, compliance, or legal claim is made anywhere in it.

---

## 1. Scope, authority, and relationship to existing architecture

### 1.1 What this document is

This is the **platform-wide data and persistence baseline**, binding every bounded context, on the same footing as `docs/architecture/04_Security_Architecture.md` (security) and `docs/architecture/07_API_Standards.md` (external contracts). It owns no domain data and defines no bounded context. It defines **how** approved domain models are persisted in PostgreSQL, and **which persistence patterns are required, permitted, and prohibited**.

**In scope:** the shared-schema tenancy model; the four relation classes; mandatory `firm_id` scoping and PostgreSQL Row-Level Security as defence in depth; connection roles and transaction-scoped Firm context; module schema and migration ownership; identifier persistence and UUIDv7 rules; baseline columns, time representation, and concurrency control; referential integrity and cross-bounded-context reference rules; indexes, Firm-scoped uniqueness, text encoding and collation; destructive-cascade policy; transaction boundaries and locking; append-only audit persistence; transactional-outbox persistence; idempotency persistence; migration and schema-evolution policy; PostgreSQL testing obligations; the backup and restore properties that constrain persistence design; prohibited patterns; non-goals; and the security and professional-responsibility consequences of all of the above.

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
| `docs/domain/06_Laravel_Module_Blueprint.md` | Tenant isolation enforced in application logic, repositories, **"database policy where appropriate"**, and tests — **global scopes alone are insufficient**; state changes and outbox records commit atomically; unapproved destructive cascades are a prohibited pattern |
| `docs/architecture/04_Security_Architecture.md` §5, §7, §8 | Firm isolation, encryption, environment separation, data classification, backup integrity, fail-closed behaviour |
| `docs/architecture/07_API_Standards.md` §10, §12 | Idempotency scoping and re-authorization on replay; at-least-once delivery |
| `AGENTS.md` | PostgreSQL and UUIDv7; never edit historical migrations; approval gates for database redesign, authorization changes, and destructive operations |
| `docs/architecture/02_Product_Requirements.md` §8, §9 | Firm isolation enforced in database policy; Thai text correctness; an **executed** restore test as production-access evidence; PostgreSQL CI before `PF-080` |
| `app/Foundation` (`PF-042`, `PF-043`, `PF-044`, `PF-047`, `PF-048`, `PF-049`) | `ValueObject`, `DomainEvent`, `BusinessIdentifier`, `Clock`, `UuidV7`, exception taxonomy — consumed, never duplicated. See `docs/PROJECT_STATUS.md` for each story's status. |

### 1.4 What this document does not unblock

Approving this document would satisfy **one** of the production-access evidence items listed in `docs/architecture/02_Product_Requirements.md` §8 — "an approved database design". It would not satisfy any other. An approved deployment architecture, an **executed and recorded** restore test, operational monitoring, a documented incident procedure, the follow-up policies and evidence required by `docs/legal/ARCH-012-Thai-Legal-Review.md`, and every applicable `AGENTS.md` approval gate all remain outstanding and are not addressed here. **The eight legal questions raised by this document were reviewed and approved at the decision-principle level on 1 August 2026; §22.2 records their disposition without claiming implementation or production readiness.**

---

## 2. Shared-schema tenancy model

### 2.1 The decision

**One logical PostgreSQL database, one shared set of relations, with every Firm's rows co-resident and separated by a mandatory `firm_id` column.** There is no schema-per-Firm and no database-per-Firm. See `docs/adr/ADR-016-Tenant-Isolation-Model.md`.

### 2.2 Why

**Constitution Article 5 does not compel any physical storage model.** It is an *ownership and isolation* rule: platform-global legal reference data must not use `FirmContext` as its ownership boundary, and Firm-owned data must be strictly isolated by it. Several physical models can satisfy it, and this document does not claim otherwise. In particular, **database-per-Firm with a separate platform-reference database would satisfy Article 5** — the reference data would exist once, in its own database, owned by nobody's `FirmContext`, with Firm-owned annotations over it living in each Firm's own database and referencing it by identifier. **That model is rejected for operational reasons, not constitutional ones:**

- **Migration fan-out multiplies the highest-risk operation on the platform.** Expand/contract migration (§15) applied across N databases or schemas produces N partial-failure states and N drift surfaces. A single shared schema has one migration state, verifiable in one place.
- **Connection and cache multiplication.** Per-Firm databases or schemas multiply connections, prepared-statement caches, and pooler slots by tenant count, and make a shared query-planner cache impossible.
- **Cross-database reference cost.** Firm annotations over platform-reference data would span two databases, so referential integrity, joins, and transactional consistency between them would all become application concerns — the same "identifier-only, validated by the owner" discipline §8.2 already applies across bounded contexts, but imposed on every reference-data read.
- **Restore is a whole-database operation in either model.** Physical recovery does not become per-Firm merely because databases are separate for *other* reasons; a per-Firm restore still requires logical export and import (§17.2).
- **Fit to the current stage.** Database-per-Firm's genuine benefit — the strongest available physical isolation — is real and would be the right answer for a small number of very large tenants. It is the wrong answer for a founding-firm pilot expected to onboard many small firms, where the operational surface would dominate.

### 2.3 The cost, stated plainly

**In a shared schema, a single missing `firm_id` predicate is a cross-Firm disclosure.** For a legal-practice platform, that is a **security and confidentiality incident requiring legal assessment**, not a data-quality defect: it exposes information about parties who never consented to the exposure, and the parties on either side of the boundary may be adverse to one another.

`docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 4 classifies a confirmed disclosure as a high-severity security and confidentiality incident requiring immediate containment, evidence preservation, Thai-qualified assessment, and a recorded decision on affected persons, professional-conduct consequences, and notification. **No universal notification outcome or timeline is asserted** (§17.5, §22).

That cost is the entire justification for the four-layer isolation model in §3: **application logic, repositories, PostgreSQL policy, and tests, each independently constraining the same mistake.** A per-Firm physical model would make that mistake harder to make but would not remove it — a query executed against the wrong database or schema search path is the same failure — and would replace it with a migration and operations surface that is harder to verify.

**Revisiting this decision is a database redesign** under the `AGENTS.md` approval gate, not an implementation choice.

### 2.4 Four relation classes, explicitly classified

Every relation is **exactly one** of the following, declared explicitly in the migration that creates it and in the owning module's documentation. **The taxonomy is exhaustive: there is no fifth class and no "sometimes-scoped" relation**, and a relation never changes class silently.

| Class | Definition | `firm_id` | Row-Level Security |
|---|---|---|---|
| **a. Firm-scoped** | Holds data belonging to exactly one Firm | `uuid NOT NULL`, immutable | **Forced Firm RLS** (§3); accessible **only within a verified `FirmContext`** |
| **b. Firm-identifying** | **The Firm registry only** — the relation whose rows *are* Firms. Exactly one row per Firm | **Absent** — a Firm's own identity is its primary key, not a tenancy column | **Not `FirmContext`-based** — it must be consulted before `FirmContext` exists (§2.5) |
| **c. Platform-global reference** | Constitution Article 5 authoritative platform-global reference and configuration data — official legal sources, reference translations, seeded taxonomies, scheme templates | **Absent — never nullable, never a sentinel** | Not Firm-scoped; read-only to the application runtime role |
| **d. Platform-realm security / operations** | Records that **must exist when no verified `FirmContext` exists** — presently the pre-authentication IdentityAccess security-event relation, and nothing else | **Absent — and no candidate Firm identifier is ever stored as `firm_id`** | Not `FirmContext`-based; access restricted to named platform-realm services and security operators (§12.4) |

**Class (d) is not platform-global reference data**, and the two are never conflated. Class (c) is *authoritative reference material that exists once for every Firm* and is read on ordinary Firm-facing paths; class (d) is *operational security recording that exists because no Firm is verified*, is never Firm-facing, and is never read as reference data by anything. Placing (d) inside (c) would make an unauthenticated stranger's failed attempt look like platform reference content and would put it on paths that legitimately serve Firms.

**Class (b) — Firm-identifying: deliberately narrow**

Tenant resolution and per-Firm background work face a genuine ordering problem: to establish Firm context you must first know which Firms exist, and a relation readable only *with* Firm context could never answer that. Rather than resolving it with a `BYPASSRLS` grant, a permissive policy, or a nullable `firm_id`, the registry is named as its own class with explicit limits. Its access posture is stated in full in §2.5.

- **The class contains exactly one relation: the Firm registry**, owned by `PlatformAdministration` (`docs/architecture/19_Platform_Administration_Architecture.md` §6). **Nothing else is ever placed in this class**, and adding a second relation to it requires its own approved decision.
- **It carries identity, canonical name, jurisdiction reference, and lifecycle metadata only** — exactly as ARCH-019 §6 defines. **It carries no Firm business data of any kind**: no `Client`, `Matter`, `Task`, document, communication, or financial content, and no field from which any could be reconstructed. **Firm-sensitive business data is never added to it** (§2.5).
- **It is never a cross-Firm bridge.** No query joins it to reach one Firm's rows from another Firm's context, and no projection, view, index, cache, or report derives cross-Firm content through it.
- **It is usable before `FirmContext` exists only by (a) the tenant resolver and (b) narrowly authorized `PlatformAdministration` paths.** No domain module reads it pre-context, and no Firm-facing surface enumerates it.
- **Enumerating Firms is not a capability granted to anything Firm-facing.** A Firm-facing caller never receives a list, count, or existence signal about another Firm, and `FirmContext` is still built only from verified identity and membership (Constitution Article 27) — registry readability is not membership and proves nothing.
- **Per-Firm outbox claiming reads the registry** to iterate Firms, and nothing more (§13.6).

**Class (c) — Platform-global reference rules**

- It **never** carries a nullable `firm_id`, a zero/sentinel UUID, or a "shared Firm" row. A nullable tenancy column is the single most common way a shared-schema isolation model fails, because every policy and predicate then needs an `OR firm_id IS NULL` branch that is trivially wrong.
- It is **not Firm-owned and is never treated as Firm-scoped data.** It is **written only by an authorized platform data-management path** and is read-only to the application runtime role. Firm-owned annotations, bookmarks, notes, and saved research **over** it are Firm-scoped relations referencing the global row by identifier (Article 5).
- A join between a Firm-scoped relation and a platform-global reference relation is permitted; a join that would place another Firm's row on either side is not, and **no query uses it as a bridge between two Firms**.

**Class (d) — Platform-realm security / operations rules**

- **The class is narrowly limited to records that must exist when a verified `FirmContext` does not exist.** Presently that is the **pre-authentication IdentityAccess security-event relation and nothing else** (§12.4).
- **No candidate Firm identifier is ever stored as `firm_id`** — a hostname, custom domain, header, parameter, route segment, submitted email address, or claimed Firm identifier identifies a *candidate* and proves nothing (Constitution Article 27). Where a candidate value must be recorded, it goes in a clearly named candidate/unverified field.
- **Never Firm-visible.** No Firm-facing surface, Firm-visible support-access history, Firm-scoped export, or Firm-scoped query reads it.
- **Never a cross-Firm bridge**, and never a source for any cross-Firm projection, aggregate, or report.
- **Access is restricted to named platform-realm services and security operators**, by dedicated database privileges and narrow query contracts — never to the Firm-scoped application runtime role.
- **Append-only requirements continue to apply in full** to the security-event relation: `UPDATE`, `DELETE`, and `TRUNCATE` each independently rejected by privilege and by trigger (§12.2).
- **Adding another relation to this class requires architecture and security approval.** The class exists to hold a named exception, not to become a home for anything inconvenient to scope.

**Reclassifying a relation between any two classes is a database redesign and a security change**, requiring both approval gates.

### 2.5 The Firm registry's access posture — why it is not `FirmContext`-based, and why that is not open access

**The Firm-identifying registry cannot use `FirmContext`-based Row-Level Security**, because it is the relation consulted **in order to establish** `FirmContext`. A policy predicate comparing a registry row to the current Firm context would either deny every pre-context read — making tenant resolution impossible — or would have to be written permissively, which is worse than having no policy because it would look like a control.

**That is not unrestricted access, and it must not be read as one.** Access is enforced by three mechanisms that do not depend on Firm context:

1. **Dedicated database privileges.** Only the roles that genuinely need the registry hold `SELECT` on it. **The Firm-scoped application runtime role receives no general enumeration access.**
2. **Narrow repository and query contracts.** The registry is reachable only through named contracts that return the specific fields tenant resolution or an authorized `PlatformAdministration` path requires — never a general "list Firms" query available to arbitrary callers.
3. **Application authorization.** Every `PlatformAdministration` path that reads it composes its own authorization first, exactly as any other protected read does (Constitution Article 28).

**Additional standing rules**

- **Only the tenant resolver and narrowly authorized `PlatformAdministration` paths may read the required fields.** Nothing else reads the registry pre-context.
- **Enumeration and returned fields are minimized**, and **audited where required** — a read that returns more Firms or more fields than the caller's purpose needs is a defect, not an optimization.
- **Firm-sensitive business data is never added to this registry**, which is what keeps the consequence of a registry read bounded to "this Firm exists" rather than anything about the Firm's clients or matters.
- **A Firm identifier is not a secret and not a capability** (ARCH-019 §16). Registry readability is **not membership** and grants nothing; `FirmContext` is still built only from verified identity and membership.
- **No `BYPASSRLS`, superuser access, owner exemption, or permissive cross-Firm policy is introduced for any Firm-scoped relation** to make registry access work — the whole point of naming class (b) is that none of those is needed.

---

## 3. Firm-scoping and Row-Level Security policy

### 3.1 Four layers, each independently sufficient

`docs/domain/06_Laravel_Module_Blueprint.md` requires tenant isolation "enforced in application logic, repositories, **database policy where appropriate**, and tests", and states that **global scopes alone are insufficient**. This document decides that, for every Firm-scoped relation, database policy **is** appropriate, and makes each layer explicit with a distinct failure mode:

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

### 3.4 Unset Firm context fails closed — enforced before the first statement

**When the Firm context is unset, empty, or unparseable, every access to a Firm-scoped relation fails.** It does not return all rows, and it does not return zero rows silently.

**The primary enforcement point is the application, before the first statement of the transaction.** `PF-073` (Transaction Manager) and `PF-082` (Tenant Middleware) establish Firm context as part of opening the transaction and **raise before any statement touching a Firm-scoped relation is issued** if context is absent, empty, or unparseable. That is where the fail-loud guarantee lives, because it is the only place that holds unconditionally.

**A raising policy predicate is defence in depth, and it cannot be relied on as the guarantee.** Two limits, stated plainly:

- **A row policy is only evaluated when rows are examined.** On an **empty relation** — a new deployment, a new Firm, a relation not yet populated — a `SELECT` may return zero rows without the predicate ever being evaluated, so a missing-context defect produces a plausible empty result and no error. The application-side check has no such gap.
- Predicate evaluation is a planner and execution concern, not a contract. Designing the fail-loud property around it would make correctness depend on whether any row happened to be examined.

**Therefore both are required, and their roles are not interchangeable:**

- The policy predicate resolves Firm context through a function that **raises** when the setting is absent, empty, or unparseable, rather than through a bare `current_setting(..., true)` returning `NULL`. A `NULL` comparison yields no rows, which is safe for reads but hides the defect and turns a write into a confusing policy violation rather than the true cause. This catches statements that reached the database despite the application-side check being bypassed.
- **The application-side pre-statement check is what guarantees the failure occurs at all**, including on empty relations.

**Testing obligation:** the fail-closed behaviour is tested on **both an empty and a populated relation** (§16.3). An empty-relation test is what proves the guarantee does not depend on rows existing; a populated-relation test is what proves the policy predicate is genuinely raising rather than quietly filtering.

- **A silent empty result is specifically rejected as a design.** It converts a missing-context defect into a plausible-looking "no records found" screen, which in a legal practice is a professionally dangerous outcome — a lawyer told a Matter has no tasks behaves very differently from a lawyer told the system failed.
- **Reads are not exempt.** §4.4 requires every statement touching a Firm-scoped relation to run inside a transaction with context established.
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
| **Application runtime role** | `SELECT`, `INSERT`, `UPDATE`, `DELETE` as each relation's rules permit | **Not the owner, not a superuser, no `BYPASSRLS`, no DDL.** Holds no `UPDATE`, `DELETE`, or `TRUNCATE` on append-only relations (§12.2). Read-only on platform-global relations (§2.4). |
| **Outbox publication role** | `SELECT` and bookkeeping `UPDATE` on the outbox relation only, plus `SELECT` on the Firm registry to iterate Firms | **No access to any business relation.** **Subject to forced Row-Level Security on the outbox, with no exemption**: it claims per-Firm under established Firm context (§13.6). **No `BYPASSRLS`, no superuser, no owner exemption, and no role-based permissive outbox policy.** |
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
- **Platform Foundation owns platform infrastructure relations** — the transactional outbox (§13), idempotency records (§14), and framework-operational tables. Foundation is `app/Foundation`: a layer of shared technical primitives, **not a bounded context**. A module writing an outbox row through Foundation's published contract, inside its own transaction, is **not** a cross-module table write (§21.2). **Foundation owning the relation is not Foundation owning every decision about it:** the outbox persistence decision is `docs/adr/ADR-019-Transactional-Outbox-Persistence.md`'s, the idempotency persistence decision is `docs/adr/ADR-021-Idempotency-Persistence.md`'s, and the idempotency **scoping contract** remains `docs/architecture/07_API_Standards.md` §10's.
- **The existing Laravel skeleton relations are framework starter artefacts, not approved domain schema**, and ownership of the decision about them splits:
  - **IdentityAccess owns `users` and identity records** — and only those. IdentityAccess owns principals, credentials, and sessions (Constitution Article 26); a stock `users` table is not the principal model. Whether it is retained, replaced, or removed is IdentityAccess's own approved story's decision.
  - **The Platform Foundation runtime owns decisions about the framework cache and queue/job infrastructure relations (`cache`, `jobs`).** They are **not** identity concerns, and **assigning them to IdentityAccess would misattribute a runtime concern to the identity context.**
  - **Every existing skeleton relation remains unapproved until its owning story decides it.** None is decided here, and **this story modifies none of them.**

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
- **One format, one validator.** `PF-048` already owns strict UUIDv7 validation and the canonical lowercase **text** representation; a second generation path would be a second format authority.

**On representation, precisely:** a native `uuid` column has **no lowercase or uppercase storage form**. PostgreSQL stores a UUID as sixteen bytes and normalizes case on input, so `PF-048`'s canonical-lowercase rule is a **textual-boundary** concern — how an identifier is rendered, logged, serialized, compared as text, and emitted in an event payload — not a property of the stored column. Text-form canonicalization therefore remains an application obligation at every boundary where an identifier becomes text, and the column type makes case-sensitivity bugs in the database impossible rather than merely unlikely.

### 6.2 UUIDv7 is not an ordering guarantee

`PF-048` records that a UUIDv7 is **time-sortable** and that this is the only term used for it. This document states the persistence consequence directly:

**A UUIDv7 is never an ordering key.** Specifically:

- Identifiers generated within the same millisecond have **arbitrary relative order**. `PF-048` deliberately declined library monotonic-counter behaviour, which in any case holds only within a single process.
- The generating clock is a wall clock that may be corrected **backwards** (`PF-047`), so a later identifier may sort earlier.
- Multiple application processes generate concurrently with no coordination.
- Commit order is not generation order. A row with a lower identifier can become visible after a row with a higher one (§13.5).

Therefore, prohibited: **an identifier as a business, chronological, causal, event, or delivery ordering key**; **identifier-only ordering and an identifier-only cursor**, including a pagination cursor that implies time order; and any ordering, deduplication, or idempotency key derived from an identifier's timestamp bits. Where ordering matters, it is explicit and per-subject (§13.5).

**One permitted use: the final deterministic tiebreaker.** After an **explicit approved business sort key** — a status, a due date, a name, a domain-assigned sequence — an identifier **may** be appended as the last sort term to make the total order deterministic and keyset pagination stable. This is legitimate precisely because it carries no meaning: it breaks ties arbitrarily but *repeatably*, which is what a stable cursor needs. It is permitted only in that position, only after a business key, and it **never** becomes the primary sort term, never implies chronology, and never substitutes for the explicit ordering §13.5 requires for delivery.

**What is retained is the index-locality benefit**: a time-prefixed identifier gives B-tree inserts far better locality than a random v4, reducing page splits and index bloat. That is a physical property, not a semantic promise. **It is the justification for preferring UUIDv7 over UUIDv4 — it is not the reason UUIDv7 is mandated.** UUIDv7 is mandated by `AGENTS.md` and by the Foundation primitive `PF-048`; locality is why that mandate is also the technically preferable choice, and if the mandate did not exist the locality argument alone would still favour it.

### 6.3 Primary keys and Firm-scoped referential identity

- **The primary key of a Firm-scoped relation is `id uuid` alone.** Identifiers are globally unique by construction, so a composite primary key buys nothing for identity.
- **Every Firm-scoped relation additionally carries `UNIQUE (firm_id, id)`.**
- **Every intra-context foreign key is composite**, referencing `(firm_id, id)` and carrying the referencing row's own `firm_id` as the first column.

The reason for the composite foreign key is worth stating: it makes **"the row I reference belongs to my Firm"** a fact the database enforces, rather than a convention the application maintains. A single-column foreign key to `id` permits a row in Firm A to reference a row in Firm B — a cross-Firm link that Row-Level Security does not catch, because each row individually satisfies its own policy and, additionally, because referential-integrity checks do not evaluate row policies at all (§8.5). That is exactly the class of defect a legal-practice platform cannot absorb.

**The cost, stated accurately:**

- One additional `UNIQUE (firm_id, id)` index per Firm-scoped relation, and wider foreign-key columns.
- **Child-side indexes are a separate, additional cost.** PostgreSQL automatically indexes the **referenced** (parent) side of a foreign key, because the constraint requires a unique index there — it does **not** automatically create any index on the **referencing** (child) columns. A composite foreign key therefore leaves the child side unindexed unless the owning story adds an index explicitly, and without one, parent-side `DELETE` and any `UPDATE` of the referenced key perform a sequential scan of the child relation to check the constraint. **Where a child-side composite index is needed — for constraint-check performance, for join performance, or for the Firm-leading access pattern in §9.1 — the owning story creates it explicitly and does not assume the foreign key provided it.**

Both costs are accepted deliberately. Neither is nil, and neither is created for free by declaring the constraint.

**Explicit exception — the `id`-only primary key.** The primary key index is `(id)` and therefore does **not** lead with `firm_id`, and `id` is **globally unique across Firms**. This is a deliberate, narrow exception to two rules stated elsewhere in this document, and it is safe for a reason that does not generalize:

- It is an exception to the **Firm-scoped-uniqueness rule** (§9.2), which exists because a global unique constraint on a **business attribute** discloses another Firm's row through a constraint error.
- It is an exception to the **index rule** (§9.1) requiring `firm_id` to lead.
- **Why the disclosure argument does not apply:** the primary key holds no business value. A collision would require two independently generated UUIDv7 values to be identical, which reveals **nothing about any business attribute of any Firm** — a caller learns only that a randomly generated 122-bit value collided, which conveys no client name, matter reference, email address, or other fact a Firm could act on or infer from. The value is generated by the application (§6.1), not chosen or supplied by a caller, so a caller cannot probe for the existence of a *particular* identifier by attempting to insert it in the ordinary course; and a collision is, in practice, a defect report rather than an information channel. Contrast a global unique constraint on a client name, where the violation tells the caller exactly which meaningful value another Firm holds.
- **The exception is limited to identifier columns generated by the platform.** It never extends to a business attribute, a human-readable reference, or any caller-supplied value, and §9.2's prohibition applies to all of those without exception.

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

Every **Firm-scoped append-only** relation (audit, ledger entries, event records) carries `id`, `firm_id`, and its own recorded-at column, and **has no `updated_at`**, because it is never updated (§12). The class (d) platform-realm pre-authentication security-event relation is the narrow exception: it has no verified Firm context and therefore no `firm_id`, but remains append-only and has no `updated_at` (§12.4).

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
- **No monetary column exists.** `PF-045` `Money` is deferred from Release 0.1 (`docs/architecture/02_Product_Requirements.md` §3; `docs/PROJECT_STATUS.md` is authoritative on its current status), and §3 of that document states the application never collects, calculates, stores, transmits, or displays an amount. As a **forward constraint**: when monetary persistence exists, it is exact decimal (`numeric`) with an explicit currency, owned by `PF-045` and Billing; **floating-point money is prohibited platform-wide** (Constitution Article 23), and no second money type may be introduced.
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
- **Deleting content never destroys the audit fact that the record existed** (Articles 19, 23; §12.7).

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

### 8.4 Platform-global and Firm-identifying references

A reference to platform-global data — a `LegalSource` version, a seeded taxonomy entry — is an identifier reference to a relation that carries no `firm_id`. A foreign key is permissible here in principle, since the referenced relation is not another Firm's data and not another Firm-scoped context's; whether one is used is the referencing context's decision, and it never makes a Firm-scoped row's visibility depend on a global row.

A reference to the **Firm-identifying** registry is the `firm_id` column itself, governed by §8.3.

### 8.5 Referential-integrity checks bypass Row-Level Security

**This is a property of PostgreSQL that the design must be built around, not a detail an implementer can discover later.**

**Foreign-key and unique-constraint enforcement is performed by the system with row security suspended.** A constraint check must be able to see rows the current caller cannot, or the constraint would mean something different for every caller and could be defeated simply by lacking visibility. The consequences are direct and cut both ways:

**Why a single-column foreign key is not saved by Row-Level Security.** A row in Firm A referencing `id` of a row in Firm B would be **accepted**: the constraint check sees the parent row regardless of the caller's Firm context, because policies are not applied during the check. Row-Level Security never gets the chance to object, and the resulting cross-Firm link is then invisible in ordinary querying — each row individually satisfies its own policy. **A row policy is therefore not a defence against cross-Firm referencing at all**, and believing otherwise is the specific misconception this subsection exists to prevent.

**Why composite Firm-carrying foreign keys do work.** A composite key referencing `(firm_id, id)` and carrying the referencing row's own `firm_id` makes same-Firm referencing a **structural** property of the key rather than a visibility question. The child row's `firm_id` is the same column the row policy constrains on insert (`WITH CHECK`) and the same column that is immutable after insert (§3.2). For the reference to point into another Firm, the child row would have to carry that other Firm's `firm_id` — which the policy rejects on write. The guarantee comes from **the key's shape plus the policy's `WITH CHECK` on the child**, not from the constraint check honouring policies, which it does not.

**Why caller-visible constraint violations must be Firm-scoped by construction.** Because the check runs with row security suspended, **a constraint can detect a conflict with a row the caller cannot see, and the resulting error is returned to that caller.** This is precisely the existence-disclosure channel §9.2 forbids: with a global unique constraint on a business attribute, a Firm attempting to insert a value another Firm already holds receives a violation and thereby learns of it. **The defence is the constraint's scope, not the caller's visibility** — a `UNIQUE (firm_id, …)` constraint can only ever conflict with a row in the caller's own Firm, so the error can disclose nothing across the boundary. Every uniqueness constraint on Firm business data is therefore Firm-scoped **by construction**, with the platform-generated-identifier exception in §6.3 and its stated reasoning.

The same reasoning applies to the message content and shape of a constraint violation surfaced to a caller: a raw database error may name a constraint, a relation, and a conflicting value, so what reaches a caller is a deliberately shaped domain error, never an unfiltered driver message.

**Required tests** (§16.3): a composite foreign key rejects a cross-Firm reference; a single-column foreign key to the same parent **accepts** one — asserted explicitly, as the evidence that Row-Level Security is not the control here and that the composite shape is load-bearing; and a Firm-scoped unique constraint permits the same value in two Firms while rejecting a duplicate within one.

---

## 9. Indexes and Firm-scoped uniqueness

### 9.1 Indexes lead with `firm_id`

Every index on a Firm-scoped relation **includes `firm_id`, and leads with it** unless the owning story records a specific reason otherwise. Every real query and every row policy filters by Firm; an index that does not include it forces the planner to filter after retrieval, which is both slow and — more importantly — a shape that invites someone to write the query without the Firm predicate because it "works".

**Two explicit exceptions:**

- **The `id`-only primary key index** does not lead with `firm_id`, and is globally unique across Firms. The exception and the reason it does not generalize are stated in §6.3.
- **A child-side index supporting a composite foreign key** leads with `firm_id` by virtue of the key's column order, and must be **created explicitly** — PostgreSQL indexes only the referenced side automatically (§6.3).

**No index exists whose only purpose is to make a cross-Firm query efficient.** Such an index is a cross-Firm capability, arriving as a performance change.

### 9.2 Uniqueness is Firm-scoped

**Every uniqueness constraint on Firm-scoped data is `UNIQUE (firm_id, ...)`.** A globally unique constraint on Firm data is prohibited.

The reason is not only correctness — two Firms obviously may both have a client named the same thing, and both may run a `MAT-2026-001` — but **confidentiality**:

**A global unique constraint discloses the existence of another Firm's row.** A Firm creating a record and receiving a uniqueness violation for a value it cannot see has just learned that another Firm holds it — and, because constraint checks run with row security suspended (§8.5), the caller's inability to *see* the row does not prevent the *error*. For a client name, a matter reference, or an email address in a legal-practice platform, that is an existence disclosure across a conflicts boundary, delivered by a constraint error. Denied-existence confidentiality (Constitution Article 28; `docs/architecture/02_Product_Requirements.md` §9) forbids it. **The defence is the constraint's scope, by construction, not the caller's visibility.**

**Explicit exception — platform-generated identifier columns.** The `id`-only primary key is globally unique and is not a violation of this rule; §6.3 states the exception and why the disclosure argument does not apply to a value that carries no business meaning and is never caller-supplied. **The exception covers platform-generated identifiers only, and never a business attribute, human-readable reference, or caller-supplied value.**

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

**Three statement types must each be independently rejected: `UPDATE`, `DELETE`, and `TRUNCATE`.** `TRUNCATE` is listed separately because it is neither an `UPDATE` nor a `DELETE`: it is a distinct privilege, it does not fire row-level triggers, and it removes every row at once. An enforcement design that covers only `UPDATE` and `DELETE` leaves the most destructive statement available.

Two independent mechanisms, deliberately redundant, each covering all three:

1. **Privileges.** For Firm-scoped audit relations, the Firm-scoped application runtime role holds `INSERT` and `SELECT` and is **not granted `UPDATE`, `DELETE`, or `TRUNCATE`.** For the class (d) platform-realm security-event relation, only its named platform-realm service roles receive the corresponding `INSERT` or narrowly authorized `SELECT`, and none is granted `UPDATE`, `DELETE`, or `TRUNCATE`; the Firm-scoped runtime role has no access at all (§12.4). A privilege that was never granted cannot be used by a bug, an ORM convenience, or an ad-hoc statement.
2. **Triggers.** A `BEFORE UPDATE OR DELETE` row-level trigger rejects those statements, **and a separate `BEFORE TRUNCATE` statement-level trigger rejects truncation** — row-level triggers do not fire on `TRUNCATE`, so one trigger cannot cover all three.

Belt and braces is proportionate here. Audit is the record that explains every other failure; if it is editable, nothing else in this document is verifiable after the fact.

**What this does and does not restrain — stated precisely.** These mechanisms constrain the **application runtime role** and any caller operating through it, which is the threat they exist to address: a bug, a careless script, or a compromised application path. **They do not constrain a role holding sufficient privilege to disable a trigger, alter a grant, or drop the relation** — most obviously the relation's owner, which is the migration role. **No claim is made that a migration owner is restrained by them**, and any statement that audit is "immutable at the database level" is true only within that boundary.

What restrains that role is different in kind and remains fully load-bearing: **role separation** (§4.1) keeps the migration role out of the request path entirely; **`AGENTS.md` approval gates** apply to any migration touching audit privileges, triggers, or relations, and a change to them is a security change (§15.5); dropping a relation holding audit history requires its own explicit approval (§15.6); and **operational and privileged-access controls** over who may act as that role are IdentityAccess's and the deployment architecture's. Those are procedural and organizational controls, not database-enforced ones, and this document does not represent them as the latter.

### 12.3 Audit must be writable when the subject is being denied

An audit row is frequently written **at the moment its subject is being refused, suspended, or destroyed** — a denied access, a revoked membership, a rejected transition. `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §48 models `SecurityEventStream` as its own boundary for exactly this reason.

Persistence consequence: **the audit write path never depends on the subject's own authorization outcome, and never on the subject aggregate's continued existence.** A denial audit row is written under the Firm context of the attempted access, wherever a verified Firm context exists — which is the case for any decision taken after authentication, because context is established before the authorization decision (§4).

### 12.4 Security events with no verified Firm context — the platform-realm stream

Some security events genuinely occur **before any verified Firm context exists**: a failed authentication attempt, an unresolvable tenant, a rejected invitation, an entitlement outcome at the authentication gate, an enumeration-shaped probe. They must be recorded — and there is no Firm to record them against.

**They are written to a distinct, platform-realm, append-only security-event relation, owned by IdentityAccess.** Rules:

- **The relation is class (d) — platform-realm security / operations — in the §2.4 taxonomy**, and it is presently the **only** relation in that class. It carries **no `firm_id`**. **It is explicitly not platform-global reference data (class (c))**: class (c) is authoritative reference material read on ordinary Firm-facing paths, while this is operational security recording that exists precisely because no Firm is verified, and conflating them would place a stranger's failed attempt on paths that legitimately serve Firms.
- **A candidate Firm identifier is never written as `firm_id`.** A hostname, custom domain, header, parameter, route segment, submitted email address, or claimed Firm identifier identifies a *candidate* and proves nothing (Constitution Article 27). Writing one into a tenancy column would fabricate a verified fact from an unverified input, and would then attribute an unauthenticated stranger's action to a real Firm's audit history. Where a candidate value must be recorded at all, it is recorded in a **clearly named candidate/unverified field**, never in `firm_id`, and never in a way that makes it queryable as though it were Firm attribution.
- **The platform-realm stream is never exposed to Firms.** No Firm-facing surface, Firm-visible support-access history, Firm-scoped export, or Firm-scoped query reads it. It would otherwise disclose failed attempts against *other* candidate Firms and turn an audit relation into the enumeration channel Constitution Article 27 forbids.
- **Append-only requirements apply in full, on the same terms as §12.2** — `UPDATE`, `DELETE`, and `TRUNCATE` each independently rejected by privilege and by trigger. It carries safe metadata only under §12.7 — never a submitted credential, and never an unredacted probe payload.
- **Access is restricted to named platform-realm services and security operators**, by dedicated database privileges and narrow query contracts. The Firm-scoped application runtime role holds no access to it.
- **It is never a cross-Firm bridge**, and never a source for a cross-Firm projection, aggregate, or report.
- **Adding another relation to class (d) requires architecture and security approval** (§2.4). The class holds a named exception; it never becomes a home for anything inconvenient to scope.
- **It never becomes a second authorization or entitlement authority**, and its existence grants nothing.
- **It is not merged with any Firm-scoped audit stream** (§12.6). An event with no verified Firm is a categorically different fact from an event within a Firm, and collapsing them would either fabricate attribution or contaminate a Firm's history with strangers' activity.
- **Pre-authentication security-event data is governed by `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 8:** minimize collection; avoid raw submitted identifiers where possible; prohibit credentials, secrets, and body dumps; restrict access; never expose the stream to a Firm; and apply a short separately approved retention period. **No numeric period is asserted here** (§17.5, §22).

### 12.5 Audit is not editable by the actor being audited

No application path exposes `UPDATE`, `DELETE`, or `TRUNCATE` on an audit relation to any actor, at any privilege level, including a Firm administrator and a platform operator. **Correction is a new row referencing the corrected one** (Constitution Articles 8, 18, 23).

The boundary of that statement is the one in §12.2: it holds for every actor operating through the application, which is every actor the application has. It is not a claim about a role that can alter grants or triggers, which role separation and the approval gates address instead.

### 12.6 Distinct streams, never merged

Each context owns its own audit relations, and they are never consolidated into one platform audit table:

| Stream | Owner | Firm attribution |
|---|---|---|
| Administrative audit — Firm lifecycle, provisioning, entitlement, seat-limit decisions, refusals within that context | `PlatformAdministration` (ARCH-019 §19) | Firm-scoped |
| Security events **within a verified Firm context** — authorization, membership, privileged access, support-access history | IdentityAccess (ARCH-016 §48) | Firm-scoped |
| Security events **with no verified Firm context** — pre-authentication failures, unresolvable tenants, enumeration-shaped probes | IdentityAccess (§12.4) | **Platform-realm; no `firm_id`; never Firm-visible** |
| Business activity history | Practice Management and each owning domain | Firm-scoped |
| Posted ledger entries | Billing (Constitution Articles 23–24) | Firm-scoped |
| Document and knowledge version and access history | Documents | Firm-scoped |

Merging them would make "this Firm's subscription lapsed" and "this person's access was revoked" the same kind of record — materially different facts about materially different subjects (ARCH-019 §9). It would also give every context read access to every other's audit content, which no context is entitled to.

### 12.7 Content rules

- **Safe metadata only**: actor, Firm, event, result, timestamp, correlation and causation, authorization provenance, and the identifiers of affected records.
- **Never**: a credential, password hash, session token, MFA secret, recovery material, API secret, signing key, payment credential, privileged narrative, document bytes, knowledge body text, embedding, or **any cross-Firm information** (Constitution Articles 29, 30; ARCH-019 §19).
- **Never a blind dump of a request or response body.** What is recorded is chosen, not captured.
- Human, system, integration, and AI actors remain **distinguishable** (Constitution Articles 26, 35). An AI-assisted operation records the initiating human, the AI or system actor, the authorization relied on, and any required approval.
- **Firm-scoped audit rows** carry `firm_id` and are read under the same Row-Level Security as any other Firm-scoped relation. The class (d) platform-realm pre-authentication security-event relation is the narrow exception: it has no verified Firm context, carries no `firm_id`, is never Firm-visible, and is protected by its dedicated platform-realm privileges and authorization (§12.4).

### 12.8 Retention

- **Audit retention outlives business-content retention.** Deleting content never deletes the audit fact that the content existed (§7.6, §10).
- **Purging audit rows is not designed here and requires its own separately approved decision.**
- **The minimum audit retention period remains a separately approved policy parameter, not a technical implementation choice.** Each stream receives its own approved minimum period; **no numeric period is asserted, implied, or defaulted here** (`docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 2; §17.5, §22).
- **No partitioning or archival scheme is selected.** If audit partitioning is adopted later, **detaching a partition must not become a silent deletion path** — it requires the applicable retention period to have expired, confirmation that no legal hold applies, and an authorized, recorded decision (`docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 2; §22).
- Where a jurisdictional obligation to erase collides with an obligation to retain audit, with a legal hold, or with retention in backups, **that conflict is unresolved and requires the same review; this document asserts no legal conclusion and resolves no such conflict** (§17.5, §20.9).

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
- **Delivery bookkeeping** — status, attempt count, next-attempt time, last error (safe metadata only), `published_at`, and the **claim token** and **lease expiry** the protocol in §13.6 requires.
- **Provenance** — correlation and causation identifiers, initiating actor, effective actor, and where applicable the AI or system actor and the authorization relied on (Constitution Articles 35, 41).

### 13.4 The outbox is not the audit record

They are separate relations with separate rules and separate lifecycles. The outbox is a **delivery mechanism** whose rows may legitimately be pruned after publication under an authorized operational policy. Audit is a **record** that is not pruned (§12.8). Using one as the other loses either durability of the record or the ability to ever clean up delivery state.

### 13.5 Ordering — and why UUIDv7 cannot provide it

**Outbox ordering never relies on UUIDv7, and never on a wall-clock timestamp.** §6.2 and §7.2 give the reasons: same-millisecond order is arbitrary, clocks move backwards, and processes generate concurrently.

Ordering, **where it is offered at all**, is:

- **per-subject only** — never global. `docs/architecture/07_API_Standards.md` §12 and Constitution Article 34 make no global-ordering claim, and neither does this document;
- derived from an **explicit monotonic sequence** assigned at insert — a bigint identity or sequence column, which is ordering metadata and never an identifier (§6.3).

**A subtlety that must not be papered over: a PostgreSQL sequence is monotonic in *assignment*, not in *commit visibility*.** A row assigned sequence 100 can become visible **after** a row assigned 101, because the transaction holding 100 committed later. A publisher that tracks a high-water mark and reads "rows with sequence greater than my cursor" will therefore **skip** rows permanently. This is a real, well-known, silent data-loss mode, and it is recorded here so `PF-091` and `PF-092` cannot design past it by accident.

**A high-water-mark cursor is therefore prohibited as the claiming mechanism.** Claiming is by status and lease, per §13.6.

### 13.6 Claiming, publication, and Firm context

**The publication protocol is three phases, and the phase boundaries are the design.** External delivery must not occur inside a transaction (Constitution Articles 34, 43; §11.3), and a claim must survive a publisher crash without stranding work. That forces exactly this shape:

**Transaction A — claim.**

- Select pending, unleased rows for this Firm with `FOR UPDATE SKIP LOCKED`, bounded by a batch size. `SKIP LOCKED` is what makes concurrent publishers safe: each takes a disjoint set instead of blocking or duplicating.
- Write a **claim token** (a fresh UUIDv7, unique to this claim attempt) and a **lease expiry** instant onto each claimed row, and mark them claimed.
- **Commit.** The transaction ends here, before any delivery.

**Outside any transaction — publish.**

- Deliver the claimed rows. No database transaction is open, so no lock is held for the duration of a remote call and no rollback can be triggered by a delivery failure.

**Transaction B — mark published.**

- Mark each row published **only where its claim token still matches** the token from Transaction A. The token comparison is the correctness condition: if the lease expired and another publisher reclaimed the row, its token has changed, this update matches nothing, and the stale publisher silently declines to overwrite a newer claim rather than corrupting its state.
- Commit.

**Lease reclamation.**

- Rows whose lease has expired and which are not marked published are **reclaimable** by any publisher, which claims them with a **new** token in a fresh Transaction A. This is what prevents a crashed publisher from stranding work forever, and it is why the lease exists rather than a bare "claimed" flag.
- Lease duration, batch size, and reclamation cadence are `PF-091`/`PF-092` operational parameters, not architecture. A lease shorter than realistic delivery time causes needless duplicate delivery; a very long one delays recovery from a crash. Neither is a correctness failure, and neither number is asserted here.

**Crash-after-publish redelivery is acknowledged, not prevented.** A publisher that crashes **after** delivering but **before** committing Transaction B leaves a row that was delivered and is not marked published. Its lease expires, it is reclaimed, and **it is delivered again.** This window cannot be closed by any purely local mechanism — the delivery and the local mark cannot be made atomic across a boundary the database does not participate in — and closing it would require exactly the distributed guarantee Constitution Articles 34 and 43 forbid claiming. **This is precisely why delivery is at-least-once and consumers must be idempotent** (§13.7). It is a named, expected behaviour of the protocol, not a defect in it.

**Firm context — per-Firm claiming is mandatory.**

- **The publisher iterates Firms using the Firm-identifying registry (§2.4), establishes each Firm's context, and claims only that Firm's rows.** Every phase above runs under an established Firm context, so the outbox is read and written under the same forced Row-Level Security as every other Firm-scoped relation.
- **There is no cross-Firm outbox scan.** No option, no bounded exception, no "where genuinely required" escape. A publisher never issues a query that spans Firms.
- **No `BYPASSRLS`, no superuser, no owner exemption, and no role-based permissive outbox policy.** In particular, a policy granting the publication role unconditional access to the outbox is prohibited: it would be a cross-Firm capability arriving as a grant, and it would make the outbox the one Firm-scoped relation whose isolation depends on which role connected.
- The publication role holds `SELECT` and bookkeeping `UPDATE` on the outbox and `SELECT` on the Firm registry, and **no access to any business relation** (§4.1).
- **The publisher never mutates domain state.** It reads outbox rows and writes its own claim, lease, and publication bookkeeping — nothing else.
- Registry iteration is the **only** cross-Firm read anywhere in this path, it returns Firm identities and nothing else, it joins no business relation, and it produces no cross-Firm report or aggregate (§21.3).

### 13.7 Delivery semantics

- **At-least-once. No exactly-once claim, ever** (Constitution Articles 34, 43).
- Consumers must be idempotent. **Two concrete causes of duplicate delivery are named rather than left abstract:** crash-after-publish under the claiming protocol (§13.6), and recovery from a restore that rewinds published state (§17.4).
- `PF-093` (consumer foundation) is not required by Release 0.1, which has no consumer.
- A **retry redelivers the same event identity under a new delivery attempt** and never mints a new event identity (`PF-043`; `docs/architecture/07_API_Standards.md` §12).
- A **permanently failed row is retained and surfaced**, never silently deleted. Dead-letter handling is `PF-091`'s design; discarding an undeliverable committed business fact without a human decision is not.
- **Pruning published rows is an authorized, recorded operational policy**, and it is safe only because the outbox is not the audit record (§13.4).
- **A restore can replay already-delivered events** (§17.4). That is acknowledged, not claimed away.

---

## 14. Idempotency persistence

**Ownership, stated precisely.** The **persistence decision** for idempotency records is `docs/adr/ADR-021-Idempotency-Persistence.md`'s — not ADR-019's, which decides the outbox only, and not ADR-020's, which decides schema evolution. The **scoping contract** remains `docs/architecture/07_API_Standards.md` §10's. The **relation** is Platform Foundation-owned infrastructure (§5.1), on the same footing as the outbox: Foundation owning the relation is not Foundation owning the decision about it. This section summarizes ADR-021; ADR-021 governs.

### 14.1 Scope and record

An idempotency record is **Firm-scoped** — `firm_id uuid NOT NULL`, immutable, Row-Level Security enabled and forced (§3) — and scoped by, at minimum: **the Firm; the integration installation where one exists; the API contract and version, or the internal command type; the operation; the target resource where applicable; and the idempotency key itself** (`docs/architecture/07_API_Standards.md` §10).

**The request scope, key, and fingerprint are immutable once written.** They are the identity of the protected request; a record whose scope or fingerprint could be updated could be made to match a different request retroactively, which would return one caller's outcome to another.

- **The scoped key carries a unique constraint**, so a duplicate is rejected by the database rather than by a read-then-write race that fails precisely under the concurrency it exists to handle (§7.4).
- The record stores a **fingerprint of the request's material inputs**.
- **Same scoped key, different fingerprint → an idempotency-conflict failure.** It must **never** return the first request's result as though the second, different request had been accepted.
- The record is written **in the same transaction as the side effect it protects** (§11.2). Written before, it can record work that never happened; written after, it can miss work that did.

### 14.2 Replay never bypasses authorization

**A replayed request re-authenticates and passes current Firm, membership, capability, and domain authorization before anything is returned** (`docs/architecture/07_API_Standards.md` §10). Idempotency short-circuits the **side effect**, never the authorization composition.

Persistence consequence: **a stored response is not a licence to disclose.** Because a recorded response must not be served to a caller whose access has since been revoked, stored response content is **minimal** and is re-authorized before return. Where a response would contain protected content, storing the content verbatim is avoided in favour of storing enough to reconstruct it under current authorization.

### 14.3 Retention and its honest tension

- Idempotency records expire under a **defined, bounded window**, and **retention is owned by a later approved policy — not by this document and not by ADR-021**, which requires that a bounded window exist without fixing its length.
- **The window must be at least as long as the period in which a client may still retry.** Expiring earlier silently re-enables the duplicate side effect the record existed to prevent — and it does so quietly, which is worse than refusing.
- Choosing that window is a **trade-off between storage and duplicate-suppression coverage that cannot be resolved by asserting a number here.**
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

| Phase | Content | Reversibility — accurately |
|---|---|---|
| **Expand** | Additive only — new nullable column, new relation, new index, new constraint added `NOT VALID` | **Safely abandonable.** The new object is unused, so ceasing to use it leaves the system in its prior working state. Removing it is itself a contract-phase change. |
| **Migrate** | Backfill and dual-write; validate the constraint; switch reads | **Not reversible by inaction.** Interrupting it is *safe* — the schema still supports both code paths — but the data already written **stays written**. Abandoning a half-finished backfill leaves partially populated data that the next attempt, and any reader, must tolerate. That is why backfills are idempotent and restartable (§15.3), and it is the reason "reversible by doing nothing" is the wrong description of this phase. |
| **Contract** | Remove the old column, relation, index, or constraint | **Not reversible at all.** Dropped data is gone; recovery is restore-and-roll-forward (§15.1). This is why contract runs only once **no code path references the object**, and why destructive changes carry their own approval gate (§15.6). |

Each phase is a **separate migration and a separate deployment**. Collapsing them produces a window in which running code and live schema disagree.

**The property that actually holds across all three is compatibility, not reversibility:** at every phase boundary, both the previous and the next application version work against the live schema. That is what makes an application-level rollback possible, and it is a different claim from the migration being undoable.

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
- **Row-Level Security and policy DDL**, which is easy to mistake for metadata-only work:
  - `ALTER TABLE … ENABLE ROW LEVEL SECURITY`, `… FORCE ROW LEVEL SECURITY`, and their `DISABLE`/`NO FORCE` counterparts take an **`ACCESS EXCLUSIVE` lock** on the relation — they block every reader and writer for the duration, and they queue behind any long-running transaction already holding a weaker lock, which in turn blocks everything arriving after them.
  - `CREATE POLICY`, `ALTER POLICY`, and `DROP POLICY` likewise take **`ACCESS EXCLUSIVE`** on the relation.
  - Consequently: **enabling or changing isolation on a populated, live relation is a planned operation with a lock-timeout strategy**, not a routine statement — and it must never be attempted behind an open long transaction. A `lock_timeout` with a bounded retry is preferred to an unbounded wait, because an unbounded wait on `ACCESS EXCLUSIVE` stalls the whole relation rather than only the migration.
  - **A failed or abandoned policy migration must never leave Row-Level Security disabled or a policy dropped.** That is the specific hazard: the isolation control absent, silently, with nothing failing (§15.5, `docs/adr/ADR-020-Migration-Rollback-and-Schema-Evolution.md`).
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

**Therefore: the PostgreSQL continuous-integration requirement must be satisfied before `PF-080` begins.** `docs/implementation/03_Engineering_Backlog.md` already records it as its own approved story with that ordering constraint, and `docs/architecture/02_Product_Requirements.md` §8 records the same.

**It is an approved requirement that currently has no assigned story identifier.** No identifier is asserted, assumed, or invented anywhere in this document or its ADRs; **assigning one is a tracking-file change** this story is barred from making, and it is recorded as an open item in §22. **This document changes no CI configuration, no `phpunit.xml`, and no test.**

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
- **Unset, empty, and malformed Firm context fail closed — asserted on both an empty relation and a populated relation** (§3.4). The **empty-relation** case is the one that matters most: it proves the guarantee comes from the application-side pre-statement check and does not depend on any row being examined. The **populated-relation** case proves the policy predicate genuinely raises rather than quietly filtering to zero rows. **A suite that tests only the populated case has not tested the guarantee.**
- The application-side check raises **before the first statement** of the transaction is issued (§3.4).
- A transaction cannot change Firm context mid-flight.
- Firm context does **not** survive the transaction — the same connection, in a new transaction with no context, fails (§4.3).
- The application runtime role is not a superuser, does not own the relations, and holds no `BYPASSRLS`.
- **Every relation declares one of the four classes** in §2.4, and the schema-level guard test enumerates relations and asserts the declared class matches the relation's actual shape — `firm_id` presence and forced RLS for (a); absence of `firm_id` for (b), (c), and (d).
- The **Firm-identifying** registry (b) is readable by the tenant resolver and the outbox publication role, and **no Firm-facing path enumerates Firms** (§2.4).
- **The Firm-scoped application runtime role holds no general enumeration access to the registry** (§2.5), asserted at the privilege level.
- **A registry read returns only the minimized field set** its named contract declares, and no general "list Firms" query is reachable from an arbitrary caller (§2.5).
- **The registry carries no Firm business-data column** — asserted structurally, so a later migration cannot quietly add one (§2.5).
- **A class (d) platform-realm relation carries no `firm_id`**, is **unreadable through every Firm-scoped path**, and **rejects `UPDATE`, `DELETE`, and `TRUNCATE`** by privilege and by trigger (§2.4, §12.4).
- **No class (d) relation is reachable by the Firm-scoped application runtime role**, asserted at the privilege level.

**Identity and integrity**

- A composite foreign key **rejects** a reference to a row in another Firm.
- **A single-column foreign key to the same parent *accepts* a cross-Firm reference** — asserted explicitly, as the standing evidence that Row-Level Security is **not** the control here (referential-integrity checks run with row security suspended, §8.5) and that the composite key's shape is what is load-bearing. Without this test, a future maintainer may "simplify" the key and lose the guarantee with every other test still green.
- A Firm-scoped unique constraint **permits** the same value in two Firms and **rejects** a duplicate within one.
- A caller-visible constraint violation cannot be produced by a conflict with another Firm's row (§8.5, §9.2).
- No relation carries a database-generated identifier default.
- Ordering tests **do not assume** identifier or timestamp ordering; where an identifier appears as a final tiebreaker, an explicit business sort key precedes it (§6.2).

**Audit**

- The application runtime role's `UPDATE`, `DELETE`, **and `TRUNCATE`** on an audit relation are **rejected at the privilege level** — all three asserted separately.
- All three are **also rejected by triggers**, independently — with `TRUNCATE` asserted specifically, since row-level triggers do not fire on it (§12.2).
- An audit row is written for a **denied** access, and the write does not depend on the subject's authorization outcome (§12.3).
- A security event with **no verified Firm context** is written to the platform-realm relation, **carries no `firm_id`**, never records a candidate Firm identifier as `firm_id`, and is **not readable through any Firm-scoped path** (§12.4).

**Outbox and idempotency**

- The state change and the outbox row commit together; a forced failure of either rolls back both.
- Claiming under concurrency yields **disjoint** row sets to concurrent publishers, and skips no row (§13.5, §13.6).
- A stale claim token **fails to mark a reclaimed row published** (§13.6).
- An expired lease is **reclaimable**, and a crashed publisher strands no work.
- The publisher issues **no cross-Firm query**, and holds no access to any business relation (§13.6).
- The outbox relation has Row-Level Security forced with **no role-based permissive policy** exempting the publication role.
- A duplicate scoped idempotency key is rejected by the constraint.
- Same key with a different fingerprint yields a conflict, not the first result.
- A replay re-evaluates authorization before returning anything.
- An idempotency record's scope, key, and fingerprint **cannot be updated** (§14.1).

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
- **Restore must preserve append-only audit continuity.** A restore that silently drops posted entries or audit records is a **defect, not an acceptable recovery outcome** (`docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §83).
- **Encryption at rest is required**, and **backups are protected as production data** (`docs/architecture/04_Security_Architecture.md` §5). No key-management product is selected.
- **A restore must not resurrect one Firm's data into another Firm's context.** Physical recovery is Firm-agnostic; Firm isolation is re-established by the same `firm_id`, policy, and context rules the running system uses (§3, §4). A restore never relaxes them, and a restored database is not exempt from forced Row-Level Security.

### 17.2 A consequence of the shared schema, stated plainly

**Per-Firm point-in-time restore is not claimed to be supported.** One shared schema means physical recovery is a **whole-database** operation: restoring Firm A to an earlier point would restore every Firm to that point.

Restoring a single Firm's data would require a **logical, application-level, authorized export and import**, which is **not designed here**. Release 0.1 includes a Firm-scoped export (`docs/architecture/02_Product_Requirements.md` §2, item 20); that is an export capability, **not a restore capability**, and this document does not represent it as one.

This is a real limitation of the §2 decision and is recorded as such rather than left for an incident to reveal. `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 3 does not require per-Firm point-in-time restore for Release 0.1. If a later Thai legal, professional, contractual, or product requirement makes it necessary, the §2 physical model must be revisited through the database-redesign approval gate.

### 17.3 The executed restore test

`docs/architecture/02_Product_Requirements.md` §8 requires an **executed and recorded** restore test as production-access evidence. **No restore test has been executed, and this document does not claim, schedule, or substitute for one.**

### 17.4 Recovery interacts with delivery and idempotency — honestly

- **A restore that rewinds the database can replay already-delivered events.** Outbox rows marked published may return to a pending state. Delivery is at-least-once and consumers must be idempotent; **recovery is one of the concrete reasons that is not a formality** (§13.7).
- **A restore to a point before an idempotency record existed can permit a duplicate side effect** (§14.3).
- Neither is claimed to be prevented. Both are consequences of recovery that the design acknowledges and that operational procedure must account for.
- **A recovery-driven duplicate side effect with a client-facing consequence — a duplicated notice, record, or financial event once Billing exists — requires recorded assessment, remediation, and a communication or notification decision** (`docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 5; §22). Consumer idempotency is mandatory but is not declared legally or professionally sufficient by itself.

### 17.5 Retention, deletion, and backups

A backup retains content after the live database has deleted it. Under `docs/legal/ARCH-012-Thai-Legal-Review.md` Decisions 1 and 2, authorized deletion removes content from live systems; encrypted backup generations age out under an approved schedule rather than selective rewriting; legal hold suspends in-scope deletion and expiry; and audit detachment requires expired retention, no applicable hold, and recorded authorization. Numeric periods and implementation procedures remain separately approval-gated (Constitution Articles 1–4; `docs/architecture/04_Security_Architecture.md` §9).

**Restored production data outside production is a data-classification event, not a convenience** (§16.4). Ordinary non-production environments use synthetic data only. Under `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 6, a test that necessarily uses production data runs only in an isolated, production-controlled recovery environment with explicit authorization, least-privilege access, access logging, encryption, and approved retention and deletion. Masking, pseudonymization, or subsetting is not presumed sufficient without separately recorded assessment.

---

## 18. Prohibited patterns

Each is prohibited. Where a pattern could be permitted under an explicit approval, that is stated.

**Tenancy**

- A nullable `firm_id`, a sentinel or zero-UUID Firm, or a "shared Firm" row on a Firm-scoped relation.
- A Firm-scoped relation without `FORCE ROW LEVEL SECURITY` and a policy.
- A policy with `USING` but no `WITH CHECK`.
- A policy predicate that reads a caller-supplied value, an entitlement status, a capability, a role, or anything other than Firm identity (§3.5).
- A row policy used to simulate an Ethical Wall, or any wall-shaped column, flag, or policy (`docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`).
- Disabling Row-Level Security, dropping a policy, or granting `BYPASSRLS` to make a query, migration, backfill, publisher, or test work.
- **A role-based permissive policy exempting any role from a relation's Firm predicate** — in particular one granting the outbox publication role unconditional access (§13.6).
- Leaving Row-Level Security disabled or a policy dropped after a failed or abandoned migration (§15.4).
- Running the application as a superuser, as a relation owner, or with `BYPASSRLS`.
- Placing any relation other than the Firm registry in the **Firm-identifying** class, or putting Firm business data in it (§2.4).
- Using the Firm registry as a bridge to reach one Firm's rows from another Firm's context.
- Enumerating Firms from a Firm-facing path.
- Treating a raising row policy as the fail-loud guarantee, rather than as defence in depth behind the application-side pre-statement check (§3.4).
- Testing fail-closed behaviour only on a populated relation (§16.3).
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
- Ordering by an identifier as a **business, chronological, causal, event, or delivery** sort key — including identifier-only `ORDER BY` and an identifier-only cursor. An identifier may appear **only as the final deterministic tiebreaker after an explicit approved business sort key** (§6.2).
- Deriving an ordering, deduplication, or idempotency key from an identifier's timestamp bits.
- A natural or business key — `MatterNumber` included — as a primary key.
- A single-column foreign key where a composite Firm-carrying one is required (§6.3).
- Relying on Row-Level Security to prevent a cross-Firm reference — referential-integrity checks run with row security suspended (§8.5).
- Assuming a foreign key created a child-side index; PostgreSQL indexes only the referenced side (§6.3).
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
- Granting `UPDATE`, `DELETE`, or `TRUNCATE` on an audit relation to any application actor.
- Enforcing append-only against `UPDATE` and `DELETE` while leaving `TRUNCATE` reachable, or relying on a row-level trigger to stop `TRUNCATE` (§12.2).
- Claiming that append-only enforcement restrains a role able to disable a trigger or alter a grant (§12.2).
- One consolidated platform-wide audit table merging distinct streams.
- Writing a candidate, unverified Firm identifier into `firm_id` on any relation, including a security-event relation (§12.4).
- Exposing the platform-realm security-event stream to any Firm-facing surface, export, or query (§12.4).
- Using the outbox as the audit record, or the audit record as the outbox.
- Outbox ordering from a UUIDv7, a wall-clock timestamp, or a bare high-water-mark cursor over a sequence (§13.5).
- Any cross-Firm outbox query, scan, or aggregate (§13.6).
- Publishing inside the claiming transaction, or holding a transaction open across an external delivery (§11.3, §13.6).
- Marking a row published without matching the claim token it was claimed under (§13.6).
- Mutating an idempotency record's scope, key, or fingerprint after it is written (§14.1).
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

This architecture does **not**: implement anything; create or modify any migration, schema, table, column, index, policy, role, grant, source file, test, configuration file, Docker file, CI workflow, dependency, environment, or GitHub setting; define the physical data model, table names, or column names of any module; define **which** aggregates, attributes, states, events, or invariants exist in any bounded context, all of which remain owned by that context's approved architecture; select, endorse, or evaluate a hosting provider, managed database service, cloud platform, region, connection pooler, backup product, replication or high-availability product, secret-management or key-management product, monitoring or observability product, search engine, vector database, queue or event-broker product, ORM alternative, migration tool, or any other vendor, provider, product, or package; define deployment, infrastructure, environment topology, network design, capacity, sizing, scaling, sharding, partitioning, replication, failover, or disaster-recovery procedure; define a PostgreSQL minimum version, extension set, or server configuration, each of which belongs to the deployment architecture and must satisfy the capability requirements recorded here; execute, schedule, or claim a backup or restore test; define a Reporting bounded context, cross-Firm reporting, analytics, benchmarking, comparison, data warehouse, or read-only analytics role — which Constitution Article 44 reserves for a future context this document **neither approves nor permanently prohibits**; define search, full-text, trigram, or vector/embedding storage or retrieval, which remain the owning contexts' decisions under Constitution Articles 21 and 42; introduce `Money`, `Currency`, or any monetary column, or schedule or unblock `PF-045`; introduce an AI capability or modify `docs/architecture/05_AI_Architecture.md`; create a second authentication, authorization, entitlement, or privileged-access path; define or approve a Firm-level suspension or emergency-disable capability; implement an Ethical Wall, conflict-checking, or per-user Matter-visibility mechanism of any kind, in any layer (`docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`); define an audit-purge or audit-partition-detachment mechanism, or assert any numeric audit retention period; implement or claim satisfaction of the decisions in `docs/legal/ARCH-012-Thai-Legal-Review.md`; assign a story identifier to the PostgreSQL CI requirement, or assert any story's status, which is `docs/PROJECT_STATUS.md`'s; assert a general legal, tax, regulatory, or compliance conclusion beyond the recorded ARCH-012 legal-review decisions; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; claim that any described control is implemented, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

**No capability, control, or property described in this document is claimed to be implemented.**

---

## 20. Security and professional-responsibility consequences

1. **A shared schema makes a missing predicate a high-severity security and confidentiality incident.** In a legal-practice platform, a cross-Firm read is not a data-quality defect: it exposes information about parties who never consented, and those parties may be adverse to one another. `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 4 requires containment, evidence preservation, Thai-qualified assessment, and a recorded decision on affected persons, professional-conduct consequences, and notification; it asserts no universal outcome or timeline. **The four-layer isolation model in §3 exists because of that severity**, and no layer may be removed on the grounds that another is present.

2. **Row-Level Security is the layer that catches what review missed** — a hand-written query, an ad-hoc script, a future maintainer's repository method, a migration-time convenience. It is defence in depth, never the primary control, and never a licence for a weaker repository (§3.1).

3. **`FORCE ROW LEVEL SECURITY` is what makes the boundary real.** Without it, the role most likely to be running the riskiest operations — the owner — is exempt. Its cost is the maintenance constraint in §15.5, which is paid rather than avoided.

4. **Unset Firm context fails closed, and fails loudly — enforced in the application before the first statement.** A silent empty result is specifically rejected: a lawyer shown an empty Matter list behaves very differently from one shown an error, and the empty list is the outcome that causes a missed deadline. **A raising row policy cannot carry that guarantee**, because on an empty relation the predicate may never be evaluated at all; it is defence in depth behind the application-side check, and both are tested, on empty and populated relations alike (§3.4, §16.3).

5. **Session-level Firm context on a pooled connection is a cross-Firm read that passes every application check.** Transaction-scoped `SET LOCAL` is not a style preference; it is the difference between an isolation control and an intermittent, load-dependent disclosure (§4.3).

6. **A global unique constraint on Firm business data discloses another Firm's rows through a constraint error** — and the caller's inability to *see* that row does not prevent the error, because constraint checks run with row security suspended (§8.5). Firm-scoped uniqueness **by construction** is a confidentiality control as much as a correctness one. The platform-generated-identifier exception in §6.3 is narrow and does not extend to any business attribute (§9.2).

7. **Row-Level Security does not prevent a cross-Firm foreign key at all** — referential-integrity checks run with row security suspended, so the constraint accepts the reference and the resulting link is then invisible in ordinary querying. Composite, Firm-carrying foreign keys make same-Firm referencing structural, via the key's shape plus the child's `WITH CHECK`, not via policy evaluation during the check (§6.3, §8.5).

8. **Entitlement never enters a row policy.** Doing so would silently convert entitlement into a per-request authorization input, reversing an approved decision that deliberately protects an already-issued session after a commercial lapse (§3.5, §21.7).

9. **No Ethical Wall exists in any layer, including this one.** An approximated wall implemented as a row policy would be the most dangerous form of the approximation `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` prohibits, because it would look like a control while being untested, undisclosed, and located where no reviewer would check. **Every Firm member with Worklist access can see every Matter in the Firm.** That absence **is required to be disclosed** in-product where reliance would occur and in the pilot agreement. The Thai-qualified reviewer and owner approved the baseline disclosure substance on 1 August 2026 in `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 7; placement and implementation remain outstanding and no claim is made that either surface contains it yet. It is not softened by a database feature.

10. **Append-only audit enforced by ungranted privileges and rejecting triggers, against `UPDATE`, `DELETE`, and `TRUNCATE` alike**, is what makes professional accountability verifiable after the fact. Audit must be writable at the moment its subject is being denied or destroyed, and never writable by the actor it records. **The enforcement binds every actor operating through the application; it is not claimed to restrain a role able to disable a trigger or alter a grant**, which role separation and the approval gates address instead (§12.2, §12.3, §12.5).

11. **Audit streams stay separate** so the security record remains truthful about what kind of event occurred and to whom (§12.6). **Security events with no verified Firm context go to a platform-realm stream that carries no `firm_id`, never records a candidate Firm identifier as one, and is never exposed to a Firm** — writing an unverified candidate into a tenancy column would fabricate attribution and would turn audit into an enumeration channel (§12.4).

12. **A UUIDv7 discloses its generation time** to anyone holding it, and identifiers propagate widely once persisted. Internal identifiers are never exposed externally by convenience (§6.4).

13. **Deleting content never deletes the audit fact; a legal hold blocks deletion; archival is not deletion.** Cascades are denied by default because a cascade is precisely a delete path that runs without consulting any of that (§10).

14. **The database enforces predicates over rows. Every other invariant is the domain's**, and treating a constraint as though it enforced a transition invariant leaves that invariant unenforced (§9.3).

15. **Per-Firm point-in-time restore is not supported**, and a Firm-scoped export is not a restore capability. Stated now rather than during an incident (§17.2).

16. **Duplicate delivery has two named causes and neither is claimed to be prevented**: crash-after-publish under the claiming protocol (§13.6) and recovery from a restore that rewinds published state (§17.4). At-least-once delivery, consumer idempotency, and bounded idempotency windows are what make them survivable. A client-facing duplicate requires recorded assessment, remediation, and a communication or notification decision (`docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 5; §22).

17. **Isolation tested on SQLite, tested as a role that bypasses the control, or tested only on a populated relation is worse than untested** — each produces evidence of a control that does not exist. PostgreSQL CI before `PF-080`, tests as the application runtime role, and the empty-relation fail-closed test are all load-bearing (§3.4, §16).

18. **The eight legal questions listed together in §22 were reviewed and approved at the decision-principle level on 1 August 2026**, with their dispositions recorded separately in `docs/legal/ARCH-012-Thai-Legal-Review.md`: backup retention versus erasure, audit retention, and legal hold; minimum audit retention and lawful partition detachment; per-Firm point-in-time restore for Release 0.1; the assessment and notification-decision process following an existence disclosure; recovery-driven duplicate side effects; restored production data; Ethical Wall absence disclosure; and platform-realm pre-authentication security-event data. Numeric retention periods, implementation, procedures, and execution evidence remain separately approval-gated.

19. **AI holds no authority over anything in this document.** AI never grants access, never authors or approves a migration, policy, role, grant, or destructive operation, never writes or alters an audit record, and is never an authorization authority (Constitution Articles 6, 26, 28, 39, 40). Release 0.1 contains no AI capability.

20. **No certification, compliance, production-readiness, or blanket legal-sufficiency claim is made.** The limited Thai-qualified review recorded in `docs/legal/ARCH-012-Thai-Legal-Review.md` occurred on 1 August 2026; nothing described here is thereby claimed to be implemented, tested, effective, or sufficient outside that record's express scope.

---

## 21. Resolved conflicts and apparent contradictions

Each is resolved explicitly rather than left to a reader's inference.

**21.1 "Enforced in database policy" versus "global scopes alone are insufficient."** `docs/domain/06_Laravel_Module_Blueprint.md` requires both, and they are not in tension. **No conflict.** Database policy is added as a fourth layer (§3.1); application logic and repositories remain the primary enforcement; a global Eloquent scope is not an isolation control in any layer.

**21.2 "Module-owned migrations" versus Foundation-owned outbox and idempotency relations.** `app/Foundation` is a layer of **shared technical primitives, not a bounded context** (`AGENTS.md`, `docs/domain/06_Laravel_Module_Blueprint.md`). The rule that no module writes another module's tables is a rule about **bounded contexts**. A module writing an outbox row through Foundation's published contract, inside its own transaction, is **not** a cross-module write — it is a module using a platform primitive, exactly as it uses `Clock`, `UuidV7`, and the transaction manager. **Resolved:** Foundation owns platform infrastructure relations; modules own domain relations; nobody writes another **context's** tables.

**Owning the relations is not owning the decisions about them**, and the three decisions are distinct: **`docs/adr/ADR-019-Transactional-Outbox-Persistence.md` owns transactional-outbox persistence only**; **`docs/adr/ADR-021-Idempotency-Persistence.md` owns idempotency persistence**; and **`docs/adr/ADR-020-Migration-Rollback-and-Schema-Evolution.md` governs the migration and schema-evolution mechanics applicable to both.** **Idempotency ownership is never attributed to ADR-019**, and the idempotency **scoping contract** remains `docs/architecture/07_API_Standards.md` §10's.

**21.3 "No record, query, cache, projection, index, or event spans Firms" versus an outbox publisher finding pending work.** Constitution Articles 45 and 48 and `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` Decision 11 bind **`PlatformAdministration`**, and the broader prohibition is on cross-Firm **reads, reports, aggregates, and comparisons of Firm data**. **Resolved without an exception: there is no cross-Firm outbox scan.** The publisher iterates Firms through the **Firm-identifying** registry (§2.4), establishes each Firm's context, and claims only that Firm's rows under forced Row-Level Security — **no `BYPASSRLS`, no superuser, no owner exemption, no role-based permissive policy** (§13.6). Registry iteration returns Firm identities only, joins no business relation, and produces no cross-Firm report or aggregate. The earlier formulation of this document offered a bounded cross-Firm scan as an alternative; **that option is withdrawn**, because an exception that exists is an exception that will be taken.

**21.4 "UUIDv7 is time-sortable" (`PF-048`) versus "UUIDv7 is not a monotonic ordering guarantee."** Both are true and they describe different things. **Resolved:** time-sortability is a **physical index-locality property** — a time-prefixed key clusters inserts — and it is the reason UUIDv7 is used rather than UUIDv4. It is **not** an ordering semantic: same-millisecond order is arbitrary, clocks move backwards, generation is concurrent, and commit order is not generation order. §6.2 keeps the benefit and denies the semantic.

**21.5 `FORCE ROW LEVEL SECURITY` versus the need to run migrations and backfills across Firms.** Forcing RLS subjects the owner to policies, so the migration role cannot see all Firms by default — which is intentional, and which would otherwise be "solved" by the worst possible means. **Resolved in §15.5:** per-Firm iteration with `SET LOCAL` (preferred), or a narrowly scoped, explicitly authorized, recorded maintenance path — **never** by disabling RLS, dropping a policy, or granting `BYPASSRLS`.

**21.6 "Firm-scoped unique constraints" versus genuinely platform-global uniqueness, and versus reading the Firm registry before Firm context exists.** Constitution Article 5 requires platform-global legal reference data to exist once for every Firm, and such data legitimately has global uniqueness (an official citation). Separately, tenant resolution and per-Firm background work must read *which Firms exist* before any Firm context can be established. **Resolved in §2.4:** four explicitly declared relation classes, no fifth. Firm-scoped uniqueness applies to Firm-scoped relations; global uniqueness applies only to platform-global relations, where there is no Firm whose existence a constraint error could disclose; and the **Firm-identifying** class contains the Firm registry alone — one row per Firm, no `firm_id`, no Firm business data, never a cross-Firm bridge, readable pre-context only by the tenant resolver and narrowly authorized `PlatformAdministration` paths. The fourth, class (d), is the narrowly bounded platform-realm security/operations class for records that must exist before verified Firm context, presently only the pre-authentication security-event relation (§12.4). Naming all four classes is what prevents the alternative resolutions: a `BYPASSRLS` grant, a permissive policy, or a nullable `firm_id`.

**21.6a "Firm-scoped uniqueness" versus the globally unique primary key.** Every `id` is globally unique across Firms, which is literally a global unique constraint on a Firm-scoped relation. **Resolved in §6.3 and §9.2 as an explicit, narrow exception:** the rule exists because a constraint error over a **business attribute** discloses a meaningful value another Firm holds. A platform-generated identifier carries no business meaning and is never caller-supplied, so a collision conveys nothing actionable about any Firm. The exception covers platform-generated identifier columns only, and never a business attribute, human-readable reference, or caller-supplied value.

**21.7 "Entitlement is enforced" versus "entitlement is never a per-request authorization input."** A row policy is the most natural-looking place to enforce entitlement and the most damaging. `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` Decision 8 and Constitution Article 46 evaluate entitlement at exactly two gates — authentication/session issuance and membership activation — precisely so that an already-issued valid session runs to its normal expiry after a commercial lapse. **Resolved:** **entitlement never appears in a row policy, a query predicate, or any per-statement check.** A policy predicate carries Firm identity only (§3.5).

**21.8 "Exact decimal with explicit currency" versus `PF-045` `Money` being deferred.** **Resolved:** no monetary column exists in Release 0.1, and the application never stores an amount (`docs/architecture/02_Product_Requirements.md` §3). §7.3 records the decimal/currency rule as a **forward constraint** owned by `PF-045` and Billing, not as a schema this document defines. Nothing here schedules or unblocks `PF-045`.

**21.9 "Firm isolation enforced in database policy" versus "Ethical Walls are absent and never approximated."** Both are approved and they are easily confused, because both are "restrict what a query returns." **Resolved:** Firm isolation is a **tenancy** boundary and belongs in a row policy. An Ethical Wall is a **per-actor, per-Matter professional-responsibility** control that Release 0.1 does not have and that `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` forbids approximating. **No wall-shaped column, flag, or policy exists in any layer**, and a row policy is never used to produce per-user Matter visibility (§3.5, §20.9).

**21.10 The PostgreSQL CI requirement has no assigned story identifier.** `docs/implementation/03_Engineering_Backlog.md` records the story — its own approved story, which must land before `PF-080` begins — **without a story identifier**. **Resolved as far as this story may resolve it:** the **substantive requirement is already approved and recorded**, and this document relies on it by description rather than by number. **No identifier is asserted, assumed, or invented here**; assigning one is a tracking-file change this story is barred from making, and it is recorded as an open item in §22. **This document does not create, rename, or renumber any story.**

**21.11 `phpunit.xml` runs SQLite versus PostgreSQL being authoritative.** **No conflict to resolve here — it is an obligation, not a contradiction.** §16 records why the current configuration cannot verify any isolation control, and the approved ordering constraint that PostgreSQL CI lands before `PF-080`. **This document changes no test configuration.**

**21.12 The Laravel skeleton relations versus context ownership.** **Resolved, and the ownership splits rather than sitting with one context:** all three are framework starter artefacts and **none is approved domain schema**. **IdentityAccess owns `users` and identity records** — a stock `users` table is not the principal model (Constitution Article 26) — and only `users`. **The Platform Foundation runtime owns decisions about the framework cache and queue/job infrastructure relations (`cache`, `jobs`)**; they are not identity concerns, and **assigning them to IdentityAccess would misattribute a runtime concern to the identity context**. **Every existing skeleton relation remains unapproved until its owning story decides it**, and none is decided or modified here (§5.1).

**21.13 "Documents owns canonical content" versus storing anything document-shaped.** **Resolved:** the database stores a storage reference, checksum, media type, and byte size — **never bytes** (§7.3). Release 0.1 has no document capability at all, so no such relation exists yet.

**21.14 Backup retention versus deletion, retention, and legal hold.** **Resolved at the decision-principle level by `docs/legal/ARCH-012-Thai-Legal-Review.md` Decisions 1 and 2.** Authorized deletion removes content from live systems; encrypted backup generations age out under an approved schedule rather than selective rewriting; legal hold suspends in-scope deletion and expiry; and audit-stream detachment requires expired retention, no applicable hold, and recorded authorization. Numeric periods, mechanisms, and procedures remain separately approval-gated and are not asserted here.

**21.15 "Audit is append-only at the database level" versus a migration owner able to disable a trigger.** Both statements are true of different actors, and conflating them would overstate the control. **Resolved in §12.2:** ungranted privileges and rejecting triggers — covering `UPDATE`, `DELETE`, **and `TRUNCATE`** — bind every actor operating through the application, which is every actor the application has. **They are not claimed to restrain a role that can alter grants, disable triggers, or drop the relation.** That role is restrained by role separation (§4.1), by the `AGENTS.md` approval gates on any migration touching audit privileges, triggers, or relations (§15.5, §15.6), and by operational and privileged-access controls owned elsewhere — procedural and organizational controls, which this document does not represent as database-enforced.

**21.16 "Firm isolation is enforced by the row policy" versus referential-integrity checks bypassing it.** A reader may reasonably assume Row-Level Security constrains foreign-key checks. It does not. **Resolved in §8.5:** constraint checks run with row security suspended, so a single-column foreign key would *accept* a cross-Firm reference, and a global unique constraint can raise a violation against a row the caller cannot see. The defences are therefore structural — composite Firm-carrying keys, and Firm-scoped uniqueness by construction — not visibility-based, and both carry explicit tests (§16.3).

**21.17 "Never use an identifier for ordering" versus needing a deterministic total order.** Keyset pagination and stable result sets require a deterministic tiebreaker, and the obvious candidate is the identifier. **Resolved in §6.2:** an identifier is permitted **only** as the final term after an explicit business sort key, precisely because it is meaningless — it breaks ties arbitrarily but repeatably. It never becomes the primary sort term and never implies chronology, and every prohibition on identifier-derived business, chronological, causal, event, or delivery ordering stands unchanged.

---

## 22. Unresolved and deferred items

Recorded openly rather than presented as settled. Each requires its own decision by the named owner. **Legal questions are listed separately below and none of them is answered anywhere in this document.**

### 22.1 Technical and tracking items

| Item | Owner | Note |
|---|---|---|
| **PostgreSQL CI story identifier — unassigned** | Tracking-file synchronization | The requirement is approved and recorded; **no identifier exists, and none is asserted here** (§16.1, §21.10). This story is barred from editing `docs/implementation/01_Implementation_Sprint_Plan.md` and `docs/implementation/03_Engineering_Backlog.md`. |
| **Whether `firm_id` carries a foreign key to `Firm`** | The implementing story for `PF-080` / `PlatformAdministration` | §8.3 states the reasoning both ways and requires an explicit recorded decision |
| **Outbox lease duration, batch size, and reclamation cadence** | `PF-091`, `PF-092` | §13.6 fixes the **protocol**; these are operational parameters, and neither number is asserted here. **The claiming strategy itself is no longer open** — cursor claiming is prohibited (§13.5) and cross-Firm scanning is withdrawn (§13.6). |
| **Idempotency retention window** | A later approved retention policy | §14.3, `docs/adr/ADR-021-Idempotency-Persistence.md`; a bounded window is required, its length is not fixed here |
| **Thai collation and normalization decisions** | The Thai-text-correctness story | §9.4; that it is explicit is required, which value is chosen is not decided here |
| **PostgreSQL minimum version, extension set, and server configuration** | Deployment architecture | §19; must satisfy the capability requirements recorded here (forced RLS, transaction-scoped settings, native `uuid`, `timestamptz`, partial unique indexes, `SKIP LOCKED`, locale/ICU collation) |
| **Connection pooling topology** | Deployment architecture | §4.3 records the incompatibility with statement-level multiplexing as a constraint; no product is selected |
| **Backup, PITR, encryption-at-rest, and key-management mechanisms** | Deployment architecture | §17; no product or provider selected; **the restore test is unexecuted** |
| **Audit purge and partition-detachment mechanism** | Its own separately approved decision | §12.8; not designed here. Its **lawfulness** is a legal question below. |
| **Whether per-module PostgreSQL schemas are adopted later** | Its own separately approved decision | §5.2; additive, not foreclosed |
| **Fate of the Laravel skeleton relations** | `users` → IdentityAccess; `cache` and `jobs` → Platform Foundation runtime | §5.1; none is modified by this story |
| **Stale cross-references to this file as an "empty placeholder"** | Tracking-file synchronization | `docs/README.md`, `docs/architecture/02_Product_Requirements.md` §8 and §10, `docs/architecture/08_Roadmap.md`, `docs/implementation/03_Engineering_Backlog.md`, and `docs/adr/ADR-012-…` describe this file as empty, and `docs/README.md`'s ADR table does not list ADR-016 through ADR-021. Statements of the form "not populated by ARCH-011" remain literally true; bare "empty placeholder" descriptions become stale on approval. This story is barred from editing those files. |

### 22.2 Thai-qualified legal-review disposition

**Michael Jand, repository owner acting in the expressly stated capacity of Thai-qualified legal reviewer, reviewed and approved all eight decisions on 1 August 2026.** The authoritative decision record is `docs/legal/ARCH-012-Thai-Legal-Review.md`. The summary below does not claim implementation, production readiness, certification, or sufficiency outside that record's express scope.

1. **Backups, erasure, audit retention, and legal hold** — live deletion when authorized; encrypted backup generations age out under an approved schedule; legal hold suspends in-scope deletion and expiry (record Decision 1; §17.5, §21.14).
2. **Audit retention and partition detachment** — a separately approved minimum period per stream; detachment only after expiry, confirmation that no hold applies, and recorded authorization. **No numeric period is asserted here** (record Decision 2; §12.8).
3. **Per-Firm point-in-time restore** — not required for Release 0.1; whole-database physical recovery is the baseline, and any later requirement triggers database-redesign review (record Decision 3; §17.2).
4. **Cross-Firm existence disclosure** — a high-severity security and confidentiality incident requiring containment, evidence preservation, Thai-qualified assessment, and a recorded notification decision based on the facts and applicable duties (record Decision 4; §2.3, §20.1).
5. **Recovery-driven or crash-driven duplicate side effects** — idempotency is mandatory but not declared professionally sufficient; a client-facing duplicate requires recorded assessment, remediation, and communication or notification decision (record Decision 5; §13.7, §17.4).
6. **Restored production data** — synthetic data only in ordinary non-production; production-data restore testing only in an isolated, production-controlled recovery environment under the controls in record Decision 6 (§16.4, §17.5).
7. **Ethical Wall absence disclosure** — the approved baseline states that Release 0.1 has no automated Ethical Walls or conflict checking, requires the Firm's documented manual process, and says manual attestation is not a system-performed conflict check. Placement remains outstanding (record Decision 7; §20.9).
8. **Platform-realm pre-authentication security events** — minimize collection; avoid raw submitted identifiers where possible; prohibit credentials, secrets, and body dumps; restrict access; never expose to a Firm; and apply a short separately approved retention period. **No numeric period is asserted here** (record Decision 8; §12.4).

---

## 23. Invariants

- Every relation is explicitly **Firm-scoped, Firm-identifying, platform-global reference, or platform-realm security/operations**. The taxonomy is exhaustive; the Firm-identifying class contains the Firm registry alone, and the platform-realm class presently contains the pre-authentication security-event relation alone.
- The platform-realm security/operations class is never treated as platform-global reference data, is never Firm-visible, is never a cross-Firm bridge, stores no candidate Firm identifier as `firm_id`, and remains append-only; adding a relation to it requires architecture and security approval.
- The Firm registry uses no `FirmContext`-based policy — because it is consulted before `FirmContext` exists — and is restricted instead by dedicated privileges, narrow query contracts, and application authorization; the Firm-scoped runtime role holds no general enumeration access, and no Firm-sensitive business data is ever added to it.
- Every Firm-scoped relation carries `firm_id uuid NOT NULL`, immutable after insert.
- No Firm-scoped relation has a nullable `firm_id`, a sentinel Firm, or a shared-Firm row.
- Every Firm-scoped relation has Row-Level Security **enabled and forced**, with a policy carrying both `USING` and `WITH CHECK`, and no role-based permissive exemption.
- A row policy predicate carries Firm identity only — never entitlement, capability, role, ownership, or wall state.
- Unset, empty, or malformed Firm context fails closed and fails loudly, enforced in the application **before the first statement** of the transaction, with the raising row policy as defence in depth — and tested on empty and populated relations alike.
- Firm context is transaction-scoped, set only from a `FirmContext` built from verified identity and membership, and never session-level.
- One transaction never spans two Firms, and Firm context never changes mid-transaction.
- The application runtime role is not a superuser, not a relation owner, and holds no `BYPASSRLS`.
- Row-Level Security is defence in depth and never the primary control; application and repository scoping are never omitted because it exists.
- Identifiers are application-generated UUIDv7 in native `uuid` columns; no database-generated default exists.
- A UUIDv7 is never a business, chronological, causal, event, or delivery ordering key, and never a deduplication or idempotency key; it may serve only as the final deterministic tiebreaker after an explicit business sort key.
- Primary keys are `id` alone; every Firm-scoped relation also carries `UNIQUE (firm_id, id)`; intra-context foreign keys are composite and carry `firm_id`, and their child-side indexes are created explicitly.
- No business or natural key is a primary key.
- Cross-bounded-context references are identifier-only, with no foreign key, no join, and no cross-context write.
- Every uniqueness constraint on Firm business data is Firm-scoped by construction; the only exception is a platform-generated identifier column, which carries no business meaning.
- Every index on a Firm-scoped relation includes `firm_id`, except the `id`-only primary key; and no index exists to serve a cross-Firm query.
- All timestamps are `timestamptz` in UTC, sourced from the injected `Clock`; the five distinct times are never collapsed.
- Destructive cascades are denied by default and require explicit recorded approval; no approval can authorize a cascade that removes audit, ledger, version, attestation, outbox, or event history.
- Deleting content never destroys the audit fact that it existed.
- The state change, its outbox row, and its audit row commit atomically or not at all.
- No external call and no waiting occurs inside a database transaction.
- Authoritative audit is persisted and append-only against `UPDATE`, `DELETE`, and `TRUNCATE` alike, enforced independently by ungranted privileges and by triggers, writable while its subject is being denied, and never editable by any actor operating through the application.
- Audit streams stay distinct and are never merged into one platform table.
- A security event with no verified Firm context is written to the platform-realm stream, carries no `firm_id`, never records a candidate Firm identifier as one, and is never exposed to a Firm.
- Outbox ordering, where offered, is per-subject and derived from an explicit sequence — never from an identifier, a wall clock, or a high-water-mark cursor — and never claims global ordering or exactly-once delivery.
- Outbox rows are claimed per Firm under established Firm context, with a claim token and lease; publication occurs outside any transaction; marking published requires a matching token; expired leases are reclaimable; and crash-after-publish redelivery is acknowledged.
- There is no cross-Firm outbox query, and no `BYPASSRLS`, superuser, owner exemption, or role-based permissive policy anywhere in the publication path.
- An idempotency record is Firm-scoped with forced Row-Level Security, has an immutable scope, key, and fingerprint, is enforced by a unique constraint, and never returns a stored result without re-evaluating current authorization.
- Entitlement is never a per-request database check.
- No Ethical Wall, conflict-checking, or per-user Matter-visibility mechanism exists in any persistence layer.
- Production migrations are forward-only, expand/contract, never edited after shipping, and never run as the application runtime role.
- Row policies, roles, and grants change only through reviewed migrations and hit the authorization approval gate.
- Firm-spanning data maintenance never disables Row-Level Security or grants `BYPASSRLS`.
- No authoritative data lives in an `UNLOGGED` relation.
- Isolation tests run on PostgreSQL, as the application runtime role, on synthetic data only, and cover both empty and populated relations.
- Referential-integrity checks bypass row policies; cross-Firm referencing is prevented by composite Firm-carrying keys, not by Row-Level Security.
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

The four relation classes (§2.4), and who owns each:

(a) Firm-scoped              — module-owned domain relations, plus the Platform
                               Foundation-owned outbox and idempotency infrastructure
                               relations. firm_id NOT NULL, forced Firm RLS.
(b) Firm-identifying         — the PlatformAdministration Firm registry, and nothing
                               else. No firm_id. Not FirmContext-based (§2.5).
(c) Platform-global          — Constitution Article 5 authoritative reference and
    reference                  configuration data. No firm_id. Never Firm-owned.
(d) Platform-realm security  — the IdentityAccess pre-authentication security-event
    / operations               relation, and nothing else. No firm_id, never Firm-
                               visible, append-only, access restricted to named
                               platform-realm services and security operators.
```

Unchanged from `docs/domain/06_Laravel_Module_Blueprint.md`: dependency direction is `Interface → Application → Domain`; Infrastructure depends on Application and Domain contracts; **the Domain layer never depends on Laravel, Eloquent, SQL, queues, HTTP, or SDKs**; Eloquent records handle mapping, casts, relationships, and scopes only, and are persistence models rather than domain aggregates.

---

## 25. Proposed implementation stages

**Proposed only. None of these is approved, scheduled, or assigned a story identifier**, and each requires its own entry in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`, with its own Definition of Ready, before implementation begins. **No story's status is asserted here; `docs/PROJECT_STATUS.md` is authoritative.**

1. **PostgreSQL continuous integration** — the suite running against PostgreSQL, as the application runtime role, with the four required check names preserved exactly. **Must be satisfied before `PF-080`** (§16.1). Already its own approved story, **with no assigned identifier**; not created, named, or renumbered here.
2. **Tenancy persistence conventions** — the baseline column set, the **four relation classes** and their declaration in every migration, `timestamptz`/`Clock` discipline, and the schema-level guard test asserting forced Row-Level Security and a policy on every Firm-scoped relation.
3. **Firm context and roles** — the transaction-scoped `SET LOCAL` contract, the **application-side pre-statement fail-closed check** in `PF-073`/`PF-082`, the raising context function behind it, the role and privilege separation, and their empty- and populated-relation tests. Depends on `PF-080`.
4. **Append-only audit persistence** — the privilege and trigger enforcement, and its tests.
5. **Transactional outbox persistence** — `PF-091`, implementing the claim/lease/publish protocol in §13.6 and the ordering rules in §13.5.
6. **Idempotency persistence** — `docs/adr/ADR-021-Idempotency-Persistence.md`: the scoped unique constraint, immutable fingerprint, re-authorization-on-replay behaviour, and a bounded retention window whose length a later approved policy owns.
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
| `docs/adr/ADR-021-Idempotency-Persistence.md` | The idempotency persistence decision this document implements (§14) |
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
