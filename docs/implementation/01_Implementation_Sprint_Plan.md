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

## Later phases

Platform Core; Legal Practice Core; Documents; Commercial Operations; Legal Intelligence; Digital Presence; Client Portal; Workflow; Integrations; Reporting; Academic Edition.

## Human approval gates

Approval is mandatory before database, authentication, authorization, public API, security model, AI behavior, billing, payment, or production deployment changes.

## Definition of Done

Acceptance criteria met, tests and static analysis pass, security and architecture reviewed, documentation updated, no critical defects, and human approval recorded.
