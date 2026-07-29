# ADR-013 — Firm, Provisioning, and Subscription Entitlement Ownership

## Status

**Accepted.** Explicit owner approval recorded on PR #30 on 29 July 2026. Acceptance authorizes the architectural decision only; it does not authorize implementation, deployment, or production access.

## Context

The platform's entire isolation model rests on a concept **no approved bounded context owns**.

`docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §4 lists both what IdentityAccess owns and what it explicitly does not. It owns Firm-scoped principals, `FirmMembership`, credentials, sessions, and invitations — **the Firm record itself appears in neither list.** `docs/adr/ADR-006-Practice-Management-Core.md` gives Practice Management `Client`, `Organization`, `Contact`, `Matter`, and their satellites, and explicitly refuses ownership it does not need. `docs/adr/ADR-003-White-Label-Platform.md` gives Branding a `BrandProfile` and `TenantDomain` **per Firm**, presupposing a Firm. Platform Foundation owns the `FirmContext` *technical primitive* (`PF-080`) and, by `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §6, "does not own membership" and resolves no identity.

So `Firm` is referenced by every context and owned by none. That is a gap, not a contradiction — but it is the gap directly beneath Constitution Article 27's Firm security realm, and it cannot stay open once a real firm is provisioned and billed.

A second concept has no owner at all. OneLegalPro must sell subscriptions to law firms, and the application must **enforce** what was sold — a subscription term, a status, and a seat limit. Nothing in ARCH-001 through ARCH-010 describes an operator-to-Firm commercial relationship. The nearest neighbour, Billing (`docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md`), is emphatically about something else: it owns **a Firm's** invoices to **its clients**, its client-money/trust ledgers, and its own general ledger. Constitution Article 22 states that "no other bounded context owns an invoice, a payment, a client-money balance, or a ledger entry."

Naming the new concept "subscription" without resolving that distinction would read as a second billing owner and would collide with Article 22 on its first sentence.

Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, this needs approved architecture before any Firm, provisioning, or entitlement implementation begins.

## Decision

1. **`PlatformAdministration` is a new, narrowly bounded supporting context**, proposed module `PlatformAdministration`. It owns **exactly three things: `Firm`, `FirmProvisioning`, and `SubscriptionEntitlement`** — nothing else, now or by later accretion. Its narrowness is the decision, not an artefact of Release 0.1 scope.

2. **`PlatformAdministration` owns the `Firm` aggregate** — the tenant's identity, canonical name, jurisdiction reference, and lifecycle state. This closes the ownership gap without moving any existing ownership: IdentityAccess keeps membership, Practice Management keeps `Client`, Branding keeps `BrandProfile`, and Platform Foundation keeps the `FirmContext` primitive.

3. **`FirmProvisioning` records the governed act of bringing a Firm into existence and into service** — `Requested → Provisioned → Active → Closed` — as an explicit, authorized, auditable lifecycle. A Firm never becomes usable as a side effect of some other operation. **There is no Firm-level suspended state in Release 0.1**, and this ADR designs no Firm-wide disable: a future Firm-level suspension or emergency-disable capability requires its own separately approved decision defining the authorizing authority, the effect on existing sessions, the recovery path, notification to the Firm, the treatment of queued and in-flight work, and audit semantics distinguishing it from both entitlement lapse and membership revocation (Decision 9).

4. **`SubscriptionEntitlement` records exactly three facts: subscription term, subscription status, and seat limit.** It contains **no amount, no price, no currency, no invoice, no payment, no ledger entry, no tax treatment, no discount, no proration, no `Money`, and no `Currency`** — and no field from which any of those could be reconstructed. It is an *entitlement* record: what the Firm is permitted to use, and until when.

5. **`SubscriptionEntitlement` is not billing, and Billing's ownership is unchanged and unshared.** Constitution Article 22 governs **the Firm's commercial and financial records about its own clients**; `SubscriptionEntitlement` governs **the platform operator's entitlement grant to the Firm**. These are different relationships with different parties, and neither owns the other's records. **Billing remains the sole owner of Firm-to-client commercial and financial records.** If Release 0.1's manual commercial arrangement is ever brought into the application, it requires its own approved ADR and does not enter `PlatformAdministration` by default.

6. **Manual external contracting, invoicing, and payment remain outside the application.** The application records and enforces entitlement; it never collects, calculates, stores, transmits, or displays an amount, price, invoice, payment, or tax figure. This is independently corroborated by `PF-045 Money` remaining Backlog and deferred: no monetary primitive exists, and `AGENTS.md` forbids introducing a second one.

7. **The dependency direction is one-way and fixed: `PlatformAdministration` → consumed by → IdentityAccess.** `PlatformAdministration` owns `Firm` and publishes entitlement state as a query. `FirmMembership` references `FirmId` **by identifier only**. **`PlatformAdministration` performs no authentication, holds no credential, session, MFA factor, recovery secret, or invitation, and makes no authorization decision.** It never calls into IdentityAccess to authorize anything, and there is no circular ownership.

8. **IdentityAccess evaluates entitlement at exactly two gates, and nowhere else.**

   - **The authentication / session-issuance gate.** Entitlement is evaluated **only after successful credential verification and any required MFA verification, and before a session is issued.** Evaluating it earlier would let an unauthenticated caller probe whether a Firm exists or whether its entitlement is active; evaluating it later would issue a session the entitlement does not support. **Failure responses stay enumeration-resistant in text, in response shape, and in practicable timing**, so a lapsed entitlement is not distinguishable from a wrong password by an unauthenticated observer. A **verified** user — one who has already proven credential and MFA — may be given the **minimum safe operational instruction** needed to seek help, and that instruction reveals nothing about any other Firm or account.
   - **The membership-activation / seat-limit gate** (Decision 10).

   **Entitlement is not a per-request resource-authorization input for an already-issued, valid session.** It cannot be, because the approved lapse policy (Decision 9) deliberately lets such a session continue to its normal expiry — re-checking entitlement per request would terminate exactly the sessions that policy protects. **Session renewal, reauthentication, and issuance of any new Firm-bound session are new authentication decisions and re-check entitlement.**

   **Resource authorization remains owned and composed by IdentityAccess and the domain modules** under Constitution Article 28 and `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §24. **Entitlement is not a term in that composition and never grants resource access**; it decides whether a session may be issued at all, not what an issued session may reach. It never substitutes for membership, capability, or domain authorization, and it never widens anything.

9. **Entitlement lapse and membership revocation are different events with different semantics, and they never share code, audit meaning, or policy semantics.** Both are preserved exactly, and **neither is a Firm-wide disable** — no such capability exists (Decision 3).

   - **Expired or suspended `SubscriptionEntitlement` — a commercial/administrative fact — blocks new authentication.** Existing valid sessions **continue only until their normal expiry** — a commercial lapse is not a security incident, and terminating live sessions mid-work would be a disproportionate and confusing response to an administrative state. `Suspended` here is a state of the **entitlement**, never of the `Firm`.
   - **Firm membership suspension or revocation is a distinct security event and terminates affected sessions immediately**, per `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §8 and §44, with cached positive decisions invalidated promptly.
   - The two produce **distinct audit events with distinct meanings**. Collapsing them would make "the Firm's subscription lapsed" indistinguishable from "this person's access was revoked" in the security record — a materially different fact about a materially different subject.

10. **Seat limit is enforced at membership activation, deterministically, and never retroactively.** Activating a membership that would exceed the Firm's seat limit is refused. A seat limit later reduced below the active-membership count **never silently revokes anyone** — the condition is surfaced for an authorized human to resolve. Seat accounting counts active Firm memberships; it is not a licence-metering, usage-metering, or billing mechanism.

11. **Every `PlatformAdministration` record is Firm-scoped or Firm-identifying, and none of it is Firm business data.** `PlatformAdministration` never stores, reads, derives, aggregates, or exports a Firm's `Client`, `Matter`, `Task`, document, communication, or financial content. **No `PlatformAdministration` record, query, cache, projection, index, or event spans Firms**, and this context **never owns or performs cross-Firm reporting, analytics, benchmarking, or comparison**. Constitution Article 5's isolation discipline applies to it in full. **This is a boundary statement about this context, not a platform-wide prohibition:** Constitution Article 44 already reserves a **future Reporting bounded context**, which requires its own approved architecture and which this ADR **neither approves nor permanently prohibits**. Any such future capability must preserve Firm isolation, authorization before retrieval, denied-existence confidentiality, purpose limitation, and the most restrictive applicable domain rule.

12. **`PlatformAdministration` owns its own administrative audit facts, and only those.** It owns append-only audit for `Firm` creation and lifecycle, provisioning, entitlement changes, seat-limit decisions, and authorization refusals **within its own context**. It **never stores or duplicates** Practice Management's business or activity history, IdentityAccess's security-event content, privileged narratives, or any other context's audit records; **Firm-visible support-access history remains owned by IdentityAccess** (`docs/adr/ADR-014-Operator-Assisted-Onboarding-and-Privileged-Access.md`). Audit and event payloads carry **safe metadata only** and never a credential, session secret, recovery material, privileged content, Client or Matter content, or cross-Firm information.

13. **`PlatformAdministration` grants no operator access to Firm data.** Owning the `Firm` record is not access to what is inside the Firm. Operator-assisted onboarding and support access run **only** through IdentityAccess's `PrivilegedAccessGrant`, per `docs/adr/ADR-014-Operator-Assisted-Onboarding-and-Privileged-Access.md`; **no second operator access path exists.**

14. **Architecture approval does not schedule implementation.** EPIC-012 is recorded as **proposed, not scheduled**; every story is **Backlog**. **`PF-040` remains the next code story and remains Backlog.** No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled.

## Domain ownership

| Concept | Owner | `PlatformAdministration`'s relationship |
|---|---|---|
| `Firm`, `FirmProvisioning`, `SubscriptionEntitlement` | **`PlatformAdministration`** | Owner — and owns nothing else |
| Principals, `FirmMembership`, credentials, authenticators, MFA, sessions, recovery, invitations, capabilities, privileged-access grants, security events | **IdentityAccess** (ADR-009) | References `FirmId`; consumes entitlement state; `PlatformAdministration` authenticates nothing |
| `FirmContext`, tenant resolution, tenant middleware | **Platform Foundation** (PF-080/PF-081/PF-082) | Consumes verified identity and membership; `PlatformAdministration` resolves no tenancy |
| Invoices, payments, ledgers, client money, tax treatment, financial classification | **Billing** (ADR-008) | **Not shared, not duplicated, not approximated** — a different commercial relationship entirely |
| `Money`, `Currency` | **Foundation `PF-045`** (Backlog, deferred) | Never consumed — no `PlatformAdministration` record carries a monetary value |
| `Client`, `Matter`, `MatterClient`, `MatterTeam`, `Task`, `EthicalWall`, `ConflictRelationship` | **Practice Management** (ADR-006) | Never read, stored, derived, aggregated, or exported |
| `BrandProfile`, `TenantDomain`, theme tokens | **Branding** (ADR-003) | Presupposes a `Firm`; `PlatformAdministration` owns no branding or presentation |
| Firm tenant websites, Client Portal | **Digital Presence** (ADR-005) | Unrelated; the operator marketing website is not a Digital Presence surface |
| Public API, webhooks, installations | **Integrations** (ADR-010) | No external contract, installation, or webhook is defined here |
| AI authority | **Nobody — AI is never an authorization authority** | No AI capability exists in this context |

## Alternatives considered

- **Give `Firm` to IdentityAccess.** Rejected — ARCH-016 §4 already draws IdentityAccess's boundary deliberately, and adding the tenant record would make the identity context the owner of the platform's commercial relationship as well. Its own reasoning against absorbing adjacent concerns applies here unchanged.
- **Give `Firm` to Platform Foundation.** Rejected for the reason ARCH-016 §2 already gives: Foundation owns *technical primitives*, not a business-governed lifecycle with provisioning, entitlement, and authorized state transitions. `FirmContext` carrying a Firm ID is not ownership of the Firm.
- **Give `Firm` to Practice Management.** Rejected — Practice Management is the Core Domain for a Firm's *legal work*. Making the Core Domain own the tenant and its subscription inverts the dependency: every other context would depend on Practice Management merely to know a Firm exists.
- **Give `SubscriptionEntitlement` to Billing.** Rejected — Billing's three financial areas are all about the Firm's relationship with *its clients* and its own accounts. Operator-to-Firm entitlement is a fourth, unrelated commercial relationship; putting it in Billing would mean Billing's first delivered capability is not a Firm capability at all, and would drag `PF-045 Money`, tax treatment, and invoice numbering into a record that needs none of them.
- **Put an amount, price, or plan tier on `SubscriptionEntitlement`.** Rejected — it would require `Money`/`Currency` (deferred), create a second monetary type in violation of `AGENTS.md`, and convert an entitlement record into a financial record subject to Article 22–25 discipline it is not designed to satisfy. Term, status, and seat limit are sufficient to enforce what was sold.
- **Build in-application subscription billing, checkout, or payment.** Rejected — outside Release 0.1 by explicit business direction, duplicative of Billing's ownership, and regulated-adjacent surface for no pilot benefit. Manual external contracting stays manual and external.
- **Let `PlatformAdministration` authenticate operators and grant Firm access.** Rejected — it would create a second authentication authority and a second privileged-access path, directly contradicting Constitution Article 26 and ADR-009 Decision 1. Owning the tenant record must not become owning the door.
- **Terminate live sessions the moment a subscription lapses.** Rejected — a commercial lapse is not a security event. Immediate termination would conflate it with revocation, produce a misleading security audit trail, and inflict disproportionate work loss for an administrative condition. Blocking *new* authentication is the proportionate control, and membership revocation remains available and immediate when the event genuinely is a security one.
- **Let a seat-limit reduction auto-revoke the most recent members.** Rejected — silently removing a lawyer's access to live matters based on an administrative counter is unacceptable; the over-limit condition is surfaced for an authorized human to resolve.
- **Let `PlatformAdministration` grow into a general platform back office** (support tooling, reporting, feature flags, reseller hierarchies). Rejected **for this context** — that is how a narrow supporting context becomes the catch-all module `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §3 warns against. **Each such capability requires its own separately approved architecture; none is declared globally impossible here**, and cross-Firm reporting specifically belongs to the future Reporting bounded context Constitution Article 44 already reserves — which this ADR neither approves nor permanently prohibits.

## Consequences

- The platform gains a single, unambiguous owner for `Firm`, which every other context already assumed existed. Nothing has to move to get it.
- `PlatformAdministration` is deliberately the smallest bounded context on the platform. That is a maintenance benefit and a standing constraint: any proposal to add a fourth concept to it needs its own ADR.
- Entitlement enforcement introduces a synchronous dependency from IdentityAccess to `PlatformAdministration` on the authentication path. It is a small, cacheable, Firm-scoped read — but it is on a critical path, and its failure behaviour is fail-closed (below), which means a `PlatformAdministration` outage prevents new logins.
- Keeping entitlement lapse and membership revocation semantically separate costs two code paths and two event types where one would appear simpler. That separation is the point: it keeps the security audit record truthful about what actually happened.
- Because `SubscriptionEntitlement` carries no monetary value, the application can never answer "how much does this Firm pay?" That is intended. The commercial record lives outside the application.
- Deferring `PF-045 Money` remains tenable only while Decision 4 holds. Any future proposal to put an amount here reopens `PF-045` scheduling as a hard prerequisite.

## Security and professional-responsibility consequences

- **Owning the `Firm` record confers no access to Firm data.** `PlatformAdministration` is not an authorization authority and holds no standing access to any Firm's `Client`, `Matter`, `Task`, or activity history, nor to IdentityAccess's security-event content. It owns its own administrative audit facts and no other context's (Decision 12).
- **`FirmContext` is still constructed only from verified identity and membership** (Constitution Article 27). A `Firm` record's existence, a Firm identifier in a request, a hostname, or an email domain proves nothing.
- **Successful entitlement is not authentication, and successful authentication is not authorization** (Constitution Article 28). Entitlement is a **gate on session issuance**, not a term in the per-request authorization composition, and it never grants resource access (Decision 8).
- **Entitlement is evaluated only after credential and required MFA verification, and before session issuance.** That ordering is a confidentiality control, not an implementation preference: checking earlier would let an unauthenticated caller learn whether a Firm exists or whether its subscription is active. **Failure responses remain enumeration-resistant in text, response shape, and practicable timing**, and any operational instruction given to a verified user reveals nothing about another Firm or account.
- **Entitlement decisions fail closed.** When entitlement state cannot be authoritatively determined, **no session is issued**, per `docs/architecture/04_Security_Architecture.md` §8 — and the same applies to session renewal, reauthentication, and any new Firm-bound session, each of which is a fresh authentication decision. An already-issued session continues only within its own validity and only where its revocation requirements remain satisfied. Availability never outranks Firm isolation or authorization.
- **No stale positive entitlement survives** a status change; entitlement caches are version-aware and invalidated promptly, on the same discipline as authorization caches.
- **Denied callers receive no existence confirmation.** Whether a Firm exists, whether it has an entitlement, and whether that entitlement has lapsed are not disclosed to an unauthenticated or unauthorized caller — including through timing, error text, or account-discovery responses (Constitution Article 27, ARCH-016 §42).
- **Every provisioning, entitlement, and seat-limit change is an append-only, actor-attributed audit event** recording the acting identity, the Firm, the change, and its authorization. Correction is a new record, never a rewrite.
- **Seat limits are an administrative control, never a professional-responsibility control.** Being within a seat limit says nothing about qualification, authority, Matter access, or any future Ethical Wall outcome.
- **No credential, secret, session token, or recovery material is stored, logged, or carried in any `PlatformAdministration` record or event.**
- **No AI capability exists in this context**, and AI is never an entitlement, provisioning, seat-limit, or authorization authority.
- **No certification or compliance claim is made.**

## Integration consequences

- **IdentityAccess** consumes a published entitlement query and enforces it at authentication and membership activation. It gains no ownership of `Firm`, and `PlatformAdministration` gains no identity authority. Membership, capability, and domain authorization are unchanged.
- **Platform Foundation** (`PF-080`/`PF-081`/`PF-082`) continues to build `FirmContext` from verified identity and membership. It consumes a `FirmId`, never a `PlatformAdministration` record, and identity ownership does not move into Foundation.
- **Practice Management** is unaffected. Its aggregates remain Firm-scoped by `FirmContext`; it never reads entitlement, and `PlatformAdministration` never reads its data.
- **Billing** is unaffected and unshared. When EPIC-008 is built, it owns Firm-to-client invoices, payments, ledgers, and client money exactly as `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md` defines; nothing here duplicates or pre-empts it.
- **Branding** presupposes a `Firm` and gains a defined owner for it; no branding, theme, domain, or presentation concern enters `PlatformAdministration`.
- **Integrations** defines no external contract, installation, or webhook for `PlatformAdministration` in Release 0.1; any future external entitlement contract is Integrations' to publish, under its own approval gates.
- **`Workflow` and the AI Copilot** have no relationship to this context. Provisioning and entitlement changes are human commands.

## Explicit non-goals

This ADR does **not**: implement anything; create modules, source files, schemas, migrations, or tests; define pricing, plans, tiers, discounts, proration, trials, renewals, dunning, tax, or invoicing; introduce `Money`, `Currency`, or any monetary field; schedule or unblock `PF-045`; create a payment, checkout, or provider integration; select a vendor, provider, package, or platform; create a platform back office, support console, feature-flag system, reseller or partner hierarchy, or usage metering — **each of which would require its own separately approved architecture**; own or perform cross-Firm reporting, analytics, benchmarking, or comparison, which is not this context's and which the future Reporting bounded context reserved by Constitution Article 44 would need its own approved architecture to provide — **this ADR neither approves nor permanently prohibits that context**; define a Firm-level suspension or emergency-disable capability; grant operators any access to Firm data; create a second authentication, authorization, or privileged-access path; alter Billing's, IdentityAccess's, Practice Management's, Branding's, Digital Presence's, Integrations', or `Workflow`'s ownership; weaken or create an exception to Constitution Articles 1–44; introduce an AI capability; claim any certification or compliance; schedule EPIC-012; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

This ADR and `docs/architecture/19_Platform_Administration_Architecture.md` are **Accepted conceptual architecture only**. They authorize no application code, schema, migration, dependency, package, infrastructure, Docker change, CI change, environment change, production configuration, or GitHub setting. **No capability described here is claimed to be implemented.**

EPIC-012 is recorded as **proposed, not scheduled** in `docs/architecture/08_Roadmap.md`; every story is **Backlog** and requires its own approved entry and Definition of Ready. **`PF-040` — AggregateRoot remains the next code story and remains Backlog. No story is In Progress.**

The story ID is **ARCH-011** while the new sequential architecture document is numbered **19** (`ARCH-019`), continuing the established distinction between story numbering and architecture-document numbering.
