# OneLegalPro Documentation Index

This is the complete, navigable index of OneLegalPro's documentation. Start with [Where to start](#where-to-start) if you are new, and see [Authority and precedence](#authority-and-precedence) if two documents appear to disagree.

## Where to start

1. [`AGENTS.md`](../AGENTS.md) — required reading before any change, AI-assisted or not.
2. [`docs/PROJECT_STATUS.md`](PROJECT_STATUS.md) — what is done, what is current, what is next.
3. [`docs/architecture/01_OneLegalPro_Constitution.md`](architecture/01_OneLegalPro_Constitution.md) — the constitutional rules every module and story must follow.
4. [`../CONTRIBUTING.md`](../CONTRIBUTING.md) — how to branch, commit, and open a pull request.

## Governance and repository standards

Repository-wide rules that apply regardless of which module or story is being worked on.

- [`AGENTS.md`](../AGENTS.md) — AI development guide: source-of-truth list, per-module rules (Legal Intelligence, White-Label, Communications, Digital Presence, Practice Management, Document & Knowledge Management, Billing/Trust Accounting & Finance, Identity/Security & Access Control, API & Integration Platform, AI Copilot & Workflow Automation), architecture layering, and approval gates.
- [`CONTRIBUTING.md`](../CONTRIBUTING.md) — branch strategy, GitHub `main` ruleset, Conventional Commits, Semantic Versioning, pull request process, testing/documentation requirements, and security/secret-handling rules.

## Project tracking

- [`PROJECT_STATUS.md`](PROJECT_STATUS.md) — the authoritative, continuously updated record of the current story, next story, upcoming sequence, and completed work. Update this file after every completed story.

## Architecture

Approved architecture documents, numbered by topic. A document with **0 lines / placeholder** below is a reserved, currently empty file — it exists in the repository but has no approved content yet; do not treat it as populated architecture.

| Document | Status |
|---|---|
| [`01_OneLegalPro_Constitution.md`](architecture/01_OneLegalPro_Constitution.md) | Approved — constitutional; prevails over all other architecture. |
| [`02_Product_Requirements.md`](architecture/02_Product_Requirements.md) | Empty placeholder, pending its own dedicated story. |
| [`03_Database_Design.md`](architecture/03_Database_Design.md) | Empty placeholder, pending its own dedicated story. |
| [`04_Security_Architecture.md`](architecture/04_Security_Architecture.md) | **Approved** — platform-wide security baseline (principles, protected assets, threat model, trust boundaries, control families, classification, failure handling, compliance posture). Populated by ARCH-008. |
| [`05_AI_Architecture.md`](architecture/05_AI_Architecture.md) | Approved — AI governance for Legal Intelligence, Communications Hub, Document Intelligence, Knowledge Intelligence/RAG, and Financial AI. |
| [`06_Marketplace.md`](architecture/06_Marketplace.md) | Empty placeholder, pending its own dedicated story. |
| [`07_API_Standards.md`](architecture/07_API_Standards.md) | **Approved** — platform-wide normative API standard (naming, versioning, representation, pagination, errors, auth, Firm isolation, idempotency, webhooks, imports/exports, security, observability, deprecation, review). Populated by ARCH-009. |
| [`08_Roadmap.md`](architecture/08_Roadmap.md) | Approved (epic sequencing) — proposed, not-yet-scheduled implementation stages for each epic. |
| [`09_Legal_Intelligence_Architecture.md`](architecture/09_Legal_Intelligence_Architecture.md) | Approved (conceptual architecture); implementation stories are proposed only. |
| [`10_White_Label_Platform_Architecture.md`](architecture/10_White_Label_Platform_Architecture.md) | Approved (conceptual architecture); implementation stories are proposed only. |
| [`11_Communications_Hub_Architecture.md`](architecture/11_Communications_Hub_Architecture.md) | Approved (conceptual architecture); implementation stories are proposed only. |
| [`12_Website_Client_Portal_Architecture.md`](architecture/12_Website_Client_Portal_Architecture.md) | Approved (conceptual architecture); implementation stories are proposed only. |
| [`13_Practice_Management_Architecture.md`](architecture/13_Practice_Management_Architecture.md) | Approved (conceptual architecture); implementation stories are proposed only. |
| [`14_Document_Knowledge_Management_Architecture.md`](architecture/14_Document_Knowledge_Management_Architecture.md) | Approved (conceptual architecture); implementation stories are proposed only. |
| [`15_Billing_Trust_Accounting_Finance_Architecture.md`](architecture/15_Billing_Trust_Accounting_Finance_Architecture.md) | Approved (conceptual architecture); implementation stories are proposed only. |
| [`16_Identity_Security_Access_Control_Architecture.md`](architecture/16_Identity_Security_Access_Control_Architecture.md) | Approved (conceptual architecture); implementation stories are proposed only. |
| [`17_API_Integration_Platform_Architecture.md`](architecture/17_API_Integration_Platform_Architecture.md) | Approved (conceptual architecture); implementation stories are proposed only. |
| [`18_AI_Copilot_Workflow_Automation_Architecture.md`](architecture/18_AI_Copilot_Workflow_Automation_Architecture.md) | Approved (conceptual architecture); implementation stories are proposed only. |

## Architecture Decision Records

Records of specific, accepted architecture decisions. Each ADR is referenced from the architecture document(s) it justifies.

| Document | Status |
|---|---|
| [`ADR-001-Architecture-First.md`](adr/ADR-001-Architecture-First.md) | Empty stub. Established the architecture-first principle by name; referenced by later ADRs, but has never itself held content. |
| [`ADR-002-Thailand-First-Legal-Intelligence.md`](adr/ADR-002-Thailand-First-Legal-Intelligence.md) | Accepted. |
| [`ADR-003-White-Label-Platform.md`](adr/ADR-003-White-Label-Platform.md) | Accepted. |
| [`ADR-004-Communications-Hub.md`](adr/ADR-004-Communications-Hub.md) | Accepted. |
| [`ADR-005-Website-Client-Portal.md`](adr/ADR-005-Website-Client-Portal.md) | Accepted. |
| [`ADR-006-Practice-Management-Core.md`](adr/ADR-006-Practice-Management-Core.md) | Accepted. |
| [`ADR-007-Document-Knowledge-Management.md`](adr/ADR-007-Document-Knowledge-Management.md) | Accepted. |
| [`ADR-008-Billing-Trust-Accounting-Finance.md`](adr/ADR-008-Billing-Trust-Accounting-Finance.md) | Accepted. |
| [`ADR-009-Identity-Security-Access-Control.md`](adr/ADR-009-Identity-Security-Access-Control.md) | Accepted. |
| [`ADR-010-API-Integration-Platform.md`](adr/ADR-010-API-Integration-Platform.md) | Accepted. |
| [`ADR-011-AI-Copilot-Workflow-Automation.md`](adr/ADR-011-AI-Copilot-Workflow-Automation.md) | Accepted. |

## Domain design

- [`domain/06_Laravel_Module_Blueprint.md`](domain/06_Laravel_Module_Blueprint.md) — how approved architecture translates into the Laravel modular-monolith structure: module layout, dependency direction, naming conventions, cross-module communication rules, tenancy/security discipline, and prohibited patterns.

## Implementation planning

- [`implementation/01_Implementation_Sprint_Plan.md`](implementation/01_Implementation_Sprint_Plan.md) — the approved delivery sequence (Phase 0 sprints and their `PF-*` stories), plus the parallel architecture track.
- [`implementation/02_AI_Developer_Playbook.md`](implementation/02_AI_Developer_Playbook.md) — the rules governing any AI coding assistant working in this repository.
- [`implementation/03_Engineering_Backlog.md`](implementation/03_Engineering_Backlog.md) — the story-level backlog for the current epic, with status per story.

## Authority and precedence

When guidance conflicts, this is the order of authority, per `AGENTS.md`:

1. **[`architecture/01_OneLegalPro_Constitution.md`](architecture/01_OneLegalPro_Constitution.md)** — constitutional; prevails over every other architecture, ADR, domain, or implementation document.
2. The rest of **[`architecture/`](architecture)** and **[`adr/`](adr)** — epic-level and per-decision architecture.
3. **[`domain/06_Laravel_Module_Blueprint.md`](domain/06_Laravel_Module_Blueprint.md)** — how that architecture is expressed in code structure.
4. **[`implementation/01_Implementation_Sprint_Plan.md`](implementation/01_Implementation_Sprint_Plan.md)** and **[`implementation/03_Engineering_Backlog.md`](implementation/03_Engineering_Backlog.md)** — approved, scheduled delivery.
5. **[`PROJECT_STATUS.md`](PROJECT_STATUS.md)** — current status against that plan.

If a request conflicts with approved architecture, stop and request an architecture decision rather than resolving the conflict unilaterally.

**A roadmap or proposed staged-delivery list is not a scheduled story.** The staged implementation lists inside `architecture/08_Roadmap.md` and the individual epic architecture documents (for EPIC-002 through EPIC-011) are proposed only. None of them carry approved `PF-*` (or other) story IDs, and none are scheduled work, until they are separately entered and approved in `implementation/03_Engineering_Backlog.md` and `implementation/01_Implementation_Sprint_Plan.md`.
