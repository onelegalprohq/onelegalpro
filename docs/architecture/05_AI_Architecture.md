# ARCH-005 — AI Architecture

**Status:** Approved

## Purpose

Define how AI retrieval, generation, and presentation must behave across OneLegalPro, with specific rules for Thailand-first Legal Intelligence. This document implements Articles 2, 3, 4, 6, and 7 of `docs/architecture/01_OneLegalPro_Constitution.md` and extends the general AI governance rules in `AGENTS.md` and `docs/implementation/02_AI_Developer_Playbook.md`.

## Governing principle

AI output is advisory only. It never directly modifies authoritative legal records, is never presented as official law, and always remains subject to human professional review. Nothing in this document authorizes an exception to that principle.

## Authority-aware retrieval-augmented generation (RAG)

Retrieval must be aware of legal-source authority, not just semantic relevance:

- Every retrievable chunk carries its source document's authority status (`is_authoritative`), jurisdiction, language, and version alongside its text — retrieval and ranking operate on this metadata, not on text similarity alone.
- Official Thai-language sources are prioritized in ranking over translations of the same provision. A translation may be retrieved to aid explanation or as a bridging reference, but never as a substitute ranking signal for the official text.
- When both an official Thai source and its linked translation are candidates for the same question, the official source is retrieved and cited; the translation is retrieved only as a supplementary aid.

## Thai-source retrieval priority

- Any answer touching Thai law must resolve to, and cite, the canonical Thai source document — even if the user asked the question in English and the answer is composed in English.
- If only a translation exists in the index (no linked official source has been ingested yet), the system must say so explicitly rather than presenting the translation as if it were the authoritative text.

## Provenance-preserving chunks

Chunking and embedding pipelines must preserve, per chunk, at minimum:

- Source document identity (UUIDv7)
- Jurisdiction and language
- Authority status (`is_authoritative`)
- Version and effective-date context
- Official source reference (for citation reconstruction)

Provenance metadata must survive the full retrieval → generation → presentation pipeline. An answer must never be composed from a chunk whose provenance has been dropped along the way.

## Translation handling

- Translations assist explanation and readability. They are never the sole authoritative citation for a legal conclusion.
- When a translation is used in generation, the system must identify and expose the corresponding official Thai text, per Constitution Article 3.
- Where no official source has been linked to a translation, the system must flag the gap rather than imply equivalence.

## Disclaimer propagation

Whenever a response surfaces translated legal text — directly or as the basis for an AI-generated explanation — the centrally governed disclaimer (Constitution Article 4) must accompany it. Disclaimer propagation is enforced at the point of presentation, driven by chunk-level provenance metadata, so it cannot be bypassed by generation logic upstream.

## Citation requirements

AI-generated answers referencing Thai law must cite the canonical Thai source using the `LegalCitation` model (see `docs/architecture/09_Legal_Intelligence_Architecture.md`): canonical Thai title, provision/section identifier, official source reference, effective date, and version/amendment status. An English reference citation may be included in addition to, never instead of, the Thai citation.

## Hallucination safeguards

- Generation must not produce statutory text, section numbers, case citations, or amendment history that is not grounded in a retrieved, provenance-tagged chunk. No legal citation may be synthesized from the model's general knowledge.
- Responses must distinguish, in structure, between retrieved official content, retrieved translation content, and any AI-generated explanatory synthesis (Constitution Article 7) — these must never be blended into a single undifferentiated statement of "the law."

## Confidence and source-sufficiency handling

- When retrieval returns no authoritative Thai source for a question, the system must state that no official source was found rather than answering from translation-only or model-only knowledge.
- When retrieved sources are outdated, superseded, or of uncertain currency relative to the effective-date metadata, the system must surface that uncertainty rather than presenting the answer as current law.
- Low-confidence or low-coverage retrieval results must be surfaced as such, not silently upgraded into a confident-sounding answer.

## Human review requirements

Legal Intelligence AI features remain advisory. Outputs that could inform legal judgment must be presented in a way that invites lawyer review (source citations, provenance, disclaimers) rather than a final-answer framing. This does not change the existing AI governance approval gate in `AGENTS.md` for AI governance changes generally.

## Prohibition on presenting generated text as official law

AI-generated explanations, summaries, and commentary must be visually and structurally distinct from official legal source text and from reference translations at every point of presentation. No interface may render AI-generated material in a way that could be mistaken for the official Thai text or an official translation.

## Communications Hub AI

The rules above govern AI over Legal Intelligence content specifically. The following extend the same governing principle — AI output is advisory only, never presented as final, always subject to human review — to AI capabilities within `docs/architecture/11_Communications_Hub_Architecture.md`: the Website AI Receptionist, Email Intelligence, and general AI Assistance across every communication channel.

- **Self-identification.** Any AI-driven conversational surface (Website AI Chat, Client Portal Chat, or an AI-assisted reply) must identify itself as AI; it must never be presented as, or be confusable with, a human staff member.
- **No implied attorney-client relationship.** Engaging with an AI receptionist or assistant does not, by itself, create an attorney-client relationship. This must be structurally clear in how the AI presents itself, consistent with `docs/architecture/01_OneLegalPro_Constitution.md`, Article 12.
- **No legal advice.** Communications AI gathers information, classifies, drafts, and routes; it does not answer what a visitor's or client's legal position is or what they should do about it. Where a question approaches legal-advice territory, the AI's response is to recommend lawyer consultation, not attempt an answer.
- **Provenance on every AI output.** Every AI-produced summary, classification, draft reply, suggested tag, suggested lawyer, or suggested business-object link carries provenance (model/version, timestamp, confidence) — the same provenance discipline this document requires of Legal Intelligence retrieval, applied here to communications AI output.
- **Human review and approval before outbound action.** AI-drafted replies, low-confidence suggested links to a Lead/Client/Matter, and any other AI-proposed action require explicit human approval before taking effect. AI never sends a communication to a Lead or Client without configured human authorization; a narrowly scoped, Firm-configured auto-send exception is the only departure from this default, never the default itself.
- **Escalation on uncertainty.** Where AI confidence in continuing a conversation safely is low, or urgency is detected, the conversation is escalated to a human without restarting it — conversation history and any collected intake data or AI annotations remain intact across the handoff.

## Document Intelligence AI

The following extend the same governing principle — AI output is advisory only, never authoritative, always subject to human review — to AI and OCR capabilities over documents, per `docs/architecture/14_Document_Knowledge_Management_Architecture.md` and `docs/architecture/01_OneLegalPro_Constitution.md`, Articles 18–19. They do not modify the Legal Intelligence, Communications Hub, or Practice Management AI rules above.

- **Derived, never authoritative.** OCR text, extracted entities and dates, summaries, suggested classifications, suggested tags, suggested Matter/Client links, duplicate detections, and suggested redactions are *derived annotations* over a specific document version. They are never canonical document content and never authoritative metadata. OCR output in particular is a lossy, probabilistic reconstruction and must never be treated as authoritative over the source it was read from.
- **Structurally separate and removable.** Derived annotations are stored distinctly from the version content and the authoritative metadata they describe — never blended into either, per Constitution Article 7 — and removing every annotation must leave the document's versions, checksums, metadata, and audit history unchanged.
- **Provenance and confidence on every output.** Each annotation carries model/version, timestamp, the source version identifier it derives from, and a confidence score, the same provenance discipline this document requires of Legal Intelligence retrieval and Communications AI output. Provenance must survive into any surface that displays or searches derived content.
- **Human authorization before consequential action.** AI may never, without explicit human authorization, change canonical document content, finalize/sign/file/send/publish/delete a document, change legal-hold or retention state, broaden internal or Client Portal access, or confirm a low-confidence Matter or Client association. Low-confidence suggestions are surfaced as uncertain and require human acceptance, never silent adoption.
- **Processor eligibility is an access-control decision.** Document content is the platform's densest concentration of privileged material. Which model or processor may receive it is governed by approved-processor policy, not treated as an infrastructure convenience; sending privileged content to an unapproved model or processor is a defect, not a configuration preference.

## Knowledge Intelligence and RAG

The following govern AI over a Firm's curated know-how — precedents, clauses, templates, playbooks, practice notes, research notes, and internal guidance — per `docs/architecture/14_Document_Knowledge_Management_Architecture.md` (Part II) and `docs/architecture/01_OneLegalPro_Constitution.md`, Articles 20–21. They extend, and do not modify, the Legal Intelligence, Communications Hub, Practice Management, and Document Intelligence rules above.

**Retrieval pipeline**

- **Firm filtering, then permission filtering, before retrieval.** Both happen before results are assembled and before any chunk or knowledge item enters model context — never as a pass over an already-retrieved set.
- **Post-generation filtering is never the primary security control.** Once inaccessible content has entered context it has already influenced the output — as paraphrase, structure, or inference — and no downstream filter reliably detects or unwinds that influence. This is a correctness impossibility, not a performance trade-off.
- **Only approved, retrieval-eligible, access-authorized content enters standard retrieval context.** Draft, rejected, superseded, retired, inaccessible, and quarantined knowledge is excluded by default; retrieval eligibility is an explicit, revocable property rather than an inference from lifecycle state.
- **Search and retrieval never disclose** inaccessible titles, snippets, tags, metadata, provenance, citations, embeddings, or the existence of an item.
- **Embeddings and index entries inherit their source's Firm and access boundary.** An embedding is a derivative of the content it encodes and is protected exactly as that content is. An access-policy change, revocation, supersession, or retirement removes or invalidates the corresponding entries for future retrieval, without rewriting the audit record of retrieval that legitimately occurred before.
- **Cross-Firm retrieval, embeddings, caching, evaluation data, and model context are prohibited** — without exception, and regardless of anonymization claims.

**Output requirements**

- **Retrieval preserves** knowledge item identity, version, approval status, source references, the access decision that permitted it, and citations, so any AI-assisted draft is traceable to exactly the approved guidance that informed it.
- **Generated synthesis stays structurally distinct from quoted knowledge content**, per Constitution Article 7 — a reader must always be able to tell what the Firm approved from what the model composed.
- **Firm know-how is never presented as official law.** Any claim about what the law says still resolves through the authority-aware retrieval, ranking, and citation rules established earlier in this document; a precedent or practice note is firm-owned commentary, never an authoritative source.
- **Staleness travels with the content.** An overdue or expired knowledge item carries its review status into retrieval context and any citation, rather than appearing indistinguishable from current guidance.
- Every AI output over knowledge retains model/run provenance, source citations, timestamp, confidence where applicable, and the specific approved knowledge versions used.

**Permitted and prohibited actions**

AI **may** suggest classifications, tags, summaries, related items, clauses, precedents, templates, and playbook steps; identify potentially stale or conflicting knowledge; propose Matter documents as candidates for human curation; draft a knowledge revision; and retrieve approved, retrieval-eligible, access-authorized knowledge for drafting assistance.

AI **may not, without explicit human authorization**, approve or publish knowledge; make Matter-derived knowledge Firm-wide; remove confidentiality, privilege, contractual, personal-data, or Ethical Wall restrictions; change an approved knowledge version; retrieve inaccessible content; mix Firms' knowledge or embeddings; treat draft or AI-generated output as approved Firm guidance; present Firm know-how as an official legal source; or send privileged content to an unapproved processor.

## Financial AI

The following govern AI over billing, client-money/trust, and Firm accounting data, per `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` and `docs/architecture/01_OneLegalPro_Constitution.md`, Articles 22–25. They extend, and do not modify, the Legal Intelligence, Communications Hub, Practice Management, Document Intelligence, and Knowledge Intelligence/RAG rules above.

**The model is never the calculator or the ledger.** Every authoritative total, balance, tax amount, allocation, currency conversion, and aging figure is computed **deterministically outside the language model**. AI may *explain* such a computed result in plain language; it may never *produce* a figure that is stored, posted, presented as authoritative, or relied on. This is how hallucinated totals, exchange rates, tax treatments, and account balances are prevented by construction rather than by review: the model is never asked to do the arithmetic.

**Required output properties.** Every financial AI output carries: the **source record identity and version** it drew on; the **currency**, explicitly, for every amount referenced; an **"as of" timestamp**, without which a balance or aging figure is meaningless; **provenance and model identity**, plus prompt/output audit metadata where permitted; and **confidence with explicit uncertainty**, surfaced rather than smoothed away.

**Context and processor rules.** Firm filtering and permission filtering happen **before** any financial content enters model context — never after retrieval, and never repaired after generation, consistent with the retrieval discipline established above and in Constitution Article 21. There is **no cross-Firm context, caching, evaluation, or training use**: one Firm's financial data never becomes another's context. **No privileged billing narrative is sent to an unapproved processor** — a time-entry narrative frequently describes the substance of legal work, so processor eligibility is an access-control decision, not an infrastructure convenience. Retrieval preserves record identity, version, currency, "as of" date, and the access decision that permitted it.

**Human approval before authoritative action.** AI output is advisory and never becomes a posted or authoritative financial record automatically.

AI **may** suggest billing narratives and time-entry descriptions; suggest billing classifications; flag anomalies; suggest collection priorities; propose reconciliation candidates; produce forecasts and analyses; and explain an authoritative deterministic calculation.

AI **may not, without explicit human authorization**, issue or void invoices; alter rates; post journals; allocate payments; write off balances; move client money; approve disbursements; release refunds; close or reopen accounting periods; reconcile accounts; change tax treatment; or expose restricted financial data. Client-money movements are absolute: **AI may never initiate, approve, or release a trust-to-operating transfer, a disbursement, or any other client-money movement**, regardless of confidence.

## AI Copilot and Workflow Automation

The following govern the OneLegalPro internal professional Copilot and its relationship to the `Workflow` bounded context, per `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md` and `docs/architecture/01_OneLegalPro_Constitution.md`, Articles 38–44. They extend, and do not modify, the Legal Intelligence, Communications Hub, Practice Management, Document Intelligence, Knowledge Intelligence/RAG, and Financial AI rules above — a Copilot answer touching any of those domains still resolves through that domain's own rules unchanged.

**Governing principle, restated for a cross-domain capability.** The Copilot is advisory only. It is never a lawyer, an authorization authority, a domain-data owner, a ledger or calculator, an approver, a workflow definition, a workflow engine, a privileged system administrator, an autonomous agent with unrestricted tools, or a substitute for professional review. It may understand a request, retrieve authorized context, draft or suggest an action plan, and propose a workflow action; it has no effect on any domain until that proposal passes the same authorization and human-approval composition every other action would.

**AI-run provenance.** Every Copilot output and every AI-dependent workflow step carries an `AIProvenance` record: model and model version; provider or processor reference; timestamp; prompt-template version; policy version; source identifiers and versions; access-decision provenance; workflow/run correlation identifiers; confidence or uncertainty where meaningful; citations and required disclaimers; and human-review state. This is the same provenance discipline this document already requires of Legal Intelligence retrieval, Communications AI, Document Intelligence, Knowledge Intelligence, and Financial AI output, applied uniformly to cross-domain Copilot activity.

**Context manifests and retrieval filtering.** Firm and permission filtering happen before retrieval and before any material enters Copilot context — never after, and never as a post-generation repair, consistent with the retrieval discipline established throughout this document and in Constitution Article 21. Every `AIRun` records a `ContextManifest` enumerating the authorized `SourceReference`s that entered its context and the access decision that permitted each one. Legal Intelligence material retrieved into Copilot context still carries authority status, version, and disclaimer per this document's authority-aware RAG rules; Knowledge material still carries approval status, version, and staleness per this document's Knowledge Intelligence rules; Document-derived material remains derived and provenance-tagged per this document's Document Intelligence rules; financial material still carries currency and "as of" time, with authoritative totals computed deterministically outside the model, per this document's Financial AI rules.

**Tool proposals and approval-gated execution.** A Copilot-proposed action (`ToolCallProposal`) is a proposal only — it has no effect until an authorized human accepts it (or a narrowly bounded, Firm-configured `AutomationPolicy` covers it at an eligible low-risk tier) and it is translated into a Workflow `ActionRequest` that passes the owning domain's own current authorization. The Copilot operates under an explicit tool allowlist, least-privilege tool access, and schema-validated tool inputs and outputs; it never discovers or grants itself a broader tool inventory, never holds direct shell, database, or credential access, and never bypasses a published command or query to act on a domain module's data directly.

**Non-delegable actions.** Regardless of confidence, Firm configuration, or automation-policy version, AI may never, without explicit human authorization: present generated content as legal advice or official law; create an attorney-client relationship; change Matter status; assign or remove lawyers; override an Ethical Wall; broaden permissions or Client Portal audiences; approve or publish Knowledge; remove confidentiality, privilege, retention, or legal-hold restrictions; finalize, sign, file, send, publish, or delete a legal document; send a substantive client communication outside an already-approved, narrow Communications policy; issue or void an invoice; alter rates or tax treatment; post journals; allocate payments; write off balances; reconcile accounts; move, release, or disburse client money; approve a refund or trust-to-operating transfer; change identity, permissions, MFA, delegation, or privileged access; expose restricted data; approve its own tool call or workflow proposal; or perform a destructive operation. **AI may never approve its own proposal under any configuration.**

**Prompt-injection and untrusted-content handling.** Documents, web content, email, messages, uploaded files, retrieved knowledge, and external payloads are treated as untrusted data, never as trusted instructions, regardless of which domain's AI feature encounters them. Where retrieved or supplied content appears to instruct the model directly, the safe behavior is to decline the implied instruction and, where appropriate, escalate for human review — never to silently comply. An authorized human operator retains an emergency disable capability over Copilot-initiated proposals and AI-dependent workflow triggers, independent of deterministic, non-AI workflow execution.

**Memory.** Copilot memory is conservative by default: no hidden, unrestricted, durable memory; no cross-Firm memory, context, caching, embeddings, evaluation, or training use, without exception and regardless of anonymization claims; no privileged content in evaluation data without explicit, separately approved governance. Any retained preference or memory is explicit, Firm-scoped, access-controlled, auditable, revocable, and retention-governed, and deleting a memory record never deletes a required audit fact.

**Session identity.** A `CopilotSession` is internal professional assistance bound to one Firm and one initiating human actor. It is not a Communications `CommunicationThread`; client-facing receptionist and messaging capabilities remain owned by Communications and governed by the Communications Hub AI rules above, unchanged.

**White-label presentation.** The Copilot's display name, avatar, and presentation may use Branding's `AIPersonaConfig`, but branding may never suppress or alter AI identification, advisory-only framing, human-review requirements, citations, disclaimers, provenance, uncertainty, or security warnings — the same non-suppression principle Constitution Article 11 already establishes for branding generally.

**Model or provider failure.** An unavailable model provider fails the Copilot turn or AI-dependent workflow step visibly; it never falls back to fabricating a response or silently skipping a safety check. Deterministic workflow steps that do not require AI remain conceptually separable from AI-dependent steps, so a model outage never corrupts workflow state or rewrites domain truth.

Full conceptual detail — workflow and Copilot aggregates, the human-approval and automation-policy model, risk tiers, trigger and idempotency semantics, cancellation and compensation, audit, and failure handling — is in `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md`; this document states the AI-governance rules that architecture must satisfy, consistent with how it already governs every other domain's AI capability above.
