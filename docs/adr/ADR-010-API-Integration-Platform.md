# ADR-010 — API & Integration Platform

## Status

Accepted. The human owner has explicitly approved this decision.

## Context

Every approved architecture already names an external API or integration surface it does not itself own, and none of them defines the platform-wide standards those surfaces would need to share safely:

- **Digital Presence** (`docs/architecture/12_Website_Client_Portal_Architecture.md` §16) supports Public APIs for the enterprise CMS integration deployment model and Embedded APIs for widgets, and states plainly that "the Public API surface, scopes, and versioning are reserved for ARCH-009," and that versioning conventions "belong to `docs/architecture/07_API_Standards.md`, currently an empty placeholder."
- **IdentityAccess** (`docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §34) owns the authenticated service principal and its credential lifecycle, and states that "ARCH-009 will own Public API surfaces, endpoint scopes, versioning, developer portals, webhook contracts, rate-limit policy, and integration orchestration."
- **Billing** (`docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §30) already defines payment-provider webhook verification, idempotency, and replay handling for its own adapters, but has no shared, platform-wide pattern for webhook ingress that other domains could reuse without duplicating the same safety logic.
- **Communications** (`docs/architecture/11_Communications_Hub_Architecture.md` §16) already defines `ChannelAdapter` for provider-specific messaging integrations, following the same "adapter behind a published contract" discipline this ADR extends platform-wide.
- **`docs/architecture/07_API_Standards.md` has been an empty reserved placeholder since ARCH-001**, deferred by every subsequent story, leaving the platform with strong per-domain security and data rules and no shared external-contract discipline tying them together.

Two structural risks follow from this gap. First, **without a shared standard, each domain would invent its own API conventions** — its own pagination, its own error shape, its own versioning discipline — producing exactly the fragmentation `docs/domain/06_Laravel_Module_Blueprint.md` exists to prevent, applied here to the platform's external face rather than its internal one. Second, and more seriously, **an API or integration surface is a new path into data that already has strict internal rules** — Firm isolation, Ethical Walls, deny-by-default document and financial visibility, co-client isolation. If external access is designed as a bolt-on convenience layer rather than a first-class consumer of those rules, it becomes the weakest link: a scope that looks narrower than it is, a webhook that keeps delivering after an installation is revoked, or an export that quietly bypasses an Ethical Wall a normal query would have respected.

Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, this needs approved architecture before any API, webhook, connector, or integration implementation begins, and before the dependent stages already staged in `docs/architecture/08_Roadmap.md` — Digital Presence's enterprise API/CMS integration, IdentityAccess's service-principal API-authentication foundation, Communications' external-channel adapters, Billing's payment-provider webhook and reconciliation boundaries, and Documents' secure upload/download integrations — can be implemented.

**No contradiction with prior architecture was found.** Every existing reference names a future API/Integration Platform and disclaims ownership of it for itself; `docs/architecture/07_API_Standards.md` was confirmed empty before this ADR. This decision resolves those dependencies without rewriting approved history — ARCH-001 through ARCH-016 and ADR-001 through ADR-009 are unmodified.

## Decision

1. **Integrations is a supporting bounded context**, proposed module `Integrations`. It owns external API contract registration and lifecycle, external representation mapping, API version and deprecation metadata, integration application registration, Firm-scoped integration installations, requested external scopes and installation configuration, redirect URI and callback endpoint registration, webhook subscriptions, stable external integration-event contracts, webhook delivery attempts and delivery state, inbound webhook envelopes and replay protection, idempotency records for external commands, connector configuration metadata, secure references to integration credentials, synchronization cursors and checkpoints, import/export job coordination, API usage/quota/health metadata, and developer documentation/sandbox coordination.

2. **Domain modules retain ownership of business data and business rules.** Integrations owns *how the outside world reaches the platform*, never *what the platform's data means*. Practice Management still owns Client/Matter/MatterTeam/Ethical Walls; Documents still owns document records and versions; Billing still owns invoices, payments, and ledgers; Communications still owns messages and channels; Branding still owns brand profiles; Legal Intelligence still owns official sources; IdentityAccess still owns principals, service principals, credentials, and permission grants. **Integrations translates a stable external contract into an owning module's published command or query — it never writes to another module's tables and never recreates its business rules.**

3. **Public REST/JSON contracts are explicit and versioned.** Every external resource and action is described by a major-version namespace and an OpenAPI-described contract; a version is never changed silently, and additive changes are distinguished from breaking ones (§4 of `docs/architecture/07_API_Standards.md`).

4. **External DTOs are separate from internal models.** No Eloquent model, internal domain event, database column, or internal identifier is ever exposed as a public contract. A stable external representation is authored deliberately and evolves on its own schedule, independent of internal refactoring.

5. **IdentityAccess owns authentication and service principals.** Every external caller — human via delegated access, or machine via a service principal — authenticates through IdentityAccess's published contracts (`docs/architecture/16_Identity_Security_Access_Control_Architecture.md`). Integrations never defines its own authentication mechanism, credential store, or session model.

6. **Scopes never replace domain authorization.** A requested and granted API scope is necessary but never sufficient: the owning domain still evaluates its own authorization — including Practice Management's Ethical Wall decision — before any protected data is returned or any protected action is taken. A scope can only ever narrow what a principal could otherwise do; it can never widen it.

7. **Every installation is Firm-scoped.** An integration application definition never grants access to any Firm by existing. Installing it into a Firm is an explicit, authorized, auditable act, and a shared application used by many Firms has fully isolated installation state, credentials, cursors, quotas, subscriptions, and audit records per Firm.

8. **Internal domain events are not exposed directly.** A stable external integration-event envelope, versioned independently of the internal event it may summarize, is the only thing ever delivered externally. Confidential fields are never carried into the envelope merely because they exist on the internal event.

9. **External integration events and webhooks are versioned contracts**, evolving under the same compatibility and deprecation discipline as the REST API, and never silently reshaped to match an internal refactor.

10. **Delivery is at-least-once, and consumers must be idempotent.** No delivery mechanism in this architecture claims exactly-once delivery or global ordering; per-subject ordering is guaranteed only where explicitly stated.

11. **Inbound webhooks are authenticated, replay-resistant, and idempotent** before any business meaning is attached to them, and are translated into an owning module's application command rather than writing to a domain table directly.

12. **External calls never occur inside a domain database transaction**, extending the existing platform rule (`docs/domain/06_Laravel_Module_Blueprint.md`) explicitly to every integration path.

13. **Bulk imports and exports remain permission-aware and auditable.** An export is never granted merely because a user can view one item; Ethical Walls and co-client restrictions apply to bulk operations exactly as they apply to a single read.

14. **API and webhook failures do not rewrite domain truth.** A rendering, delivery, or provider failure is visible and retried; it never rolls back or silently alters an already-committed business record.

15. **Developer experience is contract-driven.** Documentation, examples, and any future generated SDK are derived from the authoritative OpenAPI contract; a generated SDK never becomes a second source of truth for what the API does.

16. **Workflow ownership belongs to `Workflow` (ARCH-010, `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md`).** Integrations coordinates request/response, event delivery, and job lifecycle; it does not orchestrate multi-step business processes across domains.

17. **Marketplace distribution remains separately governed.** No public marketplace, app store, paid-app billing model, partner certification program, or cross-Firm knowledge/app exchange is architected here; `docs/architecture/06_Marketplace.md` remains an empty placeholder.

18. **Implementation and vendor choices remain deferred.** No API gateway, identity vendor, message broker, or hosting product is selected. This architecture defines protocol families, contracts, and security properties, not products.

## Domain ownership

| Concept | Owner | Integrations's relationship |
|---|---|---|
| External API contracts, versions, DTOs, integration application/installation records, webhook subscriptions/delivery, inbound webhook envelopes, idempotency records, connector configuration, sync cursors, import/export job coordination, usage/quota/health metadata, developer docs/sandbox coordination | **Integrations** | Owner |
| Principals, service principals, credentials, sessions, roles, permission grants, delegation | **IdentityAccess** | Consumed for every authentication and authorization decision |
| `FirmContext`, tenant resolution, tenant middleware | **Platform Foundation** | Consumed; Integrations resolves no tenancy of its own |
| `Client`, `Matter`, `MatterTeam`, Ethical Walls | **Practice Management** | `CheckEthicalWallAccess` remains the sole authority |
| Documents, knowledge, invoices, communications, legal sources, brand profiles | **Their owning contexts** | Reached only through published commands/queries; never duplicated |
| Business rules and record truth | **The owning domain module**, always | Integrations translates a stable contract into the owning module's command/query |
| Workflow orchestration | **`Workflow`** (ARCH-010, `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md`) | Not designed here; Integrations supplies verified events and delivery only |
| Marketplace publication/monetization | **Reserved, ungoverned** | Not designed here |
| AI authority over API access | **Nobody — AI is never an authorization authority** | Unchanged from Constitution Article 26 |

## Alternatives considered

- **A generic integration layer that also owns domain business rules.** Rejected — this is precisely the "god module" `docs/domain/06_Laravel_Module_Blueprint.md`'s Prohibited patterns exists to prevent, applied at the platform's edge; every domain's invariants would end up duplicated or contradicted in the layer that talks to the outside world.
- **Let each domain module build and expose its own public API independently.** Rejected — produces exactly the fragmentation this ADR's Context section describes: inconsistent pagination, versioning, and error shapes, and no single place to enforce authentication, rate limiting, or webhook safety consistently.
- **Expose internal domain events directly as the public webhook contract.** Rejected — couples external consumers to internal refactoring, risks leaking confidential fields that exist on the internal event but were never meant for an outside party, and removes the platform's ability to evolve internal events without a breaking external change.
- **Let internal modules call OneLegalPro's own public HTTP API to talk to each other.** Rejected — internal cross-module communication already has a working, lower-latency, transactionally-coherent mechanism (published contracts, commands, queries, and domain/integration events); routing internal calls through the public API adds latency, a network dependency, and a confused-deputy risk for no benefit.
- **Treat a granted API scope as sufficient authorization on its own.** Rejected — a scope describes what an integration *asked for*; it says nothing about Ethical Walls, co-client isolation, document restrictions, or financial visibility, all of which are per-request, per-resource decisions the owning domain must still make.
- **One global API credential per application, shared across every Firm that installs it.** Rejected — makes cross-Firm data leakage a single-credential compromise away, and makes per-Firm revocation impossible without disabling the application for every Firm at once.
- **Claim exactly-once delivery for outbound webhooks.** Rejected — no reliable-delivery mechanism this architecture can honestly specify guarantees exactly-once end-to-end; claiming otherwise sets external integrators up to build fragile, non-idempotent consumers.
- **Claim global ordering across all webhook deliveries.** Rejected — for the same reason; only per-subject ordering is achievable without a coordination cost this architecture does not require.
- **Make external calls inside a domain's database transaction (e.g., call a payment provider while holding a lock on an invoice row).** Rejected — already prohibited platform-wide by `docs/domain/06_Laravel_Module_Blueprint.md`; an external call's latency and failure modes inside a transaction risk long-held locks and inconsistent rollback semantics.
- **Allow webhook delivery to continue for a short grace period after an installation is revoked, for consumer convenience.** Rejected — a revoked installation is a security decision, not a courtesy; continued delivery after revocation is a live confidentiality and authorization risk with no acceptable grace window.
- **Let Integrations implement payment-provider, messaging-provider, identity-provider, or other provider-specific webhook verification itself, to avoid duplicating shared logic.** Rejected. Integrations owns the shared ingress envelope and pipeline — bounded receipt, raw-body preservation within an enforced size bound, generic replay/idempotency coordination, registration status, rate limiting, quarantine policy, provenance, and dispatch — but the provider-specific verification contract itself (signature algorithm, required headers, canonicalization rules, provider credential/secret lookup, provider-specific acknowledgement semantics) stays with the owning domain's own adapter: Billing's payment-provider adapter, Communications' channel adapter, IdentityAccess for identity-provider callbacks, and each other owning domain for its own providers. Integrations invokes that published verification contract as a step in its shared pipeline; it never implements the provider-specific signature logic itself, and no business meaning is attached to a callback until the owning adapter reports successful verification. A shared, generic Integrations mechanism must never weaken a provider's actual required verification procedure.
- **Move service-principal issuance and credential storage into Integrations, since it is "the API module."** Rejected — `docs/architecture/16_Identity_Security_Access_Control_Architecture.md`, Decision 11, already places service principals in IdentityAccess precisely because they are a principal type, not an API concern; duplicating that ownership here would recreate the exact "two credential stores" risk ADR-009 rejected for human authentication.
- **Architect a public marketplace, paid app store, or partner certification program as part of this story.** Rejected — explicitly out of scope; that is future, separately governed Marketplace work against `docs/architecture/06_Marketplace.md`, which remains an empty placeholder.
- **Select an API gateway, identity vendor, or message broker now, to "unblock" implementation sooner.** Rejected — vendor selection should follow the conceptual boundary, not precede it; no vendor is selected, endorsed, or configured by this ADR.

## Consequences

- Every domain module gains a consistent, predictable path to external exposure, at the cost of routing external requests through one more contract-translation layer rather than exposing internal models directly.
- Firm-scoped installation state means a shared application's operational complexity scales with the number of Firms that install it — each with its own credentials, cursors, quotas, and subscriptions — which is the correct trade for eliminating cross-Firm leakage risk.
- At-least-once delivery without exactly-once or global-ordering guarantees means every external consumer must be built to handle duplicates and reordering; this is stated honestly rather than promised away.
- Contract-driven developer experience means the OpenAPI contract becomes a genuine deliverable with its own review discipline, not an afterthought generated from routes.
- EPIC-010 becomes a dependency of Digital Presence's enterprise API/CMS integration, IdentityAccess's service-principal API-authentication foundation, Communications' external-channel adapters, Billing's payment-provider webhook and reconciliation boundaries, Documents' secure upload/download integrations, and Legal Intelligence's API-consumer provenance/disclaimer preservation — all of which must account for EPIC-010's foundation stages.
- Deferring vendor selection means implementation cannot begin on a specific gateway or provider until a separate, approved implementation-focused decision is made; this is deliberate, matching the precedent set by every prior domain architecture on this platform.

## Security and professional-responsibility consequences

- **An API scope, OAuth-style grant, or service principal never overrides an Ethical Wall.** The composition in `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §24 applies unchanged to every external request.
- **No email, hostname, header, or request-body Firm identifier proves membership.** `FirmContext` for an external request is derived only from authenticated identity and authorized installation, exactly as Constitution Article 27 already requires internally.
- **Denied external callers receive no existence signal** — no protected content, metadata, count, search result, aggregate, or confirmation that a restricted record exists, extending the same discipline every domain already applies internally to the platform's external edge.
- **Long-lived, non-rotatable secrets are prohibited everywhere in this architecture**, including any compatibility-mechanism API key, which must additionally be Firm- and purpose-bound, tied to a service principal, non-recoverable, shown only at issuance, rotatable, revocable, scope-limited, audited, and unusable for human interactive login.
- **Rate limiting and quotas are operational safeguards, never a substitute for authorization.** A request within quota that fails authorization is still denied.
- **Revoking an installation promptly revokes API access, webhook delivery, and connector activity** without deleting the immutable audit history of what already occurred under it.
- **Bulk export and import never bypass Ethical Walls, co-client restrictions, financial visibility, or document controls** — the same authorization composition governs one record and ten thousand records alike.

## Integration consequences

- **Digital Presence** finally gets an owner for its Public and Embedded API surfaces' versioning and scope model; it retains ownership of portal presentation and widget rendering.
- **IdentityAccess** remains the sole authority for authentication and service-principal credentials; Integrations consumes its contracts rather than duplicating them.
- **Practice Management** supplies `CheckEthicalWallAccess` for every Matter-linked external request; it gains no new ownership and loses none.
- **Documents, Knowledge, Billing, and Communications** each retain full ownership of their own resource rules and provider-specific semantics (document delivery, knowledge retrieval eligibility, financial invariants, messaging channels); Integrations supplies only the shared external-contract and ingress machinery around them.
- **Legal Intelligence**'s official-source provenance and mandatory disclaimer requirements (Constitution Articles 2–4) apply unchanged to any API response surfacing translated legal content.
- **`Workflow` (ARCH-010, `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md`)** consumes Integrations' contracts for orchestration without Integrations absorbing workflow state.
- **A future Marketplace architecture** will govern any cross-Firm distribution of integrations, separately from this ADR.

## Explicit non-goals

This ADR does **not**: implement any API, webhook, connector, or integration; create schemas, migrations, routes, controllers, jobs, or packages; select or configure an API gateway, identity vendor, message broker, cloud service, or Laravel package; claim any API is implemented or deployed; claim ISO, SOC, PDPA, GDPR, or any other certification or compliance; design Workflow orchestration (owned by `Workflow`, ARCH-010 — see `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md`); design Marketplace publication, monetization, or partner certification (reserved, separately governed, against `docs/architecture/06_Marketplace.md`); grant AI any authorization authority; move service-principal ownership out of IdentityAccess; put payment-provider or messaging-provider business semantics into Integrations; promise exactly-once delivery or global event ordering; or schedule EPIC-010 implementation.

## Implementation status

This ADR, `docs/architecture/17_API_Integration_Platform_Architecture.md`, and the populated `docs/architecture/07_API_Standards.md` are **conceptual architecture only**. They authorize no application code, routes, controllers, migrations, schemas, dependencies, packages, infrastructure, Docker configuration, CI changes, environment changes, or runtime behavior. **No control described anywhere in this ADR is claimed to be implemented.** EPIC-010 — API & Integration Platform is recorded as **proposed, not scheduled** in `docs/architecture/08_Roadmap.md`; none of its stages carries a story ID. **PF-010 remains current and PF-011 remains next.**

The story ID is **ARCH-009** while the new sequential architecture document is numbered **17** (`ARCH-017`), continuing the established distinction between story numbering and architecture-document numbering that ARCH-006/ARCH-014, ARCH-007/ARCH-015, and ARCH-008/ARCH-016 already set.
