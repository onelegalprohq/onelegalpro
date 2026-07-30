# ADR-017 — Identifier Persistence Strategy

## Status

**Proposed.** Not approved, and it authorizes nothing. Acceptance requires explicit owner approval recorded on the pull request; acceptance would authorize the architectural decision only, never implementation, deployment, or production access.

Authored by story **ARCH-012 — Data & Persistence Architecture**, alongside `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…`, `ADR-018-…`, `ADR-019-…`, and `ADR-020-…`.

## Context

`AGENTS.md` requires PostgreSQL and UUIDv7. `PF-048` — UUIDv7 (**Done**) delivered `UuidV7`, `UuidV7Generator`, and `SystemUuidV7Generator` as the single Foundation identifier primitive, with strict validation and canonical lowercase representation. `PF-044` — BusinessIdentifier (**Done**) delivered the typed-identifier base, so a module identifier is a UUIDv7 **with a type**. `PF-043` — DomainEvent (**Done**) uses `UuidV7` directly for stable occurrence identity.

**None of that decides persistence.** Four questions remain open, and each has a wrong answer that is easy to reach:

1. **Column type.** `uuid`, `char(36)`, `varchar`, or `bytea`?
2. **Who generates.** The application, or a database default such as `gen_random_uuid()`, a server-side UUIDv7 function, or a trigger?
3. **Ordering.** `PF-048` deliberately uses one word for UUIDv7 — **time-sortable** — and its backlog entry records that same-millisecond monotonic increment holds only within a single process and "could not be promised platform-wide anyway." It is very easy to read "time-sortable" as "orderable" and to write `ORDER BY id`, a keyset cursor over `id`, or an outbox publisher that depends on identifier order. Each is silently wrong.
4. **Keys.** Is the primary key `id` alone or `(firm_id, id)`? What guarantees that a foreign key does not point at another Firm's row? Where do human-readable business identifiers such as `MatterNumber` live?

The fourth question has a security dimension. Under `docs/adr/ADR-016-Tenant-Isolation-Model.md`, Row-Level Security evaluates each row against the current Firm context **independently**. A single-column foreign key from a Firm A row to a Firm B row therefore satisfies both rows' policies and is **invisible to Row-Level Security**. Nothing in the approved architecture currently prevents it.

`PF-048` also recorded that **a UUIDv7 discloses its approximate generation time to anyone holding it**, and `docs/architecture/07_API_Standards.md` §3 and §5 require external identifiers to be opaque and never a basis for enumeration or ordering. Persistence is where identifiers become durable and widely copied, so that constraint has to be restated where schemas are designed.

## Decision

1. **Native PostgreSQL `uuid` columns.** Not `char(36)`, `varchar`, `text`, or `bytea`. Sixteen bytes rather than thirty-six, type-checked by the database, and materially smaller and faster in every index containing it — which, given Decision 6, is most of them.

2. **Application-generated UUIDv7, from the Foundation primitive `PF-048`.** Every identifier is produced by `UuidV7Generator` and stored in canonical lowercase form.

3. **No database-generated identifier default of any kind** on a domain relation — no `gen_random_uuid()`, no server-side UUIDv7 function, no `DEFAULT`, no trigger, no sequence, no `bigserial` surrogate. Four reasons, each independently sufficient:
   - **A domain object has identity before it is persisted.** An aggregate is constructed, records events, and is referenced by those events and by its outbox row, all before any `INSERT`. A database default would mean the identity in the event and the identity in the row are assigned by different authorities at different times.
   - **Retry safety.** A command retried after an ambiguous failure must be able to write **the same** identifier. A database default makes every retry a new identity and every partial failure a duplicate.
   - **Testability.** `PF-048`'s generator is injectable precisely so identity is deterministic under test. A database default is not.
   - **One format authority.** `PF-048` already owns strict validation and canonicalization; a second generation path is a second format authority, and they will diverge.

4. **UUIDv7 is not an ordering guarantee, and is never used as one.** `PF-048`'s "time-sortable" describes a **physical index-locality property** — a time-prefixed key clusters B-tree inserts, reducing page splits and index bloat, and that is the only reason UUIDv7 is preferred to UUIDv4. It is **not** an ordering semantic, because: identifiers generated in the same millisecond have arbitrary relative order; the generating clock is a wall clock that may be corrected **backwards** (`PF-047`); multiple processes generate concurrently with no coordination; and **commit order is not generation order**, so a lower identifier can become visible after a higher one.

   Therefore, prohibited: **`ORDER BY` an identifier** for business or delivery ordering; an identifier used as a pagination cursor implying time order; and any ordering, deduplication, or idempotency key derived from an identifier's timestamp bits. Where ordering matters it is explicit and per-subject (`docs/adr/ADR-019-Transactional-Outbox-Persistence.md`).

5. **The primary key of a Firm-scoped relation is `id uuid` alone.** Identifiers are globally unique by construction, so a composite primary key buys nothing for identity and widens every referencing index for no identity benefit.

6. **Every Firm-scoped relation additionally carries `UNIQUE (firm_id, id)`, and every intra-context foreign key is composite** — referencing `(firm_id, id)` and carrying the referencing row's own `firm_id` as its first column. This makes **"the row I reference belongs to my Firm"** a fact the database enforces rather than a convention the application maintains, and it closes the Row-Level-Security-invisible cross-Firm link described in Context. **The cost — one extra unique index per Firm-scoped relation, and wider foreign-key columns and indexes — is accepted deliberately.**

7. **Cross-bounded-context references are identifier-only, with no foreign key**, per `docs/architecture/03_Database_Design.md` §8.2. Consequently there is no database-level guarantee that a cross-context identifier resolves; the owning context validates it at command time through its published contract, an unavailable owning context **fails closed**, and both sides' `firm_id` must match.

8. **No natural or business key is ever a primary key.** A human-readable business identifier — `MatterNumber` being the canonical example — is a **Firm-scoped unique attribute**, never identity. `docs/architecture/13_Practice_Management_Architecture.md` makes `MatterNumber` Firm-configurable, human-meaningful, and immutable only **from** the `Opened` transition, all of which disqualify it as a key; the aggregate's own UUIDv7 remains the system identifier. Its uniqueness is `UNIQUE (firm_id, matter_number)`, assigned concurrency-safely by a constraint plus bounded retry or a transaction-scoped advisory lock — **never** by reading the current maximum and inserting one higher — and a collision is **rejected, never silently resolved by appending a suffix**.

9. **Every uniqueness constraint on Firm-scoped data is Firm-scoped**, and a global unique constraint on Firm data is prohibited. Beyond correctness, **a global constraint discloses another Firm's row through a constraint error**: a Firm receiving a violation for a value it cannot see has learned another Firm holds it, which for a client name or matter reference is an existence disclosure across a conflicts boundary. Genuinely platform-global uniqueness lives on platform-global relations (ADR-016 Decision 4), where there is no Firm to disclose.

10. **Monotonic sequence columns are permitted only where they are ordering, not identity** — specifically the outbox sequence. Such a column is never a primary key, never a domain identifier, and never externally exposed.

11. **Identifier disclosure is managed deliberately.** A UUIDv7 discloses its generation time to millisecond precision to anyone holding it. An internal identifier is **never** exposed externally merely for convenience (Constitution Article 31); external identifiers are opaque and never a basis for enumeration or ordering (`docs/architecture/07_API_Standards.md` §3, §5); internal-to-external mapping remains `Integrations`'. **Generation time and business occurrence time are different facts and are never conflated** — an identifier may be generated before, during, or after the instant it describes. An identifier is not automatically safe to log, dump, or render.

12. **Every index on a Firm-scoped relation includes `firm_id`, and leads with it** unless the owning story records a specific reason otherwise. Every real query and every row policy filters by Firm; an index without it forces post-retrieval filtering and, worse, produces a query shape that invites omitting the Firm predicate because it "works". **No index exists whose only purpose is to serve a cross-Firm query.**

13. **This ADR schedules nothing.** `PF-040` remains the next code story and remains Backlog. No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled, and `PF-048`, `PF-044`, and `PF-043` are consumed exactly as delivered, with no change to any of them.

## Alternatives considered

- **`bigserial` / auto-increment primary keys.** Rejected. They are database-assigned, so an aggregate has no identity until insertion — breaking event and outbox co-identity (Decision 3). They are guessable and enumerable, which is a direct conflict with `docs/architecture/07_API_Standards.md` §5. They make identity collide across environments, complicating imports and restores. And they contradict `AGENTS.md`'s UUIDv7 requirement outright.
- **UUIDv4.** Rejected — no index locality. Random keys spread inserts across the whole B-tree, causing page splits and index bloat that grow with table size. UUIDv7 gives the locality benefit at the same 16 bytes with the same collision resistance, which is precisely why `PF-048` chose it.
- **Database-generated UUIDs** (`gen_random_uuid()`, a server-side UUIDv7 function, or a trigger). Rejected on all four grounds in Decision 3. The retry-safety point alone is decisive: a partially-failed command must be able to rewrite the same identifier.
- **Storing UUIDs as `char(36)` or `varchar` for readability in `psql`.** Rejected — 36 bytes instead of 16, in every index including the composite keys in Decision 6, for a debugging convenience `uuid` already provides by rendering as text.
- **Storing UUIDs as `bytea`.** Rejected — smaller than text but loses type checking and renders unreadably, with no advantage over the native `uuid` type.
- **Composite primary key `(firm_id, id)`.** Rejected as the **primary** key. Identifiers are already globally unique, so it adds nothing for identity while widening every referencing index. Decision 6 obtains the tenancy guarantee from a **separate** unique constraint plus composite foreign keys — the benefit without the cost of a wider primary key everywhere.
- **Single-column foreign keys, leaving same-Firm referencing to the application.** Rejected — the resulting cross-Firm link is invisible to Row-Level Security (Context), and the extra index in Decision 6 is a small price for a database-enforced fact.
- **Treating `MatterNumber` (or any business key) as the primary key.** Rejected — it is Firm-configurable, assigned only at a later lifecycle point, human-meaningful, and mutable until `Opened`. A key that does not exist when the row does is not a key.
- **Global unique constraints on business attributes** such as a client email address. Rejected — a constraint violation becomes an existence disclosure across a conflicts boundary (Decision 9).
- **Relying on `ORDER BY id` for event or business ordering** because UUIDv7 is time-sortable. Rejected — it is the exact misreading Decision 4 exists to prevent, and it fails silently under same-millisecond generation, clock correction, concurrency, and commit reordering.
- **Adopting a library's monotonic-counter UUIDv7 to obtain ordering.** Rejected, restating `PF-048`'s own recorded reasoning: monotonicity holds only within a single process and cannot be promised platform-wide, so it would buy a guarantee that is false in exactly the multi-process case where ordering is claimed to matter. Because generation sits behind `UuidV7Generator`, adopting a library later remains additive.
- **A separate surrogate integer key alongside the UUID** for index efficiency. Rejected — two identities per row, two things to keep consistent, two things to accidentally expose, and it reintroduces database-assigned identity through the back door.

## Consequences

- One identifier format, one validator, one generator, platform-wide. Reconstitution, comparison, and equality semantics come from `PF-048` and `PF-044` rather than being re-decided per module.
- Identity exists before persistence, so an aggregate, its events, its audit row, and its outbox row genuinely share one identifier value rather than three near-misses.
- **Composite foreign keys and the extra `UNIQUE (firm_id, id)` cost storage and write throughput** on every Firm-scoped relation. Accepted for a database-enforced same-Firm guarantee.
- **No database-level guarantee exists for cross-context references** (Decision 7). Owning contexts validate, and unavailability fails closed. That is a real, named cost of module independence, not an oversight.
- **Ordering must be designed explicitly wherever it matters** (`docs/adr/ADR-019-Transactional-Outbox-Persistence.md`). No implicit ordering exists to fall back on, which is the point.
- **Every identifier carries a readable creation timestamp.** That is unavoidable with UUIDv7 and is why Decision 11 exists.
- Keyset pagination over Firm-scoped relations needs an explicit, documented sort key — never `id` implying time.
- Adopting a UUID library later is additive because generation sits behind `UuidV7Generator`; changing the **column type** later would not be, which is why Decision 1 is settled now.

## Security and professional-responsibility consequences

- **A UUIDv7 discloses its generation time to millisecond precision to anyone holding it.** Persisted identifiers propagate into logs, exports, events, and support conversations; internal identifiers are never exposed externally by convenience (Constitution Article 31), and rendering or recording one is a deliberate decision by the calling code.
- **Generation time is not occurrence time.** Conflating them would misrepresent when a legally significant act happened — a professional-responsibility hazard, not a cosmetic one. `PF-043` keeps `occurredAt()` separate, and `docs/architecture/03_Database_Design.md` §7.2 keeps all five times separate in persistence.
- **A single-column foreign key can create a cross-Firm link that Row-Level Security cannot see.** Composite Firm-carrying keys close that hole in the database rather than in a convention (Decision 6).
- **A global unique constraint on Firm data is an existence disclosure**, delivered by a constraint error to a Firm that is not entitled to know (Decision 9). Denied-existence confidentiality forbids it (Constitution Article 28).
- **An identifier is not a capability and not a secret.** Holding a `firm_id` or a record identifier grants nothing; a valid identifier is never sufficient for access (`docs/architecture/04_Security_Architecture.md` §3, IDOR/BOLA). Authorization is composed server-side on every object access, before retrieval.
- **Sequential or guessable identifiers would enable enumeration** of Firms, Clients, and Matters — precisely the existence disclosure the platform forbids. That, not aesthetics, is why `bigserial` is rejected.
- **`MatterNumber` collisions are rejected, never silently resolved.** A silently suffixed matter reference is a professional-integrity defect in a Firm's records, not a UX nicety.
- **No monetary column exists**, and no identifier decision here introduces one; `PF-045` remains Backlog and deferred.
- **AI holds no authority here.** AI never assigns, alters, or approves an identifier, key, constraint, or index, and is never an authorization authority. Release 0.1 contains no AI capability.
- **No certification, compliance, or production-readiness claim is made**, and nothing described here is claimed to be implemented.

## Integration consequences

- **`app/Foundation`** — `PF-048` `UuidV7`/`UuidV7Generator`, `PF-044` `BusinessIdentifier`, `PF-043` `DomainEvent`, and `PF-047` `Clock` are **consumed exactly as delivered**. This ADR changes none of them, introduces no new identifier type, and creates no concrete `BusinessIdentifier` subclass.
- **`PF-040` — AggregateRoot** (Backlog) inherits the rule that an aggregate holds a typed identifier assigned at construction, never one supplied by the database. This ADR does not design `PF-040`'s API and does not schedule it.
- **Every module** gains the same key, index, and uniqueness conventions, and defines its own concrete `BusinessIdentifier` types under its own approved story.
- **Practice Management** gains the explicit ruling that `MatterNumber` is a Firm-scoped unique attribute rather than a key, with concurrency-safe assignment and rejected collisions — consistent with, and not altering, `docs/architecture/13_Practice_Management_Architecture.md`.
- **`Integrations`** retains sole ownership of internal-to-external identifier mapping and of the opacity requirement in `docs/architecture/07_API_Standards.md` §3 and §5. No external contract is defined here.
- **`PlatformAdministration`** keeps ownership of `Firm`; whether `firm_id` carries a foreign key to it remains a recorded open decision (`docs/architecture/03_Database_Design.md` §8.3, §22).
- **`PF-091` / `PF-092`** inherit Decision 4 and 10: outbox ordering comes from an explicit sequence, never from an identifier.

## Explicit non-goals

This ADR does **not**: implement anything; create or modify any migration, schema, table, column, index, constraint, source file, test, configuration, CI workflow, dependency, or GitHub setting; create an identifier type, a `BusinessIdentifier` subclass, or any concrete module identifier; alter `PF-042`, `PF-043`, `PF-044`, `PF-047`, `PF-048`, or `PF-049`, or design `PF-040`; define any module's physical data model or any table or column name; define which aggregates, attributes, or business identifiers exist in any context; select or endorse a UUID library, ORM, migration tool, database provider, hosting platform, or any other vendor, product, or package; define a PostgreSQL version, extension set, or server configuration; define search, full-text, or vector indexing; introduce `Money`, `Currency`, or any monetary column, or schedule or unblock `PF-045`; introduce an AI capability or modify `docs/architecture/05_AI_Architecture.md`; define an external identifier, external contract, or internal-to-external mapping, which remain `Integrations`'; claim exactly-once delivery or any ordering guarantee; define an Ethical Wall, conflict-checking, or per-user visibility mechanism in any layer; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; claim that any described property is implemented, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

**Proposed conceptual architecture only.** It authorizes no application code, schema, migration, dependency, package, infrastructure, Docker change, CI change, environment change, production configuration, or GitHub setting. **No property described here is claimed to be implemented.**

`PF-040` — AggregateRoot remains the next repository implementation story and remains **Backlog**. No story is In Progress. `PF-045` — Money and `PF-046` — Result remain Backlog and deferred from Release 0.1.

The story ID is **ARCH-012**; the architecture document it accompanies is the pre-reserved `docs/architecture/03_Database_Design.md` rather than a newly numbered file.
