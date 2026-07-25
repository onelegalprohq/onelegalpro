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
| Shape | Stable, versioned external DTO, OpenAPI-described | Domain commands, queries, events; may reflect internal model shape directly |
| Change discipline | Additive/breaking distinction, deprecation window, sunset policy (§4) | Governed by the owning module's own architecture and tests |
| Authentication | IdentityAccess-issued, per `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` | Trusted process boundary; no re-authentication between modules |

**No Eloquent model, internal domain event, or internal identifier is ever exposed as a public contract.** A public DTO is authored deliberately and evolves on its own schedule, independent of internal refactoring.

## 3. Naming and resource conventions

- **Resource-oriented routes** — nouns for resources (`/matters`, `/invoices`), not verbs.
- **Explicit action endpoints where a domain action does not fit CRUD** — a state transition with meaning beyond "update this field" (for example, issuing an invoice, revoking an installation) is its own named action endpoint, not an overloaded `PATCH`.
- Resource and field names are stable, published, and never renamed without a breaking-change cycle (§4).
- Identifiers in URLs and payloads are **opaque** — never a raw database primary key exposed by accident, and UUIDv7 values are treated as opaque tokens externally even though they are internally time-ordered.

## 4. Versioning and compatibility

- **Major-version namespaces** (for example `/v1/...`) are the unit of breaking change. A new major version coexists with the prior one; it never silently replaces it underneath existing callers.
- **Additive changes** — a new optional field, a new endpoint, a new enum value a well-behaved consumer is expected to tolerate — do **not** require a version bump.
- **Breaking changes** — removing or renaming a field, changing a field's type or meaning, tightening validation, removing an enum value — **require a new major version.**
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

- **Every retryable command accepts an idempotency key.** A retried request with the same key against the same resource produces the recorded outcome exactly once.
- **Optimistic concurrency using version or ETag-style preconditions** governs updates — a caller states the version it last read; a conflicting concurrent update is rejected, never silently overwritten.

## 11. Async operations

- Long-running work (bulk export/import, large reports) is an **async operation resource**: a request creates a job and returns a status reference; the caller polls or is notified.
- The status resource exposes `Pending → Running → Succeeded | Failed | Cancelled`, with progress where meaningful. Partial or speculative results are never presented as final.
- An async job inherits the same authorization composition as any other resource, re-checked immediately before sensitive delivery (§14).

## 12. Webhook rules

- **Internal domain events are never exposed directly as the permanent public contract.** A versioned `IntegrationEventEnvelope` — identifier, event type, contract version, occurrence time, subject reference, correlation/causation references, installation context, a minimal authorized payload, delivery-attempt metadata — is the only thing delivered externally. Confidential fields are never included merely because they exist internally.
- **Delivery is at-least-once; consumers must be idempotent.** **No claim of exactly-once delivery or global ordering** is ever made; per-subject ordering is guaranteed only where explicitly documented for a specific event type.
- **Deliveries are signed with timestamped signatures**, are **replay-resistant**, and support **secret rotation with bounded overlap**.
- **Bounded retries with backoff**; sustained failure produces visible **dead-letter/suspended-delivery** state; **manual replay is supported and audited**.
- **Permission and subscription checks happen before every delivery**, not only at subscription time.
- **No continued delivery after installation revocation** — revocation takes effect for webhook delivery immediately.
- **No secrets or full confidential payloads in delivery logs.**
- **Webhook event versioning is independent and explicit** from REST API versioning.
- **Inbound webhooks**: preserve the raw body for signature verification; authenticate before accepting business meaning; verify timestamp and replay window; enforce idempotency; validate schema/content-type; enforce strict size limits; rate-limit abusive sources; reject unregistered/inactive connections; prevent SSRF and internal-network callback abuse (with DNS-rebinding considered); quarantine malformed/suspicious input; record provenance; translate into an owning module's application command — **never write directly to a domain table**; acknowledge only per the provider's own contract; and keep provider delivery status separate from OneLegalPro's own business-truth state.
- **External calls are never made inside a domain database transaction.**

## 13. Import/export rules

- Bulk operations are asynchronous jobs (§11) with **immutable request and actor provenance**.
- **Firm and authorization are snapshotted at request time and re-validated immediately before sensitive delivery.**
- **Validation and partial-success reporting are per record**, not an opaque pass/fail.
- **File delivery goes through Documents' secure, expiring, authorization-gated delivery mechanism.** No unrestricted database dump, and no raw storage path is ever returned directly.
- **No bulk export bypasses Ethical Walls or co-client restrictions**, and **no export is granted merely because a user can view one item** — export authorization is evaluated per record included.
- **Every job's request, generation, and retrieval is independently audited.** No confidential payload appears in queue metadata or operational logs.

## 14. Security

Input validation and output encoding on every field; injection prevention; SSRF prevention on every outbound call the platform makes; exact callback/redirect allow-listing; DNS-rebinding considered at validation time; request size and complexity limits; rate limits and quotas per caller and per Firm; abuse detection; secrets held only as non-recoverable references with bounded rotation overlap; mandatory encryption in transit; sensitive-field redaction in logs and errors; correlation identifiers on every request; timeouts, circuit breakers, and bulkheads; a defined retry policy; backpressure under load; queue isolation; dead-letter handling; dependency-health checks; safe degradation over cascading failure; sandbox/production isolation with no default copy of production Firm data into a sandbox. **No API, integration, connector, webhook, background worker, or AI path may be less authorized than an equivalent interactive path.** Full detail: `docs/architecture/04_Security_Architecture.md` and `docs/architecture/17_API_Integration_Platform_Architecture.md` §39.

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
