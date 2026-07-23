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
