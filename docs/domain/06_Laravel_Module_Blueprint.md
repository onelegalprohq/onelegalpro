# DOM-006 — Laravel Module Blueprint

**Status:** Approved

## Purpose

Translate approved OneLegalPro architecture into a Laravel modular monolith without allowing framework conventions to replace domain boundaries.

## Structure

```text
app/
├── Foundation/
└── Modules/
```

`app/Foundation` contains reusable technical primitives. `app/Modules` contains business capabilities.

## Standard module

```text
ModuleName/
├── Application/
├── Domain/
├── Infrastructure/
├── Interface/
├── Database/
├── Routes/
├── Tests/
├── Config/
├── ModuleServiceProvider.php
└── README.md
```

## Dependency direction

Interface → Application → Domain. Infrastructure depends on Application or Domain contracts. Domain never depends on Laravel, Eloquent, queues, HTTP, or SDKs.

## Naming

- Domain aggregate: `Matter`
- Persistence record: `MatterRecord`
- Repository contract: `MatterRepository`
- Adapter: `EloquentMatterRepository`
- Commands: imperative, such as `CreateMatter`
- Queries: descriptive, such as `GetMatterDetails`
- Events: past tense, such as `MatterCreated`

## Eloquent

Eloquent records handle mapping, casts, relationships, and scopes only. Business invariants belong in domain objects.

## Cross-module communication

Allowed: published contracts, commands, queries, domain events, workflows. Forbidden: shared Eloquent records, direct cross-schema writes, controller calls, or private repository access.

## Events and transactions

Aggregates record domain events. State changes and outbox records commit atomically. Consumers must be idempotent and support retries and dead-letter handling. External APIs are never called inside database transactions.

## Tenancy and security

An explicit FirmContext carries Firm ID, Actor ID, membership, and correlation ID. Tenant isolation is enforced in application logic, repositories, database policy where appropriate, and tests. Global scopes alone are insufficient. Ethical Walls apply to matters, documents, tasks, search, reports, exports, and AI retrieval.

## AI

AI never directly modifies authoritative records. Adoption requires explicit human action through the owning module.

## Prohibited patterns

Fat controllers, god services, business logic in Eloquent records, shared records across modules, UI-only authorization, direct AI writes, generic repositories without domain meaning, and unapproved destructive cascades.
