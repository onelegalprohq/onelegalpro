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
- PF-002 Git & Repository Standards — next
- PF-003 Repository Documentation

## Development Environment
- PF-010 Docker Development Environment
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

# EPIC-002 — Legal Intelligence (proposed, not scheduled)

Architecture is approved (ARCH-001). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (jurisdiction foundation, legal-source aggregate and metadata, ingestion pipeline, translation linking, citation engine, disclaimer enforcement, Thai legal search, AI retrieval, court-decision ingestion, firm annotations and saved research, amendment tracking and legal graph) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories.

# EPIC-003 — White-Label Platform (proposed, not scheduled)

Architecture is approved (ARCH-002). Implementation is **not yet scheduled** — the staged story list in `docs/architecture/08_Roadmap.md` (theme token schema and Branding Resolver foundation, `BrandProfile` aggregate and asset management, email and PDF branding integration, AI assistant branding, custom domain registration and verification, automated SSL provisioning, accessibility guardrails, theme marketplace integration) is proposed only. Each stage requires its own approved story entry here, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carry approved story IDs yet, and none displace or renumber existing `PF-*` stories. The Digital Presence and Client Portal epics depend on this epic reaching at least its token-schema and `BrandProfile` foundation stages.
