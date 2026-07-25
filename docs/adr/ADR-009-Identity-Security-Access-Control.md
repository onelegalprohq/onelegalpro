# ADR-009 — Identity, Security & Access Control

## Status

Accepted. The human owner has explicitly approved this decision.

## Context

Every architecture approved so far depends on an identity capability none of them defines:

- **Practice Management** (`docs/architecture/13_Practice_Management_Architecture.md`, Integration Boundaries) names **Identity** as "not yet architected" and has `MatterTeam` `TeamAssignment` entries reference "a staff Actor identity" it does not own.
- **Billing** (`docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md`, Decision 21 and Integration consequences) requires configurable maker/approver/reconciler roles and names "a future Identity/Security capability" as the owner of actor identity, permissions, and segregation-of-duties assignments.
- **Documents and Knowledge** (`docs/architecture/14_Document_Knowledge_Management_Architecture.md`) attribute authorship, ownership, approval, and curation decisions to Actor identities it references but never defines.
- **Communications** (`docs/architecture/11_Communications_Hub_Architecture.md` §13) requires audit events carrying "Firm ID and Actor ID (human or AI-system actor, distinctly identified)."
- **Digital Presence** (`docs/architecture/12_Website_Client_Portal_Architecture.md` §5) currently owns `ClientPortalIdentity` and `PortalAuthPolicy` — an authentication identity, credential and MFA configuration, and session policy — inside a bounded context whose own architecture describes it as a *presentation surface* that "owns none of this data."

Two structural problems follow. First, **authentication has no owner**, so each surface would grow its own — the Client Portal already has, staff authentication is undefined, and service/integration authentication is unaddressed. Second, **`docs/architecture/04_Security_Architecture.md` has been an empty reserved placeholder since ARCH-001**, deferred by every subsequent story, which means the platform has excellent per-domain security rules and no cross-cutting security baseline tying them together.

Identity is also where multi-tenancy fails most quietly. A platform serving competing law firms cannot afford an identity model in which an email address, a hostname, or a client-supplied Firm identifier is treated as evidence of membership. The failure is not a bad login experience — it is one Firm's confidential matter data reachable from another Firm's session, which for a legal platform is a professional-responsibility and privilege event.

Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, this needs approved architecture before any identity, authentication, or authorization implementation begins, and before the dependent stages already staged in `docs/architecture/08_Roadmap.md` for Digital Presence (Client Portal authentication), Practice Management (`MatterTeam` actor references), Documents/Knowledge (author, owner, approver references), and Billing (actor identity and segregation of duties) can be implemented.

## Decision

1. **IdentityAccess is a separate bounded context**, proposed module `IdentityAccess`. It owns Firm-scoped principals and actor identifiers, identity-account lifecycle, Firm membership, authentication credentials and verified authenticators, MFA and passkey/WebAuthn registration, passwordless and magic-link state, authentication attempts and lockout, account recovery, sessions and their rotation and revocation, federated identity bindings, invitations and provisioning, roles and role assignments, permission and capability grants, delegation grants, service principals and workload identities, credential rotation and revocation, privileged-access and break-glass grants, security authentication and authorization events, and human/system/integration/AI actor provenance.

2. **Security is cross-cutting, not a module.** There is no "Security" module absorbing domain authorization or business rules. `docs/architecture/04_Security_Architecture.md` is the platform-wide security baseline populated by this story; `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` defines the IdentityAccess bounded context. Domain security rules stay in the domains that own them.

3. **Firm-scoped identity realms are the default isolation boundary.** A principal exists within a Firm security realm. Authentication occurs inside an explicitly resolved realm; every interactive session is bound to exactly one Firm context; switching Firms is an explicit, authorized context transition that carries no resource authorization across. Platform-operator identities live in a separate platform realm and receive no Firm access by default.

4. **Authentication and authorization are distinct, and successful authentication grants no resource access.** Proving who you are is not proving what you may reach. Every protected operation composes membership, session binding, role/capability, domain-owned action and resource semantics, Ethical Wall results, and narrower domain restrictions before access is granted.

5. **Domain modules own the semantics of their own resources.** IdentityAccess owns roles, assignments, and permission grants; each domain module owns what its actions and resources mean, publishes its capability vocabulary, and enforces its own invariants. **Resource-owning modules may narrow access; IdentityAccess may never widen it.** The most restrictive applicable decision wins.

6. **Practice Management alone owns Ethical Wall decisions.** `CheckEthicalWallAccess` remains the sole sanctioned enforcement point (`docs/adr/ADR-006-Practice-Management-Core.md`, Decision 4). IdentityAccess composes its result and never reimplements, approximates, caches as authoritative, or overrides it. **No role grant can override an Ethical Wall.**

7. **Digital Presence no longer owns credentials, authenticators, or sessions.** IdentityAccess owns the Client Portal principal, credentials, authentication factors, authentication attempts, recovery, and sessions. Digital Presence retains the portal surface, presentation, portal-specific preferences, and permission-aware composition, invoking IdentityAccess contracts for invitation, authentication, MFA, session, and recovery operations. `ClientPortalIdentity` is replaced by a presentation/access concept, `ClientPortalAccessProfile`, holding no credentials, MFA secrets, passwordless tokens, recovery data, or authoritative session state; `PortalAuthPolicy` becomes a Firm-configured *presentation of*, or reference to, an IdentityAccess-owned channel policy rather than the policy authority. **No approved Client Portal capability is removed.** Practice Management continues to own the underlying `Client`.

8. **Platform operators have no Firm-data access by default.** Access to a Firm's data requires an explicit, purpose-bound, time-limited grant with strong authentication and step-up.

9. **Silent impersonation is prohibited.** Acting-as and acting-on-behalf-of identities are both recorded, and Firms must be able to see appropriate support-access history.

10. **Break-glass access is exceptional, time-limited, justified, visible, and immutably audited.** It never disables Firm isolation, Ethical Walls, legal holds, financial segregation, or audit, and certain high-risk operations remain prohibited during it.

11. **Service principals are distinct from human principals.** Machine identities cannot authenticate through human login flows and cannot silently obtain interactive privileges. Workload credentials are purpose-limited, Firm-bound where applicable, rotatable, revocable, and expiring where possible; raw secrets are never stored in plaintext or recoverable from logs or audit records.

12. **AI and system actions retain both human and machine provenance.** An AI-assisted operation records the initiating human actor, the AI/system actor, the authorization it acted under, and any required human approval. **AI never becomes an authorization authority** and may never grant itself a role, permission, membership, session, delegation, or break-glass grant.

13. **Public API and Integration architecture remains for ARCH-009.** IdentityAccess owns the authenticated service principal and its credential lifecycle; ARCH-009 will own API surfaces, endpoint scopes, versioning, developer experience, webhooks, and integration contracts.

14. **Architecture approval does not schedule implementation.** EPIC-009 is proposed, not scheduled; **PF-010 remains the current repository implementation story and PF-011 remains next.** No `PF-*` story is added, renumbered, or rescheduled.

## Domain ownership

| Concept | Owner | IdentityAccess's relationship |
|---|---|---|
| Principals, actor identifiers, identity lifecycle, Firm membership, credentials, authenticators, MFA/passkeys, sessions, recovery, invitations, roles, permission grants, delegations, service principals, privileged/break-glass grants, security events | **IdentityAccess** | Owner |
| `FirmContext`, tenant resolution, tenant middleware | **Platform Foundation** (PF-080/PF-081/PF-082, unscheduled) | Consumes IdentityAccess's verified results; identity ownership never moves into Foundation |
| Firm branding, custom domains, logos, authentication-page presentation | **Branding** | Composed; presentation only |
| `Client`, `Contact`, `Organization`, `Matter`, `MatterTeam`, professional roles, `EthicalWall` | **Practice Management** | Referenced by identifier; Ethical Wall decisions obtained, never reimplemented |
| Documents, knowledge, invoices, communications, legal sources | **Their owning contexts** | Referenced; resource authorization stays with the owner |
| Client Portal presentation, portal preferences, widgets | **Digital Presence** | Serves authentication/session contracts to it |
| Public API surfaces, scopes, versioning, webhooks, developer portal | **Future ARCH-009** | Reserved; IdentityAccess supplies service-principal authentication only |
| Workflow state and orchestration | **Future Workflow architecture** | Reserved |
| AI authority | **Nobody — AI is never an authorization authority** | Records provenance only |

## Alternatives considered

- **Authentication embedded independently inside every module.** Rejected — produces one credential store, one session model, one lockout policy, and one recovery flow per surface, guaranteeing drift; a security fix would need to land in every module, and the weakest implementation would define the platform's real security posture.
- **Digital Presence remaining the authentication authority.** Rejected — its own architecture describes it as a presentation surface owning none of the data it displays, it has no natural home for staff or service authentication, and leaving credentials there means the portal, the staff application, and integrations authenticate through three unrelated models.
- **One global identity automatically spanning every Firm.** Rejected — a person's account at Firm A becoming an account at Firm B is a cross-tenant escape route by construction, it leaks the existence of Firm relationships between competing firms, and it contradicts the white-label discipline in Constitution Articles 10–11.
- **Email address as a global identity key.** Rejected — email addresses are not globally unique business identities, are frequently shared or reassigned, and using one as a merge key means controlling a mailbox becomes evidence of entitlement. The same email may be independently invited by multiple Firms without either learning of the other.
- **Authorization based only on role names.** Rejected — a role named "Lawyer" proves neither professional qualification nor entitlement to a specific Matter; role checks alone cannot express Ethical Walls, co-client isolation, document restrictions, retrieval eligibility, or segregation of duties.
- **UI-only authorization.** Rejected — hiding a button is not authorization. Every protected decision is server-side and complete-mediation; a client that constructs its own request must be denied by the same authority that renders the page.
- **Each module implementing its own Ethical Wall logic.** Rejected — already rejected by `docs/adr/ADR-006-Practice-Management-Core.md` for exactly the drift risk it creates; ARCH-008 does not reopen it, and IdentityAccess is explicitly not a second wall authority.
- **Permanent super-admin access to all Firms.** Rejected — a standing cross-Firm privilege is a single credential away from a platform-wide confidentiality breach and is indefensible for a platform hosting competing law firms.
- **Silent support impersonation.** Rejected — support access that leaves no trace the Firm can see removes the accountability that makes support access acceptable at all.
- **Long-lived unrotated API keys.** Rejected — an unrotatable, non-expiring secret is a permanent liability whose compromise cannot be bounded; credentials are rotatable, revocable, and expiring where possible, with overlap that does not become indefinite dual validity.
- **Post-retrieval permission filtering.** Rejected — the same correctness impossibility already rejected for knowledge retrieval in Constitution Article 21: content that has been retrieved has already influenced counts, aggregates, rankings, and model context, and no downstream filter reliably unwinds that.
- **Stale authorization caches without revocation or version control.** Rejected — a cache that outlives a membership removal, role revocation, suspension, or Ethical Wall change silently converts a revoked permission into a live one; caches must be policy- and version-aware and safely invalidated.
- **Letting AI decide permissions or approve privileged access.** Rejected — an AI that can grant a role or approve break-glass is an AI with administrative authority over data it cannot be accountable for, contradicting Constitution Article 6 and every subsequent AI-governance article.
- **Selecting an identity vendor or package before approving the conceptual boundary.** Rejected — vendor choice constrains the model rather than serving it; no provider is selected, endorsed, or configured by this story.

## Consequences

- Every module gains a single, consistent identity and authorization dependency, and loses the freedom to invent its own — a coordination cost accepted in exchange for one enforceable security posture.
- Digital Presence's Client Portal authentication becomes an integration rather than an ownership, requiring the boundary correction in Decision 7 and a migration path when EPIC-005 and EPIC-009 are eventually implemented.
- Composing authorization from membership, role, domain semantics, Ethical Wall results, and narrower restrictions is materially more expensive than a role check, and introduces a synchronous dependency on Practice Management for Matter-linked decisions — the same coupling cost `docs/adr/ADR-006-Practice-Management-Core.md` and `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md` already accepted.
- Firm-scoped realms mean a person working with two Firms holds two principals and switches context explicitly. This is deliberate friction and the correct default; any future cross-Firm linking requires its own ADR, explicit verification and consent, and must not weaken Firm isolation or white-label presentation.
- Deny-by-default with no existence signalling makes some diagnostics harder — "no results" and "not permitted" are deliberately indistinguishable to an unauthorized caller.
- Authorization caches must be version-aware and invalidatable, which constrains caching design; correctness outranks cache hit rate.
- EPIC-009 becomes a dependency of EPIC-005's Client Portal authentication, EPIC-006's actor references and Matter Team assignments, EPIC-007's author/owner/approver references, and EPIC-008's actor identity and segregation-of-duties assignments, and consumes Platform Foundation's PF-080/PF-081/PF-082 tenancy primitives without scheduling them.

## Security and professional-responsibility consequences

- **A hostname, custom domain, email address, or client-supplied Firm ID identifies a candidate Firm but never proves membership or grants access.** `FirmContext` is constructed only from verified identity and membership.
- **Denied callers receive no protected content, metadata, count, search result, aggregate, or existence confirmation** — existence disclosure is disclosure, particularly in a conflicts-sensitive domain.
- **Stale positive authorization must not survive** membership removal, role removal, suspension, Ethical Wall change, or security-policy change.
- **Permission filtering precedes retrieval, search, export, aggregation, AI context construction, and RAG** — never after.
- **Bulk operations, reports, exports, background jobs, and AI retrieval receive no weaker authorization treatment than interactive requests**, and background work preserves the initiating actor and authorization provenance rather than treating a queued job as an indefinite permission grant.
- **Credentials, reusable secrets, recovery tokens, and full session tokens never appear in logs, analytics, events, or audit payloads.**
- **A role name is not a professional qualification.** Regulatory and professional verification is a separate approved business determination, never an authentication assumption.
- **Legally significant actions remain attributable to accountable humans**, and human, system, integration, and AI actors remain distinguishable in audit history.

## Integration consequences

- **Practice Management** supplies Ethical Wall decisions and consumes actor references for `MatterTeam` assignments; it gains no identity ownership and loses none of its own.
- **Digital Presence** invokes IdentityAccess for invitation, authentication, MFA, session, and recovery, and consumes authentication events; it keeps `WidgetEmbed`, `AllowedOrigin`, portal presentation, and preferences. Embedded-widget capability security remains narrower than an authenticated client session and never becomes a Client identity.
- **Documents, Knowledge, Billing, and Communications** resolve actor references and authorization decisions through published contracts while retaining full ownership of their own resource rules; **IdentityAccess cannot widen any of them**.
- **Branding** supplies presentation for authentication surfaces and custom domains; it never becomes an authentication authority, and a domain claim proves nothing about membership.
- **Platform Foundation** will consume authenticated IdentityAccess results in its future PF-080/PF-081/PF-082 Firm Context and tenant-resolution stories; identity ownership does not move into Foundation, and those stories are neither scheduled nor renumbered here.
- **ARCH-009** will own the Public API and Integration Platform on top of the service-principal authentication IdentityAccess provides.

## Explicit non-goals

This ADR does **not**: implement authentication or authorization; create schemas or migrations; install or select an identity provider, package, or vendor (including Auth0, Okta, Keycloak, Cognito, or Entra ID); configure OAuth, OIDC, SAML, or SCIM; implement API keys; configure production secrets; add deployment or infrastructure; claim ISO, SOC, PDPA, GDPR, or any other certification or compliance; define biometric identity verification; verify professional qualifications; replace Practice Management's Ethical Walls or any domain-specific authorization; design the Public API or Integration Platform (ARCH-009); design Workflow orchestration; grant AI any security authority; schedule EPIC-009; add or renumber `PF-*` stories; or change PF-010 or PF-011 status.

## Implementation status

This ADR, `docs/architecture/16_Identity_Security_Access_Control_Architecture.md`, and the platform-wide baseline in `docs/architecture/04_Security_Architecture.md` are **conceptual architecture only**. They authorize no application code, authentication code, migrations, schemas, dependencies, packages, infrastructure, Docker configuration, CI changes, environment changes, production configuration, or GitHub settings. **No control described anywhere in ARCH-008 is claimed to be implemented.** EPIC-009 — Identity, Security & Access Control is recorded as **proposed, not scheduled** in `docs/architecture/08_Roadmap.md`; none of its stages carries a story ID. **PF-010 remains current and PF-011 remains next.**

The story ID is **ARCH-008** while the new sequential architecture document is numbered **16** (`ARCH-016`), continuing the established distinction between story numbering and architecture-document numbering.
