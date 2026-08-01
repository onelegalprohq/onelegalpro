# ADR-024 — PostgreSQL Runtime, Backup, Restore, and Recovery

## Status

**Accepted.** Explicit repository-owner approval was recorded on PR #35 on 1 August 2026 after independent review and all four required `Protect main` checks passed on commit `a163fce`. Acceptance authorizes this architectural decision only, never implementation, deployment, provider engagement, or production access.

Authored by story **ARCH-013 — Deployment & Operations Architecture**, alongside `docs/architecture/20_Deployment_Operations_Architecture.md`, `docs/adr/ADR-022-…`, `ADR-023-…`, `ADR-025-…`, and `ADR-026-…`.

**Depends on `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…` through `ADR-021-…`, which are Approved and Accepted** — explicit repository-owner approval recorded on PR #34 on 1 August 2026, alongside the eight Thai-qualified legal-review decisions in `docs/legal/ARCH-012-Thai-Legal-Review.md`. **ARCH-013 is synchronized with that approved baseline.** ARCH-012's approval schedules no implementation and authorizes no deployment or production access, and neither does this ADR. This ADR **satisfies** ARCH-012's recorded delegation rather than reopening any of its decisions.

**No backup has been taken and no restore test has been executed.** Nothing in this ADR claims, schedules, or substitutes for one.

## Context

`docs/architecture/03_Database_Design.md` §19 and §22.1 delegate four items to "the deployment architecture" by name, and refuse to decide them itself:

| Delegated item | ARCH-012 reference |
|---|---|
| PostgreSQL minimum version, extension set, and server configuration | §19, §22.1 |
| Connection pooling topology | §4.3, §22.1 |
| Backup, PITR, encryption-at-rest, and key-management mechanisms | §17, §22.1 |
| Whether `firm_id` carries a foreign key to `Firm` | §8.3, §22.1 — **not this ADR's**; it belongs to the implementing `PF-080`/`PlatformAdministration` story |

Alongside the delegation, ARCH-012 records constraints that any deployment must satisfy — a required capability set (§16.1, §22.1), an incompatibility with statement-level connection multiplexing (§4.3), mandatory role separation (§4.1), a prohibition on `UNLOGGED` authoritative relations (§17.1), the requirement that point-in-time recovery remain possible (§17.1), and three consequences it states plainly rather than solving: **per-Firm point-in-time restore is not supported** (§17.2), **a restore can replay already-delivered events and permit duplicate side effects** (§17.4), and **a restore must not resurrect one Firm's data into another Firm's context** (§17.1).

Two further facts frame this decision. `docs/architecture/02_Product_Requirements.md` §2 item 22 puts **"backups and an executed restore test"** inside Release 0.1 scope as an Operations deliverable, and §8 makes an **executed and recorded** restore test a production-access evidence item. And `compose.yaml` already runs `postgres:16-alpine` and `redis:7-alpine` in development, with Redis reachable but assigned to nothing — `.env.example` routes cache, session, and queue to the `database` driver and documents Redis as available "for future approved use". **Redis's production role has never been decided.**

## Decision

### PostgreSQL runtime

1. **PostgreSQL 16 is the Release 0.1 minimum version.** It matches the engine the development environment already runs (`compose.yaml`), it supports every capability ARCH-012 requires, and pinning a floor prevents a provider default from silently supplying an older engine. A later major version may be adopted by its own approved decision; **a version below 16 is prohibited.**

2. **The engine must provide, and a deployment is invalid without, the capability set ARCH-012 §16.1 and §22.1 require:** Row-Level Security including `FORCE ROW LEVEL SECURITY`; transaction-scoped settings (`SET LOCAL` / `set_config(..., true)`); native `uuid`; `timestamptz`; partial unique indexes; deferrable constraints; `FOR UPDATE SKIP LOCKED`; database roles, privileges, and grants; triggers able to reject `UPDATE`, `DELETE`, and `TRUNCATE`; real foreign-key referential actions; and locale/ICU collation sufficient for the Thai-text-correctness requirements (§9.4). **A managed service that withholds role management, policy management, or `FORCE ROW LEVEL SECURITY` cannot host this platform**, whatever else it offers. That is a selection constraint, not a product judgement — **no provider is selected here** (`docs/adr/ADR-023-…`, `docs/adr/ADR-022-…` Decision 1).

3. **Three database roles are separate, exactly as accepted `docs/architecture/03_Database_Design.md` §4.1 defines them, and no credential spans them.**

   | Role | Privileges | Constraints |
   |---|---|---|
   | **Migration role** | DDL on the relations it owns; DML for backfills | **Owns the schemas and relations.** **Subject to `FORCE ROW LEVEL SECURITY` like every other role.** **Never used to serve a request.** Not a superuser, no `BYPASSRLS`. Its use is authorized and recorded, and its credential is **non-standing** — issued for a named, authorized migration operation and bounded to it (`docs/adr/ADR-026-…` Decision 5). |
   | **Application runtime role** | `SELECT`, `INSERT`, `UPDATE`, `DELETE` as each relation's rules permit | **Not the owner, not a superuser, no `BYPASSRLS`, no DDL.** No `UPDATE`, `DELETE`, or `TRUNCATE` on append-only relations. Read-only on platform-global relations. |
   | **Outbox publication role** (when `PF-091`/`PF-092` introduce it) | `SELECT` and bookkeeping `UPDATE` on the outbox relation only, plus `SELECT` on the Firm registry to iterate Firms | **No access to any business relation.** Subject to forced RLS on the outbox with no exemption. No `BYPASSRLS`, no superuser, no owner exemption, no role-based permissive policy. |
   | **Reporting / analytics role** | **Does not exist.** | Creating a cross-Firm read role would be Constitution Article 44's reserved Reporting bounded context arriving through a privilege grant. |

   **A single database account used for migrations and for serving requests is prohibited.** It would make forced RLS ineffective for the request path, give request-time code the ability to alter schema, and erase the audit distinction between a data change and a schema change.

   **What this ADR adds to §4.1, without altering it:** the migration role's credential is **non-standing** and is **never held standingly by a human** (Decision 4; `docs/adr/ADR-023-…` Decision 13). §4.1 already requires that its use be authorized and recorded; this makes the credential lifecycle match that requirement.

3a. **How forced Row-Level Security behaves during a migration, stated honestly.**

   - Ordinarily a relation's owner is **exempt** from its own policies. **`FORCE ROW LEVEL SECURITY` removes that exemption**, so the migration role — which owns the relations — **is subject to the policies while running a migration** (`docs/architecture/03_Database_Design.md` §3.3.2, §4.1). That is intentional, and it is why Firm-spanning maintenance iterates Firms with `SET LOCAL` rather than reading every Firm's rows in one pass (§15.5; `docs/adr/ADR-020-…` Decision 4).
   - **No role in this model holds `BYPASSRLS`, and none is a superuser.** Those are the two attributes that would defeat forced RLS outright, and **granting either — to any role, for any duration — is prohibited.**
   - **The exemption returns the moment `FORCE ROW LEVEL SECURITY` is off.** A migration that disables it, or drops a policy, silently restores the owner exemption with nothing failing. That is the specific hazard `docs/adr/ADR-020-…` Decision 6 and `docs/adr/ADR-023-…` Decision 15 prohibit, and `docs/adr/ADR-026-…` Decision 7 verifies against after every migration.
   - **DDL is not itself constrained by a row policy.** Forced RLS constrains the **rows** a statement sees; it does not prevent the owning role from altering a relation, its policies, or its grants. What constrains that is the approval gate on policy, role, and grant changes (`docs/adr/ADR-020-…` Decision 5), not the database.

4. **No human holds a standing superuser credential, a standing `BYPASSRLS` credential, or a standing migration-role credential** (`docs/adr/ADR-023-…` Decision 13). Where a managed provider retains its own administrative account as a property of its construction, that account is named, its holder is named, its use is authorized and recorded, and it is never used to read Firm business content — recorded as residual risk, not concealed (`docs/adr/ADR-023-…` Decision 17).

5. **Connection pooling must preserve transaction boundaries. Statement-level pooling is prohibited.**

   `docs/architecture/03_Database_Design.md` §4.3 makes Firm context transaction-scoped via `SET LOCAL` precisely because connections are pooled and reused across requests, Firms, and actors. Transaction-level and session-level multiplexing preserve `SET LOCAL` within a transaction; **statement-level multiplexing does not**, and would produce a cross-Firm read that every application-layer check passes, appearing only under concurrency, in production, intermittently.

   **This is a deployment-invalidating constraint, not a tuning preference.** A pooler whose mode is statement-level — by configuration, by default, or by a provider's own transparent proxy — may not sit between the application and PostgreSQL. **Pooling mode is verified as a deployment precondition** (`docs/adr/ADR-026-…` Decision 4), not assumed from documentation.

6. **Connection state is verified, not assumed.** A checked-out connection's role and the absence of leftover session state are established rather than trusted; a pooler, a failover, or a connection reset can change either (§4.3). No session-level `SET` of Firm context exists anywhere, without exception.

7. **Server configuration must not defeat a control the persistence design relies on.** In particular: `row_security` is never disabled; `FORCE ROW LEVEL SECURITY` state is schema and changes only through a reviewed migration (`docs/adr/ADR-020-…` Decision 5); no authoritative relation is `UNLOGGED` (§17.1); write-ahead logging is configured such that **point-in-time recovery remains possible** (Decision 10). **No extension is adopted for Release 0.1**; adopting one is its own approved decision under the new-runtime-dependency gate in `AGENTS.md`.

8. **Encryption in transit is mandatory on the application-to-database hop, and encryption at rest is mandatory for the database and every backup** (`docs/architecture/04_Security_Architecture.md` §5; §17.1). **No key-management product is selected**; key custody, rotation, and escrow follow `docs/adr/ADR-023-…` Decisions 4 and 5. **The database is never publicly reachable** (`docs/adr/ADR-022-…` Decision 7).

### Redis

9. **Redis is not a Release 0.1 production dependency.** No production runtime role runs Redis, and no Release 0.1 capability requires it. Release 0.1's queue need is bounded to the outbox publication `PF-091` may introduce.

   Adopting Redis later requires **its own approved story** demonstrating necessity, and it is a **new runtime dependency** hitting that `AGENTS.md` approval gate. If adopted:

   - **No authoritative state may be stored in Redis.** No business record, audit record, outbox row, idempotency record, entitlement decision, or authorization decision. This mirrors §17.1's prohibition on `UNLOGGED` authoritative relations and holds for the same reason — a store that can return empty after a restart cannot hold a business fact.
   - **No authorization decision is cached in a way that could outlive a revocation** (Constitution Article 28: no stale positive authorization).
   - Anything stored is **Firm-scoped by construction**, never a cross-Firm key space or a shared cache.
   - Its absence must fail safely: a cache miss degrades, it never fails open.

   **The development `compose.yaml` Redis service is unaffected and remains development-only.** Its presence there is not a production decision and never was.

9a. **ARCH-013 selects database-backed cache, session, and queue for Release 0.1 production. This is a production decision made here, on its own reasoning — not an inheritance of a PF-011 local default.**

   The distinction matters and is easy to blur. `docs/adr/ADR-023-…` Decision 2 says the **literal `.env.example` values carry no production authority**; this decision says **what the production drivers are**. That the two happen to name the same driver is a coincidence of a good local default, not a derivation from it.

   The reasoning is independent: the platform already runs one durable, backed-up, encrypted, access-controlled PostgreSQL instance (§10, Decisions 1–8). Routing cache, session, and queue through it adds **no new runtime dependency, no second failure domain, no second secrets-boundary entry, no second backup surface, and no second store an operator could reach**. For a founding-firm pilot, a second data store bought nothing and cost all of that.

   **Constraints attaching to the selection:**

   - **This introduces no Redis production dependency**, and nothing in it may be read as adopting one (Decision 9).
   - **These stores are operational, never authoritative.** Session state, cache entries, and queued job payloads are **not** business records, audit records, outbox rows, or idempotency records. **Authoritative business and audit state remains governed solely by its own owning persistence model** — `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…` through `ADR-021-…` — and sharing an engine with the cache changes nothing about that ownership, those relations' classes, their forced Row-Level Security, or their append-only enforcement.
   - **Session records are IdentityAccess's**, not this document's. ARCH-013 selects where the driver writes; **it defines no session model, lifetime, rotation, revocation, or authorization semantic**, all of which remain IdentityAccess's (Constitution Article 26).
   - **The framework `cache` and `jobs` relations remain unblessed.** `docs/adr/ADR-020-…` Decision 15 assigns their fate to the Platform Foundation runtime, and **this decision neither approves, modifies, nor pre-empts that** — it selects a driver class, not a schema.
   - **No authorization decision is cached in a way that could outlive a revocation** (Constitution Article 28), and no cache entry, session record, or job payload carries privileged narrative or Firm business content beyond what the owning story authorizes.
   - **Revisiting this is additive.** If pilot load later makes a dedicated cache or queue necessary, that is Decision 9's approved-story path, not a silent configuration change.

### Backup, restore, and recovery

10. **Backups are required for production, and point-in-time recovery must remain possible.** The backup design must provide, at minimum: a periodic full backup; continuous write-ahead archiving sufficient for point-in-time recovery to a chosen instant; **encryption at rest**; **backups protected as production data**, at production's classification and under production's access restrictions (§17.1; `docs/architecture/04_Security_Architecture.md` §5); storage isolated from the primary such that a failure of the primary does not take the backups with it; and integrity verification of the stored backup itself.

    **No backup product, provider, storage service, region, or schedule is selected here**, and **no backup frequency, retention period, or generation count is asserted** — retention is an open owner/legal question (Decision 16).

11. **A restore is a destructive, separately authorized operation.** It is never routine, never automatic, and never performed to inspect data. It requires: explicit human authorization; a named operator; a recorded reason; a recorded target instant; and a recorded outcome. It hits the **destructive-operations approval gate** in `AGENTS.md`. **A restore is never used as a substitute for an ADR-014 `PrivilegedAccessGrant`** (`docs/adr/ADR-023-…` Decision 14).

12. **A restore re-establishes Firm isolation by the same rules the running system uses, and relaxes nothing** (§17.1). A restored database is **not exempt from forced Row-Level Security**; its roles, policies, grants, and `FORCE ROW LEVEL SECURITY` state are part of the restored schema and are **verified after restore, not assumed**. A restore that produced a database with Row-Level Security disabled, a policy missing, or the runtime role over-privileged is a **failed restore**, whatever the data looks like.

13. **A restore must preserve append-only audit continuity.** A restore that silently drops posted entries or audit records is a **defect, not an acceptable recovery outcome** (§17.1; `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §83). Audit continuity across the restore boundary is verified as part of the restore, and any gap is recorded as an incident finding (`docs/adr/ADR-025-…`), never silently accepted.

14. **Three recovery limitations are stated plainly rather than discovered during an incident**, each inherited from ARCH-012 and neither solved nor concealed here:

    - **Per-Firm point-in-time restore is not supported.** One shared schema means physical recovery is a **whole-database** operation: restoring one Firm to an earlier instant would restore every Firm to that instant. Release 0.1's Firm-scoped export is an **export capability, not a restore capability**, and is never represented as one (§17.2). **Whether per-Firm point-in-time restore is required has been decided, not left open.** `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 3 records that it is **not required for Release 0.1**, that whole-database physical recovery is the approved technical baseline, and that a Firm-scoped export **is not to be represented as point-in-time restore**. **If a later Thai legal, professional, contractual, or product requirement makes per-Firm restore necessary, the shared-schema physical model must be revisited through the database-redesign approval gate** — that trigger is part of the approved decision, not a residual doubt about it, and the model it would revisit is ARCH-012's, not this ADR's.
    - **A restore that rewinds the database can replay already-delivered events**, because outbox rows marked published may return to pending. Delivery is at-least-once and consumers must be idempotent (§13.7, §17.4). Release 0.1 has no consumer, so the exposure is latent rather than present — which is a scoping fact, not a mitigation.
    - **A restore to a point before an idempotency record existed can permit a duplicate side effect** (§14.3, §17.4).

    **Neither replay nor duplication is claimed to be prevented, and exactly-once execution or delivery is never claimed.** `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 5 records the approved rule: **technical idempotency is mandatory but is not declared legally or professionally sufficient by itself**, and a duplicate **with a client-facing consequence requires recorded assessment, remediation, and a decision on communication or notification.** **No universal notification outcome or timeline is asserted** — that depends on the incident facts and then-applicable duties (`docs/adr/ADR-025-…` Decision 19).

15. **The executed restore test is a separate operations deliverable, and this ADR neither performs nor claims it.** `docs/architecture/02_Product_Requirements.md` §8 requires an **executed and recorded** restore test as production-access evidence. That evidence is produced by the backup-and-restore-test operations requirement recorded in `docs/implementation/03_Engineering_Backlog.md`, which carries no story identifier.

    This ADR defines only **what such a test must demonstrate to count as evidence**: a restore performed from a real backup. **Where every property below can be demonstrated on synthetic data, the test does so**, because that carries none of the exposure. **Where the test necessarily uses production data, it runs only in an isolated, production-controlled recovery environment** under every control in `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 6 — explicit authorization, least-privilege access, access logging, encryption, an approved retention and deletion procedure, and recorded completion — and **never** into a general development, test, demonstration, or analytics environment (`docs/adr/ADR-022-…` Decisions 3 and 3a). Then: the restored database verified for schema completeness, migration-state agreement, **forced Row-Level Security enabled with policies present on every Firm-scoped relation**, correct role privileges, and **audit continuity**; the elapsed time measured and recorded as an observation; and the operator, date, backup identity, target instant, and outcome recorded. A restore test that verified data but not the isolation controls has not verified the property that matters most.

    **No restore test has been executed. A documented backup plan is not evidence; only an executed, recorded restore is.**

16. **Recovery objectives are defined as a framework with no values, because the values are the owner's.**

    - **Recovery Point Objective (RPO)** — the maximum acceptable data loss, measured as the interval between the last recoverable instant and the failure. **Not decided — owner/legal decision required.**
    - **Recovery Time Objective (RTO)** — the maximum acceptable time from decision-to-recover to service restored. **Not decided — owner/legal decision required.**
    - **Backup retention period** and **backup generation count** — **Not decided — owner decision required.** The **decision rule** is approved: `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 1 records that authorized deletion removes content from live systems, that **encrypted backup generations age out under a fixed, approved schedule rather than being selectively rewritten**, and that a **legal hold suspends deletion and expiry within its scope**. **The numeric schedule is not set by that record** (follow-up item 1) and is not set here.

    **No RPO or RTO value is proposed, defaulted, implied, or inferable from anything in this ADR**, and none may be inferred from a backup frequency, because none is stated either. Both are **commitments to a pilot Firm** and belong in the pilot agreement, which remains draft pending Thai-qualified legal review (`docs/adr/ADR-012-…` Decision 8). Once set, they are recorded with the evidence that the deployment actually achieves them; **an objective without a measured, executed demonstration is an aspiration, not an objective.**

17. **No high-availability, failover, replication, or continuity posture is designed or claimed.** Release 0.1 runs a single authoritative PostgreSQL instance; **that instance is a single point of failure, and saying so plainly is preferable to implying resilience that does not exist.** A replica, standby, or automated failover posture requires its own approved decision and its own budget (`docs/adr/ADR-022-…` Decision 12). **A read replica in particular is not adopted**: it would create a second connection path whose pooling mode, role privileges, and Row-Level Security state would each have to be independently guaranteed, and a stale read of an authorization-relevant row is precisely the "stale positive" Constitution Article 28 prohibits.

18. **Backup retention against deletion obligations and legal hold — the rule is approved; the numbers and procedures are not.** A backup retains content the live database has deleted. `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 1 resolves the conflict in principle: **authorized deletion removes content from live systems; encrypted backup generations age out under a fixed, approved schedule rather than being selectively rewritten; a legal hold suspends deletion and expiry within its scope; and the schedule, the authorization evidence, and the release of a hold must be recorded before implementation.** ARCH-012 §17.5 carries the same rule.

    **Outstanding, and not asserted here:** the numeric schedule, and the legal-hold application, release, and backup-expiry procedures (`docs/legal/ARCH-012-Thai-Legal-Review.md` follow-up items 1 and 2). **Selectively rewriting a backup generation to honour an erasure request is prohibited by the approved decision**, and this ADR neither designs nor claims any outstanding procedure.

19. **AI holds no authority here.** AI never provisions, configures, or administers a database; never authors, approves, or applies a role, grant, or policy change; never takes, deletes, or restores a backup; never authorizes a recovery; and is never an authorization authority (Constitution Articles 6, 26, 28, 39, 40). **Release 0.1 contains no AI capability.**

20. **This ADR asserts no story status and schedules nothing.** The PostgreSQL CI requirement now carries the identifier **`PF-033`**, whose story contract is defined in `docs/implementation/03_Engineering_Backlog.md`. **No status is asserted for it, and it is neither scheduled nor renumbered here.** `docs/PROJECT_STATUS.md` is authoritative. The four required `Protect main` check names are preserved exactly.

## Alternatives considered

- **Leave the PostgreSQL version to the provider's default.** Rejected — a provider default can be older than 16, and ARCH-012's capability set is not negotiable. A floor makes an incompatible provider a visible disqualification rather than a late discovery.
- **Adopt the newest PostgreSQL major version available.** Rejected as unnecessary risk for a founding-firm pilot: 16 already supplies every required capability, and it is what the development environment runs, so local, CI, staging, and production agree. A later adoption is additive and can be its own decision.
- **Permit statement-level pooling with a compensating application check.** Rejected. There is no application-layer check that detects a `SET LOCAL` lost to a statement-level pooler, because the application's own checks pass — that is the entire failure mode ARCH-012 §4.3 describes. The only reliable control is refusing the topology.
- **Use one database account for simplicity in a small pilot.** Rejected — it makes forced Row-Level Security ineffective for the request path, gives request-time code DDL ability, and erases the audit distinction between a data change and a schema change (`docs/adr/ADR-020-…` Decision 8). Pilot scale does not change any of that.
- **Adopt Redis for cache, session, and queue in production**, since it is already in the development stack. Rejected — presence in `compose.yaml` is not an architectural decision, `.env.example` explicitly says so, and adopting it would add a runtime dependency, a second failure domain, a second secrets entry, and a second place authoritative-looking state could accumulate, for a pilot whose load does not require it. Decision 9 keeps the door open through an approved story that must demonstrate necessity.
- **Add a read replica to reduce load on the primary.** Rejected for Release 0.1 (Decision 17) — the isolation controls would need independent guarantees on a second path, and replica lag turns an authorization-relevant read into a stale positive.
- **Set provisional RPO and RTO values now and revise them later.** Rejected, and this is the most important rejection in this ADR. A provisional number becomes the number: it is quoted in a pilot conversation, written into an agreement, and treated as a commitment long before anyone measures whether the deployment achieves it. `docs/architecture/02_Product_Requirements.md` §8 and Constitution Article 47's "a date is never a reason to move a control" reflect the same discipline. Decision 16 defines the framework and leaves the values to the owner.
- **Derive an implied RPO from a stated backup frequency.** Rejected — it is the same thing by another route, which is why no frequency is asserted either.
- **Treat a documented, tested-by-inspection backup plan as satisfying the evidence item.** Rejected — `docs/architecture/02_Product_Requirements.md` §8 says **executed**, and a backup that has never been restored is a backup whose restorability is unknown. Decision 15 defines what an executed test must demonstrate and states plainly that none has occurred.
- **Restore production into staging to rehearse the restore procedure.** Rejected — **staging is always synthetic-only** (`docs/adr/ADR-022-…` Decision 3; ARCH-012 §16.4, §17.5), and `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 6 is explicit that a restored copy must not populate a general development, test, demonstration, or analytics environment. **Decision 15's test uses synthetic data where that is sufficient** — schema, policy, privilege, and audit continuity are all verifiable on it — and **where production data is necessary, the test runs only in the ephemeral production-controlled recovery boundary of `docs/adr/ADR-022-…` Decision 3a, under all approved controls.** Neither path puts production data in staging.
- **Solve per-Firm restore by adding a logical per-Firm export/import path.** Rejected as out of scope and out of ownership: ARCH-012 §17.2 explicitly does not design it, Release 0.1's Firm-scoped export is not a restore capability, and `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 3 records that per-Firm restore is **not required for Release 0.1**. Designing it here would pre-empt an approved decision and its revisit trigger.
- **Claim consumer idempotency prevents recovery-driven duplicates.** Rejected as an overstatement, and now expressly so: `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 5 records that **idempotency is mandatory but is not declared legally or professionally sufficient by itself**, and that exactly-once must never be claimed. Decision 14 states the limitation instead of dressing it as a mitigation.

## Consequences

- **Provider selection is narrowed, usefully.** Any service that does not expose role management, policy management, and `FORCE ROW LEVEL SECURITY`, or that interposes statement-level pooling, is disqualified before evaluation begins.
- **Three role credentials plus per-environment separation** is more provisioning work than one account, and it is what makes the isolation model real rather than nominal.
- **A single PostgreSQL instance means an outage is an outage.** No failover exists; recovery is restore-based and its duration is unmeasured until Decision 15's test runs.
- **Recovery objectives stay blank until the owner sets them**, which will look unfinished in a readiness review. It is the honest state, and a blank is safer than an unmeasured number.
- **Per-Firm restore is unavailable**, and if a Firm ever asks for its own point-in-time rollback the answer is that the platform cannot do it. Stated now (Decision 14) so it is not discovered during that conversation.
- **The executed restore test is on the critical path to production access** and cannot be shortened by documentation.
- **Redis's absence keeps the runtime small**, and any future adoption carries an explicit necessity argument rather than arriving by convenience.
- **Backup retention cannot be finalized** until the erasure/retention/legal-hold conflict receives Thai-qualified review.

## Security and professional-responsibility consequences

1. **Backups are a full copy of everything the platform protects** — `docs/architecture/04_Security_Architecture.md` §2 lists "audit records and backups" as a protected asset for exactly that reason, and §3 names backup leakage as a threat. Decision 10's requirement that backups be protected **as production data** is not a formality: a backup with weaker access control is a complete confidentiality bypass of every control the running system enforces.

2. **A restored database with Row-Level Security off is a cross-Firm disclosure waiting to happen**, and nothing about the data would look wrong. Decision 12 makes policy, privilege, and forced-RLS verification part of what "restored successfully" means, because the alternative is a database that appears healthy and has no isolation.

3. **A restore that loses audit records destroys the record of what happened** at exactly the moment it matters most. Decision 13 treats that as a defect, matching the discipline Constitution Articles 8, 18, and 23 establish for immutable history elsewhere.

4. **Statement-level pooling would produce an intermittent, load-dependent cross-Firm read** that passes every application check and appears only in production — the most dangerous failure shape available to a legal-practice platform, because the affected parties may be adverse to one another (§20.1, §20.5). Decision 5 refuses the topology rather than mitigating it.

5. **A standing superuser or `BYPASSRLS` credential is a second privileged-access path** to every Firm's confidential matters, outside the Firm-visible support-access history Constitution Article 29 requires. Decision 4 and `docs/adr/ADR-023-…` Decision 13 remove it; where a provider's own administrative capability is unavoidable it is named as residual risk rather than concealed.

6. **A restore is a destructive operation on live client data.** Decision 11 puts it behind explicit authorization and the `AGENTS.md` destructive-operations gate, and forbids it as a data-inspection route, because "restore a copy and look at it" is a database console with extra steps.

7. **Refusing to state an RPO or RTO is a professional-responsibility position.** A Firm that believes recovery is fast makes different decisions about where its deadline data lives. An unmeasured objective is a false assurance, and Constitution Article 47 already forbids a release document from making a readiness claim.

8. **Per-Firm restore being unsupported is a real limitation, and its legal significance has been assessed rather than left hanging** — `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 3 records that it is not required for Release 0.1, with a defined trigger for revisiting the physical model. Decision 14 states the limitation now rather than during an incident, which is the only point at which stating it has any value.

9. **AI holds no authority over any database, backup, restore, role, grant, or policy.**

10. **No certification, compliance, or legal-sufficiency conclusion is asserted.** The **ARCH-012 Thai-qualified persistence review was completed and approved on 1 August 2026** (`docs/legal/ARCH-012-Thai-Legal-Review.md`); the **separate Thai-qualified review required by `docs/adr/ADR-012-…` Decision 8** — covering the Privacy Notice, Terms, pilot agreement, and required disclosures — **has not occurred.** **No production access has been authorized, and the complete production-access gate is not satisfied**; two of its seven evidence items are presently satisfied: the **approved database design** and this **approved deployment architecture**. **No backup-success or restore-success claim is made: no backup exists and no restore test has been executed.**

## Integration consequences

- **`docs/architecture/03_Database_Design.md` §22.1** — this ADR supplies the deployment-architecture answers for PostgreSQL version/extensions/server configuration, connection pooling topology, and backup/PITR/encryption-at-rest/key-management **mechanisms as properties, with no product selected**. The `firm_id` foreign-key question remains the implementing `PF-080`/`PlatformAdministration` story's and is untouched here.
- **`docs/adr/ADR-016-…`** — the tenancy model, four relation classes, and forced-RLS discipline are consumed unchanged. Decision 14's per-Firm restore limitation is the operational consequence ADR-016 already named as the point at which its shared-schema decision would have to be revisited.
- **`docs/adr/ADR-018-…`** — append-only audit enforcement is carried across the restore boundary by Decision 13.
- **`docs/adr/ADR-019-…` / `PF-091` / `PF-092`** — the outbox publication role is provisioned on Decision 3's discipline when those stories exist; lease duration, batch size, and reclamation cadence remain theirs and are not set here. Decision 14's replay consequence is ADR-019's at-least-once posture, restated as a recovery fact.
- **`docs/adr/ADR-021-…`** — the idempotency retention window remains a later approved policy's; Decision 14's duplicate-side-effect consequence is stated, not solved.
- **`docs/adr/ADR-020-…`** — role separation, no automatic migration on deploy, and restore-and-roll-forward as the recovery path for a destructive schema change are consumed unchanged; `docs/adr/ADR-026-…` carries the procedure.
- **`docs/adr/ADR-022-…`** — supplies the four ordinary environment classes and the ephemeral recovery boundary. **Decision 15's restore test uses synthetic data where that is sufficient; where production data is necessary it runs only in the ephemeral production-controlled recovery boundary, under all approved controls** (`docs/adr/ADR-022-…` Decisions 3 and 3a).
- **`docs/adr/ADR-023-…`** — supplies credential custody, rotation, and the operator-access posture Decisions 3, 4, and 11 depend on.
- **The PostgreSQL CI requirement** (`PF-033`, story contract defined in `docs/implementation/03_Engineering_Backlog.md`) verifies engine-dependent behaviour before `PF-080`; **no status is asserted for it**; Decision 1's version floor is the version that story's environment should exercise. **This ADR changes no CI configuration, no `phpunit.xml`, and no test.**
- **Billing (EPIC-008)** is out of Release 0.1 scope; §17.1's requirement that a restore preserve posted-entry continuity is carried forward for when it exists.

## Explicit non-goals

This ADR does **not**: implement, provision, configure, or deploy any database, replica, pooler, backup, or storage; create or modify any migration, schema, table, column, index, policy, role, grant, source file, test, configuration file, Docker file, `compose.yaml`, CI workflow, dependency, or GitHub setting; select, endorse, evaluate, price, or recommend a hosting provider, managed database service, region, connection pooler, backup product, storage service, replication or high-availability product, secret-management or key-management product, or any other vendor, provider, product, or package; decide hosting jurisdiction, data residency, or subprocessor approval; **take a backup, execute a restore, execute or schedule a restore test, or claim that any backup or restore has succeeded**; define or claim an RPO or RTO value, a backup frequency, a retention period, or a generation count; define or claim a high-availability, failover, replication, multi-region, autoscaling, zero-downtime, throughput, latency, or availability property; adopt Redis or any other runtime dependency, or authorize one without its own approved story and the `AGENTS.md` new-runtime-dependency gate; adopt any PostgreSQL extension; decide whether `firm_id` carries a foreign key to `Firm`, which belongs to the implementing `PF-080`/`PlatformAdministration` story; design a per-Firm export/import, logical restore, or data-portability capability; design an audit-purge, retention, or partition-detachment path, or assert any retention period; create a cross-Firm read role, view, projection, report, replica-based analytics path, or a Reporting bounded context — which Constitution Article 44 reserves and which this ADR **neither approves nor permanently prohibits**; authorize any restore of production data into a non-production environment; create a second operator or privileged-access path; set a numeric retention period for backups, audit streams, or pre-authentication security events, define a legal-hold or backup-expiry procedure, or supply any other parameter, procedure, or evidence that `docs/legal/ARCH-012-Thai-Legal-Review.md` leaves to separately approved follow-up; assert any story's status, or schedule, rename, or renumber `PF-033` or any operations requirement; change `phpunit.xml`, any CI workflow, or the four required `Protect main` check names; introduce an AI capability or modify `docs/architecture/05_AI_Architecture.md`; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; claim that any described control or property is implemented, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

**Accepted conceptual architecture only.** It authorizes no application code, schema, migration, role, grant, policy, infrastructure, database, backup, restore, dependency, deployment, or production access.

**No production database exists. No backup has been taken. No restore has been performed and no restore test has been executed.** **No property described here is claimed to be implemented, tested, or effective.**

**No story's status is asserted here; `docs/PROJECT_STATUS.md` is the authoritative record.** The story ID is **ARCH-013**; the accompanying architecture document is `docs/architecture/20_Deployment_Operations_Architecture.md`.
