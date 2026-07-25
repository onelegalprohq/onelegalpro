# IMP-001 — Implementation Sprint Plan

**Status:** Approved

## Purpose

Define the approved delivery sequence for OneLegalPro. No work proceeds outside an approved story or sprint.

## Global rules

Every sprint must preserve module boundaries, Firm isolation, authorization, auditability, tests, documentation, and deployability.

## Phase 0 — Platform Foundation

### Sprint 0.1 — Repository and Environment
- PF-001 Repository Structure
- PF-002 Git & Repository Standards
- PF-003 Repository Documentation
- PF-010 Docker Development Environment
- PF-011 Local Environment & Configuration
- PF-012 Development Tooling

### Sprint 0.2 — Quality and CI
- PF-020 Laravel Pint
- PF-021 PHPStan
- PF-022 Rector, if approved
- PF-023 Git Hooks
- PF-030 GitHub Actions
- PF-031 Quality Gates
- PF-032 Security Scanning

### Sprint 0.3 — Foundation Library
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

### Sprint 0.4 — Platform Runtime
- PF-060 Module Loader
- PF-061 Module Service Provider
- PF-062 Module Generator
- PF-063 Module Registration
- PF-070 Command Bus
- PF-071 Query Bus
- PF-072 Handler Registration
- PF-073 Transaction Manager
- PF-080 Firm Context
- PF-081 Tenant Resolver
- PF-082 Tenant Middleware
- PF-090 Event Dispatcher
- PF-091 Transactional Outbox
- PF-092 Event Publisher
- PF-093 Consumer foundation
- PF-100 through PF-104 Testing Infrastructure

## Architecture track (parallel to sprint numbering)

**ARCH-001 — Thailand-First Legal Intelligence Architecture: architecture approved (Completed).** See `docs/architecture/01_OneLegalPro_Constitution.md`, `docs/architecture/05_AI_Architecture.md`, `docs/architecture/08_Roadmap.md`, `docs/architecture/09_Legal_Intelligence_Architecture.md`, and `docs/adr/ADR-002-Thailand-First-Legal-Intelligence.md`.

**ARCH-002 — White-Label Platform & Multi-Tenant Branding Architecture: architecture approved (Completed).** See `docs/architecture/01_OneLegalPro_Constitution.md`, `docs/architecture/08_Roadmap.md`, `docs/architecture/10_White_Label_Platform_Architecture.md`, and `docs/adr/ADR-003-White-Label-Platform.md`.

**ARCH-003 — Communications Hub Architecture: architecture approved (Completed).** See `docs/architecture/01_OneLegalPro_Constitution.md`, `docs/architecture/05_AI_Architecture.md`, `docs/architecture/08_Roadmap.md`, `docs/architecture/11_Communications_Hub_Architecture.md`, and `docs/adr/ADR-004-Communications-Hub.md`.

**ARCH-004 — Website & Client Portal (Digital Presence Platform) Architecture: architecture approved (Completed).** See `docs/architecture/01_OneLegalPro_Constitution.md`, `docs/architecture/08_Roadmap.md`, `docs/architecture/12_Website_Client_Portal_Architecture.md`, and `docs/adr/ADR-005-Website-Client-Portal.md`. Consolidates the previously separate "Digital Presence" and "Client Portal" later-phase names below into one bounded context.

**ARCH-005 — Practice Management Core Architecture: architecture approved (Completed).** See `docs/architecture/01_OneLegalPro_Constitution.md`, `docs/architecture/08_Roadmap.md`, `docs/architecture/13_Practice_Management_Architecture.md`, and `docs/adr/ADR-006-Practice-Management-Core.md`. Resolves the "future Practice Management module" dependency named by ARCH-003 and ARCH-004, and consolidates the previously unarchitected "Legal Practice Core" later-phase name below into one bounded context. Practice Management is the platform's Core Domain: although architected after Communications and Digital Presence, its `Client`/`Matter`/`Task`/`Appointment` foundation stages are a practical prerequisite for those epics' business-object-linking and Client-Portal-read-surface stages — see the retroactive dependency note in `docs/architecture/08_Roadmap.md`.

**ARCH-006 — Document & Knowledge Management Architecture: architecture approved (Completed).** See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 18–21), `docs/architecture/05_AI_Architecture.md`, `docs/architecture/08_Roadmap.md` (EPIC-007), `docs/architecture/14_Document_Knowledge_Management_Architecture.md`, and `docs/adr/ADR-007-Document-Knowledge-Management.md`. Resolves the "future Documents bounded context" dependency named by ARCH-003 (attachment extraction), ARCH-004 (Client Portal Documents surface, Document Upload Widget), and ARCH-005 (Matter Timeline, Matter Dashboard), and consolidates the previously unarchitected "Documents" later-phase name below into one bounded context. The story ID is ARCH-006; the architecture document is numbered 14 (`ARCH-014`), continuing the existing document-number sequence rather than the story sequence.

**ARCH-007 — Billing, Trust Accounting & Finance Architecture: architecture approved (Completed).** See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 22–25), `docs/architecture/05_AI_Architecture.md`, `docs/architecture/08_Roadmap.md` (EPIC-008), `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md`, and `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md`. Defines one bounded context with three explicitly separated financial domain areas — Billing/Accounts Receivable, Client Money/Trust Accounting, and Firm Finance/Accounting — resolving the "future Billing bounded context" dependency named by ARCH-003 (Invoice linking), ARCH-004 (Client Portal Invoices/Payments, Payment Widget), ARCH-005 (Matter Timeline and Dashboard billing panels), and ARCH-006 (rendered invoice artifact ownership), and consolidating the previously unarchitected "Commercial Operations" later-phase name below into one bounded context. The story ID is ARCH-007; the architecture document is numbered 15 (`ARCH-015`), continuing the existing document-number sequence rather than the story sequence.

**ARCH-008 — Identity, Security & Access Control Architecture: architecture approved (Completed).** See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 26–30), `docs/architecture/04_Security_Architecture.md` (populated platform-wide security baseline), `docs/architecture/08_Roadmap.md` (EPIC-009), `docs/architecture/16_Identity_Security_Access_Control_Architecture.md`, and `docs/adr/ADR-009-Identity-Security-Access-Control.md`. Establishes `IdentityAccess` as a separate bounded context while keeping security cross-cutting, and resolves the "future Identity/Security" dependency named by ARCH-004 (Client Portal authentication), ARCH-005 (Practice Management actor references and `MatterTeam` assignments), ARCH-006 (Documents/Knowledge actor references), and ARCH-007 (Billing actor identity, permissions, and segregation of duties). Corrects the Digital Presence authentication-ownership boundary in `docs/adr/ADR-005-Website-Client-Portal.md` and `docs/architecture/12_Website_Client_Portal_Architecture.md` without removing any approved Client Portal capability. The story ID is ARCH-008; the architecture document is numbered 16 (`ARCH-016`), continuing the existing document-number sequence rather than the story sequence.

Architecture approval does not schedule implementation. No `PF-*` story numbering is affected. Any Legal Intelligence implementation stories (see EPIC-002 staging in `docs/architecture/08_Roadmap.md`), White-Label Platform implementation stories (see EPIC-003 staging in `docs/architecture/08_Roadmap.md`), Communications Hub implementation stories (see EPIC-004 staging in `docs/architecture/08_Roadmap.md`), Digital Presence Platform implementation stories (see EPIC-005 staging in `docs/architecture/08_Roadmap.md`), Practice Management Core implementation stories (see EPIC-006 staging in `docs/architecture/08_Roadmap.md`), Documents implementation stories (see EPIC-007 staging in `docs/architecture/08_Roadmap.md`), Billing, Trust Accounting & Finance implementation stories (see EPIC-008 staging in `docs/architecture/08_Roadmap.md`), or Identity, Security & Access Control implementation stories (see EPIC-009 staging in `docs/architecture/08_Roadmap.md`) are **proposed only** and must be added to this Sprint Plan and to `docs/implementation/03_Engineering_Backlog.md` as their own approved entries before implementation begins. The Digital Presence and Client Portal phases below depend on EPIC-003 reaching at least its token-schema and `BrandProfile` foundation stages, and on EPIC-004 reaching at least its channel-neutral thread/message foundation and website/portal chat adapter stages. Both, along with Communications' business-object linking, additionally depend on EPIC-006 reaching at least its `Client`/`Matter` foundation stages. EPIC-007's access-control stages depend on EPIC-006 reaching its `Client`/`Matter`/`MatterClient` and Ethical Wall stages; EPIC-004's attachment handling and EPIC-005's Client Portal Documents surface and Document Upload Widget in turn depend on EPIC-007's foundation stages. EPIC-008 depends on EPIC-006's `Client`/`Matter`/`MatterClient` and Ethical Wall foundations, EPIC-007's rendered-artifact foundation, EPIC-005's Client Portal and Payment Widget, EPIC-004's Invoice linking and delivery, and EPIC-009 for actor identity and segregation-of-duties assignments. EPIC-009 in turn resolves the identity dependency EPIC-005, EPIC-006, EPIC-007, and EPIC-008 each named; every module's Firm-bound authorization depends on Platform Foundation tenancy (PF-080/PF-081/PF-082) plus EPIC-009 identity results, and those `PF-*` stories are neither scheduled nor renumbered.

## Later phases

Platform Core; **Legal Practice Core (now governed by Practice Management Core, EPIC-006, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Documents (now governed by Documents & Knowledge Management, EPIC-007, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Commercial Operations (now governed by Billing, Trust Accounting & Finance, EPIC-008, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Legal Intelligence (EPIC-002, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **White-Label Platform (EPIC-003, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Communications Hub (EPIC-004, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Digital Presence; Client Portal (both now governed by Digital Presence Platform, EPIC-005, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; Workflow; Integrations; Reporting; Academic Edition.

## Human approval gates

Approval is mandatory before database, authentication, authorization, public API, security model, AI behavior, billing, payment, or production deployment changes.

## Definition of Done

Acceptance criteria met, tests and static analysis pass, security and architecture reviewed, documentation updated, no critical defects, and human approval recorded.
