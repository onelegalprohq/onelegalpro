# OneLegalPro Product Requirements

**Status:** **Approved release-scoped product requirements.** Populated by **ARCH-011 — Release 0.1 Rescope** and approved by explicit owner approval recorded on PR #30 on 29 July 2026; see `docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md`. Approval schedules no implementation.

**Scope relationship.** This document records the **Release 0.1 product scope only**. It is not a full product specification for OneLegalPro, and it does not describe, schedule, or supersede any epic in `docs/architecture/08_Roadmap.md`. Where it appears to differ from an approved architecture document, the architecture document governs — this file records *what the first release contains*, never *what the platform is*.

**No capability described here is implemented, and no production-readiness, certification, or compliance claim is made.** Implementation stories are **proposed, not scheduled**; **`PF-040` — AggregateRoot remains the next repository implementation story and remains Backlog.**

## 1. Release 0.1 — the OneLegalPro Matter Desk

Release 0.1 is a **founding-firm pilot, not a general product launch**. It delivers a Firm-scoped desk on which authenticated firm staff manage Clients, Matters, and Tasks under an enforced subscription entitlement, with actor-attributed audit history.

It is deliberately narrower than any single approved epic, and it is a **slice across four contexts, not a new architecture**: a minimum Platform Runtime from EPIC-001, `Firm`/`FirmProvisioning`/`SubscriptionEntitlement` from `PlatformAdministration` (EPIC-012), identity and access from EPIC-009, and `Client`/`Matter`/`Task` from EPIC-006. **No approved ownership boundary moves.**

**Targets, not commitments.** Public website and sales readiness targets **31 August 2026**. Planned secure production service start targets **15 September 2026**, subject to §8.

## 2. In scope

| # | Capability | Owning context |
|---|---|---|
| 1 | Minimum Platform Runtime — `FirmContext`, tenant resolution, tenant middleware; transactional outbox and its genuine prerequisites | Platform Foundation (EPIC-001) |
| 2 | `Firm` and `FirmProvisioning` — `Requested → Provisioned → Active → Closed`, with **no Firm-level suspended state** | `PlatformAdministration` (EPIC-012) |
| 3 | `SubscriptionEntitlement` — term, status, seat limit; **no monetary value of any kind** | `PlatformAdministration` (EPIC-012) |
| 4 | Secure invitation | IdentityAccess (EPIC-009) |
| 5 | Password authentication | IdentityAccess (EPIC-009) |
| 6 | Mandatory MFA and recovery | IdentityAccess (EPIC-009) |
| 7 | Firm-bound sessions | IdentityAccess (EPIC-009) |
| 8 | Firm membership, with **per-principal** suspension and revocation | IdentityAccess (EPIC-009) |
| 9 | Firm Admin and Member capability bundles | IdentityAccess (EPIC-009) |
| 10 | Subscription term, status, and seat-limit enforcement — at the **authentication / session-issuance** gate and the **membership-activation** gate only (§5) | IdentityAccess (EPIC-009), consuming EPIC-012 |
| 11 | Secure operator-assisted onboarding and recovery, with Firm-visible support-access history | IdentityAccess (EPIC-009) |
| 12 | Client management | Practice Management (EPIC-006) |
| 13 | Matter management with the reduced lifecycle (§4) | Practice Management (EPIC-006) |
| 14 | Plural `MatterClient` | Practice Management (EPIC-006) |
| 15 | Responsible-lawyer assignment | Practice Management (EPIC-006) |
| 16 | Manual conflict attestation | Practice Management (EPIC-006) |
| 17 | Task and deadline management | Practice Management (EPIC-006) |
| 18 | Firm Worklist and filtering | Practice Management (EPIC-006) |
| 19 | Actor-attributed audit / activity history | Cross-cutting |
| 20 | Firm-scoped export | Cross-cutting |
| 21 | Thai text correctness — encoding, collation, normalization, sorting, rendering | Cross-cutting |
| 22 | Backups and an **executed** restore test; monitoring; documented incident procedure | Operations — not application code |
| 23 | Public operator website, inquiry form, Privacy Notice, Terms, pilot agreement | Operator surfaces — outside `app/Modules` |

## 3. Out of scope — deferred, not cancelled

Automated billing, payments, invoicing, and trust/client-money accounting (EPIC-008 remains sole owner when built) · Documents and Knowledge Management (EPIC-007) · every AI capability, including the Copilot (EPIC-011) · Client Portal, Firm tenant websites, booking, embedded widgets (EPIC-005) · Communications Hub (EPIC-004) · Workflow orchestration and automation policy (EPIC-011) · Legal Intelligence content, legal search, citation, translation surfaces (EPIC-002) · Public API, webhooks, connectors, service principals (EPIC-010) · Branding `BrandProfile`, assets, custom domains, sender identity, AI persona (EPIC-003) · **Ethical Walls and automated conflict checking** (§6) · `Organization`, `Contact`, `Appointment`, `Note`, `PracticeArea`, Matter Timeline, Matter Dashboard (EPIC-006 later stages) · automated reminders and notifications · document storage of any kind · delegation, segregation of duties, federation/SSO/SCIM, break-glass.

**Manual external contracting, invoicing, and payment remain outside the application entirely.** The application records and enforces entitlement; it never collects, calculates, stores, transmits, or displays an amount, price, invoice, payment, or tax figure. `PF-045` — Money and `PF-046` — Result remain **Backlog and deferred from Release 0.1**.

## 4. Matter lifecycle — reduced state subset, invariants preserved

```text
Prospective → Opened → Active → Closed
Prospective, Opened → Cancelled (terminal)
```

`Paused`, `Awaiting Client`, `Awaiting Court`, and `Archived` are **deferred, not removed**; they remain approved states in `docs/architecture/13_Practice_Management_Architecture.md` §3 and re-enter additively.

**Every invariant is preserved unchanged:**

- a conflict-attestation outcome must exist before `Opened` (§6);
- `MatterNumber` is immutable from `Opened`;
- exactly one Primary `MatterClient` at `Opened`, and never absent afterwards;
- every transition is an explicit, human-authorized command emitting `MatterStatusChanged`, never inferred from other activity.

**One Firm-wide Matter numbering scheme** is used, because `PracticeArea` is out of scope and the approved multi-scheme rule selects per Matter by `PracticeArea` or another firm-configured rule. Firm-configurability, per-Firm uniqueness at assignment, concurrency-safe assignment, and immutability from `Opened` are all preserved; only parallel multi-scheme selection is deferred.

## 5. Entitlement, membership, and seat limits

- **`Firm` lifecycle is `Requested → Provisioned → Active → Closed`. There is no Firm-level suspended state**, and neither suspension meaning below is a Firm-wide disable. **Any future Firm-level suspension or emergency-disable capability requires its own separately approved decision** defining the authorizing authority, session effects, recovery, notification, queued-and-in-flight-work behaviour, and audit semantics.
- **Expired or suspended `SubscriptionEntitlement` — a commercial/administrative fact — blocks new authentication.** Existing valid sessions continue **only to their normal expiry**. `Suspended` here is a state of the **entitlement**, never of the `Firm`.
- **Firm membership suspension or revocation is a distinct, individual security event** and terminates **that principal's** sessions **immediately**.
- The two **never share code, audit meaning, or policy semantics**.
- **Seat limits are enforced at membership activation**, deterministically, and **never retroactively**. A seat limit reduced below the active count never silently revokes anyone; the condition is surfaced for an authorized human.
- **Entitlement is evaluated at exactly two gates.** At **authentication**, only after successful credential and any required MFA verification and **before a session is issued** — checking earlier would let an unauthenticated caller learn whether a Firm exists or whether its subscription is active. And at **membership activation**, for the seat limit.
- **Failure responses are enumeration-resistant in text, response shape, and practicable timing.** A **verified** user may receive the minimum safe operational instruction needed to seek help; it reveals nothing about any other Firm or account.
- **Entitlement is never a per-request resource-authorization input for an already-issued, valid session** — that session runs to its normal expiry by policy. **Session renewal, reauthentication, and any new Firm-bound session are new authentication decisions and re-check entitlement.**
- Entitlement grants **no resource access** by itself and is **not a term in the Constitution Article 28 authorization composition**, which remains owned by IdentityAccess and the domain modules.

## 6. Absent professional-responsibility controls and mandatory disclosures

Per `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`:

- **Ethical Walls are absent and never stubbed, approximated, simulated, partially implemented, or renamed.** Constitution Article 17 remains fully in force. **Every Firm member with Worklist access can see every Matter in the Firm.**
- **Automated conflict checking is absent and never approximated** — no matching, scoring, party graph, or detection of any kind.
- **Manual conflict attestation** satisfies the `Opened` gate as an actor-attributed, timestamped, append-only record of a **human determination**: attesting actor, timestamp, Matter, outcome (clear, or proceeding with recorded justification), and mandatory justification where not clear. An unrecorded checkbox, default-true field, or silent-pass path is prohibited.
- **It must never be described, labelled, presented, documented, or marketed as a conflict check performed by the system.** No surface may state or imply that OneLegalPro searched for, detected, screened for, cleared, or found no conflicts.

**Four absences are disclosed explicitly, in-product where reliance would occur and in the pilot agreement:** Ethical Walls · automated conflict checking · **automated reminders and notifications** (deadlines are recorded and displayed; nothing notifies, reminds, escalates, or alerts) · **document storage** (no document, file, attachment, or upload capability exists).

## 7. Presentation and operator surfaces

- **Temporary, bounded white-label posture.** The Firm-facing interface is neutral and token-driven. **No OneLegalPro brand value is hardcoded in any Firm-facing template or component**, and every presentation value resolves through **one replaceable indirection**. Disclosed in the pilot agreement; **ends when EPIC-003 delivers the `BrandProfile` foundation.** Constitution Articles 10–11 are **not waived**.
- **The OneLegalPro marketing website is the operator's own surface**, categorically distinct from a Firm tenant website or the Client Portal (both EPIC-005, out of scope). It sits **outside the Digital Presence bounded context and outside `app/Modules`**, creates **no Client Portal principal, no `ClientPortalAccessProfile`, and no Firm membership**, and does not weaken the Firm-facing rules above.

## 8. Readiness dependencies

**External Thai-qualified legal review is mandatory** for the Privacy Notice, Terms, pilot agreement, and the disclosures in §6. Until that review and the owner's approval are recorded, **those documents and all marketing and security copy are draft**. ARCH-012's eight database-and-persistence legal questions have received Thai-qualified review and owner-approved decisions, recorded separately in `docs/legal/ARCH-012-Thai-Legal-Review.md`; that limited review does **not** complete review of the Privacy Notice, Terms, pilot agreement, disclosures, or marketing and security copy, and no legal conclusion is asserted in this document.

**PostgreSQL continuous integration must land as its own approved story before `PF-080` begins.** The four required `Protect main` check names — `PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit` — are preserved exactly.

**15 September 2026 is a target, not a contractual commitment.** Production access requires recorded evidence of: an approved database design (`docs/architecture/03_Database_Design.md` is populated by proposed ARCH-012 but is not approved until repository-owner approval is recorded); an approved deployment architecture; an **executed and recorded** restore test; operational monitoring; a documented incident procedure; completed applicable Thai-qualified legal review; and every applicable approval gate in `AGENTS.md`.

## 9. Non-negotiable requirements

These apply to Release 0.1 in full and are **not schedule variables**:

- **Firm isolation** — `FirmContext` built only from verified identity and membership, never from a hostname, custom domain, header, parameter, route, cookie, or email address; enforced in application logic, repositories, and database policy, never by global scopes alone.
- **Authorize before retrieval** — authorization is composed and evaluated before retrieval, search, filtering, aggregation, and export, never after. The Worklist and Firm-scoped export receive no weaker treatment than an interactive read.
- **Denied-existence confidentiality** — a denied caller receives no content, metadata, count, aggregate, search hit, or existence confirmation.
- **Actor attribution** — every recorded action names an accountable actor; human, system, and operator actors remain distinguishable.
- **Human approval** — Matter status transitions, responsible-lawyer assignment, and conflict attestation are human commands, never inferred and never automated.
- **Immutable audit** — append-only; correction is a new record, never a rewrite; audit is not editable by the actor being audited.
- **Fail closed** — new authentication and high-risk operations are refused when the authoritative decision is unavailable. Availability never outranks Firm isolation or authorization.
- **Thai legal authority rules preserved** — official Thai-language text is the authoritative legal source, translations are never authoritative, and the mandatory disclaimer rules remain in force (Constitution Articles 1–4). **Release 0.1 renders no legal source text and no translated legal provision**, creates no disclaimer surface, and makes no legal-content claim of any kind.
- **No AI capability exists in Release 0.1** — nothing for AI to do, propose, retrieve, or approve. Adding one requires its own approval gate.

## 10. Relationship to other documents

| Document | Relationship |
|---|---|
| `docs/architecture/01_OneLegalPro_Constitution.md` | Constitutional; prevails over this document in every case |
| `docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md` | The decision record that populated this document |
| `docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` | `Firm`, provisioning, and entitlement ownership |
| `docs/adr/ADR-014-Operator-Assisted-Onboarding-and-Privileged-Access.md` | Operator support access and its Firm-visible history |
| `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` | Absent Ethical Walls, absent automated conflict checking, disclosures |
| `docs/architecture/19_Platform_Administration_Architecture.md` | The `PlatformAdministration` bounded context |
| `docs/architecture/08_Roadmap.md` | Epic sequencing; EPIC-012 recorded as proposed, not scheduled |
| `docs/implementation/03_Engineering_Backlog.md` | Story-level record; every Release 0.1 story is **Backlog** |
| `docs/architecture/03_Database_Design.md` | Populated by **proposed ARCH-012**; not approved and authorizes nothing until repository-owner approval is recorded |
