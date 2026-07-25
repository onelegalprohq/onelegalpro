# ARCH-018 — AI Copilot & Workflow Automation Architecture

**Status:** Approved (conceptual architecture) after human approval. Implementation stages (§31) are **proposed, not scheduled**. **PF-010 remains the current repository implementation story, PF-011 remains next, and PF-012 remains after PF-011.** No `PF-*` story is added, removed, completed, renumbered, or scheduled by this document. See `docs/architecture/08_Roadmap.md`.

## 1. Purpose and scope

This document defines the conceptual domain and system architecture for OneLegalPro's AI Copilot and Workflow Automation capability, implementing `docs/adr/ADR-011-AI-Copilot-Workflow-Automation.md` and the relevant articles of `docs/architecture/01_OneLegalPro_Constitution.md`. It establishes **`Workflow`** as a supporting bounded context containing two explicitly separated domain areas — **Workflow Orchestration** and **AI Copilot** — following the same "one bounded context, explicitly separated domain areas" pattern `docs/adr/ADR-007-Document-Knowledge-Management.md` established for Documents and `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md` established for Billing.

**In scope:** reusable workflow definitions and immutable published versions; workflow runs and step-execution state; timers, wait conditions, and triggers; human approval and automation policy; the OneLegalPro internal professional AI Copilot as a governed, assistive capability; AI-run provenance, context construction, and retrieval discipline; prompt-injection and tool-safety controls; memory and retention rules; idempotency, retries, cancellation, and compensation; audit, observability, and failure handling; white-label presentation of the Copilot persona; and the integration boundary with every existing bounded context.

**Out of scope:** Marketplace publication, monetization, partner certification, and cross-Firm workflow-template distribution (§29, §30); the future Reporting bounded context (§24.11, §30); any AI/model provider, workflow-engine product, queue/event-broker product, vector database, orchestration framework, agent framework, cloud provider, or observability vendor selection; any claim that AI Copilot, workflow automation, an AI agent, RAG, tool calling, or any workflow engine is implemented, running, deployed, production-ready, autonomous, certified, or legally compliant.

This document describes **conceptual models only**. It defines no migrations, schemas, routes, controllers, jobs, packages, or vendor selection.

## 2. Bounded-context classification

**`Workflow` is one supporting bounded context with two explicitly separated domain areas:**

- **Workflow Orchestration** (Area W1) — reusable workflow definitions, immutable published versions, runs, step-execution state, timers, wait conditions, triggers, approvals, automation policy, retries, cancellation, and compensation coordination.
- **AI Copilot** (Area W2) — the governed internal professional-assistance capability: sessions, turns, AI-run provenance, context manifests, tool-call proposals, and human-review decisions.

The two areas are enforced by **distinct aggregates, lifecycles, access policies, and authority — not by a module wall**, exactly as `docs/architecture/14_Document_Knowledge_Management_Architecture.md` separates Document Management from Knowledge Management inside `Documents`, and as `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` separates its three financial areas inside `Billing`. They share this one bounded context because they share the same governance primitives — actor/session provenance, Firm isolation, approval-before-execution, correlation and causation tracking — and because a Copilot-proposed action and a human-initiated workflow step must pass through **the same execution and approval pipeline**, not two parallel ones that could silently diverge.

**Subdomain classification (Eric Evans terminology):** `Workflow` is a **Supporting subdomain**, in the same sense `docs/architecture/17_API_Integration_Platform_Architecture.md` classifies `Integrations` — it exists to serve every other bounded context's need for coordinated, auditable multi-step execution and reusable automation, without itself becoming a second owner of what any of them mean. It is not a Generic subdomain: a drop-in, off-the-shelf workflow or AI-assistant product would not natively encode Firm isolation, Ethical Wall precedence, non-delegable-action rules, approval-binding to a material-input fingerprint, or the professional-responsibility posture this platform requires — those are OneLegalPro-specific governance decisions, deliberately built into this bounded context rather than assumed away as infrastructure.

**AI Copilot must not become a catch-all bounded context.** It owns exactly the conceptual records enumerated in §6 — session, turn, AI-run, prompt-template version, context manifest, source reference, tool-call proposal, human-review decision, and AI provenance. It never owns official law (Legal Intelligence), documents or knowledge content (Documents), communication threads (Communications), financial truth (Billing), identity or credentials (IdentityAccess), branding (Branding), external API contracts (Integrations), or any other domain's business rules. Every capability the Copilot exposes is either read-only assistance over another domain's published queries, or a proposal that must pass through Workflow Orchestration's action and approval pipeline before it has any effect.

## 3. Ubiquitous language

| Term | Meaning |
|---|---|
| **`WorkflowDefinition`** | A reusable, named workflow, owning its `WorkflowVersion` history. Grants no execution authority by existing. |
| **`WorkflowVersion`** | One published, immutable version of a `WorkflowDefinition` — its step graph, triggers, and approval requirements at the moment it was published. |
| **`WorkflowRun`** | One executing instance of a `WorkflowVersion`, bound to that exact version for its entire lifetime. |
| **`StepDefinition`** | One node in a `WorkflowVersion`'s step graph — an action, approval, wait, branch, or compensation step. |
| **`StepExecution`** | The runtime record of one `StepDefinition` executing within one `WorkflowRun`. |
| **`WorkflowTrigger`** | The Firm-bound, provenance-preserving record of what started or would start a `WorkflowRun` — human, event, schedule, verified external request, or approved Copilot proposal. |
| **`WaitCondition`** | A timer or external-signal condition a `StepExecution` is suspended on before resuming. |
| **`ApprovalRequest`** | A request for human authorization of one specific action, bound to an exact Firm, run, version, action, target, and material-input fingerprint. |
| **`ApprovalDecision`** | The recorded outcome of an `ApprovalRequest` — approved, rejected, or expired — and by whom. |
| **`AutomationPolicy`** | A Firm-scoped, versioned policy narrowly pre-authorizing specific, bounded, risk-tier-eligible actions to proceed without a fresh human approval. Never a blanket grant. |
| **`ActionRequest`** | A `StepExecution`'s request to invoke one owning domain's published command or query, carrying its idempotency key, request fingerprint, and actor/authorization provenance. |
| **`CompensationRequest`** | An explicit, separately authorized request to invoke an owning domain's compensating command for an action already completed. Never a rollback of workflow state. |
| **`WorkflowAuditRecord`** | An append-only record of a consequential Workflow Orchestration event. |
| **`CopilotSession`** | One internal professional-assistance session between a human actor and the Copilot, Firm-scoped and bound to that actor. |
| **`CopilotTurn`** | One exchange (request and response) within a `CopilotSession`. |
| **`AIRun`** | One model invocation backing a `CopilotTurn` or a Workflow AI-dependent step — model, provider reference, prompt-template version, context manifest, output, and confidence. |
| **`PromptTemplateVersion`** | One approved, versioned prompt template. Prompt content is governed, not ad hoc. |
| **`ContextManifest`** | The enumerated, authorized set of `SourceReference`s that entered an `AIRun`'s model context, and the access decision that authorized each one. |
| **`SourceReference`** | A pointer, with version and access-decision provenance, to material that entered context — a Legal Intelligence chunk, a Knowledge item version, a Document version, or another domain's authorized fact. |
| **`ToolCallProposal`** | An `AIRun`'s proposed action. It is a proposal only — it has no effect until translated into an `ActionRequest` and approved per §10–§11. |
| **`HumanReviewDecision`** | A human's recorded acceptance, rejection, or edit of an AI output or `ToolCallProposal`. |
| **`AIProvenance`** | The provenance record attached to every AI output — model/version, provider/processor reference, timestamp, prompt-template version, policy version, source identifiers and versions, access-decision provenance, workflow/run correlation, confidence, citations and disclaimers, and human-review state. |
| **Risk tier** | The classification (§11) of an action by consequence severity, governing what approval or automation-policy pre-authorization it may ever receive. |
| **Non-delegable action** | An action listed in §11 that may never be performed autonomously by AI, regardless of Firm configuration. |
| **Material input fingerprint** | A canonical, reproducible digest of the inputs an approval was granted against (§10); a change invalidates the approval. |

## 4. Ownership matrix

| Concept | Owner | Relationship |
|---|---|---|
| `WorkflowDefinition`, `WorkflowVersion`, `WorkflowRun`, `StepDefinition`, `StepExecution`, `WorkflowTrigger`, `WaitCondition`, `ApprovalRequest`, `ApprovalDecision`, `AutomationPolicy`, `ActionRequest`, `CompensationRequest`, `WorkflowAuditRecord` | **`Workflow` — Workflow Orchestration area** | Owner |
| `CopilotSession`, `CopilotTurn`, `AIRun`, `PromptTemplateVersion`, `ContextManifest`, `SourceReference`, `ToolCallProposal`, `HumanReviewDecision`, `AIProvenance` | **`Workflow` — AI Copilot area** | Owner |
| Principals, sessions, service principals, roles, capabilities, delegation, step-up authentication, security events | **IdentityAccess** | Consumed for every actor/authorization decision; never duplicated |
| `Client`, `Matter`, `MatterTeam`, `Task`, `Appointment`, Ethical Wall decisions | **Practice Management** | Referenced by identifier; `Task` creation/modification only via Practice Management's published commands; `CheckEthicalWallAccess` is the sole wall authority |
| Documents, document versions | **Documents** | Referenced by identifier; a Copilot draft is never automatically canonical content |
| Knowledge items, playbooks, knowledge versions | **Knowledge Management** (within `Documents`) | Referenced by identifier; a playbook is guidance, never executable merely by existing (§7) |
| Communication threads, messages, client-facing delivery | **Communications** | Referenced by identifier; Workflow never owns or duplicates communication history |
| Invoices, payments, client money, ledgers, financial truth | **Billing** | Referenced by identifier; financial calculations remain deterministic outside the model (§11) |
| Official law, legal sources, citations | **Legal Intelligence** | Cited via `SourceReference`; never duplicated or overridden |
| Branding, `AIPersonaConfig` | **Branding** | Composed at presentation time only (§23) |
| External API contracts, integration installations, webhook subscriptions/delivery, connector configuration | **Integrations** | Consumed; Workflow may trigger from verified external events and request external delivery, never owns the contract (§24.9) |
| `FirmContext`, tenancy primitives | **Platform Foundation** | Consumed for every Firm-scoped operation; Workflow resolves no tenancy of its own |
| AI authority to approve, authorize, or grant | **Nobody — AI is never an authorization authority** | Unchanged from Constitution Article 26 |

## 5. Workflow aggregates and conceptual model

**Aggregate roots**

- **`WorkflowDefinition`** — a reusable, named workflow, owning its `WorkflowVersion` history. Independent because a definition's identity and naming persist across many published versions.
- **`WorkflowVersion`** — one immutable, published version of a `WorkflowDefinition`: its `StepDefinition` graph, declared triggers, and approval requirements at publication time. Owned by `WorkflowDefinition` but immutable once published (§7).
- **`WorkflowRun`** — one executing instance, bound for its entire lifetime to the exact `WorkflowVersion` it started with; owns its `StepExecution` entities. Independent of `WorkflowDefinition` because a run's identity, history, and outcome must survive regardless of later definition changes.
- **`ApprovalRequest`** — independent aggregate root, because an approval's exact-binding and expiry lifecycle (§10) is distinct from the run's own lifecycle — a run may wait on several approvals, and an approval's provenance must remain queryable even after the run that requested it completes.
- **`AutomationPolicy`** — a Firm-scoped, versioned aggregate, independent of any specific run, because one policy governs many runs and must itself be versioned and auditable.
- **`WorkflowTrigger`** — an independent, Firm-scoped record of what may start (or did start) a run, kept separate from `WorkflowRun` so a trigger's registration, provenance, and deduplication key survive even for a trigger that never produced a run (for example, a rejected AI-proposed trigger).

**Entities**

- **`StepDefinition`** — owned by `WorkflowVersion`; one node in the step graph (action, approval, wait, branch, or compensation), immutable once its version is published.
- **`StepExecution`** — owned by `WorkflowRun`; the runtime record of one `StepDefinition` executing, with its own status, `WaitCondition`, and `ActionRequest`/`CompensationRequest` references.
- **`ApprovalDecision`** — owned by `ApprovalRequest`; the recorded outcome and deciding actor.
- **`ActionRequest`** — owned by `StepExecution`; one request to invoke an owning domain's published command or query.
- **`CompensationRequest`** — owned by `StepExecution` or referenced independently by an explicit compensation operation (§20); never implied by editing run state.

**Value objects**

`WorkflowStatus`, `StepStatus`, `TriggerType` (Human / DomainEvent / Schedule / VerifiedExternal / ApprovedCopilotProposal), `WaitCondition` (Timer / ExternalSignal), `RiskTier`, `MaterialInputFingerprint`, `IdempotencyKey`, `CorrelationId`, `CausationId`, `ApprovalPolicyVersion`, `AutomationPolicyVersion`, `RetryPolicy`, `CompensationOutcome`.

**Conceptual module placement.** No directory or source file is created by this story. Conceptually, `app/Modules/Workflow/` would contain `Application/Orchestration/` (workflow definition, run, and step-execution use cases) and `Application/Copilot/` (session, turn, and AI-run use cases) as clearly separated subtrees within one module, mirroring `docs/architecture/14_Document_Knowledge_Management_Architecture.md`'s Document Management/Knowledge Management split.

## 6. Copilot and AI-run conceptual model

**Aggregate roots**

- **`CopilotSession`** — one internal, professional-assistance session bound to exactly one initiating human actor and one Firm; owns its `CopilotTurn` history. It is **not** a `CommunicationThread` (§24.6) — it never represents a conversation with a Lead, Client, or external counterparty, and Communications' client-facing rules are unaffected by it.
- **`AIRun`** — an independent, short-lived aggregate recording one model invocation: the `PromptTemplateVersion` used, the `ContextManifest` assembled, the raw output reference, confidence, and the resulting `ToolCallProposal`(s) if any. Kept independent of `CopilotSession` so a Workflow AI-dependent step (§9) can also produce an `AIRun` without needing a session.
- **`PromptTemplateVersion`** — an independent, versioned, approved aggregate; prompt content is governed the same way a `KnowledgeVersion` is governed — immutable once approved, superseded by a new version, never silently edited in place.

**Entities**

- **`CopilotTurn`** — owned by `CopilotSession`; one user request and the Copilot's response, referencing the `AIRun`(s) that produced it.
- **`ToolCallProposal`** — owned by `AIRun`; a proposed action with its target, proposed parameters, and risk-tier classification. Translating an accepted `ToolCallProposal` into a Workflow `ActionRequest` (§12) is a distinct, explicit operation — a proposal is inert until that translation occurs and the resulting action passes approval.
- **`HumanReviewDecision`** — owned by the reviewed `AIRun`, `ToolCallProposal`, or output; records the reviewing human actor, decision, timestamp, and rationale where policy requires one.

**Value objects**

- **`ContextManifest`** — the enumerated, ordered set of `SourceReference`s that entered one `AIRun`'s context, each already Firm- and permission-filtered before assembly (§15).
- **`SourceReference`** — a pointer to one piece of authorized material (a Legal Intelligence chunk, a `KnowledgeItem` version, a `DocumentVersion`, a Practice Management fact, a Billing record) plus its version and the access decision that authorized its inclusion.
- **`AIProvenance`** — attached to every AI output: model and model version, provider/processor reference, timestamp, `PromptTemplateVersion`, policy version, source identifiers and versions, access-decision provenance, workflow/run correlation identifiers, confidence or uncertainty where meaningful, citations and required disclaimers, and human-review state.

**Invariant.** An `AIRun`'s output is never itself authoritative domain truth. It becomes consequential only when a human accepts a resulting `ToolCallProposal` (or an eligible, narrowly bounded `AutomationPolicy` pre-authorizes it per §11) and that proposal is translated into an `ActionRequest` that passes the same authorization and approval composition (§10, §12–§13) any other action would.

## 7. Workflow definition and version lifecycle

```text
Draft → In Review → Published (immutable) ⇄ Superseded → Retired
```

- **Draft** — a `WorkflowDefinition`'s in-progress version, freely editable, not executable by any trigger.
- **In Review** — an explicit human review step before publication; review is recorded (who, when, what was reviewed).
- **Published** — the `WorkflowVersion` becomes **immutable**. Its step graph, triggers, and approval requirements are frozen. A `WorkflowRun` that starts from this version is bound to it for its entire lifetime; **publishing a newer version never silently alters an already-running instance.**
- **Superseded** — a newer version has been published for the same `WorkflowDefinition`; the superseded version remains immutable and remains the version of record for any run still bound to it.
- **Retired** — no new `WorkflowRun` may start from this version; runs already bound to it continue unless an authorized human explicitly initiates a **version-migration operation** — itself a distinct, recorded, human-authorized act, never an automatic consequence of retirement or of publishing a new version.

**Playbook-to-workflow conversion is an explicit, governed human decision, never automatic.** A `KnowledgeItem` playbook (`docs/architecture/14_Document_Knowledge_Management_Architecture.md` §K13) remains guidance and source material — it is not executable merely because it exists. Converting or mapping an approved playbook into a `WorkflowDefinition`/`WorkflowVersion` requires an authorized human to author the resulting workflow deliberately, referencing the source playbook's identity and version as provenance; the playbook's own approval, versioning, and staleness lifecycle in Knowledge Management is unaffected, and a later playbook edit never silently changes an already-published `WorkflowVersion`.

## 8. Trigger model

A `WorkflowTrigger` may originate from:

- **Explicit human initiation** — a user starts a run directly.
- **Owning-domain events** — a published domain event (for example, a Practice Management `MatterOpened`) that a `WorkflowVersion` declares as a trigger.
- **Schedules and timers** — a Firm-configured recurring or one-time schedule.
- **Verified external requests received through Integrations** — an inbound webhook that has already passed Integrations' shared ingress pipeline **and** the owning domain adapter's provider-specific verification (`docs/architecture/17_API_Integration_Platform_Architecture.md` §29–§30). **An external request never enters Workflow as unverified business truth**; Workflow only ever sees a verified, translated command or event, never a raw inbound payload.
- **Approved AI Copilot proposals** — a `ToolCallProposal` that has passed the approval composition in §10–§11 before it may start or advance a run.

**Every trigger is Firm-bound and provenance-preserving** — it records the Firm, the originating actor/event/schedule/installation, and a correlation identifier, regardless of origin.

**Duplicate and at-least-once delivery never produce duplicate consequential actions.** Each `WorkflowTrigger` carries a deduplication key derived from its origin (the domain event's identifier, the external request's idempotency reference, the schedule occurrence identifier). A duplicate or redelivered origin is recognized and does not start a second `WorkflowRun` or repeat a consequential `ActionRequest` — this is deduplication within a defined boundary, exactly as `docs/architecture/17_API_Integration_Platform_Architecture.md` §19 defines for idempotency generally, and it is **not a claim that trigger delivery or execution is exactly-once**.

## 9. Workflow-run and step-execution lifecycle

```text
WorkflowRun:    Requested → Running ⇄ Waiting → Completed | Failed | Cancelled | Compensating → Closed
StepExecution:  Pending → Running → Succeeded | Failed | Skipped | AwaitingApproval → Running | Failed
```

- A `WorkflowRun` is created bound to one `WorkflowVersion` and remains bound to it for its lifetime (§7).
- **Waiting** covers a `StepExecution` suspended on a `WaitCondition` (a timer or an external signal) — the run is not consuming resources indefinitely and is resumable without losing its provenance or bound version.
- **AwaitingApproval** covers a `StepExecution` whose `ActionRequest` requires a human decision (§10) before it may proceed.
- Every step that invokes a domain action does so through an `ActionRequest` addressed to the owning module's published command or query (§12) — **never a direct write to another module's tables.**
- **Resuming a waiting or approval-pending step revalidates current authorization** (§13) rather than relying on the authorization state captured when the run started.
- **External calls are never made inside a database transaction.** Reliable coordination between Workflow's own state changes and any external call uses an outbox/inbox-style pattern conceptually, with retries and idempotency (§19) — no specific queue, broker, or product is selected.

## 10. Human approval model

Human review is the **default** for any consequential action. An `ApprovalRequest` is bound to the exact:

- Firm
- `WorkflowRun`
- `WorkflowVersion`
- action
- target resource
- **material input fingerprint** — a canonical, reproducible digest of the inputs the approval is granted against
- approving actor
- `AutomationPolicy`/approval-policy version in effect
- expiration or validity window

**If the material input changes after approval, the approval is invalid and a new approval is required.** An approval never carries forward against a different fingerprint, a different target, a different `WorkflowVersion`, or after its validity window has elapsed — a stale approval cannot be executed; the action must be re-requested and re-approved.

**Segregation of duties and maker-checker.** Where policy requires separation, the actor who proposed an action (human or AI-originated) is not the actor who approves it. **An AI system can never be the approving actor for its own proposal** — a `ToolCallProposal`'s eventual `ApprovalDecision` is always made by a human, distinguishable in provenance from the proposing `AIRun`.

## 11. Automation policy and risk tiers

**Risk tiers**, from lowest to highest consequence:

1. **Read-only assistance** — retrieval, summarization, explanation; no domain state changes.
2. **Drafting and recommendation** — producing a draft or suggestion for human review; no domain state changes until a human acts on it through the owning domain's own command.
3. **Reversible low-risk automation** — a narrow, easily-undone domain action (for example, creating a draft `Task` in Practice Management via its published command).
4. **Consequential domain action** — a domain action with real business effect that is not easily reversed (changing a record's status, sending an internal notification).
5. **Privileged/security action** — anything touching identity, permissions, delegation, or access scope.
6. **Financial or client-money action** — anything touching invoices, payments, ledgers, or client money.
7. **Destructive or externally binding action** — deletion, finalization, filing, signing, sending, or publishing with external or irreversible effect.

**`AutomationPolicy` may narrowly pre-authorize only explicitly eligible, bounded actions at Tier 1–2, and at most a deliberately narrow, explicitly enumerated subset of Tier 3 — never a blanket grant, never Tiers 4–7.** A policy names the exact action type, target scope, and conditions it pre-authorizes; anything outside that exact scope falls back to the human-approval default in §10.

**Non-delegable actions.** Some actions must remain non-delegable to AI and cannot be made autonomous merely by Firm configuration, regardless of `AutomationPolicy`, risk-tier classification, or confidence. AI must never autonomously:

- Provide or present generated content as legal advice or official law.
- Create an attorney-client relationship.
- Change Matter status.
- Assign or remove lawyers.
- Override an Ethical Wall.
- Broaden permissions or Client Portal audiences.
- Approve or publish Knowledge.
- Remove confidentiality, privilege, retention, or legal-hold restrictions.
- Finalize, sign, file, send, publish, or delete a legal document.
- Send a substantive client communication outside an already-approved, narrow Communications policy.
- Issue or void an invoice.
- Alter rates or tax treatment.
- Post journals.
- Allocate payments.
- Write off balances.
- Reconcile accounts.
- Move, release, or disburse client money.
- Approve a refund or trust-to-operating transfer.
- Change identity, permissions, MFA, delegation, or privileged access.
- Expose restricted data.
- Approve its own tool call or workflow proposal.
- Perform a destructive operation.

**Financial calculations remain deterministic outside the model.** Client-money movements must never be AI-initiated, AI-approved, or AI-released, regardless of confidence, policy configuration, or approval-chain composition — this extends `docs/architecture/01_OneLegalPro_Constitution.md` Article 25 and `docs/architecture/05_AI_Architecture.md`'s Financial AI rules unchanged.

## 12. Domain-command and query boundaries

Every `StepExecution` that invokes a domain action does so through an `ActionRequest` addressed to the owning module's published command or query. Workflow must never:

- Write another module's tables.
- Import another module's Eloquent model.
- Recreate another module's business rules.
- Treat successful authentication as authorization.
- Make an unavailable authorization dependency appear successful (a timeout or outage is a failure, never a silent approval).
- Use AI output as proof that an action is valid — an `AIRun`'s confidence or a `ToolCallProposal`'s content is never itself an authorization signal.

**Practice Management continues to own legal-work `Task` records.** A workflow step that needs a `Task` created or modified issues an `ActionRequest` to Practice Management's published `CreateTask`/`CompleteTask`/etc. commands (`docs/architecture/13_Practice_Management_Architecture.md` §16); Workflow never creates a second, competing `Task` model of its own.

**External calls are never made inside a database transaction.** Reliable delivery uses an outbox/inbox pattern, retries, and idempotency (§19), conceptually — no specific implementation product is selected here.

## 13. Identity, actor provenance and authorization

Every workflow and Copilot operation preserves:

- Initiating principal
- Effective actor
- Firm context
- Delegation or service-principal provenance
- Correlation and causation identifiers
- `WorkflowDefinition` and `WorkflowVersion`
- Approval provenance
- `AutomationPolicy` version
- Relevant authorization-decision provenance

**IdentityAccess remains the owner** of principals, membership, sessions, service principals, roles, capabilities, delegation, and step-up authentication (`docs/architecture/16_Identity_Security_Access_Control_Architecture.md`). Workflow may consume identity and permission decisions but never owns them, and never reimplements authentication or role/permission logic of its own.

**Long-running and queued work never treats the initiator's original authorization as an indefinite grant.** Current authorization, membership, revocation, Ethical Wall status, and any narrower domain restriction are **revalidated** whenever a step retrieves protected data or performs a consequential action — including on resumption from a `WaitCondition` or an `AwaitingApproval` state — exactly as `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §28 already requires for background and queued work generally.

**A client-supplied Firm or Actor identifier is never trusted.** `FirmContext` and actor identity for any workflow, trigger, or Copilot operation are derived only from verified identity and an authorized session, service principal, or installation — never from a hostname, header, request parameter, or payload field, per Constitution Article 27 and Article 32.

## 14. Firm isolation and Ethical Walls

**Practice Management remains the sole Ethical Wall authority**, through its published `CheckEthicalWallAccess` query exclusively (`docs/architecture/13_Practice_Management_Architecture.md` §8). No other bounded context — including Workflow and the Copilot — implements its own wall logic.

Ethical Wall authorization happens **before**:

- Retrieval
- Search
- Aggregation
- AI context construction
- A Copilot answer
- A workflow step
- An approval screen
- An export
- A notification
- Execution of a domain command

**Workflow never caches an Ethical Wall result as a permanent permission.** Every one of the checkpoints above re-evaluates the current wall state; a result computed for an earlier step in the same run is never reused as a standing grant for a later step or a later run.

**A denied caller receives no content, metadata, count, workflow title, approval detail, step output, search result, citation, or existence confirmation.** This applies uniformly whether the caller is a human, a queued background step, or the Copilot constructing context for a response.

**Cross-Firm workflow execution, Copilot context, caching, embeddings, memory, evaluation data, and model context are prohibited** — without exception, and regardless of anonymization claims, matching the cross-Firm prohibition `docs/architecture/05_AI_Architecture.md`'s Knowledge Intelligence and Financial AI sections already establish.

## 15. AI context construction and retrieval

**Firm and permission filtering occur before retrieval and before any material enters model context — never after.** Post-generation filtering is never the primary security control, for the same correctness reason `docs/architecture/01_OneLegalPro_Constitution.md` Article 21 and `docs/architecture/05_AI_Architecture.md` already establish: content that has entered context has already influenced the output.

**Only authorized and retrieval-eligible material may enter model context**, and every such item is recorded in the `AIRun`'s `ContextManifest` as a `SourceReference` carrying its own access-decision provenance.

- **Legal Intelligence retrieval** preserves official Thai authority, source version, citations, and translation disclaimers, per `docs/architecture/05_AI_Architecture.md`'s authority-aware RAG rules — unchanged by being consumed through the Copilot.
- **Knowledge retrieval** preserves approval status, version, staleness, and access policy, per `docs/architecture/05_AI_Architecture.md`'s Knowledge Intelligence rules — an overdue or superseded item's status travels with it into context, never silently upgraded to "current."
- **Document-derived content remains derived and provenance-tagged**, per `docs/architecture/05_AI_Architecture.md`'s Document Intelligence rules — never presented as canonical content.
- **Financial context preserves currency and "as of" time**, and authoritative totals remain deterministic outside the model, per `docs/architecture/05_AI_Architecture.md`'s Financial AI rules.

## 16. AI provenance, citations and disclaimers

Every AI output retains `AIProvenance` (§6): model and model version; provider or processor reference; timestamp; prompt-template version; policy version; source identifiers and versions; access-decision provenance; workflow/run correlation; confidence or uncertainty where meaningful; citations and required disclaimers; and human-review state.

**Confidential prompt and output bodies are never required in general logs, events, or audit payloads.** Safe references (identifiers, hashes, safe metadata) are used instead of storing full confidential content in ordinary telemetry; retention of any fuller record is classification-aware and access-restricted, with investigation access to it treated as a privileged, audited operation — the same discipline `docs/architecture/17_API_Integration_Platform_Architecture.md` §14 applies to secret handling and `docs/architecture/04_Security_Architecture.md` applies to sensitive telemetry generally.

## 17. Prompt-injection and tool-safety controls

**Documents, web content, email, messages, uploaded files, retrieved knowledge, and external payloads are treated as untrusted data, never as trusted system instructions.** Content encountered during retrieval or tool use is data to be reasoned about, not a source of new instructions the model follows.

- **Instruction/data separation** — the system and developer instructions that govern Copilot behavior are structurally distinct from any retrieved or user-supplied content, at every point in the pipeline.
- **Tool allowlists** — the Copilot may only invoke an explicitly enumerated, narrow set of tools; it never discovers or is granted a broader tool inventory at runtime.
- **Schema-validated tool inputs and outputs** — every `ToolCallProposal` and its eventual `ActionRequest` are validated against an explicit schema before being accepted.
- **Least-privilege tool access** — a tool available to the Copilot carries no more authority than the specific, narrow action it exists to propose.
- **Material-input fingerprints** — every consequential tool proposal carries the fingerprint discipline in §10, so a later, silently altered input cannot ride on an earlier approval.
- **Approval before consequential tool use** — per §10–§11; no tool call above the eligible automation tiers executes without a fresh, valid approval.
- **Current authorization before each protected action** — per §13; a tool call re-validates authorization at execution time, not only at proposal time.
- **Output validation** — a tool's or model's output is validated against expected shape and constraints before it is trusted for any downstream step.
- **Confidential-data minimization** — only the minimum necessary authorized content is placed in context or in a tool call payload.
- **Safe failure on ambiguous or adversarial instructions** — where retrieved content appears to instruct the model directly (a prompt-injection pattern), the safe behavior is to decline the implied instruction and, where appropriate, flag it for human review, never to silently comply.
- **No arbitrary shell, database, or credential access by the model** — the Copilot never holds direct shell, database, or credential access; every effect flows through an `ActionRequest` to a published domain command.
- **Incident traceability and an emergency disable/kill-switch capability** — every `AIRun` and `ToolCallProposal` is traceable to its session, actor, and context, and an authorized human operator can disable Copilot-initiated proposals or a specific `WorkflowDefinition`'s AI-dependent triggers without disabling deterministic, non-AI workflow execution (§22).

**The model never chooses its own expanded permissions or tool inventory.** Any change to what tools the Copilot may propose, or what `AutomationPolicy` pre-authorizes, is an explicit, human-authorized configuration change — never a runtime decision the model makes for itself.

## 18. Memory and retention

Copilot memory is defined conservatively:

- **No hidden, unrestricted, durable memory.** Anything the Copilot "remembers" across sessions is an explicit, named, governed record — never an opaque accumulation.
- **No cross-Firm memory.** A memory or preference recorded for one Firm never informs a response for another.
- **No automatic training use.** Session content, prompts, and outputs are not used to train or fine-tune a model without a separate, explicit, approved governance decision.
- **No privileged content in evaluation data without explicit approved governance** — the same processor-eligibility discipline `docs/architecture/05_AI_Architecture.md` already requires for privileged document and communications content.
- **Any retained preference or memory must be explicit, Firm-scoped, access-controlled, auditable, revocable, and retention-governed** — a Firm or user can see what is retained, and revoke it.
- **Deleting memory must not delete required audit facts.** Removing a retained preference or memory record never erases the `WorkflowAuditRecord` or `AIProvenance` history of what the Copilot actually did while that memory was in effect.

## 19. Idempotency, retries and delivery semantics

**Idempotency prevents a duplicate side effect within its defined boundary; it is not a general exactly-once execution or delivery guarantee** — the same discipline `docs/architecture/17_API_Integration_Platform_Architecture.md` §19 establishes for the API and Integration Platform, applied here to `ActionRequest`s and triggers.

- An `ActionRequest` carries an idempotency key scoped by Firm, `WorkflowRun`, `StepExecution`, and target resource, plus a canonical request fingerprint.
- The same scoped key and fingerprint returns the previously recorded result rather than repeating the side effect; the same key with a different fingerprint is rejected as a conflict, never silently accepted as if it were the original request.
- Retries follow a bounded, documented policy with backoff; sustained failure produces a visible dead-letter or manual-intervention state (§22), never a silently abandoned step.
- A replayed or retried `ActionRequest` still authenticates and passes current authorization (§13) — idempotency short-circuits only the side effect, never the authorization composition.

## 20. Cancellation and compensation

**Cancellation stops future work; it does not erase or rewrite business facts already committed.** Cancelling a `WorkflowRun` prevents further `StepExecution`s from starting; any domain action already completed by an earlier step remains exactly as the owning domain recorded it.

**A completed domain action is never rolled back by editing workflow state.** Undoing the effect of a completed action requires an explicit **`CompensationRequest`** — a separately authorized request to the owning domain's own compensating command (a reversal, a credit note, a cancellation notice), passing the same authorization and approval composition (§10–§13) as any other consequential action. `Workflow` records the compensation as its own auditable fact; it never simulates "undoing" history by mutating a `StepExecution` or `WorkflowRun` record after the fact.

## 21. Audit and observability

**Immutable, append-only `WorkflowAuditRecord`s cover, at minimum:**

- Workflow publication and retirement
- Workflow start and completion
- Every step transition
- Retries and compensation
- Approvals and rejections
- Automation-policy decisions
- AI requests and outputs, through safe references
- Context sources
- Tool proposals and execution
- Authorization results
- Policy and model versions
- Cancellations and failures
- Exports and disclosures
- Emergency disablement

**Audit events never contain** secrets, reusable credentials, raw payment material, unrestricted privileged content, full document bodies, or cross-client confidential narratives — the same discipline `docs/architecture/17_API_Integration_Platform_Architecture.md` §41 and `docs/architecture/16_Identity_Security_Access_Control_Architecture.md`'s security-audit rules already require.

**Metrics and traces** cover latency, error rate, run/step outcome distribution, approval turnaround, and AI-run confidence distribution **without ever carrying confidential payloads.** Correlation identifiers thread every metric, log line, and audit event for one run, step, or `AIRun` back together.

## 22. Failure handling

- **Retries, idempotency, and timeouts** — per §19, with bounded backoff.
- **Waiting states** — a `StepExecution` suspended on a `WaitCondition` is visible and resumable, never silently stalled without status.
- **Dead-letter / manual-intervention states** — exhausted retries move a step into a visible, queryable state requiring human attention, never a silent drop.
- **Cancellation and compensation** — per §20.
- **Partial completion** — a run that completed some steps and failed at another surfaces exactly which steps succeeded, which failed, and which never ran; it is never presented as fully succeeded or fully failed when it is neither.
- **Unavailable model provider** — an `AIRun` that cannot be completed fails the Copilot turn or AI-dependent step visibly; it never falls back to fabricating a response or silently skipping the AI-dependent step's safety checks.
- **Unavailable owning domain** — a `StepExecution`'s `ActionRequest` to an unavailable domain module fails closed and is retried or escalated; it is never treated as having succeeded.
- **Unavailable IdentityAccess or Ethical Wall authority** — per §13–§14, the operation fails closed; no stale or assumed-good authorization decision is used.
- **Revoked access during a long-running workflow** — the next revalidation point (§13) detects the revocation and fails the affected step closed, rather than continuing under the original grant.
- **Stale approval** — an `ApprovalRequest` past its validity window, or invalidated by a material-input change (§10), blocks execution until a new approval is obtained.
- **Changed workflow version** — an in-flight `WorkflowRun` is unaffected by a newer `WorkflowVersion`'s publication (§7); it continues under its bound version until explicitly migrated.
- **Invalid model output** — output that fails schema or output-validation checks (§17) is rejected before it can inform a `ToolCallProposal` or downstream step.
- **Prompt-injection detection** — a detected injection pattern results in safe failure (§17): the implied instruction is declined and, where appropriate, flagged for human review.
- **Duplicate triggers** — deduplicated per §8; a duplicate origin never starts a second consequential run.
- **Human escalation** — any of the above conditions that cannot be resolved automatically is escalated to a human, with full context and correlation identifiers preserved, rather than retried indefinitely or silently abandoned.

**Availability never outranks authorization, confidentiality, privilege, Ethical Walls, financial safeguards, or professional review.** A model outage must not corrupt workflow state or rewrite domain truth. **Deterministic workflows that do not require AI remain conceptually separable from AI-dependent steps** — a `WorkflowVersion` composed entirely of deterministic steps (approvals, domain commands, timers) can execute correctly even when the Copilot or a model provider is fully unavailable.

## 23. White-label presentation

**Copilot display name, avatar, and presentation may use Branding's `AIPersonaConfig`** (`docs/architecture/10_White_Label_Platform_Architecture.md`), consistent with the platform's white-label design (Constitution Articles 10–11).

**Branding must never suppress or alter:**

- AI identification
- Advisory-only framing
- Human-review requirements
- Citations
- Disclaimers
- Provenance
- Uncertainty
- Security warnings

A branded persona changes how the Copilot presents itself; it never changes what governance applies to it.

## 24. Integration boundaries for every existing bounded context

**24.1 Platform Foundation.** Workflow consumes `FirmContext` and tenancy primitives; it resolves no tenancy of its own (§13).

**24.2 IdentityAccess.** Workflow consumes principals, sessions, service principals, capabilities, delegation, and step-up authentication; it never owns or reimplements them (§13).

**24.3 Practice Management.** Workflow consumes `Matter`, `MatterTeam`, and Ethical Wall decisions via `CheckEthicalWallAccess`; a workflow step needing a `Task` uses Practice Management's published `Task` commands rather than creating a competing model (§12, §14).

**24.4 Documents.** Workflow and the Copilot reference documents by identifier and version; a Copilot-generated draft is not automatically canonical document content — promotion to a canonical `DocumentVersion` remains Documents' own governed act.

**24.5 Knowledge Management.** Playbooks remain Knowledge Management's governed asset; converting one into an executable `WorkflowDefinition` is the explicit, human-gated act in §7. Firm know-how retrieved for Copilot context preserves its approval status and staleness (§15).

**24.6 Communications.** Client-facing receptionist and messaging capabilities remain owned by Communications. The `CopilotSession` is internal professional assistance, never a `CommunicationThread`; a workflow step that needs to send a client communication does so through Communications' own published commands and its own human-authorization discipline, never directly.

**24.7 Billing.** Financial truth, ledgers, and client money remain Billing's alone. A workflow step touching billing invokes Billing's published commands under Billing's own authorization; financial calculations remain deterministic outside the model (§11).

**24.8 Legal Intelligence.** Official law, sources, and citations remain Legal Intelligence's alone. Copilot answers touching Thai law resolve through Legal Intelligence's authority-aware retrieval and citation rules unchanged (§15).

**24.9 Integrations.** Workflow may consume verified events and commands that Integrations' shared ingress pipeline and the owning domain's provider adapter have already authenticated, and may request external delivery through Integrations' published contracts (§8). **Integrations retains ownership of public API contracts, integration applications and installations, webhook subscriptions and delivery, connector configuration, and external contract versioning; Workflow retains only its own orchestration state.** This resolves the "reserved for a future ARCH-010" language in `docs/architecture/17_API_Integration_Platform_Architecture.md` and `docs/adr/ADR-010-API-Integration-Platform.md` without moving any integration ownership: those documents are updated by this story to reference this approved boundary (§27, and the surgical updates recorded in this story's completion report).

**24.10 Branding.** Presentation only, per §23.

**24.11 Reporting (future, unarchitected).** Workflow may expose operational projections for run status, wait time, failure rate, and approval workload (§30). It must not absorb the future Reporting bounded context or redefine domain analytics and financial reporting, which remain each owning domain's own responsibility.

**24.12 Marketplace (out of scope).** Marketplace publication, monetization, partner certification, and cross-Firm workflow-template distribution remain out of scope and unarchitected (§29, §30). `docs/architecture/06_Marketplace.md` is not populated by this document. A `WorkflowDefinition` is Firm-owned and never becomes cross-Firm merely because its structure appears reusable.

## 25. Invariants

- AI never becomes an authorization authority.
- AI never approves its own proposal.
- Authentication never substitutes for authorization.
- Current authorization is revalidated before protected retrieval and consequential execution.
- Practice Management remains the sole Ethical Wall authority.
- The most restrictive applicable decision wins.
- Firm isolation is mandatory in interactive, queued, scheduled, bulk, retry, and AI paths.
- No client-provided Firm identifier proves tenancy.
- No cross-Firm AI context, memory, embedding, cache, evaluation, or training use.
- Denied callers receive no existence signal.
- Published workflow versions are immutable.
- A run never silently changes workflow version.
- Approval is exact, scoped, versioned, and invalidated by material change.
- Idempotency does not equal exactly-once execution.
- Cancellation does not erase completed domain truth.
- Compensation is explicit and separately authorized.
- External calls do not occur inside database transactions.
- AI output remains advisory and provenance-tagged.
- The model never holds credentials or unrestricted tool access.
- Untrusted retrieved content never becomes trusted instructions.
- No AI action may weaken privilege, confidentiality, retention, legal hold, co-client isolation, financial safeguards, or professional review.
- Architecture approval schedules no implementation.

## 26. Threats and mitigations

| Threat | Mitigation |
|---|---|
| Prompt injection via retrieved or uploaded content | Instruction/data separation, safe failure on ambiguous instructions, output validation (§17) |
| Tool misuse or scope creep | Tool allowlists, least-privilege tool access, schema-validated I/O, no self-expanded permissions (§17) |
| AI approving its own proposal | Segregation of duties; AI can never be the approving actor (§10) |
| Replaying a stale or superseded approval | Exact binding to fingerprint/version/window; material-input-change invalidation (§10) |
| Cross-Firm leakage through shared AI context, cache, or memory | Firm-scoped filtering before retrieval; no cross-Firm context/memory/cache/embedding/eval (§14, §15, §18) |
| Same-process execution used to bypass domain authorization | Every `ActionRequest` still passes the owning domain's own authorization; same-process is never a bypass (§12–§13) |
| Forged or unverified external events entering Workflow as truth | External triggers only accepted after Integrations' shared pipeline and the owning domain's verification (§8, §24.9) |
| AI-generated content presented as authoritative legal or financial fact | Structural separation of retrieved/official content from AI synthesis; deterministic financial calculation outside the model (§11, §15–§16) |
| Hidden or unbounded Copilot memory becoming an unmanaged data store | Explicit, Firm-scoped, access-controlled, auditable, revocable memory only (§18) |
| Long-running workflow outliving the authorization it started under | Revalidation at every protected retrieval or consequential action, including on resumption (§13) |
| Emergency situation requiring AI capability to be disabled quickly | Emergency disable/kill-switch capability, independent of deterministic workflow execution (§17, §22) |
| Duplicate or replayed triggers causing duplicate consequential actions | Deduplication keyed to trigger origin; idempotent `ActionRequest`s (§8, §19) |
| Workflow becoming a shadow authorization or data-ownership layer | Ownership matrix (§4) and domain-command boundary (§12) enforced structurally, not by convention alone |

## 27. Alternatives considered

- **Let AI directly execute domain commands when confidence is high.** Rejected — confidence is not authorization; every action must pass the same approval and authorization composition regardless of model confidence (§10–§13).
- **Let Workflow define its own `Task`-equivalent model instead of calling Practice Management.** Rejected — this recreates exactly the duplicated-ownership problem `docs/domain/06_Laravel_Module_Blueprint.md` exists to prevent; Practice Management remains the sole `Task` owner (§12).
- **Treat a granted API scope, session, or successful authentication as sufficient approval for a workflow action.** Rejected — authentication is never authorization, and a scope never widens what a step may do; the full composition in §13 always applies.
- **Let `AutomationPolicy` grant a blanket "trusted Firm" exemption from approval.** Rejected — automation policy may only pre-authorize explicitly enumerated, narrowly bounded, low-risk-tier actions (§11); a blanket grant would collapse the risk-tier model entirely.
- **Fold the Copilot session into Communications' `CommunicationThread`.** Rejected — a `CopilotSession` is internal professional assistance with no counterparty, structurally different from a communication with a Lead or Client; conflating them would blur Communications' client-facing governance with Workflow's internal-assistance governance.
- **Rely on post-generation filtering to remove unauthorized content from an AI answer.** Rejected — for the same reason `docs/architecture/01_OneLegalPro_Constitution.md` Article 21 rejects it platform-wide: content that has entered context has already influenced the output, and no downstream filter reliably undoes that influence (§15).
- **Let Integrations implement Workflow orchestration itself, since it already handles external events.** Rejected — this is exactly the boundary Constitution Article 37 reserved; Integrations supplies external contracts and verified events, Workflow supplies orchestration, and neither absorbs the other (§24.9).
- **Make AI Copilot its own, broader "AI platform" bounded context absorbing Legal Intelligence retrieval, Document Intelligence, Knowledge RAG, and Financial AI logic.** Rejected — those AI capabilities remain owned by their respective domains (`docs/architecture/05_AI_Architecture.md`); the Copilot consumes their published retrieval and provenance rules rather than reimplementing or absorbing them (§2, §15).
- **Select a specific workflow-engine product, queue/broker, vector database, or agent framework now, to unblock design.** Rejected — vendor and product selection follow approved conceptual architecture, not precede it; none is selected here.
- **Allow a workflow-version migration to happen automatically when a newer version is published.** Rejected — this would silently alter running instances' behavior underneath already-authorized approvals and in-flight state; migration is always an explicit, separately authorized human act (§7).

## 28. Consequences and trade-offs

- Every domain gains a governed, auditable path to multi-step automation and AI-assisted proposal, at the cost of routing every consequential action through an additional approval/authorization composition rather than a direct call.
- Binding a `WorkflowRun` to its exact starting `WorkflowVersion` means concurrently running instances of the "same" workflow may execute under different logic after a new version publishes — the correct trade for guaranteeing no silent behavior change underneath an already-approved run.
- The non-delegable-action list and risk-tier model mean some automation that might be technically straightforward is deliberately never made autonomous — a professional-responsibility and safety trade accepted explicitly, not an oversight.
- Firm-scoped `AutomationPolicy` and mandatory revalidation mean long-running workflows carry ongoing authorization-checking cost rather than a one-time check at start — the correct trade for never letting a long-running process outlive the authorization it started under.
- Deduplication and idempotency without an exactly-once claim mean every consumer of a Workflow-triggered action must itself be idempotent, stated honestly rather than promised away.
- EPIC-011 becomes a dependency of any future domain automation or AI-assisted proposal capability across Practice Management, Documents, Knowledge Management, Communications, Billing, and Integrations, all of which must account for EPIC-011's foundation stages before building on it.

## 29. Explicit non-goals

This architecture does **not**: implement any workflow engine, AI agent, orchestration runtime, or Copilot capability; create schemas, migrations, routes, controllers, jobs, or packages; select or configure an AI/model provider, workflow-engine product, queue/event-broker product, vector database, orchestration framework, agent framework, cloud provider, or observability vendor; claim any AI Copilot, workflow automation, AI agent, RAG, or tool-calling capability is implemented, running, deployed, production-ready, autonomous, certified, or legally compliant; design Marketplace publication, monetization, partner certification, or cross-Firm workflow-template distribution (reserved, unarchitected, against `docs/architecture/06_Marketplace.md`, which remains an empty placeholder); design the future Reporting bounded context; grant AI any authorization or approval authority; move any existing bounded context's ownership into Workflow or the Copilot; weaken any existing Ethical Wall, Firm-isolation, financial, or identity rule; or schedule EPIC-011 implementation or any `PF-*` story.

## 30. Future expansion

Every model above is designed so that additional trigger types, additional risk-tier automation eligibility (subject to the non-delegable list in §11 remaining absolute), multi-agent or tool-composition patterns, a broader Reporting bounded context consuming Workflow's operational projections (§24.11), and a future, separately governed Marketplace pass for cross-Firm workflow-template distribution (§24.12) are **additive extensions** to the existing `WorkflowDefinition`/`WorkflowVersion`/`WorkflowRun`/`CopilotSession`/`AIRun` model, not a redesign of any of them. **None of those capabilities is implemented, architected in detail, or scheduled by this document.**

## 31. Proposed implementation stages

**Proposed only.** None of these stages is an approved, scheduled, or numbered story. Each requires its own entry in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`, with a Definition of Ready and Definition of Done, before implementation begins. None of these stages carries or displaces a `PF-*` number.

1. **Workflow definition/version and execution-state foundation** — `WorkflowDefinition`, `WorkflowVersion` immutability, `WorkflowRun`, `StepDefinition`/`StepExecution`.
2. **Trigger, timer, wait-condition, and idempotency foundation** — `WorkflowTrigger` types, `WaitCondition`, deduplication and idempotency for `ActionRequest`s.
3. **Domain command/query action adapters** — the first `ActionRequest` adapters into Practice Management, Documents, and other owning modules' published contracts.
4. **Human approval, segregation-of-duties, and automation-policy foundation** — `ApprovalRequest`/`ApprovalDecision`, exact-binding fingerprinting, `AutomationPolicy` and risk tiers.
5. **Copilot session, AI-run provenance, and context-manifest foundation** — `CopilotSession`/`CopilotTurn`, `AIRun`, `PromptTemplateVersion`, `ContextManifest`.
6. **Permission-filtered retrieval across Legal Intelligence, Knowledge, and Documents** — `SourceReference` provenance wired to each domain's existing retrieval-eligibility rules.
7. **Allowlisted tool proposals and approval-gated execution** — `ToolCallProposal`, translation into `ActionRequest`, approval-gated execution.
8. **Practice Management workflow templates and Task integration** — playbook-to-workflow conversion (§7), `Task` action adapters.
9. **Communications drafting/handoff and governed outbound-action integration** — Copilot-assisted drafting with Communications' own human-authorization discipline unchanged.
10. **Document/Knowledge playbook consumption and drafting integration** — governed playbook conversion and Copilot-assisted document drafting, with Documents' canonicalization discipline unchanged.
11. **Billing analysis and strictly non-autonomous finance integration** — advisory analysis only, with the non-delegable financial-action list absolute.
12. **Integrations-triggered workflows and external delivery coordination** — verified external triggers and outbound delivery requests through Integrations' published contracts.
13. **Audit, observability, resilience, emergency disablement, and operational hardening** — full `WorkflowAuditRecord` coverage, metrics/tracing, failure handling (§22), and the emergency kill-switch capability (§17).

## 32. Definition of Ready

A proposed implementation stage from §31 is ready only once it has: an approved story ID entered in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`; a clear goal and scope drawn from this document's conceptual model; identified dependencies on Platform Foundation, IdentityAccess, and any owning domain module it integrates with; explicit security, Firm-isolation, and Ethical Wall implications identified; explicit non-delegable-action and risk-tier implications identified where AI is involved; acceptance criteria; and no unresolved architecture blocker.

## 33. Definition of Done

A stage from §31 is done only once: its acceptance criteria are met; unit, application, integration, feature, security, and architecture tests appropriate to the change pass; static analysis passes where configured; the authorization composition, Ethical Wall checkpoints, and non-delegable-action rules in this document are demonstrably enforced, not merely documented; documentation (module `README.md`, `docs/PROJECT_STATUS.md`, and any affected architecture document) is updated; no critical defect remains; and explicit human approval is recorded, per `AGENTS.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`'s Definition of Done.
