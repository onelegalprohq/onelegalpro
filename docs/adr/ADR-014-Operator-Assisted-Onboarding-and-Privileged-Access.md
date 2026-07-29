# ADR-014 — Operator-Assisted Onboarding and Privileged Access

## Status

**Accepted.** Explicit owner approval recorded on PR #30 on 29 July 2026. Acceptance authorizes the architectural decision only; it does not authorize implementation, deployment, or production access.

## Context

Release 0.1 is a founding-firm pilot (`docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md`). A pilot firm will need help: getting the first Firm Admin authenticated, recovering an administrator who has lost their MFA factor, and understanding why something is not working. In a small pilot, the platform operator is the only available help.

That is exactly the situation Constitution Article 29 and `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §38–§40 anticipate and constrain: **platform operators hold no Firm-data access by default, silent impersonation is prohibited, support access requires an explicit, purpose-bound, time-limited grant with strong authentication, and Firms must be able to see appropriate support-access history.**

Two risks arise if this is left to implementation-time judgement. The first is that "helping the pilot firm" quietly becomes standing operator access to a law firm's confidential and privileged matter data — which for a legal platform is a professional-responsibility and privilege event, not a support convenience. The second is the opposite failure: refusing to design any operator path, so the first real support incident is handled through a database console, leaving no auditable record at all.

`docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` establishes that `PlatformAdministration` owns the `Firm` record but grants no access to what is inside a Firm. That leaves a specific question this ADR must answer: **how does an operator help a Firm in Release 0.1, and what is recorded when they do?**

Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, this needs approved architecture before any privileged-access implementation begins, and it sits squarely inside the mandatory authentication/authorization approval gate.

## Decision

1. **Operator-assisted onboarding and recovery run only through IdentityAccess's `PrivilegedAccessGrant`.** IdentityAccess remains the sole owner of privileged-access grants (`docs/adr/ADR-009-Identity-Security-Access-Control.md` Decision 1). **`PlatformAdministration` creates no operator access path of its own**, and **no second privileged-access mechanism exists anywhere in Release 0.1** (`docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` Decision 13).

2. **Platform operators have no Firm-data access by default.** Operator identities live in the separate platform security realm (Constitution Article 27, ARCH-016 §6) and hold **no standing membership in any Firm**. Owning or provisioning a `Firm` record confers no access to its `Client`, `Matter`, `Task`, or activity history. **IdentityAccess owns the Firm-visible support-access history** (Decision 5); `PlatformAdministration` owns only its own administrative audit facts (`docs/adr/ADR-013-Firm-Provisioning-and-Subscription-Entitlement-Ownership.md` Decision 12) and never another context's audit records.

3. **Every operator support session is an explicit, purpose-bound, time-limited grant** requiring strong authentication and step-up (ARCH-016 §19, §38). A grant names one Firm, one stated purpose, and an explicit expiry. It is never open-ended, never renewed silently, and never reusable for a different purpose or a different Firm.

4. **Silent impersonation is prohibited, without exception.** Both the acting operator identity and the `ActingOnBehalfOf` identity are recorded on **every** action taken under support access. No action is ever attributed solely to a Firm user when an operator performed it, and no Release 0.1 surface offers an unrecorded "log in as" capability.

5. **Release 0.1 requires Firm-visible, append-only support-access history**, recording for each grant, at minimum:

   - the **operator identity** acting;
   - the **purpose and justification** stated for the grant;
   - the **start time and expiry**;
   - the **authorization relied on** — who granted or approved it, under what policy;
   - the **actions performed** under the grant.

   The history is **visible to the Firm**, not merely logged internally (Constitution Article 29). It is **append-only**; a correction is a new record and never a rewrite, and **the actor being audited cannot edit it** (ARCH-016 §40).

6. **Break-glass is explicitly excluded from Release 0.1.** No break-glass request, approval, use, or termination path is designed, built, or offered. **This excludes the capability, not the rule**: Constitution Article 29 and ARCH-016 §39 remain in force, unamended and unweakened, and any future break-glass capability must satisfy them in full — exceptional, justified, time-limited, independently approved where organizational capacity permits, immutably audited, surfaced to the Firm, and never permitting the prohibited operations. **Excluding break-glass must never be read as license to use ordinary support access for break-glass purposes.**

7. **Support access never bypasses a control it does not own.** Under an active grant, an operator can do no more than the grant's purpose and the Firm's own authorization model permit. Support access **never disables Firm isolation, authorize-before-retrieval, denied-existence confidentiality, audit, or — when Practice Management delivers them — Ethical Walls or legal holds.** Because Release 0.1 has no Ethical Walls (`docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md`), there is correspondingly **no wall for support access to override**; that is a stated limitation of Release 0.1, never a permission granted to operators.

8. **Prohibited during support access in Release 0.1**, on the same footing as ARCH-016 §39's list: changing security ownership; granting, broadening, or self-granting any role, capability, membership, or privileged grant; changing a Firm's `SubscriptionEntitlement` or seat limit to widen the operator's own reach; destroying, editing, or truncating audit or support-access history; and — should the capability ever exist — any movement of client money. None of these becomes permissible through purpose wording, urgency, or Firm request.

9. **Operator-assisted recovery never silently bypasses MFA or Firm policy.** An operator may assist an authorized Firm Admin through the approved recovery path; the recovery itself is performed under IdentityAccess's own recovery rules (ARCH-016 §18), remains an authorized and audited act, and never becomes a covert route to a working session. **An operator never learns, sets, resets to a known value, or holds a Firm user's credential, MFA secret, or recovery material.**

10. **Onboarding assistance is provisioning plus invitation, not data entry inside the Firm.** The supported path is: `PlatformAdministration` provisions the `Firm` and its entitlement, and IdentityAccess issues a Firm-bound, purpose-scoped, time-limited, single-use invitation to the first Firm Admin, who establishes their own authentication factors to the Firm's policy (ARCH-016 §9). **An invitation is an offer, not proof of identity or entitlement**, and the operator never establishes the Firm Admin's factors on their behalf.

11. **Support access is not an entitlement bypass.** An expired or suspended `SubscriptionEntitlement` blocks new authentication for Firm principals (ADR-013 Decision 9); an operator grant does not restore Firm access for anyone, and is not a mechanism for continuing service past a lapse.

12. **Architecture approval does not schedule implementation.** EPIC-012 is **proposed, not scheduled**; every story is **Backlog**. **`PF-040` remains the next code story and remains Backlog.** No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled.

## Ownership

| Concept | Owner | Relationship |
|---|---|---|
| `PrivilegedAccessGrant`, operator authentication, step-up, sessions, `ActingOnBehalfOf` recording, security events | **IdentityAccess** (ADR-009) | Sole owner; the only privileged-access path |
| Support-access history record and its Firm visibility | **IdentityAccess** (ADR-009) | Append-only, Firm-visible, actor-attributed |
| `Firm`, `FirmProvisioning`, `SubscriptionEntitlement` | **`PlatformAdministration`** (ADR-013) | Provisions and invites; **grants no Firm-data access** |
| `Client`, `Matter`, `MatterClient`, `MatterTeam`, `Task` | **Practice Management** (ADR-006) | Reached only under an active grant, within the Firm's own authorization |
| `EthicalWall`, `CheckEthicalWallAccess` | **Practice Management** (ADR-006) | Absent in Release 0.1 (ADR-015); support access gains nothing by that absence |
| Break-glass | **IdentityAccess** (ADR-009, Constitution Article 29) | **Rule in force; capability excluded from Release 0.1** |
| AI authority | **Nobody — AI is never an authorization authority** | No AI participates in granting, approving, or using support access |

## Alternatives considered

- **Standing operator access to pilot Firms "because it is only a pilot."** Rejected — a standing cross-Firm privilege is one credential away from a confidentiality and privilege breach, and pilot status changes nothing about the confidentiality of a real firm's real matters. Constitution Article 29 admits no pilot exemption.
- **Silent impersonation with internal logging only.** Rejected — support access that leaves no trace the Firm can see removes the accountability that makes support access acceptable at all (ADR-009 Decision 9, ARCH-016 §38). Internal logs serve diagnostics; they are not the Firm's record.
- **No designed operator path at all for Release 0.1.** Rejected — the first real support incident would then be handled through a database console or a shell, producing exactly the unaudited, unbounded access this architecture exists to prevent. Designing the narrow path is safer than pretending none will be used.
- **Give `PlatformAdministration` its own operator access path**, since it already owns the `Firm`. Rejected — it would create a second authentication and privileged-access authority in direct conflict with Constitution Article 26 and ADR-009 Decision 1, and would put the door next to the tenant record. Owning the Firm record must not become owning access to the Firm.
- **Include break-glass in Release 0.1.** Rejected — a correct break-glass capability requires independent approval, an exceptional-use policy, immutable audit separation, and Firm surfacing that a founding-firm pilot cannot meaningfully exercise or review. Building a weak version would be worse than not having one, and would create a bypass with none of the review that justifies it.
- **Let an operator reset a Firm Admin's MFA directly to restore access quickly.** Rejected — recovery that bypasses MFA and Firm policy converts operator support into a covert authentication path. Assistance through the approved, audited recovery flow is slower and is the only acceptable form.
- **Let an operator create the first Firm Admin's credentials directly.** Rejected — the operator would then have held a Firm principal's credential, and every subsequent action by that principal would be deniable. Invitation-plus-self-enrollment keeps attribution intact from the first login.
- **Treat support access as a way to keep a lapsed Firm working.** Rejected — it would convert a privileged security mechanism into a commercial workaround and would make the entitlement control unenforceable in practice.

## Consequences

- Operator support in Release 0.1 is deliberately slower and more visible than ad-hoc access would be. That friction is the control.
- The Firm sees when the operator was in its data, why, for how long, under whose authorization, and what was done. That transparency may occasionally be uncomfortable; it is the condition on which support access is acceptable.
- Excluding break-glass means a genuine emergency in Release 0.1 has **no in-product exceptional path**. The correct response is an out-of-band, human, recorded decision — not an undocumented bypass — and the limitation is disclosed rather than silently absorbed.
- Because Release 0.1 has no Ethical Walls, support access has no wall to respect. This is a Release 0.1 limitation stated plainly, and it is one more reason the absence of walls is disclosed to the pilot firm (ADR-015).
- Requiring Firm-visible, append-only support-access history early means the audit model must be designed before support access is built, not retrofitted afterwards.
- Operator identities remaining in a separate realm means the operator experience is genuinely separate from the Firm experience, with no shared session and no context switch that carries authorization across.

## Security and professional-responsibility consequences

- **No standing operator access to any Firm's data exists**, and none may be created by configuration, convenience, or pilot arrangement.
- **Every action under a grant is dually attributed** — acting operator identity and `ActingOnBehalfOf` identity — and human, system, and operator actors remain distinguishable in audit.
- **Support-access history is append-only and not editable by the actor being audited.** Deleting or truncating it is prohibited outright (Decision 8).
- **Grants are purpose-bound, time-limited, and expire**; revocation ends the privileged session promptly (ARCH-016 §44), and no cached positive decision survives it.
- **Support access composes with, and never replaces, the Firm's own authorization.** It never widens what the Firm's model permits, and it is never a substitute for membership, capability, or domain authorization.
- **Authorize-before-retrieval and denied-existence confidentiality apply unchanged** to anything an operator reaches under a grant.
- **No operator ever holds, learns, sets, or recovers a Firm user's credential, MFA secret, session token, or recovery material**, and none of those appears in a grant record, a support-access history entry, an event, or a log.
- **The exclusion of break-glass narrows capability, never rules.** Constitution Article 29 and ARCH-016 §39 remain fully in force and unamended.
- **AI plays no part.** AI never requests, approves, holds, extends, or acts under a privileged-access grant, and is never an authorization authority (Constitution Articles 6, 29, 39).
- **No certification or compliance claim is made.**

## Integration consequences

- **IdentityAccess** owns and issues `PrivilegedAccessGrant`, performs operator authentication and step-up, records dual attribution, emits the security events, and owns the Firm-visible support-access history. Its approved model is used as-is; nothing here extends its authority.
- **`PlatformAdministration`** provisions the `Firm` and its entitlement and triggers the first-admin invitation through IdentityAccess. It gains no access to Firm data and defines no access path (ADR-013 Decision 13).
- **Practice Management** is reached, if at all, only under an active grant and only within the Firm's own authorization. It remains the sole future authority for Ethical Walls, and support access never becomes an alternative wall decision-maker.
- **Platform Foundation** carries the operator's `FirmContext` for the granted Firm exactly as it carries any other — built only from verified identity and an explicit, authorized grant, never from a request signal.
- **Digital Presence, Communications, Documents, Billing, Integrations, and `Workflow`** are untouched; none exists in Release 0.1, and none gains a privileged path here.

## Explicit non-goals

This ADR does **not**: implement anything; create modules, source files, schemas, migrations, or tests; design or authorize break-glass; create a support console, admin UI, impersonation feature, or platform back office; grant any standing or default operator access; define an incident-response procedure, on-call rotation, or escalation policy; select a vendor, provider, package, or platform; define infrastructure, database, or shell access policy (infrastructure-level access remains out of scope per ARCH-016 §38 and §58); create a second authentication, authorization, or privileged-access path; weaken or create an exception to Constitution Articles 1–44, and specifically not to Articles 26–30; introduce an AI capability; claim any certification or compliance; schedule EPIC-012; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

This ADR is **Accepted conceptual architecture only**. It authorizes no application code, authentication code, schema, migration, dependency, package, infrastructure, Docker change, CI change, environment change, production configuration, or GitHub setting. **No control described here is claimed to be implemented.**

Because operator-assisted onboarding and privileged access are authentication and authorization capabilities, every implementing story additionally requires the explicit human approval gate in `AGENTS.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before merge, regardless of this ADR's eventual status.

EPIC-012 is recorded as **proposed, not scheduled** in `docs/architecture/08_Roadmap.md`; every story is **Backlog**. **`PF-040` — AggregateRoot remains the next code story and remains Backlog. No story is In Progress.**

The story ID is **ARCH-011** while the new sequential architecture document is numbered **19** (`ARCH-019`), continuing the established distinction between story numbering and architecture-document numbering.
