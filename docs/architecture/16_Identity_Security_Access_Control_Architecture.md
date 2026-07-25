# ARCH-016 — Identity, Security & Access Control Architecture

**Status:** Approved (conceptual architecture) — implementation stages are **proposed, not scheduled**. **PF-010 remains the current repository implementation story and PF-011 remains next.** No `PF-*` story is added, renumbered, or rescheduled by this document. See `docs/architecture/08_Roadmap.md`.

## 1. Purpose and scope

This document defines the conceptual domain and system architecture for OneLegalPro's Identity & Access Management capability — proposed module `IdentityAccess` — implementing `docs/adr/ADR-009-Identity-Security-Access-Control.md` and the relevant articles of `docs/architecture/01_OneLegalPro_Constitution.md`.

**In scope:** Firm-scoped principals and actor identifiers; identity lifecycle; Firm membership; invitations and provisioning; authentication policy, credentials, and authenticators; MFA, passkeys, passwordless, and magic links; sessions, rotation, and revocation; account recovery; step-up authentication; roles, capabilities, permission sets, and assignments; delegation; segregation of duties; authorization-decision composition; service principals and workload credentials; federation and enterprise provisioning foundations; privileged access and break-glass; security events and audit; and human/system/integration/AI actor provenance.

**Out of scope:** everything in §58, and specifically the platform-wide security baseline (which lives in `docs/architecture/04_Security_Architecture.md`, populated by the same story) and the Public API/Integration Platform (reserved for ARCH-009).

This document describes **conceptual models only**. It defines no migrations, schemas, table names, authentication code, provider configuration, or vendor selection.

## 2. Why IdentityAccess is a bounded context

Every approved architecture already depends on an identity capability none of them owns:

| Dependent context | What it references but does not own |
|---|---|
| Practice Management | Staff Actor identity for `MatterTeam` `TeamAssignment`; names Identity as "not yet architected" |
| Billing | Actor identity, permissions, and maker/approver/reconciler assignments; names "a future Identity/Security capability" |
| Documents & Knowledge | Author, uploader, owner, approver, and curator Actor references |
| Communications | Firm ID and Actor ID on every audit event, human and AI distinctly identified |
| Digital Presence | `ClientPortalIdentity` and `PortalAuthPolicy` — authentication inside a context that owns none of the data it displays |

No candidate is a plausible owner. Practice Management would have to absorb credentials and sessions — ownership `docs/adr/ADR-006-Practice-Management-Core.md` explicitly refuses. Digital Presence would own only client-facing authentication, leaving staff, service, and integration authentication homeless. Platform Foundation owns *technical primitives*, not a business-governed lifecycle with invitations, membership, roles, and privileged-access approval. Letting each module authenticate independently produces one credential store, one lockout policy, and one recovery flow per surface — guaranteed drift, where the weakest implementation defines the platform's real posture.

A dedicated bounded context gives every consumer one place to ask "who is this, in which Firm, and under what authority," and gives revocation, session invalidation, and audit a single point of application.

## 3. Why Security remains cross-cutting

**There is no "Security" module.** Security is a property of every context, not a container. A generic Security module would inevitably absorb domain authorization — deciding which documents are readable, which matters are walled, which financial transactions are eligible — and that is precisely the duplication `docs/domain/06_Laravel_Module_Blueprint.md` and every prior ADR exist to prevent.

The split is therefore:

- **`docs/architecture/04_Security_Architecture.md`** — the platform-wide security baseline: principles, protected assets, threat model, trust boundaries, control families, classification, and failure handling. It binds every context and owns no data.
- **This document (ARCH-016)** — the IdentityAccess *bounded context*: who a principal is, how they authenticate, what roles they hold, and how an authorization decision is composed.
- **Each domain module** — the meaning of its own actions and resources, and its own invariants.

## 4. Domain ownership

**IdentityAccess owns**

Firm-scoped principals and actor identifiers · identity-account lifecycle · Firm membership · authentication credentials and verified authenticators · MFA and passkey/WebAuthn registration · passwordless and magic-link authentication state · authentication attempts and lockout state · account recovery state · sessions, rotation, and revocation · federated identity bindings · invitations and provisioning state · roles and role assignments · permission/capability grants · delegation grants · service principals and workload identities · credential rotation and revocation · privileged-access and break-glass grants · security authentication and authorization events · human, system, integration, and AI actor provenance.

**IdentityAccess does not own**

| Concept | Actual owner |
|---|---|
| `FirmContext`, tenant resolution, tenant middleware | **Platform Foundation** (PF-080/PF-081/PF-082 — technical primitives, unscheduled) |
| Firm branding, custom domains, logos, authentication-page presentation | **Branding** (`docs/architecture/10_White_Label_Platform_Architecture.md`) |
| `Client`, `Contact`, `Organization`, `Matter`, `MatterTeam`, `EthicalWall` | **Practice Management** (`docs/architecture/13_Practice_Management_Architecture.md`) |
| Matter Team professional roles (Responsible Lawyer, Supporting Lawyer, …) and their professional meaning | **Practice Management** |
| Documents, invoices, communications, legal sources, knowledge | **Their owning contexts** |
| Domain-specific resource authorization rules | **The resource-owning module** |
| Client Portal presentation | **Digital Presence** (`docs/architecture/12_Website_Client_Portal_Architecture.md`) |
| Public API design, versioning, developer portals, webhooks, integration orchestration | **Reserved for ARCH-009** |
| Workflow and AI-run state | **`Workflow`** (ARCH-010, `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md`) |
| AI authority | **Nobody — AI is never an authorization authority** |

## 5. Ubiquitous language

| Term | Meaning |
|---|---|
| **Principal** | An authenticated subject within a Firm security realm. Not a Client, Contact, Organization, or lawyer profile. |
| **Actor** | A principal (human, service, system, or AI) as recorded in provenance and audit. |
| **Identity realm** | The isolation boundary within which a principal exists and authenticates — one per Firm, plus a separate platform-operator realm. |
| **Firm membership** | The verified, active association between a principal and a Firm; the precondition for any Firm access. |
| **Credential** | A secret or key material used to authenticate. Never retrievable in plaintext. |
| **Authenticator** | A registered verification factor (password, passkey, TOTP, recovery method). |
| **Authentication strength** | How strongly a session's identity was proven; input to step-up decisions. |
| **Session** | A time-bounded authenticated context bound to exactly one Firm. |
| **Capability** | A named, domain-published action a role may permit — never a raw table permission. |
| **Permission set** | A versioned collection of capabilities. |
| **Role** | A named, assignable bundle of permission sets, platform-defined or Firm-custom. |
| **Delegation** | A scoped, purposeful, expiring grant allowing one principal to act within another's authority. |
| **Service principal** | A non-human identity for an integration or workload. |
| **Privileged access grant** | An explicit, purpose-bound, time-limited elevation, including support access and break-glass. |
| **Authorization decision** | The composed, auditable outcome of an access evaluation (§24). |
| **Acting-on-behalf-of** | The recorded relationship when one identity acts under another's authority or during support access. |

## 6. Identity realm and Firm isolation

Identity is the platform's most dangerous potential cross-tenant escape route, so the isolation rules are stated as absolutes:

- **A principal exists within a Firm security realm.** Firm realms are isolated from each other and from the platform-operator realm.
- **Authentication must occur inside an explicitly resolved Firm security realm.** There is no realm-less authentication.
- **Every interactive session is bound to exactly one Firm context.**
- **Switching Firms requires an explicit, authorized context transition** and must never carry resource authorization from the previous Firm.
- **A hostname, custom domain, email address, or client-supplied Firm ID may identify a *candidate* Firm but never proves membership or grants access.** Realm resolution is a routing hint; membership is the authority.
- **`FirmContext` is constructed only from verified identity and membership information.** It is never trusted because it arrived in a request parameter, header, hostname, cookie, or route.
- **Email addresses are not globally unique business identities** and must never be used to automatically merge or link identities across Firms.
- **The same email may be independently invited by multiple Firms without revealing those relationships to either Firm.**
- **No account-discovery or password-reset response may reveal whether an identity exists in another Firm** (§42).
- **Automatic cross-Firm account linking is prohibited.** Any future cross-Firm identity-linking capability requires a separate ADR, explicit verification and consent, and must not weaken Firm isolation or white-label presentation (Constitution Articles 10–11).
- **Platform-operator identities exist in a separate platform security realm and receive no Firm access by default** (§38).

**Relationship to Platform Foundation tenancy.** Platform Foundation's future `PF-080 Firm Context`, `PF-081 Tenant Resolver`, and `PF-082 Tenant Middleware` stories own the *technical primitives* that carry and enforce tenancy through the request and job lifecycle. They **consume** IdentityAccess's verified authentication and membership results to construct a `FirmContext`; they do not resolve identity, do not own membership, and never derive tenancy from an unverified request signal. Identity ownership does not move into Foundation, and **this document neither schedules nor renumbers PF-080/PF-081/PF-082.**

## 7. Principal versus business person, client, or contact

- **A principal is an authenticated subject, not a business record.** It is not a `Client`, `Contact`, `Organization`, lawyer profile, `MatterClient`, or employee record.
- **Business records reference a principal/actor identifier; they never own authentication.** A `MatterTeam` `TeamAssignment` references an actor; a `Document` records an uploader; a `KnowledgeItem` records an approver; a client-money transaction records a maker and approver. None of them holds a credential.
- **One human may correspond to several principals** — one per Firm realm they belong to, and separately as a Client Portal principal versus Firm staff where both apply. These are not automatically linked (§6).
- **A principal may exist without a business record** (a newly invited administrator) and a business record may exist without a principal (a `Contact` who never receives portal access). Neither implies the other.

## 8. Firm membership

`FirmMembership` is the verified association between a principal and a Firm, and **the precondition for any Firm access**.

- Membership has an explicit lifecycle: `Invited → Active → Suspended ⇄ Active → Revoked` (terminal), with `Expired` where time-bounded.
- **Firm access requires an active, verified membership.** Authentication without active membership yields no Firm access.
- Membership carries the actor category (§11), effective dates where required, and the role assignments scoped to that Firm.
- **Revocation and suspension constrain access promptly** (§44): affected sessions are invalidated or constrained, and cached positive decisions are invalidated.
- Membership changes are auditable security events (§40).

## 9. Invitations and provisioning

- `IdentityInvitation` is the governed path by which a principal joins a Firm realm: issued by an authorized actor, purpose- and role-scoped, time-limited, single-use, and Firm-bound.
- **An invitation is an offer, not proof of identity or entitlement.** Acceptance requires the invitee to establish authentication factors meeting the Firm's policy.
- Invitation issuance, acceptance, expiry, and revocation are auditable events.
- **An invitation to one Firm reveals nothing about the invitee's relationships with any other Firm** (§6, §42).
- Bulk or automated provisioning (future SCIM, §36) uses the same lifecycle and the same audit discipline as manual invitation — it is a different trigger, not a different set of rules.

## 10. Identity lifecycle

```text
Invited → Activated → Active ⇄ Locked (automatic, from failed attempts or risk)
                            ⇄ Suspended (administrative)
                            → Disabled (terminal, reversible only by reactivation)
Active → Recovery in progress → Active
```

- **Locked** is an automatic, protective state (failed attempts, detected risk) with its own release path.
- **Suspended** is an administrative decision; **Disabled** ends access entirely.
- Reactivation is an authorized, audited act — never a silent state flip.
- Every transition invalidates or constrains affected sessions as appropriate and emits a security event.

## 11. Human actor categories

The model distinguishes at least:

- **Firm staff** (general)
- **Lawyers**
- **Paralegals and assistants**
- **Firm administrators**
- **Billing and finance staff**
- **Clients and Client Portal users**
- **External counsel and contractors**
- **Platform operators** (separate realm, §38)

Categories inform default role templates and policy defaults. **A category is not itself an authorization**, and a category label such as "Lawyer" **does not prove professional qualification** (§20). External counsel and contractors are explicitly narrower-permission categories by default, consistent with Practice Management's treatment of External Counsel `TeamAssignment`s.

## 12. Service and workload principals

`ServicePrincipal` is a non-human identity for an integration or workload (§37). It is a distinct aggregate from `Principal` because its lifecycle, credential model, and prohibitions differ fundamentally: it has no recovery flow, no MFA enrollment, no interactive session, and no human accountability of its own — only the accountable human or Firm that owns it.

## 13. Authentication policy

`AuthenticationPolicy` is Firm-scoped, versioned, and **owned by IdentityAccess**. It governs, per realm and per channel (staff application, Client Portal, service):

- Permitted authentication methods
- MFA requirement and permitted factors
- Password composition and rotation rules where passwords are permitted
- Session lifetime, idle timeout, and re-authentication intervals
- Step-up requirements by action class
- Lockout thresholds and release rules
- Recovery method requirements
- Federation configuration where enabled (§35)

**Policy is versioned** (`PolicyVersion`) so an authorization or authentication decision records which policy produced it, and a policy change constrains existing sessions rather than retroactively rewriting past decisions (§44).

**Digital Presence may present** a Firm's configured portal security settings, or reference the channel-specific policy, but **is not the policy authority** (§28).

## 14. Credentials and authenticators

- **`Credential`** — secret or key material. Stored only in a form from which the original cannot be recovered.
- **`Authenticator`** — a registered verification factor with its own lifecycle: registered → verified → active → replaced/removed.

Mandatory safeguards:

- **Credentials and reusable secrets never appear in logs, analytics, events, or audit payloads** — anywhere in the platform.
- **Passwords are stored only through an approved adaptive password-hashing mechanism.** No vendor or algorithm is selected here; the requirement is adaptive hashing, never reversible encryption or plain digests.
- **WebAuthn stores public-key material, never biometric data.** Biometrics remain on the user's device; the platform never receives, stores, or verifies them.
- Authenticator enrollment, replacement, and removal require appropriate verification and produce audit events (§40).
- A `CredentialFingerprint` (a non-reversible reference) may be used for correlation; the credential itself never is.

## 15. Password and passwordless flows

- **Password authentication** is policy-permitted, not assumed. Where enabled, it is subject to §13 policy and §14 storage rules.
- **Passwordless authentication** and **one-time magic links** are first-class options.
- **Magic links are single-use, short-lived, purpose-bound, and Firm-bound.** A link issued for portal login cannot be replayed, reused, redirected to another purpose, or used against another Firm's realm.
- **Magic links and recovery flows prohibit open redirects.** Destination targets are validated against an allow-list, never taken from an arbitrary request parameter.
- **Authentication errors must resist account enumeration** (§42): responses do not distinguish "no such account" from "wrong credential," and timing is normalized as far as practicable.
- **Repeated failures are rate-limited and auditable**, with lockout per §10 and §13.

## 16. MFA and passkeys

- MFA factors and passkey/WebAuthn registrations are `Authenticator` entities under §14.
- **MFA enrollment, replacement, and removal require appropriate verification and audit** — removing a factor is a privileged act, not a preference change.
- **Recovery codes** are single-use, generated in bounded sets, stored non-recoverably, and invalidated as a set on regeneration.
- **MFA fatigue resistance** is a design requirement: repeated push-style prompts are rate-limited and anomalous patterns surfaced (§41).
- Step-up (§19) may require a stronger factor than the one that established the session.

## 17. Sessions and revocation

- **Every session is bound to exactly one Firm context** (§6) and records its authentication strength, policy version, issuance time, and expiry.
- **Session identifiers rotate after authentication and after privilege elevation** — defeating session fixation.
- **Sessions use secure transport and appropriate browser protections.** Cookie attributes, transport security, and CSRF defenses are required properties, specified as requirements rather than as a chosen implementation.
- **Device and session visibility** is available to the principal and to authorized Firm administrators: what sessions exist, from what device context, and when they were last used.
- **Revocation, suspension, and security-policy changes invalidate or constrain affected sessions promptly** (§44).
- Session issuance, rotation, revocation, and expiry are security events (§40).
- **Full session tokens never appear in logs, events, or audit records** — only a safe `SessionId` reference.

## 18. Account recovery

- **Account recovery is a privileged workflow**, not a convenience path.
- **Recovery must not silently bypass MFA or Firm policy.** Where a Firm requires MFA, recovery re-establishes a compliant factor rather than dropping the requirement.
- Recovery initiation and completion are distinct, separately audited events (§40).
- Recovery responses resist enumeration (§42): initiating recovery for an unknown address produces the same observable outcome as for a known one.
- Administrative recovery assistance is a privileged act, recorded with the assisting actor, and never a silent credential reset.

## 19. Step-up authentication

- **High-risk actions support step-up authentication** — a fresh, stronger proof of identity within the existing session rather than a new login.
- Indicative step-up classes: privileged-access requests and approvals; role and permission changes; MFA factor removal; service-credential issuance and rotation; client-money movements and trust-to-operating transfers (`docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §40–§41); Ethical Wall emergency override (Practice Management's own mechanism); bulk export; and break-glass use.
- Step-up **raises `AuthenticationStrength` on the session and rotates the session identifier** (§17).
- A step-up requirement that cannot be satisfied **fails closed** (§47).

## 20. Roles

- **`Role` is owned by IdentityAccess**: a named, assignable bundle of versioned permission sets.
- **Platform-defined role templates** provide sensible defaults per actor category; **Firm-custom roles** are supported where authorized, following the platform-seeded-plus-firm-extension pattern used throughout the platform.
- **A role name proves nothing about professional qualification.** A role named "Lawyer" is an access construct. **Regulatory and professional verification is a separate approved business determination, never an authentication assumption**, and this architecture does not perform it (§58).
- **Practice Management owns Matter Team professional roles** (Responsible Lawyer, Lead Lawyer, Supporting Lawyer, Paralegal, Assistant, External Counsel) and their professional meaning. An IdentityAccess role may *correspond* to one, but the professional semantics and the Matter-scoped permissions they derive remain Practice Management's.

## 21. Permission and capability catalog

- **Each domain module publishes its own capability vocabulary** — the named actions it supports and what they mean. IdentityAccess catalogs and grants them; it never invents or duplicates domain behavior.
- **Capabilities are domain-published names, not raw table or CRUD permissions.**
- **`PermissionSetVersion`** makes permission sets versioned, so an authorization decision can record which version applied and a catalog change does not silently rewrite historical decisions.
- A capability's *existence* in the catalog grants nothing; only an assignment does (§22), and even then only after full composition (§24).

## 22. Role and permission assignments

- `RoleAssignment` and `PermissionGrant` bind a role or capability to a principal **within a specific Firm membership** — never globally.
- **Effective-dated assignments** are supported where required, with explicit **suspension and expiry**.
- Assignment, modification, and revocation are auditable security events (§40).
- **Revocation takes effect promptly** and invalidates cached positive decisions (§44).
- **Assignments narrow or grant within a Firm; they never cross realms.**

## 23. Domain-owned resource policies

The division that keeps this architecture honest:

| IdentityAccess decides | The domain module decides |
|---|---|
| Is this principal authenticated? | What does this action mean? |
| Is the membership active? | What are this resource's facts and invariants? |
| Is the session Firm-bound and strong enough? | Is this specific resource reachable by this actor? |
| Is the capability assigned? | Are there narrower restrictions (audience, classification, eligibility, holds)? |

- **Resource-owning modules may narrow access and enforce their own invariants; IdentityAccess may not widen access.**
- Examples, each unchanged by this document: **Practice Management** owns Ethical Walls; **Billing** owns transaction eligibility and financial invariants; **Documents** owns document-level restrictions, legal holds, and portal audience; **Knowledge Management** owns approval and retrieval eligibility; **Digital Presence** owns portal presentation; **Communications** owns message and delivery state.

## 24. Authorization-decision composition

Authorization is a **composition**, never a single global role check. The final decision combines, in order:

1. **Authenticated principal**
2. **Active Firm membership**
3. **Firm-bound session or service context**
4. **Assigned role/capability**
5. **Domain-owned action semantics**
6. **Domain-owned resource facts**
7. **Practice Management's Ethical Wall decision** where Matter-linked data is involved (§25)
8. **Any narrower document, knowledge, financial, portal-audience, or jurisdiction restriction**
9. **Required step-up or segregation-of-duties policy** (§19, §27)
10. **Explicit deny rules**

**Invariants**

- **Deny by default.**
- **Authorization is server-side. Hiding a button is never authorization.**
- **Every repository and query is Firm-scoped.**
- **Permission filtering happens before retrieval, search, export, aggregation, AI context construction, and RAG — never after.**
- **A role grant cannot override an Ethical Wall.**
- **IdentityAccess never reimplements `CheckEthicalWallAccess`.**
- **Resource-owning modules may narrow; IdentityAccess may not widen. The most restrictive applicable decision wins.**
- **Denied callers receive no protected content, metadata, count, search result, aggregate, or existence confirmation.**
- **Stale positive authorization must not survive** membership removal, role removal, suspension, Ethical Wall change, or security-policy change.
- **Authorization caches are policy- and version-aware and safely invalidated** (§44).
- **Protected operations requiring an unavailable authoritative decision fail closed or remain explicitly pending** (§47).
- **Bulk operations, reports, exports, background jobs, and AI retrieval receive no weaker authorization treatment than interactive requests** (§45).
- **Background work preserves the initiating actor and authorization provenance** and never treats a queued operation as an indefinite permission grant (§45).

**`AuthorizationDecision`** is an auditable value object recording, where appropriate: Firm; principal/actor; acting-on-behalf-of identity; action/capability; resource type and **safe** identifier; decision; reason codes; policy and membership versions; authentication strength; timestamp; correlation ID; and references to required external decisions (such as an Ethical Wall check result). **It never contains secrets, credentials, tokens, or privileged domain content.**

## 25. Ethical Wall integration

- **Practice Management remains the sole owner of Ethical Wall decisions**, obtained only through its published `CheckEthicalWallAccess` query (Constitution Article 17; `docs/adr/ADR-006-Practice-Management-Core.md`, Decision 4).
- IdentityAccess **composes** the result as step 7 of §24. It does not implement, approximate, duplicate, or cache-as-authoritative any wall logic.
- **No role, permission, delegation, privileged-access grant, or break-glass grant overrides an Ethical Wall.**
- Where a resource touches more than one restricted Matter, the **most restrictive** outcome applies — consistent with Documents (`docs/architecture/14_Document_Knowledge_Management_Architecture.md` §10) and Billing (`docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §65).
- An unavailable wall check **fails closed** (§47).
- Emergency override remains Practice Management's own mechanism, requiring recorded justification and producing its own auditable event; IdentityAccess honors the result and never originates one.

## 26. Delegation

`DelegationGrant` allows one principal to act within another's authority — a lawyer's assistant acting for them, or coverage during absence.

- Every delegation carries **scope, purpose, and expiry**. Unbounded delegation is not expressible.
- **Delegation can only narrow or transfer authority the delegator actually holds** — never widen it, and never cross a Firm realm.
- **A delegation cannot override an Ethical Wall**, a segregation-of-duties requirement, or any domain restriction.
- Actions taken under delegation record **both** the acting principal and the `ActingOnBehalfOf` identity (§46).
- Grant and revocation are auditable events; revocation takes effect promptly.

## 27. Segregation of duties

- Supports configurable **maker**, **approver**, and **reconciler** roles, consumed by Billing's client-money controls (`docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` §41) and available to any domain requiring independent review.
- **Where policy requires independence, the creator of an operation must not silently self-approve it.** Where a Firm is too small to separate roles, an explicit recorded policy exception is required — the control is never simply absent.
- SoD requirements are evaluated as step 9 of §24 and can only **restrict**, never permit.
- Delegation must not be usable to defeat SoD: a delegate acting for the maker cannot supply the independent approval.

## 28. Client Portal integration

This resolves the boundary `docs/architecture/12_Website_Client_Portal_Architecture.md` §5 temporarily held.

- **IdentityAccess owns** the Client Portal principal, credentials, authentication factors, authentication attempts, recovery, and sessions.
- **Practice Management continues to own the underlying `Client`.**
- **Digital Presence owns** the portal surface, presentation, portal-specific preferences, and permission-aware composition — and **invokes IdentityAccess contracts** for invitation, authentication, MFA, session, and recovery operations.
- **Digital Presence must not store passwords, authenticators, recovery secrets, or session authority.**
- **`ClientPortalAccessProfile`** replaces the former `ClientPortalIdentity` as a Digital Presence *presentation/access* concept, linked by identifier to an IdentityAccess principal, the Practice Management `Client`, and the Firm. It may hold portal status and preferences; it holds **no credentials, MFA secrets, passwordless tokens, recovery data, or authoritative session state**.
- **`PortalAuthPolicy`** is reinterpreted: the authoritative policy is IdentityAccess's channel-specific `AuthenticationPolicy`; Digital Presence may present Firm-configured security settings or reference that policy, but **is not the policy authority**.
- **Authentication events originate from IdentityAccess** and may be consumed by Digital Presence.
- **Successful authentication is not resource visibility.** What a client may see still comes from the owning domain and its audience/authorization rules — Documents' deny-by-default `PortalDocumentAudience`, Billing's invoice audience, Practice Management's Matter-linked item scope.
- **`WidgetEmbed` and `AllowedOrigin` remain Digital Presence concepts.** Embedded-widget capability security is **narrower than an authenticated client session and must never become a Client identity**: an embed key identifies a site, not a person.

## 29. Branding and custom-domain integration

- Branding (`docs/architecture/10_White_Label_Platform_Architecture.md`) supplies the presentation of authentication surfaces — login pages, portal chrome, transactional email identity — through the Branding Resolver. **Branding is never an authentication authority.**
- A verified `TenantDomain` may **resolve a candidate realm** for routing; it **never proves membership or grants access** (§6).
- **No branding configuration may suppress a security requirement**, an MFA prompt, a security notice, or an audit obligation — Constitution Article 11 applied to authentication surfaces.
- White-label discipline applies: a Firm's authentication surface presents as the Firm's, without disclosing the platform operator's identity or any other Firm's existence.

## 30. Communications integration

- Authentication-related notifications (invitations, magic links, MFA notices, recovery messages, security alerts) are **delivered through Communications' published contracts**; IdentityAccess does not build a second delivery pipeline.
- **Delivery failure never rewrites security state.** An issued invitation, revoked session, or completed recovery stands regardless of whether its notification was delivered; the delivery failure is visible and retried.
- **Secrets are not delivery payloads to be logged**: magic links and recovery material are single-use, short-lived, and never recorded in message audit content (§14, §40).
- Communications' own audit requirement — Firm ID and Actor ID with human and AI actors distinctly identified — is satisfied by IdentityAccess's actor provenance model (§46).

## 31. Practice Management integration

- Practice Management **references actor identifiers** for `MatterTeam` `TeamAssignment`, Note authorship, conflict overrides, and Ethical Wall allow-lists — resolving them through IdentityAccess's published contracts, never owning authentication.
- **Practice Management remains the sole Ethical Wall authority** (§25).
- **Matter Team professional roles remain Practice Management's**; IdentityAccess roles are access constructs that may correspond to them without absorbing their professional meaning (§20).
- This resolves the "Identity (not yet architected)" dependency `docs/architecture/13_Practice_Management_Architecture.md` §13 already named.

## 32. Documents and Knowledge integration

- Author, uploader, owner, approver, and curator references resolve through IdentityAccess.
- **Documents retains full ownership** of document-level restrictions, legal holds, portal audience, and its most-restrictive-wins composition; **Knowledge retains** approval authority, ownership, review policy, and retrieval eligibility.
- **IdentityAccess cannot widen either.** A role grant does not make a walled document readable, a retired knowledge item retrievable, or a restricted audience broader.
- **Permission filtering precedes retrieval and AI context construction** for both (Constitution Article 21) — IdentityAccess supplies the identity and capability inputs; the owning module applies its own eligibility before anything is retrieved.

## 33. Billing integration

- Billing resolves actor identity for makers, approvers, and reconcilers, and consumes IdentityAccess's segregation-of-duties assignments (§27).
- **Billing retains full ownership** of transaction eligibility, financial invariants, client-money segregation, and its own fail-closed rules.
- **Client-money movements require step-up** (§19) and remain prohibited during break-glass (§39).
- **AI holds no financial authority** and no authorization authority — the prohibitions in Constitution Articles 25 and the new Article 30 reinforce each other.

## 34. Public API and Integration boundary — reserved for ARCH-009

- **IdentityAccess owns** the authenticated service principal and its credential lifecycle (§12, §37).
- **ARCH-009 will own** Public API surfaces, endpoint scopes, versioning, developer portals, webhook contracts, rate-limit policy, and integration orchestration.
- This document deliberately defines **no** API surface, scope grammar, token format, or developer experience. Where a future API needs an authentication decision, it consumes IdentityAccess's contracts.

## 35. SSO, OIDC, and SAML

- **Federated identity is architected as a policy-driven capability, not implemented or configured here.** No identity provider, protocol library, or vendor is selected (§58).
- `FederatedIdentity` binds a principal to an external identity-provider subject **within one Firm realm**. A federated login never spans realms and never implies membership in another Firm.
- **Federation authenticates; it does not authorize.** Group or attribute claims from an external provider may *inform* role assignment through an explicit, authorized mapping, but never grant a capability directly, and never override an Ethical Wall or domain restriction.
- Federation configuration changes are privileged, step-up-gated, and audited — an attacker who can redirect federation controls authentication.
- Where federation is enabled, the Firm's own policy still governs session lifetime, step-up, and revocation.

## 36. SCIM and enterprise provisioning

- Automated provisioning and de-provisioning are architected as a **future capability** using the same invitation, membership, and lifecycle rules as manual administration (§9, §10).
- **De-provisioning must revoke promptly** — an upstream directory removal that leaves an active membership or live session is a defect (§44).
- No protocol, schema, or vendor is selected or configured.

## 37. Service credentials

- **Workload credentials are purpose-limited, Firm-bound where applicable, rotatable, revocable, and expiring where possible.**
- **Raw API keys and reusable secrets are never stored in plaintext.** A secret value is revealed only at issuance, only when necessary, and is **never recoverable from logs or audit records**.
- **Rotation supports overlap without indefinite dual validity** — an old credential's validity window is bounded, not open-ended.
- **Machine identities cannot authenticate through human login flows**, and **service principals cannot silently obtain interactive privileges**.
- **An integration acting for a Firm records both the service principal and the Firm**; an automated action records the initiating human where one exists (§46).
- **Service principals cannot bypass Ethical Walls or domain authorization** — they compose through §24 exactly like human principals.
- Issuance, rotation, and revocation are auditable events (§40).

## 38. Privileged access

- **Platform operators have no Firm-data access by default.** They exist in a separate realm (§6) with no standing membership in any Firm.
- **No global super-admin or database-level privilege is treated as ordinary Firm access.** Infrastructure-level access is not a substitute for authorization and is out of scope for implementation here (§58).
- **Firm support access requires an explicit, purpose-bound, time-limited `PrivilegedAccessGrant`** with strong authentication and step-up (§19).
- **Silent impersonation is prohibited.** Both the acting identity and the `ActingOnBehalfOf` identity are recorded on every action taken under support access.
- **Firms must be able to see appropriate support-access history.**
- **Revocation ends the privileged session promptly** (§44).

## 39. Break-glass access

- **Break-glass is exceptional, time-limited, justified, visible, and immutably audited.** It is requested, approved, used, and ended as four distinct recorded events (§40).
- **The architecture supports independent approval when organizational capacity permits**, with an explicit recorded exception where it does not — the same posture §27 takes toward segregation of duties.
- **A break-glass grant never disables Firm isolation, Ethical Walls, legal holds, financial segregation, or audit.**
- **Certain high-risk operations remain prohibited during support or break-glass access**, including: changing security ownership or granting roles; silently broadening permissions; moving client money; and destroying or altering audit history.
- Break-glass use is surfaced to the Firm, not merely logged internally.

## 40. Security events and audit

**Append-only** security events are required for at least:

Invitation issued / accepted / expired / revoked · identity activated / locked / suspended / disabled / reactivated · authentication success and failure · MFA and passkey enrollment / replacement / removal · recovery initiated and completed · session issued / rotated / revoked / expired · role and permission assignment changes · membership changes · delegation changes · service credential issuance / rotation / revocation · SSO and federation changes · break-glass requested / approved / used / ended · authorization denials for high-risk actions · suspicious or rate-limited activity · security-policy changes.

**Content rules**

- Records include **safe actor, Firm, event, result, timestamp, correlation, and provenance** information.
- Records **never store credentials, recovery tokens, full session tokens, payment secrets, or privileged domain content**.
- **Audit history is not editable by the actor being audited.**
- **Operational logs are not the sole authoritative security audit record** — application logs serve diagnostics; the security event stream is the record.
- Audit records are append-only; correction is a new record, never a rewrite (the discipline Constitution Articles 8, 18, and 23 establish elsewhere).

## 41. Threats and abuse cases

Identity-specific threats this architecture must resist (the platform-wide model is in `docs/architecture/04_Security_Architecture.md`):

| Threat | Architectural response |
|---|---|
| Credential stuffing / brute force | Rate limiting, lockout, enumeration resistance, MFA |
| Phishing / magic-link replay | Single-use, short-lived, purpose- and Firm-bound links; no open redirects |
| MFA fatigue | Prompt rate limiting, anomaly surfacing, step-up rather than repeated push |
| Recovery abuse | Recovery as a privileged, audited workflow that never silently bypasses MFA or policy |
| Session fixation / hijacking | Rotation on authentication and elevation, secure transport and cookie protections, revocation |
| Cross-Firm escalation via identity | Firm realms, membership as authority, no email-based linking, no realm-crossing session |
| Confused deputy | Acting-on-behalf-of recorded; delegation cannot widen; service principals compose the same way |
| Privilege escalation via role naming | Roles grant capabilities, not resources; domain modules narrow; Ethical Walls unoverridable |
| Stale authorization | Version-aware caches, prompt revocation propagation, no stale positive result |
| Insider and privileged-access abuse | No default operator access, purpose-bound grants, prohibited operations, Firm-visible history |
| Secret leakage | Non-recoverable storage, no secrets in logs/events/audit, bounded rotation overlap |
| AI prompt injection seeking authorization | AI is never an authorization authority; permission filtering precedes context construction |
| Enumeration and existence leakage | Uniform responses, no existence confirmation to denied callers |

## 42. Privacy and enumeration resistance

- **No response reveals whether an identity exists** — at login, invitation, recovery, or portal access — and never whether one exists **in another Firm** (§6).
- Timing, response shape, and error codes are normalized as far as practicable.
- **Denied callers receive no existence signal** for any protected resource, extending the discipline Documents and Billing already require.
- Personal data in identity records is minimized: what is needed to authenticate and to be accountable, no more.
- Privacy regimes (PDPA, GDPR, and others) apply to identity data consistently with the platform's Thailand-first posture; **no compliance claim is made** (§58 and `docs/architecture/04_Security_Architecture.md`).

## 43. Encryption and secret handling

- Identity data is **encrypted in transit and at rest**.
- **Credentials are stored non-recoverably** (§14); signing keys, provider secrets, and federation secrets are managed as secrets, never plain configuration.
- Key and secret rotation is a required capability with bounded overlap (§37).
- **No key-management vendor, HSM, or secret store is selected** (§58).

## 44. Caching and revocation

Caching authorization is a correctness problem, not a performance one:

- **Authorization caches are policy- and version-aware.** A cached decision records the `PolicyVersion`, `PermissionSetVersion`, and membership version it was computed under.
- **A version change, membership removal, role revocation, suspension, Ethical Wall change, or policy update invalidates affected cached decisions.**
- **No stale cached positive result may broaden access.** A cache miss or an unresolvable version is treated as "recompute," and if the authority is unavailable, **fail closed** (§47).
- **Ethical Wall results are never cached as authoritative** across a change; Practice Management remains the live authority.
- Revocation propagation uses approved reliable delivery mechanisms **when implemented** — this document specifies the requirement, not a mechanism.

## 45. Background jobs and asynchronous authorization

- **Queued and scheduled work receives no weaker authorization treatment than an interactive request.**
- A job **preserves the initiating actor and the authorization provenance** that permitted it; it is not an anonymous system action.
- **A queued operation is not an indefinite permission grant.** Where the underlying authority may have changed — membership revoked, role removed, wall established — a job performing a protected action re-verifies before acting, and fails closed if it cannot.
- Long-running exports, reports, and AI retrieval jobs apply permission filtering **before** retrieval, per §24, at the time they run.

## 46. AI and system actor provenance

- **A human, system, integration, and AI actor must never be indistinguishable in audit history.** `ActorReference` carries the actor category explicitly.
- **An AI-assisted operation records**: the initiating human actor; the AI/system actor involved; the authorization under which it acted; and the resulting human approval where approval is required.
- **AI must never grant itself a role, permission, membership, session, delegation, or break-glass grant**, and never becomes an authorization authority.
- This document **introduces no new AI capability** and preserves the existing AI-governance rules in `docs/architecture/05_AI_Architecture.md` and Constitution Articles 6, 12, 17, 19, 21, and 25 unchanged.

## 47. Operations and failure handling

Nuanced, and deliberately not uniform:

- **New authentication, privilege elevation, and sensitive operations requiring a fresh authoritative decision fail closed** when the required authority is unavailable.
- **An already-issued session may continue only within its validity**, and only where its locally verifiable policy and revocation requirements remain satisfied.
- **High-risk operations fail closed** if current membership, role, policy, Ethical Wall, or revocation state cannot be confirmed.
- **No stale cached Ethical Wall or positive authorization result may be used to broaden access** (§44).
- **Notification, rendering, or analytics failure does not roll back an already committed security change** — a revoked session stays revoked even if its notification failed.
- Security events and revocation propagation use approved reliable delivery mechanisms when implemented.
- **Availability never outranks Firm isolation, authorization, Ethical Walls, financial safeguards, or privilege protection.**

Additional conceptual failure modes: identity-provider outage (federation unavailable → fail closed for new authentication, existing valid sessions unaffected); clock skew affecting expiry (bounded tolerance, never unbounded); duplicate invitation acceptance (idempotent, single-use); concurrent role changes (resolved by aggregate concurrency, never a lost update); and revocation-propagation delay (bounded, visible, and never silently assumed complete).

## 48. Aggregate boundaries

**Aggregate roots**

- **`Principal`** — identity, realm, lifecycle state, and its `Credential`, `Authenticator`, `MFAFactor`, `PasskeyRegistration`, `FederatedIdentity`, `RecoveryMethod`, and `Session` entities. The lifecycle transitions in §10 are exactly the invariant boundary it protects.
- **`FirmMembership`** — the principal-to-Firm association and its `RoleAssignment`/`PermissionGrant` entities. **Separate from `Principal`** because one principal's membership in a Firm is revoked, suspended, and re-scoped on a different cadence than the identity itself, and because membership — not identity — is the access authority (§8).
- **`IdentityInvitation`** — its own aggregate with a short, single-use lifecycle that must be auditable independently of the principal it may eventually create (which may never exist if the invitation expires).
- **`AuthenticationPolicy`** — Firm- and channel-scoped, versioned, changing independently of any principal it governs.
- **`Role`** — a reusable definition shared across many memberships; assignment is the membership's concern, definition is the role's.
- **`DelegationGrant`** — independent because it spans two principals and carries its own scope, purpose, expiry, and audit lifecycle.
- **`ServicePrincipal`** — a distinct aggregate from `Principal` (§12): no recovery, no MFA, no interactive session, different credential model and prohibitions. Owns its `ServiceCredential` entities.
- **`PrivilegedAccessGrant`** — independent, with a request → approve → use → end lifecycle that must be auditable on its own and must outlive any single session it authorizes.
- **`SecurityEventStream`** — an append-only audit boundary. Modeled as its own boundary rather than as entities inside each aggregate because audit must be writable when the subject aggregate is being denied, suspended, or destroyed, and must never be mutable by the aggregate it records.

**Why `Session` is inside `Principal` rather than its own aggregate.** A session's validity is inseparable from its principal's lifecycle — suspending a principal must invalidate its sessions atomically, which is exactly what an aggregate boundary guarantees. Session volume is bounded by device count, not by unbounded history; expired sessions are pruned to the event stream rather than accumulating.

**Why `AuthorizationDecision` is not an aggregate.** It is a computed, recorded outcome (§24) — a value object written to the event stream, never a mutable entity with its own lifecycle.

## 49. Entities and value objects

**Entities:** `Credential` · `Authenticator` · `MFAFactor` · `PasskeyRegistration` · `FederatedIdentity` · `RoleAssignment` · `PermissionGrant` · `Session` · `RecoveryMethod` · `RecoveryCode` · `ServiceCredential`.

**Value objects:** `PrincipalId` · `ActorReference` (identifier + actor category: human/service/system/AI) · `FirmMembershipId` · `IdentityRealm` · `AuthenticationStrength` · `AuthenticationMethod` · `Capability` · `PermissionSetVersion` · `SessionId` · `CredentialFingerprint` · `AuthenticatorId` · `PolicyVersion` · `AuthorizationDecision` · `AuthorizationReason` · `DelegationScope` · `PrivilegePurpose` · `Expiry` · `IPAddress` · `DeviceContext` · `CorrelationId` · `ActingOnBehalfOf`.

**Consumed from Platform Foundation, not defined here:** `AggregateRoot`, `Entity`, `ValueObject`, `DomainEvent`, `BusinessIdentifier`, `Result`, `Clock`, `UUIDv7` (PF-040–PF-049), and `FirmContext` (PF-080, constructed from this module's verified results — §6).

**Prohibited as ordinary retrievable values:** raw passwords, full session tokens, recovery tokens, magic-link secrets, and reusable service secrets. Where a reference is needed, a non-reversible `CredentialFingerprint`, `SessionId`, or `AuthenticatorId` is used instead.

## 50. Commands

`InvitePrincipal` · `AcceptInvitation` · `ActivatePrincipal` · `SuspendPrincipal` · `DisablePrincipal` · `AssignRole` · `RevokeRole` · `GrantDelegation` · `RevokeDelegation` · `RegisterAuthenticator` · `RemoveAuthenticator` · `AuthenticatePrincipal` · `IssueSession` · `RevokeSession` · `BeginAccountRecovery` · `CompleteAccountRecovery` · `RegisterServicePrincipal` · `RotateServiceCredential` · `RevokeServiceCredential` · `RequestPrivilegedAccess` · `ApprovePrivilegedAccess` · `EndPrivilegedAccess` · `UpdateAuthenticationPolicy`.

Supporting commands the model implies: `ReactivatePrincipal`, `RevokeInvitation`, `ActivateFirmMembership`, `RevokeFirmMembership`, `DefineRole`, `PublishPermissionSetVersion`, `ElevateAuthenticationStrength` (step-up), `RegenerateRecoveryCodes`, `BindFederatedIdentity`, `UnbindFederatedIdentity`.

**Authorization and step-up.** Every command is authorized under §24. `AssignRole`, `RevokeRole`, `RemoveAuthenticator`, `UpdateAuthenticationPolicy`, `RegisterServicePrincipal`, `RotateServiceCredential`, `RequestPrivilegedAccess`, `ApprovePrivilegedAccess`, and all federation-binding commands additionally require **step-up** (§19) and are **never AI-executable** (§46).

## 51. Queries

`GetPrincipal` · `GetFirmMembership` · `ListPrincipalSessions` · `ListRoleAssignments` · `GetAuthenticationPolicy` · `AuthorizeAction` · `GetAuthorizationDecision` · `ListSecurityEvents` · `CheckAuthenticationStrength` · `ResolveActorReference`.

- **Every query is permission-aware at evaluation time** and Firm-scoped.
- **`AuthorizeAction` composes domain facts (§24) and never substitutes for Practice Management's `CheckEthicalWallAccess`** — it *calls* it as one input and cannot override its result.
- `ResolveActorReference` returns safe actor identity and category for audit and display; it never returns credentials, session material, or another Firm's principals.
- `ListSecurityEvents` is itself authorization-gated, and never returns secrets or privileged content.

## 52. Events

`PrincipalInvited` · `PrincipalActivated` · `PrincipalSuspended` · `PrincipalDisabled` · `AuthenticationSucceeded` · `AuthenticationFailed` · `PrincipalLocked` · `AuthenticatorRegistered` · `AuthenticatorRemoved` · `SessionIssued` · `SessionRevoked` · `AccountRecoveryCompleted` · `FirmMembershipActivated` · `FirmMembershipRevoked` · `RoleAssigned` · `RoleRevoked` · `DelegationGranted` · `DelegationRevoked` · `ServiceCredentialRotated` · `ServiceCredentialRevoked` · `PrivilegedAccessGranted` · `PrivilegedAccessEnded` · `AuthenticationPolicyUpdated`.

Supporting events the model implies: `InvitationRevoked`, `InvitationExpired`, `SessionRotated`, `AccountRecoveryInitiated`, `PrivilegedAccessRequested`, `PrivilegedAccessApproved`, `AuthorizationDenied` (high-risk actions), `SuspiciousActivityDetected`, `FederatedIdentityBound`.

**Safe-payload rule.** Events carry **identifiers and safe metadata only**. They **never** include passwords, raw tokens, authenticator material, recovery secrets, session secrets, or privileged domain content. An event says *that* a session was issued for a principal in a Firm, with correlation and safe references; a consumer needing more must issue an authorized query.

## 53. Module structure

Conceptual only. **No directory or source file is created by this story.**

```text
app/Modules/IdentityAccess/
├── Application/
│   ├── Commands/        (InvitePrincipal, AuthenticatePrincipal, AssignRole,
│   │                     RequestPrivilegedAccess, RotateServiceCredential, ...)
│   ├── Queries/         (AuthorizeAction, GetFirmMembership, ListPrincipalSessions,
│   │                     ResolveActorReference, ListSecurityEvents, ...)
│   ├── Handlers/
│   └── DTOs/
├── Domain/
│   ├── Aggregates/      (Principal, FirmMembership, IdentityInvitation,
│   │                     AuthenticationPolicy, Role, DelegationGrant,
│   │                     ServicePrincipal, PrivilegedAccessGrant, SecurityEventStream)
│   ├── Entities/        (Credential, Authenticator, MFAFactor, PasskeyRegistration,
│   │                     FederatedIdentity, RoleAssignment, PermissionGrant, Session,
│   │                     RecoveryMethod, RecoveryCode, ServiceCredential)
│   ├── ValueObjects/    (PrincipalId, ActorReference, IdentityRealm,
│   │                     AuthenticationStrength, Capability, PermissionSetVersion,
│   │                     PolicyVersion, AuthorizationDecision, DelegationScope,
│   │                     PrivilegePurpose, ActingOnBehalfOf, ...)
│   ├── Events/
│   ├── Policies/        (authorization composition, step-up, lockout, SoD evaluation)
│   └── Repositories/    (contracts only — no persistence detail)
├── Infrastructure/      (persistence adapters, hashing adapter, WebAuthn adapter,
│                         federation adapters, rate-limit adapter — all provider-neutral)
├── Http/                (authentication and session endpoints, admin endpoints)
├── Contracts/           (published cross-module contracts other modules depend on)
├── Config/              (role templates, default policy shapes, capability catalog)
├── ModuleServiceProvider.php
└── README.md

Consumed from app/Foundation (not duplicated): AggregateRoot, Entity, ValueObject,
DomainEvent, BusinessIdentifier, Result, Clock, UUIDv7 (PF-040–PF-049);
FirmContext (PF-080) is constructed from this module's verified results.
```

Rules, per `docs/domain/06_Laravel_Module_Blueprint.md` unchanged:

- **Platform Foundation technical primitives are consumed, not duplicated.**
- **Domain modules do not import IdentityAccess Eloquent models**; cross-module use occurs only through published contracts.
- **IdentityAccess does not import another module's Eloquent models.**
- **Domain modules retain final resource authorization responsibility** (§23).
- Dependency direction is `Http → Application → Domain`; Infrastructure depends on Application/Domain contracts; Domain never depends on Laravel, Eloquent, HTTP, or provider SDKs.

## 54. Cross-module contracts

IdentityAccess publishes, and other modules consume: actor resolution (`ResolveActorReference`), membership and role queries, authentication and session operations (used by Digital Presence for the Client Portal, §28), authorization composition (`AuthorizeAction`), authentication strength checks for step-up, and its security event stream.

IdentityAccess consumes, through their published contracts: Practice Management's `CheckEthicalWallAccess` and Matter-access contracts; Branding's Resolver for authentication-surface presentation; Communications' delivery contracts for security notifications; and each domain module's published capability vocabulary.

**No storage paths, credentials, tokens, or raw security material cross a module boundary in either direction.**

## 55. Invariants

- Every protected operation is **Firm-scoped**.
- **Successful authentication is not resource authorization.**
- Every active session is **bound to exactly one Firm context**.
- **Active Firm membership is required** for any Firm access.
- **Email or domain possession alone grants no Firm or resource access.**
- **Authorization is deny-by-default** and server-side.
- **Domain modules own resource semantics**; **IdentityAccess cannot override domain restrictions**.
- **Practice Management alone owns Ethical Wall decisions**; no grant of any kind overrides a wall.
- **Authorization happens before retrieval, search, aggregation, export, and AI context construction.**
- **Denied callers receive no existence signal.**
- **Revocation and suspension constrain access promptly**; **no stale positive authorization may broaden access**.
- **Human, system, integration, and AI actors remain distinguishable** in audit history.
- **Service principals cannot use human login paths** and cannot silently obtain interactive privileges.
- **Credentials, tokens, and recovery secrets never enter logs, events, or audit records.**
- **Platform operators have no Firm access by default**; **silent impersonation is prohibited**; **break-glass is justified, time-limited, and audited**.
- **Security failure handling fails closed** where current authority is required.
- **Firm isolation, Ethical Walls, legal holds, and financial safeguards are never bypassed** — by any role, delegation, privileged grant, or break-glass.
- **AI has no authority to grant access or approve privileged operations.**
- **Architecture approval schedules no implementation.**

## 56. Alternatives considered

Recorded in full in `docs/adr/ADR-009-Identity-Security-Access-Control.md`, Alternatives considered. In summary, the following were considered and **rejected**: authentication embedded independently inside every module; Digital Presence remaining the authentication authority; one global identity automatically spanning every Firm; email address as a global identity key; authorization based only on role names; UI-only authorization; each module implementing its own Ethical Wall logic; permanent super-admin access to all Firms; silent support impersonation; long-lived unrotated API keys; post-retrieval permission filtering; stale authorization caches without revocation or version control; letting AI decide permissions or approve privileged access; and selecting an identity vendor before approving the conceptual boundary.

## 57. Consequences and trade-offs

- **One identity dependency for every module** — consistent posture, at the cost of a coordination point every context now relies on.
- **Composed authorization is more expensive than a role check**, and introduces a synchronous Practice Management dependency for Matter-linked decisions — the same coupling cost ADR-006 and ADR-008 already accepted, for the same reason.
- **Firm-scoped realms mean a person working with two Firms holds two principals** and switches context explicitly. Deliberate friction; correct default.
- **Deny-with-no-existence-signal makes diagnostics harder** — "no results" and "not permitted" are intentionally indistinguishable to an unauthorized caller.
- **Version-aware caches constrain caching design**; correctness outranks hit rate.
- **The Client Portal boundary correction creates migration work** whenever EPIC-005 and EPIC-009 are implemented, in exchange for removing an authentication authority from a presentation context.
- **Step-up and segregation of duties add friction to high-risk actions** — which is their purpose, and small Firms may need recorded policy exceptions rather than absent controls.

## 58. Explicit non-goals

This architecture does **not**: implement authentication or authorization; create schemas or migrations; install or select an identity provider, library, or vendor (including Auth0, Okta, Keycloak, Cognito, or Entra ID); configure OAuth, OIDC, SAML, or SCIM; implement API keys; configure production secrets; add deployment or infrastructure; select a key-management, HSM, or secret-store product; claim ISO, SOC, PDPA, GDPR, or any other certification or compliance; define biometric identity verification; verify professional qualifications; replace Practice Management's Ethical Walls or any domain-specific authorization; design the Public API or Integration Platform (ARCH-009); design Workflow orchestration; give AI any security authority; introduce any new AI capability; create the module directory or any source file; schedule EPIC-009; or add, renumber, or reschedule any `PF-*` story.

**No control described in this document is claimed to be implemented.**

## 59. Future expansion

Each is **describable as future work, not implemented, not architected in detail, and not scheduled**; each would require its own architecture pass and approved stories:

- **Full OIDC/SAML federation** and enterprise identity-provider integration (§35).
- **SCIM provisioning and de-provisioning** at scale (§36).
- **Risk-based and adaptive authentication** (device reputation, impossible-travel signals) — subject unchanged to fail-closed and no-AI-authority rules.
- **Cross-Firm identity linking** — requires its own ADR, explicit verification and consent, and must not weaken Firm isolation or white-label presentation (§6).
- **Partner and court-portal identities** — distinct identity and permission models named as future work in `docs/architecture/12_Website_Client_Portal_Architecture.md`.
- **Hardware-backed and attestation-based authenticators.**
- **Delegated Firm administration** at scale, and organizational hierarchies within a Firm.
- **Security analytics, anomaly detection, and continuous monitoring** — signals for human review, never automatic authorization actions.
- **Professional-qualification verification** as an approved business determination distinct from authentication (§20).

## 60. Proposed implementation stages

**Proposed only. None of these stages is approved, scheduled, or assigned a `PF-*` number.** Each requires its own entry in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`, with a Definition of Ready and Definition of Done, before implementation begins. This is the same sequence recorded for EPIC-009 in `docs/architecture/08_Roadmap.md`.

1. **Principal, actor-reference, and Firm security-realm foundation.**
2. **Firm membership, invitations, provisioning, and lifecycle.**
3. **Authentication policy, credentials, and authenticator foundation.**
4. **MFA, passkeys, recovery, session rotation, and revocation.**
5. **Roles, capabilities, permission sets, and assignments.**
6. **Authorization-decision composition and Ethical Wall integration.**
7. **Client Portal identity-boundary migration and integration.**
8. **Service principals, workload credentials, and API-authentication foundation** (API surfaces themselves remain ARCH-009).
9. **Federation, SSO, and SCIM foundations.**
10. **Delegation, segregation of duties, privileged access, and break-glass.**
11. **Security events, monitoring, risk controls, and operational hardening.**

**PF-010 remains the current repository implementation story and PF-011 remains next.** Architecture approval schedules nothing.
