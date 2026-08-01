# ARCH-020 — Deployment & Operations Architecture

**Status:** **Approved.** Explicit repository-owner approval was recorded on PR #35 on 1 August 2026 after independent review and all four required `Protect main` checks passed on commit `a163fce`. Approval establishes this conceptual architecture and accepts `docs/adr/ADR-022-Deployment-Topology-and-Environment-Separation.md` through `docs/adr/ADR-026-Release-Deployment-Migration-Rollback-and-Production-Gates.md`; it schedules, implements, provisions, purchases, or authorizes no infrastructure, deployment, credential, production access, or operational capability. No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled by this approval, and **no implementation story's status is asserted here — `docs/PROJECT_STATUS.md` is the authoritative record of what is current, next, and complete.**

**Document numbering.** The story ID is **ARCH-013**. The architecture document is numbered **20**, continuing the document-number sequence rather than the story sequence — the same convention ARCH-006/ARCH-014 established and every story since has followed.

**Dependency on ARCH-012 — now approved.** This document rests on `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…` through `ADR-021-…`, **which are Approved and Accepted** — explicit repository-owner approval recorded on PR #34 on 1 August 2026, alongside the eight Thai-qualified legal-review decisions in `docs/legal/ARCH-012-Thai-Legal-Review.md`. **This document is synchronized with that approved baseline** at merge commit `485e317`. ARCH-012's approval schedules no implementation and authorizes no deployment or production access, and neither does this document.

**No capability, control, property, or procedure described in this document is claimed to be implemented, built, configured, tested, or effective.** **No environment exists. No backup has been taken. No restore test has been executed. No monitoring is collected. No incident procedure has been written.** **No production access has been authorized, and the complete production-access gate is not satisfied** — two of its seven evidence items are presently satisfied (§11): the **approved database design** and this **approved deployment architecture**. No production-readiness, certification, compliance, backup-success, restore-success, or legal-sufficiency conclusion is asserted anywhere in it.

---

## 1. Scope, authority, and relationship to existing architecture

### 1.1 What this document is

This is the **platform-wide deployment and operations baseline**, binding every environment in which OneLegalPro runs — on the same footing as `docs/architecture/04_Security_Architecture.md` (security), `docs/architecture/07_API_Standards.md` (external contracts), and `docs/architecture/03_Database_Design.md` (persistence). It owns no domain data and defines no bounded context. It defines **how the platform is deployed, configured, operated, observed, recovered, and released**, and **which operational practices are required, permitted, and prohibited**.

**In scope:** deployment topology by role; environment separation; configuration and secrets; PostgreSQL production posture and the Redis decision; backup, restore, and recovery, including the recovery-objective framework; observability, health checks, logging, and alerting; incident response and escalation; release deployment, migration execution, and the rollback boundary; production-readiness gates; and operator/support access and auditability in production.

**Out of scope:** everything in §14, and specifically — **which** vendor, provider, product, region, or package is used, all of which require a separate owner-approved procurement decision; hosting jurisdiction and data residency; capacity, sizing, and performance targets; high availability, failover, replication, autoscaling, and multi-region topology; any RPO or RTO **value**; any retention period; any availability or uptime commitment; and any incident-notification duty or timeline.

**This document defines conceptual and normative operational rules. It creates no infrastructure, no environment, no credential, no pipeline, no configuration file, no source file, and no test.**

### 1.2 Authority and precedence

`docs/architecture/01_OneLegalPro_Constitution.md` prevails over this document in every case. Where this document appears to conflict with an approved per-context architecture, that context's architecture governs **what** it owns and **what invariants apply**; this document governs **how** an environment running it is deployed and operated. Apparent conflicts are resolved explicitly in §16 rather than left to a reader's judgement.

### 1.3 The approved architecture this document rests on

| Source | What it establishes that this document implements |
|---|---|
| Constitution Article 29 | Operators hold no Firm access by default; silent impersonation prohibited; support access explicit, purpose-bound, time-limited, strongly authenticated; break-glass rules in force |
| Constitution Article 30 | Append-only security audit; **operational logs are never the sole authoritative security record**; fail closed; **availability never outranks Firm isolation, authorization, Ethical Walls, financial safeguards, or privilege protection** |
| Constitution Article 36 | A rendering, delivery, provider, or integration failure never rolls back or rewrites a committed business record |
| Constitution Articles 44–45, 48 | A future Reporting bounded context is reserved; no `PlatformAdministration` record, query, cache, projection, index, or event spans Firms; **no operator access path exists outside `PrivilegedAccessGrant`** |
| Constitution Article 46 | **No Firm-level suspended state and no Firm-wide disable**; any such capability requires its own separately approved decision |
| Constitution Article 47 | A deferral is never a waiver; an absent control is never approximated; **a date is never a reason to move a control**; **no release document makes a production-readiness, certification, or compliance claim** |
| `docs/architecture/02_Product_Requirements.md` §2 item 22, §8, §9 | Backups and an **executed** restore test, monitoring, and a documented incident procedure as Operations deliverables and production-access evidence; the seven-item evidence list; the Release 0.1 non-negotiables |
| `docs/adr/ADR-012-…` Decision 10 | The same evidence list; **15 September 2026 is a target, not a contractual commitment** |
| `docs/adr/ADR-014-…` | **All** operator Firm-data access through IdentityAccess's `PrivilegedAccessGrant`; **no second privileged-access mechanism**; Firm-visible append-only support-access history; break-glass excluded as a capability with its rule in force |
| `docs/architecture/04_Security_Architecture.md` §1, §5, §7, §8, §9 | Least privilege, deny by default, defence in depth, secure defaults, minimized blast radius; environment separation, key and secret management, backup and recovery integrity, incident handling, secure deployment and configuration; data classification; fail-closed handling; **no certification or compliance claim** |
| `docs/architecture/07_API_Standards.md` §14, §15 | Dependency-health checks, timeouts, retry policy, dead-letter handling, safe degradation, sandbox/production isolation, secrets as opaque references in an approved encrypted boundary; telemetry carrying **no confidential payloads**; correlation identifiers; **audit is never an operational log** |
| `docs/architecture/03_Database_Design.md` §4, §15–§17, §19, §22.1 (**Approved**) | The delegated deployment items; the required PostgreSQL capability set; the pooling constraint; role separation; forward-only migration; no automatic migration on deploy; drift detection; PITR must remain possible; backups protected as production data; per-Firm restore unsupported; synthetic data only in non-production |
| `docs/adr/ADR-020-…` (**Accepted**) | Forward-only, expand/contract, the compatibility-not-reversibility distinction, prohibited blocking statements, no zero-downtime claim, the approval gate on policy and grant changes |
| `AGENTS.md`, `CONTRIBUTING.md` | PostgreSQL; never edit historical migrations; the approval gates; secret-handling and compromise rules; one active implementation pull request at a time |

### 1.4 What this document does not unblock

Approval of this document satisfies **one** of the seven production-access evidence items in `docs/architecture/02_Product_Requirements.md` §8 — "an approved deployment architecture". The approved database design is also satisfied independently. An **executed and recorded** restore test, operational monitoring, a documented incident procedure, completed Thai-qualified legal review, and every applicable `AGENTS.md` approval gate remain outstanding, and §11 records the state of each.

It additionally **unblocks no deployment, no provider engagement, no expenditure, no credential issuance, no migration execution, and no production access**, and it starts no story.

---

## 2. Deployment topology

### 2.1 Vendor neutrality

**No hosting provider, cloud platform, managed database service, region, container orchestrator, connection pooler, load balancer, CDN, backup product, secret-management or key-management product, monitoring or observability product, log-aggregation service, or any other vendor, provider, product, or package is selected, endorsed, or evaluated here** (`docs/adr/ADR-022-…` Decision 1). This document defines **properties, roles, and constraints** a future selection must satisfy.

**Provider procurement and vendor selection require their own separately approved owner decision**, entangled with hosting jurisdiction, data residency, and subprocessor approval (§17). **No expenditure, contract, trial, or account creation is authorized.**

### 2.2 Runtime roles

Release 0.1 requires exactly these production runtime roles, and no more:

| Role | Responsibility |
|---|---|
| **HTTP ingress / TLS termination** | Terminates TLS; serves `public/` only; never exposes `storage/`, `vendor/`, dotfiles, or `.env` |
| **Web application** | Serves authenticated Firm requests; holds no authoritative state |
| **Queue worker** | Processes queued work, including outbox publication where `PF-091` later introduces it |
| **Scheduled task runner** | Named so it is not improvised later. **No Release 0.1 capability requires one** |
| **PostgreSQL** | The single authoritative store (§6) |
| **Migration execution context** | Runs schema changes as an explicit controlled operation (§10); never a running application process |

**Redis is not a Release 0.1 production runtime role** (§6.4). **No object storage, search engine, vector database, message broker, or AI processor is a Release 0.1 runtime role** — Documents, Legal Intelligence, Integrations, Communications, the Client Portal, and every AI capability are out of Release 0.1 scope (`docs/architecture/02_Product_Requirements.md` §3).

### 2.3 Network posture and transport

- **Deny by default at the network boundary.** PostgreSQL and every internal service accept connections only from the roles that need them and are **never publicly reachable**. The development stack already establishes the precedent: `compose.yaml` publishes no port for PostgreSQL or Redis and binds every other published port to `127.0.0.1`. Production preserves the property.
- **Encryption in transit is mandatory on every hop leaving a host**, including application-to-database.
- **A network boundary authorizes nothing.** It is defence in depth behind Firm isolation and application authorization, and no application-layer check is ever relaxed because a service is "internal" — the same discipline `docs/architecture/03_Database_Design.md` §3.1 applies to Row-Level Security.

### 2.4 Production artefact

The existing `Dockerfile` is **development-only by its own declaration** and **must never be deployed to staging or production**. The production artefact is a separate, separately reviewed build that at minimum installs **no development dependencies**; sets `APP_DEBUG=false` and disables `display_errors`; runs application processes as a **non-root** user; contains **no `.env` file, no secret, and no credential**; excludes `docs/`, `.git`, tests, and development tooling; and is **immutable and identified by digest**.

**The same artefact digest is promoted through staging to production** (§10.1). A rebuild between staging verification and production deployment invalidates the verification.

### 2.5 What is not claimed

**No high-availability posture, failover, replication, autoscaling, multi-region or multi-zone posture, zero-downtime deployment, zero-downtime migration, throughput or latency target, or uptime figure** is designed, promised, or implied. Release 0.1 is a founding-firm pilot running a single modest deployment, and **the single PostgreSQL instance is a single point of failure.** Stating that plainly is preferable to implying resilience that does not exist. Any such property requires its own approved decision and its own budget (§17).

---

## 3. Environment separation

### 3.1 Four ordinary environment classes

| Environment | Purpose | Firm data | Provisioning |
|---|---|---|---|
| **Local development** | A developer's own machine | **Never** — synthetic only | The existing `compose.yaml` stack (`PF-010`, `PF-011`) |
| **CI/test** | Automated verification on pull requests | **Never** — synthetic only | Ephemeral, per job |
| **Staging** | Pre-production verification of a release candidate **and of the deployment and migration procedures themselves** | **Never** — synthetic only | Deployed, persistent, separately credentialed |
| **Production** | The service a pilot Firm uses | Authoritative | Deployed, persistent, separately credentialed |

**Separately, one ephemeral production-controlled recovery boundary may be provisioned solely for an authorized restore test** (Decision 3a). It is **not an ordinary environment class**: it is **not a reusable application environment**, **not staging**, and **not a fifth ordinary class**. It **inherits production classification and controls**, exists only for the duration of the authorized test, and **must be destroyed under the approved retention and deletion procedure**. Adding an ordinary environment class beyond the four requires its own approved decision.

**An environment is not a configuration flag.** Each has its own credentials, its own secrets-boundary entries, its own database, and its own operator-access posture. **No credential, secret, key, database, backup, connection string, or service account is shared between any two environments**, including between local development and CI/test.

### 3.2 Synthetic data only

**No production database, production backup, production export, or production log may be restored, copied, imported, or streamed into local development, CI/test, or staging.**

This is the approved rule, not a placeholder: `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 6 confirms that ordinary non-production environments use synthetic data only, and that a restored copy **must not populate a general development, test, demonstration, or analytics environment.**

A restore of production into a non-production environment places Confidential and Privileged/Restricted material (`docs/architecture/04_Security_Architecture.md` §7) into an environment with weaker controls, for clients who are not party to that decision. It is a **data-classification event, not a convenience** (`docs/architecture/03_Database_Design.md` §16.4, §17.5).

**The one approved exception is a restore test, and it runs in none of the four ordinary environment classes.** Where a restore test necessarily uses production data, Decision 6 permits it **only in an isolated, production-controlled recovery environment**, under **every** one of the following, none severable: **explicit authorization**; **least-privilege access**; **access logging**; **encryption**; **an approved retention and deletion procedure**; and **recorded completion**. **Masking, pseudonymization, or subsetting is not presumed sufficient without a separately recorded assessment.**

That recovery boundary is **not an ordinary environment class** under §3.1 — it is **ephemeral**, production-controlled, provisioned and destroyed under production's own controls and classification, **not a reusable application environment**, **not staging**, and never reachable from, credentialed alongside, or reused as staging, CI/test, or local development. **The approved retention and deletion procedure for a restored copy does not yet exist** (legal-review follow-up items 1 and 5) and must be approved before such a test is run.

### 3.3 Staging is a verification environment

Staging never holds a real Firm's data, never serves a real Firm's users, and is never used to reproduce a production incident with production data. Where an incident cannot be reproduced on synthetic data, that is **recorded as a limitation of the investigation** (§9.4), not resolved by relaxing §3.2.

---

## 4. Configuration

- **Configuration is layered, and only one layer is committed.** Tracked defaults are **non-secret, non-production values only**, exactly as `.env.example` already is. Environment-specific and secret values are supplied at runtime from outside the repository and outside the artefact.
- **`.env.example` is a local-development artefact and never a production template.** **The literal PF-011 values are local defaults and carry no production authority.** Stated explicitly so silence cannot be read as approval: `APP_DEBUG=true`, `display_errors = On` (`docker/php/php.dev.ini`), the literal `onelegalpro`/`onelegalpro_dev_only` credentials, and `MAIL_MAILER=log` have **no production standing whatever.** Production values are set explicitly by the deploying story.
- **That is a statement about authority, not about which driver is correct.** Where a production value coincides with a local one, it does so because **§6.4 selects it for production on its own reasoning**, not because `.env.example` contained it.
- **Secure defaults apply**: the safe configuration is the default one, and opting into risk is explicit, authorized, and recorded (`docs/architecture/04_Security_Architecture.md` §1, §5).
- **Configuration changes to a deployed environment are authorized, recorded, and attributable** — never an undocumented console edit.

---

## 5. Secrets

- **No production artefact contains a secret.** A built image, a repository, a baked-in configuration file, a CI artefact, and a source file each contain **no** credential, key, token, connection string with an embedded password, or `.env` file. **`APP_KEY` is a production secret, not a build input.**
- **Production secrets live in an external managed secrets boundary** — the "approved, encrypted secret-management boundary with tightly restricted runtime access" `docs/architecture/07_API_Standards.md` §14 already requires. Everything outside it holds an **opaque reference and safe metadata only**. **No secret-management or key-management product is selected** (§2.1).
- **Prefer short-lived workload identity** where the selected platform supports it; **otherwise rotatable, least-privilege, purpose-bound credentials with bounded rotation overlap** (`docs/architecture/04_Security_Architecture.md` §5). **A long-lived, non-rotatable production credential is prohibited.**
- **Every credential is scoped to exactly one environment and one role.** A credential that would work in two is a defect.
- **Secrets never enter telemetry** — no credential, key, token, session identifier, recovery material, or connection string in a log line, metric label, trace attribute, exception message, error page, event payload, or audit record (Constitution Articles 29, 30). **Redaction happens at emission** (§8.1).
- **A leaked or suspected-leaked production secret is treated as compromised and rotated immediately**, and the rotation is recorded. A history rewrite or a private repository is **not** remediation (`CONTRIBUTING.md`, `README.md`), and a suspected leak triggers the incident procedure (§9).

---

## 6. PostgreSQL and Redis

### 6.1 Version and capability

**PostgreSQL 16 is the Release 0.1 minimum**; a version below 16 is prohibited. The engine must provide, and a deployment is invalid without: Row-Level Security including `FORCE ROW LEVEL SECURITY`; transaction-scoped settings (`SET LOCAL`); native `uuid`; `timestamptz`; partial unique indexes; deferrable constraints; `FOR UPDATE SKIP LOCKED`; database roles, privileges, and grants; triggers able to reject `UPDATE`, `DELETE`, and `TRUNCATE`; real foreign-key referential actions; and locale/ICU collation sufficient for Thai text correctness.

**A managed service that withholds role management, policy management, or `FORCE ROW LEVEL SECURITY` cannot host this platform.** That is a selection constraint, not a product judgement.

**No PostgreSQL extension is adopted for Release 0.1**; adopting one is its own approved decision under the new-runtime-dependency gate.

### 6.2 Roles

**Three database roles are separate, exactly as accepted `docs/architecture/03_Database_Design.md` §4.1 defines them, and no credential spans them.**

| Role | Privileges | Constraints |
|---|---|---|
| **Migration role** | DDL on the relations it owns; DML for backfills | **Owns the schemas and relations.** **Subject to `FORCE ROW LEVEL SECURITY` like every other role.** **Never used to serve a request.** Not a superuser, no `BYPASSRLS`. Its use is authorized and recorded, and its credential is **non-standing** (§10.2). |
| **Application runtime role** | `SELECT`/`INSERT`/`UPDATE`/`DELETE` as each relation permits | **Not the owner, not a superuser, no `BYPASSRLS`, no DDL.** No `UPDATE`/`DELETE`/`TRUNCATE` on append-only relations. Read-only on platform-global relations. |
| **Outbox publication role** (when `PF-091`/`PF-092` introduce it) | `SELECT` and bookkeeping `UPDATE` on the outbox only, plus `SELECT` on the Firm registry | **No access to any business relation**; subject to forced RLS with no exemption; no `BYPASSRLS`, no superuser, no owner exemption. |
| **Reporting / analytics role** | **Does not exist.** | Creating a cross-Firm read role would be Constitution Article 44's reserved Reporting context arriving through a privilege grant. |

**A single account used for both migrations and request serving is prohibited.** **No human holds a standing superuser credential, a standing `BYPASSRLS` credential, or a standing migration-role credential**; where a managed provider retains its own administrative account, that is named, authorized, recorded, never used to read Firm business content, and recorded as residual risk (§16.6).

**What this architecture adds to §4.1, without altering it:** the migration-role credential is **non-standing** — issued for a named, authorized migration operation and bounded to it (§10.2) — and is never held standingly by a human (§12). §4.1 already requires its use to be authorized and recorded; this makes the credential lifecycle match that requirement.

**How forced Row-Level Security behaves during a migration, stated honestly** (`docs/adr/ADR-024-…` Decision 3a):

- Ordinarily a relation's owner is **exempt** from its own policies. **`FORCE ROW LEVEL SECURITY` removes that exemption**, so the migration role — which owns the relations — **is subject to the policies while running a migration.** That is why Firm-spanning maintenance iterates Firms with `SET LOCAL` rather than reading every Firm's rows in one pass (§10.2).
- **No role in this model holds `BYPASSRLS`, and none is a superuser** — the two attributes that would defeat forced RLS outright. Granting either, to any role, for any duration, is prohibited.
- **The exemption returns the moment `FORCE ROW LEVEL SECURITY` is off.** A migration that disables it, or drops a policy, silently restores the owner exemption with nothing failing — which is why §10.2 verifies it before and after.
- **DDL is not itself constrained by a row policy.** Forced RLS constrains the **rows** a statement sees; it does not prevent the owning role from altering a relation, its policies, or its grants. What constrains that is the approval gate on policy, role, and grant changes, not the database.

### 6.3 Connection pooling

**Pooling must preserve transaction boundaries. Statement-level pooling is prohibited.**

`docs/architecture/03_Database_Design.md` §4.3 makes Firm context transaction-scoped via `SET LOCAL` precisely because connections are pooled and reused across requests, Firms, and actors. Transaction-level and session-level multiplexing preserve `SET LOCAL` within a transaction; **statement-level multiplexing does not**, and would produce a cross-Firm read that every application check passes, appearing only under concurrency, in production, intermittently.

**This is deployment-invalidating, not a tuning preference**, and **pooling mode is verified as a deployment precondition**, not assumed from documentation. **Connection state — role and absence of leftover session state — is verified, not assumed.**

### 6.4 Cache, session, and queue drivers; and Redis

**ARCH-013 selects database-backed cache, session, and queue for Release 0.1 production.** This is a production decision made here on its own reasoning — **not an inheritance of the PF-011 local default** (§4). That the two name the same driver is a coincidence of a good local default, not a derivation from it.

The reasoning is independent: the platform already runs one durable, backed-up, encrypted, access-controlled PostgreSQL instance. Routing cache, session, and queue through it adds **no new runtime dependency, no second failure domain, no second secrets-boundary entry, no second backup surface, and no second store an operator could reach.**

Constraints attaching to the selection:

- **This introduces no Redis production dependency**, and nothing here may be read as adopting one.
- **These stores are operational, never authoritative.** Session state, cache entries, and queued job payloads are **not** business records, audit records, outbox rows, or idempotency records. **Authoritative business and audit state remains governed solely by its own owning persistence model** — `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…` through `ADR-021-…` — and sharing an engine changes nothing about that ownership, those relations' classes, their forced Row-Level Security, or their append-only enforcement.
- **Session records are IdentityAccess's.** ARCH-013 selects where the driver writes; it defines **no** session model, lifetime, rotation, revocation, or authorization semantic (Constitution Article 26).
- **The framework `cache` and `jobs` relations remain unblessed** — `docs/adr/ADR-020-…` Decision 15 assigns their fate to the Platform Foundation runtime, and this selects a driver class, not a schema.
- **No authorization decision is cached in a way that could outlive a revocation** (Constitution Article 28).
- **Revisiting this is additive**, through the approved-story path below.

**Redis is not a Release 0.1 production dependency.** Adopting it later requires **its own approved story demonstrating necessity** and hits the **new-runtime-dependency** approval gate. If adopted: **no authoritative state may be stored in it** — no business record, audit record, outbox row, idempotency record, entitlement decision, or authorization decision; **no authorization decision cached in a way that could outlive a revocation**; anything stored is **Firm-scoped by construction**; and its absence degrades rather than failing open.

**The development `compose.yaml` Redis service is unaffected and remains development-only.**

### 6.5 Encryption and exposure

**Encryption at rest is mandatory** for the database and every backup, and **encryption in transit is mandatory** on the application-to-database hop. **The database is never publicly reachable.** **No key-management product is selected**; key custody and rotation follow §5.

---

## 7. Backup, restore, and recovery

### 7.1 Backup requirements

The backup design must provide, at minimum: a periodic full backup; continuous write-ahead archiving sufficient for **point-in-time recovery**; **encryption at rest**; **backups protected as production data**, at production's classification and access restrictions; storage isolated from the primary; and integrity verification of the stored backup.

**No backup product, provider, storage service, region, schedule, frequency, retention period, or generation count is selected or asserted here.**

### 7.2 Restore

A restore is a **destructive, separately authorized operation** hitting the `AGENTS.md` destructive-operations gate. It is never routine, never automatic, and **never performed to inspect data or as a substitute for a `PrivilegedAccessGrant`** (§12). It requires explicit human authorization, a named operator, a recorded reason, a recorded target instant, and a recorded outcome.

- **A restore re-establishes Firm isolation by the same rules the running system uses and relaxes nothing.** A restored database is **not exempt from forced Row-Level Security**; roles, policies, grants, and `FORCE ROW LEVEL SECURITY` state are **verified after restore, not assumed**. A restore producing a database with Row-Level Security disabled, a policy missing, or the runtime role over-privileged is a **failed restore**, whatever the data looks like.
- **A restore must preserve append-only audit continuity.** A restore that silently drops posted entries or audit records is a **defect, not an acceptable recovery outcome**. Continuity is verified across the restore boundary, and any gap is an incident finding.

### 7.3 Three limitations, stated plainly

Inherited from `docs/architecture/03_Database_Design.md` §17.2 and §17.4, neither solved nor concealed here:

1. **Per-Firm point-in-time restore is not supported, and is not required.** One shared schema means physical recovery is a **whole-database** operation: restoring one Firm to an earlier instant restores every Firm to that instant. Release 0.1's Firm-scoped export is an **export capability, not a restore capability**, and **is never to be represented as point-in-time restore.** `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 3 records that per-Firm point-in-time restore is **not required for Release 0.1** and that whole-database physical recovery is the approved baseline. **If a later Thai legal, professional, contractual, or product requirement makes it necessary, the shared-schema physical model must be revisited through the database-redesign approval gate.**
2. **A restore that rewinds the database can replay already-delivered events** — outbox rows marked published may return to pending. Delivery is at-least-once and consumers must be idempotent. Release 0.1 has no consumer, which is a scoping fact rather than a mitigation.
3. **A restore to a point before an idempotency record existed can permit a duplicate side effect.**

**Neither replay nor duplication is claimed to be prevented, and exactly-once execution or delivery is never claimed.** `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 5 records the approved rule: **technical idempotency is mandatory but is not declared legally or professionally sufficient by itself**, and a recovery-driven or crash-driven duplicate **with a client-facing consequence requires recorded assessment, remediation, and a decision on communication or notification.** **No universal notification outcome or timeline is asserted** (§9.3).

### 7.4 The executed restore test

`docs/architecture/02_Product_Requirements.md` §8 requires an **executed and recorded** restore test as production-access evidence. That evidence is produced by the backup-and-restore-test operations requirement recorded in `docs/implementation/03_Engineering_Backlog.md`, which carries no story identifier. **This document defines only what such a test must demonstrate:**

- a restore performed from a real backup. **Use synthetic data wherever every required property below can be demonstrated with it**, because that carries none of the exposure. **Where production data is necessary, the test runs only in the ephemeral production-controlled recovery boundary of `docs/adr/ADR-022-…` Decision 3a**, under **every** control approved by `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 6 — explicit authorization, least-privilege access, access logging, encryption, an approved retention and deletion procedure, and recorded completion. **Production data is never placed in staging or in any other ordinary non-production environment** (§3.1, §3.2);
- the restored database verified for schema completeness, migration-state agreement, **forced Row-Level Security enabled with policies present on every Firm-scoped relation**, correct role privileges, and **audit continuity**;
- elapsed time measured and recorded as an observation;
- operator, date, backup identity, target instant, and outcome recorded.

A restore test that verified data but not the isolation controls has not verified the property that matters most.

**No restore test has been executed. A documented backup plan is not evidence; only an executed, recorded restore is.**

### 7.5 Recovery objectives — framework, no values

- **Recovery Point Objective (RPO)** — maximum acceptable data loss, measured as the interval between the last recoverable instant and the failure. ***Not decided — owner/legal decision required.***
- **Recovery Time Objective (RTO)** — maximum acceptable time from decision-to-recover to service restored. ***Not decided — owner/legal decision required.***
- **Backup retention period and generation count** — ***Not decided — owner decision required.*** The **decision rule** is approved: `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 1 records that authorized deletion removes content from live systems, that **encrypted backup generations age out under a fixed, approved schedule rather than being selectively rewritten**, and that a **legal hold suspends deletion and expiry within its scope**, with the schedule, the authorization evidence, and the release of a hold recorded before implementation. **The numeric schedule is not set by that record and is not set here.**

**No RPO or RTO value is proposed, defaulted, implied, or inferable from anything in this document**, and none may be inferred from a backup frequency, because none is stated either. Both are **commitments to a pilot Firm** belonging in the pilot agreement, which remains draft pending Thai-qualified legal review. **Once set, they are recorded with the evidence that the deployment actually achieves them; an objective without a measured, executed demonstration is an aspiration, not an objective.**

---

## 8. Observability, health checks, logging, and alerting

### 8.1 Telemetry content rules

**Operational telemetry excludes, by construction and at the point of emission:** credentials, passwords, API keys, tokens, session identifiers, recovery material, and connection strings; Client, Matter, `MatterClient`, Task, and conflict-attestation **content**; privileged narratives and legal work product; personal data of a Firm's clients; and any other Confidential or Privileged/Restricted material.

**Redaction happens where the log line, metric, or span is created — never as a downstream filter or a periodic scrub.**

**Non-Firm identifiers are permitted; content is not.** A correlation identifier, request identifier, actor identifier, route, status, and duration are permitted; the values those identifiers point at are not.

**`FirmId`, and every other Firm identifier, name, slug, domain, or derived Firm key, is prohibited as a metric dimension, label, tag, bucket, partition, or dashboard grouping. There is no retained per-Firm operational series, and no per-Firm comparison, ranking, or benchmarking of any kind.** A `FirmId` is not a secret, but a *retained time series keyed by Firm* is a cross-Firm operational dataset about identifiable law firms, one dashboard away from the comparison Constitution Articles 44, 45, and 48 reserve for a separately approved Reporting context.

**Exception, narrowly bounded — a Firm identifier in a restricted diagnostic log or trace.** Where incident diagnosis genuinely cannot proceed without knowing which Firm a fault affected, a Firm identifier may appear in a diagnostic log line or trace span subject to **every** one of the following, none severable:

- **purpose-bound to incident diagnosis only** — never routine operation, capacity planning, product analytics, or reporting;
- **least-privilege restricted access**, to named responders under a recorded authorization;
- **encrypted in transit and at rest**, and held inside the platform's own boundary (§8.7);
- **no Firm business content, Client or Matter content, or privileged narrative** travelling with it — §8.1's exclusions apply unchanged;
- **retention requires legally approved policy**, and until that exists no period is defaulted (§8.7);
- **never promoted into a metric dimension, label, dashboard grouping, comparison, ranking, benchmark, aggregate, or any reporting path.**

The exception ends at the diagnostic record and creates no derived series.

Two further constraints attach to identifiers generally: a UUIDv7 **discloses its generation time** and has a predictable prefix, so identifiers in telemetry are identifying data with a retention consequence; and identifiers are **never placed in URL parameters or query strings**, where intermediaries log them routinely.

**Correlation identifiers thread a request across logs, metrics, traces, and audit**, which is what makes content-free telemetry sufficient for investigation.

### 8.2 Audit is not logging

- Authoritative audit is **persisted, append-only data** enforced by ungranted privileges and rejecting triggers. It is **not written to a log sink, not reconstructed from one, and not considered complete because a log exists.**
- An operational log is **mutable, rotated, sampled, and lossy by design.**
- **A telemetry outage, dropped log line, or full disk never blocks, degrades, or rolls back an audit write**, and never causes a security-relevant operation to proceed unrecorded. Where the authoritative audit write cannot occur, the operation **fails closed**.

### 8.3 Health checks

Three semantics, never collapsed into one endpoint:

| Semantic | Question | Consequence of failure |
|---|---|---|
| **Liveness** | Is this process irrecoverably stuck? | Restart the process |
| **Readiness** | May this instance receive traffic now? | Remove from rotation; do not restart |
| **Dependency health** | Are dependencies reachable and behaving? | Operator signal |

**The stock `/up` endpoint is insufficient as the complete production health design.** It is retained as a liveness signal and is not represented as readiness or dependency health; readiness and dependency-health surfaces are their own design.

**A health surface discloses nothing** — no Firm identifier, Firm name, Firm count, record count, version string, dependency hostname, credential, connection string, stack trace, or downstream error detail. Any surface carrying more than a coarse status is authenticated and operator-only. **A dependency-health check never becomes a data path**: it verifies reachability, never reads a business relation, and never establishes Firm context.

### 8.4 Telemetry is Firm-agnostic

- Operational aggregates measure **the platform** — request rate, error rate, latency, queue depth, job failures, connection counts, resource headroom. They do not measure **Firms**: no per-Firm business metric, no Firm ranking, no Firm comparison, no Matter or Client counts as a product signal.
- **No monitoring component holds a database credential that can read a Firm-scoped business relation.**
- **Constitution Article 44's reserved Reporting bounded context is neither approved nor permanently prohibited here.** Cross-Firm insight, if ever wanted, arrives through that approved architecture — preserving Firm isolation, authorization before retrieval, denied-existence confidentiality, purpose limitation, and the most restrictive applicable domain rule — never through a metrics pipeline.
- **No metric is dimensioned by Firm**, and **no per-Firm operational series is retained** (§8.1). A fault affecting one Firm is diagnosed through the bounded diagnostic-record exception in §8.1, which produces no series and no dashboard grouping.

### 8.5 Minimum operational signals

Obligations on the monitoring operations requirement, not an implementation: process liveness and readiness per role; HTTP error rate and latency distribution; unhandled exception rate; database reachability, connection count, and pool saturation; queue depth, job failure rate, and worker liveness; **migration-state agreement — schema drift detected, not discovered during an incident**; backup job outcome and age of the most recent backup; certificate expiry; and disk, memory, and CPU headroom.

**Naming a signal is not claiming it is collected. None is implemented.** **A monitoring gap is a known state, not a healthy one** — absence of an alert is not evidence of health where the signal is not collected, and §11 records which signals exist rather than treating silence as a pass.

### 8.6 Alerting and process supervision

- **Alerting is an operator capability, categorically distinct from the Firm-facing notification Release 0.1 does not have.** `docs/architecture/02_Product_Requirements.md` §6 discloses that nothing notifies, reminds, escalates, or alerts a Firm user. Operator alerting **never** delivers to a Firm user, never surfaces in a Firm-facing interface, and **never becomes, is described as, or implies a deadline reminder, Matter alert, or any product notification capability.**
- **An alert carries no confidential content** — §8.1 applies to alert text, subject lines, and payloads, more sharply because alerts leave the platform boundary.
- **Queue and web processes require explicit supervision**: a defined **restart policy** with backoff; **bounded retry** with a defined failure destination rather than infinite redelivery; **failure visibility** — a failed job observable, counted, and inspectable without reading Firm content; and **alerting** on sustained failure, worker absence, and queue growth. A silently absent queue worker means outbox rows accumulate unpublished with nothing failing.

### 8.7 Third-party services and retention — open

- **Third-party observability, log aggregation, and error tracking are not authorized.** Whether any may be used at all is an owner decision entangled with hosting jurisdiction and subprocessor approval. ***Not decided — owner/legal decision required.*** Until decided, telemetry stays within the platform's own boundary.
- **Operational log and telemetry retention** — ***Not decided — owner decision required.*** The bounding rules are approved: `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 8 requires platform-realm pre-authentication security-event data to **minimize collection, avoid raw submitted identifiers where technically possible, prohibit credentials, authentication secrets, recovery material, and request-body dumps, stay non-Firm-visible and restricted to named platform-realm service and security roles, and carry a short, separately approved retention period with access logging and a recorded deletion or expiry process**; Decision 2 requires a separately approved minimum retention per audit stream. **Neither sets a numeric period, and none is asserted, implied, or defaulted here** for logs, telemetry, or audit.

---

## 9. Incident response and escalation

### 9.1 The procedure

**An incident procedure must exist and be recorded before production access.** It must be a documented, followed procedure covering, at minimum: how an incident is declared and by whom; severity classification; the accountable responder; how a communication decision is made; how the timeline and actions are recorded; how the incident is closed; and how findings are carried into corrective work.

**No incident procedure currently exists, and none has been written.** It is an Operations deliverable owned by the corresponding operations requirement in `docs/implementation/03_Engineering_Backlog.md`, which carries no story identifier; **this document defines what it must contain and does not substitute for it.** **No notification duty, recipient, threshold, or timeline is asserted anywhere** (§9.3).

### 9.2 Severity classes

**A cross-Firm disclosure** — one Firm's data reaching another Firm's context, or an existence disclosure about a Firm or its records — is **a high-severity security and confidentiality incident**, not a data-quality defect. In a legal-practice platform the affected parties may be adverse to one another.

`docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 4 makes the response mandatory rather than discretionary: **every confirmed cross-Firm existence disclosure requires immediate containment, evidence preservation, Thai-qualified legal assessment, and a recorded decision** on affected persons, professional-conduct consequences, and any required notification.

Other classes the procedure must carry: unavailability; data loss or suspected data loss; suspected or confirmed secret compromise; audit gap or suspected audit tampering; **a discovered isolation-control absence** — Row-Level Security disabled, a policy missing, an over-privileged role — treated as a disclosure-risk incident even absent evidence of an actual cross-Firm read; unauthorized or out-of-procedure operator access; and a recovery-driven duplicate side effect.

### 9.3 Notification — decision rule approved, procedure and outcomes outstanding

**Approved and binding** (`docs/legal/ARCH-012-Thai-Legal-Review.md` Decisions 4 and 5): a confirmed cross-Firm existence disclosure requires **containment, evidence preservation, Thai-qualified legal assessment, and a recorded notification decision**; and a recovery- or crash-driven duplicate **with a client-facing consequence** requires **recorded assessment, remediation, and a decision on communication or notification**, with technical idempotency mandatory but **not sufficient by itself**.

**Outstanding, and not asserted here:** **no universal notification outcome or timeline is asserted** — those depend on the incident facts and then-applicable duties — and the **incident assessment and notification decision procedure** itself is a recorded follow-up (legal-review follow-up item 3) that **does not yet exist.** ***Not decided — owner decision required.*** **No timeline, threshold, recipient, or duty is asserted, implied, or defaulted anywhere in this document.**

Also decided: an incident's facts are **recorded contemporaneously and preserved**, so whatever duty is determined can be discharged from a real record rather than reconstructed from memory — which Decision 4's evidence-preservation requirement makes mandatory rather than merely prudent.

### 9.4 Investigation constraints

An investigator has **zero standing access to Firm data**. Investigation proceeds from content-free telemetry, from synthetic reproduction, and — where genuinely necessary and available — from an IdentityAccess `PrivilegedAccessGrant` under `docs/adr/ADR-014-…`, purpose-bound, time-limited, and Firm-visible.

**Where an incident cannot be fully diagnosed within those constraints, that is recorded as a limitation of the investigation.** It is never resolved by a production database prompt, by restoring production into a non-production environment, or by an unrecorded access. **Urgency is not an authorization.**

### 9.5 During an incident

Constitution Articles 30 and 36 hold during an incident exactly as outside one: a committed business or security fact is not edited, an audit record is not corrected by rewriting, and **availability never outranks Firm isolation, authorization, or privilege protection.** **Restoring service by disabling an isolation control is prohibited** — an outage is recoverable; a cross-Firm disclosure is not.

### 9.6 Responder — open

**The responder, escalation path, and any after-hours expectation are the owner's to name.** ***Not decided — owner/legal decision required.*** This repository is single-owner; an escalation path naming nobody is not a procedure. **No on-call tooling, rotation, paging service, or status page is selected or designed.**

---

## 10. Release deployment, migration execution, and the rollback boundary

### 10.1 Release and deployment

- **A release is an identified, immutable artefact promoted through environments by digest — never a rebuild per environment.**
- **Deployment is a human-authorized, recorded operation.** No deployment is triggered automatically by a merge, tag, schedule, or external event. Each records the artefact digest, source commit, target environment, authorizing human, executing operator, start and end time, and outcome.
- **CI builds and verifies; it does not deploy.** The existing workflows contain no deployment trigger, step, environment, secret, or write permission, and **this document adds none.** Introducing deployment automation is a **production deployment change** requiring the owner approval gate. **The four required `Protect main` check names — `PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit` — are preserved exactly.**
- **A production deployment is preceded by the same deployment to staging, using the same artefact and the same procedure.**

### 10.2 Migration execution

**Migrations run as an explicit, controlled operation. They never run automatically on application startup, on container start, on merge, or as a side effect of deployment.**

Every element of the procedure is required: a **named operator**; **explicit recorded authorization** (a database change; additionally an **authorization change** where it touches policies, roles, or grants; additionally a **destructive-operations** change where it removes anything); execution as the **migration role** with a **non-standing** credential; **preflight checks**; **lock and timeout criteria** with bounded retry; **abort criteria** defined in advance; and a **recorded result**.

**Blocking preflight checks:**

- **Schema drift check** — actual schema agrees with recorded migration state. **Drift aborts the operation.** This is the mechanism `docs/adr/ADR-020-…` Decision 10 records as unbuilt.
- **No long-running open transaction** in flight — policy DDL takes `ACCESS EXCLUSIVE` and queues behind weaker locks, then blocks everything after it.
- **Backup currency** — a recent backup exists and its age is known.
- **Role verification** — the connected role is the migration role, not the application runtime role; it is not a superuser and holds no `BYPASSRLS`; and `FORCE ROW LEVEL SECURITY` is confirmed still enabled on every Firm-scoped relation **before the first statement**, since that setting is what denies the owning role its ordinary policy exemption (§6.2).
- **Environment confirmation** — the target is the intended environment.
- **Phase declaration** — expand, migrate, or contract; **contract additionally confirms no running code path references the object being removed.**

**Post-migration verification is part of the operation:** migration state re-read and recorded; the **schema-level guard test** asserting every Firm-scoped relation declares its class and has Row-Level Security **enabled and forced** with a policy carrying `USING` and `WITH CHECK` re-run; and the readiness signal confirmed.

**A migration that completed but left Row-Level Security disabled or a policy dropped is a failed migration**, whatever its exit status. **A failed or aborted policy migration never leaves Row-Level Security disabled or a policy dropped**, and restoring the isolation control is the **first** recovery action, ahead of restoring service.

**Firm-spanning backfills** follow `docs/adr/ADR-020-…` Decision 4 unchanged — per-Firm iteration with `SET LOCAL` (preferred), or a narrowly scoped, authorized, recorded, time-bounded maintenance path. **Never** by disabling Row-Level Security, dropping a policy, or granting `BYPASSRLS`.

**No zero-downtime claim is made for any migration or any deployment.** Where a migration requires an interruption, it is a **planned platform maintenance window** under §13.

### 10.3 The rollback boundary

**Application rollback and schema rollback are different operations with different permissions, and they are never described by one word.**

**Application rollback is permitted only within an explicitly verified compatibility window.** Redeploying a previous digest is safe only where that version is known to work against the **live schema** — the property expand/contract preserves at each phase boundary.

- Every release records **which prior artefact digests are compatible with the schema as currently deployed** — a recorded fact established before deployment, not a judgement made during an incident.
- Rolling back **past a completed contract phase is prohibited.**
- Rolling back **into a half-completed migrate phase** is permitted only where the older version tolerates partially populated data.
- **Where no compatibility window is recorded, application rollback is not available**, and the response is forward correction.

**Schema rollback is prohibited. There is no production down-migration, ever.** A `down()` may exist for local development and is never the production recovery plan; **historical migrations are never edited**; a reverse migration is untested code against a state it has never seen and routinely cannot restore what the forward migration destroyed.

**A failed schema change is recovered by forward correction, or by restore under a separately authorized recovery procedure** (§7.2) — with the whole-database consequence, the audit-continuity verification, and the replay and duplicate consequences all applying. **A restore is never chosen because it is faster than thinking.**

**Reversibility is never claimed for what is merely compatible.** Expand is safely abandonable; migrate is safe to interrupt but leaves written data written; contract is not reversible at all. A deployment record states which of the three a change is, and **no release note, runbook, or gate artefact describes a migration as "reversible" or "rollback-able".**

---

## 11. Production-readiness gates

**Production access requires recorded evidence of every item in `docs/architecture/02_Product_Requirements.md` §8 and `docs/adr/ADR-012-…` Decision 10.**

| # | Evidence item | What counts | State at time of drafting |
|---|---|---|---|
| 1 | **Approved database design** | ARCH-012's documents Approved/Accepted with owner approval recorded | **Satisfied** — approval recorded on PR #34, 1 August 2026 |
| 2 | **Approved deployment architecture** | This document and `ADR-022-…`–`ADR-026-…` Approved/Accepted with owner approval recorded | **Satisfied** — owner approval recorded on PR #35, 1 August 2026 |
| 3 | **Executed and recorded restore test** | An **executed** restore meeting §7.4 with all outcomes recorded | **Not satisfied** — **no restore test has been executed** |
| 4 | **Operational monitoring** | §8.5's minimum signals collected and alerting configured, gaps recorded as gaps | **Not satisfied** — no monitoring exists |
| 5 | **Documented incident procedure** | A written procedure meeting §9, with a named responder | **Not satisfied** — none written |
| 6 | **Completed Thai-qualified legal review** | The review required by `docs/adr/ADR-012-…` Decision 8 — the Privacy Notice, Terms, pilot agreement, and `docs/architecture/02_Product_Requirements.md` §6 disclosures — reviewed by a Thai-qualified lawyer and approved by the owner | **Not satisfied.** That review **has not occurred**, and the approved Ethical Wall/conflict-checking disclosure wording is **not yet placed** (legal-review follow-up item 4) |
| 7 | **Every applicable `AGENTS.md` approval gate** | Recorded human approval per applicable change | **Not satisfied** |

**Authoritative context, not partial satisfaction of row 6.** The **ARCH-012 Thai-qualified persistence review was completed and approved on 1 August 2026** (`docs/legal/ARCH-012-Thai-Legal-Review.md`), answering the eight persistence questions and binding this architecture throughout. **It is a different review, of different material, and it does not satisfy row 6 in whole or in part.** Row 6 concerns the Privacy Notice, Terms, pilot agreement, and required disclosures under `docs/adr/ADR-012-…` Decision 8; that review has not occurred, and row 6 is **Not satisfied**.

**Prerequisites outside the evidence list that nonetheless block production:** **`PF-033` PostgreSQL Continuous Integration**, whose story contract is defined in `docs/implementation/03_Engineering_Backlog.md` and which **must land before `PF-080` begins** — **this document asserts no status for it and neither schedules nor renumbers it**; **`PF-080`/`PF-081`/`PF-082`**; every Release 0.1 requirement recorded in `docs/implementation/03_Engineering_Backlog.md`, including the backup-and-restore-test, monitoring, and incident-procedure operations requirements and the in-product and contractual disclosure requirements; and the owner/legal decisions in §17.

**Missing evidence blocks release, regardless of date.** No item may be waived, deferred past first production access, marked "in progress" as though satisfied, or satisfied by a plan to satisfy it later. **A date is never a reason to move a control** (Constitution Article 47).

**15 September 2026 is a target, not a contractual commitment. 31 August 2026 is a public-website and sales-readiness target and confers no production access** — a sellable public website is not a deployed service, and **no marketing, sales, or website statement may assert or imply that the product is available, secure, compliant, certified, or production-ready.** All such copy remains **draft** pending the Thai-qualified legal review required by `docs/adr/ADR-012-…` Decision 8, which **has not occurred** — a review distinct from, and not satisfied by, the ARCH-012 persistence review recorded in `docs/legal/ARCH-012-Thai-Legal-Review.md`. The approved Ethical Wall/conflict-checking disclosure wording exists (legal-review Decision 7) but **its placement in the pilot agreement and in-product surfaces remains outstanding** (follow-up item 4).

**The gate is a decision point with a recorded outcome**, granted by an explicit owner decision naming the evidence relied on. **Nothing in this document grants production access.** Approval of this document satisfies exactly **row 2**.

---

## 12. Operator and support access in production

**Production operators have zero standing access to Firm data.** An infrastructure operator holds **no** standing ability to read, export, aggregate, or infer a Firm's `Client`, `Matter`, `MatterClient`, `MatterTeam`, `Task`, conflict-attestation, or activity content. **Owning, deploying, or administering the platform confers no access to what is inside a Firm.**

**Firm-data access is available through exactly one path: IdentityAccess's `PrivilegedAccessGrant`** — purpose-bound, time-limited, strongly authenticated with step-up, dually attributed with **no silent impersonation**, recorded in a **Firm-visible, append-only support-access history** (`docs/adr/ADR-014-…`; Constitution Article 29). **Infrastructure administration is not that path and never becomes it.**

**Infrastructure administration operates on the platform, never on Firm content.** In scope: deploying and rolling back an artefact; executing a migration under §10.2; restarting, scaling, and supervising processes; reading content-free telemetry; managing certificates, networking, and platform configuration; executing a backup or restore under §7.2. **Structurally out of scope: reading Firm business rows**, made real by the runtime role's privileges, the migration role's non-standing credential and its prohibition on serving requests or operational inspection, `FORCE ROW LEVEL SECURITY` denying even the owning role its ordinary exemption, transaction-scoped Firm context built only from verified identity and membership, fail-closed unset context, and the absence of any reporting role.

**A production database credential capable of reading Firm business rows may not exist as a standing operator credential**, and **no human holds a standing migration-role credential** (§6.2).

**A genuine operational need to inspect Firm data does not create a new path; it uses the `PrivilegedAccessGrant` or it is refused.** "It was faster through the database" is not an authorization. Where the grant mechanism does not yet exist because Release 0.1 has not been built, the inspection does not happen and the limitation is recorded (§9.4).

**Break-glass remains excluded as a capability, and nothing here introduces one.** Constitution Article 29's break-glass rules remain fully in force and unamended. **Infrastructure administration is never used for break-glass purposes**, and the absence of a break-glass capability is never a reason to route an emergency through a database prompt.

**Every access decision here is an authorization matter and hits the `AGENTS.md` approval gate.**

---

## 13. Planned platform maintenance

**Planned platform maintenance is platform availability management. It is never a Firm-level suspended state and never a per-Firm disable.**

- It applies to the **whole platform**, never to one Firm or a subset.
- It **creates, changes, and reads no `Firm`, `FirmProvisioning`, or `SubscriptionEntitlement` state.**
- It is never described, labelled, surfaced, or audited as a Firm suspension, and never used to achieve the effect of one.
- **Constitution Article 46 is unamended.** A Firm-level suspension or emergency-disable capability remains reserved for its own separately approved decision defining the authorizing authority, session effects, recovery path, Firm notification, the treatment of queued and in-flight work, and audit semantics distinguishing it from both entitlement lapse and membership revocation. **Nothing here may be read as authorizing one.**
- Maintenance affecting availability is a **planned, announced, human-authorized operation with a recorded window** — an operator communication, not a product state.

---

## 14. Prohibited patterns

Each is prohibited. Where a pattern could be permitted under an explicit approval, that is stated.

- Deploying the development `Dockerfile`, `compose.yaml`, `php.dev.ini`, or `.env.example` values to staging or production.
- Shipping a secret, credential, key, token, or `.env` file inside a built artefact, repository, or configuration baked into an image.
- A long-lived, non-rotatable production credential.
- Sharing any credential, secret, key, database, backup, or service account between two environments, or between the runtime, migration, and ownership database roles.
- Restoring, copying, importing, or streaming production data, backups, exports, or logs into staging or any other ordinary non-production environment. **The only permitted destination for production data outside production is the ephemeral production-controlled recovery boundary**, for an authorized restore test, under every control approved by `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 6 (§3.2, §7.4).
- A standing superuser, `BYPASSRLS`, or Firm-business-readable database credential held by a human.
- Any operator path to Firm data other than an IdentityAccess `PrivilegedAccessGrant`; any impersonation, "log in as", or session-forging capability; any second privileged-access mechanism.
- Disabling Row-Level Security, dropping a policy, or granting `BYPASSRLS` for an operational, migration, backfill, or incident purpose.
- Editing, deleting, or truncating audit or support-access history, or disabling a trigger or grant that enforces append-only behaviour.
- A connection pooler that multiplexes below the transaction boundary; a session-level `SET` of Firm context.
- An `UNLOGGED` relation holding anything authoritative; authoritative state in Redis or any cache.
- Automatic migration on application startup, container start, merge, or as a side effect of deployment.
- Relying on a down-migration, `migrate:rollback`, or any schema rollback as a production recovery path; editing a historical migration; squashing migrations.
- Application rollback outside a recorded compatibility window, or past a completed contract phase.
- Describing a migration as "reversible" or "rollback-able" in a release note, runbook, or gate artefact.
- Rebuilding an artefact between staging verification and production deployment.
- Deploying without the same artefact and procedure having been exercised in staging.
- Credentials, tokens, session identifiers, recovery material, Firm or Matter content, privileged narratives, or client personal data in a log line, metric, trace, alert, error page, or exception message; redaction applied downstream rather than at emission.
- Treating operational logs as the authoritative audit record, emitting audit as a log line, or letting a telemetry failure block, degrade, or bypass an audit write.
- A health endpoint disclosing a Firm identifier, Firm count, record count, version, dependency hostname, or error detail; a health check that reads a business relation or establishes Firm context.
- A monitoring component holding a credential able to read a Firm-scoped business relation; a per-Firm business metric, Firm ranking, Firm comparison, benchmark, or cross-Firm dashboard, report, or analytics capability.
- A Firm identifier, name, slug, domain, or derived Firm key used as a metric dimension, label, tag, bucket, partition, or dashboard grouping; any retained per-Firm operational series; or promoting a Firm identifier out of a restricted diagnostic record into a metric, dashboard, comparison, or reporting path.
- Sending telemetry to a third-party service before the owner authorizes it.
- Operator alerting that reaches a Firm user or is described as a reminder, deadline alert, or product notification.
- A queue worker, web process, or scheduled task running without a defined restart policy, bounded retry, failure visibility, and alerting.
- Resolving an incident by disabling an isolation control, by an unrecorded access, or by a production database prompt.
- Per-Firm maintenance, per-Firm read-only mode, or any platform operation that constitutes or is described as a Firm-level suspension.
- Waiving, deferring, or asserting a production-readiness gate item; claiming production access on the basis of a date.
- Claiming an availability target, uptime figure, RPO, RTO, high availability, failover, zero downtime, backup success, restore success, certification, compliance, or production readiness without executed evidence.
- Any AI participation in authorizing, executing, approving, or aborting a deployment, migration, rollback, restore, access grant, incident decision, or gate sign-off.

---

## 15. Explicit non-goals

This architecture does **not**: implement, provision, configure, purchase, contract for, or deploy anything; create or modify any Docker file, `compose.yaml`, CI workflow, environment file, configuration file, migration, schema, role, grant, policy, source file, test, dependency, or GitHub setting; select, endorse, evaluate, benchmark, price, or recommend a hosting provider, cloud platform, region, managed database service, container orchestrator, connection pooler, load balancer, CDN, backup product, storage service, replication or high-availability product, secret-management or key-management product, observability/APM/log-aggregation/tracing/alerting/paging/status-page/incident-management product, migration or schema-diff tool, artefact registry, or any other vendor, provider, product, or package; decide hosting jurisdiction, data residency, or subprocessor approval; authorize any expenditure, contract, trial, or account creation; define capacity, sizing, throughput, latency, scaling, autoscaling, high availability, failover, replication, multi-region, or disaster-recovery topology; define or claim an RPO or RTO **value**, a backup frequency, a retention period for backups, logs, telemetry, or audit, or a generation count; **take a backup, execute a restore, execute or schedule a restore test**, or claim any backup or restore has succeeded; **execute, authorize, or schedule any deployment, migration, backfill, or rollback**, or claim any has occurred; grant, imply, or schedule production access, or claim any gate item is satisfied, waived, or on track; define, assert, imply, or default any incident-notification duty, recipient, threshold, or timeline; adopt Redis, any PostgreSQL extension, or any other runtime dependency without its own approved story and the `AGENTS.md` new-runtime-dependency gate; decide whether `firm_id` carries a foreign key to `Firm`, which belongs to the implementing `PF-080`/`PlatformAdministration` story; design a per-Firm export/import, logical restore, or data-portability capability; create a Firm-facing notification, reminder, alert, or escalation capability; create a security-event store, a second audit record, or any authoritative record outside `docs/adr/ADR-018-…`'s; create a second operator or privileged-access path, an impersonation or support-console capability, or a break-glass capability; define or approve a Firm-level suspension, emergency-disable, or per-Firm maintenance capability, or read Constitution Article 46 as permitting one; create a cross-Firm read role, view, projection, dashboard, report, benchmark, or analytics capability, or a Reporting bounded context — which Constitution Article 44 reserves and which this document **neither approves nor permanently prohibits**; define authentication, authorization, session, MFA, recovery, invitation, membership, or entitlement behaviour, which remain IdentityAccess's and `PlatformAdministration`'s; define an Ethical Wall, conflict-checking, or per-user Matter-visibility mechanism in any layer; define an audit-purge or partition-detachment path, or assert any audit retention period; set a numeric retention period for backups, logs, telemetry, audit, or pre-authentication security events; define a legal-hold, backup-expiry, incident-notification, or restore-test **procedure**; assert any notification outcome or timeline; place the approved Ethical Wall/conflict-checking disclosure; or otherwise supply any parameter, procedure, or evidence that `docs/legal/ARCH-012-Thai-Legal-Review.md` leaves to separately approved follow-up; assert any story's status, or schedule, rename, or renumber `PF-033` or any operations requirement; change `phpunit.xml`, any CI workflow, workflow permission, pinned action, the `Protect main` ruleset, or the four required check names; introduce an AI capability, grant AI any authority, or modify `docs/architecture/05_AI_Architecture.md`; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; claim that any described control, property, signal, or procedure is implemented, built, collected, configured, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

**No capability, control, property, or procedure described in this document is claimed to be implemented.**

---

## 16. Security and professional-responsibility consequences

1. **Environment separation is a confidentiality control, not an engineering convention.** A production restore into staging exposes privileged client work product to an environment with weaker access control, weaker audit, and a wider operator population — for clients who never consented and are not told. That is why §3.2 requires legal review and an explicit authorization rather than an engineer's judgement.

2. **A shared credential collapses the separation entirely.** If staging can reach production's database, staging *is* production for confidentiality purposes, whatever the diagram says.

3. **The development artefact is an exposure if deployed.** `display_errors = On` plus `APP_DEBUG=true` turns any unhandled exception into a disclosure of configuration, query text, and file paths, which `docs/architecture/07_API_Standards.md` §19 already prohibits. §2.4 removes the possibility structurally rather than relying on an environment variable being set correctly.

4. **A standing operator credential over Firm data is a second privileged-access mechanism** — standing, unbounded by purpose, invisible to the Firm, and outside the support-access history Constitution Article 29 requires. It reads **every** Firm's Clients, Matters, and conflict attestations, parties who may be adverse to one another. §12 closes the infrastructure route that would otherwise bypass `docs/adr/ADR-014-…` entirely.

5. **Silence about infrastructure access is the more dangerous failure.** `docs/adr/ADR-014-…` names both: standing operator access becoming normal, and refusing to design any path so the first incident is handled through a database console with no auditable record. §12 exists so the second does not arrive by omission.

6. **Residual risk is stated rather than concealed.** The controls bind every actor operating through the application and every ordinary operational path. **They are not claimed to restrain an actor holding a platform's own root or provider-administrator capability**, who could alter grants, disable a trigger, or read storage directly. That actor is restrained by role separation, the approval gates, the recorded-and-authorized-use rule, and organizational control — **procedural controls, not represented here as technically enforced.** This matches the precedent `docs/adr/ADR-018-…` and `docs/adr/ADR-020-…` set for audit enforcement, and it matters because an overstated control stops people applying the compensating one.

7. **Statement-level pooling would produce an intermittent, load-dependent cross-Firm read** that passes every application check and appears only in production — the most dangerous failure shape available to a legal-practice platform. §6.3 refuses the topology rather than mitigating it.

8. **A migration is the most privileged operation in the system**, and one that leaves Row-Level Security off is a cross-Firm disclosure waiting to happen, with nothing failing. §10.2 makes verification part of the operation and makes restoring the isolation control the first recovery action — ahead of restoring service.

9. **Documenting `migrate:rollback` as a recovery path is a false assurance about data that is already gone**, and in a legal practice the lost data is a Matter, a deadline, or a conflict attestation — a client-facing professional failure. **An overstated reversibility claim is itself a safety hazard.**

10. **Backups are a full copy of everything the platform protects.** A backup with weaker access control is a complete confidentiality bypass of every control the running system enforces, which is why §7.1 requires backups be protected **as production data**.

11. **A restored database with Row-Level Security off looks entirely healthy.** §7.2 makes policy, privilege, and forced-RLS verification part of what "restored successfully" means.

12. **A restore that loses audit records destroys the record of what happened** at the moment it matters most.

13. **A log line containing Matter content replicates itself** into every sink, backup, and index, and outlives the request by whatever retention applies. §8.1's redaction at emission is the only point at which the copy count is one.

14. **Conflating logs with audit weakens both** — audit gains a mutable, lossy substrate; logging gains an integrity expectation it cannot meet. After-the-fact professional accountability has to survive a rotation policy.

15. **Monitoring is the most plausible accidental route to a cross-Firm read role.** It is easy to justify, built by people focused on availability, and lives outside the review path that guards the application's isolation. §8.4 forecloses it.

16. **An unauthenticated health endpoint that counts anything is an enumeration channel**, contradicting a Release 0.1 non-negotiable regardless of how well the application enforces it.

17. **A silently absent queue worker is a silent-control-absence hazard** — outbox rows accumulating unpublished with nothing failing, the same shape as an abandoned policy migration leaving Row-Level Security off.

18. **Operator alerting must never become a Firm-facing notification**, because Release 0.1 explicitly discloses that no reminder or notification exists, and a Firm receiving one would reasonably infer the disclosed absence was inaccurate.

19. **Incident pressure is when controls get bypassed.** §9.5 states that availability never outranks isolation during an incident, because that is exactly when the trade looks tempting: an outage is recoverable, a disclosure of one client's matter to another firm is not.

20. **Per-Firm restore being unsupported is a real limitation with potential professional consequences**, and its legal significance is unresolved. §7.3 states it now rather than during the conversation in which a Firm asks for it.

21. **Refusing to state an RPO, RTO, or uptime figure is a professional-responsibility position, not modesty.** A Firm that believes recovery is fast, or the platform highly available, makes different decisions about where its deadline data lives.

22. **Maintenance mode is one careless design away from becoming the Firm-level disable Constitution Article 46 forbids inventing.** The dangerous version is a well-meant "pause Firm X while we fix their data" — a Firm-wide disable with no authorizing authority, no defined session effect, no recovery path, no notification, and no audit semantics. §13 forecloses it by stating what maintenance is not.

23. **Deploying without the evidence would be precisely the failure Constitution Article 47 anticipates** — a date moving a control. The controls at risk are Firm isolation, immutable audit, and the ability to recover a Firm's data at all.

24. **A sales-readiness date is when readiness language appears.** A marketing claim of security, compliance, or readiness would be false in a way a pilot Firm would reasonably rely on. §11 forecloses it; the copy remains draft pending the Thai-qualified review required by `docs/adr/ADR-012-…` Decision 8 — **a review distinct from the completed ARCH-012 persistence review**.

25. **AI holds no authority over anything in this document.** AI never selects, provisions, configures, deploys, or destroys an environment; never holds, reads, or rotates a secret; never receives shell, database, or console access; never authorizes or performs operator access; never executes or approves a migration, rollback, backup, or restore; never declares, classifies, or closes an incident; never signs off a gate item; and is never an authorization authority (Constitution Articles 6, 26, 28, 39, 40). **Release 0.1 contains no AI capability.**

26. **No certification, compliance, or legal-sufficiency conclusion is asserted.** The **ARCH-012 Thai-qualified persistence review was completed and approved on 1 August 2026** (`docs/legal/ARCH-012-Thai-Legal-Review.md`); the **separate Thai-qualified review required by `docs/adr/ADR-012-…` Decision 8** — covering the Privacy Notice, Terms, pilot agreement, and required disclosures — **has not occurred.** **No production access has been authorized, and the complete production-access gate is not satisfied**; two of its seven evidence items are presently satisfied: the **approved database design** and this **approved deployment architecture**. Nothing described here is claimed to be implemented, tested, or effective.

---

## 17. Unresolved and deferred items

Recorded openly rather than presented as settled. **Legal questions are listed separately below and none of them is answered anywhere in this document.**

### 17.1 Owner decisions — not decided, and no value invented

| Item | Owner | Note |
|---|---|---|
| **Hosting jurisdiction and data residency** | Business/legal owner | Constrains provider selection, backups, and every §17.2 question. ***Not decided — owner/legal decision required.*** |
| **Provider and subprocessor approval** | Business/legal owner | Which organizations may process, store, or transit Firm data. **No provider is named anywhere in this document.** |
| **RPO and RTO values** | Business/legal owner | §7.5 defines the framework only. **No value is proposed, defaulted, implied, or inferable.** Both are commitments belonging in the pilot agreement, which is draft. |
| **Backup retention period and generation count** | Business/legal owner | Additionally blocked on §17.2 item 1. **No period asserted.** |
| **Operational log and telemetry retention** | Business/legal owner | Additionally blocked on §17.2 items 1 and 8. **No period asserted.** |
| **Third-party observability authorization** | Business/legal owner | Whether any external service may process platform telemetry at all (§8.7). |
| **Availability or uptime commitments** | Business/legal owner | A contractual act belonging in the pilot agreement. **None made.** |
| **Budget, and whether any paid HA posture is procured** | Business/legal owner | Constrains §2.5 directly. |
| **Named incident responder, escalation path, after-hours expectation** | Business/legal owner | §9.6. An escalation path naming nobody is not a procedure. |

### 17.2 Thai-qualified legal-review disposition, and what remains

**ARCH-012's eight legal questions were reviewed and approved on 1 August 2026** by Michael Jand, repository owner acting in the expressly stated capacity of Thai-qualified legal reviewer. The authoritative record is `docs/legal/ARCH-012-Thai-Legal-Review.md`; `docs/architecture/03_Database_Design.md` §22.2 carries the summary. **That record does not certify compliance, production readiness, or the legal sufficiency of any unimplemented control, and neither does this document.**

**How each approved decision binds this architecture:**

| Legal-review decision | What it decides | Carried in |
|---|---|---|
| **1** — backups, erasure, audit retention, legal hold | Live deletion on authorization; encrypted backup generations age out under an approved schedule rather than selective rewriting; legal hold suspends in-scope deletion and expiry | §7.1, §7.5 |
| **2** — audit retention and partition detachment | A separately approved minimum period per stream; detachment only after expiry, no applicable hold, and recorded authorization. **No numeric period** | §8.7 |
| **3** — per-Firm point-in-time restore | **Not required for Release 0.1**; whole-database recovery is the baseline; a Firm-scoped export is never represented as restore; a later requirement triggers database-redesign review | §7.3 |
| **4** — cross-Firm existence disclosure | High-severity incident requiring containment, evidence preservation, Thai-qualified assessment, and a recorded notification decision. **No universal outcome or timeline** | §9.2, §9.3 |
| **5** — duplicate side effects | Idempotency mandatory but not sufficient by itself; a client-facing duplicate requires recorded assessment, remediation, and a communication decision; exactly-once is never claimed | §7.3, §9.2, §9.3 |
| **6** — restored production data | Synthetic data only in ordinary non-production; a production-data restore test only in an isolated, production-controlled recovery environment under six named controls; masking not presumed sufficient | §3.2, §7.4 |
| **7** — Ethical Wall / conflict-checking disclosure | The baseline wording is **approved**; **placement remains outstanding** | §11 gate row 6 |
| **8** — platform-realm pre-authentication security events | Minimize collection; avoid raw identifiers where possible; prohibit credentials, secrets, and body dumps; restrict access; never Firm-visible; short separately approved retention. **No numeric period** | §8.7 |

**What the legal review expressly did not do**, and what therefore remains outstanding — these are parameters, procedures, and evidence the approved decisions require, and **none reverses an approved decision**:

1. **Numeric backup, audit-stream, and pre-authentication-event retention periods** — not set (§7.5, §8.7).
2. **Legal-hold application, release, and backup-expiry procedures** — not defined (§7.5).
3. **The incident assessment and notification decision procedure** — not defined (§9.3).
4. **Placement of the approved Ethical Wall/conflict-checking disclosure** in the pilot agreement and product surfaces — outstanding (§11).
5. **The isolated restore-test procedure, and an executed and recorded restore test** — neither approved nor executed (§7.4, §11 gate row 3).
6. **Implementation and verification of the technical controls** through separately approved stories — none implemented.

**A separate review remains outstanding.** `docs/adr/ADR-012-…` Decision 8 requires Thai-qualified review of the **Privacy Notice, Terms, pilot agreement, and the `docs/architecture/02_Product_Requirements.md` §6 disclosures.** That review is **distinct from the ARCH-012 persistence review and is not satisfied by it**; it **has not occurred**, and all such copy remains **draft** (§11 gate row 6).

**Raised by this document and not answered by the approved record:**

- **Incident-notification duties, recipients, thresholds, and timelines** in any specific incident — expressly left to the facts and then-applicable duties by legal-review Decision 4. **No duty, threshold, or timeline is asserted, implied, or defaulted anywhere in this document.**

### 17.3 Technical and tracking items

| Item | Owner | Note |
|---|---|---|
| **Schema-drift detection mechanism** | Its own approved story | §10.2 makes it a blocking preflight; `docs/adr/ADR-020-…` Decision 10 records it as unbuilt. Not designed here. |
| **Readiness and dependency-health surfaces** | The monitoring operations requirement | §8.3; stock `/up` is retained as liveness only. |
| **Compatibility-window recording mechanism** | The deploying story | §10.3; the metadata does not exist today. |
| **PostgreSQL CI story identifier — unassigned** | Tracking-file synchronization | The requirement is approved and recorded; **no identifier exists, and none is asserted here.** |
| **Whether Redis is ever adopted** | Its own approved story | §6.4; requires a demonstrated necessity and the new-runtime-dependency gate. |
| **Outbox lease duration, batch size, reclamation cadence** | `PF-091`, `PF-092` | Unchanged; not set here. |
| **Stale cross-references to `03_Database_Design.md` as an "empty placeholder"** | Tracking-file synchronization | `docs/README.md`, `02_Product_Requirements.md` §8/§10, `08_Roadmap.md`, `03_Engineering_Backlog.md`, and ADR-012 still describe it as empty; `docs/README.md`'s ADR table lists neither ADR-016–021 nor ADR-022–026. **ARCH-012 §22.1 already records this; this story is likewise barred from editing those files.** |

---

## 18. Invariants

- No vendor, provider, product, region, or package is selected by this architecture; procurement is a separate owner-approved decision.
- Four ordinary environment classes exist — local development, CI/test, staging, production — and separately one ephemeral, production-controlled recovery boundary may be provisioned solely for an authorized restore test; it is not a reusable application environment, not staging, not an ordinary class, inherits production classification and controls, and is destroyed under the approved retention and deletion procedure.
- Ordinary non-production environments hold synthetic data only, and **production data is never placed in staging or any other ordinary non-production environment**. **Production data may be restored only into the ephemeral production-controlled recovery boundary**, under **explicit authorization, least-privilege access, access logging, encryption, an approved retention and deletion procedure, and recorded completion** — the controls approved by `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 6, **a completed review, not a pending one**. **The retention and deletion procedure itself remains outstanding** (legal-review follow-up items 1 and 5) and must be approved before such a restore is performed.
- No credential, secret, key, database, backup, or service account is shared between environments or between database roles.
- No production artefact contains a secret; production secrets live in an external managed secrets boundary and appear outside it only as opaque references.
- Production credentials are short-lived workload identities where supported, otherwise rotatable, least-privilege, and purpose-bound with bounded overlap; no long-lived non-rotatable production credential exists.
- The development image, compose stack, php.ini, and `.env.example` values are never deployed to staging or production.
- One artefact digest is promoted from staging to production; a rebuild invalidates verification.
- PostgreSQL 16 is the minimum; the required capability set is a deployment precondition.
- The migration role owns the schemas and relations, is subject to forced Row-Level Security, is never used to serve a request or for operational inspection, is neither superuser nor `BYPASSRLS`, and its credential is non-standing; the application runtime role is not the owner and holds no DDL, no `BYPASSRLS`, and no superuser capability; no credential spans the two.
- `FORCE ROW LEVEL SECURITY` denies the owning role its ordinary policy exemption, including during a migration; no role holds `BYPASSRLS` or superuser; no reporting or analytics role exists; no human holds a standing superuser, `BYPASSRLS`, or migration-role credential.
- Connection pooling preserves transaction boundaries; statement-level pooling is prohibited and pooling mode is verified, not assumed.
- Redis is not a Release 0.1 production dependency, and no authoritative state ever lives in Redis or any cache.
- Encryption in transit on every hop leaving a host; encryption at rest for the database and every backup; the database is never publicly reachable.
- Point-in-time recovery remains possible; backups are protected as production data; a restore re-establishes Firm isolation and preserves audit continuity, and is verified rather than assumed.
- Per-Firm point-in-time restore is not supported; a Firm-scoped export is never represented as a restore.
- Recovery-driven event replay and duplicate side effects are acknowledged and not claimed to be prevented.
- No RPO, RTO, retention period, availability target, uptime figure, or notification timeline is asserted, defaulted, or inferable.
- Telemetry carries no credentials, tokens, Firm or Matter content, privileged narratives, or confidential payloads; redaction happens at emission.
- Operational logs are never authoritative audit records; a telemetry failure never blocks, degrades, or bypasses an audit write.
- Liveness, readiness, and dependency health are distinct; stock `/up` is liveness only; a health surface discloses nothing and never reads a business relation.
- No monitoring component can read a Firm-scoped business relation; no cross-Firm metric, dashboard, report, benchmark, or analytics capability exists.
- No Firm identifier is a metric dimension, label, tag, bucket, partition, or dashboard grouping; no per-Firm operational series is retained; and a Firm identifier permitted in a restricted diagnostic record under the bounded exception is never promoted into a metric, dashboard, comparison, or reporting path.
- The `database` cache, session, and queue drivers are a production selection made by this architecture on its own reasoning, not an inheritance of a local default; those stores are operational, never authoritative, and authoritative business and audit state remains governed solely by its own owning persistence model.
- Operator alerting never reaches a Firm user and never constitutes or implies a product notification.
- Every long-running process has a restart policy, bounded retry, failure visibility, and alerting.
- Migrations are explicit, human-authorized, preflighted, lock-bounded, verified, and recorded; they never run automatically; they never run as the application runtime role.
- A migration leaving Row-Level Security disabled or a policy dropped is a failed migration; restoring the isolation control precedes restoring service.
- Firm-spanning maintenance never disables Row-Level Security, drops a policy, or grants `BYPASSRLS`.
- Application rollback occurs only within a recorded compatibility window; schema rollback is prohibited; recovery is forward correction or an authorized restore.
- No zero-downtime, high-availability, failover, replication, autoscaling, or multi-region property is designed or claimed.
- Production operators hold zero standing Firm-data access; the only path to Firm data is an IdentityAccess `PrivilegedAccessGrant`; no second privileged-access mechanism exists; break-glass remains excluded as a capability with Constitution Article 29 in force.
- Planned maintenance is platform-wide availability management, never a Firm-level suspended state and never a per-Firm disable.
- Missing production-gate evidence blocks release regardless of date; no gate item is waived, deferred past first production access, or claimed satisfied without its evidence. **No production access has been authorized, and the complete production-access gate is not satisfied** — two of its seven evidence items are presently satisfied: the approved database design and this approved deployment architecture.
- A cross-Firm disclosure is a top-severity incident requiring legal assessment; availability never outranks Firm isolation or authorization, during an incident or outside one.
- AI holds no authority over any environment, secret, credential, deployment, migration, rollback, backup, restore, access grant, incident decision, or gate sign-off.

---

## 19. Proposed implementation stages

**Proposed only. None of these is approved, scheduled, or assigned a story identifier**, and each requires its own entry in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`, with its own Definition of Ready, before implementation begins. **No story's status is asserted here; `docs/PROJECT_STATUS.md` is authoritative.**

1. **Production build artefact** — the hardened image distinct from the development `Dockerfile` (§2.4).
2. **Environment provisioning** — staging and production, following the owner's provider decision (§2, §3).
3. **Secrets boundary integration** — runtime credential retrieval, rotation, and the removal of any environment-file dependency in deployed environments (§5).
4. **Database provisioning and role separation** — the three roles, forced Row-Level Security posture, pooling-mode verification (§6).
5. **Backup configuration** — full backups and write-ahead archiving, encryption, isolation, integrity verification (§7.1). **Backups and the executed restore test are owned by their own operations requirement, which carries no story identifier.**
6. **Health, telemetry, and alerting** — readiness and dependency-health surfaces, redaction at emission, the §8.5 signals, process supervision. **Operational monitoring is owned by its own operations requirement, which carries no story identifier.**
7. **Incident procedure** — the written procedure meeting §9. **It is owned by its own operations requirement, which carries no story identifier.**
8. **Migration execution tooling and schema-drift detection** — the preflight and verification steps in §10.2.
9. **Release and deployment procedure** — artefact promotion, compatibility-window recording, the deployment record (§10.1, §10.3).
10. **Production-readiness gate run** — evidence assembly and the recorded owner decision (§11).

---

## 20. Relationship to other documents

| Document | Relationship |
|---|---|
| `docs/architecture/01_OneLegalPro_Constitution.md` | Constitutional; prevails over this document in every case |
| `docs/adr/ADR-022-Deployment-Topology-and-Environment-Separation.md` | The topology and environment decision this document implements (§2, §3, §13) |
| `docs/adr/ADR-023-Configuration-Secrets-and-Production-Operator-Access.md` | The configuration, secrets, and operator-access decision this document implements (§4, §5, §12) |
| `docs/adr/ADR-024-PostgreSQL-Runtime-Backup-Restore-and-Recovery.md` | The database, backup, and recovery decision this document implements (§6, §7) |
| `docs/adr/ADR-025-Observability-Health-Alerting-and-Incident-Operations.md` | The observability and incident decision this document implements (§8, §9) |
| `docs/adr/ADR-026-Release-Deployment-Migration-Rollback-and-Production-Gates.md` | The release, migration, rollback, and gate decision this document implements (§10, §11) |
| `docs/architecture/03_Database_Design.md` (**Approved**) | The persistence baseline whose delegated deployment items §6 and §7 answer, and whose constraints this document satisfies |
| `docs/adr/ADR-016-…` through `ADR-021-…` (**Accepted**) | Tenancy, identifier, audit, outbox, migration, and idempotency decisions consumed unchanged |
| `docs/adr/ADR-020-…` (**Accepted**) | **Extended, never amended** — §10 supplies the operational procedure its Decision 9 required and the drift mechanism its Decision 10 named |
| `docs/adr/ADR-014-…` | **Extended, never amended** — §12 closes the infrastructure route that would otherwise bypass `PrivilegedAccessGrant` |
| `docs/architecture/04_Security_Architecture.md` | Platform-wide security baseline; this document is its deployment and operations expression |
| `docs/architecture/07_API_Standards.md` | §14 operational safeguards and §15 observability rules this document applies to the platform's own operation |
| `docs/architecture/02_Product_Requirements.md` | Release 0.1 scope, non-negotiables, and the production-access evidence list §11 operationalizes |
| `docs/adr/ADR-012-…` | Release 0.1 scope and the Decision 10 evidence list |
| `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` | Owns principals, sessions, privileged-access grants, and security events; **nothing here creates an identity, session, or authorization decision** |
| `docs/architecture/19_Platform_Administration_Architecture.md` | Owns `Firm`, provisioning, and entitlement; **nothing here reads, changes, or disables Firm state** |
| `docs/architecture/13_Practice_Management_Architecture.md` | Owns `Matter` and — when built — Ethical Walls; **no Ethical Wall exists in any layer here** |
| `docs/architecture/05_AI_Architecture.md` | **Unmodified by this story** |
| `AGENTS.md`, `CONTRIBUTING.md` | The approval gates, secret-handling rules, and serialization discipline this document repeatedly invokes |
| `docs/implementation/01_Implementation_Sprint_Plan.md`, `docs/implementation/03_Engineering_Backlog.md`, `docs/PROJECT_STATUS.md`, `docs/README.md` | **Deliberately unmodified by this story**; their synchronization is deferred (§17.3) |
