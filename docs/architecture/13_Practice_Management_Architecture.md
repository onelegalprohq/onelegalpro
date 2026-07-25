# ARCH-013 — Practice Management Architecture

**Status:** Approved (conceptual architecture) — implementation stories are proposed, not scheduled; see `docs/architecture/08_Roadmap.md`.

## Purpose and scope

This document defines the conceptual domain and system architecture for OneLegalPro's Practice Management Core, implementing `docs/adr/ADR-006-Practice-Management-Core.md` and the relevant articles of `docs/architecture/01_OneLegalPro_Constitution.md`. It covers the lifecycle of Clients, Organizations, Contacts, Matters, Matter Teams, Practice Areas, Tasks, Appointments, Notes, and Activities — the shared vocabulary every other bounded context references.

This document describes **conceptual models only**. It does not define migrations, Eloquent schemas, or implementation code — those belong to future, separately approved implementation stories (see `docs/architecture/08_Roadmap.md`).

## 1. Purpose

Practice Management is the central bounded context — the platform's Core Domain, in Domain-Driven Design terms — because it owns the vocabulary every other context is built around without itself needing to know how those other contexts work:

- Communications (`docs/architecture/11_Communications_Hub_Architecture.md`) links threads to Lead, **Client, Matter, Task, Appointment**, Invoice, and Knowledge.
- Digital Presence (`docs/architecture/12_Website_Client_Portal_Architecture.md`) surfaces **Matters, Tasks, Appointments** in the Client Portal and produces `BookingRequest`s that become **Appointments**.
- Legal Intelligence (`docs/architecture/09_Legal_Intelligence_Architecture.md`) already names a firm-scoped `MatterLegalLink` — "a Firm's **Matter** referencing a legal source" — as a forward-looking concept.
- Branding, Billing, and Documents each need to know *whose* Client and *which* Matter a piece of branding, an invoice, or a file belongs to.

None of those contexts is a plausible owner of Client or Matter — each would have to duplicate the concept to function, and duplication is exactly what `docs/domain/06_Laravel_Module_Blueprint.md`'s cross-module rules and every architecture so far (`docs/architecture/10_White_Label_Platform_Architecture.md` through `docs/architecture/12_Website_Client_Portal_Architecture.md`) have been designed to prevent. Practice Management exists so that "what is a Matter, who is on it, and what has happened on it" has exactly one owner, and every other bounded context is a Supporting or Generic subdomain around this Core Domain, per Eric Evans' Core Domain terminology — never the reverse.

**This resolves a dependency named, but not yet defined, in two earlier documents**: `docs/architecture/11_Communications_Hub_Architecture.md` §10 (Matter Integration) and `docs/architecture/12_Website_Client_Portal_Architecture.md` §7 (Booking System, Unresolved implementation choice) both named "a future Practice Management module" as an external dependency for `Matter`, `Task`, and `Appointment`. This document is that architecture.

## 2. Domain Model

**Aggregates** (see Aggregates, entities, and value objects for full detail)

- `Client` — an Individual, Corporate, Government, Non-profit, or Foreign Entity that has (or had) an engagement relationship with the Firm.
- `Organization` — any organizational party the Firm has data about: a Client's corporate entity, an opposing party's organization, a related third party, a court, or a government body. Not every `Organization` is a `Client`.
- `Contact` — an individual person, independently identified because the same person can be linked to more than one `Client`/`Organization` over time and in different roles (a `Client`'s CFO today, an opposing party's contact tomorrow).
- `Matter` — the central aggregate; the unit of legal work, professional responsibility, conflicts, and billing. Owns `MatterTeam` and `MatterClient` (entities) — a `Matter` references one or more `Client`s via `MatterClient` (see Matter Clients, below), never exactly one.
- `Task`, `Appointment`, `Note` — independent aggregates that reference a `Matter` (usually) and a `Client` by identifier; not entities owned inside `Matter` (see Aggregates, entities, and value objects for why).
- `EthicalWall` — the aggregate governing restricted access to a `Matter` (see Ethical Walls).
- `ConflictRelationship` — an independent, Firm-scoped aggregate root recording a conflict-relevant relationship between two `PartyReference`s, referencing an optional source `Matter` or disclosure event (see Conflict Checking).

**Reference data**

- `PracticeArea` — a taxonomy entry (Civil, Criminal, Corporate, Employment, Family, Immigration, Tax, IP, Real Estate, Litigation, Arbitration, Bankruptcy, or Firm-defined custom), platform-seeded with room for firm-scoped extension (see Practice Areas).

**Read-models (not stored aggregates)**

- `Matter Timeline` — the chronological, cross-context history of a `Matter` (see Matter Timeline, below).
- `Matter Dashboard` — the lawyer's assembled workspace for a `Matter` (see Matter Dashboard, below).

**Relationships and ownership, at a glance**

| Concept | Owned by | References |
|---|---|---|
| `Client` | Practice Management | zero or more `Contact`, zero or more `Organization` (corporate structure), zero or more `Matter` (via `MatterClient`) |
| `Organization` | Practice Management | zero or more `Contact` |
| `Contact` | Practice Management | zero or more `Client`/`Organization`, via a role-carrying relationship |
| `Matter` | Practice Management | one or more `Client`, each via a `MatterClient` entity; one `PracticeArea`; one `MatterTeam` |
| `MatterClient` | Owned by `Matter` (entity) | one `Client` identifier, carries a `MatterClientRole` |
| `MatterTeam` | Owned by `Matter` (entity) | one or more staff Actor identities (owned by a future Identity capability — see Integration Boundaries) |
| `Task`, `Appointment`, `Note` | Practice Management, each its own aggregate | optional `Matter`, required `Client` for client-related items |
| `EthicalWall` | Practice Management | exactly one `Matter` |
| `ConflictRelationship` | Practice Management (independent, Firm-scoped aggregate) | two `PartyReference` value objects; optional source `Matter` or disclosure event |
| `PracticeArea` | Practice Management (platform-seeded + firm-scoped custom) | referenced by `Matter` |

Every relationship above is a reference by identifier, never a shared Eloquent record or an embedded copy — the same discipline `docs/domain/06_Laravel_Module_Blueprint.md` requires between any two modules, applied here between Practice Management's own aggregates.

## 3. Matter Lifecycle

```text
Prospective → Opened → Active ⇄ Paused / Awaiting Client / Awaiting Court → Closed → Archived
Prospective, Opened → Cancelled (terminal)
```

- **Prospective** — a candidate matter, typically created from a qualified Lead or consultation once Communications/Digital Presence hands off (Purpose, above); no `MatterNumber` yet, no formal engagement.
- **Opened** — administrative creation: conflict check has passed or been explicitly overridden with recorded justification, a `MatterNumber` is assigned (immutable from this point on — see Matter Numbering), at least a Responsible Lawyer is assigned to the `MatterTeam`, and exactly one `MatterClient` holds the administrative Primary Client role (Matter Clients, below).
- **Active** — work is underway. Some Firms treat Opened and Active as effectively simultaneous; both are modeled distinctly so Firms that separate "matter administratively created" from "work has formally begun" can represent that gap.
- **Paused / Awaiting Client / Awaiting Court** — substates of "not actively progressing," each entered and exited with a recorded reason, always returning to Active once the blocking condition clears.
- **Closed** — the Firm's own work has concluded. Other contexts (Billing, Documents) may still have trailing actions (final invoice, document retention) but those are their own lifecycles, not gates on Practice Management's Closed transition.
- **Archived** — retention-driven, well after Closed, for records-management purposes; an Archived `Matter` remains queryable but read-only.
- **Cancelled** — terminal, reachable only from Prospective or Opened: the matter never proceeded (conflict found, client withdrew, engagement declined).

**Rules**

- Every transition is an explicit, human-authorized command producing a `MatterStatusChanged` event — never inferred from other activity (a document upload does not imply "Active").
- `MatterNumber` is immutable once assigned at Opened, regardless of any later status change (Matter Numbering).
- Status changes are never performed by AI without explicit human authorization (AI Rules).
- Reaching Opened requires a conflict check outcome to exist (clear, or overridden with justification) — a `Matter` cannot silently skip Conflict Checking.
- A `Matter` must have an administrative Primary `MatterClient` to reach Opened, and must never be left without any `Client` or without an administrative Primary Client at any point after Opened (Matter Clients, below).

## 4. Matter Numbering

Every `Matter` carries a `MatterNumber` that is:

- **Firm-configurable** — each Firm defines its own `MatterNumberingScheme` (for example, sequential, year-prefixed, practice-area-prefixed, client-code-prefixed, or a custom pattern).
- **Human-readable** — meaningful to lawyers and staff, not an opaque internal identifier (the `Matter` aggregate's own UUIDv7 identity remains the system-level identifier; `MatterNumber` is the human-facing one).
- **Unique** — enforced per Firm at assignment time; concurrent `Matter` creation must not produce a collision.
- **Immutable** — once assigned at the Opened transition, a `MatterNumber` never changes, even if the Firm later changes its numbering scheme (a scheme change applies prospectively, to new Matters only) or reorganizes practice areas.
- **Multi-scheme** — a Firm may support more than one numbering scheme in parallel (for example, a distinct scheme for litigation versus transactional matters), selected per Matter at Opened time based on `PracticeArea` or another firm-configured rule.

## 5. Practice Areas

Architecture only — no fixed enum. `PracticeArea` is a taxonomy with a platform-seeded default set:

Civil, Criminal, Corporate, Employment, Family, Immigration, Tax, IP, Real Estate, Litigation, Arbitration, Bankruptcy

plus **Firm-defined custom practice areas**, following the same "platform-seeded default, firm-scoped extension" shape already established for Legal Intelligence's `Jurisdiction` (`docs/architecture/09_Legal_Intelligence_Architecture.md`), Branding's theme token schema, and Communications' channel capability schema. A Firm is never limited to the seeded list, and a custom practice area is first-class, not a lesser "other" bucket.

## 6. Client Model

`Client` supports:

- Individual
- Corporate
- Government
- Non-profit
- Foreign Entity

as a `ClientType` value object, not a subtype hierarchy — a single `Client` aggregate shape accommodates all five, since the differences are attributes (registration details, entity structure), not fundamentally different lifecycles.

- **Relationships** — a `Client` may reference one or more `Organization` records (its own corporate structure or affiliates) and is linked to `Contact` records via role-carrying relationships (for example, "CFO," "authorized signatory," "primary contact").
- **Multiple contacts** — a `Client` is not limited to one `Contact`; `Contact` is its own aggregate precisely so the same person can be linked from more than one `Client` or `Organization` without duplication.
- **Multiple matters** — a `Client` may have any number of `Matter`s over time, and a `Matter` may in turn involve more than one `Client` (Matter Clients, below); `Matter` references `Client` by identifier via `MatterClient`, never the reverse ownership, and never an embedded or copied `Client` record.
- **Conflict relationships** — a `Client` participates in the `ConflictRelationship` model (Conflict Checking, below) the same way any other party does; being a current Client does not exempt a party from conflict analysis for a new, unrelated matter.

## Matter Clients

A `Matter` references one or more `Client`s, never exactly one — joint representation and co-client matters are common in practice (for example, spouses in an estate matter, or co-defendants sharing counsel). `MatterClient` is an entity owned by the `Matter` aggregate, referencing a `Client` by identifier and carrying a `MatterClientRole`:

- **Primary Client** — the administrative point of contact and engagement-letter/billing party of record for the Matter.
- **Joint/Co-client** — a `Client` jointly represented on the same Matter.

**"Primary" is a purely administrative designation** — who receives default correspondence and whose engagement letter is of record. It does not imply that a Joint/Co-client has lesser legal status, weaker confidentiality or privilege protections, or reduced professional-responsibility duties owed by the Firm. Every `MatterClient`, regardless of role, is a full client of the Firm for that Matter.

**Rules**

- A `Matter` must reference at least one `Client` via `MatterClient`, and must never be left without any `Client` or without an administrative Primary Client, at any point after it reaches Opened (Matter Lifecycle, above).
- Reaching Opened requires exactly one `MatterClient` to hold the Primary Client role.
- Removing the sole Primary Client (for example, a co-client's engagement ending) requires designating a new Primary Client from the remaining `MatterClient`s in the same operation — a Matter can never be left without one.
- Adding a `MatterClient`, changing its role, or removing it are recorded operations producing `MatterClientAdded`, `MatterClientRoleChanged`, or `MatterClientRemoved` events respectively (Aggregates, entities, and value objects, below) — never a silent field update.
- `MatterClient` references a `Client` by identifier only; the `Client` aggregate is never embedded or copied into `Matter` (Relationships and ownership, above).
- Conflict Checking (below) evaluates every `Client` attached to the Matter via `MatterClient` — Primary and Joint/Co-client alike — and every other related party, never only the Primary Client.

## 7. Matter Teams

`MatterTeam` is an entity owned by `Matter`, composed of role-carrying `TeamAssignment` entries:

- Responsible Lawyer
- Lead Lawyer
- Supporting Lawyers
- Paralegals
- Assistants
- External Counsel

Each `TeamAssignment` references a staff Actor identity (owned by a future Identity/staff-directory capability — see Integration Boundaries) and carries the role plus role-derived permissions (for example, Responsible Lawyer can change Matter status; a Supporting Lawyer may not). Role-based responsibility, not seniority alone, determines what a `TeamAssignment` may authorize — an External Counsel entry, for instance, is explicitly a narrower-permission role by default, reflecting that they are not Firm staff.

## 8. Ethical Walls

`EthicalWall` is the aggregate governing restricted access to a `Matter`:

- **Restricted matters** — a `Matter` may be flagged as walled, at which point default team/staff visibility no longer applies.
- **Need-to-know access** — an explicit allow-list of Actor identities who may access a walled `Matter` and everything that references it (its `Task`s, `Appointment`s, `Note`s, and — via Matter Timeline — indirectly gated visibility into cross-context activity too).
- **Auditing** — every access attempt against a walled `Matter`, granted or denied, is an auditable event; walls are enforced, not merely documented.
- **Emergency override** — a narrow, exceptional path for access outside the allow-list, requiring an explicit recorded justification and producing its own distinctly auditable event (`EthicalWallEmergencyOverride`), never a silent bypass.

See Why Ethical Walls belong here (in `docs/adr/ADR-006-Practice-Management-Core.md`) for why this authorization decision is centralized in Practice Management rather than reimplemented per consuming module.

## 9. Tasks

`Task` is its own aggregate (not an entity of `Matter`), referencing a `Matter` optionally and a responsible Actor identity, with:

- **Dependencies** — a `Task` may depend on one or more other `Task`s completing first; dependency structure is explicit, not inferred from due dates.
- **Deadlines** — a due date/time, distinct from any court- or statute-driven deadline that may originate in Legal Intelligence or a Matter's own facts (Practice Management models the `Task` deadline; it does not compute statutory deadlines itself).
- **Priority** — an explicit priority level, not derived solely from proximity to deadline.
- **Assignments** — one responsible Actor per `Task` (reassignment is a recorded event, not a silent field update).
- **Recurring tasks** — modeled as a `RecurrenceRule` on a task template that spawns a new, independent `Task` instance on each occurrence, rather than one mutable `Task` object cycling through states — consistent with this platform's general preference for new records over in-place history-erasing mutation (the same reasoning Communications applies to message "edits," `docs/architecture/11_Communications_Hub_Architecture.md` §9).

## 10. Appointments

`Appointment` is its own aggregate, covering:

- Court
- Meeting
- Consultation
- Internal
- Video
- Travel

as an `AppointmentType`/`Modality` value object (Office / Video / Phone / Travel / Court, extending the Modality concept `docs/architecture/12_Website_Client_Portal_Architecture.md` §7 already defines for Booking). Every `Appointment` is **timezone-aware** (`TimeSlot` carries an explicit timezone, not an implicit server-local assumption), matching Digital Presence's Booking System.

**This is the same aggregate Digital Presence's `BookingRequest` produces or updates on confirmation** — resolving the "unresolved implementation choice" `docs/architecture/12_Website_Client_Portal_Architecture.md` §7 named: a confirmed `BookingRequest` issues a command into Practice Management, which creates or updates the corresponding `Appointment`; `BookingRequest` and `Appointment` remain distinct aggregates in distinct bounded contexts, connected only through that published command.

## 11. Notes

`Note` is its own aggregate, with a `NoteVisibility` value object distinguishing exactly three mutually exclusive states — who may see the Note, and nothing else:

- Internal (staff-only)
- Client-visible (surfaced through the Client Portal, `docs/architecture/12_Website_Client_Portal_Architecture.md` §4)
- Private (visible only to its author, distinct from Internal's staff-wide visibility)

Two further properties apply independently of `NoteVisibility`, not as visibility values:

- **AI provenance** — a `Note` optionally carries `AIAnnotation`-style provenance (model/version, timestamp, confidence — the same discipline `docs/architecture/05_AI_Architecture.md` requires of AI output elsewhere) when it is AI-generated. This is metadata about authorship, not a visibility state: an AI-generated `Note` can be Internal, Client-visible, or Private.
- **Pinned** — an independent state on `Note`, set or cleared by a distinct `PinNote` operation (Proposed module structure, below), surfacing the Note prominently on the Matter Dashboard. Pinning never changes a Note's `NoteVisibility`.

A `Note` can be simultaneously AI-generated, pinned, and Internal, Client-visible, or Private — these are three independent dimensions, not one combined enumeration.

A `Note`'s content is **immutable once recorded** — the same immutable-audit discipline `docs/architecture/11_Communications_Hub_Architecture.md` applies to `Message`. A correction is a new `Note` referencing the one it supersedes, never an in-place edit.

## 12. Activity Timeline

See Matter Timeline (dedicated section, below) for the full model — this section names what it must include:

- Communications (from `docs/architecture/11_Communications_Hub_Architecture.md`)
- Documents (from a future Documents bounded context)
- Billing (from a future Billing bounded context)
- AI (summaries, suggestions, and any other AI activity touching the Matter)
- Appointments (this module's own `Appointment` aggregate)
- Tasks (this module's own `Task` aggregate)
- Portal activity (from `docs/architecture/12_Website_Client_Portal_Architecture.md`'s Client Portal)

## 13. Integration Boundaries

Practice Management integrates with, but never absorbs ownership of:

- **Communications Hub** (`docs/architecture/11_Communications_Hub_Architecture.md`) — `CommunicationThread`/`Message` link to `Client`/`Matter`/`Task`/`Appointment` via `CommunicationLink`; Practice Management is the linked-to party, never the owner of communication content.
- **Legal Intelligence** (`docs/architecture/09_Legal_Intelligence_Architecture.md`) — a `Matter` may reference a `LegalSource` via the firm-scoped `MatterLegalLink` that document already anticipated; Practice Management never owns or copies platform-global legal-source content.
- **Website / Client Portal** (`docs/architecture/12_Website_Client_Portal_Architecture.md`) — Digital Presence reads `Matter`/`Task`/`Appointment`/`Client` through Practice Management's published queries for the Client Portal and produces `Appointment`s via confirmed `BookingRequest`s; Practice Management never renders portal UI or owns branding/domain concerns.
- **Billing** (future bounded context) — Invoices reference `Matter`/`Client` by identifier; Practice Management never owns invoices, payments, or billing rates.
- **Documents** (future bounded context) — Documents reference `Matter`/`Client` by identifier; Practice Management never stores or versions document content.
- **Identity** (not yet architected) — staff Actor identity (who a Responsible Lawyer, Paralegal, or Assistant *is*, and their platform-wide login/RBAC) is an external, not-yet-architected dependency; `MatterTeam` `TeamAssignment` entries reference an Actor identifier without owning identity/authentication itself.

**No duplicated ownership** — in either direction. Practice Management never copies Communications/Documents/Billing/Legal Intelligence/Branding data into its own schema, and no other context copies `Client`/`Matter`/`Task`/`Appointment`/`Note` data into its own. Every cross-context reference is a published query, command, or event, per `docs/domain/06_Laravel_Module_Blueprint.md`.

## 14. Security

- **Permissions** — role-derived from `MatterTeam` `TeamAssignment` plus firm-wide staff roles; a `Task`, `Appointment`, or `Note` referencing a `Matter` inherits that `Matter`'s access rules.
- **Ethical Walls** — see Ethical Walls, above; enforced centrally, checked by every consuming context before revealing Matter-linked data.
- **Tenant isolation** — every Practice Management aggregate is firm-scoped, isolated by `FirmContext`, enforced at the application, repository, and database-policy layers, never by global scopes alone.
- **Auditing** — every lifecycle transition (`Matter`, `Task`, `Appointment`), team assignment/removal, conflict override, and Ethical Wall event is an auditable domain event.
- **Retention** — Matter and Client records are typically subject to long or professional-conduct-mandated retention periods, which may exceed a Firm's general data-retention default; retention policy is jurisdiction-aware (Thailand-first, per `docs/architecture/01_OneLegalPro_Constitution.md`, Article 1), not a single hardcoded platform value.

## 15. Privacy

- **PDPA / GDPR** — `Client` and `Contact` records are personal data; regional privacy regimes apply consistent with the platform's Thailand-first posture and the privacy models already established in `docs/architecture/11_Communications_Hub_Architecture.md` §12 and `docs/architecture/12_Website_Client_Portal_Architecture.md` §14.
- **Data export** — a Client's Practice Management data (their `Matter`s, `Task`s, `Appointment`s, Client-visible `Note`s) is exportable to support a data-subject access request.
- **Deletion** — a deletion request is honored subject to Legal retention (below); Practice Management does not silently purge records a professional-conduct or statutory rule requires retaining.
- **Legal retention** — Matter records frequently fall under statutory or professional-conduct retention obligations (limitation periods, regulator record-keeping rules) that override an ordinary deletion request, the same override relationship `docs/architecture/11_Communications_Hub_Architecture.md` §11 establishes for legal hold over communications retention policy.

## 16. API Architecture

- **Published contracts** — every cross-context interaction with Practice Management (Communications' `CommunicationLink`, Digital Presence's Client Portal reads and `BookingRequest` confirmation, a future Billing/Documents module's `Matter`/`Client` references) goes through published commands, queries, and events, never direct database coupling.
- **Queries** — for example, `GetMatterSummary`, `GetClientMatters`, `CheckEthicalWallAccess`, `SearchConflicts` (Conflict Checking).
- **Commands** — for example, `CreateProspectiveMatter`, `OpenMatter`, `ChangeMatterStatus`, `AddMatterClient`, `AssignMatterTeamMember`, `CreateTask`, `CompleteTask`, `ScheduleAppointment`, `AddNote`, `PinNote`, `EstablishEthicalWall`.
- **Events** — see Aggregates, entities, and value objects for the full list; every meaningful state change is published for the Matter Timeline and for other contexts' own projections to consume.
- **No direct database coupling** — no other module may query or write Practice Management's Eloquent records directly, and Practice Management may not do so to any other module's records, per `docs/domain/06_Laravel_Module_Blueprint.md`.

## 17. Future Expansion

- **Multi-office firms** — `Matter`/`Client` can carry an office affiliation as additive metadata, without redesigning either aggregate.
- **International matters** — `Matter` can adopt Legal Intelligence's `Jurisdiction` value object (`docs/architecture/09_Legal_Intelligence_Architecture.md`) to represent cross-border matters, reusing an existing concept rather than inventing a parallel one.
- **Court integrations** — external court system references attach to `Matter`/`Appointment` as additive fields, complementing the Court integrations already anticipated as future work in `docs/architecture/11_Communications_Hub_Architecture.md` (court hearing assistant) and `docs/architecture/12_Website_Client_Portal_Architecture.md` (court portals).
- **Workflow automation** — `Task` dependencies and `RecurrenceRule` are already extensible primitives a future workflow-automation capability can build on rather than replace.
- **Matter templates** — `PracticeArea`-scoped default `Task`/Timeline templates, layered on top of existing `CreateTask`/`ScheduleAppointment` commands.
- **Knowledge graph** — `ConflictRelationship`'s party-relationship graph (Conflict Checking) generalizes naturally into a broader relationship/knowledge graph without redesign.

## 18. Failure Modes

Conceptual failure modes the architecture must account for (mitigations are implementation-story-level detail):

- **Duplicate matters** — mitigated by `MatterNumber` uniqueness enforcement at assignment time and duplicate-detection heuristics at `Matter` creation (implementation-level detail); a collision must be rejected, never silently resolved by appending a suffix.
- **Conflicts** — a missing or failed conflict check must block the Prospective → Opened transition, or require an explicit, recorded override — never silently allow Opened without a recorded conflict-check outcome.
- **Permission failures** — deny by default; an authorization check that cannot positively confirm access (Ethical Wall check unavailable, role ambiguous) must fail closed, never open.
- **Timeline inconsistencies** — the Matter Timeline is an eventually consistent read-model (Matter Timeline, below); a delayed or out-of-order event from another context must not corrupt the Timeline, only appear late or be reconciled, never silently dropped without trace.
- **Relationship corruption** — an incorrect `Contact`-to-`Organization` or `ConflictRelationship` link is corrected by recording a new, corrected relationship, never by silently overwriting the erroneous one, preserving the audit trail.
- **Integration failures** — a dependent context being unavailable (Documents, Billing, Communications) must not block core `Matter`/`Task`/`Appointment` operations; the Matter Dashboard degrades gracefully (showing stale or partial data with an explicit staleness indicator) rather than failing entirely, the same reasoning `docs/architecture/12_Website_Client_Portal_Architecture.md` §18 applies to Portal downtime.

## Matter Timeline

Every significant event across the platform that relates to a `Matter` contributes to **one chronological matter history** — the Matter Timeline. It is a **read-model projection**, not owned, stored history inside the `Matter` aggregate: Communications, Documents, Billing, and AI activity are owned by their respective bounded contexts (Integration Boundaries, above), so the Timeline is assembled from their published events and queries, the same "read-model, not a second source of truth" pattern `docs/architecture/11_Communications_Hub_Architecture.md`'s Communication Inbox and `docs/architecture/12_Website_Client_Portal_Architecture.md`'s Client Portal dashboard already establish. This is now the third instance of that pattern in this platform's architecture, and deliberately so: it is how a Core Domain composes activity from Supporting subdomains without absorbing their ownership.

An `Activity` entry in the Timeline is a lightweight, append-only record — "something happened, of this type, at this time, referencing this Matter" — not a duplicate copy of the underlying record's full content. Selecting an `Activity` entry navigates to the owning context's own detail (a `Message` in Communications, a document version in Documents, an invoice in Billing) rather than the Timeline holding a stale copy of it.

## Conflict Checking

**Architecture only — this section does not specify a matching algorithm and must not be read as one.** The conceptual model must support checking:

- Clients
- Opposing parties
- Related parties
- Organizations
- Lawyers (whether a `MatterTeam` assignment creates a conflict, including from a lawyer's history prior to joining the Firm)
- Historical relationships

**Model.** A `PartyReference` value object identifies a party for conflict-checking purposes — a name, a role (Client / Opposing Party / Related Party / Organization / Lawyer), and an optional link to an actual `Client`, `Contact`, or `Organization` record where one exists. Not every party relevant to a conflict check has a full record: an opposing party in a matter the Firm never took on may exist only as a `PartyReference`. For a `Matter` with more than one `Client` (Matter Clients, above), conflict checking evaluates every attached `Client` — Primary and Joint/Co-client alike — plus every other related party; it is never limited to the Primary Client.

`ConflictRelationship` is an **independent, Firm-scoped aggregate root** — not an entity owned by `Matter` or by any other aggregate — connecting two `PartyReference`s with a relationship type (for example, `Opposed`, `Related`, `Represents`, `FormerClient`, `PriorEmployment`) and a source `Matter` or disclosure event, plus the date recorded. Its own consistency and lifecycle boundary is exactly one recorded relationship between two parties; it does not belong to, and is not loaded as part of, the `Matter` or `Client` aggregate it may reference by identifier. `SearchConflicts` (API Architecture, above) is a published query that traverses this relationship data — conceptually a graph traversal, and a natural precursor to the Knowledge graph named in Future Expansion — to surface potential conflicts for a proposed new `Matter`, new `Client`, or new `MatterTeam` assignment. Conflict matching and scoring algorithms remain explicitly out of scope of this aggregate's definition, per the architecture-only limitation above.

**Explicit limitation.** Completeness depends on what has been disclosed and recorded, particularly for a lawyer's history prior to joining the Firm — this is an inherent limitation of any conflict system, not a gap unique to this design, and this document does not claim otherwise. Exact matching/scoring logic (fuzzy name matching, similarity thresholds) is implementation-story-level detail, deliberately unresolved here, consistent with "architecture only, do not implement."

**Future extensibility.** The `PartyReference`/`ConflictRelationship` model is designed to extend into the broader Knowledge graph (Future Expansion) without redesign — a conflict-relationship graph is a specialization of a general party-relationship graph, not a separate model that would need replacing later.

## Matter Dashboard

The **Matter Dashboard** is the lawyer's primary workspace for a given `Matter` — the same "single assembled workspace replaces fragmented tools" pattern `docs/architecture/11_Communications_Hub_Architecture.md`'s Communication Inbox and `docs/architecture/12_Website_Client_Portal_Architecture.md`'s Client Portal establish, applied here at the level of one Matter rather than one inbox or one client relationship:

```text
Matter Dashboard — [Matter Number] [Client Name]
  • Tasks              — open/overdue tasks for this Matter, by priority
  • Timeline            — the Matter Timeline (chronological, cross-context)
  • Communications      — recent CommunicationThread activity linked to this Matter
  • Documents           — recent document activity (from the Documents context)
  • Billing              — outstanding invoices / recent billing activity (from the Billing context)
  • AI summaries         — AI-produced Matter/Note/Communication summaries, provenance-tagged
  • Deadlines            — upcoming Task and statutory/court deadlines
  • Appointments          — upcoming Appointments linked to this Matter
```

Like the Timeline it displays, the Dashboard is an assembled read-model over Practice Management's own aggregates (`Task`, `Appointment`, `Note`) and other contexts' published queries (Communications, Documents, Billing, AI) — it does not require those contexts to write into Practice Management's schema, and Practice Management does not need to understand how any of them work internally to display their activity.

## AI Rules

AI capability touching Practice Management is bound by `docs/architecture/01_OneLegalPro_Constitution.md`, Article 6, and extends into explicit, enumerated permissions and prohibitions here:

**AI may:**

- Summarize (a `Matter`, `Note`, or linked `Communication`)
- Suggest tasks
- Suggest timelines
- Suggest practice area
- Suggest lawyer (for a `MatterTeam` assignment)
- Suggest deadlines

**AI may never, without explicit human authorization:**

- Change matter status
- Assign lawyers
- Close matters
- Override Ethical Walls

Every AI suggestion in the "may" list is presented as a proposal a human acts on — the same draft-and-suggest posture `docs/architecture/11_Communications_Hub_Architecture.md`'s AI Rules establish for Communications — and carries `AIAnnotation`-style provenance (model/version, timestamp, confidence). The "never" list is deliberately absolute: these four actions carry professional-responsibility consequences (an Ethical Wall exists to prevent exactly the kind of access an AI override could silently create) that place them outside AI's advisory role entirely, not merely subject to a lower confidence threshold.

## Aggregates, entities, and value objects

**Aggregates**

- `Client` — `ClientType` (Individual / Corporate / Government / Non-profit / Foreign Entity), profile data, references to `Organization`/`Contact` relationships.
- `Organization` — organizational party data; may or may not be linked to a `Client`.
- `Contact` — an individual person; linked to `Client`/`Organization` via role-carrying relationships.
- `Matter` — the central aggregate: `MatterNumber`, `MatterStatus`, `PracticeArea` reference, owns `MatterTeam` and `MatterClient` (entities) — one or more `Client` references via `MatterClient`, never exactly one.
- `Task` — independent aggregate; optional `Matter` reference, `TaskPriority`, `RecurrenceRule`, dependency references to other `Task`s.
- `Appointment` — independent aggregate; optional `Matter` reference, `Modality`, timezone-aware `TimeSlot`.
- `Note` — independent aggregate; optional `Matter` reference, `NoteVisibility` (Internal / Client-visible / Private), independent `Pinned` state, optional `AIAnnotation` provenance, immutable content.
- `EthicalWall` — independent aggregate; exactly one `Matter` reference, allow-list of Actor identifiers.
- `ConflictRelationship` — independent, Firm-scoped aggregate root; connects two `PartyReference`s, with an optional source `Matter` or disclosure event reference, a relationship type, and a recorded date (Conflict Checking).

**Entities** (owned by an aggregate, identity matters, mutable within the aggregate's lifecycle rules)

- `MatterTeam` — owned by `Matter`; composed of `TeamAssignment` entries.
- `TeamAssignment` — owned by `MatterTeam`; references an Actor identifier, carries `TeamRole` and role-derived permissions.
- `MatterClient` — owned by `Matter`; references a `Client` identifier, carries `MatterClientRole` (Matter Clients, above).

**Value objects** (immutable, no identity)

- `ClientType`, `MatterStatus`, `TeamRole`, `TaskPriority`, `NoteVisibility`, `AppointmentType`/`Modality`, `MatterClientRole` (Primary Client / Joint-Co-client).
- `MatterNumber`, `MatterNumberingScheme` — see Matter Numbering.
- `PracticeArea` reference (the taxonomy entry a `Matter` points to; the taxonomy itself is reference data — see Practice Areas).
- `TimeSlot` (timezone-aware).
- `RecurrenceRule` — governs `Task` template recurrence.
- `PartyReference` — a name, role, and optional link to a `Client`/`Contact`/`Organization`, used in Conflict Checking.
- `AIAnnotation` — provenance on any AI-produced suggestion/summary or Note (model/version, timestamp, confidence), reusing the shape `docs/architecture/11_Communications_Hub_Architecture.md` already defines.

**Events** (past tense, per `docs/domain/06_Laravel_Module_Blueprint.md` naming convention)

`ClientRegistered`, `ContactAdded`, `OrganizationRegistered`, `ProspectiveMatterCreated`, `MatterOpened`, `MatterNumberAssigned`, `MatterActivated`, `MatterPaused`, `MatterResumed`, `MatterClosed`, `MatterArchived`, `MatterCancelled`, `MatterStatusChanged`, `MatterClientAdded`, `MatterClientRoleChanged`, `MatterClientRemoved`, `MatterTeamMemberAssigned`, `MatterTeamMemberRemoved`, `TaskCreated`, `TaskAssigned`, `TaskCompleted`, `TaskOverdue`, `AppointmentScheduled`, `AppointmentConfirmed`, `AppointmentRescheduled`, `AppointmentCancelled`, `AppointmentNoShow`, `NoteAdded`, `NotePinned`, `NoteUnpinned`, `ConflictCheckRequested`, `ConflictFlagged`, `ConflictCheckOverridden`, `ConflictRelationshipRecorded`, `EthicalWallEstablished`, `EthicalWallAccessGranted`, `EthicalWallAccessDenied`, `EthicalWallEmergencyOverride`, `EthicalWallLifted`.

**Lifecycle and state transitions**

- `Matter`: see Matter Lifecycle, above.
- `MatterClient`: `Added` with a `MatterClientRole` → role may change (`MatterClientRoleChanged`) while attached → `Removed`; removing the sole Primary Client requires assigning a new one in the same operation (Matter Clients, above).
- `Task`: `Open` → `InProgress` → `Completed` / `Cancelled`. A recurring task's next occurrence is a new `Task` instance, not a state the same instance cycles back through.
- `Appointment`: `Requested`/`Scheduled` → `Confirmed` → `Completed` / `Cancelled` / `NoShow` — the same shape `docs/architecture/12_Website_Client_Portal_Architecture.md`'s `BookingRequest` uses, since a confirmed `BookingRequest` produces this transition.
- `Note`: recorded once, immutable; `Pinned` toggles independently of content and of `NoteVisibility` via `PinNote`/unpin (Notes, above). A correction is a new `Note` referencing the one it supersedes.
- `EthicalWall`: `Established` → `Active` (allow-list may be modified while Active) → `Lifted`.
- `ConflictRelationship`: recorded once with a relationship type and date; a correction is a new `ConflictRelationship` recording the corrected relationship, never an in-place edit (Failure Modes, Relationship corruption).

## Proposed module structure

**Unresolved implementation choice:** exact module name. This document proposes `PracticeManagement`, consistent with the module-per-bounded-context pattern already established for `LegalIntelligence`, `Branding`, `Communications`, and `DigitalPresence`.

```text
PracticeManagement/
├── Application/
│   ├── Clients/           (RegisterClient, UpdateClientProfile, AddContact, LinkOrganization, ...)
│   ├── Matters/            (CreateProspectiveMatter, OpenMatter, ChangeMatterStatus,
│   │                         AssignMatterNumber, CloseMatter, ArchiveMatter, CancelMatter, ...)
│   ├── MatterTeams/         (AssignTeamMember, RemoveTeamMember, ChangeTeamRole, ...)
│   ├── MatterClients/       (AddMatterClient, ChangeMatterClientRole, RemoveMatterClient, ...)
│   ├── Tasks/               (CreateTask, AssignTask, CompleteTask, DefineRecurringTask, ...)
│   ├── Appointments/        (ScheduleAppointment, ConfirmAppointment, RescheduleAppointment,
│   │                          CancelAppointment, ...)
│   ├── Notes/               (AddNote, PinNote, UnpinNote, ...)
│   ├── Conflicts/           (RequestConflictCheck, RecordConflictRelationship,
│   │                          OverrideConflictFlag, ...)
│   └── EthicalWalls/        (EstablishEthicalWall, GrantNeedToKnowAccess,
│                              EmergencyOverrideAccess, LiftEthicalWall, ...)
├── Domain/
│   ├── Client, Organization, Contact         (aggregate roots)
│   ├── Matter                                (aggregate root — the central aggregate)
│   ├── Task, Appointment, Note               (aggregate roots)
│   ├── EthicalWall                           (aggregate root)
│   ├── ConflictRelationship                  (aggregate root, Firm-scoped — see Conflict Checking)
│   ├── MatterTeam, TeamAssignment, MatterClient   (entities)
│   ├── PracticeArea                          (reference-data entity)
│   ├── ClientType, MatterStatus, TeamRole, TaskPriority, NoteVisibility,
│   │   AppointmentType/Modality, MatterClientRole, MatterNumber, MatterNumberingScheme,
│   │   TimeSlot, RecurrenceRule, PartyReference, AIAnnotation   (value objects)
├── Infrastructure/         (Eloquent adapters, numbering-sequence generator,
│                             conflict-search index adapter)
├── Interface/               (Matter Dashboard read model, Matter Timeline read model,
│                             published queries/commands API)
├── Database/                (new migrations only — no historical migrations touched)
├── Routes/
├── Tests/
├── Config/                  (default practice-area taxonomy, default numbering scheme,
│                             conflict-check policy)
├── ModuleServiceProvider.php
└── README.md
```

Dependency direction and cross-module rules follow `docs/domain/06_Laravel_Module_Blueprint.md` unchanged: Interface → Application → Domain; Infrastructure depends on Application/Domain contracts; Domain never depends on Laravel/Eloquent/HTTP; every other module reaches Practice Management data only through published queries/commands, and Practice Management itself reaches Communications, Legal Intelligence, and future Billing/Documents contexts only through their published contracts, never through Eloquent records directly.

## Ownership boundary

Practice Management is the fourth module — after `Branding`, `Communications`, and `DigitalPresence` — to take a wholly firm-scoped shape, with only the `PracticeArea` taxonomy schema and default numbering-scheme templates as platform-global, static configuration:

| Subdomain | Scope | Ownership boundary |
|---|---|---|
| `PracticeArea` seeded taxonomy, default `MatterNumberingScheme` templates | Platform-global | Not `FirmContext` — static configuration shared by every Firm |
| `Client`, `Organization`, `Contact`, `Matter`, `MatterClient`, `MatterTeam`, `Task`, `Appointment`, `Note`, `EthicalWall`, `ConflictRelationship`, firm-custom `PracticeArea` entries | Firm-scoped | `FirmContext` |

This is now the recurring shape for the platform's client/firm-facing product surfaces — the exception remains Legal Intelligence's platform-global legal-source content (`docs/architecture/09_Legal_Intelligence_Architecture.md`), which is genuinely shared reference data rather than a schema every Firm fills in independently.

## Access-control boundaries

- `Matter` status transitions and `MatterTeam` assignment require Responsible-Lawyer-or-above authorization by default (Matter Teams).
- `EthicalWall`-governed `Matter`s (and their referencing `Task`/`Appointment`/`Note`) are accessible only to allow-listed Actors, checked via the published `CheckEthicalWallAccess` query by every consuming context, never re-implemented per context.
- Firm-scoped isolation is enforced at the application, repository, and database-policy layers, never by global scopes alone, per `docs/domain/06_Laravel_Module_Blueprint.md`.
- Any write path that could let AI change `Matter` status, assign a lawyer, close a `Matter`, or override an `EthicalWall` without explicit human authorization is a defect, per AI Rules, and requires the same elevated approval discipline as other high-impact changes in `AGENTS.md`.

## Phased implementation guidance

See `docs/architecture/08_Roadmap.md`, the proposed Practice Management Core epic, for staged delivery order (`Client`/`Organization`/`Contact` foundation → `Matter` aggregate, lifecycle, and `MatterNumber` → `PracticeArea` taxonomy → `MatterTeam` and role-based permissions → `Task`/`Appointment`/`Note` aggregates → Ethical Walls → Conflict Checking → Matter Timeline read-model → Matter Dashboard → integration hardening with Communications and Digital Presence). That staging is proposed only; formal scheduling requires entries in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` and separate story-level approval before implementation begins.
