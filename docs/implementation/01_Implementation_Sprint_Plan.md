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
- PF-033 PostgreSQL Continuous Integration

**PF-033 is the Release 0.1 database-test prerequisite.** It moves the existing
`Application Tests` check from SQLite `:memory:` to an ephemeral PostgreSQL 16
service without renaming any required check. It must land before PF-080 begins.
Its story contract is recorded in
`docs/implementation/03_Engineering_Backlog.md`; listing it here assigns the
story identifier but does not approve or begin implementation.

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

**The numeric `PF-040`–`PF-049` list above is a story catalogue, not an execution order.** It enumerates the approved Foundation Library stories; it does not state the sequence in which they are implemented.

**Approved implementation order:**

**PF-049 → PF-047 → PF-042 → PF-048 → PF-044 → PF-041 → PF-043 → PF-040 → PF-045 → PF-046**

**Nothing was renamed, renumbered, merged, split, or deleted.** Every `PF-04x` number and catalogue entry above is preserved exactly as approved; only the implementation sequence is recorded, and it does not change any story's identity, scope, or number.

**Dependency reason (brief).** The order follows dependency direction rather than numbering. `PF-049` (exception hierarchy) is first because every later Foundation primitive throws through it. `PF-047` (Clock) is standalone and unblocks time-dependent primitives. `PF-042` (ValueObject) precedes `PF-048` (UUIDv7) and `PF-044` (BusinessIdentifier), which build on it; identifiers in turn precede `PF-041` (Entity), which precedes `PF-043` (DomainEvent) and `PF-040` (AggregateRoot). `PF-045` (Money) builds on `PF-042`. `PF-046` (Result) is last so it is designed against primitives that already exist rather than shaping them. The standing conventions for this layer are recorded in [`app/Foundation/README.md`](../../app/Foundation/README.md).

### Sprint 0.4 — Platform Runtime
> **Release 0.1 minimum-runtime note (approved architecture, ARCH-011 — not scheduled).** `docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md` Decision 3 identifies a **dependency-complete minimum subset** of this sprint required by Release 0.1: **`PF-080` Firm Context, `PF-081` Tenant Resolver, and `PF-082` Tenant Middleware are mandatory**, because every Firm-bound Release 0.1 capability depends on them and `docs/domain/06_Laravel_Module_Blueprint.md` requires tenant isolation enforced in application logic, repositories, and database policy rather than by global scopes alone; **`PF-091` Transactional Outbox and only its genuine prerequisites** are included where a committed audit or event fact must be durable with the state change that produced it. **The prerequisite analysis is recorded honestly in `docs/implementation/03_Engineering_Backlog.md` and is not complete** — which stories `PF-091` genuinely requires is determined by that story's own approved analysis, not asserted here. Buses, publishers, consumers, and module-generation tooling not established as prerequisites remain **deferred**. **Nothing in this note schedules, renumbers, reorders, renames, merges, splits, or deletes any story below, and architecture approval never schedules implementation.**

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

**ARCH-009 — API & Integration Platform Architecture: architecture approved (Completed).** See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 31–37), `docs/architecture/07_API_Standards.md` (populated platform-wide API standard), `docs/architecture/08_Roadmap.md` (EPIC-010), `docs/architecture/17_API_Integration_Platform_Architecture.md`, and `docs/adr/ADR-010-API-Integration-Platform.md`. Establishes `Integrations` as a supporting bounded context, resolving the "reserved for ARCH-009" Public API and Integration Platform dependency named by ARCH-004/Digital Presence and ARCH-008/IdentityAccess, alongside Communications', Billing's, and Documents' external-integration boundaries. IdentityAccess remains the sole owner of authentication and service-principal credentials, Practice Management remains the sole Ethical Wall authority, and Workflow orchestration is now governed by ARCH-010/ARCH-018 and the proposed EPIC-011 — `Workflow` consumes Integrations' verified events and delivery contracts without moving any integration ownership out of `Integrations`. The story ID is ARCH-009; the architecture document is numbered 17 (`ARCH-017`), continuing the existing document-number sequence rather than the story sequence.

**ARCH-010 — AI Copilot & Workflow Automation Architecture: architecture approved (Completed).** See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 38–44), `docs/architecture/05_AI_Architecture.md` (AI Copilot and Workflow Automation), `docs/architecture/08_Roadmap.md` (EPIC-011), `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md`, and `docs/adr/ADR-011-AI-Copilot-Workflow-Automation.md`. Establishes `Workflow` as a supporting bounded context with two explicitly separated domain areas — Workflow Orchestration and AI Copilot — resolving the "reserved for a future ARCH-010"/"future Workflow" dependency named by Constitution Article 37, ARCH-005/Practice Management, ARCH-006/Documents & Knowledge Management, ARCH-007/Billing, ARCH-008/IdentityAccess, and ARCH-009/Integrations. IdentityAccess remains the sole owner of authentication and service principals, Practice Management remains the sole Ethical Wall authority, and the AI Copilot remains assistive only — never an authorization or approval authority, with a defined, absolute list of non-delegable actions that can never be performed autonomously by AI regardless of Firm configuration. The story ID is ARCH-010; the architecture document is numbered 18 (`ARCH-018`), continuing the existing document-number sequence rather than the story sequence.

**ARCH-011 — Release 0.1 Rescope, Platform Administration Ownership, and Deferred-Control Decisions: Completed — architecture Approved.** Explicit owner approval was recorded on PR #30 on 29 July 2026. Constitution Articles 45–48 are Approved; the Release 0.1 product requirements and Platform Administration architecture are Approved; and ADR-012 through ADR-015 are Accepted. The decision establishes `PlatformAdministration` as a **narrowly bounded supporting context owning exactly `Firm`, `FirmProvisioning`, and `SubscriptionEntitlement`**, records **Release 0.1 — the OneLegalPro Matter Desk** as a founding-firm pilot slice, and records the bounded deferral of Ethical Walls and automated conflict checking. **Architecture approval schedules no implementation and authorizes no deployment or production access.** At ARCH-011 acceptance, `PF-040` remained next and Backlog; it has since been separately authorized, implemented, and completed and merged through PR #31 under its own entry in `docs/implementation/03_Engineering_Backlog.md`. The story ID is **ARCH-011** while the architecture document is numbered **19** (`ARCH-019`).

Architecture approval does not schedule implementation. No `PF-*` story numbering is affected. Any Legal Intelligence implementation stories (see EPIC-002 staging in `docs/architecture/08_Roadmap.md`), White-Label Platform implementation stories (see EPIC-003 staging in `docs/architecture/08_Roadmap.md`), Communications Hub implementation stories (see EPIC-004 staging in `docs/architecture/08_Roadmap.md`), Digital Presence Platform implementation stories (see EPIC-005 staging in `docs/architecture/08_Roadmap.md`), Practice Management Core implementation stories (see EPIC-006 staging in `docs/architecture/08_Roadmap.md`), Documents implementation stories (see EPIC-007 staging in `docs/architecture/08_Roadmap.md`), Billing, Trust Accounting & Finance implementation stories (see EPIC-008 staging in `docs/architecture/08_Roadmap.md`), Identity, Security & Access Control implementation stories (see EPIC-009 staging in `docs/architecture/08_Roadmap.md`), API & Integration Platform implementation stories (see EPIC-010 staging in `docs/architecture/08_Roadmap.md`), or AI Copilot & Workflow Automation implementation stories (see EPIC-011 staging in `docs/architecture/08_Roadmap.md`) are **proposed only** and must be added to this Sprint Plan and to `docs/implementation/03_Engineering_Backlog.md` as their own approved entries before implementation begins. The Digital Presence and Client Portal phases below depend on EPIC-003 reaching at least its token-schema and `BrandProfile` foundation stages, and on EPIC-004 reaching at least its channel-neutral thread/message foundation and website/portal chat adapter stages. Both, along with Communications' business-object linking, additionally depend on EPIC-006 reaching at least its `Client`/`Matter` foundation stages. EPIC-007's access-control stages depend on EPIC-006 reaching its `Client`/`Matter`/`MatterClient` and Ethical Wall stages; EPIC-004's attachment handling and EPIC-005's Client Portal Documents surface and Document Upload Widget in turn depend on EPIC-007's foundation stages. EPIC-008 depends on EPIC-006's `Client`/`Matter`/`MatterClient` and Ethical Wall foundations, EPIC-007's rendered-artifact foundation, EPIC-005's Client Portal and Payment Widget, EPIC-004's Invoice linking and delivery, and EPIC-009 for actor identity and segregation-of-duties assignments. EPIC-009 in turn resolves the identity dependency EPIC-005, EPIC-006, EPIC-007, and EPIC-008 each named; every module's Firm-bound authorization depends on Platform Foundation tenancy (PF-080/PF-081/PF-082) plus EPIC-009 identity results, and those `PF-*` stages are neither scheduled nor renumbered. EPIC-010 depends on EPIC-009 for authentication and service-principal identity and on Platform Foundation's `FirmContext` primitive for every Firm-bound external request; it resolves the Public API dependency EPIC-005, EPIC-008, and EPIC-009 each named, without scheduling, duplicating, or renumbering any `PF-*` stage. **EPIC-011 depends on EPIC-009 for identity and authorization, EPIC-006 for `Matter`/`Task`/Ethical Wall foundations, EPIC-007 for governed playbook content, and EPIC-010 for verified external triggers and delivery**; it resolves the "reserved for a future ARCH-010"/"future Workflow" dependency EPIC-006, EPIC-007, EPIC-008, EPIC-009, and EPIC-010 each named, without scheduling, duplicating, or renumbering any `PF-*` stage. **EPIC-012 — Platform Administration & Release 0.1 Matter Desk (proposed, see EPIC-012 staging in `docs/architecture/08_Roadmap.md`) resolves the `Firm` ownership gap** that EPIC-003 (per-Firm `BrandProfile`/`TenantDomain`), EPIC-006 (Firm-scoped aggregates), EPIC-009 (`FirmMembership`), and Platform Foundation (`FirmContext`) each referenced and none owned; it **depends on `PF-040` and on Platform Foundation tenancy (`PF-080`/`PF-081`/`PF-082`) plus `PF-091` and only its genuine prerequisites**, is **consumed by EPIC-009** at the authentication and membership boundaries, and **neither depends on nor pre-empts EPIC-008** — Billing remains the sole owner of Firm-to-client commercial and financial records, and `SubscriptionEntitlement` carries no monetary value, so **`PF-045` remains Backlog and deferred**. It schedules, duplicates, and renumbers no `PF-*` stage, and every EPIC-012 story is **Backlog**.

## Later phases

Platform Core; **Legal Practice Core (now governed by Practice Management Core, EPIC-006, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Documents (now governed by Documents & Knowledge Management, EPIC-007, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Commercial Operations (now governed by Billing, Trust Accounting & Finance, EPIC-008, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Integrations (now governed by API & Integration Platform, EPIC-010, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Legal Intelligence (EPIC-002, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **White-Label Platform (EPIC-003, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Communications Hub (EPIC-004, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Digital Presence; Client Portal (both now governed by Digital Presence Platform, EPIC-005, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Workflow (now governed by AI Copilot & Workflow Automation, EPIC-011, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; **Platform Administration & Release 0.1 Matter Desk (EPIC-012, proposed — staged in `docs/architecture/08_Roadmap.md`, not yet scheduled)**; Reporting; Academic Edition.

## Human approval gates

Approval is mandatory before database, authentication, authorization, public API, security model, AI behavior, billing, payment, or production deployment changes.

## Definition of Done

Acceptance criteria met, tests and static analysis pass, security and architecture reviewed, documentation updated, no critical defects, and human approval recorded.
