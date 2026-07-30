# ADR-017 — Identifier Persistence Strategy

## Status

**Proposed.** Not approved, and it authorizes nothing. Acceptance requires explicit owner approval recorded on the pull request; acceptance would authorize the architectural decision only, never implementation, deployment, or production access.

Authored by story **ARCH-012 — Data & Persistence Architecture**, alongside `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…`, `ADR-018-…`, `ADR-019-…`, `ADR-020-…`, and `ADR-021-…`.

## Context

`AGENTS.md` requires PostgreSQL and UUIDv7. `PF-048` — UUIDv7 delivered `UuidV7`, `UuidV7Generator`, and `SystemUuidV7Generator` as the single Foundation identifier primitive, with strict validation and a canonical lowercase **text** representation. `PF-044` — BusinessIdentifier delivered the typed-identifier base, so a module identifier is a UUIDv7 **with a type**. `PF-043` — DomainEvent uses `UuidV7` directly for stable occurrence identity. (See `docs/PROJECT_STATUS.md` for each story's status.)

**None of that decides persistence.** Four questions remain open, and each has a wrong answer that is easy to reach:

1. **Column type.** `uuid`, `char(36)`, `varchar`, or `bytea`?
2. **Who generates.** The application, or a database default such as `gen_random_uuid()`, a server-side UUIDv7 function, or a trigger?
3. **Ordering.** `PF-048` deliberately uses one word for UUIDv7 — **time-sortable** — and its backlog entry records that same-millisecond monotonic increment holds only within a single process and "could not be promised platform-wide anyway." It is very easy to read "time-sortable" as "orderable" and to write `ORDER BY id`, a keyset cursor over `id`, or an outbox publisher that depends on identifier order. Each is silently wrong.
4. **Keys.** Is the primary key `id` alone or `(firm_id, id)`? What guarantees that a foreign key does not point at another Firm's row? Where do human-readable business identifiers such as `MatterNumber` live?

The fourth question has a security dimension, and it is sharper than it first appears. Under `docs/adr/ADR-016-Tenant-Isolation-Model.md`, Row-Level Security evaluates each row against the current Firm context **independently**, so a single-column foreign key from a Firm A row to a Firm B row satisfies both rows' policies and the resulting link is invisible in ordinary querying. **Worse: PostgreSQL performs referential-integrity checks with row security suspended**, so the policy is never consulted during the check either — the cross-Firm reference is simply **accepted**. Row-Level Security is therefore not a partial defence here; it is no defence at all, and nothing in the approved architecture currently prevents the link.

`PF-048` also recorded that **a UUIDv7 discloses its approximate generation time to anyone holding it**, and `docs/architecture/07_API_Standards.md` §3 and §5 require external identifiers to be opaque and never a basis for enumeration or ordering. Persistence is where identifiers become durable and widely copied, so that constraint has to be restated where schemas are designed.

## Decision

1. **Native PostgreSQL `uuid` columns.** Not `char(36)`, `varchar`, `text`, or `bytea`. Sixteen bytes rather than thirty-six, type-checked by the database, and materially smaller and faster in every index containing it — which, given Decision 6, is most of them.

2. **Application-generated UUIDv7, from the Foundation primitive `PF-048`.** Every identifier is produced by `UuidV7Generator`.

   **On representation, precisely: a native `uuid` column has no lowercase or uppercase storage form.** PostgreSQL stores sixteen bytes and normalizes case on input, so `PF-048`'s canonical-lowercase rule is a **textual-boundary** obligation — how an identifier is rendered, logged, serialized, compared as text, and emitted in an event payload — **not** a property of the stored column. Text-form canonicalization therefore remains an application obligation at every boundary where an identifier becomes text, and the native type makes database-side case-sensitivity bugs impossible rather than merely unlikely.

3. **No database-generated identifier default of any kind** on a domain relation — no `gen_random_uuid()`, no server-side UUIDv7 function, no `DEFAULT`, no trigger, no sequence, no `bigserial` surrogate. Four reasons, each independently sufficient:
   - **A domain object has identity before it is persisted.** An aggregate is constructed, records events, and is referenced by those events and by its outbox row, all before any `INSERT`. A database default would mean the identity in the event and the identity in the row are assigned by different authorities at different times.
   - **Retry safety.** A command retried after an ambiguous failure must be able to write **the same** identifier. A database default makes every retry a new identity and every partial failure a duplicate.
   - **Testability.** `PF-048`'s generator is injectable precisely so identity is deterministic under test. A database default is not.
   - **One format authority.** `PF-048` already owns strict validation and canonicalization; a second generation path is a second format authority, and they will diverge.

4. **UUIDv7 is not an ordering guarantee, and is never used as one.** `PF-048`'s "time-sortable" describes a **physical index-locality property** — a time-prefixed key clusters B-tree inserts, reducing page splits and index bloat. **That is the justification for preferring UUIDv7 over UUIDv4; it is not the reason UUIDv7 is mandated.** UUIDv7 is mandated by `AGENTS.md` and by the Foundation primitive `PF-048`; locality is why that mandate is also the technically preferable choice, and the locality argument alone would favour it even absent the mandate.

   It is **not** an ordering semantic, because: identifiers generated in the same millisecond have arbitrary relative order; the generating clock is a wall clock that may be corrected **backwards** (`PF-047`); multiple processes generate concurrently with no coordination; and **commit order is not generation order**, so a lower identifier can become visible after a higher one.

   Therefore, prohibited: an identifier as a **business, chronological, causal, or delivery** ordering key; an identifier used as a pagination cursor implying time order; and any ordering, deduplication, or idempotency key derived from an identifier's timestamp bits. Where ordering matters it is explicit and per-subject (`docs/adr/ADR-019-Transactional-Outbox-Persistence.md`).

   **One permitted use — the final deterministic tiebreaker.** After an **explicit business sort key**, an identifier **may** be appended as the last sort term to make the total order deterministic and keyset pagination stable. This is legitimate precisely because it carries no meaning: it breaks ties arbitrarily but *repeatably*, which is what a stable cursor requires. Permitted **only** in that position, **only** after a business key; it never becomes the primary sort term, never implies chronology, and never substitutes for the explicit per-subject ordering delivery requires.

5. **The primary key of a Firm-scoped relation is `id uuid` alone.** Identifiers are globally unique by construction, so a composite primary key buys nothing for identity and widens every referencing index for no identity benefit.

   **This is an explicit, narrow exception to two rules stated elsewhere**, and it is recorded as an exception rather than left to be noticed:
   - to the **Firm-scoped-uniqueness rule** (`docs/architecture/03_Database_Design.md` §9.2), because `id` is globally unique across Firms; and
   - to the **index rule** (§9.1) requiring `firm_id` to lead, because the primary-key index is `(id)`.

   **Why the existence-disclosure argument does not apply.** That rule exists because a constraint violation over a **business attribute** tells the caller exactly which meaningful value another Firm holds. A primary key holds **no business value**: a collision would mean two independently generated 122-bit random values coincided, which conveys no client name, matter reference, email address, or other fact a Firm could act on or infer from. The value is **generated by the application** (Decision 2), never chosen or supplied by a caller, so a caller cannot probe for a *particular* identifier's existence in the ordinary course; a collision is a defect report, not an information channel. **The exception covers platform-generated identifier columns only** — never a business attribute, human-readable reference, or caller-supplied value.

6. **Every Firm-scoped relation additionally carries `UNIQUE (firm_id, id)`, and every intra-context foreign key is composite** — referencing `(firm_id, id)` and carrying the referencing row's own `firm_id` as its first column. This makes **"the row I reference belongs to my Firm"** structural, closing the cross-Firm link described in Context — which, as Decision 7a records, **Row-Level Security does not close**.

   **The cost, stated completely — it is two costs, not one:**
   - one extra `UNIQUE (firm_id, id)` index per Firm-scoped relation, and wider foreign-key columns and indexes; **and**
   - **explicitly created child-side indexes.** PostgreSQL automatically indexes only the **referenced** (parent) side of a foreign key, because the constraint requires a unique index there. **It creates no index on the referencing (child) columns.** Without one, a parent-side `DELETE` or an `UPDATE` of the referenced key sequentially scans the child relation to check the constraint. **Where a child-side composite index is needed — for constraint-check performance, join performance, or the Firm-leading access pattern — the owning story creates it explicitly and never assumes the foreign key provided it.**

   Both are accepted deliberately. Neither is nil, and neither arrives free with the constraint declaration.

7. **Cross-bounded-context references are identifier-only, with no foreign key**, per `docs/architecture/03_Database_Design.md` §8.2. Consequently there is no database-level guarantee that a cross-context identifier resolves; the owning context validates it at command time through its published contract, an unavailable owning context **fails closed**, and both sides' `firm_id` must match.

7a. **Referential-integrity checks bypass Row-Level Security, and the design is built around that.** PostgreSQL performs foreign-key and unique-constraint checks **with row security suspended** — a check must see rows the caller cannot, or the constraint would mean something different per caller. Two direct consequences:
   - **A single-column foreign key would *accept* a cross-Firm reference.** The check sees the parent row regardless of the caller's Firm context, so no policy is consulted and none can object; the resulting link is then invisible in ordinary querying because each row satisfies its own policy. **Row-Level Security is not a defence against cross-Firm referencing at all.** The guarantee in Decision 6 comes from the key's **shape** plus the child's `WITH CHECK` on `firm_id` — the column the policy constrains on write and which is immutable thereafter — not from the check honouring policies, which it does not.
   - **A constraint can raise a violation against a row the caller cannot see**, and the error reaches that caller. This is the existence-disclosure channel Decision 9 forbids, and it is why **the defence is the constraint's scope by construction, not the caller's visibility**. What reaches a caller is a deliberately shaped domain error, never an unfiltered driver message naming a constraint, relation, or conflicting value.

8. **No natural or business key is ever a primary key.** A human-readable business identifier — `MatterNumber` being the canonical example — is a **Firm-scoped unique attribute**, never identity. `docs/architecture/13_Practice_Management_Architecture.md` makes `MatterNumber` Firm-configurable, human-meaningful, and immutable only **from** the `Opened` transition, all of which disqualify it as a key; the aggregate's own UUIDv7 remains the system identifier. Its uniqueness is `UNIQUE (firm_id, matter_number)`, assigned concurrency-safely by a constraint plus bounded retry or a transaction-scoped advisory lock — **never** by reading the current maximum and inserting one higher — and a collision is **rejected, never silently resolved by appending a suffix**.

9. **Every uniqueness constraint on Firm business data is Firm-scoped by construction**, and a global unique constraint on Firm business data is prohibited. Beyond correctness, **a global constraint discloses another Firm's row through a constraint error** — and, per Decision 7a, the caller's inability to *see* that row does not prevent the error. For a client name or matter reference that is an existence disclosure across a conflicts boundary. **The single exception is the platform-generated identifier column in Decision 5**, for the reasons recorded there. Genuinely platform-global uniqueness lives on platform-global relations (ADR-016 Decision 4), where there is no Firm to disclose.

10. **Monotonic sequence columns are permitted only where they are ordering, not identity** — specifically the outbox sequence. Such a column is never a primary key, never a domain identifier, and never externally exposed.

11. **Identifier disclosure is managed deliberately.** A UUIDv7 discloses its generation time to millisecond precision to anyone holding it. An internal identifier is **never** exposed externally merely for convenience (Constitution Article 31); external identifiers are opaque and never a basis for enumeration or ordering (`docs/architecture/07_API_Standards.md` §3, §5); internal-to-external mapping remains `Integrations`'. **Generation time and business occurrence time are different facts and are never conflated** — an identifier may be generated before, during, or after the instant it describes. An identifier is not automatically safe to log, dump, or render.

12. **Every index on a Firm-scoped relation includes `firm_id`, and leads with it** unless the owning story records a specific reason otherwise. Every real query and every row policy filters by Firm; an index without it forces post-retrieval filtering and, worse, produces a query shape that invites omitting the Firm predicate because it "works". **Two explicit exceptions:** the `id`-only primary-key index (Decision 5), and a child-side foreign-key index, which leads with `firm_id` by the key's column order and must be created explicitly (Decision 6). **No index exists whose only purpose is to serve a cross-Firm query.**

13. **This ADR schedules nothing and asserts no story's status.** No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled; `PF-048`, `PF-044`, and `PF-043` are consumed exactly as delivered, with no change to any of them. **`docs/PROJECT_STATUS.md` is the authoritative record of story status.**

## Alternatives considered

- **`bigserial` / auto-increment primary keys.** Rejected. They are database-assigned, so an aggregate has no identity until insertion — breaking event and outbox co-identity (Decision 3). They are guessable and enumerable, which is a direct conflict with `docs/architecture/07_API_Standards.md` §5. They make identity collide across environments, complicating imports and restores. And they contradict `AGENTS.md`'s UUIDv7 requirement outright.
- **UUIDv4.** Rejected — no index locality. Random keys spread inserts across the whole B-tree, causing page splits and index bloat that grow with table size. UUIDv7 gives the locality benefit at the same 16 bytes with the same collision resistance, which is precisely why `PF-048` chose it.
- **Database-generated UUIDs** (`gen_random_uuid()`, a server-side UUIDv7 function, or a trigger). Rejected on all four grounds in Decision 3. The retry-safety point alone is decisive: a partially-failed command must be able to rewrite the same identifier.
- **Storing UUIDs as `char(36)` or `varchar` for readability in `psql`.** Rejected — 36 bytes instead of 16, in every index including the composite keys in Decision 6, for a debugging convenience `uuid` already provides by rendering as text.
- **Storing UUIDs as `bytea`.** Rejected — smaller than text but loses type checking and renders unreadably, with no advantage over the native `uuid` type.
- **Composite primary key `(firm_id, id)`.** Rejected as the **primary** key. Identifiers are already globally unique, so it adds nothing for identity while widening every referencing index. Decision 6 obtains the tenancy guarantee from a **separate** unique constraint plus composite foreign keys — the benefit without the cost of a wider primary key everywhere. It also keeps the Decision 5 exception narrow and inspectable rather than diffusing it.
- **Single-column foreign keys, leaving same-Firm referencing to the application.** Rejected — the cross-Firm reference would be **accepted by the constraint**, because referential-integrity checks run with row security suspended (Decision 7a), and the resulting link is then invisible in ordinary querying. The extra indexes in Decision 6 are a small price for a structural guarantee.
- **Relying on Row-Level Security to reject a cross-Firm foreign key.** Rejected because it does not work at all (Decision 7a). It is the single most plausible-sounding wrong assumption in this design, which is why Decision 7a states it explicitly and why `docs/architecture/03_Database_Design.md` §16.3 requires a standing test asserting that a single-column key *accepts* the cross-Firm reference.
- **Treating `MatterNumber` (or any business key) as the primary key.** Rejected — it is Firm-configurable, assigned only at a later lifecycle point, human-meaningful, and mutable until `Opened`. A key that does not exist when the row does is not a key.
- **Global unique constraints on business attributes** such as a client email address. Rejected — a constraint violation becomes an existence disclosure across a conflicts boundary (Decision 9).
- **Relying on `ORDER BY id` for event or business ordering** because UUIDv7 is time-sortable. Rejected — it is the exact misreading Decision 4 exists to prevent, and it fails silently under same-millisecond generation, clock correction, concurrency, and commit reordering.
- **Prohibiting the identifier in a sort clause outright**, to remove the misreading entirely. Rejected as over-broad: keyset pagination needs a deterministic total order, and forbidding the tiebreaker would push implementers toward a *worse* substitute such as a timestamp column, which carries a false implication of chronology. Decision 4 permits the narrow, meaningless use and keeps every meaningful one prohibited.
- **Adopting a library's monotonic-counter UUIDv7 to obtain ordering.** Rejected, restating `PF-048`'s own recorded reasoning: monotonicity holds only within a single process and cannot be promised platform-wide, so it would buy a guarantee that is false in exactly the multi-process case where ordering is claimed to matter. Because generation sits behind `UuidV7Generator`, adopting a library later remains additive.
- **A separate surrogate integer key alongside the UUID** for index efficiency. Rejected — two identities per row, two things to keep consistent, two things to accidentally expose, and it reintroduces database-assigned identity through the back door.

## Consequences

- One identifier format, one validator, one generator, platform-wide. Reconstitution, comparison, and equality semantics come from `PF-048` and `PF-044` rather than being re-decided per module.
- Identity exists before persistence, so an aggregate, its events, its audit row, and its outbox row genuinely share one identifier value rather than three near-misses.
- **Composite foreign keys, the extra `UNIQUE (firm_id, id)`, and explicitly created child-side indexes cost storage and write throughput** on every Firm-scoped relation. Accepted for a structurally enforced same-Firm guarantee. **The child-side index is an easy omission** — nothing fails immediately, and the cost appears later as slow parent deletes — so it is an item each owning story must decide rather than inherit.
- **No database-level guarantee exists for cross-context references** (Decision 7). Owning contexts validate, and unavailability fails closed. That is a real, named cost of module independence, not an oversight.
- **Ordering must be designed explicitly wherever it matters** (`docs/adr/ADR-019-Transactional-Outbox-Persistence.md`). No implicit ordering exists to fall back on, which is the point.
- **Every identifier carries a readable creation timestamp.** That is unavoidable with UUIDv7 and is why Decision 11 exists.
- Keyset pagination over Firm-scoped relations needs an explicit, documented business sort key, with the identifier permitted only as the final tiebreaker after it (Decision 4).
- Adopting a UUID library later is additive because generation sits behind `UuidV7Generator`; changing the **column type** later would not be, which is why Decision 1 is settled now.

## Security and professional-responsibility consequences

- **A UUIDv7 discloses its generation time to millisecond precision to anyone holding it.** Persisted identifiers propagate into logs, exports, events, and support conversations; internal identifiers are never exposed externally by convenience (Constitution Article 31), and rendering or recording one is a deliberate decision by the calling code.
- **Generation time is not occurrence time.** Conflating them would misrepresent when a legally significant act happened — a professional-responsibility hazard, not a cosmetic one. `PF-043` keeps `occurredAt()` separate, and `docs/architecture/03_Database_Design.md` §7.2 keeps all five times separate in persistence.
- **Row-Level Security does not prevent a cross-Firm foreign key**, because referential-integrity checks run with row security suspended (Decision 7a). The composite key's shape plus the child's `WITH CHECK` is the control, and a standing test asserts the difference so a later "simplification" cannot quietly remove it.
- **A global unique constraint on Firm business data is an existence disclosure**, delivered by a constraint error to a Firm not entitled to know — and suspended row security during the check means visibility does not protect against it (Decisions 7a, 9). Denied-existence confidentiality forbids it (Constitution Article 28). **The Decision 5 identifier exception is narrow and turns on the value carrying no business meaning and never being caller-supplied.**
- **An identifier is not a capability and not a secret.** Holding a `firm_id` or a record identifier grants nothing; a valid identifier is never sufficient for access (`docs/architecture/04_Security_Architecture.md` §3, IDOR/BOLA). Authorization is composed server-side on every object access, before retrieval.
- **Sequential or guessable identifiers would enable enumeration** of Firms, Clients, and Matters — precisely the existence disclosure the platform forbids. That, not aesthetics, is why `bigserial` is rejected.
- **`MatterNumber` collisions are rejected, never silently resolved.** A silently suffixed matter reference is a professional-integrity defect in a Firm's records, not a UX nicety.
- **No monetary column exists**, and no identifier decision here introduces one; `PF-045` remains deferred from Release 0.1 (`docs/PROJECT_STATUS.md` is authoritative on its status).
- **AI holds no authority here.** AI never assigns, alters, or approves an identifier, key, constraint, or index, and is never an authorization authority. Release 0.1 contains no AI capability.
- **No certification, compliance, production-readiness, or legal-sufficiency claim is made; no legal review has occurred or is claimed**, and nothing described here is claimed to be implemented, tested, or effective.

## Integration consequences

- **`app/Foundation`** — `PF-048` `UuidV7`/`UuidV7Generator`, `PF-044` `BusinessIdentifier`, `PF-043` `DomainEvent`, and `PF-047` `Clock` are **consumed exactly as delivered**. This ADR changes none of them, introduces no new identifier type, and creates no concrete `BusinessIdentifier` subclass.
- **`PF-040` — AggregateRoot** inherits the rule that an aggregate holds a typed identifier assigned at construction, never one supplied by the database. This ADR does not design `PF-040`'s API, does not schedule it, and asserts nothing about its status.
- **Every module** gains the same key, index, and uniqueness conventions, and defines its own concrete `BusinessIdentifier` types under its own approved story.
- **Practice Management** gains the explicit ruling that `MatterNumber` is a Firm-scoped unique attribute rather than a key, with concurrency-safe assignment and rejected collisions — consistent with, and not altering, `docs/architecture/13_Practice_Management_Architecture.md`.
- **`Integrations`** retains sole ownership of internal-to-external identifier mapping and of the opacity requirement in `docs/architecture/07_API_Standards.md` §3 and §5. No external contract is defined here.
- **`PlatformAdministration`** keeps ownership of `Firm`; whether `firm_id` carries a foreign key to it remains a recorded open decision (`docs/architecture/03_Database_Design.md` §8.3, §22).
- **`PF-091` / `PF-092`** inherit Decisions 4 and 10: outbox ordering comes from an explicit sequence, never from an identifier, and never from a high-water-mark cursor over one (`docs/adr/ADR-019-Transactional-Outbox-Persistence.md`).

## Explicit non-goals

This ADR does **not**: implement anything; create or modify any migration, schema, table, column, index, constraint, source file, test, configuration, CI workflow, dependency, or GitHub setting; create an identifier type, a `BusinessIdentifier` subclass, or any concrete module identifier; alter `PF-042`, `PF-043`, `PF-044`, `PF-047`, `PF-048`, or `PF-049`, or design `PF-040`; define any module's physical data model or any table or column name; define which aggregates, attributes, or business identifiers exist in any context; select or endorse a UUID library, ORM, migration tool, database provider, hosting platform, or any other vendor, product, or package; define a PostgreSQL version, extension set, or server configuration; define search, full-text, or vector indexing; introduce `Money`, `Currency`, or any monetary column, or schedule or unblock `PF-045`; introduce an AI capability or modify `docs/architecture/05_AI_Architecture.md`; define an external identifier, external contract, or internal-to-external mapping, which remain `Integrations`'; claim exactly-once delivery or any ordering guarantee; define an Ethical Wall, conflict-checking, or per-user visibility mechanism in any layer; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; claim that any described property is implemented, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

**Proposed conceptual architecture only.** It authorizes no application code, schema, migration, dependency, package, infrastructure, Docker change, CI change, environment change, production configuration, or GitHub setting. **No property described here is claimed to be implemented.**

**No story's status is asserted here; `docs/PROJECT_STATUS.md` is the authoritative record** of what is current, next, and complete. `PF-045` — Money and `PF-046` — Result remain deferred from Release 0.1 per `docs/architecture/02_Product_Requirements.md` §3.

The story ID is **ARCH-012**; the architecture document it accompanies is the pre-reserved `docs/architecture/03_Database_Design.md` rather than a newly numbered file.
