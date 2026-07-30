# ADR-018 — Audit Persistence and Append-Only Enforcement

## Status

**Proposed.** Not approved, and it authorizes nothing. Acceptance requires explicit owner approval recorded on the pull request; acceptance would authorize the architectural decision only, never implementation, deployment, or production access.

Authored by story **ARCH-012 — Data & Persistence Architecture**, alongside `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…`, `ADR-017-…`, `ADR-019-…`, and `ADR-020-…`.

## Context

Append-only audit is required in six separate places in the approved architecture, and never once specified as persistence:

- **Constitution Article 8** — published legal-source versions are immutable; corrections create new versions.
- **Constitution Articles 18–20** — document versions and approved knowledge versions are immutable; superseded versions stay auditable.
- **Constitution Article 23** — issued invoices and posted ledger entries are immutable; **posted history is corrected, never rewritten**; every consequential financial action and every denied access to a restricted financial record is an auditable event; deleting a record never destroys the audit fact it existed.
- **Constitution Article 30** — security events are append-only; **audit history is not editable by the actor being audited**; and **operational logs are never the sole authoritative security record**.
- **Constitution Article 45 / ARCH-019 §19** — `PlatformAdministration` owns its own administrative audit facts and **never stores or duplicates another context's audit records**; Firm-visible support-access history remains IdentityAccess's.
- **`docs/architecture/02_Product_Requirements.md` §9** — immutable audit, append-only, correction as a new record, audit not editable by the audited actor, as a Release 0.1 non-negotiable.

`docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §521 goes further and models `SecurityEventStream` as **its own boundary** rather than as entities inside each aggregate, "because audit must be writable when the subject aggregate is being denied, suspended, or destroyed, and must never be mutable by the aggregate it records."

**What has never been decided is how any of that is enforced.** Three concrete gaps:

1. **Where the authoritative audit record lives.** Article 30 says logs are not the sole authoritative record, which implies persisted relations — but does not say so, and does not say what happens if a story reaches for a log file instead.
2. **What makes append-only true.** "Append-only" as a code convention is a comment. Any repository method, any migration, any ad-hoc `UPDATE` under the application role defeats it, and the defeat is invisible afterwards — because the record that would have shown it is the record that was altered.
3. **Whether audit is one relation or many.** A single platform audit table is the obvious implementation and it would silently violate Article 45 (`PlatformAdministration` duplicating other contexts' audit records), collapse the deliberate distinction between an entitlement lapse and a membership revocation (ARCH-019 §9), and give every context read access to every other's audit content.

Audit is the record that explains every other failure on the platform. If it is editable, nothing else in `docs/architecture/03_Database_Design.md` is verifiable after the fact.

## Decision

1. **The authoritative audit record is persisted relational data, not logs.** Operational logs are **never** the sole authoritative security or business record (Constitution Article 30). A log is unindexed, unqueryable under authorization, unreachable by a Firm-visible history surface, and — being a stream someone can rotate, truncate, or lose — is not a record a Firm can rely on for professional accountability. Logs remain useful for operations and carry **safe metadata only**.

2. **Append-only is enforced by the database, through two independent mechanisms, deliberately redundant.**
   - **Privileges.** The application runtime role holds `INSERT` and `SELECT` on audit relations and **is not granted `UPDATE` or `DELETE`**. A privilege that was never granted cannot be used by a bug, an ORM convenience, or an ad-hoc statement.
   - **A trigger or rule that rejects `UPDATE` and `DELETE` outright.** Privileges can be misgranted by a future migration, or granted broadly by a convenience script; a rejecting trigger fails the statement regardless of who runs it, **including the relation's owner**.

   Redundancy is proportionate here precisely because audit is the control that verifies all the others.

3. **A rejecting trigger is the one permitted use of a trigger for anything resembling a rule.** `docs/domain/06_Laravel_Module_Blueprint.md` puts business invariants in the Domain layer, and `docs/architecture/03_Database_Design.md` §18 prohibits business logic in triggers, rules, stored procedures, and functions. **Append-only enforcement and referential integrity are the only exceptions**, because they are structural properties of the relation rather than domain rules, and because their whole value lies in holding when application code is wrong.

4. **Audit relations have no `updated_at` column and no version column.** A column that exists to record modification, on a relation that is never modified, is an invitation.

5. **The audit write path never depends on its subject's authorization outcome or continued existence.** An audit row is frequently written **at the moment its subject is being refused, suspended, or destroyed** — a denied access, a revoked membership, a rejected transition, a closed Firm. A denial audit row is written under the Firm context of the attempted access, which is available because Firm context is established before the authorization decision (`docs/adr/ADR-016-Tenant-Isolation-Model.md` Decision 8). Modelling audit inside the subject aggregate is prohibited, per ARCH-016 §521.

6. **Audit is not editable by the actor being audited — structurally, not by policy.** No application path exposes `UPDATE` or `DELETE` on an audit relation to any actor at any privilege level, including a Firm administrator and a platform operator. Role separation (ADR-016 Decision 10) means even an authorized administrative surface has no mechanism. **Correction is a new row referencing the corrected one** (Constitution Articles 8, 18, 23).

7. **Distinct audit streams, owned by their contexts, never merged into one platform table.** `PlatformAdministration`'s administrative audit; IdentityAccess's security events and Firm-visible support-access history; each domain's business activity history; Billing's posted ledger entries; Documents' version and access history. **Constitution Article 45 forbids `PlatformAdministration` from storing or duplicating another context's audit records**, which a shared table would do by construction. Merging would also make "this Firm's subscription lapsed" the same kind of record as "this person's access was revoked" — materially different facts about materially different subjects (ARCH-019 §9) — and would give every context read access to every other's audit content, which no context is entitled to.

8. **Audit rows are Firm-scoped and subject to the same tenancy model as any other Firm-scoped relation** — `firm_id uuid NOT NULL`, immutable, Row-Level Security enabled and forced, policy carrying `USING` and `WITH CHECK` (ADR-016). Reading audit is additionally subject to the owning context's own authorization; **a denied caller receives no audit content, metadata, count, or existence confirmation** (Constitution Article 28).

9. **Content is chosen, never captured.** Audit rows carry **safe metadata only** — actor, Firm, event, result, timestamp, correlation and causation, authorization provenance, and the identifiers of affected records. They **never** carry a credential, password hash, session token, MFA secret, recovery material, API secret, signing key, payment credential, privileged narrative, document bytes, knowledge body text, embedding, or **any cross-Firm information** (Constitution Articles 29, 30; ARCH-019 §19). **A blind dump of a request or response body is prohibited** — it is the standard way secrets and privileged content reach an append-only relation from which they can never be removed.

10. **Human, system, integration, and AI actors remain distinguishable in every audit row** (Constitution Articles 26, 35). An AI-assisted operation records the initiating human, the AI or system actor, the authorization relied on, and any required approval — and **an action is never attributed to AI**, because AI proposes and a human decides (Constitution Article 40).

11. **The audit row commits atomically with the state change it records** (`docs/architecture/03_Database_Design.md` §11.2). An audit trail written in a separate transaction has holes exactly where a failure occurred, which is exactly where it is needed. A **denial** audit row, where there is no state change, commits in its own transaction.

12. **Five distinct times are never collapsed** (`PF-043`): `occurred_at`, `recorded_at`, `committed_at`, `published_at`, `delivered_at`. Domain instants come from the injected `PF-047` `Clock`, in UTC, in `timestamptz`; a database-side default is permitted only for an infrastructure-internal recorded-at column that never replaces the domain instant. **A timestamp promises no ordering, monotonicity, authenticity, trusted time, or non-repudiation** — the clock is a wall clock that may be corrected backwards.

13. **Audit retention outlives business-content retention.** Deleting content never deletes the audit fact that it existed (Constitution Articles 19, 23). Destructive cascades that would remove an audit row are prohibited **regardless of approval**, because the destructive-operations approval gate cannot authorize a constitutional violation (`docs/architecture/03_Database_Design.md` §10).

14. **Audit purge is not designed here and requires its own separately approved decision.** Where a jurisdictional obligation to erase collides with an obligation to retain audit, with a legal hold, or with retention in backups, that conflict requires **Thai-qualified legal review and its own approved policy**. **This ADR asserts no legal conclusion and resolves no such conflict.**

15. **No partitioning or archival scheme is selected. If audit partitioning is adopted later, detaching a partition must not become a silent deletion path** — a detached partition remains audit history until an authorized, recorded decision says otherwise.

16. **The outbox is not the audit record, and the audit record is not the outbox.** Separate relations, separate rules, separate lifecycles: outbox rows may be pruned after publication under an authorized operational policy; audit rows are not pruned (`docs/adr/ADR-019-Transactional-Outbox-Persistence.md`).

17. **Restore must preserve append-only audit continuity.** A restore that silently drops posted entries or audit records is a **defect, not an acceptable recovery outcome** (`docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §925). Authoritative audit therefore never lives in an `UNLOGGED` relation.

18. **This ADR schedules nothing.** `PF-040` remains the next code story and remains Backlog. No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled, and no context's audit ownership changes.

## Alternatives considered

- **Application logs as the authoritative audit record.** Rejected — Constitution Article 30 forbids it, and a log cannot be queried under authorization, cannot back a Firm-visible support-access history, and can be rotated or lost by an operational action nobody reviews as a data change.
- **Append-only by code convention** — a repository that only inserts. Rejected. It is a comment. Any other repository method, any ORM mass-update, any ad-hoc statement under the application role, and any future maintainer defeats it, and the defeat is undetectable because the evidence is the thing altered.
- **Privileges alone, without a rejecting trigger.** Rejected as insufficiently robust for this specific control. A later migration can grant `UPDATE` broadly, a convenience script can grant it to "fix" something, and nothing then fails. Two independent mechanisms mean both must be wrong at once.
- **A rejecting trigger alone, without revoking privileges.** Rejected — a trigger can be dropped or disabled by a role holding sufficient privilege, and an ungranted privilege is the cheaper and stronger of the two. Both, or neither is trustworthy.
- **Enforcing immutability with a `CHECK` constraint or a computed hash chain.** Rejected as the primary mechanism. A `CHECK` constraint cannot compare a row to its previous version. A hash chain detects tampering **after** it has occurred and adds ordering and rebuild problems of its own; **preventing** the write is stronger than detecting it. A hash chain remains available as a future additive integrity measure and is neither designed nor prohibited here.
- **One consolidated platform-wide audit table.** Rejected on three independent grounds: it makes `PlatformAdministration` store other contexts' audit records, which Constitution Article 45 forbids; it collapses the approved semantic distinction between entitlement lapse and membership revocation (ARCH-019 §9); and it gives every context read access to every other's audit content.
- **Audit as entities inside each subject aggregate.** Rejected, restating ARCH-016 §521's own reasoning: audit must be writable when the subject is being denied, suspended, or destroyed, and must never be mutable by the aggregate it records.
- **Storing the full request and response body for completeness.** Rejected — it is how credentials, session tokens, privileged narratives, and cross-Firm content reach a relation from which they can never be removed. Content is chosen, not captured (Decision 9).
- **Allowing a Firm administrator to correct an erroneous audit entry.** Rejected — Constitution Article 30 makes audit not editable by the actor being audited, and an administrator is frequently exactly that actor. Correction is a new row (Decision 6).
- **Allowing a platform operator to edit or delete audit rows for support purposes.** Rejected — Constitution Article 29 states break-glass never permits destroying audit history, and ARCH-019 §15 establishes that no second privileged path exists. Support access is read-scoped, purpose-bound, and itself audited.
- **A single `updated_at`/`version` column on audit relations for ORM convenience.** Rejected (Decision 4) — a modification-recording column on a never-modified relation is an invitation.
- **Writing audit asynchronously, outside the state-change transaction, for throughput.** Rejected — it produces missing audit precisely at the failures where audit matters, and it makes "the action happened but was not recorded" a normal state.
- **Deriving audit from the outbox** (using the event stream as the audit record). Rejected — the outbox is delivery state that may legitimately be pruned, and using it as the record either makes pruning impossible or makes audit lossy. Two purposes, two relations (Decision 16).
- **Designing an audit-purge path now** to bound growth. Rejected as out of scope and legally unresolved: it requires Thai-qualified legal review of erasure-versus-retention obligations and its own approved policy (Decision 14). Guessing here would be asserting a legal conclusion.

## Consequences

- Audit becomes queryable under authorization, Firm-scoped, and capable of backing a Firm-visible history surface — which Release 0.1 requires for support-access history and actor-attributed activity history.
- **Two enforcement mechanisms mean two things to get right in every audit relation's migration**, and a schema-level guard test is needed to assert both are present (`docs/architecture/03_Database_Design.md` §16.3).
- **Distinct streams cost duplication.** Several contexts will have structurally similar audit relations, and the temptation to consolidate them will recur at every review. The separation is the point.
- **Audit relations grow without bound until an approved purge policy exists** (Decision 14). That is a real operational consequence, stated plainly rather than solved by an unapproved deletion path.
- **Synchronous audit writes add a write to every consequential command.** Accepted: a missing audit row at a failure is worse than a slower command.
- **A blind-capture prohibition means each story must decide what its audit rows contain.** That is more design work per story and is the correct place for the decision.
- **No hash chain, signature, or external attestation exists.** Tamper-**prevention** is via privileges and triggers; tamper-**evidence** beyond that is not claimed. Anyone with sufficient database privilege outside the application could still act, which is why role separation, privileged-access grants, and operational controls remain load-bearing — and why no certification claim is made.
- **Restore correctness becomes an audit obligation** (Decision 17), constraining the future deployment architecture without selecting anything in it.

## Security and professional-responsibility consequences

- **Audit is the record that makes every other control verifiable after the fact.** If it is editable, nothing else in this architecture can be demonstrated to have held. That asymmetry justifies the redundant enforcement in Decision 2.
- **Audit must survive the events it records.** A denied access, a revoked membership, a suspended entitlement, and a closed Firm all produce audit rows written while their subject is being refused or ended (Decision 5).
- **Audit is not editable by the actor being audited**, structurally rather than by policy — including by a Firm administrator and a platform operator (Decision 6).
- **An append-only relation is a one-way door for whatever is written into it.** A credential, session token, or privileged narrative captured by accident cannot be removed. Decision 9's choose-not-capture rule is a confidentiality control, not a style preference.
- **Distinct streams keep the security record truthful** about what kind of event occurred and to whom. Collapsing a commercial lapse and a security revocation into one record type would misrepresent both (ARCH-019 §9).
- **Actor attribution is a professional-responsibility control.** A legally significant act must name an accountable human; **AI is never the acting or approving principal**, and an AI-assisted action is attributed to the human decision actor and the owning domain's execution (Constitution Article 40).
- **Deleting content never deletes the audit fact.** A cascade that would remove an audit row is prohibited regardless of approval (Decision 13).
- **A denied caller receives no audit content, metadata, count, or existence confirmation** (Constitution Article 28).
- **Audit purge versus erasure obligations, legal hold, and backup retention is unresolved and requires Thai-qualified legal review.** **No legal conclusion is asserted here** (Decision 14).
- **Tamper-evidence is not claimed** beyond prevention by privilege and trigger (see Consequences). No attestation, certification, or non-repudiation property is asserted.
- **AI holds no authority here.** AI never writes, alters, approves, deletes, or retains an audit record, and is never an authorization authority. Release 0.1 contains no AI capability.
- **No certification, compliance, or production-readiness claim is made**, and no control described here is claimed to be implemented.

## Integration consequences

- **IdentityAccess** keeps sole ownership of security events and of the Firm-visible, append-only support-access history (Constitution Articles 29–30, ADR-014). This ADR supplies their persistence discipline and changes no ownership.
- **`PlatformAdministration`** keeps its own administrative audit facts and only those (Constitution Article 45, ARCH-019 §19). Decision 7 is the persistence rule that makes "never duplicates another context's audit records" enforceable rather than aspirational.
- **Practice Management** keeps its business and activity history, including manual conflict attestation as an actor-attributed, timestamped, **append-only** human determination — which Decision 2 now enforces at the database level, and which `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` forbids describing as a system-performed conflict check.
- **Billing** keeps posted ledger entries as its append-only financial record, with balances derived and never mutated (Constitution Article 23). This ADR adds no monetary column; `PF-045` remains Backlog and deferred.
- **Documents and Knowledge** keep immutable versions and access history (Constitution Articles 18–20); deleting content never destroys the audit fact.
- **Legal Intelligence** keeps immutable published source versions (Constitution Article 8).
- **`Integrations`** keeps its own append-only delivery and installation audit (Constitution Article 34). An internal audit row is never an external contract.
- **`Workflow` and the AI Copilot** keep run and approval provenance; **cancellation never erases a committed business fact** (Constitution Article 43), which Decision 2 makes structurally true.
- **`PF-091` / `PF-092`** — the outbox stays separate from audit (Decision 16), and audit is never derived from delivery state.
- **Platform Foundation** — `PF-073` Transaction Manager becomes where the atomicity in Decision 11 is realized. Not scheduled here.

## Explicit non-goals

This ADR does **not**: implement anything; create or modify any migration, schema, table, column, index, trigger, policy, role, grant, source file, test, configuration, CI workflow, dependency, or GitHub setting; define any context's audit event list, table names, or column names; move, merge, split, or reassign any context's audit ownership; define an audit-purge, audit-retention-period, partition, archival, or partition-detachment path — **each requiring its own separately approved decision**; resolve any conflict between erasure obligations, retention obligations, legal hold, and backup retention; define a hash chain, digital signature, external attestation, notarization, or non-repudiation mechanism, none of which is claimed; define log aggregation, SIEM, monitoring, alerting, or observability tooling, or select any product for them; define a Firm-visible audit user interface or export format; select or endorse a hosting provider, managed database, backup product, key-management product, or any other vendor, product, or package; define deployment, infrastructure, capacity, or disaster-recovery design; define, execute, schedule, or claim a backup or restore test; create a Reporting context, cross-Firm audit reporting, analytics, benchmarking, or a cross-Firm read role — Constitution Article 44 reserves that context, which this ADR **neither approves nor permanently prohibits**; introduce `Money`, `Currency`, or any monetary column, or schedule or unblock `PF-045`; introduce an AI capability or modify `docs/architecture/05_AI_Architecture.md`; define an Ethical Wall, conflict-checking, or per-user visibility mechanism in any layer, or describe manual conflict attestation as a system-performed conflict check; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim tamper-evidence, non-repudiation, or production readiness; claim that any described control is implemented, tested, or effective; weaken or create an exception to Constitution Articles 1–48; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

**Proposed conceptual architecture only.** It authorizes no application code, schema, migration, trigger, policy, role, grant, dependency, package, infrastructure, Docker change, CI change, environment change, production configuration, or GitHub setting. **No control described here is claimed to be implemented.**

`PF-040` — AggregateRoot remains the next repository implementation story and remains **Backlog**. No story is In Progress. Audit-relation enforcement cannot be honestly tested before the PostgreSQL CI story lands, which remains **Backlog** and must precede `PF-080`.

The story ID is **ARCH-012**; the architecture document it accompanies is the pre-reserved `docs/architecture/03_Database_Design.md` rather than a newly numbered file.
