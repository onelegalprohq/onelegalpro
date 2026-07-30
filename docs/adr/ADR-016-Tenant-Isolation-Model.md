# ADR-016 — Tenant Isolation Model

## Status

**Proposed.** Not approved, and it authorizes nothing. Acceptance requires explicit owner approval recorded on the pull request; acceptance would authorize the architectural decision only, never implementation, deployment, or production access.

Authored by story **ARCH-012 — Data & Persistence Architecture**, alongside `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-017-…` through `ADR-020-…`.

## Context

Firm isolation is the load-bearing control of the entire platform. Constitution Article 27 makes a principal exist inside a Firm security realm; Article 28 requires every repository and query to be Firm-scoped with permission filtering before retrieval; Article 5 requires firm-owned data to be strictly isolated by `FirmContext` while requiring platform-global legal reference data **not** to use `FirmContext` as its ownership boundary at all. `docs/domain/06_Laravel_Module_Blueprint.md` requires tenant isolation "enforced in application logic, repositories, database policy where appropriate, and tests" and states plainly that **"global scopes alone are insufficient."** `docs/architecture/02_Product_Requirements.md` §9 repeats it as a Release 0.1 non-negotiable.

Every approved architecture assumes this. **None of them says how it is persisted.** `docs/architecture/03_Database_Design.md` was an empty placeholder, explicitly not populated by ARCH-006 through ARCH-011, and every one of those stories recorded that fact. Three questions have therefore never been decided:

1. **Physical model** — shared schema, schema-per-Firm, or database-per-Firm?
2. **What "database policy" means concretely** — a convention, a check constraint, or PostgreSQL Row-Level Security?
3. **How Firm context reaches the database** — and how it fails when it is absent?

The third question is the dangerous one. The platform runs on pooled connections. Any mechanism that stores Firm context on the **connection** rather than the **transaction** produces a defect where a connection returned to the pool carrying one Firm's context is handed to a request acting for another. That read passes every application-layer check, appears only under concurrency, and is a cross-Firm confidentiality breach in a product where the parties on either side of the boundary may be adverse to one another.

Per `docs/adr/ADR-001-Architecture-First.md` and the `AGENTS.md` database and authorization approval gates, this requires approved architecture before `PF-080` Firm Context, `PF-081` Tenant Resolver, `PF-082` Tenant Middleware, or any module schema exists.

## Decision

1. **A shared PostgreSQL schema.** One logical database, one shared set of relations, every Firm's rows co-resident. **No schema-per-Firm and no database-per-Firm.** The decisive reason is Constitution Article 5: platform-global legal reference data must exist **once, for every Firm**, so a per-Firm physical partition would either duplicate it — making a platform-global source a Firm-owned artefact — or require a shared location alongside the per-Firm one, reintroducing the shared-schema problem in addition to a per-Firm migration, connection, and drift surface. Secondary reasons: one verifiable migration state instead of N, no connection or cache multiplication by tenant count, and no per-Firm restore benefit, since physical recovery is a whole-database operation in either model.

2. **The cost is accepted explicitly, not minimized.** In a shared schema, **a single missing `firm_id` predicate is a cross-Firm disclosure** — a confidentiality breach and potentially a conflicts exposure, affecting clients who never consented and potentially unremediable once it has occurred. Decisions 3 through 8 exist because of that asymmetry. **Revisiting this decision is a database redesign** under the `AGENTS.md` gate, not an implementation choice.

3. **`firm_id` is mandatory on every Firm-scoped record.** `firm_id uuid NOT NULL`, and **immutable after insert** — a row never changes Firm. **No nullable `firm_id`, no sentinel or zero-UUID Firm, no "shared Firm" row.** A nullable tenancy column is the most common way a shared-schema isolation model fails, because every policy and predicate then needs an `OR firm_id IS NULL` branch that is trivially wrong and passes review.

4. **Exactly two relation classes, declared explicitly, with no third.** A relation is either **Firm-scoped** (carries `firm_id`, Row-Level Security enabled and forced) or **platform-global** (Constitution Article 5 reference data and static configuration; carries **no** `firm_id`; read-only to the application runtime role). The class is declared in the migration that creates the relation. Firm-owned annotations over platform-global data are Firm-scoped relations referencing the global row by identifier. Reclassifying a relation is a database redesign **and** a security change, requiring both approval gates.

5. **Firm isolation is enforced in four independent layers, each with a distinct failure mode, and none may be omitted because another exists.**
   - **Application logic** — an explicit `FirmContext`, built only from verified identity and membership (Article 27).
   - **Repositories** — every method scopes by Firm explicitly, as a query parameter and not as ambient state.
   - **PostgreSQL policy** — Row-Level Security, enabled and forced (Decision 6).
   - **Tests** — executed PostgreSQL tests, run as the application runtime role (Decision 9).

   **Row-Level Security is defence in depth. It is never the primary control.** A repository that omits its Firm predicate "because RLS will catch it" is a defect: it deletes a layer and makes the remaining one load-bearing. **Equally, application scoping never licenses omitting the policy.** Both hold at once.

6. **PostgreSQL Row-Level Security on every Firm-scoped relation, with `FORCE ROW LEVEL SECURITY`.** The policy carries **both `USING` and `WITH CHECK`** — `USING` alone permits inserting a row into another Firm. `FORCE ROW LEVEL SECURITY` is required because, without it, the relation's **owner** — which is the migration role — bypasses every policy, so migrations, backfills, and any statement run under ownership silently see and write every Firm's rows. Forcing it makes the policy a boundary rather than a boundary that applies only to the role least likely to make a mistake.

7. **The policy predicate compares `firm_id` to the current transaction's Firm context and nothing else.** It **never** references entitlement status, a capability, a role, an ownership rule, or a Matter-visibility condition.
   - **Never entitlement.** `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` Decision 8 and Constitution Article 46 evaluate entitlement at exactly two gates — authentication/session issuance and membership activation — precisely so an already-issued valid session runs to its normal expiry after a commercial lapse. A policy referencing entitlement would make it a per-request input on every statement, silently reversing that approved decision.
   - **Never authorization.** Authorization is composed in the application under Article 28. A row policy cannot see the request, cannot record a decision, and cannot be tested as authorization.
   - **Never an Ethical Wall.** `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` forbids stubbing, approximating, simulating, partially implementing, or renaming an absent control. A policy producing per-user Matter visibility would be exactly that, in the one layer nobody would inspect. **No wall-shaped column, flag, or policy exists.** Practice Management remains the sole wall authority when walls are built (Article 17).

8. **Firm context is transaction-scoped, via `SET LOCAL`, and never session-level.** `SET LOCAL` (equivalently `set_config(..., true)`) inside an explicit transaction. **A session-level `SET` or `set_config(..., false)` is prohibited without exception**, for the pooled-connection reason in Context: session state outlives the request that set it, and the resulting cross-Firm read passes every application check and appears only under concurrency. `SET LOCAL` reverts at commit or rollback, making the safe behaviour automatic rather than dependent on a `RESET` someone must remember. The same reasoning makes **advisory locks transaction-scoped only**. Additional rules: context is set once at transaction start from `FirmContext`; **never** from a header, parameter, route, body, cookie, hostname, custom domain, or email address; never changed mid-transaction; **one transaction never spans two Firms**; and **every statement touching a Firm-scoped relation — reads included — runs inside a transaction with context established.**

9. **Unset Firm context fails closed, and fails loudly.** The policy resolves context through a function that **raises** when the setting is absent, empty, or unparseable — not a bare `current_setting(..., true)` that yields `NULL`. **A silent empty result is specifically rejected as a design.** It converts a missing-context defect into a plausible "no records found" screen, and in a legal practice a lawyer told a Matter has no tasks behaves very differently from a lawyer told the system failed. Availability never outranks Firm isolation (Constitution Article 30).

10. **Role separation, with no bypass anywhere.** A **migration role** owning DDL and never serving a request; an **application runtime role** with DML only — **not the owner, not a superuser, no `BYPASSRLS`, no DDL** — holding no `UPDATE`/`DELETE` on append-only relations and read-only on platform-global relations; a narrowly scoped **outbox publication role** with access to the outbox relation and no business relation; and **no cross-Firm reporting or analytics role**, because no Reporting bounded context is approved (Constitution Article 44). **A single account used for both migrations and request serving is prohibited** — it makes forced Row-Level Security ineffective for the request path and erases the audit distinction between a data change and a schema change.

11. **Cross-Firm referential integrity is enforced by the database, not by convention.** Primary key is `id` alone; every Firm-scoped relation additionally carries `UNIQUE (firm_id, id)`; **every intra-context foreign key is composite, referencing `(firm_id, id)` and carrying the referencing row's own `firm_id`.** A single-column foreign key permits a row in Firm A to reference a row in Firm B — a cross-Firm link **invisible to Row-Level Security**, because each row independently satisfies its own policy. The cost is one extra unique index per relation and wider foreign-key columns; it is accepted. Detail in `docs/adr/ADR-017-Identifier-Persistence-Strategy.md`.

12. **Every uniqueness constraint on Firm-scoped data is Firm-scoped.** A global unique constraint on Firm data is prohibited — not only because two Firms may legitimately hold the same value, but because **a global constraint discloses the existence of another Firm's row through a constraint error.** A Firm receiving a uniqueness violation for a value it cannot see has learned that another Firm holds it, which for a client name or matter reference is an existence disclosure across a conflicts boundary. Genuinely platform-global uniqueness lives on platform-global relations, where there is no Firm to disclose.

13. **Firm-spanning data maintenance never disables the boundary.** Because forced Row-Level Security subjects the owner to policies, the migration role cannot see all Firms by default — which is intentional. Maintenance across Firms is performed **either** per-Firm, iterating Firms with `SET LOCAL` (**preferred**, because it keeps the boundary intact and makes progress resumable per Firm), **or** through a narrowly scoped, explicitly authorized, recorded, time-bounded maintenance path. **Never** by disabling Row-Level Security, dropping a policy, or granting `BYPASSRLS` — least of all to the runtime role.

14. **Isolation is tested on PostgreSQL, as the application runtime role.** **A Row-Level Security test run as the owner or a superuser proves nothing** — it passes, and it would pass with the policy deleted. The current suite runs on SQLite `:memory:`, where **none** of forced RLS, `SET LOCAL`, native `uuid`, `timestamptz` semantics, partial unique indexes, roles, privileges, or referential actions is testable. **Database-policy-level Firm isolation cannot be honestly tested on a different engine**, and a green suite that proves nothing about the control it names is the most dangerous possible outcome. **The PostgreSQL CI story therefore lands before `PF-080`** — already approved and Backlog in `docs/implementation/03_Engineering_Backlog.md`, and required by `docs/architecture/02_Product_Requirements.md` §8. **The four required `Protect main` check names are preserved exactly.** This ADR changes no CI configuration and no test.

15. **Row policies, roles, and grants are schema objects and change only through reviewed migrations**, and a policy or privilege change is a **security and authorization change** hitting that `AGENTS.md` gate, not only the database one.

16. **This ADR schedules nothing.** `PF-040` remains the next code story and remains Backlog. No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled.

## Alternatives considered

- **Database-per-Firm.** Rejected. It cannot host Constitution Article 5 platform-global reference data without either duplicating it per Firm — converting a platform-global source into a Firm-owned artefact, which Article 5 forbids in substance — or adding a second shared database, which reintroduces every shared-schema concern on top of per-Firm ones. It also multiplies migrations, connections, credentials, and drift surfaces by tenant count, and makes any future cross-Firm platform capability an integration project. Its genuine benefit — the strongest possible physical isolation — is real, and would be the right answer for a small number of very large tenants. It is the wrong answer for a founding-firm pilot expected to onboard many small firms.
- **Schema-per-Firm.** Rejected. It keeps most of database-per-Firm's operational cost while providing weaker isolation: a query executed under the wrong `search_path` is the same failure as a missing predicate, and `search_path` is exactly the kind of connection-level state Decision 8 rejects for Firm context. Article 5's global data has the same problem. Adopting per-module (not per-Firm) schemas later remains additive and is not foreclosed.
- **Application-only isolation, no Row-Level Security.** Rejected — it directly contradicts `docs/domain/06_Laravel_Module_Blueprint.md`, which requires database policy and states global scopes alone are insufficient. It leaves ad-hoc scripts, hand-written queries, and future maintainers' repository methods with no backstop, in the one failure mode the platform cannot absorb.
- **Row-Level Security as the only isolation control**, with repositories relying on it. Rejected — it makes one mechanism load-bearing, makes query correctness depend entirely on session state, and produces queries whose Firm scoping is invisible at the call site. It also collapses to nothing the moment a role with `BYPASSRLS` or ownership runs.
- **Row-Level Security without `FORCE`.** Rejected — the owner bypasses every policy, so the boundary would be absent for exactly the role running migrations, backfills, and emergency operations.
- **A policy with `USING` only.** Rejected — it permits writing a row into another Firm, which is worse than reading one.
- **Session-level `SET` for Firm context.** Rejected for the pooled-connection reason in Context. It is the single most likely path to a silent, intermittent, production-only cross-Firm disclosure.
- **Firm context from the request** (hostname, custom domain, header, parameter, or email domain). Rejected — Constitution Article 27 forbids it outright: those identify a candidate Firm and never prove membership.
- **A `NULL`-tolerant policy so unset context returns zero rows.** Rejected as unsafe-by-plausibility. It reads as safe, converts a bug into an empty screen, and in a legal practice an empty deadline list is a professional-responsibility hazard, not a cosmetic one.
- **A nullable `firm_id` for "shared" rows, or a sentinel Firm.** Rejected — it forces an `OR`-branch into every predicate and policy on the platform, and one wrong branch is a cross-Firm read.
- **Enforcing entitlement in a row policy.** Rejected — it silently reverses ADR-013 Decision 8 by making entitlement a per-request input, terminating exactly the sessions the approved lapse policy protects.
- **Implementing Matter visibility in a row policy.** Rejected — it is an approximated Ethical Wall, which `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` prohibits absolutely, placed where no reviewer would look for it and where it could not be disclosed honestly.
- **Single-column foreign keys, with same-Firm referencing left to the application.** Rejected — the resulting cross-Firm link is invisible to Row-Level Security, since each row satisfies its own policy. The extra index is a small price for a database-enforced fact.
- **Global unique constraints on Firm data.** Rejected — a constraint violation becomes an existence disclosure across a conflicts boundary.
- **Granting `BYPASSRLS` to the migration or runtime role to simplify backfills.** Rejected — it removes the isolation control during the riskiest operation on the platform. Decision 13 gives the alternative.
- **Testing isolation on SQLite to keep CI fast.** Rejected — it produces evidence of a control that does not exist.

## Consequences

- One migration state, one schema to verify, and one restore to perform. Operationally the simplest option, and its simplicity is a security property: fewer places for drift to hide.
- **Four layers cost real duplication.** The same Firm predicate is expressed in a repository, in a policy, and in a test. That duplication is the design, and reviewers must resist consolidating it.
- **Composite foreign keys cost an extra unique index per Firm-scoped relation** and wider index entries. Accepted deliberately for a database-enforced same-Firm guarantee.
- **Forced Row-Level Security makes routine data maintenance harder** — Decision 13's per-Firm iteration is more code than a single `UPDATE`. That is the correct trade; the alternative is turning the boundary off during the most dangerous operations.
- **Every statement runs in a transaction**, including reads. That is a real constraint on the application's data-access design and is why `PF-073` and `PF-082` matter.
- **Per-Firm point-in-time restore is not supported.** Physical recovery is whole-database; restoring one Firm to an earlier point would restore all of them. A Firm-scoped export is not a restore capability. Recorded in `docs/architecture/03_Database_Design.md` §17.2.
- **PostgreSQL CI becomes a hard prerequisite for `PF-080`**, which is already the approved ordering.
- **A statement-level-multiplexing connection pooler would break this design.** Recorded as a constraint on the future deployment architecture; no pooler or provider is selected.
- Adopting per-module PostgreSQL schemas later remains additive and separately approvable.

## Security and professional-responsibility consequences

- **A cross-Firm read in this product is a privilege and conflicts incident**, not a data-quality defect, and it may be unremediable. Every decision above is calibrated to that, and the shared-schema choice is defensible only because of it.
- **Row-Level Security catches what review missed** — an ad-hoc script, a hand-written query, a migration-time convenience, a future maintainer. It is never the primary control and never licenses a weaker repository.
- **`FORCE ROW LEVEL SECURITY` is what makes the boundary real**, at the cost of the maintenance constraint in Decision 13, which is paid rather than avoided.
- **Unset context fails closed and loudly.** A silent empty result is rejected because it is the failure mode most likely to cause a missed deadline.
- **Transaction-scoped context is an isolation control, not a style preference.** Session-level context on a pooled connection is a disclosure that passes every application check.
- **A global unique constraint discloses another Firm's rows through a constraint error**; Firm-scoped uniqueness is a confidentiality control.
- **A cross-Firm foreign key is invisible to Row-Level Security**; composite Firm-carrying keys close that hole.
- **Entitlement never enters a row policy**, or an approved decision protecting live sessions is silently reversed.
- **No Ethical Wall exists in any layer, including this one.** Every Firm member with Worklist access can see every Matter in the Firm, and that absence is disclosed in-product and in the pilot agreement — never softened by a database feature.
- **A `firm_id` value is not a secret and not a capability** (ARCH-019 §16); holding one grants nothing. It is nevertheless never placed in a URL parameter, query string, or any position routinely logged by intermediaries.
- **Isolation tested on the wrong engine, or as a bypassing role, is worse than untested.**
- **AI holds no authority here.** AI never grants access, authors or approves a policy, role, grant, or migration, and is never an authorization authority (Constitution Articles 6, 28, 39, 40). Release 0.1 contains no AI capability.
- **No certification, compliance, or production-readiness claim is made**, and no control described here is claimed to be implemented.

## Integration consequences

- **Platform Foundation** — `PF-080` `FirmContext`, `PF-081` Tenant Resolver, and `PF-082` Tenant Middleware gain their persistence contract: build context from verified identity and membership, and establish it as transaction-scoped state. `PF-073` Transaction Manager becomes the place transactions and context are established together. None of these is scheduled by this ADR.
- **IdentityAccess** keeps sole ownership of principals, membership, credentials, and sessions. This ADR adds no authentication or authorization path and no second authority.
- **`PlatformAdministration`** keeps ownership of `Firm`, whose identity `firm_id` carries. Whether `firm_id` carries a database foreign key to `Firm` is left as a recorded open decision (`docs/architecture/03_Database_Design.md` §8.3, §22).
- **Practice Management, Documents, Billing, Communications, Digital Presence, Branding, Integrations, `Workflow`, Legal Intelligence** — each keeps its own ownership unchanged and gains a common tenancy persistence contract. Cross-context references remain identifier-only (`docs/architecture/03_Database_Design.md` §8.2).
- **Legal Intelligence** gains the explicit platform-global relation class Constitution Article 5 requires, and the rule that Firm annotations over global data are Firm-scoped relations referencing it by identifier.
- **Billing** is unaffected in ownership. No monetary column exists; `PF-045` remains Backlog and deferred.
- **Testing infrastructure** (`PF-100`–`PF-104`) and the PostgreSQL CI story gain their obligations. Neither is scheduled here.

## Explicit non-goals

This ADR does **not**: implement anything; create or modify any migration, schema, table, column, index, policy, role, grant, source file, test, configuration, Docker file, CI workflow, dependency, or GitHub setting; define any module's physical data model or any table or column name; define which aggregates, attributes, states, or events exist in any context; select or endorse a hosting provider, managed database, cloud platform, region, connection pooler, backup product, secret- or key-management product, or any other vendor, product, or package; define deployment, infrastructure, network, capacity, sizing, sharding, partitioning, replication, or failover design; define a PostgreSQL version, extension set, or server configuration; define, execute, schedule, or claim a backup or restore test; create a Reporting context, cross-Firm reporting, analytics, benchmarking, or a cross-Firm read role — Constitution Article 44 reserves that context, which this ADR **neither approves nor permanently prohibits**; define search, full-text, or vector storage; introduce `Money`, `Currency`, or any monetary column, or schedule or unblock `PF-045`; introduce an AI capability or modify `docs/architecture/05_AI_Architecture.md`; create a second authentication, authorization, entitlement, or privileged-access path; define a Firm-level suspension or emergency-disable capability; define an Ethical Wall, conflict-checking, or per-user Matter-visibility mechanism in any layer; change `phpunit.xml`, any CI workflow, or the four required `Protect main` check names; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; claim that any described control is implemented, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

**Proposed conceptual architecture only.** It authorizes no application code, schema, migration, policy, role, grant, dependency, package, infrastructure, Docker change, CI change, environment change, production configuration, or GitHub setting. **No capability or control described here is claimed to be implemented.**

`PF-040` — AggregateRoot remains the next repository implementation story and remains **Backlog**. No story is In Progress. The PostgreSQL CI story remains **Backlog** and must land before `PF-080`; its story identifier is an open tracking item recorded in `docs/architecture/03_Database_Design.md` §21.10 and §22, and this ADR neither creates nor renumbers it.

The story ID is **ARCH-012**; the architecture document it accompanies is the pre-reserved `docs/architecture/03_Database_Design.md` rather than a newly numbered file.
