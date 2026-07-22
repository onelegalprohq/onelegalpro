# IMP-002 — AI Developer Playbook

**Version:** 1.0  
**Status:** Approved

## Purpose

Govern Claude Code, Codex, and other AI coding assistants working on OneLegalPro.

## Authority

Approved architecture and implementation documents are authoritative. When requirements conflict with architecture, stop, describe the conflict, propose options, and wait for approval.

## Required pre-implementation analysis

Identify:

- Story ID and module
- Use case and aggregate
- Authorization and Firm-isolation requirements
- Ethical Wall implications
- Database schema
- Events and audit requirements
- Required tests

Do not invent missing legal or business rules.

## Scope control

Only modify files needed for the approved task. Do not refactor unrelated code, reformat the repository, upgrade dependencies, edit historical migrations, delete tests, or change architecture without approval.

## Architecture rules

- Thin controllers
- Application handlers coordinate use cases
- Domain objects enforce invariants
- Infrastructure implements adapters
- Eloquent records are persistence-only
- Cross-module access uses contracts, commands, queries, events, or workflows
- External APIs are not called inside database transactions

## Security rules

Never weaken authentication, authorization, Firm tenancy, Ethical Walls, classification controls, encryption, or auditability. Never commit or log secrets or confidential content.

## AI governance

AI output is advisory. AI must not directly change authoritative legal records, make binding legal decisions, approve invoices or payments, override access controls, or present generated material as verified law.

## Database rules

PostgreSQL is authoritative. Use UUIDv7. Each module owns its schema and migrations. Historical migrations are immutable. Preserve referential integrity.

## Testing

Use unit, application, integration, feature, security, and architecture tests as appropriate. Never fabricate test results.

## Mandatory approval points

Stop before authentication, authorization, database redesign, new runtime dependencies, billing, payment, AI governance, breaking APIs, security-control removal, or destructive operations.

## Final report

Summary; files created/modified; database changes; events; tests added/executed; security; documentation; assumptions; risks; architecture compliance.
