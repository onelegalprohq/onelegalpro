# ADR-006 — Practice Management Core

## Status

Accepted. The human owner has explicitly approved this decision.

## Context

Every architecture approved so far assumes concepts it does not itself define: Communications (`docs/adr/ADR-004-Communications-Hub.md`) links threads to Lead, Client, Matter, Task, Appointment, Invoice, and Knowledge; Digital Presence (`docs/adr/ADR-005-Website-Client-Portal.md`) surfaces Matters, Tasks, and Appointments in the Client Portal and produces `BookingRequest`s that become Appointments; Legal Intelligence (`docs/adr/ADR-002-Thailand-First-Legal-Intelligence.md`) already names a firm-scoped `MatterLegalLink` as a forward-looking concept. None of those documents defines Client or Matter — each explicitly named "a future Practice Management module" as an external, unresolved dependency.

That gap cannot remain open indefinitely: it is the platform's shared vocabulary, and until it has an approved architecture, every other bounded context's integration points into it are provisional. Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, this needs an approved architecture before any Practice Management implementation work begins, and before the dependent stages already staged in `docs/architecture/08_Roadmap.md` for Communications (business-object linking) and Digital Presence (Practice Management read-surfaces, Booking System) can be implemented.

## Decision

1. **Practice Management is its own bounded context and the platform's Core Domain**, owning the lifecycle of Client, Organization, Contact, Matter, Matter Team, Practice Area, Task, Appointment, Note, and Activity. It explicitly does not own Communications, Documents, Billing, Legal Intelligence, or Branding — those remain separate bounded contexts Practice Management integrates with, never absorbs.
2. **`Matter` is the central aggregate.** It is the unit of legal work, professional responsibility, conflicts checking, and (by reference) billing — the one concept nearly every other business event exists in relation to. `Client`, `Organization`, and `Contact` are distinct aggregates from `Matter` because they have materially different identities and lifecycles (a `Client` has an engagement relationship; a `Contact` is a person who moves between organizations and roles over time), while `Task`, `Appointment`, `Note`, and `EthicalWall` are independent aggregates that *reference* `Matter` rather than living inside it, to avoid a single unbounded "god aggregate."
3. **The Matter Timeline is a read-model, composed from other bounded contexts' published events and queries, never owned, materialized history inside `Matter`.** Communications, Documents, Billing, and AI activity are owned elsewhere; the Timeline assembles their activity without duplicating their data, the same pattern already proven by Communications' Communication Inbox and Digital Presence's Client Portal dashboard.
4. **Ethical Walls belong in Practice Management**, because the thing being walled off — a `Matter`, and everything that references it — is owned here. A single published `CheckEthicalWallAccess` query is the only sanctioned enforcement point; no consuming context implements its own wall logic.
5. **Conflict Checking is architected, not implemented, here.** The conceptual model (`PartyReference`, `ConflictRelationship`, a `SearchConflicts` published query) supports Clients, opposing parties, related parties, Organizations, Lawyers, and historical relationships, while deliberately leaving matching/scoring algorithms as unresolved implementation-story-level detail.
6. **AI within Practice Management is explicitly enumerated, not merely "advisory."** AI may summarize, and suggest tasks, timelines, practice area, lawyer, and deadlines. AI may never change matter status, assign lawyers, close matters, or override Ethical Walls without explicit human authorization — these four actions carry professional-responsibility consequences that place them outside AI's advisory role entirely.
7. **Practice Management data is wholly firm-scoped**, isolated by `FirmContext`, with only the `PracticeArea` taxonomy schema and default numbering-scheme templates as platform-global, static configuration — the fourth module, after Branding, Communications, and Digital Presence, to take this shape.

Full conceptual detail is recorded in `docs/architecture/13_Practice_Management_Architecture.md`.

## Why Matter is the central aggregate

A `Task`, an `Appointment`, a `Note`, a `Communication`, a Document, an Invoice, and an AI summary each exist most meaningfully *in relation to* a specific piece of legal work — the Matter. It is also the natural boundary for professional responsibility: conflicts are checked against a prospective Matter, Ethical Walls restrict a Matter, and a lawyer's duty of care attaches to a Matter, not to a Client relationship in the abstract (a Client can have one walled Matter and several open ones simultaneously). Making `Matter` the central aggregate gives every one of those concerns — conflicts, walls, timeline, dashboard — a single, unambiguous boundary to attach to.

## Why Practice Management is its own bounded context

No existing or planned module is a plausible owner of Client or Matter without duplicating the concept to function: Communications would need its own notion of "who is this thread about," Digital Presence would need its own notion of "whose portal is this," Billing and Documents would each need their own notion of "which engagement does this belong to." A single, dedicated Core Domain avoids that duplication entirely and gives every Supporting/Generic subdomain (Communications, Digital Presence, Legal Intelligence, Billing, Documents, Branding) one place to look up the shared vocabulary they all depend on.

## Why Activity Timeline is composed from other modules

Communications, Documents, Billing, and AI activity are owned by their respective bounded contexts as a deliberate, explicit non-goal of this architecture (Context, above; `docs/architecture/13_Practice_Management_Architecture.md`, Integration Boundaries). If the Timeline stored a materialized copy of that activity inside the `Matter` aggregate, those other contexts would need to write into Practice Management's schema to keep it current — directly violating the "never write directly to another module's tables" rule in `docs/domain/06_Laravel_Module_Blueprint.md`. A read-model projection, assembled from each context's own published events and queries, avoids that violation and keeps ownership exactly where Decision 1 places it.

## Why Ethical Walls belong here

An Ethical Wall restricts access to a `Matter`; the authorization decision therefore has to be made where `Matter` access itself is authorized. If Documents, Billing, and Communications each implemented their own wall-checking logic independently, the walls could drift out of sync — one context honoring a wall another has forgotten to check — which is exactly the kind of duplicated-authority risk `docs/domain/06_Laravel_Module_Blueprint.md`'s "never bypass Ethical Walls" rule exists to prevent. Centralizing the check in Practice Management, behind one published query every consuming context must call, is the only way to guarantee uniform enforcement.

## Alternatives considered

- **Model Client, Contact, and Organization as a single generic "Party" aggregate.** Rejected — collapses meaningfully different lifecycles (a Client's engagement relationship, a Contact's cross-organization identity, an Organization's lack of any engagement of its own) into one over-general concept, and the objective explicitly requires modeling them distinctly.
- **Make Task, Appointment, and Note entities owned inside the Matter aggregate.** Rejected — would make `Matter` an unbounded "god aggregate," loading years of tasks/notes/appointments on every fetch and forcing unrelated mutations through Matter's own concurrency boundary; independent aggregates referencing `Matter` by identifier avoid this, the same reasoning already applied to keeping `TenantDomain` separate from `BrandProfile` (`docs/adr/ADR-003-White-Label-Platform.md`) and `Message` independently lifecycle-managed within `CommunicationThread` (`docs/adr/ADR-004-Communications-Hub.md`).
- **Store the Matter Timeline as owned, materialized history inside Matter, populated by direct writes from Documents/Billing/Communications.** Rejected — requires those modules to write into Practice Management's schema, violating the platform's cross-module write rule; a read-model projection achieves the same visible result without the violation.
- **Let each consuming module implement its own Ethical Wall check.** Rejected — creates drift risk between contexts, the exact failure mode a single centralized, published authorization query prevents.
- **Treat Practice Area as a fixed, hardcoded enum.** Rejected — the objective explicitly requires custom practice areas per Firm; a hardcoded enum cannot be extended without a code deployment, contradicting the platform-seeded-schema-plus-firm-scoped-extension pattern already established for Jurisdiction, theme tokens, and channel capabilities.
- **Implement a conflict-matching algorithm as part of this architecture.** Rejected — the objective explicitly scopes Conflict Checking to "architecture only, do not implement"; matching/scoring logic is implementation-story-level detail that risks locking in an unvalidated approach if specified prematurely here.

## Trade-offs

- Splitting Task, Appointment, and Note into independent aggregates from Matter means assembling "everything about this Matter" (the Matter Dashboard) always requires a cross-aggregate read rather than one aggregate load — accepted in exchange for avoiding the god-aggregate problem.
- A read-model Timeline is eventually consistent with the contexts it composes (a document upload may take a moment to appear) rather than instantly consistent — acceptable for a chronological history view, and explicitly not acceptable if the Timeline were ever mistaken for a system of record, which it must never become.
- A single centralized Ethical Wall check adds a synchronous cross-context dependency for any Matter-linked read in Documents, Billing, or Communications — a coupling cost accepted in exchange for guaranteed-consistent enforcement.
- Firm-configurable, multi-scheme Matter numbering is materially more complex than a single fixed sequential counter (concurrency-safe uniqueness, immutability once assigned, prospective-only scheme changes), but Firms have real, differing regulatory and practice conventions this must accommodate.
- Conflict Checking's dependence on manually recorded `ConflictRelationship` data — especially for a lateral-hire lawyer's history prior to joining the Firm — means its completeness is only as good as what has been disclosed and entered; this is an inherent limitation of any conflict system, stated plainly rather than implied away.

## Future extensibility

The architecture is designed so that multi-office Firms, international matters (via Legal Intelligence's existing `Jurisdiction` value object), court integrations, workflow automation, Matter templates, and a broader knowledge graph (generalizing `ConflictRelationship`) can each be added as additive extensions to the existing `Matter`/`Task`/`Appointment`/`ConflictRelationship` model, without redesigning any of them. See `docs/architecture/13_Practice_Management_Architecture.md`, Future Expansion.
