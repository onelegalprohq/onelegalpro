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
7. **EPIC-007 — Documents & Knowledge Management** (proposed — see below). Consolidates the previously unarchitected "Documents" name in the Later epics list below into one bounded context containing two explicitly separated domain areas — Document Management and Knowledge Management (`docs/architecture/14_Document_Knowledge_Management_Architecture.md`, `docs/adr/ADR-007-Document-Knowledge-Management.md`). Depends on EPIC-006 reaching at least its `Client`/`Matter`/`MatterClient` and Ethical Wall stages, since both areas derive Matter-linked access — document Client Portal audience resolution and Matter-to-knowledge curation alike — from Practice Management's published contracts rather than implementing their own. **Retroactive dependency note:** EPIC-004's attachment handling (`docs/architecture/11_Communications_Hub_Architecture.md` §6) and EPIC-005's Client Portal Documents surface and Document Upload Widget (`docs/architecture/12_Website_Client_Portal_Architecture.md` §4, §8) both already named the Documents domain as an external dependency; implementation sequencing for those specific stages must account for EPIC-007's foundation stages.
8. **EPIC-008 — Billing, Trust Accounting & Finance** (proposed — see below). Consolidates the previously unarchitected "Commercial Operations" name in the Later epics list below into one bounded context containing three explicitly separated financial domain areas — Billing/Accounts Receivable, Client Money/Trust Accounting, and Firm Finance/Accounting (`docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md`, `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md`). **Dependencies:** **EPIC-001 Platform Foundation, specifically `PF-045 — Money`** in Sprint 0.3 (Foundation Library), which supplies the platform-wide exact-decimal `Money`/`Currency` contract that Billing consumes and must never duplicate — EPIC-008 depends on PF-045 without scheduling, duplicating, renumbering, or completing it; EPIC-006's `Client`/`Matter`/`MatterClient` and Ethical Wall foundations (Matter attribution and authorization for every financial record); EPIC-007's rendered-artifact foundation (invoices, receipts, statements, and tax documents are stored by Documents); EPIC-005's Client Portal and Payment Widget (invoice/payment read surfaces and provider-mediated payment initiation); EPIC-004's Invoice linking and delivery (`CommunicationLink` to Invoice, notices and reminders); and a **future Identity/Security capability** for actor identity, permissions, and segregation-of-duties role assignments. **Retroactive dependency note:** EPIC-004's business-object linking (`docs/architecture/11_Communications_Hub_Architecture.md` §9–§10), EPIC-005's Client Portal Invoices/Payments surfaces and Payment Widget (`docs/architecture/12_Website_Client_Portal_Architecture.md` §4, §8), EPIC-006's Matter Timeline and Matter Dashboard billing panels (`docs/architecture/13_Practice_Management_Architecture.md`), and EPIC-007's rendered invoice artifact (`docs/adr/ADR-007-Document-Knowledge-Management.md`, Decision 4) all already named a future Billing bounded context as an external dependency; implementation sequencing for those specific stages must account for EPIC-008's foundation stages.
9. **EPIC-009 — Identity, Security & Access Control** (proposed — see below). Establishes the `IdentityAccess` bounded context and the platform-wide security baseline (`docs/architecture/16_Identity_Security_Access_Control_Architecture.md`, `docs/architecture/04_Security_Architecture.md`, `docs/adr/ADR-009-Identity-Security-Access-Control.md`). **Retroactive dependency note:** every prior epic already depends on an identity capability none of them defined — EPIC-005's Client Portal authentication (whose `ClientPortalIdentity`/`PortalAuthPolicy` ownership ARCH-008 corrects), EPIC-006's Actor references and `MatterTeam` assignments, EPIC-007's author/owner/approver Actor references, and EPIC-008's actor identity, permissions, and segregation-of-duties assignments. **Every module's Firm-bound authorization depends on Platform Foundation tenancy (PF-080/PF-081/PF-082, unscheduled) *plus* EPIC-009 identity results**; Foundation consumes verified identity and membership rather than resolving them. Implementation sequencing for those dependent stages must account for EPIC-009's foundation stages.
10. **EPIC-010 — API & Integration Platform** (proposed — see below). Establishes the `Integrations` supporting bounded context and populates `docs/architecture/07_API_Standards.md` as the platform-wide normative API standard (`docs/architecture/17_API_Integration_Platform_Architecture.md`, `docs/adr/ADR-010-API-Integration-Platform.md`). **Retroactive dependency note:** every prior epic already named a Public API or integration capability none of them owned — Digital Presence's Public/Embedded APIs (`docs/architecture/12_Website_Client_Portal_Architecture.md` §16, "reserved for ARCH-009"), IdentityAccess's service-principal API-authentication foundation (`docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §34, "ARCH-009 will own Public API surfaces"), Communications' provider and external-channel adapters, Billing's payment-provider webhook and reconciliation boundaries, Documents' secure upload/download and any future external document integration, and Legal Intelligence's API consumers requiring provenance and disclaimer preservation. **Depends on EPIC-009 for authentication and service-principal identity** and on Platform Foundation's `FirmContext` primitive (PF-080/PF-081/PF-082, unscheduled) for every Firm-bound external request — Integrations resolves no tenancy or identity of its own. Implementation sequencing for those dependent stages must account for EPIC-010's foundation stages.
11. Later epics: Platform Core; **Legal Practice Core (now governed by EPIC-006 — Practice Management Core, see below)**; **Documents (now governed by EPIC-007 — Documents & Knowledge Management, see below)**; **Commercial Operations (now governed by EPIC-008 — Billing, Trust Accounting & Finance, see below)**; **Digital Presence; Client Portal (both now governed by EPIC-005 — Digital Presence Platform, see below)**; Workflow; **Integrations (now governed by EPIC-010 — API & Integration Platform, see below)**; Reporting; Academic Edition (unchanged from `docs/implementation/01_Implementation_Sprint_Plan.md`).

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

## EPIC-007 — Documents & Knowledge Management (proposed)

> **Status note:** The *architecture* for Documents & Knowledge Management is approved — see `docs/adr/ADR-007-Document-Knowledge-Management.md` and `docs/architecture/14_Document_Knowledge_Management_Architecture.md`. The stage list below is a **proposed** staging of future implementation stories. None of these stages are approved, scheduled, or numbered `PF-*` stories. They require separate entry into `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before any implementation work begins. This epic covers **two explicitly separated domain areas** — Document Management (the source artifact) and Knowledge Management (curated, approved, reusable Firm know-how) — within one bounded context. Both derive Matter-linked access from Practice Management's published contracts, so their access-control and curation stages depend on EPIC-006's `Client`/`Matter`/`MatterClient` and Ethical Wall stages — see the retroactive dependency note in the Epic sequence, above.

Proposed staged delivery:

1. **Document record, immutable versioning, provenance, and Firm isolation** — the `Document` aggregate, `DocumentVersion` immutability, checksums, `DocumentSource` provenance, explicit Firm scoping.
2. **Private storage, secure delivery, upload validation, scanning, and quarantine** — object-storage adapter, `StorageObjectReference`, short-lived authorization-gated delivery, size and media-type validation, filename normalization, malware-scanning adapter, fail-closed acceptance.
3. **Matter associations, Ethical Walls, document access policy, and co-client portal audience** — `DocumentAssociation` with referential integrity, `CheckEthicalWallAccess` integration, restriction-only `DocumentAccessPolicy`, deny-by-default `PortalDocumentAudience` with co-client isolation and immediate revocation.
4. **Document metadata, search, OCR, and document intelligence** — classification taxonomy, tags, `DocumentCollection`, permission-aware search evaluated at query time, `DocumentAnnotation` with provenance and confidence, approved-processor policy.
5. **Knowledge Item, immutable Knowledge version, taxonomy, and collection foundation** — the `KnowledgeItem` aggregate, `KnowledgeVersion` immutability, `KnowledgeClassification` taxonomy, `KnowledgeCollection`.
6. **Precedent, clause, template, playbook, practice-note, and research-note management** — the typed knowledge assets, including `DocumentTemplate`'s dual role as governed knowledge and document-generation input.
7. **Matter-to-knowledge curation, confidentiality review, de-identification, and approval** — the explicit human curation workflow, `KnowledgeSourceReference` provenance, confidentiality/privilege/personal-data/contractual/Ethical Wall review, and recorded curation decisions.
8. **Knowledge review, supersession, retirement, ownership, and staleness management** — `KnowledgeOwner`, `KnowledgeReviewPolicy`, `ReviewDueAt`, visible overdue status, supersession and retirement.
9. **Permission-aware Knowledge search and governed AI/RAG** — Firm-then-permission filtering before retrieval, access-inheriting index and embedding entries, `RetrievalEligibility`, citation- and provenance-preserving retrieval.
10. **Retention, legal hold, audit, Digital Presence publication, and integration hardening** — `DocumentRetentionPolicy`, `LegalHold`, governed deletion with audit tombstones, audit and export, the Digital Presence publication contract, and Communications/Practice Management integration hardening.

## Future Documents & Knowledge Management expansion

Every stage above is designed so that e-signature integration, court e-filing, advanced document automation, collaborative editing, semantic cross-document search, redaction workflows, and a document knowledge graph are additive extensions to the existing `Document`/`DocumentVersion`/`DocumentAccessPolicy`/`PortalDocumentAudience`/`LegalHold` model, and so that knowledge analytics and reuse metrics, cross-matter precedent comparison, automated clause-conflict detection, multilingual knowledge, expertise location, a knowledge graph, and future Workflow consumption of playbooks are additive extensions to the existing `KnowledgeItem`/`KnowledgeVersion`/`KnowledgeAccessPolicy`/`RetrievalEligibility` model — not a redesign of any of them. None of those capabilities is implemented, architected in detail, or scheduled.

A **future Marketplace** may distribute separately governed, approved template or knowledge packages. That would require its own architecture pass against `docs/architecture/06_Marketplace.md`, which remains an empty placeholder; **no Marketplace capability is claimed, architected, or implemented**, and cross-Firm knowledge distribution remains prohibited until a separately approved architecture establishes how such packages are governed. See `docs/architecture/14_Document_Knowledge_Management_Architecture.md`, Future expansion (§34) and Knowledge future expansion (§K34), for the extension model.

## EPIC-008 — Billing, Trust Accounting & Finance (proposed)

> **Status note:** The *architecture* for Billing, Trust Accounting & Finance is approved — see `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md` and `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md`. The stage list below is a **proposed** staging of future implementation stories. **None of these stages is approved, scheduled, or assigned a `PF-*` number.** They require separate entry into `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before any implementation work begins. This epic covers **three explicitly separated financial domain areas** — Billing/Accounts Receivable, Client Money/Trust Accounting, and Firm Finance/Accounting — within one bounded context whose proposed module is `Billing`. See the dependency and retroactive dependency notes in the Epic sequence, above.

Proposed staged delivery:

1. **Adoption of the PF-045 Foundation `Money`/`Currency` contract, Billing-specific `ExchangeRate` provenance, Firm accounting configuration, and financial-policy versioning** — Billing consumes the Foundation exact-decimal `Money`/`Currency` primitive rather than reimplementing it, and adds its own `ExchangeRate` provenance, effective-dated jurisdiction and accounting policy, Firm financial configuration, and Firm isolation. This stage does not reimplement, reschedule, or renumber PF-045.
2. **Billing arrangements, rates, time, fees, expenses, and approval** — `BillingArrangement` (hourly, fixed-fee, capped, staged, retainer-funded and extensible), effective-dated `RateSchedule`, `TimeEntry`/`FeeEntry`/`ExpenseEntry`/`DisbursementEntry`, billable-versus-billed distinction, approval-before-invoicing.
3. **Invoices, tax documents, credit/debit notes, and accounts receivable** — `Invoice`/`InvoiceLine` with immutability at issue, numbering, effective-dated tax treatment and statutory document particulars, `CreditNote`/`DebitNote`/`WriteOff`, receivables and aging, deny-by-default invoice audience with co-client isolation.
4. **Payments, allocations, refunds, chargebacks, and provider reconciliation** — provider-neutral `PaymentIntent`, provider adapters, tokenized payment methods, authenticated/idempotent/replay-resistant webhooks, distinct payment and settlement status, `PaymentAllocation`, unapplied cash, `Refund`, `Chargeback`, settlement and provider-fee reconciliation.
5. **Client-money/trust account, ledger, and subledger foundation** — `ClientMoneyBankAccount` segregated from operating accounts, append-only `ClientMoneyLedger`, per-client/per-Matter `ClientMoneySubledger`, derived balances with no direct mutation.
6. **Trust transactions, authorization, segregation of duties, and three-way reconciliation** — `ClientMoneyTransaction`/`ClientMoneyTransfer` with full attribution, `ClientMoneyAuthorization`, maker/approver/reconciler roles, trust-to-operating transfer gated on an eligible obligation and explicit human approval, deny-by-default cross-client/cross-Matter transfers, bank-to-control-account-to-subledger reconciliation with visible discrepancies.
7. **General ledger, chart of accounts, journal posting, and accounting periods** — `ChartOfAccounts`/`LedgerAccount`, double-entry `JournalEntry`/`JournalLine` balancing per currency, immutable posted entries with reversals and adjustments, `AccountingPeriod` open/closing/closed/reopened controls.
8. **Bank reconciliation, multi-currency, tax configuration, and financial reporting** — `BankAccount`/`BankTransaction`/`BankReconciliation`, multi-currency handling with conversion provenance, jurisdiction tax configuration, "as of" financial statements and management reports as read-model projections.
9. **Practice Management, Documents, Communications, and Client Portal integration** — Matter/Client references and Ethical Wall checks, rendered-artifact requests to Documents, invoice notices and reminders through Communications, permission-aware Client Portal invoice/payment surfaces and Payment Widget.
10. **Financial AI, analytics, audit, security, and operational hardening** — advisory-only Financial AI with deterministic calculation outside the model, analytics and profitability read models, audit trails and safe exports, segregation-of-duties and break-glass hardening, reconciliation and failure-handling operations.

## Future Billing, Trust Accounting & Finance expansion

Every stage above is designed so that accounts payable, procurement, payroll, fixed assets, budgeting, advanced treasury, an external accountant/auditor portal, banking feeds and open banking, e-tax/e-invoice provider integrations, additional jurisdiction packages, financial analytics and profitability analysis, advanced collections, and mobile payment experiences are additive extensions to the existing `Invoice`/`Payment`/`ClientMoneyLedger`/`JournalEntry` model, not a redesign of any of them. **None of those capabilities is implemented, architected in detail, or scheduled.**

**No cryptocurrency, no direct custody of funds by OneLegalPro, and no autonomous AI financial authority is contemplated.** OneLegalPro is not a bank, escrow provider, custodian, or regulated payment service; any future design in which it holds or settles funds on another party's behalf requires separate legal, regulatory, security, and architecture approval. See `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md`, Future expansion (§86), for the extension model.

## EPIC-009 — Identity, Security & Access Control (proposed)

> **Status note:** The *architecture* for Identity, Security & Access Control is **approved** — see `docs/adr/ADR-009-Identity-Security-Access-Control.md`, `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` (the `IdentityAccess` bounded context), and `docs/architecture/04_Security_Architecture.md` (the platform-wide security baseline populated by the same story). The stage list below is a **proposed** staging of future implementation stories. **Implementation is proposed, not scheduled: none of these stages is approved, scheduled, or assigned a `PF-*` number**, and none displaces PF-010 or PF-011. They require separate entry into `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before any implementation work begins. See the retroactive dependency note in the Epic sequence, above.

**Retroactive dependencies this epic resolves:**

- **Digital Presence Client Portal authentication depends on EPIC-009** — ARCH-008 moves the portal principal, credentials, authenticators, recovery, and sessions out of `DigitalPresence` and into `IdentityAccess`, replacing `ClientPortalIdentity` with a presentation-only `ClientPortalAccessProfile`.
- **Practice Management Actor references and `MatterTeam` assignments depend on EPIC-009** — resolving the "Identity (not yet architected)" dependency `docs/architecture/13_Practice_Management_Architecture.md` §13 already named.
- **Documents and Knowledge author/owner/approval Actor references depend on EPIC-009.**
- **Billing actor identity, permissions, and segregation-of-duties assignments depend on EPIC-009** — resolving the "future Identity/Security capability" `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md` already named.
- **Every module's Firm-bound authorization depends on Platform Foundation tenancy (PF-080/PF-081/PF-082) *plus* EPIC-009 identity results.** Foundation owns the technical `FirmContext` primitive and consumes verified identity and membership; it never resolves identity itself. Those `PF-*` stories are neither scheduled nor renumbered here.

Proposed staged delivery:

1. **Principal, actor-reference, and Firm security-realm foundation** — `Principal`, `ActorReference` with actor categories, `IdentityRealm`, and the Firm-scoped isolation rules.
2. **Firm membership, invitations, provisioning, and lifecycle** — `FirmMembership` as the access authority, `IdentityInvitation`, and the identity lifecycle.
3. **Authentication policy, credentials, and authenticator foundation** — versioned `AuthenticationPolicy`, non-recoverable credential storage, `Authenticator` lifecycle.
4. **MFA, passkeys, recovery, session rotation, and revocation** — MFA factors and passkey registration, recovery as a privileged workflow, Firm-bound sessions with rotation and prompt revocation.
5. **Roles, capabilities, permission sets, and assignments** — domain-published capability catalog, versioned permission sets, platform and Firm-custom roles, effective-dated assignments.
6. **Authorization-decision composition and Ethical Wall integration** — the full composition, `AuthorizationDecision` audit, and `CheckEthicalWallAccess` integration with Practice Management remaining the sole wall authority.
7. **Client Portal identity-boundary migration and integration** — `ClientPortalAccessProfile`, Digital Presence invoking IdentityAccess contracts, no credentials or sessions in Digital Presence.
8. **Service principals, workload credentials, and API-authentication foundation** — non-human principals, rotatable and expiring credentials (API *surfaces*, scopes, and versioning remain ARCH-009).
9. **Federation, SSO, and SCIM foundations** — `FederatedIdentity` binding within one realm, enterprise provisioning; no protocol or vendor selected.
10. **Delegation, segregation of duties, privileged access, and break-glass** — scoped and expiring delegation, maker/approver/reconciler separation, purpose-bound support access, audited break-glass.
11. **Security events, monitoring, risk controls, and operational hardening** — the append-only security event stream, anomaly signals for human review, revocation propagation, and fail-closed operational behavior.

## Future Identity, Security & Access Control expansion

Every stage above is designed so that full OIDC/SAML federation, SCIM provisioning at scale, risk-based and adaptive authentication, cross-Firm identity linking (which would require its own approved ADR with explicit verification and consent), partner and court-portal identities, hardware-backed authenticators, delegated Firm administration, security analytics and anomaly detection, and professional-qualification verification are additive extensions to the existing `Principal`/`FirmMembership`/`Role`/`AuthorizationDecision` model, not a redesign of any of them. **None of those capabilities is implemented, architected in detail, or scheduled.**

**No identity vendor, provider, protocol library, or package is selected, and no certification or compliance claim is made.** The Public API and Integration Platform — endpoint scopes, versioning, developer experience, webhooks, and integration contracts — is reserved for **ARCH-009** and is not designed here. See `docs/architecture/16_Identity_Security_Access_Control_Architecture.md`, Future expansion (§59), for the extension model.

## EPIC-010 — API & Integration Platform (proposed)

> **Status note:** The *architecture* for the API & Integration Platform is **approved** — see `docs/adr/ADR-010-API-Integration-Platform.md`, `docs/architecture/17_API_Integration_Platform_Architecture.md` (the `Integrations` supporting bounded context), and the now-populated `docs/architecture/07_API_Standards.md` (the platform-wide normative API standard). The stage list below is a **proposed** staging of future implementation stories. **Implementation is proposed, not scheduled: none of these stages is approved, scheduled, or assigned a `PF-*` number**, and none displaces PF-010 or PF-011. They require separate entry into `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before any implementation work begins. See the retroactive dependency note in the Epic sequence, above.

**Retroactive dependencies this epic resolves:**

- **ARCH-008's reserved Public API and Integration Platform boundary** — `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §34 named "ARCH-009 will own Public API surfaces, endpoint scopes, versioning, developer portals, webhook contracts, rate-limit policy, and integration orchestration"; this epic is that ownership.
- **Digital Presence enterprise API/CMS integration** — `docs/architecture/12_Website_Client_Portal_Architecture.md` §16's Public and Embedded APIs, whose versioning it explicitly deferred to this document.
- **Communications provider and external-channel adapters** — the shared ingress/verification/replay pattern this epic supplies alongside Communications' own `ChannelAdapter` business semantics.
- **Billing payment-provider webhook and reconciliation boundaries** — Integrations supplies shared webhook safety machinery; Billing retains payment-provider business semantics.
- **Documents secure upload/download and external document integrations** — file transfer delegated to Documents' own quarantine, scanning, and delivery rules.
- **Legal Intelligence API consumers requiring provenance and disclaimer preservation** — any API response surfacing translated legal content still carries the mandatory disclaimer (Constitution Articles 2–4), unchanged by delivery through an external contract.
- **Every external integration requiring IdentityAccess and `FirmContext`** — Integrations resolves no authentication or tenancy of its own; it consumes EPIC-009's identity results and Platform Foundation's `FirmContext` primitive (PF-080/PF-081/PF-082, unscheduled) for every Firm-bound external request.

Proposed staged delivery:

1. **API contract and standards foundation** — `ApiContract`/`ApiContractVersion`, OpenAPI authoring discipline, and the shared DTO/error/pagination conventions from `docs/architecture/07_API_Standards.md`.
2. **Integration application registration and Firm-scoped installation** — `IntegrationApplication`, `IntegrationInstallation`, consent and Firm-admin approval, exact redirect-URI/callback matching.
3. **IdentityAccess service-principal, delegated-access, and scope integration** — consuming IdentityAccess's service-principal and delegation contracts; the API scope model composing with, never widening, domain authorization.
4. **Domain API adapters and stable external representations** — the first stable DTOs mapping domain commands/queries to external contracts, per participating domain module.
5. **Idempotency, concurrency, pagination, errors, and async-operation foundation** — `IdempotencyRecord`, optimistic concurrency preconditions, cursor pagination, the problem-style error catalogue, async job status resources.
6. **Outbound integration events, webhook subscriptions, and delivery** — the versioned `IntegrationEventEnvelope`, `WebhookSubscription`, `WebhookDelivery`, signed and retried delivery with dead-lettering, at-least-once with no exactly-once or global-ordering claim.
7. **Inbound webhook verification, replay protection, and command intake** — the shared ingress pipeline (authenticate, verify replay window, enforce idempotency, validate, translate into an owning module's command).
8. **Connector configuration, synchronization, and reconciliation** — `Connector`, `SyncCursor`, conflict detection, human-visible reconciliation, no silent last-write-wins for legally or financially significant records.
9. **Permission-aware import, export, and secure file delivery** — `ImportExportJob`, Documents-delegated file handling, Firm/authorization revalidation before sensitive delivery.
10. **Developer documentation, sandbox, and contract-derived SDK foundations** — OpenAPI-derived docs, `DeveloperSandbox` isolation with no default production-data copy, generated-SDK tooling that never replaces the authoritative contract.
11. **Rate limiting, observability, deprecation, and operational hardening** — quotas, metrics/tracing without confidential payloads, deprecation/sunset tooling, circuit breakers and bulkheads.

## Future API & Integration Platform expansion

Every stage above is designed so that a full public marketplace and partner certification program, Workflow-driven multi-step orchestration consuming these contracts, GraphQL or other query-language surfaces, advanced per-integration analytics, a dedicated developer-portal product, partner-tier rate-limit/SLA differentiation, and cross-Firm integration templates are additive extensions to the existing `ApiContract`/`IntegrationInstallation`/`WebhookSubscription`/`Connector` model, not a redesign of any of them. **None of those capabilities is implemented, architected in detail, or scheduled.**

**No API gateway, identity vendor, message broker, or hosting product is selected. Workflow orchestration remains reserved for a future ARCH-010, and Marketplace publication/monetization remains reserved and ungoverned against `docs/architecture/06_Marketplace.md`, which remains an empty placeholder.** See `docs/architecture/17_API_Integration_Platform_Architecture.md`, Future expansion (§52), for the extension model.
