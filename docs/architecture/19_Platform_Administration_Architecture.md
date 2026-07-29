# ARCH-019 — Platform Administration Architecture

**Status:** Proposed (conceptual architecture) — **not yet approved**. Implementation stages are **proposed, not scheduled**. **`PF-040` — AggregateRoot remains the next repository implementation story and remains Backlog.** No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled by this document. See `docs/architecture/08_Roadmap.md`.

## 1. Purpose and scope

This document defines the conceptual domain and system architecture for OneLegalPro's platform-administration capability — proposed module `PlatformAdministration` — implementing `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` and the relevant articles of `docs/architecture/01_OneLegalPro_Constitution.md`. It closes the ownership gap beneath the platform's tenancy model: **`Firm` is referenced by every bounded context and, until now, owned by none.**

**In scope:** the `Firm` aggregate and its lifecycle; `FirmProvisioning` as the governed act of bringing a Firm into existence and into service; `SubscriptionEntitlement` as term, status, and seat limit; entitlement lapse semantics and their strict separation from membership revocation; seat-limit enforcement at membership activation; and the cross-cutting Firm-isolation, security, privacy, audit, and failure-handling requirements that apply to all of the above.

**Out of scope:** everything in §26, and specifically — authentication, sessions, credentials, membership, and privileged access (**IdentityAccess**, `docs/architecture/16_Identity_Security_Access_Control_Architecture.md`); every monetary, invoice, payment, ledger, and tax concern (**Billing**, `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md`); branding and custom domains (**Branding**, `docs/architecture/10_White_Label_Platform_Architecture.md`); the `FirmContext` primitive and tenant resolution (**Platform Foundation**, `PF-080`/`PF-081`/`PF-082`); and every Firm business record (**Practice Management** and its peers).

This document describes **conceptual models only**. It defines no migrations, schemas, table names, source files, provider configuration, or vendor selection.

## 2. Why PlatformAdministration is a bounded context

Every approved architecture depends on a concept none of them owns:

| Dependent context | What it references but does not own |
|---|---|
| IdentityAccess | Firm security realm, `FirmMembership`'s Firm — `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §4 lists what it owns *and* what it does not; the Firm record is in neither list |
| Platform Foundation | `FirmContext` carries a Firm ID it never creates; §6 of ARCH-016 states Foundation "does not own membership" and resolves no identity |
| Branding | `BrandProfile` and `TenantDomain` are defined **per Firm**, presupposing a Firm |
| Practice Management | Every aggregate is Firm-scoped by `FirmContext`; `docs/adr/ADR-006-Practice-Management-Core.md` claims no ownership of the Firm |
| Documents, Billing, Communications, Digital Presence, Integrations, `Workflow` | Each isolates by Firm; none defines one |

No existing context is a plausible owner. IdentityAccess would become the owner of the platform's commercial relationship as well as its identity model — the adjacent-concern absorption its own §3 argues against. Platform Foundation owns *technical primitives*, not a business-governed lifecycle with provisioning, entitlement, and authorized state transitions. Practice Management is the Core Domain for a Firm's *legal work*; making it own the tenant would invert every dependency in the platform. Billing owns a Firm's records about **its clients**, a different commercial relationship with different parties (§12).

A dedicated, deliberately tiny context gives every consumer one place to ask "does this Firm exist, is it in service, and what is it entitled to" — and gives provisioning and entitlement a single point of audit.

## 3. Why it is deliberately narrow

`PlatformAdministration` owns **exactly three concepts** and is designed never to own a fourth. Narrowness is the architecture, not a Release 0.1 artefact.

A context named "platform administration" is the natural gravity well for support consoles, feature flags, reporting, usage analytics, reseller hierarchies, and operator tooling. Each would arrive individually justified, and together they would produce precisely the catch-all module `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §3 rejects for security — with the added hazard that a cross-Firm back office is, by construction, the one place where Firm isolation could be lost wholesale.

The constraint is therefore explicit: **any proposal to add a concept to `PlatformAdministration` requires its own approved ADR**, and the default answer is a different context or no capability at all.

**This is a boundary statement about this context, not a platform-wide prohibition.** Constitution Article 44 already reserves a **future Reporting bounded context**, which would require its own approved architecture. This document neither approves nor forecloses it; it states only that `PlatformAdministration` is not it (§16, §26, §27).

## 4. Domain ownership

**`PlatformAdministration` owns**

`Firm` · `FirmProvisioning` · `SubscriptionEntitlement` — and nothing else.

**`PlatformAdministration` does not own**

| Concept | Actual owner |
|---|---|
| Principals, `FirmMembership`, credentials, authenticators, MFA, sessions, recovery, invitations, capabilities, delegations, service principals, privileged-access and break-glass grants, security events | **IdentityAccess** (`docs/architecture/16_Identity_Security_Access_Control_Architecture.md`) |
| `FirmContext`, tenant resolution, tenant middleware | **Platform Foundation** (`PF-080`/`PF-081`/`PF-082` — technical primitives) |
| Invoices, payments, ledgers, client money, tax treatment, financial classification, `ExchangeRate` | **Billing** (`docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md`) |
| `Money`, `Currency` | **Foundation `PF-045`** — Backlog, deferred; **never consumed here** |
| `Client`, `Organization`, `Contact`, `Matter`, `MatterClient`, `MatterTeam`, `Task`, `EthicalWall`, `ConflictRelationship` | **Practice Management** (`docs/architecture/13_Practice_Management_Architecture.md`) |
| `BrandProfile`, `TenantDomain`, theme tokens, sender identity, AI persona | **Branding** (`docs/architecture/10_White_Label_Platform_Architecture.md`) |
| Firm tenant websites, Client Portal, widgets, booking | **Digital Presence** (`docs/architecture/12_Website_Client_Portal_Architecture.md`) |
| Documents, document versions, knowledge | **Documents** (`docs/architecture/14_Document_Knowledge_Management_Architecture.md`) |
| Communication threads and messages | **Communications** (`docs/architecture/11_Communications_Hub_Architecture.md`) |
| Public API contracts, installations, webhooks, connectors | **Integrations** (`docs/architecture/17_API_Integration_Platform_Architecture.md`) |
| Workflow and AI-run state | **`Workflow`** (`docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md`) |
| Official law and legal sources | **Legal Intelligence** (`docs/architecture/09_Legal_Intelligence_Architecture.md`) |
| AI authority | **Nobody — AI is never an authorization authority** |

## 5. Ubiquitous language

| Term | Meaning |
|---|---|
| **Firm** | The tenant — a law firm as a subject of the platform. The isolation boundary every other context scopes to. |
| **Firm provisioning** | The governed act of bringing a Firm into existence and into service. Never a side effect of another operation. |
| **Subscription entitlement** | What the operator has permitted this Firm to use, and until when: term, status, seat limit. **Never an amount, price, invoice, payment, or ledger entry.** |
| **Term** | The period an entitlement covers, with an explicit start and end. |
| **Status** | The entitlement's current state (§9). An administrative fact, never a security verdict. |
| **Seat limit** | The maximum number of **active Firm memberships** permitted. An administrative counter, never a licence-metering, usage-metering, or billing mechanism. |
| **Entitlement lapse** | Term expiry or administrative suspension of an entitlement. **Blocks new authentication; never terminates a live session** (§9). |
| **Membership revocation** | A **security** event owned by IdentityAccess that terminates affected sessions immediately (§9). A categorically different event from lapse. |
| **Operator realm** | The separate platform security realm in which platform-operator identities exist, holding no Firm access by default (Constitution Article 27). |

## 6. Firm

`Firm` is the aggregate root for the tenant.

- It carries the Firm's **identity, canonical name, jurisdiction reference, and lifecycle state**. It carries no branding, no business data, and no financial data.
- Its identifier is the value every other context means when it says "Firm" — the same identity `FirmContext` carries, `FirmMembership` references, and every Firm-scoped aggregate isolates by.
- **A `Firm` record's existence proves nothing about access.** It is not membership, not authentication, and not authorization (Constitution Articles 27–28).
- Firm lifecycle transitions are **explicit, authorized, audited commands**, never inferred from other activity — the same discipline `docs/architecture/13_Practice_Management_Architecture.md` §3 applies to `Matter`.
- **`Firm` is never merged, linked, or cross-referenced with another `Firm`.** Automatic cross-Firm linking is prohibited (Constitution Article 27), and no `PlatformAdministration` record spans two Firms.

## 7. FirmProvisioning

`FirmProvisioning` records the governed act of bringing a Firm into service.

```text
Requested → Provisioned → Active → Closed (terminal)
```

- **Requested** — a Firm is intended, with the commercial arrangement agreed **outside the application** (§12).
- **Provisioned** — the `Firm` record and its initial `SubscriptionEntitlement` exist; the Firm is not yet usable.
- **Active** — the Firm is in service. IdentityAccess may issue the first Firm Admin invitation (§11).
- **Closed** — terminal. Closure ends service; **it never destroys audit history**, and it is never a data-deletion mechanism (§19).

**Rules**

- Every transition is an explicit, human-authorized command producing an auditable event, recording the acting identity, the authorization relied on, and the reason where one is required.
- **A Firm never becomes usable as a side effect.** There is no implicit activation, no activation-on-first-login, and no self-service signup path in this architecture.
- **Provisioning creates no principal, credential, session, or membership.** It triggers an invitation through IdentityAccess and nothing more (§11).
- Provisioning state and entitlement state are **distinct facts** and are never collapsed into one field: a Firm may be `Active` with a lapsed entitlement, and the two produce different consequences (§9).

**There is no Firm-level suspended state in Release 0.1.** The two suspension meanings that *do* exist are owned elsewhere and operate at different levels: a **suspended or expired `SubscriptionEntitlement`** is a commercial/administrative fact that blocks new authentication while existing valid sessions continue to normal expiry (§9), and a **suspended or revoked `FirmMembership`** is an individual security event, owned by IdentityAccess, that terminates that principal's sessions immediately (§9). Neither is a Firm-wide disable.

**A future Firm-level suspension or emergency-disable capability requires its own separately approved decision** — one that explicitly defines the authorizing authority, the effect on existing sessions, the recovery path, notification to the Firm, the treatment of queued and in-flight work, and the audit semantics distinguishing it from both entitlement lapse and membership revocation. **This document neither designs nor pre-authorizes such a capability, and nothing here may be read as an emergency-disable policy.**

## 8. SubscriptionEntitlement

`SubscriptionEntitlement` records **exactly three facts**: term, status, and seat limit.

**It contains** — a term with an explicit start and end; a status (§9); a seat limit expressed as a maximum count of active Firm memberships; and the provenance of each change (acting identity, timestamp, authorization).

**It does not contain, and no field may reconstruct** — an amount, price, rate, currency, `Money`, `Currency`, invoice, invoice number, payment, payment method, payment reference, ledger entry, balance, discount, proration, credit, refund, tax rate, tax identifier, tax treatment, plan price, or any monetary value of any kind.

**Why the prohibition is structural, not stylistic.** Constitution Articles 22–25 place every monetary concern in Billing under a specific discipline — immutable posted records, append-only ledgers, no direct balance mutation, exact decimal with explicit currency, and `Money`/`Currency` as Foundation primitives governed by `PF-045` that **no module may duplicate**. An entitlement record carrying an amount would be a financial record that satisfies none of that discipline, and would introduce a second money type `AGENTS.md` prohibits outright. Independently: `PF-045` is Backlog and deferred, so no monetary primitive exists to carry a value correctly.

**Entitlement is not authorization.** A valid entitlement grants nothing by itself. It is one composed input to IdentityAccess's decision (§11) and can only ever narrow.

## 9. Entitlement lifecycle and lapse semantics

```text
Active → Expired   (term end reached)
Active ⇄ Suspended (administrative — the ENTITLEMENT, never the Firm)
Active → Closed    (terminal, with the Firm)
```

**`Suspended` here is a state of the `SubscriptionEntitlement` alone.** It is **not** a Firm-level suspension — no such state exists (§7) — and it is not a membership suspension, which IdentityAccess owns.

**The two lapse paths and the one security path are permanently distinct.**

| Event | Owner | Effect on new authentication | Effect on live sessions | Audit meaning |
|---|---|---|---|---|
| **Entitlement expired** | `PlatformAdministration` | **Blocked** | **Continue to normal expiry** | Commercial/administrative fact |
| **Entitlement suspended** | `PlatformAdministration` | **Blocked** | **Continue to normal expiry** | Commercial/administrative fact |
| **Membership suspended or revoked** | **IdentityAccess** | Blocked for that principal | **Terminated immediately**, cached positive decisions invalidated | **Security event** |

**Rules**

- **These paths never share code, audit meaning, or policy semantics.** Collapsing them would make "this Firm's subscription lapsed" indistinguishable from "this person's access was revoked" in the security record — a materially different fact about a materially different subject.
- **A lapse is not a security incident.** Terminating live sessions mid-work for an administrative condition would be disproportionate and would corrupt the security audit trail with commercial events.
- **Membership revocation remains immediate and unaffected**, exactly as `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §8 and §44 require. Where an individual's access genuinely warrants immediate cutoff, the correct instrument is IdentityAccess's own membership suspension or revocation — never a repurposed entitlement flag. **Release 0.1 offers no Firm-wide instrument for this**, and inventing one is a separately approved decision (§7).
- **An existing session continues only within its own validity** and only where its locally verifiable revocation requirements remain satisfied (`docs/architecture/04_Security_Architecture.md` §8). A lapse never extends a session.
- **No stale positive entitlement survives a status change.** Entitlement caches are version-aware and invalidated promptly, on the same discipline as authorization caches.
- **Restoration is an explicit, authorized, audited act**, never an automatic consequence of time passing.

## 10. Seat limits

- **The seat limit caps active Firm memberships**, counted by IdentityAccess as the owner of membership.
- **Enforcement occurs at membership activation**, deterministically: activating a membership that would exceed the limit is **refused**, with a clear, non-disclosing result.
- **Enforcement is never retroactive.** A seat limit later reduced below the current active count **never silently revokes anyone**. The over-limit condition is surfaced for an authorized human to resolve; the platform never chooses which lawyer loses access to live matters.
- **Seat accounting is administrative, never professional.** Being within a seat limit says nothing about qualification, authority, Matter access, or any future Ethical Wall outcome.
- **Seat limits are not usage metering, licence metering, or billing.** No consumption is measured, priced, or reported.

## 11. Relationship to IdentityAccess

**The dependency is one-way and fixed: `PlatformAdministration` → consumed by → IdentityAccess.**

- `PlatformAdministration` publishes `Firm` identity and entitlement state as queries. **It performs no authentication, holds no credential, session, MFA factor, recovery secret, or invitation, and makes no authorization decision.**
- `FirmMembership` references `FirmId` **by identifier only**. IdentityAccess never embeds, copies, or duplicates a `Firm` record.
- **IdentityAccess enforces entitlement at the authentication and membership boundaries** — the only places it can be enforced without creating a second authorization authority. The result composes into the existing decision in `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §24 and **can only narrow**; it never widens, and never substitutes for membership, capability, or domain authorization.
- **`PlatformAdministration` never calls IdentityAccess to authorize anything.** There is no circular ownership and no mutual dependency.
- **First-admin onboarding** is provisioning plus invitation: `PlatformAdministration` provisions; IdentityAccess issues a Firm-bound, purpose-scoped, time-limited, single-use invitation; the invitee establishes their own factors to the Firm's policy (ARCH-016 §9). **An invitation is an offer, not proof of identity or entitlement.**
- **Operator support access is IdentityAccess's `PrivilegedAccessGrant` and nothing else** (§15, `docs/adr/ADR-014-Operator-Assisted-Onboarding-and-Privileged-Access.md`).

## 12. Relationship to Billing

**Two different commercial relationships, two different owners, no overlap.**

| Relationship | Parties | Owner |
|---|---|---|
| Operator grants a Firm the right to use the platform | Platform operator ↔ Firm | **`PlatformAdministration`** — entitlement only, no money |
| Firm bills, receives, and accounts for its clients' money | Firm ↔ its clients | **Billing** (Constitution Articles 22–25) |

- **Billing remains the sole owner of Firm-to-client commercial and financial records** — invoices, payments, client-money/trust ledgers, and the Firm's general ledger. Nothing here duplicates, approximates, or pre-empts any of it.
- **Constitution Article 22 is not narrowed.** Its statement that no other context owns an invoice, payment, client-money balance, or ledger entry is preserved exactly, because `SubscriptionEntitlement` is none of those things (§8).
- **Manual external contracting, invoicing, and payment remain outside the application.** The application records and enforces entitlement; it never collects, calculates, stores, transmits, or displays a monetary figure.
- Bringing the operator↔Firm commercial arrangement into the application would require **its own approved ADR**. It does not enter `PlatformAdministration` by default, and it would not enter it merely because entitlement lives here.

## 13. Relationship to Practice Management

- **`PlatformAdministration` never stores, reads, derives, aggregates, exports, or reports on any Firm business record** — no `Client`, `Matter`, `MatterClient`, `MatterTeam`, `Task`, or conflict attestation — and it **never stores or duplicates another context's audit records**, including Practice Management's business and activity history. It does own its own administrative audit facts about `Firm`, provisioning, entitlement, and seat limits (§19).
- Practice Management's aggregates remain Firm-scoped by `FirmContext`; they never read entitlement and never depend on `PlatformAdministration` at request time.
- **Practice Management remains the sole future authority for Ethical Walls and conflict checking** (`docs/adr/ADR-006-Practice-Management-Core.md` Decision 4; `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`). `PlatformAdministration` has no relationship to either and never gains one.

## 14. Relationship to Branding and the temporary white-label posture

- **Branding owns `BrandProfile`, `TenantDomain`, theme tokens, assets, sender identity, and AI persona.** `PlatformAdministration` owns none of them and defines no theme-token schema.
- Branding's per-Firm model presupposes a `Firm`; this document supplies the owner it assumed, and nothing more.
- Release 0.1 carries a **temporary, bounded white-label posture** (`docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md` Decision 6): a neutral, token-driven Firm-facing interface with **no OneLegalPro brand value hardcoded in any Firm-facing template or component**, and every presentation value resolving through **one replaceable indirection**. **Constitution Articles 10–11 are not waived, narrowed, or excepted** — the posture is a bounded deferral that ends when EPIC-003 delivers the `BrandProfile` foundation, which replaces the indirection rather than rewriting the surfaces.
- **The operator's OneLegalPro marketing website is not a Firm-facing surface and is not a Firm tenant website.** It sits outside Digital Presence and outside `app/Modules`, creates no Client Portal principal and no Firm membership, and does not weaken the Firm-facing rules above. "OneLegalPro" is the platform operator's own identity there, exactly as Constitution Article 10 states.

## 15. Operator realm and support access

- **Platform-operator identities exist in the separate platform security realm and hold no Firm access by default** (Constitution Article 27, ARCH-016 §6, §38).
- **Owning or provisioning a `Firm` record confers no access to what is inside the Firm.**
- **All operator support access runs through IdentityAccess's `PrivilegedAccessGrant`** — explicit, purpose-bound, time-limited, strongly authenticated, dually attributed, and recorded in a **Firm-visible, append-only support-access history**. **`PlatformAdministration` defines no operator access path of its own, and no second privileged-access mechanism exists.** See `docs/adr/ADR-014-Operator-Assisted-Onboarding-and-Privileged-Access.md`.
- **Break-glass is excluded from Release 0.1 as a capability**, while Constitution Article 29 and ARCH-016 §39 remain fully in force and unamended.
- **Support access is never an entitlement bypass**: an operator grant does not restore Firm access for anyone past a lapse.

## 16. Firm isolation

- **Every `PlatformAdministration` record concerns exactly one Firm.** No record, query, projection, index, cache, or event spans two Firms.
- **`PlatformAdministration` never owns or performs cross-Firm reporting, analytics, benchmarking, or comparison**, and there is no cross-Firm read, list, search, aggregate, ranking, or report **in this context** (§3, §26).
- **A future Reporting bounded context is reserved by Constitution Article 44 and requires its own approved architecture.** This document neither approves nor permanently prohibits it. Any such capability would have to preserve **Firm isolation, authorization before retrieval, denied-existence confidentiality, purpose limitation, and the most restrictive applicable domain rule** — and it would not live here.
- **`FirmContext` is still constructed only from verified identity and membership** (Constitution Article 27). A `Firm` record's existence, a Firm identifier in a request, a hostname, a custom domain, a header, or an email domain proves nothing.
- **A Firm identifier is not a secret and not a capability.** Knowing one grants nothing.

## 17. Security

- **Entitlement decisions fail closed.** When entitlement state cannot be authoritatively determined, **new authentication is refused** (`docs/architecture/04_Security_Architecture.md` §8). An already-issued session continues only within its own validity and satisfied revocation requirements. **Availability never outranks Firm isolation or authorization.**
- **Successful entitlement is not authentication; successful authentication is not authorization** (Constitution Article 28).
- **Denied and unauthenticated callers receive no existence confirmation.** Whether a Firm exists, whether it holds an entitlement, and whether that entitlement has lapsed are not disclosed — including through error text, response shape, timing, or account-discovery and recovery responses (ARCH-016 §42).
- **No credential, reusable secret, session token, MFA secret, or recovery material** is stored, logged, cached, or carried in any `PlatformAdministration` record, event, or audit payload.
- **Every provisioning, entitlement, and seat-limit change requires explicit human authorization** and is refused when the authorizing decision is unavailable.
- **No standing operator access to Firm data is created anywhere in this context** (§15).
- **Authorization caches are version-aware and invalidated promptly**; no stale positive entitlement broadens access.

## 18. Privacy

- `PlatformAdministration` holds **Firm-level administrative data**, not client or matter data, and never personal data belonging to a Firm's clients.
- **No Firm business content enters any `PlatformAdministration` record, event, projection, or log.**
- **Firm identifiers and administrative state are never placed in URL parameters, query strings, or any other position where they would be logged by intermediaries** as a matter of routine handling.
- Data classification follows `docs/architecture/04_Security_Architecture.md` §7: Firm administrative configuration is **Internal**; nothing in this context is Confidential client/Matter data, and nothing is a Security Secret.

## 19. Audit

**`PlatformAdministration` owns its own append-only administrative audit facts** for Firm creation and lifecycle, provisioning, entitlement, seat-limit decisions, and authorization refusals **within its own context** — and owns nothing else. It **never stores or duplicates** Practice Management's business or activity history, IdentityAccess's security-event content, privileged narratives, or any other context's audit records. **Firm-visible support-access history remains owned by IdentityAccess** (§15, `docs/adr/ADR-014-Operator-Assisted-Onboarding-and-Privileged-Access.md`).

Append-only administrative audit events are required for at least:

Firm created · Firm lifecycle transition (provisioned, activated, closed) · entitlement created · entitlement term changed · entitlement status changed (expired, suspended, restored, closed) · seat limit changed · seat-limit enforcement refusal · authorization refusal for a high-risk administrative action.

**Content rules**

- Records carry **safe metadata only** — actor, Firm, event, result, timestamp, correlation, and authorization provenance — and **never a credential, session secret, recovery material, token, privileged narrative, Client or Matter content, or any cross-Firm information**.
- **Records are append-only. Correction is a new record, never a rewrite**, on the same discipline Constitution Articles 8, 18, and 23 and ARCH-016 §40 establish elsewhere.
- **Audit history is not editable by the actor being audited.**
- **Closing a Firm never destroys the audit fact that it existed**, and closure is never a data-deletion mechanism.
- **Entitlement events and security events are distinct streams with distinct meanings** and are never merged (§9).

## 20. Failure handling

- **Entitlement unavailable → new authentication refused** (fail closed). Live sessions continue only within their own validity.
- **Provisioning partially completed → the Firm remains not usable.** There is no partially-active Firm and no implicit completion.
- **A notification, rendering, or downstream failure never rolls back a committed provisioning or entitlement change**, and never rewrites a committed audit record (Constitution Article 30 discipline, applied here).
- **A seat-limit check that cannot be evaluated refuses the activation** rather than approximating the count.
- **No automatic remediation.** An over-limit condition, an expired term, or an inconsistent state is surfaced for an authorized human; the platform never resolves it by revoking access on its own initiative.

## 21. AI rules

This document **introduces no AI capability**, and Release 0.1 contains none.

**AI is never an entitlement, provisioning, seat-limit, membership, or authorization authority.** AI may not create, activate, or close a `Firm`; may not create, change, suspend, or restore an entitlement term, status, or seat limit; may not grant, widen, or approve access of any kind; and may never approve its own proposal (Constitution Articles 6, 28, 39, 40). `docs/architecture/05_AI_Architecture.md` is **unmodified** by this story.

## 22. Conceptual Laravel module placement

Conceptual only. **No directory or source file is created by this story.**

```text
app/Modules/PlatformAdministration/
├── Application/
│   ├── Commands/        (ProvisionFirm, ActivateFirm, CloseFirm,
│   │                     CreateSubscriptionEntitlement, ChangeEntitlementTerm,
│   │                     SuspendEntitlement, RestoreEntitlement, ChangeSeatLimit)
│   │                     — note: no SuspendFirm; there is no Firm-level
│   │                       suspended state in Release 0.1 (§7)
│   ├── Queries/         (GetFirm, GetEntitlementStatus, GetSeatLimit)
│   ├── Handlers/
│   └── DTOs/
├── Domain/
│   ├── Aggregates/      (Firm, FirmProvisioning, SubscriptionEntitlement)
│   ├── ValueObjects/    (FirmId, FirmName, EntitlementTerm, EntitlementStatus, SeatLimit)
│   ├── Events/          (FirmProvisioned, FirmActivated, FirmClosed,
│   │                     EntitlementCreated, EntitlementTermChanged,
│   │                     EntitlementStatusChanged, SeatLimitChanged)
│   ├── Policies/
│   └── Repositories/    (contracts only)
├── Infrastructure/      (Eloquent adapters, outbox integration)
├── Interface/           (administrative routes — operator realm only)
├── Database/
├── Routes/
├── Tests/
├── Config/
├── ModuleServiceProvider.php
└── README.md

Consumed, not duplicated: app/Foundation primitives (PF-040 AggregateRoot, PF-041 Entity,
PF-042 ValueObject, PF-043 DomainEvent, PF-044 BusinessIdentifier, PF-047 Clock,
PF-048 UuidV7, PF-049 exceptions) and PF-080 FirmContext.

Never consumed: PF-045 Money and PF-046 Result — no PlatformAdministration record
carries a monetary value, and Foundation primitives throw rather than return Result.
```

Rules, per `docs/domain/06_Laravel_Module_Blueprint.md` unchanged:

- **No other module imports `PlatformAdministration`'s Eloquent models, and it imports none of theirs.** Cross-module use is published contracts only.
- **`PlatformAdministration` never writes another module's tables**, and never recreates another module's business rules.
- **It defines no credential, session, or authorization model** — every one of those is IdentityAccess's.
- Dependency direction: `Interface → Application → Domain`; Infrastructure depends on Application/Domain contracts; Domain never depends on Laravel, Eloquent, queues, HTTP, or SDKs.

## 23. Invariants

- No `PlatformAdministration` record, query, cache, projection, index, or event spans Firms.
- `PlatformAdministration` never owns or performs cross-Firm reporting, analytics, benchmarking, or comparison; a future Reporting bounded context is reserved by Constitution Article 44 and needs its own approved architecture.
- A `Firm` never becomes usable as a side effect of another operation.
- There is no Firm-level suspended state; a future Firm-level suspension or emergency-disable capability requires its own separately approved decision.
- `PlatformAdministration` owns its own administrative audit facts and never stores or duplicates another context's audit records.
- Every Firm and entitlement transition is an explicit, human-authorized, audited command.
- `SubscriptionEntitlement` carries no monetary value, and no field permits one to be reconstructed.
- Entitlement lapse blocks new authentication and never terminates a live session.
- Membership suspension or revocation terminates affected sessions immediately and remains IdentityAccess's own security event.
- Entitlement lapse and membership revocation never share code, audit meaning, or policy semantics.
- Seat-limit enforcement occurs at membership activation, is deterministic, and is never retroactive.
- A seat-limit reduction never silently revokes an existing membership.
- Entitlement grants no access by itself and can only narrow an authorization decision.
- Owning or provisioning a `Firm` confers no access to Firm data.
- No second authentication, authorization, or privileged-access path exists in this context.
- Audit records are append-only; closing a Firm never destroys audit history.
- Entitlement decisions fail closed; availability never outranks Firm isolation or authorization.
- AI holds no authority over any of the above.

## 24. Alternatives considered

Recorded in full in `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md`. In summary, each of the following was rejected: giving `Firm` to IdentityAccess, to Platform Foundation, or to Practice Management; giving `SubscriptionEntitlement` to Billing; putting an amount, price, or plan tier on the entitlement; building in-application subscription billing or checkout; letting `PlatformAdministration` authenticate operators or grant Firm access; terminating live sessions the moment a subscription lapses; auto-revoking memberships on a seat-limit reduction; and letting this context grow into a general platform back office.

## 25. Consequences and trade-offs

- The platform gains one unambiguous owner for `Firm` without moving any existing ownership.
- This is the smallest bounded context on the platform, and it is constrained to stay that way — a maintenance benefit and a standing review obligation.
- Entitlement enforcement puts a synchronous, Firm-scoped read on the authentication path. It is small and cacheable, but its failure mode is fail-closed, so a `PlatformAdministration` outage prevents new logins. That is the correct trade and is stated plainly.
- Keeping lapse and revocation separate costs two paths and two event types where one would look simpler. The separation is the point: it keeps the security audit record truthful.
- Because entitlement carries no monetary value, the application can never answer "how much does this Firm pay?" That is intended; the commercial record lives outside the application.
- Deferring `PF-045 Money` remains tenable only while §8's prohibition holds. Any proposal to record an amount here reopens `PF-045` as a hard prerequisite.

## 26. Explicit non-goals

This architecture does **not**: implement anything; create modules, source files, schemas, migrations, or tests; define pricing, plans, tiers, discounts, proration, trials, renewals, dunning, tax, or invoicing; introduce `Money`, `Currency`, or any monetary field; schedule or unblock `PF-045`; create payment, checkout, or provider integration; build a platform back office, support console, admin impersonation feature, feature-flag system, reseller or partner hierarchy, or usage metering — **each of which would require its own separately approved architecture**; own or perform **cross-Firm reporting, analytics, benchmarking, or comparison**, which is not this context's and which a future Reporting bounded context (Constitution Article 44) would need its own approved architecture to provide; define a Firm-level suspension or emergency-disable capability; define self-service signup; grant operators any access to Firm data; create a second authentication, authorization, or privileged-access path; design break-glass; define infrastructure, deployment, database design, or production procedures; populate `docs/architecture/03_Database_Design.md`; select or endorse a vendor, provider, package, or platform; assert a legal, tax, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; introduce an AI capability; modify `docs/architecture/05_AI_Architecture.md`; weaken or create an exception to Constitution Articles 1–44; schedule EPIC-012; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

**No capability described in this document is claimed to be implemented.**

## 27. Future expansion

Each is **describable as future work, not implemented, not architected in detail, and not scheduled**; each would require its own architecture pass, its own approved ADR, and its own approved stories — and none of them is presumed to belong in this context:

- **Operator↔Firm commercial records** (if ever brought into the application) — would require its own ADR and would engage Billing's ownership questions directly.
- **Self-service Firm signup** — currently absent by design; provisioning is a governed, authorized act.
- **Multi-entity Firm structures** (branch offices, affiliated practices, group hierarchies) — must not be assumed to be a `Firm` hierarchy without its own analysis.
- **Firm data export and portability at the tenant level**, beyond Release 0.1's Firm-scoped export.
- **Firm-level configuration and policy defaults** — likely belonging to the owning contexts rather than here.
- **A Firm-level suspension or emergency-disable capability** — would have to define the authorizing authority, session effects, recovery, notification, queued-and-in-flight-work behaviour, and audit semantics distinguishing it from entitlement lapse and membership revocation (§7).

**Outside this context, and each requiring its own separately approved architecture rather than being globally impossible:** a general platform back office or support console; feature-flag and configuration-management systems; reseller, partner, or multi-tenant group hierarchies; usage or licence metering; and **cross-Firm reporting, analytics, benchmarking, or comparison**, which Constitution Article 44 already reserves for a **future Reporting bounded context**. **This document neither approves nor permanently prohibits that context**; it states only that `PlatformAdministration` is not it, and that any such capability must preserve Firm isolation, authorization before retrieval, denied-existence confidentiality, purpose limitation, and the most restrictive applicable domain rule.

**One thing is genuinely absolute rather than deferred:** there is no operator access path outside IdentityAccess's `PrivilegedAccessGrant` (§15, Constitution Articles 29, 48).

## 28. Proposed implementation stages

**Proposed only. None of these stages is approved, scheduled, or assigned a story ID**, and each requires separate entry into `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`, with its own Definition of Ready, before implementation begins.

1. **`Firm` aggregate foundation** — identity, canonical name, jurisdiction reference, lifecycle state, and audited transitions.
2. **`FirmProvisioning` lifecycle** — the governed requested → provisioned → active → closed path with explicit authorization and audit. **No Firm-level suspended state**; a future Firm-level suspension or emergency-disable capability is a separately approved decision (§7).
3. **`SubscriptionEntitlement`** — term, status, and seat limit, with the monetary prohibition (§8) enforced by the model itself.
4. **Entitlement lapse semantics and published entitlement query** — the fail-closed contract IdentityAccess consumes, with lapse and revocation kept strictly distinct.
5. **Seat-limit contract** — the deterministic, non-retroactive check IdentityAccess applies at membership activation.
6. **Administrative audit stream** — the append-only event set in §19, distinct from IdentityAccess's security event stream.

Every stage depends on Platform Foundation's `PF-080` `FirmContext` and on `PF-040` `AggregateRoot`, neither of which is scheduled by this document. **`PF-040` remains the next code story and remains Backlog.**
