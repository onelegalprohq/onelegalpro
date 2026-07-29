# ADR-012 — Release 0.1 Product Scope and Matter Desk Slice

## Status

**Proposed.** Awaiting explicit human review and approval. This ADR is **not Accepted** and authorizes no implementation.

## Context

Every architecture approved so far (ARCH-001 through ARCH-010) describes the platform OneLegalPro intends to become. None of them describes what the platform must be able to do *first*, for a paying firm, on a date. `docs/architecture/08_Roadmap.md` sequences eleven epics; `docs/implementation/01_Implementation_Sprint_Plan.md` schedules only `PF-*` Platform Foundation stories; and `docs/architecture/02_Product_Requirements.md` has been an empty reserved placeholder since ARCH-001.

The business direction is now explicit: OneLegalPro must become a sellable subscription SaaS product for lawyers, beginning with a narrow founding-firm pilot called the **OneLegalPro Matter Desk**. Public website and sales readiness targets **31 August 2026**; planned secure production service start targets **15 September 2026**.

Three structural problems follow from stating that as an objective rather than as architecture.

First, **an unstated scope is an unbounded scope.** Eleven approved epics are each individually justified. Without a recorded decision naming what Release 0.1 contains, every one of them is arguably in scope, and the first implementation story has no defensible boundary.

Second, **a narrow first release necessarily defers approved controls.** Ethical Walls (Constitution Article 17), automated conflict checking, Documents, Billing, the Client Portal, Communications, Workflow, and the AI Copilot are all approved architecture that Release 0.1 will not contain. Deferring an approved control without recording *that it is deferred, why, and what compensates* is indistinguishable, six months later, from having quietly weakened it.

Third, **a date creates pressure on exactly the rules that must not move.** Firm isolation, authorize-before-retrieval, denied-existence confidentiality, actor attribution, human approval, immutable audit, white-label presentation, and the Thai-language legal-authority rules are not schedule variables. A release scope that is honest about what it omits is the mechanism that keeps them fixed.

Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, this needs an approved architecture decision before any Release 0.1 implementation begins.

## Decision

1. **Release 0.1 is the OneLegalPro Matter Desk — a founding-firm pilot, not a general product launch.** It delivers a Firm-scoped desk for authenticated firm staff to manage Clients, Matters, and Tasks under an enforced subscription entitlement, with actor-attributed audit history. It is deliberately narrower than any single approved epic.

2. **Release 0.1 is a slice across four contexts, not a new architecture.** It draws a minimum Platform Runtime from EPIC-001, `Firm`/`FirmProvisioning`/`SubscriptionEntitlement` from the new `PlatformAdministration` context (`docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md`), identity and access from EPIC-009, and `Client`/`Matter`/`Task` from EPIC-006. **No approved ownership boundary moves.**

3. **Release 0.1 includes a dependency-complete minimum Platform Runtime.** `PF-080 Firm Context`, `PF-081 Tenant Resolver`, and `PF-082 Tenant Middleware` are **mandatory** — every "Firm-bound" capability below is meaningless without them, and `docs/domain/06_Laravel_Module_Blueprint.md` requires tenant isolation enforced in application logic, repositories, and database policy, never by global scopes alone. `PF-091 Transactional Outbox` and **only its genuine prerequisites** are included where a committed audit or event fact must be durable with the state change that produced it. Buses, publishers, consumers, and module-generation tooling not required by that analysis remain deferred. The prerequisite analysis is recorded in `docs/implementation/03_Engineering_Backlog.md`; **architecture approval of a runtime stage does not schedule it.**

4. **Release 0.1 uses a strict subset of the approved Matter lifecycle, with every invariant preserved.** The subset is:

   ```text
   Prospective → Opened → Active → Closed
   Prospective, Opened → Cancelled (terminal)
   ```

   `Paused`, `Awaiting Client`, `Awaiting Court`, and `Archived` are **deferred, not removed** — they remain approved states in `docs/architecture/13_Practice_Management_Architecture.md` §3 and are re-introduced additively. Every existing invariant is preserved unchanged: a conflict-attestation outcome must exist before `Opened`; `MatterNumber` is immutable from `Opened`; exactly one Primary `MatterClient` at `Opened` and never absent afterwards; every transition is an explicit, human-authorized command emitting `MatterStatusChanged`, never inferred from other activity.

5. **Release 0.1 uses one Firm-wide Matter numbering scheme,** because `PracticeArea` is excluded from Release 0.1 and the approved multi-scheme selection rule (`docs/architecture/13_Practice_Management_Architecture.md` §4) selects a scheme per Matter by `PracticeArea` or another firm-configured rule. Firm-configurability, per-Firm uniqueness at assignment, concurrency-safe assignment, and immutability from `Opened` are all preserved; only parallel multi-scheme selection is deferred, and it re-enters additively with `PracticeArea`.

6. **Release 0.1 carries a temporary, bounded white-label posture — a deferral, never a waiver of Constitution Articles 10–11.** The Firm-facing interface is neutral and token-driven. **No OneLegalPro brand value is hardcoded in any Firm-facing template or component**, and every presentation value resolves through **one replaceable indirection**, so that EPIC-003's `BrandProfile` foundation and Branding Resolver replace the indirection rather than rewriting the surfaces. The posture is disclosed in the pilot agreement and **ends when EPIC-003 delivers the `BrandProfile` foundation.** Per-Firm brand resolution, assets, custom domains, sender identity, and AI persona configuration are not delivered in Release 0.1.

7. **The OneLegalPro marketing website is the platform operator's own surface and is categorically distinct from a Firm tenant website.** A Firm tenant website and the Client Portal are owned by Digital Presence (`docs/architecture/12_Website_Client_Portal_Architecture.md`, EPIC-005) and are **excluded from Release 0.1**. The operator marketing website and its inquiry form sit **outside the Digital Presence bounded context and outside `app/Modules`**, create **no Client Portal principal**, no `ClientPortalAccessProfile`, and no Firm membership, and **do not weaken or reinterpret the Firm-facing white-label rules** in Decision 6 — Constitution Article 10 governs Firm-facing and client-facing surfaces, and "OneLegalPro" remains the operator's own identity there.

8. **External Thai-qualified legal review is a mandatory readiness dependency.** The Privacy Notice, Terms, pilot agreement, and the disclosures concerning absent Ethical Walls and manual conflict attestation (`docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`) all require review by a Thai-qualified lawyer before publication or execution. Until that review and the owner's approval are recorded, **every one of those documents, and all marketing and security copy, is draft.** This ADR records the dependency only: **no reviewer is engaged, no review has occurred, and no legal conclusion is asserted here.**

9. **PostgreSQL continuous integration must land as its own approved story before `PF-080` begins.** The suite currently runs against SQLite `:memory:` per `phpunit.xml`, while `AGENTS.md` and `docs/implementation/02_AI_Developer_Playbook.md` make PostgreSQL authoritative — and Firm isolation enforced at the database-policy layer cannot be honestly tested on SQLite. **The four required `Protect main` check names — `PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit` — are preserved exactly.** No CI change is made by ARCH-011.

10. **15 September 2026 is a target, not a contractual commitment.** Production access requires recorded evidence of: an approved database design (`docs/architecture/03_Database_Design.md` is an empty placeholder and is **not populated by this story**); an approved deployment architecture; an **executed and recorded** restore test; operational monitoring; a documented incident procedure; completed Thai-qualified legal review (Decision 8); and every applicable security and authorization approval gate in `AGENTS.md`. **No production readiness, certification, or compliance claim is made by this ADR or by any Release 0.1 document.**

11. **Architecture approval does not schedule implementation.** EPIC-012 is recorded as **proposed, not scheduled**. Every Release 0.1 story is **Backlog** and requires its own approved entry and Definition of Ready in `docs/implementation/03_Engineering_Backlog.md`. **`PF-040` — AggregateRoot remains the next code story and remains Backlog**; `PF-045` — Money and `PF-046` — Result remain **Backlog and deferred from Release 0.1**. No `PF-*` story is renamed, renumbered, merged, split, or deleted, and **no story becomes In Progress because planning exists.**

## Release 0.1 capability scope

**In scope**

| Capability | Owning context |
|---|---|
| Minimum Platform Runtime — `FirmContext`, tenant resolution, tenant middleware; transactional outbox and its genuine prerequisites | Platform Foundation (EPIC-001) |
| `Firm`, `FirmProvisioning`, `SubscriptionEntitlement` — subscription term, status, seat limit | `PlatformAdministration` (EPIC-012) |
| Secure invitation; password authentication; mandatory MFA and recovery; Firm-bound sessions | IdentityAccess (EPIC-009) |
| Firm membership, suspension, revocation; Firm Admin and Member capability bundles; seat-limit enforcement at membership activation | IdentityAccess (EPIC-009) |
| Secure operator-assisted onboarding and recovery, with Firm-visible support-access history | IdentityAccess (EPIC-009), per `docs/adr/ADR-014-Operator-Assisted-Onboarding-and-Privileged-Access.md` |
| Client management; Matter management; plural `MatterClient`; responsible-lawyer assignment; reduced Matter lifecycle; manual conflict attestation; Task and deadline management; Firm Worklist and filtering | Practice Management (EPIC-006) |
| Actor-attributed audit/activity history; Firm-scoped export | Cross-cutting |
| Thai text correctness — encoding, collation, normalization, sorting, and rendering | Cross-cutting |
| Backups and an **executed** restore test; monitoring; documented incident procedure | Operations (not application code) |
| Public operator website, inquiry form, Privacy Notice, Terms, pilot agreement | Operator surfaces, outside `app/Modules` |

**Out of scope — deferred, not cancelled**

Automated billing, payments, invoicing, and trust/client-money accounting (Billing, EPIC-008, remains the sole owner when built); Documents and Knowledge Management (EPIC-007); every AI capability, including the Copilot (EPIC-011) and all AI Architecture surfaces; the Client Portal, Firm tenant websites, booking, and embedded widgets (EPIC-005); the Communications Hub (EPIC-004); Workflow orchestration and automation policy (EPIC-011); Legal Intelligence content, legal search, citation, and translation surfaces (EPIC-002); the Public API, webhooks, connectors, and service principals (EPIC-010); Branding's `BrandProfile`, assets, custom domains, sender identity, and AI persona (EPIC-003); **Ethical Walls and automated conflict checking** (`docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`); `Organization`, `Contact`, `Appointment`, `Note`, `PracticeArea`, Matter Timeline, and Matter Dashboard (EPIC-006 later stages); automated reminders and notifications; document storage of any kind; delegation, segregation of duties, federation/SSO/SCIM, and break-glass.

**Manual external contracting, invoicing, and payment remain outside the application entirely.** The application records and enforces `SubscriptionEntitlement`; it never collects, calculates, stores, or transmits an amount, price, invoice, payment, or tax figure.

## Domain ownership

| Concept | Owner | Release 0.1 relationship |
|---|---|---|
| `Firm`, `FirmProvisioning`, `SubscriptionEntitlement` | **`PlatformAdministration`** (ADR-013) | New, narrowly bounded supporting context |
| Principals, membership, credentials, MFA, sessions, recovery, invitations, capabilities, privileged-access grants | **IdentityAccess** (ADR-009) | Unchanged owner; evaluates entitlement and seat limits at the **session-issuance** and **membership-activation** gates only |
| `Client`, `Matter`, `MatterClient` and `MatterTeam` as Matter-owned entities, `Task` as an independent aggregate referencing `Matter` | **Practice Management** (ADR-006) | Unchanged owner and unchanged aggregate shape |
| `FirmContext`, tenant resolution, tenant middleware | **Platform Foundation** (PF-080/PF-081/PF-082) | Mandatory in Release 0.1; consumes verified identity and membership, resolves neither |
| Invoices, payments, ledgers, client money, tax, `Money`/`Currency` | **Billing** (ADR-008) and **Foundation `PF-045`** | Not built in Release 0.1; ownership unchanged and unshared |
| `EthicalWall`, `ConflictRelationship`, `SearchConflicts` | **Practice Management** (ADR-006) | Absent from Release 0.1, never stubbed or approximated (ADR-015) |
| Firm tenant websites, Client Portal, widgets | **Digital Presence** (ADR-005) | Excluded; the operator marketing website is not this |
| `BrandProfile`, Branding Resolver, custom domains | **Branding** (ADR-003) | Deferred; Release 0.1 uses one replaceable presentation indirection |
| AI authority of any kind | **Nobody — no AI capability exists in Release 0.1** | No model, provider, retrieval path, or Copilot surface |

## Alternatives considered

- **Ship no explicit release scope and let the backlog imply it.** Rejected — an implied scope cannot be reviewed, cannot be defended when a date applies pressure, and gives no basis for saying a story is out of scope.
- **Deliver a full epic (EPIC-006 or EPIC-009) as Release 0.1.** Rejected — each is far larger than a founding-firm pilot needs, and both depend on capabilities the other has not yet delivered; a vertical slice reaches a usable product sooner and exercises the cross-context boundaries earlier, when correcting them is cheap.
- **Defer `PF-080`/`PF-081`/`PF-082` and enforce Firm isolation inside module code.** Rejected — `docs/domain/06_Laravel_Module_Blueprint.md` requires an explicit `FirmContext` carrying Firm ID, Actor ID, membership, and correlation ID, enforced in application logic, repositories, and database policy. Per-module isolation is exactly the drift that produces one Firm's matter data in another Firm's session.
- **Include the full approved Matter lifecycle.** Rejected — `Paused`, `Awaiting Client`, and `Awaiting Court` each require recorded entry/exit reasons and return semantics, and `Archived` requires retention rules that depend on unbuilt Documents and retention architecture. The subset is additive to extend and preserves every invariant.
- **Reduce the Matter invariants along with the lifecycle.** Rejected outright — the conflict-attestation gate, `MatterNumber` immutability, the Primary `MatterClient` rule, and explicit human authorization of transitions are professional-responsibility controls, not lifecycle conveniences. A smaller state machine does not license a weaker one.
- **Hardcode OneLegalPro branding in Firm-facing surfaces "just for the pilot."** Rejected — Constitution Article 10 admits no pilot exemption, and retrofitting brand indirection across an existing UI is materially harder than building it once. One replaceable indirection costs almost nothing now and is the entire migration path to EPIC-003.
- **Treat the operator marketing website as an early Digital Presence delivery.** Rejected — it would silently start EPIC-005, whose Firm-website and Client-Portal capabilities depend on Branding and Communications foundations that do not exist, and would blur the one distinction that keeps white-label coherent: the operator's identity is not a Firm's.
- **Build a minimal in-app subscription billing surface.** Rejected — it would duplicate Billing's ownership (Constitution Article 22), require `PF-045 Money`, and convert a manual commercial arrangement into regulated-adjacent product surface for no pilot benefit.
- **Ship a simplified or advisory Ethical Wall.** Rejected in `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` — an approximated wall is worse than a disclosed absent one, because it invites reliance it cannot support.
- **Commit contractually to 15 September 2026.** Rejected — production access depends on evidence that does not yet exist, including an approved database design, an approved deployment architecture, an executed restore test, and completed Thai-qualified legal review. A target is honest; a commitment would not be.
- **Keep running CI on SQLite through Release 0.1.** Rejected — PostgreSQL is authoritative per `AGENTS.md`, and database-policy-level Firm isolation cannot be tested on a different engine. Scheduled as its own story before `PF-080`, with the four required check names preserved.

## Consequences

- Release 0.1 is deliverable by a small team, but it is **visibly incomplete as a legal practice product**. It has no documents, no billing, no communications, no client portal, no reminders, and no conflict search. That must be stated plainly to the pilot firm rather than implied away.
- A cross-context slice touches four ownership boundaries early. Getting `PlatformAdministration → IdentityAccess → Practice Management` dependency direction right in Release 0.1 is cheap; getting it wrong is expensive to unwind after data exists.
- The minimum Platform Runtime is genuinely minimum. Some later module work will be blocked until further Sprint 0.4 stages land, and that is preferable to building buses and consumers no Release 0.1 capability uses.
- The temporary white-label posture means the pilot firm sees an unbranded product. This is a real product limitation, disclosed, with a defined end condition.
- Deferring `PF-045 Money` and `PF-046 Result` is only tenable because `SubscriptionEntitlement` carries no monetary value (ADR-013) and Foundation primitives throw exceptions rather than returning `Result` (`app/Foundation/README.md`). Any request to put an amount in Release 0.1 reopens both.
- Recording the Release 0.1 scope in `docs/architecture/02_Product_Requirements.md` ends that file's status as an empty placeholder. It becomes a **release-scoped** product-requirements record, not a full product specification.

## Security and professional-responsibility consequences

- **Every Firm-isolation, authorization, audit, and human-approval rule in `AGENTS.md` and Constitution Articles 1–44 applies to Release 0.1 in full.** Nothing in this ADR weakens, narrows, reinterprets, or creates an exception to any of them.
- **Firm isolation is enforced through `FirmContext` built only from verified identity and membership** — never from a hostname, header, parameter, route, cookie, or email address (Constitution Article 27).
- **Authorization is composed and evaluated before retrieval, search, filtering, aggregation, and export** — never after. The Firm Worklist and Firm-scoped export receive no weaker treatment than an interactive read.
- **Denied callers receive no content, metadata, count, aggregate, search hit, or existence confirmation.** In a conflicts-sensitive domain, existence disclosure is disclosure.
- **Every recorded action is actor-attributed** and human, system, and operator actors remain distinguishable in audit. Audit records are append-only; correction is a new record, never a rewrite.
- **Legally significant actions require an accountable human.** Matter status transitions, responsible-lawyer assignment, and conflict attestation are human commands, never inferred and never automated.
- **No AI capability exists in Release 0.1**, so no AI-governance control is relaxed — there is nothing for AI to do, propose, retrieve, or approve. Any proposal to add one requires its own approval gate.
- **Official Thai-language legal text remains the authoritative legal source, translations are never authoritative, and the mandatory disclaimer rules remain in force** (Constitution Articles 1–4). Release 0.1 renders **no legal source text and no translated legal provision**, so it creates no disclaimer surface — and correspondingly makes **no legal-content claim of any kind**.
- **Ethical Walls and automated conflict checking are absent, disclosed, and never approximated** (`docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`). A Firm Worklist that shows every Matter to every member is precisely what a wall would restrict; the pilot firm must know that before it relies on the product.
- **No certification or compliance claim is made**, consistent with `docs/architecture/04_Security_Architecture.md` §9.

## Integration consequences

- **`PlatformAdministration`** publishes `Firm` identity and entitlement state; it never authenticates, never holds a credential or session, and never reaches into a Firm's business data (ADR-013).
- **IdentityAccess** consumes entitlement state at the **authentication / session-issuance** gate (only after credential and any required MFA verification, before a session is issued) and at the **membership-activation** gate — **never as a per-request resource-authorization input** (`docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` Decision 8). It remains the sole owner of credentials, sessions, membership, MFA, recovery, invitations, and privileged-access grants, and gains no new authority from Release 0.1.
- **Practice Management** consumes actor references and `FirmContext`; it owns `Client`, `Matter`, `MatterClient`, `MatterTeam`, and `Task` exactly as `docs/adr/ADR-006-Practice-Management-Core.md` already defines them, and remains the sole future authority for Ethical Walls and conflict checking.
- **Billing** is untouched. Nothing in Release 0.1 owns, duplicates, or approximates an invoice, payment, ledger entry, client-money balance, tax treatment, or monetary amount.
- **Branding** is untouched. Release 0.1 introduces one replaceable presentation indirection that EPIC-003 replaces; it defines no theme token schema and no `BrandProfile`.
- **Digital Presence** is untouched. The operator marketing website is not a Firm tenant website, not a Client Portal, and not a widget host.
- **Platform Foundation** delivers the minimum runtime Release 0.1 requires, under its existing `PF-*` numbering, neither renumbered nor rescheduled by this ADR.

## Explicit non-goals

This ADR does **not**: implement anything; create modules, source files, schemas, migrations, tests, CI configuration, deployment configuration, or production procedures; populate `docs/architecture/03_Database_Design.md`; select or endorse any vendor, provider, package, hosting platform, or deployment target; assert a legal conclusion, tax rule, or compliance position; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; engage or name a legal reviewer; change the four required `Protect main` check names; modify CI; weaken, rewrite, or create an exception to Constitution Articles 1–44; alter Billing's, Documents', Communications', Digital Presence's, Branding's, Integrations', or `Workflow`'s ownership; introduce an AI capability; schedule EPIC-012; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

This ADR is **conceptual architecture only** and is **Proposed, not Accepted**. It authorizes no application code, schema, migration, dependency, package, infrastructure, Docker change, CI change, environment change, production configuration, or GitHub setting. **No capability described here is claimed to be implemented.**

EPIC-012 — Platform Administration & Release 0.1 Matter Desk is recorded as **proposed, not scheduled** in `docs/architecture/08_Roadmap.md`; none of its stages carries an approved story ID or a Definition of Ready. **`PF-040` — AggregateRoot remains the next code story and remains Backlog**, requiring its own approved entry before implementation begins. **No story is In Progress.**

The story ID is **ARCH-011** while the new sequential architecture document is numbered **19** (`ARCH-019`), continuing the established distinction between story numbering and architecture-document numbering.
