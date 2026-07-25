# ARCH-017 — API & Integration Platform Architecture

**Status:** Approved (conceptual architecture) — implementation stages are **proposed, not scheduled**. **PF-010 remains the current repository implementation story and PF-011 remains next.** No `PF-*` story is added, renumbered, or rescheduled by this document. See `docs/architecture/08_Roadmap.md`.

## 1. Purpose and scope

This document defines the conceptual domain and system architecture for OneLegalPro's Public API & Integration Platform — proposed module `Integrations` — implementing `docs/adr/ADR-010-API-Integration-Platform.md` and the relevant articles of `docs/architecture/01_OneLegalPro_Constitution.md`.

**In scope:** external API contract registration and versioning; integration application and Firm-scoped installation lifecycle; requested scopes and their relationship to domain authorization; outbound integration events and webhook delivery; inbound webhook verification and command intake; connector configuration and synchronization; import/export job coordination; developer documentation and sandbox coordination; and the cross-cutting security, privacy, audit, and failure-handling requirements that apply to all of the above.

**Out of scope:** everything in §41, and specifically Workflow orchestration (reserved for a future ARCH-010) and Marketplace publication/monetization (reserved, ungoverned, against `docs/architecture/06_Marketplace.md`).

This document describes **conceptual models only**. It defines no migrations, schemas, routes, controllers, jobs, packages, or vendor selection.

## 2. Bounded-context classification

Integrations is a **Supporting subdomain**, per Eric Evans' terminology, in the same sense Documents, Communications, and Billing are Supporting subdomains around Practice Management's Core Domain. It exists to serve every other bounded context's need to be safely reachable from outside the platform, without itself becoming a second owner of what any of them mean.

## 3. Why Integrations is a bounded context

Every approved architecture already names an external API or integration capability it does not own:

| Dependent context | What it references but does not own |
|---|---|
| Digital Presence | Public APIs, Embedded APIs, and their versioning — explicitly reserved for "ARCH-009" (`docs/architecture/12_Website_Client_Portal_Architecture.md` §16) |
| IdentityAccess | "Public API surfaces, endpoint scopes, versioning, developer portals, webhook contracts, rate-limit policy, and integration orchestration" — explicitly reserved for ARCH-009 (`docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §34) |
| Billing | Payment-provider webhook verification and reconciliation, built for its own adapters with no shared, reusable ingress pattern |
| Communications | `ChannelAdapter` provider integrations, the same "adapter behind a published contract" pattern this document extends platform-wide |
| Documents | Secure upload/download delegation and any future external document integration |

No candidate is a plausible owner. Each domain module building its own API independently reproduces the exact fragmentation `docs/domain/06_Laravel_Module_Blueprint.md` exists to prevent — inconsistent versioning, pagination, and error shapes, and no single place to enforce authentication and webhook safety consistently. A dedicated bounded context gives every domain one place to publish a stable external contract, and gives revocation, rate limiting, and delivery safety a single point of application.

## 4. Domain ownership

**Integrations owns**

External API contract registration and lifecycle · external representation mapping · API version and deprecation metadata · integration application registration · Firm-scoped integration installations · requested external scopes and installation configuration · redirect URI and callback endpoint registration · webhook subscriptions · stable external integration-event contracts · webhook delivery attempts and delivery state · inbound webhook envelopes and replay protection · idempotency records for external commands · connector configuration metadata · secure references to integration credentials · synchronization cursors and checkpoints · import/export job coordination · API usage, quota, and integration-health metadata · developer documentation and sandbox coordination.

**Integrations does not own**

| Concept | Actual owner |
|---|---|
| Business-domain records (Clients, Matters, Documents, Invoices, Messages, etc.) | **Each owning domain module** |
| Authentication credentials, sessions, or service-principal identity | **IdentityAccess** |
| Firm tenancy resolution, `FirmContext` | **Platform Foundation** |
| Domain authorization rules, resource-level facts | **Each owning domain module** |
| Ethical Wall decisions | **Practice Management**, via `CheckEthicalWallAccess` exclusively |
| `Client`, `Matter`, `MatterTeam` | **Practice Management** |
| Documents or knowledge content | **Documents** |
| Communications content | **Communications** |
| Billing or financial ledgers | **Billing** |
| Branding | **Branding** |
| Legal Intelligence content | **Legal Intelligence** |
| Workflow orchestration | **Reserved for a future ARCH-010** |
| AI authority | **Nobody — AI is never an authorization authority** |
| Marketplace publication or monetization | **Reserved, ungoverned**, against `docs/architecture/06_Marketplace.md` |
| Provider-specific business semantics already owned elsewhere | **The owning domain's adapter** (§29) |

## 5. Ubiquitous language

| Term | Meaning |
|---|---|
| **External contract** | A versioned, OpenAPI-described public representation of a resource or action — never an internal model. |
| **Integration application** | A registered definition of a third-party or Firm-built integration. Grants no access by existing. |
| **Installation** | The Firm-scoped, authorized act of activating an application for one specific Firm. |
| **Scope** | A named, requested unit of access an installation asks for. Never itself a grant. |
| **Permission grant** | IdentityAccess's resulting grant after a scope request is authorized; still subject to domain authorization at request time. |
| **Service principal** | The IdentityAccess-owned machine identity an installation authenticates as. |
| **Connector** | A configured, Firm-scoped synchronization relationship with an external system. |
| **Synchronization cursor** | The durable checkpoint marking how far a connector has progressed. |
| **Integration event** | A stable, versioned external representation of something that happened, distinct from the internal domain event it may summarize. |
| **Webhook subscription** | An installation's registration to receive a class of integration events at a callback endpoint. |
| **Delivery attempt** | One recorded try to deliver one integration event to one subscription. |
| **Inbound webhook envelope** | The verified, replay-checked wrapper around an inbound provider or partner callback, before translation into a command. |
| **Idempotency key** | A caller-supplied token ensuring a retried external command is applied at most once. |
| **Import/export job** | An asynchronous, authorized, audited bulk operation. |
| **Sandbox** | An isolated, non-production environment for integration development, never containing copied production Firm data by default. |

## 6. Trust boundaries

| Boundary | Discipline |
|---|---|
| Public API caller → Integrations | Untrusted; authenticated via IdentityAccess; rate-limited; enumeration-resistant |
| Webhook provider → Integrations | Untrusted until signature-verified; raw body preserved for verification; treated as hostile input until proven otherwise |
| Integrations → domain module | Published commands/queries only; no direct table access |
| Integrations → external provider/partner | Outbound adapter, never inside a domain database transaction (§12) |
| Integrations → IdentityAccess | Consumes authentication/authorization contracts; never re-implements them |
| Integrations → Practice Management | Consumes `CheckEthicalWallAccess`; never re-implements wall logic |
| Sandbox → production | Hard isolation; no default production-data copy (§39) |

## 7. Domain model overview

Aggregates are grouped into five areas, each with its own lifecycle and consistency boundary: **Contract & Application** (§13–§15), **Installation & Consent** (§16–§18), **Outbound Delivery** (§26–§28), **Inbound Intake** (§29–§30), and **Connector & Bulk Operations** (§31–§35). See §22–§25 for the full aggregate/entity/value-object catalog.

## 8. API edge

The API edge is the single, uniform entry point for every external REST/JSON request:

```text
Request → Authenticate (IdentityAccess) → Resolve Firm/installation context
        → Authorize (domain composition, incl. Ethical Wall) → Rate-limit/quota check
        → Translate to domain command/query → Owning module executes
        → Map result to external DTO → Respond
```

No step is skippable, and no step reorders ahead of authorization. A request that fails any step earlier than "owning module executes" never reaches domain data.

## 9. Authentication integration

- **All external requests use IdentityAccess results.** Integrations defines no authentication mechanism, password, token format, or session model of its own.
- **Human delegated access** — an end user authorizes an installation to act with a bounded subset of their own access, through IdentityAccess's consent and delegation contracts (`docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §26).
- **Service-principal access** — a machine-to-machine caller authenticates as an IdentityAccess-owned `ServicePrincipal` (§12), never through a human login path.
- **Short-lived access wherever practical** — tokens and sessions issued for API use follow IdentityAccess's expiry and rotation discipline; nothing here introduces a longer-lived credential than IdentityAccess's own policy permits.

## 10. Authorization composition

Every external request composes, in order, exactly as `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §24 already establishes internally, with the installation's granted scope added as an explicit input:

1. Authenticated principal or service principal (IdentityAccess)
2. Active Firm membership or valid installation
3. Firm-bound session or service context
4. **Granted API scope** — necessary, never sufficient
5. Owning domain's action and resource semantics
6. Owning domain's resource facts
7. Practice Management's `CheckEthicalWallAccess` result, where Matter-linked data is involved
8. Any narrower document, knowledge, financial, portal-audience, or jurisdiction restriction
9. Required step-up or segregation-of-duties policy
10. Explicit deny rules

**Invariants**

- **No API scope widens domain authorization.** A scope can only ever narrow what its holder could otherwise reach.
- **No API key, OAuth-style grant, or service principal overrides an Ethical Wall.** `AuthorizeAction` composes; it never substitutes for `CheckEthicalWallAccess`.
- **Deny is total.** A denied external caller receives no content, metadata, count, aggregate, search hit, or existence confirmation — the same rule Constitution Article 28 already states internally, extended to the platform's edge.
- **Authorization precedes retrieval, search, count, export, and AI context construction** — never after.
- **Fail closed** when identity, authorization, or Ethical Wall authorities are unavailable (§37).
- **No caller-selected tenant switching.** A request cannot name a different Firm than the one its authenticated context and installation resolve to.
- **No global integration identity with silent access to every Firm.** Every installation is Firm-scoped (§16).

## 11. Firm isolation

- **No email, hostname, header, or request-body Firm ID proves membership.** `FirmContext` for an external request is constructed only from the authenticated principal/service-principal identity and its authorized installation — never trusted because it arrived in a request parameter, header, hostname, or body field, extending Constitution Article 27 to the API edge without exception.
- **Every repository and query invoked through Integrations is Firm-scoped**, enforced at the application and repository layers, never by convention alone.
- **One independently approved installation per Firm.** A shared application installed in Firm A carries no relationship to its installation in Firm B — separate credentials, separate scopes, separate cursors, separate quotas, separate webhook subscriptions, separate audit records (§17).

## 12. API scope model

- A **scope** is a named, published unit of access a domain module defines for its own capability vocabulary (`docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §21) — Integrations catalogs scopes for external consumption but does not invent domain capabilities.
- **Requested scopes are not granted permissions.** An application requests scopes; installation and consent (§16–§18) produce an IdentityAccess permission grant; the owning domain still makes the resource-level authorization decision at request time (§10).
- Scopes are **purpose-bound and least-privilege** by design — a scope names a capability ("read Matter-linked documents a client may see"), never a blanket table or module.
- **Effective-dated and revocable.** A scope grant can be narrowed or revoked independently of the rest of an installation.

## 13. Application registration

`IntegrationApplication` is the definition of an integration — first-party (built by the platform operator) or third-party.

- **An application definition never automatically grants access to any Firm.** Defining an application is a catalog act, not an access act.
- Carries: name, description, requested scope set, redirect URI(s), callback endpoint(s) for webhooks, and a lifecycle (`Draft → Published → Deprecated → Retired`).
- **Redirect URI and callback registration require exact matching at use time** — no wildcard or prefix matching that could be abused to redirect an authorization flow or webhook delivery to an unregistered destination.
- A retired application's existing installations are handled per §18 (revocation); retiring the definition never silently revokes every Firm's installation without notice.

## 14. Installation lifecycle

`IntegrationInstallation` is the Firm-scoped activation of an application.

```text
Requested → Pending Firm-Admin Approval → Active ⇄ Suspended → Revoked (terminal)
```

- **Installation requires an authorized Firm decision** — explicit Firm-admin consent and approval — and **produces an auditable record** (§14, §40).
- **No automatic cross-Firm installation.** Installing an application in one Firm has no effect on any other Firm.
- **One independently approved installation per Firm** per application; re-installing after revocation is a new, independently authorized act, not a reactivation of history.
- **Active** installations hold their own scope grants, credentials, cursors, quotas, and webhook subscriptions.
- **Suspended** installations retain their configuration but are denied at the authorization step (§10) until reactivated.
- **Revoked is terminal and prompt.** Revoking an installation **promptly revokes associated API access, webhook delivery, and connector activity**, and **never deletes the immutable audit history** of what already occurred under it.

## 15. Consent

- Firm-admin installation approval is itself a recorded, auditable act — who approved, which scopes, when.
- Where an installation acts on behalf of an individual (human delegated access, §9), that individual's consent is recorded separately from the Firm's installation approval; both are required where both apply.
- Consent to a scope set does not imply consent to any scope added later — a scope-set change re-triggers approval.

## 16. Credential relationship

- An installation's **service principal and its credentials are owned by IdentityAccess** (§9; `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §37). Integrations holds only a **secure reference** to the credential relationship — never the secret itself.
- **Long-lived, non-rotatable API secrets are prohibited.** Where an API key is supported as a compatibility mechanism, it must be: Firm- and purpose-bound; associated with a service principal; stored non-recoverably where possible; displayed only at issuance; rotatable; revocable; expiring where practical; scope-limited; audited; and prohibited from human interactive login.
- **Rotation supports overlap without indefinite dual validity** — the same bound-overlap discipline `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §37 already requires for service credentials generally.

## 17. API standards (summary)

Full normative detail lives in `docs/architecture/07_API_Standards.md`; this section summarizes the standards Integrations exists to enforce:

- Resource-oriented routes, with explicit action endpoints where a domain action does not fit CRUD.
- Major-version API namespaces; OpenAPI-described external contracts; stable external DTOs distinct from internal entities.
- Additive vs. breaking compatibility rules; deprecation notices and supported migration windows; a stated sunset policy.
- JSON request/response media types, with content negotiation where appropriate.
- Opaque identifiers; UUIDv7 handling; ISO-8601 timestamps; UTC transport with explicit legal/business timezone context where required; exact decimal money with ISO currency codes (consuming the Foundation `Money` contract per Constitution Article 23).
- Cursor-based pagination; explicit, allow-listed filtering/sorting/sparse-field rules; bounded page sizes.
- Consistent, problem-style validation and error responses; correlation and request identifiers on every response.

## 18. Versioning and compatibility

- **Major-version namespaces** (for example `/v1/...`) are the unit of breaking change; a new major version is a new, coexisting contract, not a replacement that silently changes behavior underneath existing callers.
- **Additive changes** (new optional field, new endpoint, new enum value where the consumer is expected to tolerate unknown values) do not require a version bump.
- **Breaking changes** (removing or renaming a field, changing a field's type or meaning, tightening validation) require a new major version and an explicit, published deprecation notice for the prior version.
- **Deprecation carries a supported migration window** — a stated period during which both versions are live — communicated in the developer documentation and, where practical, in response metadata.
- **Sunset is a scheduled, communicated event**, never a silent removal.
- **Public API breaking changes require explicit human approval**, per the existing `AGENTS.md` approval gate.

## 19. Idempotency

- **Every retryable external command accepts an idempotency key.** A retried request with the same key against the same resource produces the same recorded outcome exactly once, never a duplicate side effect.
- Idempotency records are Firm- and installation-scoped, with a bounded retention window sufficient to cover realistic retry behavior.
- Idempotency applies uniformly to inbound webhook processing (§30) and outbound command initiation.

## 20. Concurrency

- **Optimistic concurrency using version or ETag-style preconditions** governs updates to a resource through the API — a caller states the version it last read, and a conflicting concurrent update is rejected rather than silently overwritten.
- Concurrency conflicts are reported as a distinct, structured error, never as a generic failure indistinguishable from an authorization denial.

## 21. Pagination and querying

- **Cursor-based pagination** is the default and required mechanism for any collection endpoint; offset-based paging is not the standard, since it drifts under concurrent writes.
- **Explicit filtering, sorting, and sparse-field rules** — every filterable/sortable field is deliberately published by the owning domain, never inferred from column names, and **arbitrary sort/filter expressions are never accepted**.
- **Bounded page sizes**, with a platform-wide maximum a caller cannot exceed regardless of a requested size parameter.
- Every query executed through a collection endpoint passes through the same authorization composition (§10) as a single-resource read — a filter can never become a way to enumerate records a caller could not otherwise see.

## 22. Async operations

- Long-running work (bulk export/import, large synchronization, expensive reports) is modeled as an **async operation resource**: a request creates a job, returns a status reference, and the caller polls or is notified.
- The status resource exposes state (`Pending → Running → Succeeded | Failed | Cancelled`), progress where meaningful, and a result reference once complete — never partial or speculative results presented as final.
- Async operations inherit the same authorization composition and Firm scoping as any other resource; a job's status is visible only to the principal/installation authorized to have requested it.

## 23. Files

- **File upload and download are delegated to Documents.** Integrations never stores document bytes, never issues its own storage references, and never bypasses Documents' quarantine, scanning, or access rules (`docs/architecture/14_Document_Knowledge_Management_Architecture.md`).
- An API file-upload endpoint accepts the file, forwards it into Documents' standard intake pipeline, and returns Documents' resulting reference; an API file-download endpoint requests Documents' short-lived, authorization-gated delivery mechanism rather than serving a stored path directly.

## 24. Aggregates, entities, and value objects

**Aggregate roots**

- **`ApiContract`** — a versioned external contract for one resource or action family, owning its `ApiContractVersion` history and deprecation metadata. Independent because contract evolution happens on its own release cadence, decoupled from any specific application or installation.
- **`IntegrationApplication`** — the application definition (§13), owning its requested scope set and registered redirect/callback endpoints.
- **`IntegrationInstallation`** — the Firm-scoped activation (§14), owning its granted scopes, connector configuration reference, and webhook subscriptions for that Firm. Kept separate from `IntegrationApplication` precisely because one definition has many independent installations, each with its own consent, credentials, and revocation lifecycle — collapsing them would make per-Firm isolation impossible to express.
- **`WebhookSubscription`** — a specific installation's registration to receive a class of integration events; independent lifecycle (subscribe/pause/unsubscribe) from the installation's broader activation state.
- **`WebhookDelivery`** — one integration event's delivery record to one subscription, owning its `DeliveryAttempt` entities. Separate from the event itself because one event may fan out to many subscriptions, each with its own delivery outcome.
- **`InboundWebhookEnvelope`** — the verified, replay-checked wrapper for one inbound callback, existing independently of any resulting domain command so that verification and intake are auditable even when translation fails.
- **`IdempotencyRecord`** — an independent, short-lived aggregate recording the outcome of one idempotency-keyed command, deliberately separate from the command's own domain aggregate so idempotency bookkeeping never pollutes domain history.
- **`Connector`** — a Firm-scoped, configured synchronization relationship with an external system, owning its `SyncCursor` and reconciliation state (§31–§35).
- **`ImportExportJob`** — an asynchronous bulk operation with its own request → validate → execute → deliver lifecycle (§36–§38), independent so its provenance and audit survive regardless of the resources it touches.
- **`DeveloperSandbox`** — an isolated environment coordination record (§39), independent of any specific application or installation.

**Entities**

- **`ApiContractVersion`** — owned by `ApiContract`; one published version with its own compatibility notes and sunset date.
- **`ScopeGrant`** — owned by `IntegrationInstallation`; one requested-and-approved scope with its own effective dates.
- **`ServiceCredentialReference`** — owned by `IntegrationInstallation`; a secure reference to the IdentityAccess-owned credential (§16), never the secret.
- **`DeliveryAttempt`** — owned by `WebhookDelivery`; one recorded try with outcome, timestamp, and response metadata.
- **`SyncCursor`** — owned by `Connector`; the durable checkpoint for one synchronization direction.
- **`ReconciliationRecord`** — owned by `Connector`; a recorded discrepancy or conflict awaiting human resolution (§34).

**Value objects**

`ApiVersion`, `EndpointIdentifier`, `RedirectUri`, `CallbackEndpoint`, `ScopeName`, `InstallationStatus`, `WebhookSecret` (opaque, non-retrievable reference), `SignatureAlgorithm`, `IdempotencyKey`, `CorrelationId`, `CausationId`, `DeliveryStatus`, `RetryPolicy`, `RateLimitPolicy`, `SyncDirection` (Pull/Push/Bidirectional), `RemoteIdentifier`, `MappingProvenance`, `BatchSize`, `JobStatus`, `DataClassification` (consumed from `docs/architecture/04_Security_Architecture.md` §7), `SandboxContext`.

**Consumed from Platform Foundation, not defined here:** `AggregateRoot`, `Entity`, `ValueObject`, `DomainEvent`, `BusinessIdentifier`, `Result`, `Clock`, `UUIDv7` (PF-040–PF-049); `Money`/`Currency` (PF-045); `FirmContext` (PF-080).

**Consumed from IdentityAccess, not defined here:** `PrincipalId`, `ActorReference`, `ServicePrincipal`, `AuthenticationStrength`, `AuthorizationDecision`.

## 25. Aggregate-boundary reasoning

- **`IntegrationApplication` and `IntegrationInstallation` are separate** because one definition has many independent Firm installations, each with its own consent, scope grants, credentials, and revocation timeline — exactly the reasoning `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` applies to keeping `BillingArrangement` distinct from the entries billed under it.
- **`WebhookSubscription` and `WebhookDelivery` are separate from the integration event itself** because one event fans out to many subscriptions with independent delivery outcomes; conflating them would make "the event failed to deliver to Firm B's subscription" indistinguishable from "the event never happened."
- **`InboundWebhookEnvelope` exists independently of any resulting command** so that a verification failure, or a translation failure, is itself an auditable fact — the same reasoning that keeps `DocumentAnnotation` structurally separate from the `DocumentVersion` it derives from.
- **`IdempotencyRecord` is its own aggregate**, never folded into the command it protects, so idempotency bookkeeping has its own bounded lifecycle and never becomes part of domain history.
- **`Connector` and `ImportExportJob` are independent** because a connector's synchronization relationship long outlives any single job, and a job's provenance must survive regardless of which resources it happened to touch.

## 26. Outbound webhooks

- **Internal domain events are not exposed directly as the permanent public contract.** A stable **`IntegrationEventEnvelope`** is the only thing ever delivered externally, containing: event identifier; event type; contract version; occurrence time; subject reference; correlation and causation references; integration installation context; a minimal, authorized payload; and delivery-attempt metadata.
- **Confidential fields are never included merely because they exist on the internal event.** Building the envelope is a deliberate authoring act — the same discipline `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §81's safe-payload rule already applies to financial events, extended platform-wide.
- **Publication uses a transactional outbox or equivalent reliable-publication boundary**, so an event is durably recorded before any external delivery attempt (`docs/implementation/01_Implementation_Sprint_Plan.md`, Sprint 0.4).
- **Delivery is at-least-once; consumers are expected to be idempotent** (§19). **No claim of global ordering** is made; **per-subject ordering is guaranteed only where explicitly stated** for a specific event family.
- **Deliveries are signed, with timestamped signatures**, giving the receiver both authenticity and replay resistance.
- **Webhook secret rotation supports bounded overlap**, mirroring §16's credential-rotation discipline.
- **Bounded retries with backoff**; sustained failure produces **dead-letter or suspended-delivery** state, with **delivery health and failure visibility** surfaced to the Firm and the integration owner.
- **Manual replay is supported and audited** — a human-initiated redelivery is a recorded act, never a silent retry outside the normal policy.
- **Permission and subscription checks happen before each delivery**, not only at subscription time — a subscription whose underlying permission has since narrowed does not receive data it is no longer authorized for.
- **No continued delivery after installation revocation.** Revocation (§14) takes effect for webhook delivery immediately.
- **No secrets or full confidential payloads in delivery logs** — logs record identifiers, status, and timing, never the envelope's sensitive content or the signing secret.
- **Webhook event versioning is independent and explicit** from both the API's REST versioning and any internal event's shape — an event type's version changes on its own schedule, communicated the same way as any other breaking change (§18).

## 27. Webhook subscription model

- A subscription names the event types it wants, the callback endpoint (validated against the application's registered callbacks, §13), and its own active/paused/unsubscribed state.
- Subscribing to an event type never grants visibility beyond what the installation's scopes and the resource owner's authorization already permit — a subscription is a delivery preference, not an access grant.

## 28. Delivery health and dead-lettering

- Every delivery attempt is recorded with outcome and timing, giving the Firm and the integration owner visibility into success rate, latency, and failure patterns.
- Exhausted retries move a delivery into a dead-letter or suspended state that remains visible and queryable — never silently dropped.
- Sustained failure for one subscription never blocks delivery to other subscriptions of the same event, or delivery of other event types to the same subscription, unless the failure is specific to the shared underlying cause (queue isolation, §37).

## 29. Inbound webhooks and command intake

The safe inbound boundary, applied uniformly to every provider or partner callback:

```text
Receive → Preserve raw body → Authenticate (signature) → Verify timestamp/replay window
        → Enforce idempotency → Validate schema/content-type → Enforce size limits
        → Rate-limit source → Reject unregistered/inactive connections
        → Translate to owning module's command → Acknowledge per provider contract
```

- **Preserve the raw body** before any parsing, since signature verification depends on the exact bytes received.
- **Authenticate before accepting business meaning** — an unverified callback is rejected and recorded as a security event, never processed optimistically.
- **Verify timestamp and replay window**, rejecting stale or replayed deliveries.
- **Enforce idempotency** (§19) so redelivery never produces a duplicate business effect.
- **Validate schema and content type**, and **apply strict size limits**, before any further processing.
- **Rate-limit abusive sources** and **reject unregistered or inactive connections** outright.
- **Prevent SSRF and internal-network callback abuse** — registered callback URLs and any outbound verification calls are validated against an allow-list and never permitted to target internal network ranges, with DNS-rebinding considerations addressed at validation time, not assumed away.
- **Quarantine malformed or suspicious inputs** rather than discarding them silently, preserving them for investigation.
- **Record provenance** — source, timestamp, signature outcome, and correlation identifiers — on every envelope, verified or not.
- **Translate valid input into an owning module's application command.** Integrations never writes directly to a domain table.
- **Never call an external provider inside a domain database transaction.**
- **Acknowledge only according to the provider's own contract** — some providers require synchronous acknowledgment, others tolerate asynchronous processing; the acknowledgment discipline follows the provider, not a platform-wide assumption.
- **Provider delivery status is kept separate from OneLegalPro business state** — a provider's "delivered" or "processed" signal is never itself the platform's authoritative record of what happened; the owning domain's own command execution is (mirroring `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §30.3's payment-status/settlement-status distinction, generalized).

## 30. Provider-specific semantics stay with the owning domain

- **Payment-provider semantics remain with Billing.** Integrations supplies the shared ingress, verification, and replay pattern; Billing's own adapters interpret what a payment-provider callback means.
- **Messaging-provider semantics remain with Communications**, behind its own `ChannelAdapter`.
- **Document/file semantics remain with Documents.**
- **Identity-provider semantics remain with IdentityAccess.**
- **Digital Presence retains embedded-surface presentation rules.**
- Integrations never absorbs a domain's provider-specific business logic; it supplies the reusable safety pattern every provider integration needs, once, instead of once per domain.

## 31. Connector lifecycle

`Connector` supports **pull, push, and bidirectional** synchronization with an external system, Firm-scoped throughout:

```text
Configured → Connecting → Connected ⇄ Degraded → Disconnected (terminal, or reconnectable)
```

- **Firm-scoped connection lifecycle** — a connector belongs to exactly one Firm; no shared connector state crosses Firms.
- **Data minimization** — a connector synchronizes only the fields its configured purpose requires, not an unbounded mirror of the remote system.

## 32. Synchronization

- **Initial synchronization** establishes the starting state; **incremental synchronization** applies changes since the last checkpoint, using a durable `SyncCursor`.
- **Stable remote identifiers** and **mapping provenance** are recorded for every synchronized record, so a re-sync or a mapping dispute can be traced to its source.
- **Bounded batch sizes**, **retry and resumption** from the last good cursor, and **duplicate detection** (via stable remote identifiers) are required, not optional refinements.
- **The platform must not claim that every provider supports exactly-once delivery.** Synchronization logic is built to tolerate at-least-once and out-of-order remote signals.

## 33. Conflict and reconciliation

- **Conflict detection** compares incoming remote state against the platform's current state before applying a change.
- **No silent last-write-wins for legally or financially significant records.** A conflicting update to a document, invoice, ledger entry, or client-money record is surfaced for **human-visible reconciliation**, never silently overwritten by whichever side arrived last.
- **Partial failure** during a synchronization batch is reported per-record, not as an all-or-nothing outcome that obscures which records actually applied.

## 34. Revocation, deletion, and retention interaction

- **Revocation and disconnection** stop future synchronization immediately without deleting already-synchronized records or their provenance.
- **Deletion and retention conflicts** — where a connector's remote deletion signal would remove a record subject to legal hold or statutory retention, the platform's own retention and hold rules win; the record is not deleted.
- **Legal-hold preservation** — a connector never becomes a path around Documents' or Billing's legal-hold discipline.
- **No connector bypass of approval, Ethical Wall, financial, or document controls.** A synchronized change still passes through the owning domain's own authorization and invariants exactly as a manually entered one would.

## 35. Import, export, and bulk operations

- **Asynchronous jobs** handle large imports and exports (§22); nothing here implies a synchronous bulk endpoint for significant volume.
- **Immutable job request and actor provenance** — who requested the job, when, and under what authorization is recorded and never altered after the fact.
- **Firm and authorization snapshot plus revalidation before sensitive delivery** — authorization is checked at request time and **re-checked immediately before delivering results**, since a job may run long enough for the requester's access to have changed.
- **Validation reports and partial-success reporting** — a bulk operation reports what succeeded, what failed, and why, per record, rather than an opaque pass/fail.
- **Safe retry and idempotency** apply to bulk operations exactly as to any other command (§19).
- **Cancellation is supported where legally and operationally safe** — a job already committing financially or legally significant changes is not cancelled mid-flight in a way that leaves an inconsistent partial state.
- **Data classification** (§7 of `docs/architecture/04_Security_Architecture.md`) governs handling of the job's content throughout.
- **Secure, expiring download delivery through Documents' approved storage boundary** (§23) — never a raw, unrestricted database dump.
- **No bulk export bypass of Ethical Walls or co-client restrictions.** **No export merely because a user can view one item** — export authorization is evaluated per record included, not inferred from the ability to request the job.
- **Audit of request, generation, and retrieval** — three distinct recorded events, not one.
- **No confidential payloads in queue metadata or operational logs** for a job — the job's identity and status are logged; its content is not.

## 36. Developer documentation

- **OpenAPI-derived reference documentation** — the contract is authoritative; documentation is generated from it, not written independently and left to drift.
- **Stable examples using synthetic data** — never real Firm or client data, ever.
- Registration and installation guidance; scope descriptions; authentication guidance; webhook verification examples; idempotency and retry guidance; an error catalogue; a changelog; deprecation and migration notices.

## 37. Sandbox

- **A sandbox must never contain copied production Firm data by default.** Sandbox and test-Firm data is synthetic or explicitly, separately authorized.
- Sandbox credentials and installations are distinct from production ones and cannot be silently promoted to production access.
- Credential rotation and revocation controls, and usage/delivery-health visibility, are available in sandbox exactly as in production, so integration developers can validate real operational behavior safely.

## 38. Generated SDKs

- SDKs **may be generated from the authoritative contract in the future**, but **a generated SDK never replaces the contract** — it is a derived convenience, and any discrepancy is resolved in favor of the OpenAPI contract.
- No SDK language, generator, or distribution mechanism is selected here.

## 39. Security

Extending `docs/architecture/04_Security_Architecture.md` to the API edge specifically: input validation and output encoding on every field; injection prevention (parameterized access, never string-built queries from external input); SSRF prevention on every outbound call the platform itself makes (callback verification, connector calls); callback and redirect allow-listing with exact matching (§13); DNS-rebinding considerations addressed at validation time; request size and complexity limits; rate limits and quotas per caller and per Firm; abuse detection; secret references and rotation (§16); mandatory encryption in transit; sensitive-field redaction in logs and error responses; audit trails (§40); metrics that never carry confidential payloads; correlation identifiers on every request and response; timeouts, circuit breakers, and bulkheads isolating one integration's failure from another's; a defined retry policy; backpressure under load; queue isolation so one Firm's or one integration's backlog cannot starve another's; dead-letter handling (§28); dependency-health checks; safe degradation rather than cascading failure; an incident-response posture; prompt revocation propagation; backup/export leakage prevention (§35); supply-chain risk awareness for any third-party library eventually used; and sandbox/production isolation (§37).

**No API, integration, connector, webhook, background worker, or AI path may be less authorized than an equivalent interactive path.** Every one of them passes through the same composition in §10.

## 40. Privacy

- External requests are subject to the same data-minimization and classification discipline as internal ones (`docs/architecture/04_Security_Architecture.md` §7).
- Personal data reaching the platform through an inbound integration is handled under the same PDPA/GDPR-consistent posture the platform already applies internally, per Firm and jurisdiction configuration — no new privacy regime is invented here.
- **No compliance or certification claim is made.** Jurisdiction-specific obligations require authoritative legal review and separately approved policy, exactly as `docs/architecture/04_Security_Architecture.md` §9 already states platform-wide.

## 41. Audit

Every consequential Integrations action is an append-only security/audit event, never editable by the actor being audited: application registration and lifecycle changes; installation request, approval, suspension, and revocation; scope grant and revocation; credential-reference association and rotation; webhook subscription changes; every delivery attempt outcome (success/failure/dead-letter); manual replay; inbound-webhook verification outcomes (including rejections); connector connect/disconnect/reconciliation events; import/export job request, generation, and retrieval; and every denied authorization at the API edge for a high-risk action. Records carry safe actor (human, service principal, or system), Firm, installation, event, result, timestamp, and correlation information — **never credentials, webhook secrets, full payloads, or privileged domain content.**

## 42. Observability

Metrics and traces cover latency, error rate, quota consumption, delivery health, and dependency health **without ever carrying confidential payloads** — a metric records that a request happened and how it went, not what it contained. Correlation identifiers thread every metric, log line, and audit event for one request or delivery back together.

## 43. Failure handling

- **New authentication, authorization, or Ethical Wall decisions required for a request fail closed** when the required authority is unavailable — an external request gets exactly the same fail-closed treatment `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §47 and `docs/architecture/04_Security_Architecture.md` §8 already require internally.
- **API and webhook failures never rewrite domain truth.** A failed delivery, a failed rendering dependency, or a provider outage is visible and retried; it never rolls back or silently alters an already-committed business record — extending `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §83's discipline platform-wide.
- **Provider outage** — outbound calls queue and retry (bounded); inbound processing tolerates a recovering provider's redelivery without assuming in-order arrival.
- **Duplicate/out-of-order webhooks** are resolved by idempotency and per-subject ordering guarantees only where stated (§19, §26).
- **Dependency outage inside Integrations' own path** (IdentityAccess, Practice Management, a domain module) never silently substitutes a cached or assumed-good decision — it fails closed for the specific decision that dependency was needed for, exactly as the dependent domain's own architecture already requires.

## 44. Operational resilience

Timeouts, circuit breakers, and bulkheads keep one failing external dependency from cascading into the rest of the platform; backpressure protects the platform under load rather than accepting unbounded work; queue isolation keeps one Firm's or one integration's backlog from starving another's; dead-letter queues make stuck work visible rather than silently lost; and safe degradation (serving what is available, clearly marking what is not) is preferred over an all-or-nothing outage.

## 45. Domain integration boundaries

- **Legal Intelligence** owns official legal sources, citations, provenance, and disclaimers; any API response surfacing translated legal content still carries the mandatory disclaimer (Constitution Articles 2–4), unchanged by being delivered through an external contract.
- **Branding** owns brand profiles, tokens, assets, and branded rendering decisions; an API response may reflect Firm branding metadata but Integrations never resolves or overrides it.
- **Communications** owns messages, threads, channels, and provider-specific communication semantics.
- **Digital Presence** owns website, portal, and embedded-component presentation.
- **Practice Management** owns Client, Matter, `MatterClient`, `MatterTeam`, conflicts, and Ethical Walls.
- **Documents** owns document records, versions, content, secure delivery, and governed knowledge.
- **Billing** owns invoices, payments, trust/client-money records, journals, and financial truth.
- **IdentityAccess** owns principals, service principals, credentials, sessions, permissions, and security events.
- **Platform Foundation** owns `FirmContext` and technical tenancy primitives.
- **Integrations** owns stable external contracts and integration lifecycle records — never domain truth.
- **Workflow orchestration** remains reserved for a future ARCH-010.
- **AI** never becomes an API authorization authority.

## 46. AI and system-actor rules

This document **introduces no new AI capability**. AI holds no authority over API scopes, installation approval, webhook subscriptions, connector configuration, or export authorization. Where an AI-assisted process initiates an external call or consumes an API result, the same human/system/integration/AI actor provenance `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §46 already requires applies unchanged: the initiating human, the AI/system actor, the authorization relied on, and any required human approval are all recorded. `docs/architecture/05_AI_Architecture.md` is unmodified by this story.

## 47. Conceptual Laravel module placement

Conceptual only. **No directory or source file is created by this story.**

```text
app/Modules/Integrations/
├── Application/
│   ├── Commands/        (RegisterIntegrationApplication, RequestInstallation,
│   │                     ApproveInstallation, RevokeInstallation, CreateWebhookSubscription,
│   │                     RecordInboundWebhook, RequestImportJob, RequestExportJob, ...)
│   ├── Queries/         (GetApiContract, ListInstallations, GetWebhookDeliveryStatus,
│   │                     GetSyncCursorStatus, GetImportExportJobStatus, ...)
│   ├── Handlers/
│   └── DTOs/            (external representations — distinct from internal entities)
├── Domain/
│   ├── Aggregates/      (ApiContract, IntegrationApplication, IntegrationInstallation,
│   │                     WebhookSubscription, WebhookDelivery, InboundWebhookEnvelope,
│   │                     IdempotencyRecord, Connector, ImportExportJob, DeveloperSandbox)
│   ├── Entities/        (ApiContractVersion, ScopeGrant, ServiceCredentialReference,
│   │                     DeliveryAttempt, SyncCursor, ReconciliationRecord)
│   ├── ValueObjects/    (ApiVersion, ScopeName, WebhookSecret, IdempotencyKey,
│   │                     CorrelationId, SyncDirection, RemoteIdentifier, JobStatus, ...)
│   ├── Events/
│   ├── Policies/        (authorization composition entry point, rate-limit/quota policy)
│   └── Repositories/    (contracts only)
├── Infrastructure/      (outbound webhook signer/delivery adapter, inbound signature
│                         verification adapters per provider, connector adapters,
│                         outbox integration, Eloquent adapters — all provider-neutral)
├── Http/                (versioned API routes, webhook receive endpoints)
├── Contracts/           (published cross-module contracts)
├── Config/              (rate-limit defaults, retry/backoff policy, sandbox configuration)
├── ModuleServiceProvider.php
└── README.md

Consumed, not duplicated: app/Foundation primitives (PF-040–PF-049, PF-045 Money,
PF-080 FirmContext); IdentityAccess principals/service-principals/credentials/permissions;
Practice Management's CheckEthicalWallAccess; every domain module's published
commands/queries/events for translation into external contracts.
```

Rules, per `docs/domain/06_Laravel_Module_Blueprint.md` unchanged:

- **Domain modules do not import Integrations' Eloquent models**; Integrations does not import theirs. Cross-module use is published contracts only.
- **Integrations never writes directly to another module's tables**, and never recreates its business rules.
- **Domain modules retain final resource authorization responsibility** — Integrations composes and forwards; it never decides alone.
- **Internal modules do not call OneLegalPro's own public HTTP API to communicate with each other.** Internal cross-module communication continues through published contracts, commands, queries, and domain/integration events, exactly as it already does; the public API is for external callers.
- Dependency direction: `Http → Application → Domain`; Infrastructure depends on Application/Domain contracts; Domain never depends on Laravel, Eloquent, HTTP, or provider SDKs.

## 48. Invariants

- Every protected external request is Firm-scoped, with `FirmContext` derived only from authenticated identity and authorized installation.
- An API scope narrows access; it never widens it, and never overrides an Ethical Wall.
- Authentication and service-principal identity are IdentityAccess's; Integrations defines neither.
- An integration application definition grants no Firm access by existing; installation is an explicit, authorized, auditable, Firm-scoped act.
- A shared application's installations across Firms are fully isolated in state, credentials, cursors, quotas, subscriptions, and audit.
- Revoking an installation promptly stops API access, webhook delivery, and connector activity without deleting audit history.
- Internal domain events are never exposed directly as the permanent public contract; only the versioned `IntegrationEventEnvelope` is.
- Delivery is at-least-once; no exactly-once or global-ordering claim is made; per-subject ordering holds only where explicitly stated.
- Inbound webhooks are authenticated, replay-resistant, and idempotent before any business meaning is attached, and are translated into an owning module's command — never a direct table write.
- External calls never occur inside a domain database transaction.
- Bulk import/export never bypasses Ethical Walls, co-client restrictions, or any domain's access rules; export authorization is evaluated per record, not inferred from single-item viewability.
- Long-lived, non-rotatable secrets are prohibited; any compatibility API key is Firm-bound, purpose-bound, non-recoverable, rotatable, revocable, scope-limited, and audited.
- A denied external caller receives no existence signal.
- Failure handling fails closed where current authority is required, and never rewrites already-committed domain truth.
- Workflow orchestration and Marketplace publication remain reserved and unarchitected here.
- AI holds no API or integration authorization authority.
- Architecture approval schedules no implementation.

## 49. Alternatives considered

Recorded in full in `docs/adr/ADR-010-API-Integration-Platform.md`, Alternatives considered. In summary, the following were considered and **rejected**: a generic integration layer that also owns domain business rules; letting each domain build its own public API independently; exposing internal domain events directly as the public webhook contract; letting internal modules call the platform's own public API to talk to each other; treating a granted scope as sufficient authorization; one global API credential shared across every installing Firm; claiming exactly-once delivery or global event ordering; making external calls inside a domain database transaction; allowing webhook delivery to continue after installation revocation; moving payment- or messaging-provider verification ownership into Integrations; moving service-principal ownership out of IdentityAccess; architecting a public marketplace or partner certification program in this story; and selecting an API gateway, identity vendor, or message broker before approving the conceptual boundary.

## 50. Consequences and trade-offs

- A consistent external contract layer for every domain, at the cost of one more translation step between an external request and domain execution.
- Firm-scoped installation isolation scales operational state with the number of Firms installing a shared application — the correct trade for eliminating cross-Firm leakage.
- At-least-once delivery without stronger guarantees means every external consumer must handle duplicates and reordering, stated honestly rather than promised away.
- Contract-driven developer experience makes the OpenAPI contract a first-class deliverable with its own review discipline.
- EPIC-010 becomes a dependency of Digital Presence's enterprise integration, IdentityAccess's service-principal API-authentication foundation, Communications' external-channel adapters, Billing's payment-provider webhook boundaries, and Documents' secure file integrations.
- Deferred vendor selection means no implementation can begin on a specific gateway or provider until a separately approved, implementation-focused decision follows this one.

## 51. Explicit non-goals

This architecture does **not**: implement any API, webhook, connector, or integration; create schemas, migrations, routes, controllers, jobs, or packages; select or configure an API gateway, identity vendor, message broker, cloud service, or Laravel package; claim any API is implemented or deployed; claim ISO, SOC, PDPA, GDPR, or any other certification or compliance; design Workflow orchestration (reserved for ARCH-010); design Marketplace publication, monetization, or partner certification (reserved against `docs/architecture/06_Marketplace.md`, which remains an empty placeholder); grant AI any authorization authority; move service-principal ownership out of IdentityAccess; put payment-provider or messaging-provider business semantics into Integrations; promise exactly-once delivery or global event ordering; or schedule any implementation work.

## 52. Future expansion

Each of the following is **describable as future work, not implemented, not architected in detail, and not scheduled**: full public marketplace and partner certification (reserved for a future Marketplace architecture); Workflow-driven multi-step orchestration across domains consuming these contracts (reserved for ARCH-010); GraphQL or other query-language surfaces layered atop the same authorization composition; advanced API analytics and per-integration profitability views; a dedicated developer portal product; partner-tier rate-limit and SLA differentiation; and cross-Firm integration templates distributed through a future Marketplace. **No cryptocurrency, no autonomous AI API authority, and no cross-Firm data sharing is contemplated by any of them.**

## 53. Proposed implementation stages

**Proposed only.** None of these stages is an approved, scheduled, or numbered story. Each requires its own entry in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`, with a Definition of Ready and Definition of Done, before implementation begins. This is the same sequence recorded for EPIC-010 in `docs/architecture/08_Roadmap.md`.

1. **API contract and standards foundation** — `ApiContract`/`ApiContractVersion`, OpenAPI authoring discipline, the platform's shared DTO and error conventions from `docs/architecture/07_API_Standards.md`.
2. **Integration application registration and Firm-scoped installation** — `IntegrationApplication`, `IntegrationInstallation`, consent and Firm-admin approval.
3. **IdentityAccess service-principal, delegated-access, and scope integration** — consuming IdentityAccess's service-principal and delegation contracts; the API scope model composing with domain authorization.
4. **Domain API adapters and stable external representations** — the first stable DTOs mapping domain commands/queries to external contracts, per participating domain.
5. **Idempotency, concurrency, pagination, errors, and async-operation foundation** — `IdempotencyRecord`, optimistic concurrency preconditions, cursor pagination, the error catalogue, async job status resources.
6. **Outbound integration events, webhook subscriptions, and delivery** — `IntegrationEventEnvelope`, `WebhookSubscription`, `WebhookDelivery`, signed and retried delivery with dead-lettering.
7. **Inbound webhook verification, replay protection, and command intake** — the shared ingress pipeline (§29), translating into owning-module commands.
8. **Connector configuration, synchronization, and reconciliation** — `Connector`, `SyncCursor`, conflict detection, human-visible reconciliation.
9. **Permission-aware import, export, and secure file delivery** — `ImportExportJob`, Documents-delegated file handling, revalidation before delivery.
10. **Developer documentation, sandbox, and contract-derived SDK foundations** — OpenAPI-derived docs, `DeveloperSandbox` isolation, generated-SDK tooling.
11. **Rate limiting, observability, deprecation, and operational hardening** — quotas, metrics/tracing without confidential payloads, deprecation/sunset tooling, circuit breakers and bulkheads.

## Phased implementation guidance

See `docs/architecture/08_Roadmap.md`, the proposed EPIC-010 — API & Integration Platform epic, for the staged delivery order restated at epic level. That staging is **proposed only**; formal scheduling requires entries in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` and separate story-level approval before implementation begins. No `PF-*` story numbering or approved implementation sequence is changed by this document: **PF-010 remains the current repository implementation story and PF-011 remains next.**

The story ID is **ARCH-009** while this architecture document is numbered **17** (`ARCH-017`), continuing the established distinction between story numbering and architecture-document numbering that ARCH-006/ARCH-014, ARCH-007/ARCH-015, and ARCH-008/ARCH-016 already set.
