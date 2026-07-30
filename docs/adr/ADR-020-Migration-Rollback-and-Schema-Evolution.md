# ADR-020 — Migration, Rollback, and Schema Evolution

## Status

**Proposed.** Not approved, and it authorizes nothing. Acceptance requires explicit owner approval recorded on the pull request; acceptance would authorize the architectural decision only, never implementation, deployment, or production access.

Authored by story **ARCH-012 — Data & Persistence Architecture**, alongside `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…` through `ADR-019-…`, and `docs/adr/ADR-021-Idempotency-Persistence.md`.

## Context

`AGENTS.md` establishes two rules and one gate: **"Never edit historical migrations"**, **"Modify only files required by the approved story"**, and **approval required before a database redesign**. Constitution Article 8 establishes the discipline those rules mirror — published versions are immutable and corrections create new records, "the same rule that historical migrations are never edited."

Beyond that, migration practice is undecided. Four questions matter, and each has a default answer that is wrong in production:

1. **Rollback.** Laravel supplies `down()`, and the framework's idiom is `migrate:rollback`. A story that names it as its recovery plan has no recovery plan: a reverse migration runs untested code against a state it has never seen, and routinely cannot restore what the forward migration destroyed.
2. **Compatibility across a deployment.** Any single migration that both adds and removes creates a window in which running code and live schema disagree.
3. **Data maintenance under forced Row-Level Security.** `docs/adr/ADR-016-Tenant-Isolation-Model.md` applies `FORCE ROW LEVEL SECURITY`, which subjects the **relation's owner** — the migration role — to its policies. A backfill that must touch every Firm therefore cannot simply run. The obvious workaround is to disable the policy or grant `BYPASSRLS`, which would remove the platform's primary isolation control during its riskiest operation.
4. **Locking.** Several routine-looking DDL statements take `ACCESS EXCLUSIVE` locks or rewrite whole relations. On an empty repository this is invisible; on a live Firm's database it is an outage.

There is a further, specific hazard in this repository. **Row policies, roles, and grants are schema objects.** If they change through migrations — and they must — then an ordinary-looking migration can silently weaken Firm isolation. Nothing currently routes such a change through the authorization approval gate rather than only the database one.

Finally, `docs/PROJECT_STATUS.md` and `docs/architecture/02_Product_Requirements.md` §8 make an **approved database design** one of the recorded evidence items for production access, alongside an **executed** restore test. Migration policy is where "approved design" meets "what actually happens to a live database", so it needs deciding before any schema exists — which is now, while there are only three Laravel skeleton migrations and no `app/Modules`.

## Decision

1. **Production migrations are forward-only.** No down-migration is relied upon in production, ever.
   - A `down()` method may exist for local development convenience. **It is never the production recovery plan**, and a story that documents "roll back with `migrate:rollback`" has documented nothing.
   - **Production recovery from a bad migration is: stop, assess, and roll forward with a corrective migration** — or, where data was lost, restore and roll forward (`docs/architecture/03_Database_Design.md` §17).
   - **Historical migrations are never edited** (`AGENTS.md`). A mistake in a shipped migration is corrected by a new migration.

2. **Expand / contract, in three separate migrations and three separate deployments.**

   | Phase | Content | Reversibility — accurately |
   |---|---|---|
   | **Expand** | Additive only — a new nullable column, relation, or index; a constraint added `NOT VALID` | **Safely abandonable.** The new object is unused, so ceasing to use it leaves the prior working state. Removing it is itself a contract-phase change. |
   | **Migrate** | Backfill and dual-write; validate the constraint; switch reads | **Not reversible by inaction.** Interrupting it is *safe* — the schema still supports both code paths — but **data already written stays written.** Abandoning a half-finished backfill leaves partially populated data the next attempt and every reader must tolerate. This is why backfills are idempotent and restartable (Decision 3). |
   | **Contract** | Remove the old column, relation, index, or constraint | **Not reversible at all.** Dropped data is gone; recovery is restore-and-roll-forward (Decision 1). Hence: runs only once **no code path references it**, under the Decision 7 approval gate. |

   **The property that holds across all three phases is compatibility, not reversibility:** at every phase boundary both the previous and the next application version work against the live schema, which is what makes an application-level rollback possible. **That is a different claim from the migration being undoable, and this ADR does not make the stronger one.**

   **Collapsing phases is prohibited**, because it creates a window in which running code and live schema disagree — the failure that makes a deployment an outage rather than a change.

3. **Backfills are batched, idempotent, restartable, and interruptible.** A backfill that must complete in one statement is an outage. It never runs in the same transaction as the schema change that enabled it. **A backfill computing a domain value applies the domain's own rules**, not a hand-written SQL approximation; where that is impractical, the story **records what approximation was made and why** rather than leaving the divergence undocumented.

4. **Firm-spanning data maintenance never disables the isolation boundary.** Because forced Row-Level Security subjects the owner to policies (Context), maintenance across Firms is performed **either**:
   - **(a) per-Firm, iterating Firms and establishing each one's context with `SET LOCAL`** — the **preferred** approach, because it keeps the boundary intact and makes progress resumable per Firm; **or**
   - **(b) through a narrowly scoped, explicitly authorized, recorded, time-bounded maintenance path.**

   **Never** by disabling Row-Level Security, dropping a policy, or granting `BYPASSRLS` — least of all to the application runtime role. A migration that turns the isolation boundary off to get its work done has removed the control for the duration of the platform's riskiest operation.

5. **Row policies, roles, grants, and `FORCE ROW LEVEL SECURITY` state change only through migrations**, under the same review as any other schema change — **and a policy, role, or privilege change is a security and authorization change**, hitting that `AGENTS.md` approval gate and not only the database one. This is the decision that stops Firm isolation from being weakened by a migration that reads as routine.

6. **Each of the following is prohibited without an approved plan recorded in the story**, because each takes a long or blocking lock or rewrites a relation:
   - adding a column with a **volatile** default, or adding `NOT NULL` to a populated column in one step;
   - **changing a column's type** on a populated relation;
   - **adding a constraint that validates immediately** — instead: add `NOT VALID`, then `VALIDATE CONSTRAINT` as a separate step;
   - **creating or dropping an index without `CONCURRENTLY`** on a populated relation — and `CREATE INDEX CONCURRENTLY` cannot run inside a transaction, so it is its own migration;
   - **renaming** a relation or column that running code references;
   - **changing a collation**, which is a reindexing event with a plan (`docs/architecture/03_Database_Design.md` §9.4);
   - **Row-Level Security and policy DDL**, which is easy to mistake for metadata-only work and is the most consequential omission from a list like this:
     - `ALTER TABLE … ENABLE ROW LEVEL SECURITY`, `… FORCE ROW LEVEL SECURITY`, and their `DISABLE`/`NO FORCE` counterparts take an **`ACCESS EXCLUSIVE`** lock on the relation, blocking every reader and writer for the duration — and queueing behind any long-running transaction already holding a weaker lock, which then blocks everything arriving after them;
     - `CREATE POLICY`, `ALTER POLICY`, and `DROP POLICY` likewise take **`ACCESS EXCLUSIVE`** on the relation;
     - therefore **enabling or changing isolation on a populated, live relation is a planned operation with a `lock_timeout` and a bounded retry**, never a routine statement, and never attempted behind an open long transaction — an unbounded wait on `ACCESS EXCLUSIVE` stalls the entire relation rather than only the migration;
     - **and a failed or abandoned policy migration must never leave Row-Level Security disabled or a policy dropped.** That is the specific hazard this bullet exists for: the isolation control absent, silently, with nothing failing (Decision 5).
   - any statement holding `ACCESS EXCLUSIVE` for an unbounded period.

   **No zero-downtime claim is made** for any migration.

7. **Destructive schema changes require explicit approval** under the `AGENTS.md` destructive-operations gate — dropping a relation or column, narrowing a type, adding a cascade, or removing a constraint. **Dropping anything holding audit, posted, or version history requires its own approval and is never a routine contraction step**, and a change that would destroy the audit fact of a record's existence is prohibited **regardless of approval**, because the gate cannot authorize a constitutional violation (Constitution Articles 19, 23; `docs/adr/ADR-018-Audit-Persistence-and-Append-Only-Enforcement.md` Decision 13).

   **This gate is also part of what makes append-only audit meaningful.** `docs/adr/ADR-018-…` Decision 2a is explicit that its privilege and trigger enforcement binds actors operating through the application and **is not claimed to restrain a role able to disable a trigger, alter a grant, or drop the relation.** What restrains that role is role separation (Decision 8), the approval gate in Decision 5 on any migration touching audit privileges or triggers, and this gate on dropping the relation itself — **procedural and organizational controls, which this ADR does not represent as database-enforced.**

8. **Migrations never run as the application runtime role**, and the runtime role holds no DDL privilege (`docs/adr/ADR-016-Tenant-Isolation-Model.md` Decision 10). A single account used for both migrations and request serving is prohibited.

9. **No automatic migration on deploy** without an approved operational procedure. The existing Docker development environment already establishes "no automatic migrations" as the repository's precedent (`PF-010`).

10. **Migration state is authoritative and verified.** A hand-applied schema change is a defect, not a shortcut; **drift between recorded migration state and actual schema is detected, not discovered later during an incident.**

11. **Module-owned migrations, with Platform Foundation owning platform infrastructure relations.** Each module's migrations live in its own `Database/` directory and touch **only** its own relations (`docs/domain/06_Laravel_Module_Blueprint.md`). Foundation — a shared technical-primitive layer, **not a bounded context** — owns the outbox and idempotency relations (`docs/adr/ADR-019-Transactional-Outbox-Persistence.md` Decision 2). **No module migrates, alters, reads, or writes another context's relations.**

12. **Cross-module migration ordering is minimal by construction**, because cross-bounded-context foreign keys are prohibited (`docs/architecture/03_Database_Design.md` §8.2). What remains: a module's own migrations are ordered internally, and a dependency on a Foundation relation is a real ordering dependency **recorded explicitly** by the depending story. A migration never depends on another module's relation existing, because it never references one.

13. **Every new relation declares its class** — **Firm-scoped, Firm-identifying, or platform-global** — in the migration that creates it (ADR-016 Decision 4), and a Firm-scoped relation ships with `firm_id NOT NULL`, Row-Level Security **enabled and forced**, and a policy carrying both `USING` and `WITH CHECK`, with **no role-based permissive exemption**. A **schema-level guard test enumerates relations** and asserts this, so a new table cannot silently ship unprotected (`docs/architecture/03_Database_Design.md` §16.3). **A migration never adds a second relation to the Firm-identifying class**, which contains the Firm registry alone; doing so requires its own approved decision.

14. **Migration behaviour is verified on PostgreSQL, as the application runtime role where the assertion concerns runtime access.** A forward-only run from an empty database succeeds; **no test depends on a down-migration**; a backfill is restartable and produces the same result when re-run; and the fail-closed guarantees are asserted on **both an empty and a populated relation** (ADR-016 Decision 14). On SQLite none of Row-Level Security, roles, privileges, `timestamptz` semantics, partial unique indexes, `SKIP LOCKED`, or referential actions is testable at all, so **the PostgreSQL CI requirement must be satisfied before `PF-080`** — already its own approved story. **That story has no assigned identifier, and none is asserted or invented here**; assigning one is a tracking-file change this story is barred from making. **The four required `Protect main` check names are preserved exactly.** This ADR changes no CI configuration and no test.

15. **The three existing Laravel skeleton migrations are framework starter artefacts, not approved domain schema**, and ownership of the decision about them **splits rather than sitting with one context**:
    - **`users` is IdentityAccess's, and only `users`.** A stock `users` table is not the principal model; IdentityAccess owns principals, credentials, and sessions (Constitution Article 26). Whether it is retained, replaced, or removed is **its own approved story's decision**.
    - **`cache` and `jobs` are framework-operational relations belonging to the Platform Runtime**, and their fate is a **Platform Foundation runtime** decision. They are not identity concerns, and **IdentityAccess has no ownership of them** — attributing them to IdentityAccess would misassign a runtime concern to the identity context.
    - **Neither decision is made here, and this ADR modifies none of the three.**

16. **This ADR schedules nothing and asserts no story's status.** No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled; no identifier is assigned to any unnamed requirement; **no migration is created, edited, or run**; and **`docs/PROJECT_STATUS.md` is the authoritative record of story status.**

## Alternatives considered

- **Reversible migrations with a maintained `down()` as the production rollback path.** Rejected. A reverse migration is untested code running against a state it has never seen; it cannot restore a dropped column's data, a narrowed type's precision, or a deleted row; and it encourages destructive forward steps on the false comfort that they are reversible. Expand/contract plus roll-forward achieves the actual goal — a safe path off a bad change — without that comfort.
- **Forward-only with no `down()` at all, including locally.** Rejected as unnecessarily strict. A local `down()` is genuinely useful for iterating on a branch. Decision 1 permits it and forbids relying on it, which is the honest position.
- **Single-migration schema changes** (add and remove together), relying on a deployment window. Rejected — it guarantees a period where running code and live schema disagree, and the window is exactly when a rollback of application code becomes impossible.
- **Backfilling in one statement** for simplicity. Rejected — an unbounded `UPDATE` on a populated relation holds locks and generates WAL for its whole duration, cannot be interrupted safely, and cannot resume.
- **Describing the migrate phase as "reversible by doing nothing."** Rejected as inaccurate, and the inaccuracy matters: it is *safe* to stop, but the data already written stays written, so a half-finished backfill leaves partially populated data every reader must tolerate (Decision 2). Compatibility across the phase boundary is the property that actually holds, and overstating it would invite abandoning a backfill on the belief that nothing was left behind.
- **Treating Row-Level Security and policy DDL as metadata-only changes.** Rejected — each takes an `ACCESS EXCLUSIVE` lock on the relation, so enabling isolation on a live populated relation is a planned, lock-timeout-bounded operation; and a failed policy migration can leave the isolation control off with nothing failing (Decision 6).
- **Granting `BYPASSRLS` to the migration role so backfills can see every Firm.** Rejected — it removes the primary isolation control during the platform's riskiest operation, and it would apply to every future migration, not only the one that asked. Decision 4 gives two acceptable alternatives.
- **Temporarily disabling Row-Level Security or dropping a policy during a migration.** Rejected for the same reason, with the additional hazard that a failed migration can leave the policy off, silently, with no failing test — the isolation control absent and nothing indicating it.
- **Making the application runtime role the relation owner** so migrations and runtime share one account. Rejected — it makes forced Row-Level Security ineffective for the request path (the owner bypasses policies unless forced, and owning the relation grants DDL), and it erases the audit distinction between a data change and a schema change.
- **Treating policy and grant changes as ordinary database changes.** Rejected — a row policy **is** the isolation control. Routing its change through only the database gate would let Firm isolation be weakened by a migration that reads as routine (Decision 5).
- **Automatic migration on deploy** for operational convenience. Rejected — it makes the highest-risk operation on the platform an unattended side effect of a deployment, with no approval, no plan, and no human watching the lock.
- **Editing a historical migration** to fix a mistake before it reaches production. Rejected — `AGENTS.md` forbids it outright, and it desynchronizes any environment that already applied it, including a developer's and a restored one.
- **Squashing migrations periodically** to reduce their number. Rejected as an unapproved edit of history. It also destroys the record of how the schema reached its current state, which is the migration equivalent of rewriting audit. It is not declared permanently impossible; it would require its own approved decision.
- **A schema-diff / declarative-state tool** generating changes automatically. Rejected for this decision — a generated diff routinely produces exactly the blocking statements Decision 6 prohibits, and it removes the human plan that makes a change safe. No tool is selected or endorsed either way.
- **Deferring migration policy until the first module needs a schema.** Rejected — the current moment, with three skeleton migrations and no `app/Modules`, is the only point at which this policy costs nothing to adopt. Deciding later means retrofitting it onto shipped migrations.

## Consequences

- Migrations become **smaller, more numerous, and more deliberate**. A change that would have been one migration becomes three across three deployments, each individually safe.
- **A safe change takes longer to complete.** Accepted: on a live legal-practice database, an outage or a lost column is not a recoverable inconvenience.
- **Forced Row-Level Security makes routine data maintenance harder.** Decision 4's per-Firm iteration is more code than a single `UPDATE`. That is the correct trade; the alternative is turning the boundary off during the most dangerous operation.
- **Policy and grant changes now hit the authorization approval gate**, which slows them deliberately and makes weakening Firm isolation a reviewed act. **They are also blocking DDL**, so the review must consider a lock plan and not only the predicate (Decision 6).
- **No zero-downtime claim exists**, so every migration needs an operational plan that the deployment architecture — not yet approved — must accommodate.
- **Drift detection is a real obligation**, needing a mechanism nobody has built. Recorded as an obligation rather than assumed.
- **The migration count grows monotonically** and squashing is prohibited. That is a legibility cost, accepted in exchange for an unrewritten history.
- **The schema-level guard test in Decision 13 becomes load-bearing**: it is what makes "every Firm-scoped relation is protected" a verified property rather than a review habit.
- **PostgreSQL CI is a hard prerequisite for `PF-080`**, which is already the approved ordering.
- The three Laravel skeleton migrations remain, unmodified and explicitly unblessed, until IdentityAccess's own story decides (Decision 15).

## Security and professional-responsibility consequences

- **A migration is the most privileged operation in the system.** It runs DDL, can alter policies and grants, and can destroy data — which is why role separation (Decision 8), the authorization gate on policy changes (Decision 5), and the destructive-operations gate (Decision 7) all apply to it.
- **Disabling Row-Level Security during a migration would remove the platform's primary isolation control at its riskiest moment**, and a failed migration could leave it off silently, with nothing failing. Decision 4 forbids it and supplies alternatives.
- **A row-policy change is a Firm-isolation change.** Treating it as ordinary schema work is how a shared-schema platform loses isolation through a routine-looking pull request.
- **Forward-only recovery is honest about what a rollback cannot do.** Documenting `migrate:rollback` as a recovery path would be a false assurance about data that is already gone — and in a legal practice, lost Matter, deadline, or attestation data is a client-facing professional failure, not a data-quality metric. Decision 2's reversibility column is stated accurately for the same reason: **an overstated reversibility claim is itself a safety hazard.**
- **Destructive changes never destroy the audit fact that a record existed** (Constitution Articles 19, 23), and no approval can authorize that (Decision 7).
- **A backfill that approximates a domain rule can silently produce wrong legal-practice data** — a wrong deadline, a wrong status, a wrong attribution. Decision 3 requires the domain's own rules, or a recorded approximation, never an undocumented divergence.
- **A hand-applied schema change is undetectable drift** and defeats every guarantee this document makes about what the database enforces (Decision 10).
- **Migrations run on synthetic data in non-production**; a restore of production data into a non-production environment is a data-classification event, not a convenience (`docs/architecture/04_Security_Architecture.md` §5).
- **An unverified isolation control is worse than an absent one**, because it produces evidence of a control that does not exist. Decision 14's PostgreSQL and correct-role requirements exist for that reason.
- **AI holds no authority here.** AI never authors, approves, applies, reverses, squashes, or edits a migration, never changes a policy, role, or grant, and never authorizes a destructive operation. Release 0.1 contains no AI capability.
- **No certification, compliance, production-readiness, or legal-sufficiency claim is made; no legal review has occurred or is claimed.** An approved database design is **one** of the evidence items in `docs/architecture/02_Product_Requirements.md` §8; an approved deployment architecture, an **executed** restore test, monitoring, a documented incident procedure, and completed Thai-qualified legal review all remain outstanding, alongside the open legal questions in `docs/architecture/03_Database_Design.md` §22.2. **No restore test has been executed.**

## Integration consequences

- **Every future module story** inherits the expand/contract discipline, the relation-class declaration in Decision 13, the module-ownership rule in Decision 11, and the prohibited-statement list in Decision 6.
- **`PF-080` / `PF-081` / `PF-082`** — the Firm context, resolver, and middleware stories inherit Decision 4's per-Firm maintenance pattern and Decision 5's authorization gate on policy changes. Not scheduled here.
- **`PF-091` / `PF-092`** — the Foundation outbox relation is a Foundation-owned migration under Decision 11, and a module's dependency on it is a recorded ordering dependency under Decision 12. The **idempotency** relation is likewise Foundation-owned infrastructure, but its persistence decision is `docs/adr/ADR-021-Idempotency-Persistence.md`'s, not this ADR's and not ADR-019's.
- **IdentityAccess** owns the decision on the Laravel skeleton **`users`** table, and only that one (Decision 15). **`cache` and `jobs` belong to the Platform Foundation runtime.** Nothing here pre-empts either decision.
- **The PostgreSQL CI requirement** (its own approved story, which must precede `PF-080`) gains the migration test obligations in Decision 14 and the requirement that the four `Protect main` check names be preserved exactly. **It has no assigned identifier**; that is an open tracking item recorded in `docs/architecture/03_Database_Design.md` §21.10 and §22.1, and **this ADR neither creates, names, nor renumbers it.**
- **The future deployment architecture** must accommodate migrations that are not zero-downtime, a migration role distinct from the runtime role, no automatic migration on deploy, and drift detection. **No product, provider, or topology is selected here.**
- **Testing infrastructure** (`PF-100`–`PF-104`) gains the forward-only-run and restartable-backfill obligations. Not scheduled here, and no status is asserted.

## Explicit non-goals

This ADR does **not**: implement anything; create, edit, run, squash, revert, or delete any migration, schema, table, column, index, constraint, policy, role, grant, source file, test, configuration, Docker file, CI workflow, dependency, or GitHub setting; modify the three existing Laravel skeleton migrations or decide their fate; define any module's physical data model or any table or column name; define which aggregates, attributes, states, or events exist in any context; select or endorse a migration tool, schema-diff or declarative-state tool, ORM, deployment pipeline, orchestrator, hosting provider, managed database, connection pooler, backup product, monitoring product, or any other vendor, product, or package; define deployment, release, environment, infrastructure, network, capacity, or scaling design, or a deployment runbook; claim zero-downtime migration, any availability target, or any service level; define, execute, schedule, or claim a backup or restore test, or a disaster-recovery procedure; define an audit-purge, retention, partition, or partition-detachment path, assert any retention period, or authorize any change that would destroy an audit fact; resolve any of the open legal questions in `docs/architecture/03_Database_Design.md` §22.2, including any conflict between erasure obligations, retention obligations, legal hold, and backup retention; decide the fate of the `cache` or `jobs` skeleton relations, which is a Platform Foundation runtime decision, or of `users`, which is IdentityAccess's; assign a story identifier to the PostgreSQL CI requirement, or assert any story's status; change `phpunit.xml`, any CI workflow, or the four required `Protect main` check names; create a Reporting context, cross-Firm reporting, analytics, or a cross-Firm read role — Constitution Article 44's reserved Reporting context is **neither approved nor permanently prohibited** here; introduce `Money`, `Currency`, or any monetary column, or schedule or unblock `PF-045`; introduce an AI capability or modify `docs/architecture/05_AI_Architecture.md`; define an Ethical Wall, conflict-checking, or per-user visibility mechanism in any layer; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness or that the production-access evidence list is satisfied; claim that any described practice is implemented, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

**Proposed conceptual architecture only.** It authorizes no application code, schema, migration, policy, role, grant, dependency, package, infrastructure, Docker change, CI change, environment change, production configuration, or GitHub setting. **No practice described here is claimed to be implemented**, and **no migration has been created, edited, or run by this story.**

**No story's status is asserted here; `docs/PROJECT_STATUS.md` is the authoritative record** of what is current, next, and complete. The PostgreSQL CI requirement is its own approved story, **with no assigned identifier**, and must be satisfied before `PF-080`.

The story ID is **ARCH-012**; the architecture document it accompanies is the pre-reserved `docs/architecture/03_Database_Design.md` rather than a newly numbered file.
