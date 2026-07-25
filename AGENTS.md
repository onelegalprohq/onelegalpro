# OneLegalPro AI Development Guide

Every AI coding assistant must read this file before changing the repository.

## Source of truth

- `docs/implementation/01_Implementation_Sprint_Plan.md`
- `docs/implementation/02_AI_Developer_Playbook.md`
- `docs/implementation/03_Engineering_Backlog.md`
- `docs/domain/06_Laravel_Module_Blueprint.md`
- `docs/PROJECT_STATUS.md`
- `docs/architecture/` (all files, including the Constitution, AI Architecture, Roadmap, and Legal Intelligence Architecture)
- `docs/adr/` (all Architecture Decision Records)

If a request conflicts with approved architecture, stop, explain the conflict, and request human approval.

## Legal Intelligence rules

- Official Thai-language text is the authoritative legal source for Thai law. Translations are never authoritative.
- Translated legal text is non-authoritative reference material and must always carry the mandatory disclaimer.
- AI-generated content must never be presented as official law.
- Platform-global legal sources and firm-owned legal work must not be conflated — see `docs/architecture/01_OneLegalPro_Constitution.md` and `docs/architecture/09_Legal_Intelligence_Architecture.md`.

## White-label rules

- OneLegalPro is white-label by design — no Firm-facing or client-facing surface may hardcode a non-brandable OneLegalPro identity. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 10–11), `docs/architecture/10_White_Label_Platform_Architecture.md`, and `docs/adr/ADR-003-White-Label-Platform.md`.
- UI styling uses theme tokens only, resolved per Firm through the Branding Resolver — never hardcoded brand-specific colors, fonts, or asset paths in template or component code.
- Branding is presentation-only and must never suppress a mandatory legal disclaimer, citation, or AI-advisory notice.

## Communications rules

- Communications is its own bounded context — a `CommunicationThread` per counterparty, per Firm, unifying every channel into one timeline. Other modules reference communications through published links; they never own or duplicate communication history. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 12–13), `docs/architecture/11_Communications_Hub_Architecture.md`, and `docs/adr/ADR-004-Communications-Hub.md`.
- Provider-specific logic stays behind a `ChannelAdapter`; the Application and Domain layers never branch on a specific provider.
- Communications AI (Website AI Receptionist, Email Intelligence, AI Assistance) must identify itself, never imply an attorney-client relationship or give legal advice, and never send a communication without configured human authorization.

## Digital Presence rules

- Digital Presence (public websites, Client Portal, embedded widgets, booking) composes the Branding Engine and the Communications Hub through their published contracts — it never reimplements branding or messaging logic. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 14–15), `docs/architecture/12_Website_Client_Portal_Architecture.md`, and `docs/adr/ADR-005-Website-Client-Portal.md`.
- The Client Portal is a presentation surface, not a data owner, for Matters, Documents, Invoices, Payments, Tasks, and Appointments — it reads through the owning module's published queries and never duplicates that data into its own schema.
- Every embedded widget is one reusable, sandboxed component (Booking, Client Login, Matter Status, AI Chat, Contact, Payment, Document Upload) — never a bespoke per-site or per-Firm reimplementation.
- The embedded AI receptionist is the Communications Hub's AI, not a separate AI system; it stays bound by the Communications rules above wherever it is embedded.

## Practice Management rules

- Practice Management is the platform's Core Domain — it owns Client, Organization, Contact, Matter, Matter Team, Practice Area, Task, Appointment, Note, and Activity. It never owns Communications, Documents, Billing, Legal Intelligence, or Branding; every other module reaches Practice Management data only through its published contracts. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 16–17), `docs/architecture/13_Practice_Management_Architecture.md`, and `docs/adr/ADR-006-Practice-Management-Core.md`.
- `Matter` is the central aggregate; `Task`, `Appointment`, `Note`, and `EthicalWall` are independent aggregates that reference `Matter`, never entities owned inside it.
- The Matter Timeline and Matter Dashboard are read-model projections over other bounded contexts' published events and queries — never owned, materialized copies of their data.
- Ethical Wall access is checked only through Practice Management's published `CheckEthicalWallAccess` query; no other module implements its own wall logic.
- AI may summarize and suggest tasks, timelines, practice area, lawyer, and deadlines. AI must never change matter status, assign lawyers, close matters, or override Ethical Walls without explicit human authorization.

## Document & Knowledge Management rules

- Documents is its own bounded context containing two explicitly separated domain areas — **Document Management** and **Knowledge Management**. The proposed module stays named `Documents`; the separation is enforced by distinct aggregates, lifecycles, access policies, and retrieval eligibility, not by a module wall. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 18–21), `docs/architecture/14_Document_Knowledge_Management_Architecture.md`, and `docs/adr/ADR-007-Document-Knowledge-Management.md`.
- It is the sole owner of the canonical document record and every stored document version. Stored versions are immutable — a correction or replacement creates a new version, preserving historical bytes, checksums, authorship, timestamps, and provenance.
- Every document belongs to exactly one Firm, with Firm identity explicit and enforced in application and repository paths — never by global scopes alone.
- Matter-linked access derives from Practice Management's published contracts, and Ethical Wall authorization comes only from `CheckEthicalWallAccess`. Document-level controls may narrow that access, never widen it; where a document is reachable from more than one restricted Matter, the most restrictive outcome applies. A denied caller receives no content, metadata, preview, search hit, or existence confirmation.
- Client Portal visibility is explicit and deny-by-default. Internal availability never implies portal visibility, publication is a distinct recorded decision, and audience resolution names specific `MatterClient`s — one co-client must never see another's document merely because they share a Matter.
- Document objects are private, never served from public or guessable locations; delivery is authorization-gated and short-lived. Uploads require size and media-type validation, filename normalization, and malware scanning, and stay quarantined until positively cleared — an unavailable or indeterminate scanner fails closed. Previews and other derivatives inherit their source's security boundary.
- Retention is Firm- and jurisdiction-aware; archival is not deletion; a legal hold blocks deletion, purge, retention expiry, and destructive redaction until an authorized human releases it. Deleting content never destroys the audit fact that the document existed.
- AI and OCR output is derived annotation, never canonical content or authoritative metadata: structurally separate, provenance- and confidence-tagged, and removable without altering the source. AI must never, without explicit human authorization, alter document content, finalize/sign/file/send/publish/delete a document, change legal-hold or retention state, broaden access, confirm a low-confidence Matter/Client association, or send privileged content to an unapproved model or processor.
- Other modules reach Documents only through published commands, queries, and events — never its tables or object-storage paths — and Documents reaches Practice Management, Communications, Billing, Branding, and Legal Intelligence only through theirs. Storage paths never cross a module boundary; events carry identifiers and safe metadata, never document bytes, knowledge body text, privileged content, or embeddings.

**Knowledge Management**

- A source `Document` and a curated `KnowledgeItem` are distinct records with different authority, lifecycle, audience, and governance — neither is a state of the other. Knowledge covers precedents, clauses, templates, playbooks, practice notes, research notes, and internal guidance. Legal Intelligence still owns official law; Digital Presence still owns public editorial content; Practice Management still owns Clients, Matters, `MatterClient`s, `PracticeArea`s, `MatterTeam`s, and Ethical Walls.
- **A Matter document never automatically becomes Firm-wide knowledge.** Promotion requires an explicit human curation workflow that creates a separate record, retains access-controlled provenance, and reaches recorded determinations on confidentiality, privilege, personal data, contractual restrictions, and Ethical Walls before any reuse audience exists. Removing names is not de-identification — re-identification risk from context must be assessed. Source restrictions and any legal hold on the source remain effective until an explicit authorized declassification decision says otherwise.
- Approval is human, version-specific, and never AI-performed. Each approved knowledge version is immutable; updates create a new version, and superseded/retired versions stay auditable. Every approved item has a Firm, a named owner, provenance, an approval record, an access policy, and a review policy — missing any of these makes it invalid. Overdue review status is always visible, and stale knowledge is never silently presented as current.
- Knowledge access may narrow Matter-derived access but never overrides an Ethical Wall; collection membership and practice-area association never grant access. A denied caller receives no title, snippet, tag, metadata, provenance, citation, search hit, or existence confirmation.
- **Search and AI/RAG filter by Firm and then by permission before retrieval** — never after retrieval, and never by post-generation filtering. Only approved, retrieval-eligible, access-authorized content enters standard AI context. Index entries and embeddings inherit their source's Firm and access boundary; an access change, revocation, supersession, or retirement removes them from future retrieval without erasing audit history. Cross-Firm retrieval, embeddings, caching, evaluation data, and model context are prohibited.
- AI may suggest, draft, propose curation candidates, flag stale or conflicting knowledge, and retrieve authorized knowledge for drafting. AI must never, without explicit human authorization, approve or publish knowledge, make Matter-derived knowledge Firm-wide, remove confidentiality/privilege/contractual/personal-data/Ethical Wall restrictions, change an approved version, retrieve inaccessible content, mix Firms' knowledge, treat draft or AI output as approved Firm guidance, present Firm know-how as official law, or send privileged content to an unapproved processor.

## Architecture

OneLegalPro uses Domain-Driven Design, Clean Architecture, a Laravel modular monolith, PostgreSQL, UUIDv7, event-driven integration, REST-first interfaces, and Firm-based multi-tenancy.

Business code belongs in `app/Modules`. Shared technical primitives belong in `app/Foundation`.

## Standard module structure

```text
Application/
Domain/
Infrastructure/
Interface/
Database/
Routes/
Tests/
Config/
ModuleServiceProvider.php
README.md
```

## Rules

- Controllers contain no business logic.
- The Domain layer remains framework-independent.
- Eloquent records are persistence models, not domain aggregates.
- Modules communicate through published contracts, commands, queries, events, or workflows.
- Never import another module's Eloquent model or write directly to another module's tables.
- Never bypass authorization, Firm isolation, Ethical Walls, audit, or security controls.
- AI output is advisory and never directly modifies authoritative legal records.
- Use PostgreSQL and UUIDv7.
- Never edit historical migrations.
- Modify only files required by the approved story.
- Never claim tests passed unless they were actually executed.

## Before writing code

Identify the story, module, use case, aggregate, permissions, schema, events, audit impact, security impact, and required tests. Ask rather than guess.

## Approval required before

- Authentication or authorization changes
- Database redesign
- Public API breaking changes
- New runtime dependencies
- Billing or payment changes
- AI governance changes
- Destructive operations
- Removing tests or security controls

## Required completion report

1. Summary
2. Files created
3. Files modified
4. Database changes
5. Events
6. Tests added and executed
7. Security considerations
8. Documentation updated
9. Risks
10. Architecture compliance

> The AI is an implementation partner, not the architect.
