# ADR-025 — Observability, Health, Alerting, and Incident Operations

## Status

**Accepted.** Explicit repository-owner approval was recorded on PR #35 on 1 August 2026 after independent review and all four required `Protect main` checks passed on commit `a163fce`. Acceptance authorizes this architectural decision only, never implementation, deployment, provider engagement, or production access.

Authored by story **ARCH-013 — Deployment & Operations Architecture**, alongside `docs/architecture/20_Deployment_Operations_Architecture.md`, `docs/adr/ADR-022-…` through `ADR-024-…`, and `ADR-026-…`.

**Depends on `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…` through `ADR-021-…`, which are Approved and Accepted** — explicit repository-owner approval recorded on PR #34 on 1 August 2026, alongside the eight Thai-qualified legal-review decisions in `docs/legal/ARCH-012-Thai-Legal-Review.md`. **ARCH-013 is synchronized with that approved baseline.** ARCH-012's approval schedules no implementation and authorizes no deployment or production access, and neither does this ADR.

## Context

`docs/architecture/02_Product_Requirements.md` §2 item 22 puts **operational monitoring** and a **documented incident procedure** inside Release 0.1 scope as Operations deliverables, and §8 makes both production-access evidence items. Both are recorded as operations requirements in `docs/implementation/03_Engineering_Backlog.md`, neither carrying a story identifier. Nothing designs either.

Four approved constraints shape what that design may be.

**Audit is not logging.** Constitution Article 30 states that **operational logs are never the sole authoritative security record**, and `docs/architecture/07_API_Standards.md` §15 repeats it: "audit is append-only and is never the same thing as an operational log." `docs/architecture/03_Database_Design.md` §12.1 makes audit **persisted data with database-enforced append-only semantics**, not log output. A monitoring design that conflated the two would either weaken audit or fabricate an authoritative record out of a mutable one.

**Telemetry must not carry confidential payloads.** `docs/architecture/07_API_Standards.md` §15 requires metrics and traces to cover latency, error rate, quota consumption, and dependency health "without ever carrying confidential payloads", and §14 requires sensitive-field redaction in logs and errors. Constitution Article 29 forbids credentials, reusable secrets, recovery material, and full session tokens from appearing in logs, analytics, events, or audit payloads. `docs/architecture/04_Security_Architecture.md` §7 classifies Client and Matter data as Confidential, and privileged work product and walled-Matter data as Privileged/Restricted.

**Telemetry must not become cross-Firm reporting.** `docs/architecture/03_Database_Design.md` §4.1 records that a reporting/analytics database role **does not exist**, because creating a cross-Firm read role "would be that context arriving through a privilege grant" — Constitution Article 44 reserves a future Reporting bounded context requiring its own approved architecture. A monitoring stack that aggregates across Firms over business data is that same arrival by another route.

**Release 0.1 notifies nobody.** `docs/architecture/02_Product_Requirements.md` §6 discloses that Release 0.1 has **no automated reminders or notifications** — "deadlines are recorded and displayed; nothing notifies, reminds, escalates, or alerts". Operational alerting to the platform operator is a different thing entirely, and the two must not be confused in either direction: operator alerting is not a product capability, and its existence never implies a Firm-facing notification exists.

The repository's current state: `/up` — Laravel's stock health endpoint — is the only health surface; the development stack has container health checks for PostgreSQL and Redis only; there is no metrics, tracing, log-aggregation, alerting, or on-call arrangement of any kind.

## Decision

### Health checks

1. **Three health semantics are distinct and are never collapsed into one endpoint.**

   | Semantic | Question it answers | Consequence of failure |
   |---|---|---|
   | **Liveness** | Is this process irrecoverably stuck? | Restart the process |
   | **Readiness** | May this instance receive traffic right now? | Remove from rotation; do not restart |
   | **Dependency health** | Are the things this instance depends on reachable and behaving? | Operator signal; may or may not affect readiness |

   Collapsing them produces the two classic failures: a database blip restarts every healthy application process, or a process that cannot serve anything keeps receiving traffic because it is technically alive.

2. **The stock `/up` endpoint is insufficient as the complete production health design.** It is retained as a liveness signal, and it is not represented as readiness or dependency health. **Readiness and dependency-health surfaces are their own design**, owned by the monitoring operations requirement rather than by this ADR.

3. **A health surface discloses nothing.** No health endpoint returns a Firm identifier, a Firm name, a Firm count, a record count, a version string, a dependency hostname, a credential, a connection string, a stack trace, or an error detail from a downstream system. It returns a status and, at most, a coarse component state.

   This is not fussiness: unauthenticated surfaces are rate-limited and **enumeration-resistant** by `docs/architecture/04_Security_Architecture.md` §4, and denied-existence confidentiality (`docs/architecture/02_Product_Requirements.md` §9) is meaningless if an open endpoint reports how many Firms exist. **Any health surface carrying more than a coarse status is authenticated and operator-only.**

4. **A dependency-health check never becomes a data path.** It verifies reachability and responsiveness. It does not read a business relation, does not count rows in one, and does not establish Firm context. A database health check that ran a query against a Firm-scoped relation would need Firm context to succeed at all under `docs/architecture/03_Database_Design.md` §3.4's fail-closed rule — and manufacturing context for a health check is precisely the sort of ambient-context path §4.2 prohibits.

### Logging and telemetry

5. **Operational telemetry excludes, by construction and at the point of emission:** credentials, passwords, API keys, tokens, session identifiers, recovery material, and connection strings; Client, Matter, `MatterClient`, Task, and conflict-attestation **content**; privileged narratives and any legal work product; personal data of a Firm's clients; and any other Confidential or Privileged/Restricted material under `docs/architecture/04_Security_Architecture.md` §7.

   **Redaction happens where the log line, metric, or span is created — never as a downstream filter or a periodic scrub.** A downstream filter means the value already left the process, and a scrub means it already reached the sink, the backup of the sink, and the index.

6. **Non-Firm identifiers are permitted; content is not; and a Firm identifier is never a metric dimension.** A log line, metric, or trace may carry a correlation identifier, a request identifier, an actor identifier, a route, a status, and a duration. **It may not carry the values those identifiers point at** — a Matter's subject line is confidential.

   **`FirmId`, and every other Firm identifier, name, slug, domain, or derived Firm key, is prohibited as a metric dimension, label, tag, bucket, partition, or dashboard grouping.** There is **no retained per-Firm operational series, and no per-Firm comparison, ranking, or benchmarking of any kind.** A `FirmId` is not a secret (`docs/architecture/19_Platform_Administration_Architecture.md` §16), but a *retained time series keyed by Firm* is a cross-Firm operational dataset about identifiable law firms, and it is one dashboard away from the comparison Constitution Articles 44, 45, and 48 reserve for a separately approved Reporting context (Decision 9).

   **Exception, narrowly bounded — a Firm identifier in a restricted diagnostic log or trace.** Where incident diagnosis genuinely cannot proceed without knowing which Firm a fault affected, a Firm identifier may appear in a **diagnostic log line or trace span** subject to every one of the following, none of which is severable:

   - **purpose-bound to incident diagnosis only** — never routine operation, capacity planning, product analytics, or reporting;
   - **least-privilege restricted access**, to named responders under an authorization that is recorded;
   - **encrypted in transit and at rest**, and held inside the platform's own boundary (Decision 10);
   - **no Firm business content, Client or Matter content, or privileged narrative** travels with it — Decision 5 applies unchanged and is not relaxed by this exception;
   - **retention requires legally approved policy**; until that exists, the retention question in Decision 11 is unresolved and no period is defaulted;
   - **never promoted into a metric dimension, label, dashboard grouping, comparison, ranking, benchmark, aggregate, or any reporting path** — the exception ends at the diagnostic record and creates no derived series.

   Two further constraints attach to identifiers generally. A UUIDv7 **discloses its generation time** and its prefix is predictable (`docs/architecture/03_Database_Design.md` §6.4, §20.12), so identifiers in telemetry are identifying data with a retention consequence, not opaque noise. And identifiers are **never placed in URL parameters or query strings**, where intermediaries log them as a matter of routine handling (`docs/architecture/19_Platform_Administration_Architecture.md` §18).

7. **Correlation identifiers thread a request across logs, metrics, traces, and audit** (`docs/architecture/07_API_Standards.md` §15), so an investigation can be assembled from telemetry that carries no confidential content. This is what makes Decision 5 workable rather than merely restrictive.

8. **Operational logs are never authoritative audit records, and audit is never emitted as a log line.**

   - Authoritative audit is **persisted, append-only data** enforced by ungranted privileges and rejecting triggers (`docs/architecture/03_Database_Design.md` §12.1, §12.2; `docs/adr/ADR-018-…`). It is not written to a log sink, not reconstructed from one, and not considered complete because a log exists.
   - An operational log is **mutable, rotated, sampled, and lossy by design**. Treating it as a security record would assert durability and integrity it does not have (Constitution Article 30).
   - A telemetry outage, a dropped log line, or a full disk **never blocks, degrades, or rolls back an audit write**, and never causes a security-relevant operation to proceed unrecorded. Where the authoritative audit write cannot occur, the operation **fails closed** — that is ARCH-012's rule (§12.3), not a monitoring decision, and monitoring may not weaken it.

9. **Telemetry is Firm-agnostic in aggregate and never becomes cross-Firm reporting over Firm business data.**

   - Operational aggregates measure **the platform** — request rate, error rate, latency, queue depth, job failures, database connection counts, disk and memory. They do not measure **Firms** — no per-Firm business metric, no Firm ranking, no Firm comparison, no Matter or Client counts as a product signal.
   - **No monitoring component holds a database credential that can read a Firm-scoped business relation.** There is no reporting or analytics role (`docs/architecture/03_Database_Design.md` §4.1), and monitoring does not become the first holder of one.
   - **Constitution Article 44's reserved Reporting bounded context is neither approved nor permanently prohibited here.** If cross-Firm insight is ever wanted, it arrives through that approved architecture — preserving Firm isolation, authorization before retrieval, denied-existence confidentiality, purpose limitation, and the most restrictive applicable domain rule — not through a metrics pipeline.
   - **No metric is dimensioned by Firm**, and **no per-Firm operational series is retained** (Decision 6). A fault affecting one Firm is diagnosed through the bounded diagnostic-record exception in Decision 6, which produces no series and no dashboard grouping.

10. **Third-party observability, log aggregation, and error-tracking services are not authorized.** Sending telemetry to an external service makes that service a processor of platform data, and a redaction defect there is a confidentiality incident with a third party involved. **Whether any third-party observability service may be used at all is an owner decision, entangled with the hosting-jurisdiction and subprocessor questions in `docs/adr/ADR-022-…` Decision 12.** *Not decided — owner/legal decision required.* Until it is decided, telemetry stays within the platform's own boundary.

11. **Operational log and telemetry retention is not set here.** *Not decided — owner decision required.* The **decision rules** that bound it are approved: `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 8 requires that platform-realm pre-authentication security-event data **minimize collection, avoid raw submitted identifiers where technically possible, prohibit credentials, authentication secrets, recovery material, and request-body dumps, stay non-Firm-visible and restricted to named platform-realm service and security roles, and carry a short, separately approved retention period with access logging and a recorded deletion or expiry process**; Decision 2 requires a separately approved minimum retention per audit stream. **Neither record sets a numeric period** (follow-up item 1), and **no retention period is asserted, implied, or defaulted here** for logs, telemetry, or audit. Audit retention remains its own approved decision and **nothing here sets one.**

### Alerting and process supervision

12. **Alerting is an operator capability, categorically distinct from the Firm-facing notification Release 0.1 does not have.** `docs/architecture/02_Product_Requirements.md` §6 discloses that nothing notifies, reminds, escalates, or alerts a Firm user. Operator alerting **never** delivers to a Firm user, never surfaces in a Firm-facing interface, and **never becomes, is described as, or is allowed to imply a deadline reminder, a Matter alert, or any product notification capability.** An alert channel is not a product feature and creates no obligation the disclosed absence contradicts.

13. **An alert carries no confidential content** — Decision 5 applies to alert text, subject lines, and payloads identically, and more sharply, because alerts leave the platform's boundary to reach a human.

14. **Queue and web processes require explicit supervision**, and the requirement is design-level, not tooling-level: a defined **restart policy** with backoff; **bounded retry** with a defined failure destination rather than infinite redelivery; **failure visibility** — a failed job is observable, counted, and inspectable without reading Firm content; and **alerting** on sustained failure, worker absence, and queue growth.

    The development environment already demonstrates why: `README.md` records that the queue container stops on a fresh database because its tables do not exist yet, and requires a manual restart. In production, a silently absent queue worker means outbox rows accumulate unpublished with nothing failing — the same shape of silent-control-absence hazard `docs/adr/ADR-020-…` Decision 6 names for an abandoned policy migration.

15. **Minimum operational signals** for Release 0.1, stated as obligations on the monitoring operations requirement rather than as an implementation: process liveness and readiness per role; HTTP error rate and latency distribution; unhandled exception rate; database reachability, connection count, and connection-pool saturation; queue depth, job failure rate, and worker liveness; **migration state agreement with the recorded state — schema drift is detected, not discovered during an incident** (`docs/adr/ADR-020-…` Decision 10); backup job outcome and age of the most recent backup; certificate expiry; and disk, memory, and CPU headroom.

    **Naming a signal is not claiming it is collected.** None is implemented.

16. **A monitoring gap is a known state, not a healthy one.** Absence of an alert is not evidence of health where the signal is not collected; the production-readiness gate (`docs/adr/ADR-026-…`) records which signals exist rather than treating silence as a pass.

### Incident operations

17. **An incident procedure must exist and be recorded before production access.** It must be a documented, followed procedure covering, at minimum: how an incident is declared and by whom; severity classification; the accountable responder; how a communication decision is made; how the timeline and actions are recorded; how the incident is closed; and how findings are carried into corrective work.

    **No incident procedure currently exists, and none has been written.** It is an Operations deliverable owned by the corresponding operations requirement in `docs/implementation/03_Engineering_Backlog.md`, which carries no story identifier; **this ADR defines what it must contain and does not substitute for it.** **No notification duty, recipient, threshold, or timeline is asserted anywhere** (Decision 19).

18. **Severity classification includes a class the platform's own architecture defines as its most serious.** A **cross-Firm disclosure** — one Firm's data reaching another Firm's context, or an existence disclosure about a Firm or its records — is, per `docs/architecture/03_Database_Design.md` §20.1, **a security and confidentiality incident requiring legal assessment**, not a data-quality defect. In a legal-practice platform the affected parties may be adverse to one another. Such an incident is declared at the highest severity, and legal assessment is part of its handling rather than an optional follow-up.

    Other classes the procedure must carry: unavailability; data loss or suspected data loss; suspected or confirmed secret compromise (`docs/adr/ADR-023-…` Decision 8); audit gap or suspected audit tampering; **a discovered isolation-control absence** — Row-Level Security disabled, a policy missing, an over-privileged role — which is treated as a disclosure-risk incident even absent evidence of an actual cross-Firm read; unauthorized or out-of-procedure operator access; and a recovery-driven duplicate side effect (`docs/adr/ADR-024-…` Decision 14).

19. **Incident response has an approved decision rule; the procedure and every notification outcome remain outstanding.**

    **Approved and binding** (`docs/legal/ARCH-012-Thai-Legal-Review.md` Decisions 4 and 5):

    - **Every confirmed cross-Firm existence disclosure is a high-severity security and confidentiality incident**, and its response **must** include **immediate containment, evidence preservation, Thai-qualified legal assessment, and a recorded decision** on affected persons, professional-conduct consequences, and any required notification.
    - **A recovery-driven or crash-driven duplicate with a client-facing consequence requires recorded assessment, remediation, and a decision on communication or notification.** Technical idempotency is mandatory but **is not declared legally or professionally sufficient by itself**, and **exactly-once execution or delivery must never be claimed**.

    **Outstanding, and not asserted here:** **no universal notification outcome or timeline is asserted** — those depend on the incident facts and then-applicable duties — and the **incident assessment and notification decision procedure** itself is a recorded follow-up (`docs/legal/ARCH-012-Thai-Legal-Review.md` follow-up item 3) that **does not yet exist.** ***Not decided — owner decision required.*** **No timeline, threshold, recipient, or duty is asserted, implied, or defaulted anywhere in this ADR.**

    Also decided: an incident's facts are **recorded contemporaneously and preserved**, so that whatever duty is determined can be discharged from a real record rather than reconstructed from memory — which is what Decision 4's evidence-preservation requirement makes mandatory rather than merely prudent.

20. **Incident investigation operates under the same access rules as everything else.** An investigator has **zero standing access to Firm data** (`docs/adr/ADR-023-…` Decision 10). Investigation proceeds from telemetry that carries no Firm content, from synthetic reproduction, and — where genuinely necessary and available — from an IdentityAccess `PrivilegedAccessGrant` under `docs/adr/ADR-014-…`, purpose-bound, time-limited, and Firm-visible.

    **Where an incident cannot be fully diagnosed within those constraints, that is recorded as a limitation of the investigation.** It is never resolved by a production database prompt, by restoring production into a non-production environment (`docs/adr/ADR-022-…` Decision 3), or by an unrecorded access. Urgency is not an authorization.

21. **The responder, escalation path, and any after-hours expectation are the owner's to name.** *Not decided — owner/legal decision required.* This repository is single-owner; an escalation path that names nobody is not a procedure. **No on-call tooling, rotation, paging service, or status page is selected or designed here.**

22. **An incident never rewrites a committed fact.** Constitution Articles 30 and 36 hold during an incident exactly as outside one: a committed business or security fact is not edited, an audit record is not corrected by rewriting, and **availability never outranks Firm isolation, authorization, or privilege protection.** Restoring service by disabling an isolation control is prohibited (`docs/adr/ADR-023-…` Decision 15) — an outage is recoverable, a cross-Firm disclosure is not.

23. **AI holds no authority here.** AI never declares, classifies, triages, escalates, or closes an incident; never authorizes access during one; never suppresses, edits, or acknowledges an alert as an authority; never reads telemetry containing Firm content, since none exists; and is never an authorization authority (Constitution Articles 6, 26, 28, 39, 40). **Release 0.1 contains no AI capability.**

24. **This ADR asserts no story status and schedules nothing.** `docs/PROJECT_STATUS.md` is authoritative. The four required `Protect main` check names are preserved exactly.

## Alternatives considered

- **Use `/up` as the single health endpoint for liveness, readiness, and dependency health.** Rejected — one endpoint cannot carry three different failure responses. A restart is right for a stuck process and wrong for a database blip; removing an instance from rotation is right for a warming instance and wrong for a deadlocked one.
- **Have the readiness check query a business relation to prove the database really works.** Rejected — it would need Firm context to pass `docs/architecture/03_Database_Design.md` §3.4's fail-closed check, and manufacturing context for a probe is exactly the ambient-context path §4.2 prohibits. Decision 4 keeps health checks off the data path.
- **Return component detail and version information from the health endpoint** for easier debugging. Rejected — it is an unauthenticated fingerprinting and enumeration surface, and `docs/architecture/04_Security_Architecture.md` §4 requires unauthenticated surfaces to be enumeration-resistant. Detail moves behind authentication (Decision 3).
- **Log full request payloads and responses, redacting later at the aggregation layer.** Rejected — the value has already left the process, been written to disk, been shipped, and been indexed. Constitution Article 29's prohibition is on appearance in logs, not on appearance in queries over logs. Decision 5 redacts at emission.
- **Ship telemetry to a third-party observability service now**, since it is the fastest way to get visibility. Rejected as not this document's to authorize: it makes an external organization a processor of platform data and is entangled with hosting-jurisdiction and subprocessor questions that are the owner's (Decision 10).
- **Treat structured application logs as the audit trail**, avoiding a separate audit mechanism. Rejected outright — it would contradict Constitution Article 30 and `docs/adr/ADR-018-…`, and would replace database-enforced append-only records with a rotated, sampled, mutable stream. It would also make an audit gap indistinguishable from a log-shipping hiccup.
- **Let a failed audit write fall back to a log line so the operation can proceed.** Rejected — it converts a fail-closed guarantee into a silent downgrade, and produces exactly the "evidence of a control that does not exist" outcome `docs/architecture/03_Database_Design.md` §20.17 warns about. Decision 8 keeps the fail-closed behaviour ARCH-012 §12.3 defines.
- **Build per-Firm operational dashboards** so the operator can see how each Firm is using the platform. Rejected — that is cross-Firm reporting over Firm business data, which `PlatformAdministration` never owns (Constitution Articles 45, 48) and which Article 44 reserves for a future approved Reporting context. Decision 9 keeps operational metrics about the platform.
- **Reuse the alerting channel to notify Firm users of deadlines**, since the plumbing would exist. Rejected — Release 0.1 discloses that no automated reminder or notification exists (`docs/architecture/02_Product_Requirements.md` §6), and a channel that quietly became one would make a disclosed absence false. Decision 12 keeps operator alerting categorically separate.
- **Set a provisional log retention period** to avoid an unbounded store. Rejected — the approved legal record deliberately sets no numeric period for either audit streams or pre-authentication security events (`docs/legal/ARCH-012-Thai-Legal-Review.md` Decisions 2 and 8, follow-up item 1), and a number written here would become the number by quotation rather than by approval. Decision 11 records it open.
- **Define incident notification timelines** so the procedure is complete. Rejected, and the approved record is explicit about why: **no universal notification outcome or timeline is asserted**, because the outcome depends on the incident facts and then-applicable duties (Decision 4). What the record requires instead — containment, evidence preservation, Thai-qualified assessment, and a recorded decision — is carried in Decision 19.
- **Allow a read-only production database session during a severity-1 incident.** Rejected — it is the standing-credential failure `docs/adr/ADR-023-…` rejects, arriving under time pressure with no review. Decision 20 sends genuine need to the ADR-014 grant and records the limitation otherwise.

## Consequences

- **Investigations are harder and sometimes incomplete.** Diagnosis runs on content-free telemetry, correlation identifiers, and synthetic reproduction. Decision 20 requires recording the limitation rather than working around it.
- **Redaction at emission is engineering work in every emitting path**, not a single configuration setting, and it must be maintained as code grows.
- **A separate audit mechanism must exist** and cannot be satisfied by logging. That is `docs/adr/ADR-018-…`'s and the cross-cutting audit story's work, not monitoring's.
- **Telemetry stays inside the platform boundary** until the owner decides on third-party processing, which limits tooling choice and rules out a fast hosted-APM shortcut.
- **Retention is unbounded until decided**, which is itself an operational and privacy exposure and is recorded as one rather than resolved by a default.
- **The incident procedure is incomplete without a named responder**, which is the owner's to supply (Decision 21). A procedure naming nobody is not a procedure.
- **Cross-Firm disclosure being a top-severity class with mandatory legal assessment** means an incident type most platforms treat as a bug carries a legal step here. That is the correct weighting for a legal-practice platform.
- **Naming the minimum signals does not collect them.** The gap between the list and reality is visible at the readiness gate, which is the intent.

## Security and professional-responsibility consequences

1. **A log line containing Matter content is a confidentiality incident that replicates itself.** It is copied into every sink, every backup of that sink, and every index, and it outlives the request by whatever retention applies. Decision 5's redaction-at-emission is the only point at which the copy count is one.

2. **Conflating logs with audit weakens both.** Audit gains a mutable, lossy substrate; logging gains an integrity expectation it cannot meet. Constitution Article 30's "operational logs are never the sole authoritative security record" exists because after-the-fact accountability in a legal practice has to survive a rotation policy.

3. **An unauthenticated health endpoint that counts anything is an enumeration channel.** Denied-existence confidentiality is a Release 0.1 non-negotiable (`docs/architecture/02_Product_Requirements.md` §9); an open endpoint reporting Firm counts contradicts it regardless of how well the application enforces it.

4. **Monitoring is the most plausible accidental route to a cross-Firm read role.** It is easy to justify ("we only aggregate"), it is built by people focused on availability, and it lives outside the review path that guards the application's isolation. Decision 9 forecloses it: no monitoring component holds a credential that can read a Firm-scoped business relation.

5. **A silently absent queue worker is a silent-control-absence hazard.** Outbox rows accumulate unpublished with nothing failing — the same shape as an abandoned policy migration leaving Row-Level Security off (`docs/adr/ADR-020-…` Decision 6). Decision 14 requires worker absence to be an alerting condition, not an inference from an unrelated symptom.

6. **A cross-Firm disclosure may involve adverse parties**, which is why Decision 18 places it at the highest severity with legal assessment in the handling path rather than in a retrospective.

7. **Incident pressure is when controls get bypassed.** Decision 22 states that availability never outranks isolation during an incident, because that is exactly when the trade looks tempting: an outage is recoverable, a disclosure of one client's matter to another firm is not.

8. **Operator alerting must never become a Firm-facing notification**, because Release 0.1 explicitly discloses that no reminder or notification exists, and a Firm that received one would reasonably infer the disclosed absence was inaccurate (`docs/adr/ADR-015-…`'s discipline: an approximated control is more dangerous than an absent one).

9. **AI holds no authority over incident declaration, classification, escalation, closure, or access.**

10. **No certification, compliance, or legal-sufficiency conclusion is asserted.** The **ARCH-012 Thai-qualified persistence review was completed and approved on 1 August 2026** (`docs/legal/ARCH-012-Thai-Legal-Review.md`); the **separate Thai-qualified review required by `docs/adr/ADR-012-…` Decision 8** — covering the Privacy Notice, Terms, pilot agreement, and required disclosures — **has not occurred.** **No production access has been authorized, and the complete production-access gate is not satisfied**; two of its seven evidence items are presently satisfied: the **approved database design** and this **approved deployment architecture**. **No monitoring exists, no alert has been configured, and no incident procedure has been written.**

## Integration consequences

- **`docs/adr/ADR-018-…` and `docs/architecture/03_Database_Design.md` §12** — authoritative audit remains persisted and database-enforced. Decision 8 consumes that boundary and does not move it.
- **`docs/adr/ADR-022-…`** — supplies the four ordinary environment classes and the ephemeral recovery boundary; telemetry is separated per environment and never merged. Decision 20 inherits ADR-022 Decision 4's constraint on incident reproduction.
- **`docs/adr/ADR-023-…`** — supplies the redaction obligation (Decision 7 there) and the zero-standing-Firm-data-access posture Decision 20 here depends on.
- **`docs/adr/ADR-024-…`** — supplies backup-outcome and backup-age signals (Decision 15) and the recovery-driven duplicate class (Decision 18).
- **`docs/adr/ADR-026-…`** — consumes the drift signal, the readiness surface, and the "monitoring gap is a known state" rule as gate inputs.
- **`docs/adr/ADR-019-…` / `PF-091` / `PF-092`** — queue depth, publication lag, and worker liveness become operational signals when the outbox exists; lease and batch parameters remain those stories'.
- **IdentityAccess (EPIC-009)** owns security events and the security-event stream; **this ADR creates no security event, no security-event store, and no second security record.** Security analytics and anomaly detection remain ARCH-016 §671's, as signals for human review and never automatic authorization actions.
- **Practice Management** remains unaffected: no Ethical Wall exists in Release 0.1, and nothing here creates per-Matter or per-user visibility of any kind.
- **The cross-cutting audit story** (backlog EPIC-012 story 26) is where actor-attributed audit is designed; this ADR neither pre-empts nor substitutes for it.

## Explicit non-goals

This ADR does **not**: implement, configure, provision, or deploy any monitoring, metrics, tracing, logging, log-aggregation, alerting, paging, status-page, error-tracking, or incident-management capability; create or modify any source file, test, health endpoint, route, controller, configuration file, Docker file, `compose.yaml`, CI workflow, dependency, or GitHub setting; select, endorse, evaluate, price, or recommend an observability vendor, APM, log-aggregation service, metrics backend, tracing system, alerting or paging service, status-page product, incident-management tool, or any other vendor, provider, product, or package; authorize third-party processing of platform or Firm data; define or claim a log, metric, trace, or audit **retention period**; define, assert, imply, or default any incident-notification duty, recipient, threshold, or timeline; define or claim an availability target, uptime figure, error budget, service level, RPO, or RTO; create a Firm-facing notification, reminder, alert, or escalation capability, or read operator alerting as constituting one; create a security-event store, a second audit record, or any authoritative record outside `docs/adr/ADR-018-…`'s; create a cross-Firm read role, view, projection, dashboard, report, benchmark, or analytics capability, or a Reporting bounded context — which Constitution Article 44 reserves and which this ADR **neither approves nor permanently prohibits**; dimension, label, tag, bucket, partition, or group any metric or dashboard by Firm, retain any per-Firm operational series, or promote a Firm identifier out of a restricted diagnostic record into a metric, dashboard, comparison, ranking, benchmark, or reporting path; create a second operator or privileged-access path, or authorize any access to Firm data outside `docs/adr/ADR-014-…`'s `PrivilegedAccessGrant`; authorize any restore of production data into a non-production environment; execute, schedule, or claim a backup or restore test; set a numeric retention period for any log, telemetry, audit, or pre-authentication security-event stream, or assert any notification outcome or timeline, all of which the approved legal record leaves to separately approved follow-up; assert any story's status, or schedule, rename, or renumber `PF-033` or any operations requirement; change `phpunit.xml`, any CI workflow, or the four required `Protect main` check names; introduce an AI capability, grant AI any authority, or modify `docs/architecture/05_AI_Architecture.md`; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; claim that any signal, control, alert, or procedure is implemented, collected, configured, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

**Accepted conceptual architecture only.** It authorizes no application code, endpoint, instrumentation, telemetry pipeline, alert, dependency, infrastructure, deployment, or production access.

**No monitoring exists. No signal is collected. No alert is configured. No incident procedure has been written, and no incident has been handled under one.** **No capability described here is claimed to be implemented, tested, or effective.**

**No story's status is asserted here; `docs/PROJECT_STATUS.md` is the authoritative record.** The story ID is **ARCH-013**; the accompanying architecture document is `docs/architecture/20_Deployment_Operations_Architecture.md`.
