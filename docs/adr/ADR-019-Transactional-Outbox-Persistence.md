# ADR-019 — Transactional Outbox Persistence

## Status

**Proposed.** Not approved, and it authorizes nothing. Acceptance requires explicit owner approval recorded on the pull request; acceptance would authorize the architectural decision only, never implementation, deployment, or production access.

Authored by story **ARCH-012 — Data & Persistence Architecture**, alongside `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…`, `ADR-017-…`, `ADR-018-…`, and `ADR-020-…`.

## Context

`docs/domain/06_Laravel_Module_Blueprint.md` states the requirement in one sentence: **"State changes and outbox records commit atomically."** `docs/architecture/17_API_Integration_Platform_Architecture.md` §295 requires publication through "a transactional outbox or equivalent reliable-publication boundary, so an event is durably recorded before any external delivery attempt." Constitution Articles 34 and 43 fix the delivery semantics: **at-least-once, no exactly-once claim, no global-ordering claim**, per-subject ordering only where explicitly stated, and **no external call inside a database transaction**. `PF-091` — Transactional Outbox is an approved Sprint 0.4 story, Backlog, and `docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md` Decision 3 includes it in the Release 0.1 minimum runtime "where a committed audit or event fact must be durable with the state change that produced it."

`PF-043` — DomainEvent (**Done**) supplies the pieces and deliberately stops short of persistence. Its backlog entry is unusually explicit about what it does **not** decide: it "ships no persistence, serialization, or reconstitution path," it defines no outbox, no deduplication store, no delivery mechanism, and it records that **cross-boundary preservation of event identity is "an obligation on PF-091, PF-092, and `Integrations`, not a property any PF-043 test can prove."** It also warns, twice, that `eventId()` is "a source of keys, not a key" and that its own identity and timestamp promise **no ordering of any kind**.

Four questions are therefore open, and three of them have a plausible-looking wrong answer:

1. **One outbox relation or one per module?** Module-owned migrations are the rule; a shared relation looks like a violation of it.
2. **How is ordering obtained**, given that `PF-043` promises none and `PF-048`'s UUIDv7 is time-sortable but not ordered?
3. **How does a publisher find pending work** without either breaking Firm isolation or silently skipping rows?
4. **What may the payload contain**, given that every per-module rule in `AGENTS.md` forbids events from carrying document bytes, knowledge body text, privileged content, embeddings, or secrets?

The third question hides a real, silent data-loss mode. A PostgreSQL sequence is monotonic in **assignment**, not in **commit visibility**: a row assigned sequence 100 can become visible **after** a row assigned 101, because its transaction committed later. A publisher tracking a high-water mark and selecting "rows with sequence greater than my cursor" therefore **skips rows permanently**. This is well known, it is the natural first implementation, and nothing in the approved architecture currently warns against it.

## Decision

1. **One Platform Foundation-owned outbox relation**, delivered by `PF-091`. Not per-module. A publisher must read one place; N module relations would mean N publishers, or a union view that becomes a de-facto shared relation with none of a real relation's guarantees, ordering properties, or index behaviour.

2. **A module writing an outbox row is not a cross-module table write.** `app/Foundation` is a layer of **shared technical primitives, not a bounded context** (`AGENTS.md`, `docs/domain/06_Laravel_Module_Blueprint.md`). The rule that no module writes another module's tables is a rule about **bounded contexts**. A module writing an outbox row through Foundation's published contract, inside its own transaction, is using a platform primitive exactly as it uses `Clock`, `UuidV7`, and the transaction manager. **Foundation owns platform infrastructure relations; modules own domain relations; nobody writes another context's tables.**

3. **The state change and its outbox row commit atomically, in one transaction, with the audit row** (`docs/domain/06_Laravel_Module_Blueprint.md`; `docs/adr/ADR-018-Audit-Persistence-and-Append-Only-Enforcement.md` Decision 11). **If the outbox row cannot be written, the command fails.** This is the entire purpose of the pattern: it removes both the window where state changed and the event did not, and the window where the event was published and the state rolled back.

4. **The outbox is Firm-scoped like everything else** — `firm_id uuid NOT NULL`, immutable, Row-Level Security enabled and forced, policy carrying both `USING` and `WITH CHECK` (`docs/adr/ADR-016-Tenant-Isolation-Model.md`).

5. **Event identity is `PF-043`'s `eventId()` value, stored as a native `uuid`** (`docs/adr/ADR-017-Identifier-Persistence-Strategy.md`). Across persistence, reconstitution, publication, translation, and retries, **what is preserved is the same canonical UUID *value*, never the same object instance** — this is precisely the cross-boundary obligation `PF-043` assigned to `PF-091`, `PF-092`, and `Integrations`. **A retry redelivers the same event identity under a new delivery attempt and never mints a new event identity.** Delivery attempts carry their own separate identifiers.

6. **Ordering never comes from a UUIDv7 or from a wall clock.** Same-millisecond generation order is arbitrary, the clock may be corrected backwards (`PF-047`), generation is concurrent across processes, and commit order is not generation order. **`ORDER BY` an event identifier, or by `occurred_at`, is prohibited** for delivery ordering.

7. **Ordering, where offered at all, is per-subject only and derived from an explicit monotonic sequence** assigned at insert — ordering metadata, never an identifier, never a primary key, never externally exposed (ADR-017 Decision 10). **No global ordering is claimed, ever** (Constitution Article 34; `docs/architecture/07_API_Standards.md` §12).

8. **The sequence-visibility hazard is named, and a bare high-water-mark cursor is prohibited.** Because a sequence is monotonic in assignment but not in commit visibility (Context), a cursor-based publisher **silently and permanently skips rows**. Permitted approaches:
   - **Claim by status, not by cursor** — select pending rows with `FOR UPDATE SKIP LOCKED`, publish, then mark them. Correct under concurrency and immune to the visibility gap, at the cost of an update per row.
   - **Per-subject gating** — publish a subject's next row only once its predecessor is published, which is what makes per-subject ordering meaningful at all.
   - A high-water-mark cursor **only** in combination with an explicit mechanism that closes the visibility gap, and never on its own.

   **`PF-091` and `PF-092` must choose and record which, with reasoning. This ADR does not pretend the choice is made.**

9. **Publication respects Firm isolation, with one bounded exception explicitly named rather than assumed.**
   - **Preferred: per-Firm claiming.** The publisher establishes Firm context and claims that Firm's pending rows, so the outbox is read under the same Row-Level Security as everything else.
   - **Where a cross-Firm scan of pending work is genuinely required**, it is a bounded, named exception: a dedicated **outbox publication role** (ADR-016 Decision 10) with access to **the outbox relation only and no business relation**, joining nothing, producing no cross-Firm report or aggregate, and **re-establishing each row's own Firm context before any domain-side work occurs**.
   - **This does not contradict Constitution Article 45.** That Article and `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` Decision 11 bind **`PlatformAdministration`**, and the broader prohibition is on cross-Firm **reads, reports, aggregates, and comparisons of Firm data**. The publisher is Platform Foundation infrastructure dispatching already-committed events; it creates no cross-Firm business read under either option.
   - **`PF-091` and `PF-092` must choose and record which.**

10. **The publisher never mutates domain state.** It reads outbox rows and writes its own delivery bookkeeping. It never writes a module's tables, never recreates a module's business rules, and never makes an authorization decision.

11. **The payload carries safe content only.** Identifiers and safe metadata. **Never** document bytes, knowledge body text, privileged narratives, embeddings, credentials, reusable secrets, session tokens, payment secrets or credentials, or **any cross-Firm information** — the standing rule in every per-module section of `AGENTS.md` and in Constitution Articles 30 and 34. Payload is `jsonb` and is **not** an authorization input; nothing in a policy, constraint, or authorization decision reads it. **An error message stored on a failed row carries safe metadata only.**

12. **An internal event is not an external contract.** The outbox row is internal. `IntegrationEventEnvelope` authoring, external versioning, deprecation, and internal-to-external identifier mapping remain `Integrations`' (Constitution Article 31; `docs/architecture/17_API_Integration_Platform_Architecture.md`). The row nevertheless carries an explicit internal event type **and version**, so a consumer is never guessing at shape.

13. **The five times stay separate** (`PF-043`): `occurred_at` (the domain instant, from the injected `PF-047` `Clock`, UTC, `timestamptz`) and a distinct infrastructure `recorded_at`, plus `published_at` and, where applicable, `delivered_at`. **They are never collapsed**, and `occurred_at` is never a sort key, deduplication key, or idempotency key.

14. **Provenance travels with the row** — correlation and causation identifiers, initiating principal, effective actor, and where applicable the AI or system actor and the authorization relied on (Constitution Articles 35, 41). A queued or long-running consumer **revalidates current authorization** and never treats provenance as an indefinite grant (Constitution Article 41).

15. **At-least-once delivery. No exactly-once claim, ever** (Constitution Articles 34, 43). Consumers must be idempotent; `PF-093` — Consumer foundation is **not** required by Release 0.1, which has no consumer. Retry, backoff, and attempt bookkeeping live on the row.

16. **A permanently undeliverable row is retained and surfaced for a human, never silently deleted.** Discarding a committed business fact because delivery failed is not an operational decision. Dead-letter handling is `PF-091`'s design.

17. **The outbox is not the audit record, and the audit record is not the outbox** (ADR-018 Decision 16). Separate relations, separate rules, separate lifecycles. Because of that separation, **pruning already-published rows is permitted under an authorized, recorded operational policy** — which would be impossible if the outbox were also the audit trail.

18. **External calls never occur inside the transaction** (Constitution Articles 34, 43). The publisher runs outside it. This is the reason the outbox exists rather than a constraint on it.

19. **Recovery consequences are acknowledged, not claimed away.** A restore that rewinds the database **can replay already-delivered events**, because rows marked published may return to a pending state. At-least-once delivery and consumer idempotency are what make that survivable, and **recovery is a concrete reason those are not a formality** (`docs/architecture/03_Database_Design.md` §17.4).

20. **This ADR schedules nothing.** `PF-040` remains the next code story and remains Backlog; `PF-091`, `PF-092`, and `PF-093` remain Backlog and are neither scheduled nor designed here. No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled. `PF-043`'s honest, deliberately incomplete prerequisite analysis for `PF-091` — recorded in `docs/implementation/03_Engineering_Backlog.md` — stands unamended: which stories `PF-091` genuinely requires remains that story's own approved analysis to perform.

## Alternatives considered

- **Publish directly to a broker or queue inside the transaction.** Rejected — Constitution Articles 34 and 43 forbid an external call inside a database transaction, and it produces the two failure windows the outbox exists to remove: published-then-rolled-back, and committed-then-lost.
- **Publish after commit, from application code, with no outbox row.** Rejected — a crash between commit and publish loses the event silently, with no record that anything was owed. This is the failure the Blueprint's atomicity sentence exists to prevent.
- **One outbox relation per module.** Rejected — N publishers, or a union view with none of a real relation's ordering, index, or locking properties. It also multiplies the sequence-visibility hazard in Decision 8 by module count. Rejecting it required Decision 2's explicit resolution of the apparent module-ownership conflict, which is why that resolution is recorded rather than assumed.
- **A per-Firm outbox relation.** Rejected — it is schema-per-Firm arriving through the back door, with the same migration fan-out and drift problems `docs/adr/ADR-016-Tenant-Isolation-Model.md` rejected, for a Firm-isolation benefit `firm_id` plus forced Row-Level Security already provides.
- **Ordering by event identifier, since UUIDv7 is time-sortable.** Rejected — the exact misreading `PF-048` and `PF-043` both warn about. It fails silently under same-millisecond generation, backward clock correction, concurrency, and commit reordering.
- **Ordering by `occurred_at`.** Rejected — `PF-043` records that a timestamp promises no ordering or monotonicity, that two events may share an instant, and that a later event may carry an earlier one.
- **A high-water-mark cursor over the sequence.** Rejected as a standalone design — it permanently skips rows because sequence assignment order is not commit-visibility order (Context, Decision 8). It is the natural first implementation and the reason Decision 8 is stated at this level rather than left to `PF-091`.
- **`LISTEN`/`NOTIFY` as the delivery trigger, without a persisted row.** Rejected — notifications are not durable, are lost if no listener is connected, and are not delivered on rollback-then-retry paths. `NOTIFY` remains available as an **optional latency optimization on top of** a persisted row, and is neither required nor prohibited by this ADR.
- **Logical replication or change-data-capture instead of an outbox.** Rejected for this decision. It would publish **row shapes** rather than domain events, coupling every external consumer to internal schema in direct conflict with Constitution Article 31, and it would require a replication mechanism this document is explicitly not selecting. It is not declared globally impossible; it would need its own approved architecture.
- **Claiming exactly-once delivery**, or global ordering, because a single database and a single publisher make it look attainable. Rejected — Constitution Articles 34 and 43 forbid the claim, and Decision 19's recovery-replay case is a concrete counter-example.
- **Deriving audit from the outbox** to avoid a second relation. Rejected — the outbox is delivery state that may legitimately be pruned; using it as the record makes pruning impossible or makes audit lossy (ADR-018 Decision 16).
- **Deleting undeliverable rows after a retry budget.** Rejected — it discards a committed business fact with no human decision (Decision 16).
- **Putting full document or knowledge content in the payload** so consumers need no callback. Rejected — every per-module rule in `AGENTS.md` forbids it, and an outbox payload is exactly the kind of widely-copied, long-lived, replicated store where privileged content must never land.
- **Letting the publisher run with `BYPASSRLS` for simplicity.** Rejected — it removes the isolation control from a component that touches every Firm's events. Decision 9 gives two acceptable alternatives.
- **Letting the publisher call domain code inside the claiming transaction.** Rejected — it reintroduces external calls and long transactions, and it makes a delivery failure a domain rollback.

## Consequences

- Every consequential state change acquires a durable, ordered-per-subject, replayable record of what it emitted — which is what makes eventual integration between contexts trustworthy at all.
- **Every command that emits an event does an extra insert**, inside the same transaction. Accepted: the alternative is silently losing events.
- **The publisher is a real component with real operational needs** — claiming, retry, backoff, dead-lettering, monitoring — and none of that is designed here. `PF-091` and `PF-092` own it.
- **Two genuinely open design decisions are handed forward with their hazards named**: the ordering/claiming strategy (Decision 8) and the publication Firm-context strategy (Decision 9). Naming them is more useful than guessing them.
- **`FOR UPDATE SKIP LOCKED` claiming costs an update per row** and makes the outbox a high-churn relation with vacuum and bloat characteristics an operational plan must account for. Not designed here.
- **Pruning published rows is required eventually**, and is safe only because of the audit separation in Decision 17.
- **Recovery can cause redelivery** (Decision 19). Consumers must be idempotent, and that obligation is now traceable to a concrete cause rather than an abstract principle.
- **Release 0.1 has no consumer.** The outbox is included for durability of committed facts, not for cross-context delivery; `PF-093` is not required. That keeps the delivered surface small and the claim honest.
- The internal event type and version create a small versioning discipline internally, distinct from and never substituting for `Integrations`' external contract versioning.

## Security and professional-responsibility consequences

- **An outbox row is a durable, widely-copied, replicated, backed-up copy of whatever was put in it.** It is one of the worst places on the platform for privileged content or a secret to land, because it is retained, replayed, and restored. Decision 11's content rule is a confidentiality control, not a schema preference.
- **The payload is never an authorization input.** No policy, constraint, or authorization decision reads it, so a payload cannot become a covert authorization channel.
- **Firm isolation applies to the outbox** — `firm_id`, forced Row-Level Security, and per-Firm claiming as the preferred publication path (Decisions 4, 9). Where the bounded cross-Firm exception is used, the publication role reaches **no business relation**, joins nothing, produces no cross-Firm aggregate, and re-establishes each row's Firm context before any domain-side work.
- **Atomicity is a professional-responsibility property, not only a technical one.** A committed Matter status change whose event was lost produces a record that disagrees with what downstream contexts believe — and in a legal practice, disagreement about whether a deadline, assignment, or closure happened is a client-facing failure.
- **A retry never mints a new event identity**, so a duplicate delivery is recognizable as the same occurrence rather than appearing as a second business fact (Decision 5).
- **A queued or long-running consumer revalidates authorization** and never treats captured provenance as an indefinite grant (Constitution Article 41).
- **Cancellation never erases a committed business fact** (Constitution Article 43). Undoing a completed action requires a separately authorized compensating command to the owning domain — never a rollback achieved by editing outbox or workflow state.
- **An undeliverable event is surfaced, never discarded** (Decision 16). Silently dropping a committed fact would leave a Firm's records internally inconsistent with no evidence of why.
- **No exactly-once and no global-ordering claim is made anywhere**, and recovery-driven redelivery is acknowledged explicitly (Decisions 15, 19).
- **AI holds no authority here.** AI never writes, approves, publishes, retries, dead-letters, prunes, or alters an outbox row, is never a consumer's authorization authority, and is never the acting principal for an event's underlying action. Release 0.1 contains no AI capability.
- **No certification, compliance, or production-readiness claim is made**, and no mechanism described here is claimed to be implemented.

## Integration consequences

- **`PF-091` — Transactional Outbox** (Backlog) gains its persistence contract, its atomicity requirement, and the two open decisions in Decisions 8 and 9, which it must resolve and record. **Its prerequisite analysis remains its own**, exactly as `docs/implementation/03_Engineering_Backlog.md` records; nothing here completes it.
- **`PF-092` — Event Publisher** (Backlog) gains the claiming, ordering, Firm-context, retry, and dead-letter obligations, and the rule that it never mutates domain state.
- **`PF-093` — Consumer foundation** (Backlog) gains the at-least-once and idempotency obligations. **Not required by Release 0.1.**
- **`PF-073` — Transaction Manager** (Backlog) is where the atomicity in Decision 3 is realized, together with Firm context establishment (ADR-016 Decision 8).
- **`PF-043` — DomainEvent** (Done) is **consumed exactly as delivered**. This ADR adds no member to it, invents no event name, creates no identifier type, and takes up the cross-boundary value-preservation obligation `PF-043` explicitly assigned to `PF-091`, `PF-092`, and `Integrations`.
- **`PF-040` — AggregateRoot** (Backlog) records events; this ADR does not design its API and does not schedule it.
- **`Integrations`** keeps sole ownership of `IntegrationEventEnvelope`, external versioning and deprecation, webhook signing and replay resistance, and internal-to-external identifier mapping. **An internal outbox row is never an external contract** (Constitution Article 31).
- **Every domain context** gains one way to emit events durably, and none gains the ability to publish externally on its own.
- **`Workflow`** consumes events through the same at-least-once discipline and keeps the rule that cancellation stops future work without erasing committed facts.
- **`PlatformAdministration`** emits its provisioning, entitlement, and seat-limit events through this mechanism; Decision 9 records why the publisher does not breach its cross-Firm boundary.
- **Billing** emits financial events carrying identifiers and safe metadata only — never payment secrets, bank credentials, privileged narratives, or cross-client content — which is Decision 11 applied to its own standing rule. No monetary column is introduced; `PF-045` remains Backlog and deferred.

## Explicit non-goals

This ADR does **not**: implement anything; create or modify any migration, schema, table, column, index, sequence, policy, role, grant, source file, test, configuration, CI workflow, dependency, or GitHub setting; define the outbox's actual column names, types, or migration, which are `PF-091`'s; design `PF-091`, `PF-092`, `PF-093`, `PF-090`, or `PF-073`, complete `PF-091`'s prerequisite analysis, or schedule any of them; define or name any concrete domain event; add a member to `PF-043` `DomainEvent` or alter any Foundation primitive; select or endorse a queue, broker, message bus, streaming platform, change-data-capture or replication mechanism, scheduler, worker supervisor, monitoring or observability product, hosting provider, managed database, connection pooler, or any other vendor, product, or package; define delivery infrastructure, deployment, capacity, throughput, latency, or scaling design; claim exactly-once delivery, global ordering, guaranteed latency, or any delivery service level; define an external contract, `IntegrationEventEnvelope`, webhook payload, signature scheme, external versioning, deprecation policy, or internal-to-external identifier mapping, all of which remain `Integrations`'; define consumer implementations, dead-letter operational procedures, or replay tooling; define an audit-purge or audit-retention path, or make the outbox an audit record; define, execute, schedule, or claim a backup or restore test, or prevent recovery-driven redelivery; create a Reporting context, cross-Firm event reporting, analytics, or a cross-Firm read role beyond the bounded publication exception in Decision 9 — Constitution Article 44's reserved Reporting context is **neither approved nor permanently prohibited** here; introduce `Money`, `Currency`, or any monetary column, or schedule or unblock `PF-045`; introduce an AI capability or modify `docs/architecture/05_AI_Architecture.md`; define an Ethical Wall, conflict-checking, or per-user visibility mechanism in any layer; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; claim that any described mechanism is implemented, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

**Proposed conceptual architecture only.** It authorizes no application code, schema, migration, policy, role, grant, dependency, package, infrastructure, Docker change, CI change, environment change, production configuration, or GitHub setting. **No mechanism described here is claimed to be implemented.** No outbox, publisher, consumer, dispatcher, or concrete domain event exists in production code, and `app/Modules` has not been created.

`PF-040` — AggregateRoot remains the next repository implementation story and remains **Backlog**. `PF-091`, `PF-092`, and `PF-093` remain **Backlog**. No story is In Progress. Outbox atomicity and publication behaviour cannot be honestly tested before the PostgreSQL CI story lands, which remains **Backlog** and must precede `PF-080`.

The story ID is **ARCH-012**; the architecture document it accompanies is the pre-reserved `docs/architecture/03_Database_Design.md` rather than a newly numbered file.
