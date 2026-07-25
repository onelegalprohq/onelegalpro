# ADR-011 — AI Copilot & Workflow Automation

## Status

Accepted, subject to the repository's recorded human approval process.

## Context

Every approved architecture from ARCH-001 through ARCH-009 already names "Workflow" as a future, unarchitected capability it deliberately does not own:

- `docs/architecture/01_OneLegalPro_Constitution.md` Article 37 reserves "Workflow orchestration" as requiring its own separately approved architecture before any such capability exists.
- `docs/architecture/13_Practice_Management_Architecture.md` §17 names "workflow automation" as a future extension `Task` dependencies and `RecurrenceRule` are already designed to support.
- `docs/architecture/14_Document_Knowledge_Management_Architecture.md` §K13 and §K34 describe playbooks as "guidance, not workflow execution," explicitly naming "a future Workflow context" as the eventual consumer of approved playbooks.
- `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §4 and §60 name a "future Workflow" that "may orchestrate billing processes... but owns no financial state and can perform no action Billing's own authorization would refuse."
- `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §4 and `docs/adr/ADR-009-Identity-Security-Access-Control.md`'s domain-ownership table both list "Workflow state" as "reserved for the future Workflow architecture."
- `docs/architecture/17_API_Integration_Platform_Architecture.md` and `docs/adr/ADR-010-API-Integration-Platform.md` both explicitly name Workflow orchestration as "reserved for a future ARCH-010," distinguishing it from Integrations' own external-contract and delivery scope.

At the same time, `docs/architecture/05_AI_Architecture.md` already establishes AI governance for Legal Intelligence, Communications, Document Intelligence, Knowledge Intelligence/RAG, and Financial AI — but no architecture yet defines an internal, cross-domain professional Copilot capable of proposing multi-step action, nor the governed pipeline such a capability would need to execute anything at all.

Two structural risks follow from this gap. First, **without an owned orchestration boundary, any future automation would be built ad hoc inside whichever domain needed it first**, duplicating retry, approval, and compensation logic per domain exactly as `docs/domain/06_Laravel_Module_Blueprint.md` exists to prevent. Second, and more seriously, **an AI capability that can propose action across domains is a new path to consequential effect** — if it is not designed from the start as a strictly governed, human-approved, non-autonomous capability bound to the same authorization and Ethical Wall discipline as every interactive path, it becomes the platform's least-controlled point of entry into privileged, financial, and identity-sensitive operations.

Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, this needs approved architecture before any workflow, automation, or Copilot implementation begins.

**No contradiction with prior architecture was found.** Every existing reference names a future Workflow capability and disclaims ownership of it for itself; `docs/architecture/05_AI_Architecture.md`'s existing AI-governance rules for Legal Intelligence, Communications, Document Intelligence, Knowledge Intelligence, and Financial AI are extended, not modified, by this decision. This decision resolves the "reserved for ARCH-010" dependencies without rewriting approved history — ARCH-001 through ARCH-017 and ADR-001 through ADR-010 are unmodified except for the surgical corrections recorded in this story's completion report, which update only the specific "future Workflow"/"reserved for ARCH-010" statements this decision resolves.

## Decision

1. **Workflow is a supporting bounded context**, proposed module `Workflow`, containing two explicitly separated domain areas — **Workflow Orchestration** and **AI Copilot** — following the same pattern `docs/adr/ADR-007-Document-Knowledge-Management.md` established for Documents and `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md` established for Billing.

2. **Workflow owns orchestration state, not domain truth.** It owns `WorkflowDefinition`, `WorkflowVersion`, `WorkflowRun`, `StepDefinition`/`StepExecution`, `WorkflowTrigger`, `WaitCondition`, `ApprovalRequest`/`ApprovalDecision`, `AutomationPolicy`, `ActionRequest`, `CompensationRequest`, and `WorkflowAuditRecord`. It never owns Clients, Matters, `Task`s, Documents, communications, financial records, identities, or official law — every domain action remains owned, validated, authorized, and recorded by the owning domain.

3. **AI Copilot is assistive and governed, never an authorization or approval authority.** It may understand a request, retrieve authorized context, draft or suggest an action plan, and propose workflow actions. It is never a lawyer, an authorization authority, a domain-data owner, a ledger or calculator, an approver, a workflow definition, a workflow engine, a privileged system administrator, an autonomous agent with unrestricted tools, or a substitute for professional review. **Four roles are distinct and never collapsed:** AI proposes; a specific, identified human decides (approves, rejects, edits and re-proposes, or initiates the action directly); `Workflow` orchestrates the approved request into execution; and the owning domain performs its own current authorization and business-rule validation and is the sole party that actually executes and records the business action. Human approval of an AI proposal never converts AI into the acting or approving principal, and a resulting action is never described, presented, or audited as "AI performing the action" — it is attributed to the human decision actor and the owning domain's execution.

4. **Published workflow versions are immutable.** A `WorkflowVersion` is frozen at publication; a correction or change creates a new version, never an in-place edit.

5. **A `WorkflowRun`'s version binding is permanent.** A `WorkflowRun` remains bound to the exact `WorkflowVersion` it started with for its entire lifetime, and that binding can never be changed in place — by anyone, including an administrator or otherwise authorized human, and including as a consequence of publishing, superseding, or retiring a `WorkflowVersion`. There is no version-migration operation on an existing run. A run bound to a retired or superseded version may continue, be cancelled, or reach a safe terminal/manual-intervention state. If the underlying business process must continue under a newer version, the existing run is first cancelled or terminated, and an entirely new `WorkflowRun` — with its own new identity, fresh trigger provenance, fresh authorization checks, fresh approval decisions, a fresh material-input fingerprint, and its own independent idempotency scope — is created against the newer version. The original run's complete history remains preserved; any link between the original and replacement run is provenance only and never implies a change to the original run's bound version, and no approval granted to the original run carries into the replacement.

6. **Every domain action passes through the owning module's published contract and current authorization.** Workflow never writes another module's tables, imports another module's Eloquent model, or recreates another module's business rules; every `ActionRequest` re-validates current authorization at execution time, not only at proposal or approval time.

7. **Human review is the default for consequential action.** An action above the eligible automation tiers (Decision 8) always requires a fresh, exactly-scoped `ApprovalDecision` before it executes.

8. **Automation policies are explicit, versioned, Firm-scoped, and narrowly bounded.** An `AutomationPolicy` may pre-authorize only explicitly eligible, low-risk-tier actions; it can never become a blanket grant, and it can never reach the non-delegable actions in Decision 9.

9. **Some actions are non-delegable and can never be autonomously performed by AI**, regardless of Firm configuration, confidence, or automation-policy version — including changing Matter status, overriding an Ethical Wall, issuing or voiding an invoice, moving client money, altering identity or permissions, finalizing or sending a legal document or substantive client communication, approving or publishing Knowledge, and approving its own proposal. The full enumerated list is recorded in `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md` §11, which distinguishes two kinds of non-delegable action, neither weaker than the other: **human-gated domain actions** (Matter status, lawyer assignment, document finalization, invoice issuance, permission changes) that a qualified human may still decide to take through the platform, with AI limited to proposing or drafting; and **structural, absolute AI prohibitions** — presenting content as official law or legal advice, approving its own proposal, and any client-money movement — that remain absolute under any human-approval wording, Firm configuration, or workflow design, because they are properties of what AI structurally is, not a delegation boundary a human decision can cross on its behalf.

10. **Long-running operations revalidate authorization and Ethical Walls.** A workflow or Copilot operation never treats the authorization captured when it started as an indefinite grant; current membership, role, revocation, and Ethical Wall status are re-checked before every protected retrieval or consequential action, including on resumption from a wait or approval state.

11. **Workflow retries and deduplication do not imply exactly-once execution.** Delivery and distributed execution remain at-least-once; idempotency and deduplication make retrying safe without ever claiming a single, guaranteed execution.

12. **Compensation is an explicit domain operation, not a rollback of history.** Undoing a completed action's effect requires a separately authorized `CompensationRequest` to the owning domain's own compensating command; cancellation stops future work but never erases or rewrites an already-committed business fact.

13. **AI tools are allowlisted, least-privilege, and approval-gated.** The Copilot never discovers or grants itself a broader tool inventory, never holds direct shell, database, or credential access, and never executes a consequential tool call without passing the approval and authorization composition in Decisions 6–10.

14. **Retrieval filters before context construction.** Firm and permission filtering occur before any material enters model context, never as a post-generation repair — the same rule `docs/architecture/01_OneLegalPro_Constitution.md` Article 21 and `docs/architecture/05_AI_Architecture.md` already establish, applied here to Copilot context assembly.

15. **No cross-Firm memory, context, caching, embeddings, evaluation, or training use.** One Firm's Copilot session, context, or retained preference never informs another Firm's session, without exception.

16. **Client-facing communications remain owned by Communications.** The `CopilotSession` is internal professional assistance, never a `CommunicationThread`; any client-facing message a workflow or Copilot proposal produces is sent only through Communications' own published commands and existing human-authorization discipline.

17. **External API and webhook lifecycle remains owned by Integrations.** Workflow may consume verified external events and commands and may request external delivery through Integrations' published contracts, but Integrations retains ownership of public API contracts, integration applications and installations, webhook subscriptions and delivery, connector configuration, and external contract versioning. This resolves the "reserved for a future ARCH-010" language in `docs/architecture/17_API_Integration_Platform_Architecture.md` and this ADR's own prior reservation without moving any integration ownership into Workflow.

18. **Marketplace distribution remains out of scope.** Publication, monetization, partner certification, and cross-Firm workflow-template distribution are not designed here and remain unarchitected against `docs/architecture/06_Marketplace.md`, which remains an empty placeholder.

19. **No vendor or workflow-engine selection is made.** No AI/model provider, workflow-engine product, queue/event-broker product, vector database, orchestration framework, agent framework, cloud provider, or observability vendor is selected, endorsed, or configured by this decision.

20. **Architecture approval does not schedule implementation.** EPIC-011 — AI Copilot & Workflow Automation is recorded as proposed, not scheduled, in `docs/architecture/08_Roadmap.md`; none of its stages carries a `PF-*` number. PF-010 remains the current repository implementation story, PF-011 remains next, and PF-012 remains after PF-011.

## Domain ownership

| Concept | Owner | Workflow/Copilot's relationship |
|---|---|---|
| `WorkflowDefinition`, `WorkflowVersion`, `WorkflowRun`, `StepDefinition`/`StepExecution`, `WorkflowTrigger`, `WaitCondition`, `ApprovalRequest`/`ApprovalDecision`, `AutomationPolicy`, `ActionRequest`, `CompensationRequest`, `WorkflowAuditRecord` | **`Workflow` — Workflow Orchestration area** | Owner |
| `CopilotSession`, `CopilotTurn`, `AIRun`, `PromptTemplateVersion`, `ContextManifest`, `SourceReference`, `ToolCallProposal`, `HumanReviewDecision`, `AIProvenance` | **`Workflow` — AI Copilot area** | Owner |
| Principals, sessions, service principals, roles, capabilities, delegation, step-up authentication | **IdentityAccess** | Consumed for every actor/authorization decision |
| `Client`, `Matter`, `MatterTeam`, `Task`, Ethical Wall decisions | **Practice Management** | `CheckEthicalWallAccess` remains the sole wall authority; `Task` created only via Practice Management's commands |
| Documents, document versions, Knowledge items, playbooks | **Documents (Document Management and Knowledge Management areas)** | Referenced; a Copilot draft is never automatically canonical; a playbook is guidance until explicitly converted |
| Communication threads, messages | **Communications** | Referenced; all client-facing delivery goes through Communications' own commands |
| Invoices, payments, client money, ledgers | **Billing** | Referenced; financial calculation stays deterministic outside the model |
| Official law, sources, citations | **Legal Intelligence** | Cited; never duplicated or overridden |
| Branding, `AIPersonaConfig` | **Branding** | Composed at presentation time only |
| External API contracts, installations, webhooks, connectors | **Integrations** | Consumed for triggers and delivery; ownership unchanged |
| `FirmContext`, tenancy primitives | **Platform Foundation** | Consumed; Workflow resolves no tenancy of its own |
| Reporting and analytics | **Reserved for a future Reporting architecture** | Workflow exposes only operational projections, never domain analytics |
| Marketplace publication/monetization | **Reserved, ungoverned** | Not designed here |
| AI authority over approval, authorization, or grant | **Nobody — AI is never an authorization authority** | Unchanged from Constitution Article 26 |

## Alternatives considered

- **Let each domain build its own ad hoc automation and approval logic as the need arises.** Rejected — this reproduces exactly the fragmentation `docs/domain/06_Laravel_Module_Blueprint.md` exists to prevent, this time in retry, approval, and compensation logic rather than API shape.
- **Let AI directly execute domain commands whenever its confidence is high enough.** Rejected — confidence is a property of a model's output, not an authorization decision; every action must pass the same authorization and approval composition regardless of stated confidence.
- **Give AI Copilot its own broad "AI platform" bounded context that absorbs Legal Intelligence retrieval, Document Intelligence, Knowledge RAG, and Financial AI.** Rejected — this is precisely the catch-all module `docs/domain/06_Laravel_Module_Blueprint.md`'s Prohibited patterns exists to prevent; each domain's AI rules (`docs/architecture/05_AI_Architecture.md`) remain owned by that domain, and the Copilot consumes them rather than reimplementing or absorbing them.
- **Let `AutomationPolicy` grant a Firm a "fully trusted" exemption from human approval.** Rejected — this collapses the risk-tier model entirely and would let a single Firm configuration setting reach into non-delegable, professional-responsibility-bound actions.
- **Treat a granted API scope, an authenticated session, or a workflow's own internal state as sufficient authorization for a step's action.** Rejected — authentication is never authorization, and same-process or already-authenticated execution is never permission to skip the owning domain's own resource-level check.
- **Let Integrations own Workflow orchestration itself, since it already handles verified external events and delivery.** Rejected — this is exactly the boundary Constitution Article 37 reserved; Integrations supplies external contracts and verified events, Workflow supplies orchestration state, and this decision keeps that boundary from being blurred by expedience.
- **Fold the internal Copilot session into Communications' `CommunicationThread` model.** Rejected — a `CopilotSession` has no external counterparty and is governed by internal professional-assistance rules, structurally different from a client- or lead-facing communication.
- **Allow post-generation filtering to correct an AI answer that was assembled from unauthorized context.** Rejected — for the same correctness reason Constitution Article 21 already rejects it: content that has entered a model's context has already influenced the output, and no downstream filter reliably undoes that influence.
- **Allow a `WorkflowRun` to be migrated to a newer `WorkflowVersion` — automatically on publication, or by explicit human action.** Rejected in both forms. Automatic application would silently change behavior underneath an already-approved, in-flight run; an explicit human-authorized migration would let an approval, fingerprint, or authorization decision made against one version's step graph carry forward and apply to a different one, the same silent-authority-carryover risk this decision exists to prevent. A run's version binding is therefore permanent (Decision 5); the only path to a newer version is cancelling the existing run and creating a new one.
- **Select a specific workflow-engine product, AI/model provider, vector database, or agent framework now, to "unblock" implementation sooner.** Rejected — vendor selection should follow the conceptual boundary, not precede it; no vendor is selected, endorsed, or configured by this ADR.
- **Architect a public workflow-template marketplace or cross-Firm distribution as part of this story.** Rejected — explicitly out of scope; that is future, separately governed Marketplace work against `docs/architecture/06_Marketplace.md`, which remains an empty placeholder.

## Consequences

- Every domain gains a governed path to multi-step automation and AI-assisted proposal, at the cost of routing every consequential action through an additional approval and authorization composition rather than a direct call.
- Binding a run to its starting version means the "same" workflow may execute under different logic across concurrently running instances after a new version publishes — the correct trade for never silently changing behavior underneath an already-approved run.
- The non-delegable-action list and risk-tier model mean some technically feasible automation is deliberately never made autonomous, a professional-responsibility trade accepted explicitly rather than an oversight to fix later.
- Mandatory revalidation on long-running and queued work means ongoing authorization-checking cost across the life of a run, rather than a one-time check at start — the correct trade for never letting a process outlive the authorization it started under.
- At-least-once delivery without an exactly-once claim means every consumer of a Workflow-triggered action must itself be idempotent, stated honestly rather than promised away.
- EPIC-011 becomes a dependency of any future domain automation or AI-assisted proposal capability across Practice Management, Documents, Knowledge Management, Communications, Billing, and Integrations, all of which must account for EPIC-011's foundation stages.
- Deferring vendor and model-provider selection means implementation cannot begin on a specific product until a separate, approved implementation-focused decision is made — deliberate, matching the precedent set by every prior domain architecture on this platform.

## Security and professional-responsibility consequences

- **AI never overrides an Ethical Wall, and no automation policy or approval chain may route around `CheckEthicalWallAccess`.** Practice Management remains the sole wall authority for every workflow step, approval screen, and Copilot answer.
- **No email, hostname, header, or request-body Firm or actor identifier proves identity or tenancy for a workflow, trigger, or Copilot operation.** `FirmContext` and actor identity are derived only from verified identity and an authorized session, service principal, or installation.
- **Denied callers receive no existence signal** — no protected content, metadata, count, workflow title, approval detail, step output, search result, citation, or confirmation that a restricted record exists.
- **Client-money movement is absolute: AI may never initiate, approve, or release any client-money movement**, regardless of confidence, policy configuration, or approval-chain composition, extending Constitution Article 25 unchanged.
- **Revoking access, membership, or an Ethical Wall grant takes effect promptly against any in-flight workflow or Copilot operation** that has not yet completed its next protected checkpoint; no stale positive authorization survives revocation.
- **An emergency disable/kill-switch capability exists for Copilot-initiated proposals and AI-dependent triggers**, independent of deterministic, non-AI workflow execution, so an incident can be contained without halting the platform's core orchestration capability.

## Integration consequences

- **Practice Management** gains a governed path for workflow-driven `Task` creation and Matter-linked automation without losing sole ownership of `Task`, `Matter`, or Ethical Wall decisions.
- **Documents and Knowledge Management** gain a governed, explicit path from an approved playbook to an executable workflow, without playbooks becoming executable merely by existing, and without a Copilot draft becoming canonical content automatically.
- **Communications** retains full ownership of client-facing delivery; Workflow and the Copilot may propose a communication, but sending one still passes through Communications' own human-authorization discipline unchanged.
- **Billing** retains full ownership of financial truth and authorization; Workflow may orchestrate a billing process but can perform no action Billing's own authorization would refuse, and client-money movement remains categorically non-delegable to AI.
- **Legal Intelligence**'s authority-aware retrieval, citation, and disclaimer rules apply unchanged to any Copilot answer touching Thai law.
- **Integrations** resolves its own "reserved for a future ARCH-010" language against this approved boundary; its ownership of external contracts, installations, webhooks, and connectors is unchanged.
- **A future Reporting architecture** will consume Workflow's operational projections for run status, wait time, failure rate, and approval workload, without Workflow absorbing domain analytics or financial reporting.
- **A future Marketplace architecture** will govern any cross-Firm workflow-template distribution, separately from this ADR.

## Explicit non-goals

This ADR does **not**: implement any workflow engine, AI agent, orchestration runtime, or Copilot capability; create schemas, migrations, routes, controllers, jobs, or packages; select or configure an AI/model provider, workflow-engine product, queue/event-broker product, vector database, orchestration framework, agent framework, cloud provider, or observability vendor; claim any AI Copilot, workflow automation, AI agent, RAG, or tool-calling capability is implemented, running, deployed, production-ready, autonomous, certified, or legally compliant; design Marketplace publication, monetization, partner certification, or cross-Firm workflow-template distribution; design a future Reporting bounded context; grant AI any authorization or approval authority; move any existing bounded context's data ownership into Workflow or the Copilot; weaken any existing Ethical Wall, Firm-isolation, financial, or identity rule; or schedule EPIC-011 implementation or any `PF-*` story.

## Implementation status

This ADR and `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md` are **conceptual architecture only**. They authorize no application code, routes, controllers, migrations, schemas, dependencies, packages, infrastructure, Docker configuration, CI changes, environment changes, or runtime behavior. **No control described anywhere in this ADR is claimed to be implemented.** EPIC-011 — AI Copilot & Workflow Automation is recorded as **proposed, not scheduled** in `docs/architecture/08_Roadmap.md`; none of its stages carries a story ID. **PF-010 remains current, PF-011 remains next, and PF-012 remains after PF-011.**

The story ID is **ARCH-010** while the new sequential architecture document is numbered **18** (`ARCH-018`), continuing the established distinction between story numbering and architecture-document numbering that ARCH-006/ARCH-014, ARCH-007/ARCH-015, ARCH-008/ARCH-016, and ARCH-009/ARCH-017 already set.
