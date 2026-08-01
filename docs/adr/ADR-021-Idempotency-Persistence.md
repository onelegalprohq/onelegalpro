# ADR-021 — Idempotency Persistence

## Status

**Proposed.** Not approved, and it authorizes nothing. Acceptance requires explicit owner approval recorded on the pull request; acceptance would authorize the architectural decision only, never implementation, deployment, or production access.

Authored by story **ARCH-012 — Data & Persistence Architecture**, alongside `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…` through `ADR-020-…`.

## Context

`docs/architecture/07_API_Standards.md` §10 already decides what idempotency **means** and how it is **scoped**: every retryable command accepts an idempotency key; the record is scoped by at least the Firm, the integration installation, the API contract and version, the operation, the target resource where applicable, and the key itself; the same scoped key with a different request fingerprint is an idempotency-conflict error and **never** returns the first request's result; a replayed request still authenticates and passes current Firm, installation, scope, and domain authorization; and a previously recorded response **must not** disclose protected content after a revocation. Constitution Articles 34 and 43 fix the surrounding reality: **at-least-once execution and delivery, no exactly-once claim**, with idempotency being what makes a retry safe within that reality rather than a guarantee that retries do not happen.

**What has never been decided is how an idempotency record is persisted** — and the gap has been filled inconsistently. Earlier ARCH-012 drafts attributed the idempotency relation to `docs/adr/ADR-019-Transactional-Outbox-Persistence.md`, which decides the **outbox** and should decide nothing else, and treated its retention as an implementing story's incidental choice. Both are misattributions: the outbox and the idempotency record solve different problems with different lifecycles, and a retention window that bounds duplicate suppression is a policy decision, not an implementation detail.

Four questions are therefore open, and each has a plausible wrong answer:

1. **Relation class.** Is an idempotency record Firm-scoped, or is it infrastructure that sits outside tenancy? The record contains a Firm in its scope, so treating it as untenanted is tempting and wrong.
2. **What is immutable.** If the scope, key, or fingerprint of an existing record can be updated, the record can be made to match a *different* request retroactively — returning one caller's outcome to another.
3. **When it is written.** Before the side effect, and it can record work that never happened; after, and it can miss work that did.
4. **Retention.** Expire too early and the duplicate suppression silently lapses inside the window a client may still be retrying; keep forever and the relation grows without bound. Neither failure announces itself.

Recovery makes the fourth question sharper rather than academic: **a restore to a point before an idempotency record existed can permit a duplicate side effect**, exactly as it can replay an already-delivered outbox row (`docs/architecture/03_Database_Design.md` §17.4).

Per `docs/adr/ADR-001-Architecture-First.md` and the `AGENTS.md` database approval gate, this needs a decision of its own rather than a paragraph inside a decision about something else.

## Decision

1. **An idempotency record is a Firm-scoped relation — class (a)** in the four-class taxonomy `docs/adr/ADR-016-Tenant-Isolation-Model.md` Decision 4 defines, and never class (b), (c), or (d): `firm_id uuid NOT NULL`, **immutable after insert**, with Row-Level Security **enabled and forced** and a policy carrying both `USING` and `WITH CHECK`. It is not untenanted infrastructure. A record is created by a request acting for exactly one Firm, its replay must be visible only within that Firm, and a cross-Firm read of it would disclose that another Firm performed a particular operation against a particular target — an existence disclosure of exactly the kind Constitution Article 28 forbids.

2. **Ownership is stated precisely, because it has been misattributed.**
   - **The persistence decision is this ADR's.** Not `docs/adr/ADR-019-Transactional-Outbox-Persistence.md`'s, which decides the outbox and nothing else; and not `docs/adr/ADR-020-Migration-Rollback-and-Schema-Evolution.md`'s, which decides schema evolution and applies to this relation exactly as it applies to any other.
   - **The scoping contract remains `docs/architecture/07_API_Standards.md` §10's.** This ADR persists that contract; it does not restate, extend, or reinterpret it, and where the two appear to differ, §10 governs the contract and this ADR governs its storage.
   - **The relation is Platform Foundation-owned infrastructure**, on the same footing as the outbox (`docs/architecture/03_Database_Design.md` §5.1). **Foundation owning the relation is not Foundation owning the decision about it** — the same distinction ADR-019 Decision 2 draws for the outbox.
   - **A module writing an idempotency record through Foundation's published contract, inside its own transaction, is not a cross-module table write** (ADR-019 Decision 2's reasoning, applied here).

3. **The scoped key carries a unique constraint, and the constraint is the mechanism.** Duplicate suppression is enforced by the database rejecting the second insert, **never** by reading for an existing record and then writing — a read-then-write check fails precisely under the concurrency it exists to handle, and two simultaneous retries would both find nothing and both proceed.

4. **The request scope, key, and fingerprint are immutable once written.** They are the identity of the protected request. A record whose scope or fingerprint could be updated could be made to match a different request retroactively, returning one caller's recorded outcome to another — a correctness failure and, where the outcome carries any content, a disclosure. Only delivery-neutral bookkeeping may change after insert; **the identity may not.**

5. **Replay semantics are deterministic, and conflict is an error rather than a convenience.**
   - **Same scoped key, same fingerprint** → the recorded outcome, subject to Decision 6.
   - **Same scoped key, different fingerprint** → an **idempotency-conflict failure**. It **never** returns the first request's result as though the second, different request had been accepted (`docs/architecture/07_API_Standards.md` §10). Returning the first result would silently tell a caller that a request it never made had succeeded.
   - **No scoped key** where one is required → the request is refused rather than executed unprotected.

6. **A replay re-evaluates current authorization before anything is returned.** Idempotency short-circuits the **side effect**, never the authorization composition (Constitution Article 28; `docs/architecture/07_API_Standards.md` §10). **A stored response is therefore not a licence to disclose:** stored outcome content is **minimal**, and where a response would contain protected content, enough is stored to reconstruct it **under current authorization** rather than storing it verbatim to be replayed later. A caller whose membership, capability, installation, or resource access has since been revoked receives nothing from the record.

7. **The record is written in the same transaction as the side effect it protects** (`docs/architecture/03_Database_Design.md` §11.2). Written earlier, it can record work that never happened; written later, it can miss work that did. Where the protected effect is a domain state change, the record commits atomically with that change, its outbox row, and its audit row.

8. **Retention is bounded, and the bound is owned by a later approved policy — not by this ADR.**
   - **A bounded window is required.** An idempotency relation that grows forever is not an acceptable end state.
   - **The window must be at least as long as the period in which a client may still retry.** Expiring earlier silently re-enables the duplicate side effect the record existed to prevent, and it does so quietly, which is worse than refusing.
   - **This ADR asserts no duration**, because the correct value depends on client retry behaviour, integration contracts that do not yet exist, and storage characteristics of a deployment architecture that has not been approved. **A number stated here would be a guess wearing the authority of an approved decision.**
   - Expiry removes only records outside the window; it is never a mechanism for clearing a conflict, resolving a duplicate, or making a rejected replay succeed.

9. **No payload and no secret is stored.** No credential, password, token, session identifier, MFA secret, recovery material, API secret, signing key, or payment credential. No request body, no response body, and no privileged narrative, document content, knowledge text, or embedding. The record holds the scope, the key, a **fingerprint** of the material inputs, the outcome reference, and delivery-neutral bookkeeping — and a fingerprint is a comparison value, never a store of the inputs it summarizes.

10. **An idempotency record is not the outbox and not the audit record.** Three concerns, three relations, three lifecycles: the outbox is delivery state that may be pruned once published (ADR-019 Decision 17); audit is a record that is not pruned (`docs/adr/ADR-018-Audit-Persistence-and-Append-Only-Enforcement.md`); an idempotency record is duplicate-suppression state with a bounded window. **Merging any two of them makes at least one of them wrong.**

11. **Recovery consequences are acknowledged, not claimed away.** A restore to a point before a record existed **can permit a duplicate side effect**, exactly as a restore can replay an already-delivered outbox row. This is not prevented, and no mechanism here claims to prevent it. Under `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 5, a client-facing duplicate requires recorded assessment, remediation, and a communication or notification decision; technical idempotency is mandatory but is not declared legally or professionally sufficient by itself.

12. **Scope in Release 0.1 is stated honestly.** There is no public API, no webhook, no connector, and no service principal, so **external idempotency has no consumer yet**. **Internal command-retry idempotency still applies** wherever a command may be retried after an ambiguous failure — which is precisely what makes retrying a serialization failure or deadlock safe (`docs/architecture/03_Database_Design.md` §11.4). This ADR decides persistence for both cases without asserting that either is implemented.

13. **This ADR schedules nothing and asserts no story's status.** No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled, and no identifier is assigned to any unnamed requirement. **`docs/PROJECT_STATUS.md` is the authoritative record of what is current, next, and complete.**

## Alternatives considered

- **Treating the idempotency relation as untenanted infrastructure** — placing it outside Firm scoping, or in the platform-realm class (d) reserved for records that must exist when no verified `FirmContext` does — on the reasoning that it is a platform mechanism rather than business data. Rejected — a cross-Firm read would disclose that another Firm performed a particular operation against a particular target, which is an existence disclosure (Constitution Article 28). The Firm appears in the record's own scope; making the relation untenanted would mean the tenancy is *described* by a column that nothing enforces.
- **Deciding idempotency persistence inside ADR-019.** Rejected — it is what earlier drafts did, and it is a misattribution. The outbox and the idempotency record share an owner for their relations and nothing else: different lifecycles, different retention, different failure modes, and different reasons to exist. Bundling them would also make any future revision of one appear to reopen the other.
- **Read-then-write duplicate checking**, without a unique constraint. Rejected — it fails under exactly the concurrency it exists to handle: two simultaneous retries both find nothing and both proceed. The constraint is the mechanism; the read is at best an optimization.
- **Allowing the fingerprint to be updated** so a client can "correct" a request under the same key. Rejected — it would let a record be made to match a different request retroactively, returning one caller's outcome to another (Decision 4). A changed request is a different request and needs a different key.
- **Returning the first result when a key is reused with a different fingerprint**, as a convenience. Rejected outright by `docs/architecture/07_API_Standards.md` §10, and rightly: it silently tells a caller that a request it never made succeeded.
- **Replaying a stored response without re-evaluating authorization**, since the request was authorized when first made. Rejected — it would serve protected content to a caller whose membership, capability, installation, or resource access has since been revoked, which is a stale-positive-authorization disclosure (Constitution Article 28).
- **Storing the full response body** so replay is cheap and exact. Rejected — it turns the relation into a durable copy of protected content that must then be re-authorized on every replay and is a standing disclosure risk if that check is ever missed. Decision 6 stores the minimum and reconstructs under current authorization.
- **Writing the record before the side effect** (to reserve the key). Rejected — it records work that may never happen, so a crash between the two makes a legitimate retry look like a duplicate and silently drops it.
- **Writing the record after the side effect**, in a separate transaction. Rejected — it misses work that did happen, which is the duplicate the record exists to prevent.
- **Fixing a retention window in this ADR** — 24 hours, 7 days, 30 days. Rejected as a guess with unearned authority: the correct value depends on client retry behaviour, integration contracts that do not exist yet, and a deployment architecture that is not approved. Decision 8 requires a bounded window and leaves its length to an approved retention policy.
- **Keeping records indefinitely** to avoid the question. Rejected — unbounded growth in a high-write relation is not an acceptable end state, and "we never decided" is not a retention policy.
- **Claiming idempotency delivers exactly-once execution.** Rejected — Constitution Articles 34 and 43 forbid the claim, and Decision 11's restore case is a concrete counter-example. Idempotency makes a retry safe; it does not make retries or duplicates impossible.

## Consequences

- Duplicate suppression becomes a database-enforced property rather than an application convention, and it holds under the concurrency where a hand-rolled check would fail.
- **The relation is high-write and bounded-lifetime**, giving it vacuum, bloat, and index-maintenance characteristics an operational plan must account for. Not designed here.
- **A bounded window with no assigned length is a real open item**, not a resolved one. Until an approved retention policy exists, the relation's growth is unbounded in practice, and that is stated rather than papered over (Decision 8).
- **Storing the minimum outcome makes replay more expensive** than returning a cached body, because the response is reconstructed under current authorization. That cost is accepted: the cheap version is the one that discloses to a revoked caller.
- **Three separate relations — outbox, audit, idempotency — where a single "event log" would look simpler.** The separation is the point (Decision 10), and it will look like duplication at every review.
- **Release 0.1 exercises only the internal-retry case** (Decision 12), so the external contract's behaviour will first be exercised by a later epic. The persistence model is decided now so that epic inherits it rather than inventing one.
- **A restore can re-enable a duplicate side effect** (Decision 11). Acknowledged, not prevented.

## Security and professional-responsibility consequences

- **A replay is a fresh authorization decision.** Idempotency short-circuits the side effect, never the authorization composition; a caller whose access has since been revoked receives nothing from a record created when they still had it (Decision 6, Constitution Article 28).
- **The record is Firm-scoped because it would otherwise disclose across Firms** — that Firm B performed a particular operation against a particular target is exactly the kind of existence signal denied-existence confidentiality forbids (Decision 1).
- **A mutable fingerprint would be a disclosure channel**, not merely a correctness bug: it would allow one caller's recorded outcome to be returned against another caller's request (Decision 4).
- **Returning the first result on a fingerprint conflict would assert a false fact to the caller** — that a request they never made succeeded. In a legal-practice product, a false confirmation that an action was taken is a professional hazard, not a UX detail (Decision 5).
- **The record holds no payload and no secret** (Decision 9). Like any long-lived, replicated, backed-up relation, whatever is written into it propagates widely; the correct defence is not writing it.
- **Duplicate side effects remain possible** — from a restore (Decision 11), and, for delivery, from crash-after-publish (ADR-019 Decision 8b). `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 5 requires a client-facing duplicate to receive recorded assessment, remediation, and a communication or notification decision. Technical idempotency is mandatory but is not declared legally or professionally sufficient by itself.
- **Retention is a security-relevant parameter, not only an operational one:** too short and duplicate suppression lapses silently inside a live retry window. That is why Decision 8 requires the bound to exist and refuses to guess its value.
- **AI holds no authority here.** AI never issues, reuses, resolves, expires, or overrides an idempotency key or record, and is never an authorization authority. Release 0.1 contains no AI capability.
- **No certification, compliance, production-readiness, or blanket legal-sufficiency claim is made.** The limited Thai-qualified review recorded in `docs/legal/ARCH-012-Thai-Legal-Review.md` occurred on 1 August 2026; no mechanism described here is thereby claimed to be implemented, tested, effective, or sufficient outside that record's express scope.

## Integration consequences

- **`docs/architecture/07_API_Standards.md`** retains sole ownership of the idempotency **contract** — key acceptance, scope composition, conflict semantics, and re-authorization on replay. This ADR persists that contract and changes none of it.
- **`Integrations`** retains ownership of the external surface: inbound webhook replay resistance, delivery identifiers, and the external error catalogue. **An idempotency record is internal and is never an external contract** (Constitution Article 31).
- **Platform Foundation** owns the relation as infrastructure and publishes the contract modules write through. It does not own this decision (Decision 2).
- **`docs/adr/ADR-019-Transactional-Outbox-Persistence.md`** decides the outbox only. Its Decision 2 scope note and this ADR's Decision 2 agree: shared relation ownership, separate decisions.
- **`docs/adr/ADR-016-Tenant-Isolation-Model.md`** supplies the tenancy model this relation is subject to, with no exemption.
- **`docs/adr/ADR-018-Audit-Persistence-and-Append-Only-Enforcement.md`** — an idempotency record is **not** an audit record and is never used as one; an audited action still writes its own audit row (Decision 10).
- **`docs/adr/ADR-020-Migration-Rollback-and-Schema-Evolution.md`** applies to this relation exactly as to any other, including the relation-class declaration and the schema-level guard test.
- **`PF-073` — Transaction Manager** is where Decision 7's atomicity is realized. **Every domain module** consuming the contract inherits Decisions 3 through 6 unchanged. Neither is scheduled here, and no status is asserted.

## Explicit non-goals

This ADR does **not**: implement anything; create or modify any migration, schema, table, column, index, constraint, policy, role, grant, source file, test, configuration, CI workflow, dependency, or GitHub setting; define the relation's actual column names, types, or migration; restate, extend, or reinterpret the idempotency contract in `docs/architecture/07_API_Standards.md` §10; **set, imply, or default a retention window**, which a later approved policy owns; define a fingerprint algorithm or its collision properties; define key generation, key format, or client retry behaviour; define an external error catalogue, external contract, webhook replay mechanism, or delivery identifier, all of which remain `Integrations`'; decide the outbox model, which is `docs/adr/ADR-019-Transactional-Outbox-Persistence.md`'s; make an idempotency record an audit record or an audit record an idempotency record; claim exactly-once execution or delivery, or claim that duplicate side effects are prevented; prevent recovery-driven duplicates or claim satisfaction of `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 5; select or endorse a hosting provider, managed database, cache, queue, or any other vendor, product, or package; define deployment, infrastructure, capacity, or scaling design; define, execute, schedule, or claim a backup or restore test; introduce `Money`, `Currency`, or any monetary column, or schedule or unblock `PF-045`; introduce an AI capability or modify `docs/architecture/05_AI_Architecture.md`; define an Ethical Wall, conflict-checking, or per-user visibility mechanism in any layer; assign a story identifier to any unnamed requirement, or assert any story's status; assert a general legal, tax, regulatory, or compliance conclusion beyond the recorded ARCH-012 legal-review decisions; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; claim that any described mechanism is implemented, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

**Proposed conceptual architecture only.** It authorizes no application code, schema, migration, policy, role, grant, dependency, package, infrastructure, Docker change, CI change, environment change, production configuration, or GitHub setting. **No mechanism described here is claimed to be implemented.** No idempotency relation, record, or contract exists in production code, and `app/Modules` has not been created.

**No story's status is asserted here; `docs/PROJECT_STATUS.md` is the authoritative record** of what is current, next, and complete. Idempotency behaviour cannot be honestly tested before the PostgreSQL CI requirement is satisfied — that is its own approved story, **with no assigned identifier**, and it must precede `PF-080`. **This ADR neither creates, names, nor renumbers it.**

The story ID is **ARCH-012**; the architecture document it accompanies is the pre-reserved `docs/architecture/03_Database_Design.md` rather than a newly numbered file.
