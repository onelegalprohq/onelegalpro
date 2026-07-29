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

## White-label rules

- OneLegalPro is white-label by design — no Firm-facing or client-facing surface may hardcode a non-brandable OneLegalPro identity. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 10–11), `docs/architecture/10_White_Label_Platform_Architecture.md`, and `docs/adr/ADR-003-White-Label-Platform.md`.
- UI styling uses theme tokens only, resolved per Firm through the Branding Resolver — never hardcoded brand-specific colors, fonts, or asset paths in template or component code.
- Branding is presentation-only and must never suppress a mandatory legal disclaimer, citation, or AI-advisory notice.

## Communications rules

- Communications is its own bounded context — a `CommunicationThread` per counterparty, per Firm, unifying every channel into one timeline. Other modules reference communications through published links; they never own or duplicate communication history. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 12–13), `docs/architecture/11_Communications_Hub_Architecture.md`, and `docs/adr/ADR-004-Communications-Hub.md`.
- Provider-specific logic stays behind a `ChannelAdapter`; the Application and Domain layers never branch on a specific provider.
- Communications AI (Website AI Receptionist, Email Intelligence, AI Assistance) must identify itself, never imply an attorney-client relationship or give legal advice, and never send a communication without configured human authorization.

## Digital Presence rules

- Digital Presence (public websites, Client Portal, embedded widgets, booking) composes the Branding Engine and the Communications Hub through their published contracts — it never reimplements branding or messaging logic. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 14–15), `docs/architecture/12_Website_Client_Portal_Architecture.md`, and `docs/adr/ADR-005-Website-Client-Portal.md`.
- The Client Portal is a presentation surface, not a data owner, for Matters, Documents, Invoices, Payments, Tasks, and Appointments — it reads through the owning module's published queries and never duplicates that data into its own schema.
- Every embedded widget is one reusable, sandboxed component (Booking, Client Login, Matter Status, AI Chat, Contact, Payment, Document Upload) — never a bespoke per-site or per-Firm reimplementation.
- The embedded AI receptionist is the Communications Hub's AI, not a separate AI system; it stays bound by the Communications rules above wherever it is embedded.

## Practice Management rules

- Practice Management is the platform's Core Domain — it owns Client, Organization, Contact, Matter, Matter Team, Practice Area, Task, Appointment, Note, and Activity. It never owns Communications, Documents, Billing, Legal Intelligence, or Branding; every other module reaches Practice Management data only through its published contracts. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 16–17), `docs/architecture/13_Practice_Management_Architecture.md`, and `docs/adr/ADR-006-Practice-Management-Core.md`.
- `Matter` is the central aggregate; `Task`, `Appointment`, `Note`, and `EthicalWall` are independent aggregates that reference `Matter`, never entities owned inside it.
- The Matter Timeline and Matter Dashboard are read-model projections over other bounded contexts' published events and queries — never owned, materialized copies of their data.
- Ethical Wall access is checked only through Practice Management's published `CheckEthicalWallAccess` query; no other module implements its own wall logic.
- AI may summarize and suggest tasks, timelines, practice area, lawyer, and deadlines. AI must never change matter status, assign lawyers, close matters, or override Ethical Walls without explicit human authorization.

## Document & Knowledge Management rules

- Documents is its own bounded context containing two explicitly separated domain areas — **Document Management** and **Knowledge Management**. The proposed module stays named `Documents`; the separation is enforced by distinct aggregates, lifecycles, access policies, and retrieval eligibility, not by a module wall. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 18–21), `docs/architecture/14_Document_Knowledge_Management_Architecture.md`, and `docs/adr/ADR-007-Document-Knowledge-Management.md`.
- It is the sole owner of the canonical document record and every stored document version. Stored versions are immutable — a correction or replacement creates a new version, preserving historical bytes, checksums, authorship, timestamps, and provenance.
- Every document belongs to exactly one Firm, with Firm identity explicit and enforced in application and repository paths — never by global scopes alone.
- Matter-linked access derives from Practice Management's published contracts, and Ethical Wall authorization comes only from `CheckEthicalWallAccess`. Document-level controls may narrow that access, never widen it; where a document is reachable from more than one restricted Matter, the most restrictive outcome applies. A denied caller receives no content, metadata, preview, search hit, or existence confirmation.
- Client Portal visibility is explicit and deny-by-default. Internal availability never implies portal visibility, publication is a distinct recorded decision, and audience resolution names specific `MatterClient`s — one co-client must never see another's document merely because they share a Matter.
- Document objects are private, never served from public or guessable locations; delivery is authorization-gated and short-lived. Uploads require size and media-type validation, filename normalization, and malware scanning, and stay quarantined until positively cleared — an unavailable or indeterminate scanner fails closed. Previews and other derivatives inherit their source's security boundary.
- Retention is Firm- and jurisdiction-aware; archival is not deletion; a legal hold blocks deletion, purge, retention expiry, and destructive redaction until an authorized human releases it. Deleting content never destroys the audit fact that the document existed.
- AI and OCR output is derived annotation, never canonical content or authoritative metadata: structurally separate, provenance- and confidence-tagged, and removable without altering the source. AI must never, without explicit human authorization, alter document content, finalize/sign/file/send/publish/delete a document, change legal-hold or retention state, broaden access, confirm a low-confidence Matter/Client association, or send privileged content to an unapproved model or processor.
- Other modules reach Documents only through published commands, queries, and events — never its tables or object-storage paths — and Documents reaches Practice Management, Communications, Billing, Branding, and Legal Intelligence only through theirs. Storage paths never cross a module boundary; events carry identifiers and safe metadata, never document bytes, knowledge body text, privileged content, or embeddings.

**Knowledge Management**

- A source `Document` and a curated `KnowledgeItem` are distinct records with different authority, lifecycle, audience, and governance — neither is a state of the other. Knowledge covers precedents, clauses, templates, playbooks, practice notes, research notes, and internal guidance. Legal Intelligence still owns official law; Digital Presence still owns public editorial content; Practice Management still owns Clients, Matters, `MatterClient`s, `PracticeArea`s, `MatterTeam`s, and Ethical Walls.
- **A Matter document never automatically becomes Firm-wide knowledge.** Promotion requires an explicit human curation workflow that creates a separate record, retains access-controlled provenance, and reaches recorded determinations on confidentiality, privilege, personal data, contractual restrictions, and Ethical Walls before any reuse audience exists. Removing names is not de-identification — re-identification risk from context must be assessed. Source restrictions and any legal hold on the source remain effective until an explicit authorized declassification decision says otherwise.
- Approval is human, version-specific, and never AI-performed. Each approved knowledge version is immutable; updates create a new version, and superseded/retired versions stay auditable. Every approved item has a Firm, a named owner, provenance, an approval record, an access policy, and a review policy — missing any of these makes it invalid. Overdue review status is always visible, and stale knowledge is never silently presented as current.
- Knowledge access may narrow Matter-derived access but never overrides an Ethical Wall; collection membership and practice-area association never grant access. A denied caller receives no title, snippet, tag, metadata, provenance, citation, search hit, or existence confirmation.
- **Search and AI/RAG filter by Firm and then by permission before retrieval** — never after retrieval, and never by post-generation filtering. Only approved, retrieval-eligible, access-authorized content enters standard AI context. Index entries and embeddings inherit their source's Firm and access boundary; an access change, revocation, supersession, or retirement removes them from future retrieval without erasing audit history. Cross-Firm retrieval, embeddings, caching, evaluation data, and model context are prohibited.
- AI may suggest, draft, propose curation candidates, flag stale or conflicting knowledge, and retrieve authorized knowledge for drafting. AI must never, without explicit human authorization, approve or publish knowledge, make Matter-derived knowledge Firm-wide, remove confidentiality/privilege/contractual/personal-data/Ethical Wall restrictions, change an approved version, retrieve inaccessible content, mix Firms' knowledge, treat draft or AI output as approved Firm guidance, present Firm know-how as official law, or send privileged content to an unapproved processor.

## Billing, Trust Accounting & Finance rules

- Billing is its own bounded context containing three explicitly separated financial domain areas — **Billing/Accounts Receivable**, **Client Money/Trust Accounting**, and **Firm Finance/Accounting**. It owns invoices, payments, client-money/trust ledgers, and the Firm's general ledger. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 22–25), `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md`, and `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md`.
- Practice Management owns Client, Matter, `MatterClient`, Matter teams, and Ethical Walls; Billing references them by identifier through published contracts only, and Ethical Wall authorization comes only from `CheckEthicalWallAccess`. Documents owns rendered invoice/receipt/statement/tax-document artifacts; Billing owns their commercial meaning. Billing stores no document bytes, owns no portal presentation, and owns no communication threads.
- **Issued invoices and posted ledger entries are immutable.** Corrections are adjustments, reversals, credit notes, debit notes, or replacement records — never a rewrite. Posted history is corrected, never edited.
- **`Money` and `Currency` are Foundation Library primitives governed by PF-045, not Billing's.** Billing consumes the Foundation contract and **must never introduce a second, incompatible money or currency type** — nor may any other module. Exact decimal with explicit currency; floating-point money is prohibited. Billing owns the financial-domain layer over it: `ExchangeRate` provenance, tax treatment and identifiers, invoice numbering, allocation instructions, authorization records, beneficiary attribution, financial classification, and accounting/jurisdiction policy versions. Multi-currency amounts record transaction currency, base currency, rate, rate source, and rate timestamp; historical conversions are never recalculated with a newer rate.
- **All balances are derived from append-only ledger entries. Direct balance mutation is prohibited** — there is no editable balance field anywhere.
- **Client money is money held by the Firm that is not the Firm's own money.** It is segregated from operating funds and is never Firm revenue. A payment is not automatically revenue; a trust balance is never an invoice balance. No negative client/Matter balance, no cross-client funding, no silent commingling, no unexplained transaction. Trust-to-operating transfers require an eligible obligation plus explicit human authorization; cross-client and cross-Matter transfers are deny-by-default and Ethical-Wall-checked on both sides.
- **Beneficial entitlement is recorded, never inferred.** A held balance may be beneficially owned by the client on the Matter, by an identified third-party beneficiary, or by a party pending an authorized determination — never presumed from the depositor, payer, Primary `MatterClient`, or Matter membership. Accountable client/Matter subledgers are retained; entitlement is a distinct recorded fact alongside them. Being named as beneficiary or payer grants no Matter, portal, invoice, or document access.
- Payment processing stays behind provider adapters with tokenized/provider-held references only — never raw card credentials, CVV data, or reusable banking secrets. Webhooks are authenticated, idempotent, and replay-resistant, and a provider status is never a posted ledger entry.
- **Financial visibility is deny-by-default, and co-client isolation is absolute** — one client on a shared Matter never sees another's invoice, payment method, payment, or trust balance without an explicit authorized audience decision. A Primary `MatterClient` is only the default billing recipient, not the liable party, audience, payer, or beneficiary. Third-party payment grants no Matter or portal access. A denied caller receives no balance, amount, metadata, report, or existence confirmation.
- Tax treatment, invoice requirements, numbering, and retention are effective-dated jurisdiction policy backed by an official primary source and approved by an authorized human owner. **Never hardcode a tax rate or claim compliance.** OneLegalPro is not a bank, escrow provider, custodian, or regulated payment service.
- **Financial AI is advisory only.** Authoritative totals are computed deterministically outside the model. AI must never issue or void invoices, alter rates, post journals, allocate payments, write off balances, move client money, approve disbursements, release refunds, close or reopen periods, reconcile accounts, change tax treatment, or expose restricted financial data without explicit human authorization.
- **A Practice Management outage fails closed.** Every operation needing Matter validation, Matter authorization, `MatterClient` audience resolution, or `CheckEthicalWallAccess` — reads and writes alike, including audience changes, disclosure of Matter-linked financial data, receipt attribution requiring Matter verification, disbursements, trust-to-operating and cross-Matter/cross-client transfers, refunds and allocations, exports, and AI context construction — is rejected or left explicitly pending. Never approximate an authorization decision or use a stale cached Ethical Wall result. Only local bookkeeping recording an already-authorized fact, with its authorization provenance preserved, may continue. Availability never outranks authorization, Ethical Walls, or trust-account safeguards.
- **Documents, Communications, or Digital Presence outages never rewrite or roll back an issued or posted financial record.** Rendering, delivery, and integration queue and retry; outbound events wait in the outbox.
- Other modules reach Billing only through published commands, queries, and events, and Billing reaches Practice Management, Documents, Communications, Digital Presence, Branding, and Legal Intelligence only through theirs. Financial events carry identifiers and safe metadata only — never payment secrets, bank credentials, privileged narratives, or cross-client financial content.

## Identity, Security & Access Control rules

- IdentityAccess is its own bounded context and owns Firm-scoped principals and actor identifiers, identity lifecycle, Firm membership, credentials and authenticators, MFA/passkeys, sessions, recovery, invitations, roles and permission grants, delegations, service principals, privileged-access and break-glass grants, and security events. **Security is cross-cutting, not a module** — see `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 26–30), `docs/architecture/04_Security_Architecture.md`, `docs/architecture/16_Identity_Security_Access_Control_Architecture.md`, and `docs/adr/ADR-009-Identity-Security-Access-Control.md`.
- **Principals are Firm-scoped and every session is bound to exactly one Firm context.** Firm access requires an active, verified Firm membership. Switching Firms is an explicit authorized transition that carries no authorization across.
- **Never trust a client-supplied Firm or Actor identifier.** A hostname, custom domain, email address, header, parameter, or route may identify a candidate Firm but never proves membership. `FirmContext` is built only from verified identity and membership. Email is never a cross-Firm identity key, and automatic cross-Firm account linking is prohibited.
- **Successful authentication is not resource authorization.** Authorization is deny-by-default, server-side, and composed from principal + membership + Firm-bound session + capability + domain action/resource semantics + Ethical Wall result + narrower domain restrictions + step-up/SoD + explicit denies. Hiding a button is never authorization.
- **Domain modules own their resource rules; IdentityAccess may narrow but never widen.** The most restrictive applicable decision wins. **Practice Management alone owns Ethical Wall decisions** via `CheckEthicalWallAccess` — no role, delegation, privileged grant, or break-glass overrides a wall, and IdentityAccess never reimplements the check.
- **Authorize before retrieval, search, export, aggregation, and AI context construction** — never after. Bulk, background, and AI paths get no weaker treatment than interactive requests, and a queued job preserves the initiating actor and re-verifies rather than acting as an indefinite grant.
- **Denied callers receive no content, metadata, count, aggregate, search hit, or existence confirmation.** **No stale positive authorization survives** membership removal, role revocation, suspension, wall change, or policy change; authorization caches are policy- and version-aware and invalidated promptly.
- **Credentials, reusable secrets, recovery material, and full session tokens never appear in logs, analytics, events, or audit payloads.** Passwords use approved adaptive hashing; WebAuthn stores public keys, never biometrics. Magic links are single-use, short-lived, purpose- and Firm-bound, with no open redirects. Recovery never silently bypasses MFA or Firm policy. Session identifiers rotate after authentication and elevation; high-risk actions require step-up.
- **Service principals are distinct from human principals** — they cannot use human login paths, cannot silently gain interactive privileges, and cannot bypass Ethical Walls or domain authorization. Workload credentials are purpose-limited, rotatable, revocable, expiring where possible, and never stored or recoverable in plaintext.
- **Platform operators have no Firm access by default, and silent impersonation is prohibited.** Support access is explicit, purpose-bound, and time-limited; acting-as and acting-on-behalf-of are both recorded. **Break-glass is exceptional, justified, time-limited, and immutably audited**, never disables Firm isolation/Ethical Walls/legal holds/financial segregation/audit, and never permits changing security ownership, broadening permissions, moving client money, or destroying audit history.
- **Human, system, integration, and AI actors must remain distinguishable in audit.** An AI-assisted operation records the initiating human, the AI/system actor, the authorization relied on, and any required approval. **AI is never an authorization authority** and may never grant a role, permission, membership, session, delegation, or privileged grant.
- **Security decisions fail closed.** New authentication, privilege elevation, and high-risk operations are refused or held when the authoritative decision is unavailable; an already-issued session continues only within its validity and satisfied revocation requirements. A notification or rendering failure never rolls back a committed security change. Availability never outranks Firm isolation, authorization, Ethical Walls, financial safeguards, or privilege protection.
- **Digital Presence is not an authentication authority.** IdentityAccess owns the Client Portal principal, credentials, authenticators, recovery, and sessions; Digital Presence owns the portal surface, preferences, and permission-aware composition via `ClientPortalAccessProfile`, and must never store passwords, authenticators, recovery secrets, or session authority. Widget `EmbedKey`/`AllowedOrigin` scope is narrower than a client session and is never a Client identity.

## API & Integration Platform rules

- Integrations is a supporting bounded context (proposed module `Integrations`) owning external API contracts, integration applications and Firm-scoped installations, webhook subscriptions/delivery, connector configuration, and import/export job coordination. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 31–37), `docs/architecture/07_API_Standards.md`, `docs/architecture/17_API_Integration_Platform_Architecture.md`, and `docs/adr/ADR-010-API-Integration-Platform.md`.
- **Public API contracts are versioned and explicit.** A breaking change requires a new major version, a deprecation notice, and a migration window, and requires explicit human approval before merge. **Public DTOs are not Eloquent models** — no internal model, column, or internal domain event is ever exposed directly.
- **Integrations never writes another module's tables or recreates its business rules.** It translates a stable external contract into the owning domain's published command or query; the owning domain keeps final authorization and business-rule responsibility. Payment-, messaging-, document-, and identity-provider semantics stay with Billing, Communications, Documents, and IdentityAccess respectively — never absorbed into Integrations.
- **IdentityAccess owns service-principal authentication.** Integrations defines no credential store, password, or session model of its own; every external caller — human delegated access or service principal — authenticates through IdentityAccess.
- **Firm isolation and domain authorization always apply to external requests.** No email, hostname, header, or request-body Firm ID proves membership; `FirmContext` comes only from authenticated identity and an authorized, Firm-scoped installation. **API scopes never widen domain access** — a scope narrows, and only the owning domain's own authorization decides what a request may actually reach.
- **Ethical Walls apply before retrieval or delivery, with no exception for an API path.** No scope, OAuth-style grant, or service principal overrides `CheckEthicalWallAccess`; Practice Management remains its sole authority. Rate limiting and quotas are operational safeguards, never a substitute for authorization.
- **Internal events are not automatically public events.** A stable, versioned `IntegrationEventEnvelope` is authored deliberately for external delivery; confidential fields are never carried into it merely because they exist on the internal event.
- **Webhooks are signed, replay-resistant, and idempotent.** Delivery is at-least-once with no claim of exactly-once delivery or global ordering; inbound webhooks are verified before any business meaning is attached and are translated into an owning module's command, never a direct table write. Delivery stops immediately on installation revocation.
- **External calls never occur inside a database transaction.**
- **Secrets and confidential payloads never enter logs or events.** No long-lived, non-rotatable API secret is permitted; any compatibility API key is Firm-bound, purpose-bound, non-recoverable, rotatable, revocable, and audited.
- **Public API breaking changes require explicit human approval**, per the existing approval-gate list below.
- **Workflow orchestration remains outside Integrations** — it is the approved boundary of `Workflow` (see AI Copilot & Workflow Automation rules, below); Integrations supplies verified events and delivery, never orchestration state. **Marketplace distribution remains outside Integrations** — reserved for a future Marketplace architecture, not designed here.

## AI Copilot & Workflow Automation rules

- `Workflow` is a supporting bounded context (proposed module `Workflow`) with two explicitly separated domain areas — **Workflow Orchestration** and **AI Copilot** — owning orchestration and AI-run state only, never domain truth. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 38–44), `docs/architecture/05_AI_Architecture.md` (AI Copilot and Workflow Automation), `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md`, and `docs/adr/ADR-011-AI-Copilot-Workflow-Automation.md`.
- **Published workflow versions are immutable, and a running workflow's version binding is permanent.** Publishing, superseding, or retiring a version never alters an already-running instance, and no one — including an administrator — may migrate an existing run to a different version. If the process must continue under a newer version, the existing run is cancelled and a new run is created against that version with its own fresh authorization, approval, and idempotency scope; no approval carries over.
- **Every workflow step invoking a domain action does so through the owning module's published command or query, with that module's own current authorization re-checked at execution time.** Workflow never writes another module's tables, imports another module's Eloquent model, recreates another module's business rules, or treats same-process execution, prior authentication, or AI output as proof that an action is valid. Practice Management continues to own the `Task` model — Workflow calls its published commands rather than defining a competing one.
- **The OneLegalPro Copilot is assistive and governed, never an authorization or approval authority.** It may retrieve authorized context and propose an action; the proposal has no effect until it passes the same approval and authorization composition any other action would. The Copilot never writes another module's tables, holds provider credentials, executes arbitrary code, calls an unrestricted tool, bypasses Firm isolation, bypasses an Ethical Wall, or approves its own proposal — **AI can never be the approving actor for its own proposal, under any Firm configuration.**
- **Four roles are distinct and never collapsed: AI proposes, a human decides, Workflow orchestrates, the owning domain authorizes and executes.** A human's approval of an AI proposal never converts AI into the acting or approving principal; the resulting action is attributed to the human decision actor and the owning domain's execution, and is never described as "AI performing the action."
- **Human review is the default for consequential action.** An approval is bound to the exact Firm, run, workflow version, action, target, material-input fingerprint, approving actor, and policy version; a change to the material input invalidates the approval. A Firm-configured `AutomationPolicy` may pre-authorize only explicitly eligible, bounded, low-risk actions — never a blanket grant, and never a non-delegable action.
- **Non-delegable actions are of two kinds, neither weaker than the other.** *Human-gated domain actions* — changing Matter status, assigning/removing lawyers, finalizing/signing/filing/sending/publishing/deleting a legal document, issuing/voiding an invoice, and changing identity/permissions/MFA/delegation/privileged access — a qualified human may still decide; AI may at most propose or draft, and Workflow/the owning domain then execute it under the human's authority. *Structural, absolute prohibitions* — presenting content as official law or legal advice, implying an attorney-client relationship, overriding or deciding an Ethical Wall outcome, acting as an authentication/authorization authority, approving its own proposal or tool call, unrestricted shell/database/credential access, and any client-money movement — hold regardless of human-approval wording or Firm configuration, because they describe what AI is, not a delegation boundary. The full list is in `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md` §11. **Financial calculations remain deterministic outside the model, and client-money movement is never AI-initiated, AI-approved, or AI-released.**
- **Long-running and queued workflow or Copilot work revalidates current authorization, membership, and Ethical Wall status before every protected retrieval or consequential action** — the authorization captured when a run started is never treated as an indefinite grant. A client-supplied Firm or Actor identifier is never trusted.
- **Firm and permission filtering happen before retrieval and before any material enters Copilot context — never after.** Every AI output carries provenance (model/version, prompt-template version, source identifiers/versions, access-decision provenance, confidence, citations, disclaimers, human-review state). Documents, web content, email, messages, uploads, and retrieved knowledge are treated as untrusted data, never as trusted instructions; the Copilot operates under an explicit tool allowlist and never expands its own permissions or tool inventory.
- **No cross-Firm Copilot context, memory, embedding, cache, evaluation, or training use, without exception.** Any retained memory or preference is explicit, Firm-scoped, access-controlled, auditable, revocable, and retention-governed; deleting memory never deletes a required audit fact.
- **External triggers enter Workflow only after Integrations' shared ingress pipeline and the owning domain's provider verification** — never as unverified business truth. **A `CopilotSession` is internal professional assistance, never a `CommunicationThread`** — client-facing delivery remains Communications' own, through its own commands and human-authorization discipline.
- **Cancellation stops future work; it never erases a committed business fact.** Undoing a completed action requires an explicit, separately authorized compensating command to the owning domain — never a rollback achieved by editing workflow state.
- **No AI/model provider, workflow-engine product, queue/event-broker product, vector database, orchestration framework, agent framework, or observability vendor is selected.** No exactly-once delivery or global ordering is claimed. Marketplace publication/monetization and cross-Firm workflow-template distribution remain outside this scope.

## Platform Administration rules

> **Status: Approved (ARCH-011).** The rules in this section and the next derive from `docs/architecture/01_OneLegalPro_Constitution.md` Articles 45–48 and from `docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md`, `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md`, `docs/adr/ADR-014-Operator-Assisted-Onboarding-and-Privileged-Access.md`, and `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` — **all Accepted by explicit owner approval recorded on PR #30 on 29 July 2026**. Architecture approval does not authorize implementation, deployment, or production access; every applicable story and approval gate below still applies.

- `PlatformAdministration` is a **narrowly bounded supporting context owning exactly three concepts — `Firm`, `FirmProvisioning`, and `SubscriptionEntitlement` — and nothing else.** It closes the ownership gap beneath the platform's tenancy model: the `Firm` record every other context references and none previously owned. See `docs/architecture/01_OneLegalPro_Constitution.md` (Articles 45–48), `docs/architecture/19_Platform_Administration_Architecture.md`, and `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md`.
- **Its narrowness is the rule, not an accident.** It never becomes a platform back office, support console, administrative impersonation feature, feature-flag system, reseller or partner hierarchy, usage-metering capability, or self-service signup — **each of those requires its own separately approved architecture**, and adding a fourth concept to this context requires its own approved ADR.
- **`PlatformAdministration` never owns or performs cross-Firm reporting, analytics, benchmarking, or comparison**, and **no `PlatformAdministration` record, query, cache, projection, index, or event spans Firms**. That is a boundary on this context, **not a platform-wide prohibition**: Constitution Article 44 reserves a **future Reporting bounded context**, which requires its own approved architecture and which ARCH-011 **neither approves nor permanently prohibits**. Any such future capability must preserve Firm isolation, authorization before retrieval, denied-existence confidentiality, purpose limitation, and the most restrictive applicable domain rule.
- **Owning the `Firm` record is never access to what is inside the Firm.** `PlatformAdministration` performs no authentication, holds no credential, session, authenticator, recovery material, or invitation, makes no authorization decision, defines no operator access path, and never stores, reads, derives, aggregates, or exports a Firm's business data.
- **`PlatformAdministration` owns its own administrative audit facts, and only those** — Firm creation and lifecycle, provisioning, entitlement, seat-limit decisions, and authorization refusals **within its own context**. It **never stores or duplicates** Practice Management's business or activity history, IdentityAccess's security-event content, privileged narratives, or any other context's audit records; **Firm-visible support-access history is owned by IdentityAccess**. Audit and event payloads carry **safe metadata only** — never credentials, session secrets, recovery material, privileged content, Client or Matter content, or cross-Firm information.
- **`SubscriptionEntitlement` records exactly term, status, and seat limit.** It carries **no amount, price, currency, `Money`, `Currency`, invoice, payment, ledger entry, balance, discount, proration, tax rate, or tax treatment**, and no field from which one could be reconstructed. **Billing remains the sole owner of Firm-to-client commercial and financial records** (Articles 22–25). Manual external contracting, invoicing, and payment stay outside the application.
- **Entitlement is not authorization, and never a per-request authorization input.** IdentityAccess evaluates it at exactly two gates: **at authentication — only after successful credential and any required MFA verification, and before a session is issued** — and at **membership activation**, for the seat limit. **Never check it earlier**: doing so lets an unauthenticated caller learn whether a Firm exists or whether its subscription is active. **Never check it per request on an already-issued valid session**: the approved lapse policy lets that session run to normal expiry, so a per-request check would terminate exactly the sessions that policy protects. **Session renewal, reauthentication, and any new Firm-bound session are new authentication decisions and re-check entitlement.** Resource authorization stays owned and composed by IdentityAccess and the domain modules under Constitution Article 28; **entitlement is not a term in that composition and never grants resource access.**
- **Entitlement failures are enumeration-resistant in text, response shape, and practicable timing.** A lapsed entitlement must not be distinguishable from a wrong password by an unauthenticated observer. A **verified** user — credential and MFA already proven — may be given the **minimum safe operational instruction** to seek help, and it reveals nothing about any other Firm or account.
- Entitlement decisions **fail closed** — when entitlement state cannot be authoritatively determined, **no session is issued**, and the same applies to renewal and reauthentication.
- **Entitlement lapse and membership revocation never share code, audit meaning, or policy semantics.** An expired or suspended entitlement is a **commercial/administrative** fact that **blocks new authentication** while existing valid sessions continue only to their normal expiry; membership suspension or revocation is an **individual security event terminating that principal's sessions immediately**.
- **There is no Firm-level suspended state.** The `Firm` lifecycle is `Requested → Provisioned → Active → Closed` (owned by `docs/architecture/19_Platform_Administration_Architecture.md` §7), and **neither suspension meaning above is a Firm-wide disable**. **Never invent one** — a future Firm-level suspension or emergency-disable capability requires its own separately approved decision defining the authorizing authority, session effects, recovery, notification, queued-and-in-flight-work behaviour, and audit semantics distinguishing it from both entitlement lapse and membership revocation.
- **Seat limits are enforced at membership activation, deterministically, and never retroactively.** A seat limit reduced below the active count never silently revokes anyone — the condition is surfaced for an authorized human.
- **All operator access to Firm data runs through IdentityAccess's `PrivilegedAccessGrant` and nothing else** — explicit, purpose-bound, time-limited, strongly authenticated, dually attributed with **no silent impersonation**, and recorded in a **Firm-visible, append-only support-access history**. **No second privileged-access mechanism exists.** Where break-glass is excluded as a capability, Constitution Article 29's break-glass rules remain fully in force, and ordinary support access is never used for break-glass purposes.
- **Every `PlatformAdministration` record concerns exactly one Firm.** No record, query, projection, cache, or event spans two, and no cross-Firm read exists in this context. `FirmContext` is still built only from verified identity and membership.
- **AI holds no authority here.** AI never creates, activates, or closes a `Firm`, never creates, changes, suspends, or restores an entitlement term, status, or seat limit, and is never an authorization authority.

## Release 0.1 scope rules

- **A deferral is never a waiver.** An approved control a release does not deliver stays in force as architecture, is recorded as deferred with its reason, and is re-introduced **additively**. See `docs/architecture/01_OneLegalPro_Constitution.md` (Article 47), `docs/architecture/02_Product_Requirements.md`, and `docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md`.
- **Never stub, approximate, simulate, partially implement, or rename an absent control.** In a legal-practice product an approximated control is more dangerous than an absent one, because only the second tells a Firm what it must do for itself.
- **Ethical Walls and automated conflict checking are absent from Release 0.1.** No `EthicalWall`, allow-list, restricted-matter flag, private-matter toggle, hidden-from-worklist mechanism, or per-user Matter visibility rule; no `ConflictRelationship`, `PartyReference`, `SearchConflicts`, name matching, fuzzy matching, similarity scoring, or party-graph traversal. **Constitution Article 17 is unamended, and Practice Management remains the sole wall authority when walls are built.** See `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`.
- **Manual conflict attestation is a human determination, never a system finding.** It is actor-attributed, timestamped, and append-only, and it satisfies the `Opened` gate. An unrecorded checkbox, default-true field, nullable flag, or silent-pass path is prohibited. **Never describe, label, present, document, or market it as a conflict check performed by the system** — no surface may state or imply that OneLegalPro searched for, detected, screened for, cleared, or found no conflicts.
- **Scope reduction never reduces an invariant.** The reduced Matter lifecycle (`Prospective → Opened → Active → Closed`, `Cancelled` terminal) preserves every approved invariant: the conflict-attestation gate before `Opened`, `MatterNumber` immutability from `Opened`, exactly one Primary `MatterClient` at `Opened` and never absent afterwards, explicit human authorization of every transition, and `MatterStatusChanged` events. A smaller state machine does not license a weaker one.
- **The white-label deferral is bounded, not a waiver of Articles 10–11.** **Never hardcode a OneLegalPro brand value in a Firm-facing template or component.** Firm-facing presentation values resolve through **one replaceable indirection** until EPIC-003 delivers the `BrandProfile` foundation.
- **The operator's OneLegalPro marketing website is not a Firm tenant website and not the Client Portal.** It lives outside the Digital Presence bounded context and **outside `app/Modules`**, creates no Client Portal principal, no `ClientPortalAccessProfile`, and no Firm membership, and never weakens the Firm-facing rules above.
- **Disclose absent capabilities** — Ethical Walls, automated conflict checking, automated reminders and notifications, and document storage — **in-product where reliance would occur and in the pilot agreement.** Treat the Privacy Notice, Terms, pilot agreement, and all marketing and security copy as **draft** until Thai-qualified human legal review and owner approval are recorded. **Never claim a reviewer is engaged or that review is complete.**
- **A date is never a reason to move a control.** Firm isolation, authorize-before-retrieval, denied-existence confidentiality, actor attribution, human approval, immutable audit, fail-closed behaviour, and the Thai-language legal-authority and disclaimer rules (Articles 1–4) are not schedule variables.
- **Never claim production readiness, certification, or compliance.** Production access requires recorded evidence of an approved database design, an approved deployment architecture, an **executed** restore test, monitoring, a documented incident procedure, completed Thai-qualified legal review, and every applicable approval gate below.
- **Planning is not scheduling.** A Release 0.1 story is `Backlog` until it has its own approved entry and Definition of Ready; **no story becomes In Progress because planning exists**, and pull requests remain serialized at one active implementation PR at a time.

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
