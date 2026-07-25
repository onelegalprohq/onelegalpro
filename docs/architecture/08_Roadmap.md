# ARCH-008 — Roadmap

**Status:** Approved (Epic sequencing) — see note on story status below

## Purpose

Sequence OneLegalPro's major epics at the architecture level. This document sits above `docs/implementation/01_Implementation_Sprint_Plan.md`, which governs approved sprint-level delivery of `PF-*` stories. Where the two differ on delivery order, the Implementation Sprint Plan governs day-to-day work; this Roadmap governs epic-level sequencing.

## Epic sequence

1. **EPIC-001 — Platform Foundation** (in progress — see `docs/implementation/01_Implementation_Sprint_Plan.md` and `docs/implementation/03_Engineering_Backlog.md` for approved `PF-*` stories).
2. **EPIC-002 — Legal Intelligence** (proposed — see below). Begins after Platform Foundation's repository, environment, quality/CI, and Foundation Library sprints are far enough along to support a new module (see `docs/domain/06_Laravel_Module_Blueprint.md`).
3. **EPIC-003 — White-Label Platform** (proposed — see below). Must reach at least its token-schema and `BrandProfile` foundation stages before the Digital Presence or Client Portal epics can implement any client-facing rendering surface, since both depend on the Branding Resolver contract.
4. **EPIC-004 — Communications Hub** (proposed — see below). Must reach at least its channel-neutral thread/message foundation and website/portal chat adapter stages before the Digital Presence or Client Portal epics can implement any client-facing chat experience, since both depend on the `Communications` module's adapter contract and human-handoff model.
5. **EPIC-005 — Digital Presence Platform** (proposed — see below). Consolidates the previously separate "Digital Presence" and "Client Portal" names in the Later epics list below into one bounded context (`docs/architecture/12_Website_Client_Portal_Architecture.md`, `docs/adr/ADR-005-Website-Client-Portal.md`). Depends on EPIC-003 reaching its Branding Resolver foundation and EPIC-004 reaching its channel-neutral thread/message foundation and website/portal chat adapter stages, since Digital Presence composes both rather than reimplementing either.
6. **EPIC-006 — Practice Management Core** (proposed — see below). Consolidates the previously unarchitected "Legal Practice Core" name in the Later epics list below into one bounded context (`docs/architecture/13_Practice_Management_Architecture.md`, `docs/adr/ADR-006-Practice-Management-Core.md`). **Retroactive dependency note:** although architected sixth in this sequence, Practice Management Core is the platform's Core Domain — EPIC-004's business-object-linking stage (Communications' `CommunicationLink` to Client/Matter/Task/Appointment) and EPIC-005's Practice Management read-surfaces and Booking System stages both already named "a future Practice Management module" as an external dependency (`docs/architecture/11_Communications_Hub_Architecture.md` §10; `docs/architecture/12_Website_Client_Portal_Architecture.md` §7, §12). Implementation sequencing for those specific stages must account for EPIC-006's `Client`/`Matter`/`Task`/`Appointment` foundation stages, regardless of this document's architecture-approval ordering.
7. Later epics: Platform Core; **Legal Practice Core (now governed by EPIC-006 — Practice Management Core, see below)**; Documents; Commercial Operations; **Digital Presence; Client Portal (both now governed by EPIC-005 — Digital Presence Platform, see below)**; Workflow; Integrations; Reporting; Academic Edition (unchanged from `docs/implementation/01_Implementation_Sprint_Plan.md`).

## EPIC-002 — Legal Intelligence (proposed)

> **Status note:** The *architecture* for Legal Intelligence is approved — see `docs/adr/ADR-002-Thailand-First-Legal-Intelligence.md` and `docs/architecture/09_Legal_Intelligence_Architecture.md`. The stage list below is a **proposed** staging of future implementation stories. None of these stages are approved, scheduled, or numbered `PF-*` stories. They require separate entry into `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before any implementation work begins.

Proposed staged delivery:

1. **Jurisdiction foundation** — `Jurisdiction` value object, `PlatformDefaultJurisdictionPolicy` (Thailand as default), jurisdiction/language metadata primitives.
2. **Legal-source aggregate and metadata** — `LegalSource` aggregate, authority/version/provenance metadata (see `docs/architecture/09_Legal_Intelligence_Architecture.md`).
3. **Ingestion pipeline** — intake of official Thai statutory, regulatory, and judicial texts, with integrity hashing and provenance capture.
4. **Translation linking** — `Translation` entity linked to its canonical `LegalSource`, non-authoritative by construction.
5. **Citation engine** — `LegalCitation` model supporting Thai canonical citation and optional English reference citation.
6. **Disclaimer enforcement** — centrally governed disclaimer rendering wherever a translation is presented, in web, API, and AI-generated output.
7. **Thai legal search** — search across official sources and linked translations, authority-aware ranking.
8. **AI retrieval** — authority-aware RAG per `docs/architecture/05_AI_Architecture.md`.
9. **Court-decision ingestion** — court decisions modeled distinctly from legislation/regulations, including licensing and source-rights metadata.
10. **Firm annotations and saved research** — firm-scoped notes, bookmarks, saved research, private AI context, strictly isolated by `FirmContext`.
11. **Amendment tracking and legal graph** — supersession history, amendment records, and cross-reference graph between legal sources.

## Future multi-jurisdiction expansion

Every stage above is designed so that adding a jurisdiction beyond Thailand is a configuration and data exercise, not a redesign of the `LegalSource`, `Translation`, `LegalCitation`, or retrieval models. See `docs/architecture/09_Legal_Intelligence_Architecture.md` for the extension model.

## EPIC-003 — White-Label Platform (proposed)

> **Status note:** The *architecture* for the White-Label Platform is approved — see `docs/adr/ADR-003-White-Label-Platform.md` and `docs/architecture/10_White_Label_Platform_Architecture.md`. The stage list below is a **proposed** staging of future implementation stories. None of these stages are approved, scheduled, or numbered `PF-*` stories. They require separate entry into `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before any implementation work begins.

Proposed staged delivery:

1. **Theme token schema and Branding Resolver foundation** — platform-global token schema, platform default token values, and the Branding Resolver contract other modules will consume.
2. **`BrandProfile` aggregate and asset management** — per-Firm theme values, `BrandAsset` upload/versioning, object storage integration.
3. **Email and PDF branding integration** — `EmailBrandingConfig` and `PDFBrandingConfig` wired into transactional email and generated-document rendering.
4. **AI assistant branding** — `AIPersonaConfig`, with the AI governance rules in `docs/architecture/05_AI_Architecture.md` preserved unchanged under any persona.
5. **Custom domain registration and verification** — `TenantDomain` aggregate, DNS-based verification challenge.
6. **Automated SSL provisioning** — certificate issuance and renewal wired to `TenantDomain` activation.
7. **Accessibility guardrails** — contrast validation on custom theme token values.
8. **Theme marketplace integration** — dependent on a future, separately approved pass on `docs/architecture/06_Marketplace.md`.

## Future sub-firm and marketplace expansion

Every stage above is designed so that department/office-level branding inheritance and theme marketplace integration are additive to `BrandProfile` and `TenantDomain`, not a redesign of either aggregate. See `docs/architecture/10_White_Label_Platform_Architecture.md`, Future expansion, for the extension model.

## EPIC-004 — Communications Hub (proposed)

> **Status note:** The *architecture* for the Communications Hub is approved — see `docs/adr/ADR-004-Communications-Hub.md` and `docs/architecture/11_Communications_Hub_Architecture.md`. The stage list below is a **proposed** staging of future implementation stories. None of these stages are approved, scheduled, or numbered `PF-*` stories. They require separate entry into `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before any implementation work begins.

Proposed staged delivery:

1. **Channel-neutral thread/message foundation** — `CommunicationThread` aggregate, `Message`/`CommunicationLink` entities, `ChannelAdapter` contract.
2. **Email adapters** — Google Workspace, Microsoft 365, and generic IMAP, behind the shared adapter contract.
3. **Messaging-platform adapters** — WhatsApp Business, LINE Official Account, Telegram, and SMS.
4. **Website and client portal chat, with the AI receptionist** — Website AI Chat and Client Portal Chat adapters, intake collection, practice-area/urgency detection, consultation booking, Lead creation.
5. **Human handoff** — escalation from AI to a human without restarting the conversation.
6. **Business-object linking** — `CommunicationLink` to Lead, Client, Matter, Task, Appointment, Invoice, and Knowledge, with confidence-scored, human-confirmable matching.
7. **AI assistance and provenance** — summaries, translation, classification, entity extraction, draft replies, suggested tags/lawyer/reminders, all provenance-tagged per `docs/architecture/05_AI_Architecture.md`.
8. **Communication Inbox** — the unified, permission-aware triage workspace.
9. **Privacy, retention, and legal hold** — consent tracking, jurisdiction-scoped retention policy, legal hold, deletion, and export.
10. **Analytics** — response time, AI resolution rate, lead conversion, channel usage, missed enquiries, and lawyer workload, as read-model projections.

## Future channel and capability expansion

Every stage above is designed so that Slack, Microsoft Teams, Voice, and Video channels, along with voice AI, meeting transcription, a court hearing assistant, real-time translation, and video consultation, are additive — new `ChannelAdapter`s and, where relevant, new `AIAnnotation` types layered on the existing `CommunicationThread`/`Message` model — not a redesign of either. See `docs/architecture/11_Communications_Hub_Architecture.md`, Future Expansion, for the extension model.

## EPIC-005 — Digital Presence Platform (proposed)

> **Status note:** The *architecture* for the Digital Presence Platform is approved — see `docs/adr/ADR-005-Website-Client-Portal.md` and `docs/architecture/12_Website_Client_Portal_Architecture.md`. The stage list below is a **proposed** staging of future implementation stories. None of these stages are approved, scheduled, or numbered `PF-*` stories. They require separate entry into `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before any implementation work begins.

Proposed staged delivery:

1. **`DigitalPresenceProfile` and deployment-model foundation** — per-Firm configuration of fully hosted / existing website / enterprise CMS deployment models.
2. **Content Management and Website Builder** — `ContentItem` aggregate, Draft → Review → Publish, Practice Areas, Lawyer Profiles, Office Locations, News, Articles, FAQs, Contact.
3. **Client Portal authentication** — `ClientPortalIdentity`, `PortalAuthPolicy` (password, passwordless, magic link, MFA).
4. **Practice Management read-surfaces** — Matters/Documents/Invoices/Payments/Tasks/Appointments surfaced in the Client Portal, staged as each owning module's own architecture becomes available.
5. **Booking System** — `AvailabilitySchedule`, `BookingRequest`, conflict detection, reminder workflow via Communications.
6. **AI Receptionist and widget integration** — Website AI Chat, Client Portal Chat, AI Chat Widget, wired to the Communications Hub's AI receptionist and handoff model.
7. **Embedded Component Framework** — `WidgetEmbed`, `EmbedKey`/`AllowedOrigin` security boundary, sandboxed third-party-site embedding for all seven widgets.
8. **Knowledge Publishing** — Articles, legal updates, FAQs, educational content, with a `CrossReference`-style citation path to Legal Intelligence.
9. **SEO and accessibility hardening** — `SEOMetadata`, structured data, WCAG AA across Website Builder, Portal, and widgets.
10. **Enterprise API/CMS integration** — Public APIs for headless content pull and programmatic booking/intake.

## Future Digital Presence expansion

Every stage above is designed so that mobile app reuse, a Progressive Web App, a client mobile app, partner portals, and court portals are additive consumers of the existing Public/Embedded API surface and the existing `DigitalPresenceProfile`/`ContentItem`/`ClientPortalIdentity`/`BookingRequest` model, not a redesign of any of them. See `docs/architecture/12_Website_Client_Portal_Architecture.md`, Future Expansion, for the extension model.

## EPIC-006 — Practice Management Core (proposed)

> **Status note:** The *architecture* for Practice Management Core is approved — see `docs/adr/ADR-006-Practice-Management-Core.md` and `docs/architecture/13_Practice_Management_Architecture.md`. The stage list below is a **proposed** staging of future implementation stories. None of these stages are approved, scheduled, or numbered `PF-*` stories. They require separate entry into `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before any implementation work begins. Because Practice Management is the platform's Core Domain, its early stages are a practical prerequisite for EPIC-004 and EPIC-005's Matter/Client/Task/Appointment-dependent stages — see the retroactive dependency note in the Epic sequence, above.

Proposed staged delivery:

1. **`Client`/`Organization`/`Contact` foundation** — the three party aggregates and their cross-referencing relationships.
2. **`Matter` aggregate, lifecycle, and `MatterNumber`** — the central aggregate, its status lifecycle, and firm-configurable, immutable matter numbering.
3. **`PracticeArea` taxonomy** — platform-seeded defaults plus firm-scoped custom practice areas.
4. **`MatterTeam` and role-based permissions** — Responsible/Lead/Supporting Lawyer, Paralegal, Assistant, External Counsel roles and their derived permissions.
5. **`Task`/`Appointment`/`Note` aggregates** — including dependencies, recurrence, timezone-aware scheduling, and immutable-audit note content.
6. **Ethical Walls** — restricted-matter access, need-to-know allow-lists, auditing, and emergency override.
7. **Conflict Checking** — `PartyReference`/`ConflictRelationship` model and the `SearchConflicts` published query (architecture only; matching/scoring logic is a separate, future implementation decision).
8. **Matter Timeline read-model** — the cross-context, chronological history assembled from Communications, Documents, Billing, and AI activity.
9. **Matter Dashboard** — the lawyer's primary workspace, composing Tasks, Timeline, Communications, Documents, Billing, AI summaries, Deadlines, and Appointments.
10. **Integration hardening with Communications and Digital Presence** — resolving the `CommunicationLink` and `BookingRequest`/Client-Portal-read-surface dependencies those architectures already named.

## Future Practice Management expansion

Every stage above is designed so that multi-office Firms, international matters (via Legal Intelligence's `Jurisdiction` value object), court integrations, workflow automation, Matter templates, and a broader knowledge graph are additive extensions to the existing `Matter`/`Task`/`Appointment`/`ConflictRelationship` model, not a redesign of any of them. See `docs/architecture/13_Practice_Management_Architecture.md`, Future Expansion, for the extension model.
