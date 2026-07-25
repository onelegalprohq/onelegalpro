# OneLegalPro API Standards

**Status:** Approved (normative, platform-wide standard). Populated by **ARCH-009 — API & Integration Platform**; see `docs/adr/ADR-010-API-Integration-Platform.md` and `docs/architecture/17_API_Integration_Platform_Architecture.md`.

**No implementation currently exists.** This document establishes the standard every future public API implementation must follow. It authorizes no routes, controllers, migrations, packages, or vendor selection, and makes no claim that an API is deployed.

## 1. Scope and authority

This standard governs every **public** (external-facing) API surface OneLegalPro exposes, across every bounded context. It is normative: an implementation story that deviates from it must either conform or obtain an explicit, recorded architecture exception before merging, per the approval gates in `AGENTS.md`.

It does **not** govern internal cross-module communication. Internal modules communicate through published contracts, commands, queries, and domain/integration events, per `docs/domain/06_Laravel_Module_Blueprint.md` — **never by calling OneLegalPro's own public HTTP API**. Routing internal calls through the public API adds latency, a network dependency, and a confused-deputy risk for no benefit, and is a defect if found in review.

Full conceptual context — bounded-context ownership, the integration lifecycle, webhook and connector architecture — is in `docs/architecture/17_API_Integration_Platform_Architecture.md` (ARCH-017). This document is the concise, enforceable standard; that document is the complete architecture.

## 2. Public versus internal contract distinction

| | Public contract | Internal contract |
|---|---|---|
| Consumer | External caller (Firm-authorized third party, Firm-built integration, Client Portal front end where applicable) | Another bounded context inside the platform |
| Shape | Stable, versioned external DTO, OpenAPI-described | Explicit published commands, queries, DTOs, and events — never a shared Eloquent record, private repository, or mutable aggregate instance |
| Change discipline | Additive/breaking distinction, deprecation window, sunset policy (§4) | Governed by the owning module's own architecture and tests |
| Authentication | IdentityAccess-issued, per `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` | No repeated credential authentication for an already-authenticated request; verified actor/service identity, `FirmContext`, authorization provenance, and correlation context are propagated instead (see below) |

**No Eloquent model, internal domain event, or internal identifier is ever exposed as a public contract.** A public DTO is authored deliberately and evolves on its own schedule, independent of internal refactoring.

**Same-process execution is not permission to bypass domain authorization.** Internal modules do not repeat full credential authentication for a request that has already been authenticated at the edge, but every call still carries the verified actor or service identity, `FirmContext`, authorization provenance, and correlation context forward — the receiving module then performs its own command/query and resource authorization using that carried context. Being in the same process, and not re-authenticating, is never license to skip that check. Published internal contracts are always **explicit commands, queries, DTOs, and events**; they never share Eloquent records, private repositories, or mutable aggregate instances across a module boundary, per `docs/domain/06_Laravel_Module_Blueprint.md`.

## 3. Naming and resource conventions

- **Resource-oriented routes** — nouns for resources (`/matters`, `/invoices`), not verbs.
- **Explicit action endpoints where a domain action does not fit CRUD** — a state transition with meaning beyond "update this field" (for example, issuing an invoice, revoking an installation) is its own named action endpoint, not an overloaded `PATCH`.
- Resource and field names are stable, published, and never renamed without a breaking-change cycle (§4).
- Identifiers in URLs and payloads are **opaque** — never a raw database primary key exposed by accident, and UUIDv7 values are treated as opaque tokens externally even though they are internally time-ordered.

## 4. Versioning and compatibility

- **Major-version namespaces** (for example `/v1/...`) are the unit of breaking change. A new major version coexists with the prior one; it never silently replaces it underneath existing callers.
- **Additive changes** — a new optional field, a new endpoint — do **not** require a version bump.
- **Enums are closed by default.** A field's contract must **explicitly declare** whether it is a closed enum (a fixed, exhaustive set of values) or an **open/extensible enum** (a set that may grow, which consumers are contractually required to preserve or tolerate unknown values for). Open-versus-closed behavior is a property of the published contract, never an assumption about consumer quality.
  - Adding a value to an **explicitly declared open enum** is additive and does **not** require a version bump.
  - Adding a value to a **closed enum** (or to any enum whose openness is not explicitly declared) **is a breaking change.**
  - **Removing or renaming any enum value is breaking**, regardless of open/closed status.
  - **Changing an enum value's meaning is breaking**, even if the value's name is unchanged.
- **Breaking changes** — removing or renaming a field, changing a field's type or meaning, tightening validation, removing or renaming an enum value, changing an enum value's meaning, or adding a value to a closed enum — **require a new major version.**
- **Deprecation notices** are published before a version is sunset, stating the deprecated version, the replacement, and the **supported migration window** during which both remain live.
- **Sunset policy**: a version is retired only after its published migration window elapses, with the sunset date communicated in developer documentation and, where practical, in response metadata ahead of time. Sunset is a scheduled, communicated event, never a silent removal.
- **Public API breaking changes require explicit human approval** before merge, per the existing `AGENTS.md` approval gate — this is not optional for any story touching a public contract.

## 5. Request/response representation

- **JSON** is the standard request and response media type; **content negotiation** is supported where a resource genuinely has more than one meaningful representation (for example, a rendered document requested as metadata versus binary), delegated to Documents for file content (§13).
- **Opaque identifiers**, **UUIDv7** internally, never exposed as a basis for external enumeration or ordering assumptions.
- **Timestamps are ISO-8601.**
- **Transport is UTC**, with explicit legal/business timezone context carried as a separate field wherever the timezone itself is legally or operationally significant (a court deadline, a Matter's office timezone) — UTC transport never silently discards that context.
- **Money is the exact-decimal Foundation `Money` contract (PF-045) plus an ISO 4217 currency code** — never a bare number, and never floating point, consistent with Constitution Article 23.
- Every response carries a **correlation/request identifier** for tracing and support.

## 6. Pagination, filtering, sorting

- **Cursor-based pagination is the required mechanism** for collection endpoints. Offset-based paging is not the standard, since it drifts under concurrent writes.
- **Filtering, sorting, and sparse-field selection are explicit and allow-listed** by the owning domain, field by field. **Arbitrary sort/filter expressions are never accepted** — no raw expression language, no unrestricted query passthrough.
- **Page sizes are bounded** by a platform-wide maximum a caller cannot exceed regardless of a requested value.
- A collection query is subject to the **same authorization composition as a single-resource read** (§9) — filtering can never become an enumeration path to records a caller could not otherwise see.

## 7. Errors

- **Validation errors are consistent and structured**, identifying the field and the reason, never a generic message that varies unpredictably by endpoint.
- **Problem-style error responses** — a stable error shape (type, title, status, detail, and a correlation identifier) used uniformly across every endpoint.
- **Stack traces, internal exception messages, and internal authorization reasoning that could aid an attacker are never included** in an error response.
- **Concurrency conflicts** (§10) are a distinct, structured error type, never indistinguishable from a generic failure or an authorization denial.

## 8. Authentication and authorization

- **All external requests authenticate through IdentityAccess.** No public endpoint defines its own authentication mechanism, password handling, or session model.
- **Human delegated access** and **service-principal access** are the two supported caller shapes (`docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §9); a service principal never authenticates through a human login path.
- **Authorization is a composition**, never a single scope check: authenticated principal → active Firm membership/installation → Firm-bound session/service context → granted scope → owning domain's action/resource semantics → Practice Management's `CheckEthicalWallAccess` where Matter-linked → narrower domain restrictions → step-up/segregation-of-duties policy → explicit deny rules (full detail: `docs/architecture/17_API_Integration_Platform_Architecture.md` §10).
- **An API scope never widens domain authorization.** It narrows what its holder could otherwise reach and nothing more. **No API key, OAuth-style grant, or service principal overrides an Ethical Wall.**
- **Deny is total.** A denied caller receives no protected content, metadata, count, search result, aggregate, or existence confirmation — the platform never signals that a restricted resource exists.
- **Authorization happens before retrieval, search, count, export, and AI context construction** — never after.
- **Rate limiting and quotas are operational safeguards, never a substitute for authorization.** A request within quota that fails authorization is still denied.

## 9. Firm isolation

- **No email, hostname, header, or request-body Firm identifier proves membership.** `FirmContext` for an external request is derived only from authenticated identity and an authorized installation (Constitution Article 27, applied without exception at the API edge).
- **No caller-selected tenant switching** — a request cannot name a Firm other than the one its authenticated context resolves to.
- **No global integration identity with silent access to every Firm.** Every integration installation is Firm-scoped; a shared application's installations across Firms are fully isolated in credentials, scopes, cursors, quotas, and audit records.

## 10. Idempotency and concurrency

**Idempotency prevents duplicate side effects within its defined boundary; it is not a general exactly-once execution or delivery guarantee.** Distributed execution and delivery remain at-least-once (§12); idempotency is what makes a retry of the same request safe within that reality.

- **Every retryable command accepts an idempotency key.** An idempotency record is scoped by, at minimum: the Firm; the integration installation; the API contract/version and operation; the target resource, where applicable; and the idempotency key itself.
- **A canonical request fingerprint** covers the relevant method/action, target, and normalized request content — **never secrets or unnecessary confidential content.**
- **Same scoped key plus the same fingerprint** returns the previously recorded result, or a reference to it, **without repeating the side effect.**
- **Same scoped key plus a different fingerprint is rejected as an idempotency-conflict error.** It must **never** return the first request's result as if the second, different request had been accepted.
- **The retention window is bounded and documented.** After it elapses, the caller must use a new key; the platform makes no guarantee for a key reused past its window.
- **A replayed request still authenticates and passes current Firm, installation, scope, and domain authorization checks** — idempotency short-circuits the side effect, never the authorization composition (§8).
- **A previously recorded response must not disclose protected content after membership, permission, Ethical Wall, installation, or resource-access revocation.** Replaying an idempotency key re-evaluates current authorization before returning anything; a stale positive result is never served to a now-unauthorized caller.
- **Store a safe status or result reference where possible**, rather than indefinitely retaining a full, potentially confidential response body.
- **Optimistic concurrency using version or ETag-style preconditions** governs updates — a caller states the version it last read; a conflicting concurrent update is rejected, never silently overwritten.

## 11. Async operations

- Long-running work (bulk export/import, large reports) is an **async operation resource**: a request creates a job and returns a status reference; the caller polls or is notified.
- The status resource exposes `Pending → Running → Succeeded | Failed | Cancelled`, with progress where meaningful. Partial or speculative results are never presented as final.
- An async job inherits the same authorization composition as any other resource, re-checked immediately before sensitive delivery (§14).

## 12. Webhook rules

### Stable event versus delivery attempt

- **A stable integration event and a delivery attempt are separate concepts, never conflated.** The **stable integration event** carries: a stable event ID that persists unchanged across every retry; event type; contract version; occurrence time; subject reference; correlation and causation references; installation context where safe and necessary; and a minimal, authorized payload. It **never** carries delivery-attempt or retry-history metadata.
- The **webhook delivery attempt** is a separate record: its own delivery ID, a reference to the stable event ID and the subscription, an attempt number, a delivery timestamp, outcome/status, safe response metadata, and signature/timestamp transport metadata.
- **A retry retains the same stable event ID; each attempt receives its own delivery ID.** Consumers deduplicate the business event using the **stable event ID**, never the delivery/attempt ID.
- **Internal retry history and operational diagnostics never enter the stable event payload.** Full confidential payloads and secrets remain prohibited from delivery logs regardless.
- **Internal domain events are never exposed directly as the permanent public contract.** The versioned stable integration event described above is the only thing delivered externally, and confidential fields are never included merely because they exist internally.

### Delivery discipline

- **Delivery is at-least-once; consumers must be idempotent.** **No claim of exactly-once delivery or global ordering** is ever made; per-subject ordering is guaranteed only where explicitly documented for a specific event type.
- **Deliveries are signed with timestamped signatures**, are **replay-resistant**, and support **secret rotation with bounded overlap**.
- **Bounded retries with backoff**; sustained failure produces visible **dead-letter/suspended-delivery** state; **manual replay is supported and audited**.
- **Permission and subscription checks happen before every delivery**, not only at subscription time.
- **No continued delivery after installation revocation** — revocation takes effect for webhook delivery immediately.
- **No secrets or full confidential payloads in delivery logs.**
- **Webhook event versioning is independent and explicit** from REST API versioning.

### Provider-specific verification ownership

- **Integrations owns the shared ingress envelope and pipeline**: bounded receipt, raw-body preservation, generic replay/idempotency coordination, registration status, rate limiting, quarantine policy, provenance recording, and dispatch to the owning module.
- **The owning domain's provider adapter owns provider-specific verification** — the signature algorithm, required headers, canonicalization rules, provider credential/secret lookup, and provider-specific acknowledgment semantics. Payment-provider callbacks are verified by Billing's own adapter; provider-specific communications callbacks by Communications' own channel adapter; identity-provider callbacks by IdentityAccess. **Integrations does not implement Stripe-, Microsoft-, Google-, LINE-, WhatsApp-, identity-provider-, or other provider-specific signature logic.**
- **Integrations invokes the owning domain's published verification contract** as one step of the shared pipeline; it never re-implements or approximates that verification itself. **A generic Integrations verification mechanism never weakens a provider's required verification procedure.**
- **No business meaning is attached until the owning adapter reports successful verification.**

### Inbound handling and quarantine

Inbound webhooks: **enforce a hard transport/body-size limit before an unbounded body is fully buffered**, and **reject oversized input before signature processing or quarantine storage**; preserve the exact raw body, only within that approved bound, for signature verification; authenticate before accepting business meaning (per the owning adapter's verification contract, above); verify timestamp and replay window; enforce idempotency (§10); validate schema/content-type; rate-limit abusive sources; reject unregistered/inactive connections; prevent SSRF and internal-network callback abuse (with DNS-rebinding considered); **quarantine malformed or suspicious input under a policy that is size-bounded, access-restricted, encrypted according to data classification, retention-limited, and auditable** — unverified or malformed content is never stored indefinitely, operational logs carry only safe metadata about a quarantined item and never its payload, and any investigation access to quarantined content still respects Firm and provider-connection isolation; record provenance; translate into an owning module's application command — **never write directly to a domain table**; acknowledge only per the provider's own contract; and keep provider delivery status separate from OneLegalPro's own business-truth state.
- **External calls are never made inside a domain database transaction.**

## 13. Import/export rules

- Bulk operations are asynchronous jobs (§11) with **immutable request and actor provenance**.
- **Firm and authorization are snapshotted at request time and re-validated immediately before sensitive delivery.**
- **Validation and partial-success reporting are per record**, not an opaque pass/fail.
- **File delivery goes through Documents' secure, expiring, authorization-gated delivery mechanism.** No unrestricted database dump, and no raw storage path is ever returned directly.
- **No bulk export bypasses Ethical Walls or co-client restrictions**, and **no export is granted merely because a user can view one item** — export authorization is evaluated per record included.
- **Every job's request, generation, and retrieval is independently audited.** No confidential payload appears in queue metadata or operational logs.

## 14. Security

Input validation and output encoding on every field; injection prevention; SSRF prevention on every outbound call the platform makes; exact callback/redirect allow-listing; DNS-rebinding considered at validation time; request size and complexity limits; rate limits and quotas per caller and per Firm; abuse detection; mandatory encryption in transit; sensitive-field redaction in logs and errors; correlation identifiers on every request; timeouts, circuit breakers, and bulkheads; a defined retry policy; backpressure under load; queue isolation; dead-letter handling; dependency-health checks; safe degradation over cascading failure; sandbox/production isolation with no default copy of production Firm data into a sandbox. **No API, integration, connector, webhook, background worker, or AI path may be less authorized than an equivalent interactive path.** Full detail: `docs/architecture/04_Security_Architecture.md` and `docs/architecture/17_API_Integration_Platform_Architecture.md` §39.

**Secret handling.** Integrations' own domain records and ordinary configuration store only **opaque secret references and safe metadata** — never a secret value. The secret material itself is held in an **approved, encrypted secret-management boundary with tightly restricted runtime access**, never in Integrations' tables, logs, events, analytics, audit payloads, or ordinary configuration. **Verification-only API keys are stored one-way/non-recoverably where possible.** Outbound webhook signing material and provider credentials are a different case: the narrowly authorized signing or provider adapter may need to retrieve them at runtime to sign a delivery or call a provider — **that authorized runtime retrievability never permits plaintext storage anywhere Integrations itself persists data.** Rotation, revocation, and bounded-overlap rules remain mandatory regardless of which secrets are involved. **No secret-management product or vendor is selected here.**

## 15. Observability

Metrics and traces cover latency, error rate, quota consumption, and delivery/dependency health **without ever carrying confidential payloads.** Correlation identifiers thread every log line, metric, and audit event for one request or delivery together. Audit is append-only and is never the same thing as an operational log (`docs/architecture/04_Security_Architecture.md` §1).

## 16. Documentation

Every public contract is **OpenAPI-described**, and reference documentation is generated from that contract, never authored independently and left to drift. Documentation includes stable examples using **synthetic data only**, registration and installation guidance, scope descriptions, authentication guidance, webhook verification examples, idempotency/retry guidance, an error catalogue, a changelog, and deprecation/migration notices. A generated SDK, where one exists in the future, is a derived convenience and **never replaces the authoritative contract.**

## 17. Deprecation

A deprecated version or field is announced with its replacement and a supported migration window (§4) before sunset. Deprecation notices appear in developer documentation and, where practical, in response metadata. A deprecated capability is never removed without having gone through this notice-and-window discipline first.

## 18. Review and approval requirements

- **Public API changes require explicit human approval before merge**, per `AGENTS.md`. This applies to any new endpoint, any field change, and any webhook contract change — not only breaking ones.
- A story introducing or changing a public contract must identify: the affected `ApiContract`/version, whether the change is additive or breaking, the domain module(s) it translates to, and the authorization composition it relies on.
- Security-relevant changes (authentication, scope model, webhook signing, rate limiting) require the same review discipline as any other security-relevant change in `AGENTS.md`'s approval-gate list.

## 19. Prohibited patterns

- Exposing an Eloquent model, an internal domain event, an internal database column name, a stack trace, a secret, or credential material through a public response.
- Exposing cross-Firm identifiers, confirming the existence of an inaccessible resource, or allowing an unrestricted database query or arbitrary sort/filter expression.
- Exposing a raw storage path or a raw payment credential.
- Letting a rate limit or quota stand in for an authorization decision.
- Letting an API scope, OAuth-style grant, or service principal widen domain authorization or override an Ethical Wall.
- Claiming exactly-once delivery or global event ordering.
- Making an external call inside a domain database transaction.
- Continuing webhook delivery after an installation has been revoked.
- Letting a bulk export or import bypass Ethical Walls, co-client restrictions, or any domain's own access rules.
- Letting internal modules call the platform's own public API to communicate with each other.
- Sharing an Eloquent record, a private repository, or a mutable aggregate instance across a module boundary, or treating same-process execution as permission to skip domain authorization.
- Treating a new value added to a closed (or undeclared) enum as additive, or omitting an explicit open/closed declaration from an enum's contract.
- Honoring an idempotency key reused with a different request fingerprint as if it were the same request, or returning a stale idempotent result to a caller whose authorization has since been revoked.
- Letting Integrations implement provider-specific signature verification (payment, messaging, identity, or otherwise) instead of invoking the owning domain's published verification contract.
- Conflating a stable integration event's identity with a delivery attempt's identity, or letting consumers deduplicate on the attempt ID.
- Storing a secret value in plaintext in an Integrations table, log, event, analytics payload, or ordinary configuration file.
- Buffering an inbound request body without a hard size bound before signature processing, or retaining unverified/quarantined content indefinitely.
- Selecting or endorsing a specific API gateway, identity vendor, or hosting product in architecture documentation.
- Claiming an API is implemented, deployed, or certified/compliant with any standard.

## Relationship to other documents

| Document | Relationship |
|---|---|
| `docs/architecture/01_OneLegalPro_Constitution.md` | Constitutional API/integration articles prevail over this document |
| `docs/architecture/17_API_Integration_Platform_Architecture.md` | The full IdentityAccess-consuming, `Integrations`-bounded-context architecture this standard serves |
| `docs/adr/ADR-010-API-Integration-Platform.md` | The decision record that populated this document |
| `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` | Owns authentication, service principals, and the authorization composition this standard consumes |
| `docs/architecture/04_Security_Architecture.md` | The platform-wide security baseline this standard extends to the API edge |
| `docs/architecture/06_Marketplace.md` | Empty placeholder; public marketplace/monetization is reserved, separately governed |
| `AGENTS.md` | Enforceable day-to-day rules and the public-API-change approval gate |
