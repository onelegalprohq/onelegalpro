# IMP-003 — Engineering Backlog

**Status:** Active  
**Current Epic:** EPIC-001 Platform Foundation

## Story lifecycle

Backlog → Ready → In Progress → Code Review → Architecture Review → QA → Approved → Done

## Definition of Ready

Clear goal, identified owner, resolved dependencies, acceptance criteria, security implications, tests, and no architecture blocker.

# EPIC-001 — Platform Foundation

## Repository Bootstrap
- PF-001 Repository Structure — verify repository state
- PF-002 Git & Repository Standards — Done
- PF-003 Repository Documentation — Done

## Development Environment
- PF-010 Docker Development Environment — Next
- PF-011 Local Environment & Configuration
- PF-012 Development Tooling

## Code Quality
- PF-020 Laravel Pint
- PF-021 PHPStan
- PF-022 Rector, optional
- PF-023 Git Hooks

## CI/CD
- PF-030 GitHub Actions
- PF-031 Quality Gates
- PF-032 Security Scanning

## Foundation Library
- PF-040 AggregateRoot
- PF-041 Entity
- PF-042 ValueObject
- PF-043 DomainEvent
- PF-044 BusinessIdentifier
- PF-045 Money
- PF-046 Result
- PF-047 Clock
- PF-048 UUIDv7
- PF-049 Exception hierarchy

## Module Infrastructure
- PF-060 through PF-063

## Application Framework
- PF-070 through PF-073

## Multi-Tenant Foundation
- PF-080 through PF-082

## Event Infrastructure
- PF-090 through PF-093

## Testing Infrastructure
- PF-100 through PF-104

## Standard story requirements

Objective, dependencies, deliverables, acceptance criteria, allowed and forbidden files, tests, security, documentation, and Definition of Done.

## Architecture track (parallel to EPIC-001, does not renumber PF-* stories)

- **ARCH-001 — Thailand-First Legal Intelligence Architecture — Done (architecture approved).** Populated `docs/architecture/01_OneLegalPro_Constitution.md`, `docs/architecture/05_AI_Architecture.md`, `docs/architecture/08_Roadmap.md`; created `docs/adr/ADR-002-Thailand-First-Legal-Intelligence.md` (Accepted) and `docs/architecture/09_Legal_Intelligence_Architecture.md`.
- **ARCH-002 — White-Label Platform & Multi-Tenant Branding Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 10–11), `docs/architecture/08_Roadmap.md` (EPIC-003); created `docs/adr/ADR-003-White-Label-Platform.md` (Accepted) and `docs/architecture/10_White_Label_Platform_Architecture.md`. `docs/architecture/02_Product_Requirements.md` and `docs/architecture/04_Security_Architecture.md` remain empty placeholders, consistent with the ARCH-001 precedent.
- **ARCH-003 — Communications Hub Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 12–13), `docs/architecture/05_AI_Architecture.md` (Communications Hub AI), `docs/architecture/08_Roadmap.md` (EPIC-004); created `docs/adr/ADR-004-Communications-Hub.md` (Accepted) and `docs/architecture/11_Communications_Hub_Architecture.md`. `docs/architecture/02_Product_Requirements.md` and `docs/architecture/04_Security_Architecture.md` remain empty placeholders, consistent with the ARCH-001 precedent.
- **ARCH-004 — Website & Client Portal (Digital Presence Platform) Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 14–15), `docs/architecture/08_Roadmap.md` (EPIC-005); created `docs/adr/ADR-005-Website-Client-Portal.md` (Accepted) and `docs/architecture/12_Website_Client_Portal_Architecture.md`. Consolidates the previously unarchitected "Digital Presence" and "Client Portal" later-phase names into one bounded context. `docs/architecture/02_Product_Requirements.md`, `docs/architecture/04_Security_Architecture.md`, and `docs/architecture/05_AI_Architecture.md` were not modified — the embedded AI receptionist reuses ARCH-003's AI governance unchanged.
- **ARCH-005 — Practice Management Core Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 16–17), `docs/architecture/08_Roadmap.md` (EPIC-006); created `docs/adr/ADR-006-Practice-Management-Core.md` (Accepted) and `docs/architecture/13_Practice_Management_Architecture.md`. Resolves the "future Practice Management module" dependency named by ARCH-003 and ARCH-004, and consolidates the previously unarchitected "Legal Practice Core" later-phase name into one bounded context. `docs/architecture/02_Product_Requirements.md`, `docs/architecture/04_Security_Architecture.md`, and `docs/architecture/05_AI_Architecture.md` remain untouched, same precedent as ARCH-004.
- **ARCH-006 — Document & Knowledge Management Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 18–21), `docs/architecture/05_AI_Architecture.md` (Document Intelligence AI, Knowledge Intelligence and RAG), `docs/architecture/08_Roadmap.md` (EPIC-007); created `docs/adr/ADR-007-Document-Knowledge-Management.md` (Accepted) and `docs/architecture/14_Document_Knowledge_Management_Architecture.md`. Resolves the "future Documents bounded context" dependency named by ARCH-003 (attachment extraction), ARCH-004 (Client Portal Documents surface, Document Upload Widget), and ARCH-005 (Matter Timeline, Matter Dashboard), and consolidates the previously unarchitected "Documents" later-phase name into one bounded context. `docs/architecture/02_Product_Requirements.md`, `docs/architecture/03_Database_Design.md`, `docs/architecture/04_Security_Architecture.md`, `docs/architecture/06_Marketplace.md`, and `docs/architecture/07_API_Standards.md` remain empty placeholders, consistent with the ARCH-001 precedent. The story ID is ARCH-006; the architecture document is numbered 14 (`ARCH-014`), continuing the existing document-number sequence.
- **ARCH-007 — Billing, Trust Accounting & Finance Architecture — Done (architecture approved).** Updated `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 22–25), `docs/architecture/05_AI_Architecture.md` (Financial AI), `docs/architecture/08_Roadmap.md` (EPIC-008); created `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md` (Accepted) and `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md`. Defines one bounded context with three explicitly separated financial domain areas — Billing/Accounts Receivable, Client Money/Trust Accounting, and Firm Finance/Accounting. Resolves the "future Billing bounded context" dependency named by ARCH-003 (Invoice linking), ARCH-004 (Client Portal Invoices/Payments, Payment Widget), ARCH-005 (Matter Timeline, Matter Dashboard billing panels), and ARCH-006 (rendered invoice artifact ownership), and consolidates the previously unarchitected "Commercial Operations" later-phase name into one bounded context. `docs/architecture/02_Product_Requirements.md`, `docs/architecture/03_Database_Design.md`, `docs/architecture/04_Security_Architecture.md`, `docs/architecture/06_Marketplace.md`, and `docs/architecture/07_API_Standards.md` remain empty placeholders, consistent with the ARCH-001 precedent. The story ID is ARCH-007; the architecture document is numbered 15 (`ARCH-015`), continuing the existing document-number sequence.

# EPIC-002 — Legal Intelligence (proposed, not scheduled)

Architecture is approved (ARCH-001). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (jurisdiction foundation, legal-source aggregate and metadata, ingestion pipeline, translation linking, citation engine, disclaimer enforcement, Thai legal search, AI retrieval, court-decision ingestion, firm annotations and saved research, amendment tracking and legal graph) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories.

# EPIC-003 — White-Label Platform (proposed, not scheduled)

Architecture is approved (ARCH-002). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (theme token schema and Branding Resolver foundation, `BrandProfile` aggregate and asset management, email and PDF branding integration, AI assistant branding, custom domain registration and verification, automated SSL provisioning, accessibility guardrails, theme marketplace integration) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories. The Digital Presence and Client Portal epics depend on this epic reaching at least its token-schema and `BrandProfile` foundation stages.

# EPIC-004 — Communications Hub (proposed, not scheduled)

Architecture is approved (ARCH-003). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (channel-neutral thread/message foundation, email adapters, messaging-platform adapters, website/portal chat with AI receptionist, human handoff, business-object linking, AI assistance and provenance, Communication Inbox, privacy/retention/legal hold, analytics) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories. The Digital Presence and Client Portal epics depend on this epic reaching at least its channel-neutral thread/message foundation and website/portal chat adapter stages.

# EPIC-005 — Digital Presence Platform (proposed, not scheduled)

Architecture is approved (ARCH-004). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (`DigitalPresenceProfile` and deployment-model foundation, Content Management and Website Builder, Client Portal authentication, Practice Management read-surfaces, Booking System, AI Receptionist and widget integration, Embedded Component Framework, Knowledge Publishing, SEO and accessibility hardening, enterprise API/CMS integration) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories. This epic consolidates the previously separate "Digital Presence" and "Client Portal" names elsewhere in this backlog and depends on EPIC-003 reaching its Branding Resolver foundation and EPIC-004 reaching its channel-neutral thread/message foundation and website/portal chat adapter stages, and on EPIC-006 reaching its `Client`/`Matter` foundation stages.

# EPIC-006 — Practice Management Core (proposed, not scheduled)

Architecture is approved (ARCH-005). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (`Client`/`Organization`/`Contact` foundation, `Matter` aggregate/lifecycle/`MatterNumber`, `PracticeArea` taxonomy, `MatterTeam` and role-based permissions, `Task`/`Appointment`/`Note` aggregates, Ethical Walls, Conflict Checking, Matter Timeline read-model, Matter Dashboard, integration hardening with Communications and Digital Presence) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories. This epic consolidates the previously unarchitected "Legal Practice Core" name elsewhere in this backlog and resolves the "future Practice Management module" dependency EPIC-004 and EPIC-005 already named. As the platform's Core Domain, its `Client`/`Matter` foundation stages are a practical prerequisite for EPIC-004's business-object-linking stage, EPIC-005's Practice Management read-surfaces and Booking System stages, and EPIC-007's document access-control stages, regardless of this backlog's listing order.

# EPIC-007 — Documents & Knowledge Management (proposed, not scheduled)

Architecture is approved (ARCH-006). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (document record/immutable versioning/provenance/Firm isolation; private storage, secure delivery, upload validation, scanning and quarantine; Matter associations, Ethical Walls, document access policy and co-client portal audience; document metadata, search, OCR and document intelligence; Knowledge Item, immutable Knowledge version, taxonomy and collection foundation; precedent, clause, template, playbook, practice-note and research-note management; Matter-to-knowledge curation, confidentiality review, de-identification and approval; Knowledge review, supersession, retirement, ownership and staleness management; permission-aware Knowledge search and governed AI/RAG; retention, legal hold, audit, Digital Presence publication and integration hardening) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories.

This epic covers **two explicitly separated domain areas** — Document Management (the source artifact) and Knowledge Management (curated, approved, reusable Firm know-how) — within one bounded context whose proposed module remains `Documents`. It consolidates the previously unarchitected "Documents" name elsewhere in this backlog and resolves the "future Documents bounded context" dependency EPIC-004 (attachment extraction), EPIC-005 (Client Portal Documents surface, Document Upload Widget), and EPIC-006 (Matter Timeline, Matter Dashboard) already named. Its access-control and curation stages depend on EPIC-006 reaching its `Client`/`Matter`/`MatterClient` and Ethical Wall stages, since both areas derive Matter-linked access from Practice Management's published contracts rather than implementing their own. Legal Intelligence retains ownership of official law, and Digital Presence retains ownership of public editorial content and publication.

E-signature, court e-filing, advanced document automation, collaborative editing, knowledge analytics, cross-matter precedent comparison, automated clause-conflict detection, multilingual knowledge, expertise location, Workflow consumption of playbooks, and Marketplace distribution of knowledge or template packages are named as future capabilities in `docs/architecture/14_Document_Knowledge_Management_Architecture.md` and are neither architected in detail nor proposed as stages here. `docs/architecture/06_Marketplace.md` remains an empty placeholder, and cross-Firm knowledge distribution remains prohibited.

# EPIC-008 — Billing, Trust Accounting & Finance (proposed, not scheduled)

Architecture is approved (ARCH-007). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (adoption of the PF-045 Foundation `Money`/`Currency` contract, Billing-specific `ExchangeRate` provenance, Firm accounting configuration and financial-policy versioning; billing arrangements, rates, time, fees, expenses and approval; invoices, tax documents, credit/debit notes and accounts receivable; payments, allocations, refunds, chargebacks and provider reconciliation; client-money/trust account, ledger and subledger foundation; trust transactions, authorization, segregation of duties and three-way reconciliation; general ledger, chart of accounts, journal posting and accounting periods; bank reconciliation, multi-currency, tax configuration and financial reporting; Practice Management, Documents, Communications and Client Portal integration; Financial AI, analytics, audit, security and operational hardening) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. **None of these stages carry approved story IDs yet, none is assigned a `PF-*` number, and none displace or renumber existing `PF-*` stories.**

This epic covers **three explicitly separated financial domain areas** — Billing/Accounts Receivable, Client Money/Trust Accounting, and Firm Finance/Accounting — within one bounded context whose proposed module is `Billing`. It consolidates the previously unarchitected "Commercial Operations" name elsewhere in this backlog and resolves the "future Billing bounded context" dependency EPIC-004 (Invoice linking and delivery), EPIC-005 (Client Portal Invoices/Payments surfaces and Payment Widget), EPIC-006 (Matter Timeline and Matter Dashboard billing panels), and EPIC-007 (rendered invoice artifact ownership) already named. Its stages depend on **`PF-045 — Money` in this epic's own Foundation Library section above**, which supplies the platform-wide exact-decimal `Money`/`Currency` contract Billing consumes and must never duplicate — EPIC-008 depends on PF-045 without scheduling, duplicating, renumbering, or marking it complete, and PF-045 remains an unscheduled Sprint 0.3 backlog item. It further depends on EPIC-006 reaching its `Client`/`Matter`/`MatterClient` and Ethical Wall stages, EPIC-007's rendered-artifact foundation, EPIC-005's Client Portal and Payment Widget, EPIC-004's Invoice linking and delivery, and a future Identity/Security capability for actor identity and segregation-of-duties assignments. Practice Management retains ownership of Client/Matter/Ethical Walls, Documents retains ownership of rendered artifacts, Digital Presence retains ownership of portal presentation, Communications retains ownership of delivery, and Legal Intelligence retains ownership of official law.

Accounts payable, procurement, payroll, fixed assets, budgeting, advanced treasury, an external accountant/auditor portal, banking feeds and open banking, e-tax/e-invoice provider integrations, additional jurisdiction packages, financial analytics and profitability analysis, advanced collections, and mobile payment experiences are named as future capabilities in `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` and are neither architected in detail nor proposed as stages here. No tax rate is hardcoded, no compliance is claimed, no payment provider is selected, no cryptocurrency is contemplated, OneLegalPro takes no custody of funds, and AI holds no financial authority.
