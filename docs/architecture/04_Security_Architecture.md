# OneLegalPro Security Architecture

**Status:** Approved (conceptual, platform-wide security baseline). Populated by **ARCH-008 — Identity, Security & Access Control**; see `docs/adr/ADR-009-Identity-Security-Access-Control.md`.

**Scope relationship.** This document is the **platform-wide security baseline** binding every bounded context. It owns no data and defines no module. `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` (ARCH-016) defines the **IdentityAccess bounded context** — principals, authentication, roles, and authorization composition. Domain-specific security rules remain owned by the domains that define them, and are referenced rather than restated here (§6).

**No control described in this document is claimed to be implemented.** This is architecture, not an assessment. Implementation stages are proposed, not scheduled; **PF-010 remains the current repository implementation story and PF-011 remains next.**

## 1. Security principles

- **Least privilege** — every principal, service, and job holds the narrowest authority that lets it do its work, for the shortest time it needs it.
- **Deny by default** — absence of an explicit grant is a denial. Nothing is reachable because nobody thought to forbid it.
- **Defense in depth** — no single control is load-bearing. Firm scoping, authorization composition, Ethical Walls, domain restrictions, and audit each independently constrain access.
- **Explicit Firm context** — tenancy is carried explicitly and derived from verified identity and membership, never inferred from a hostname, header, parameter, or route.
- **Complete mediation** — every access to every protected resource is checked at the time of access, on the server, on every path: interactive, bulk, background, export, and AI retrieval alike.
- **Separation of duties** — high-risk operations support maker/approver/reconciler separation, and where policy requires independence, the actor who creates an operation does not silently approve it.
- **Secure defaults** — the safe configuration is the default one. Opting into risk is explicit, authorized, and recorded.
- **Minimize blast radius** — credentials are scoped and expiring, sessions are Firm-bound, privileges are purpose-bound and time-limited, and no standing privilege spans Firms.
- **Fail closed for authoritative security decisions** — when the authority needed to decide is unavailable, access is refused or held, never assumed (§8).
- **No security through UI or obscurity** — hiding a control is not authorization; an unguessable identifier is not access control.
- **Immutable security and business audit history** — audit is append-only, is not editable by the actor being audited, and correction is a new record rather than a rewrite.
- **Human accountability for legally significant actions** — legally and professionally consequential acts remain attributable to accountable humans; AI is never an authority.

## 2. Protected assets

| Asset | Why it matters |
|---|---|
| **Client and Matter data** | The Firm's core confidential work; conflicts-sensitive, so even existence is protected |
| **Privileged and confidential communications** | Privilege waiver is irreversible |
| **Documents and knowledge** | Work product, evidence, and curated know-how, some under legal hold |
| **Financial and client-money records** | Fiduciary obligations; client money is not the Firm's money |
| **Authentication credentials and sessions** | Compromise yields everything above |
| **Firm configuration and branding** | White-label integrity; a branding compromise is a client-facing impersonation |
| **Legal Intelligence sources and Firm annotations** | Authoritative-source integrity; firm annotations are confidential |
| **AI context, embeddings, and derived data** | Derivatives inherit the sensitivity of their source |
| **Secrets, signing keys, and integration credentials** | Master keys to the above |
| **Audit records and backups** | The record of what happened, and a full copy of everything |

## 3. Threat model

Threats the architecture must resist. Mitigations are named at principle level; specifics live in the owning documents.

| Threat | Primary architectural response |
|---|---|
| **Account takeover** | MFA, step-up, session rotation, revocation, anomaly surfacing |
| **Credential stuffing and brute force** | Rate limiting, lockout, enumeration resistance, adaptive password hashing |
| **Phishing and magic-link replay** | Single-use, short-lived, purpose- and Firm-bound links; no open redirects |
| **MFA fatigue and recovery abuse** | Prompt rate limiting; recovery as a privileged, audited workflow that never silently bypasses MFA |
| **Session fixation and hijacking** | Identifier rotation on authentication and elevation; secure transport and cookie protections; prompt revocation |
| **IDOR / BOLA** | Server-side authorization on every object access; a valid identifier is never sufficient |
| **Horizontal and vertical privilege escalation** | Composed authorization; roles grant capabilities, not resources; domain modules narrow; walls unoverridable |
| **Cross-Firm data leakage** | Firm-scoped realms, membership as authority, Firm-scoped repositories, no email-based identity linking |
| **Confused-deputy attacks** | Acting-on-behalf-of recorded; delegation cannot widen; service principals compose identically |
| **Stale authorization and cache errors** | Version-aware caches, prompt revocation propagation, no stale positive result |
| **Insider threat and privileged-access abuse** | No default operator access; purpose-bound, time-limited grants; prohibited operations; Firm-visible history |
| **Secret leakage** | Non-recoverable storage; no secrets in logs, events, or audit; bounded rotation overlap |
| **Injection** | Input validation, parameterized access, output encoding |
| **XSS** | Output encoding, content-security discipline, sandboxed embeds |
| **CSRF** | State-changing requests protected; widget embeds additionally origin-validated |
| **SSRF** | Outbound request restrictions on provider and integration adapters |
| **Malicious uploads** | Validation, filename normalization, quarantine until positively scanned, fail-closed scanning |
| **Supply-chain compromise** | Dependency management and review discipline (§5) |
| **Webhook or integration spoofing** | Authenticated, idempotent, replay-resistant callbacks |
| **Queue / background-job authorization drift** | Jobs preserve initiating actor and provenance and re-verify before protected actions |
| **Backup or export leakage** | Exports authorized, audience-resolved, and audited; backups protected as production data |
| **AI prompt injection attempting to bypass authorization** | AI is never an authorization authority; permission filtering precedes context construction; instructions found in content are data, not commands |
| **Search, count, metadata, and existence leakage** | Filtering before retrieval; denied callers receive no existence signal, count, or aggregate |

## 4. Trust boundaries

| Boundary | Discipline |
|---|---|
| **Public browser → platform** | Untrusted input; unauthenticated surfaces rate-limited and enumeration-resistant |
| **Client Portal → platform** | Authenticated but least-privileged; visibility comes from owning domains, not from login |
| **Firm staff application → platform** | Authenticated, Firm-bound, composed authorization on every request |
| **Embedded widget → platform** | Narrowest surface; embed key identifies a site, not a person; origin-validated and sandboxed |
| **Public API / integration → platform** | Service-principal authentication; surface design reserved for ARCH-009 |
| **Application → database / cache / queue / search / object storage** | Firm scoping enforced in application and repository paths; derived stores inherit source access boundaries |
| **Application → external providers** | Provider-neutral adapters; credentials as secrets; responses treated as untrusted input |
| **Application → approved AI processors** | Processor eligibility is an access-control decision; Firm and permission filtering precede context |
| **Platform operator → production** | No Firm access by default; explicit, purpose-bound, time-limited, audited grants only |
| **Firm ↔ Firm** | Hard isolation; no shared identity, session, index, embedding, cache, or evaluation data |
| **Module ↔ module** | Published contracts only; no cross-module table access, Eloquent imports, or storage paths |

## 5. Control families

Conceptual control families. **No infrastructure vendor, product, or library is selected, and none of these is claimed to be currently implemented.**

- **Identity and access** — authentication, roles, capabilities, composed authorization (ARCH-016).
- **Firm isolation** — explicit Firm context from verified membership, enforced in application and repository paths, never by global scopes alone.
- **Ethical Walls** — a single authority (Practice Management), honored by every context.
- **Data classification and minimization** — collect and retain what is needed; classify what is held (§7).
- **Encryption in transit and at rest** — mandatory for identity, document, communication, financial, and derived data.
- **Key and secret management** — secrets never in plain configuration, logs, events, or audit; rotation with bounded overlap.
- **Secure session handling** — Firm-bound sessions, rotation, timeout, visibility, revocation.
- **Input validation and output encoding** — at every boundary, including provider responses.
- **File quarantine and malware scanning** — fail-closed acceptance; derivatives inherit source protections.
- **Secure integration and webhook handling** — authenticated, idempotent, replay-resistant, safe under duplication and reordering.
- **Authorization-aware search, exports, and AI retrieval** — filtering before retrieval, never after.
- **Audit and monitoring** — append-only security and business audit; anomaly signals for human review.
- **Vulnerability and dependency management** — review discipline for third-party code and updates.
- **Backup and recovery integrity** — recovery preserves audit continuity; backups protected as production data.
- **Incident handling** — investigation supported by sufficient, tamper-evident audit.
- **Environment separation** — non-production never holds unprotected production data.
- **Secure deployment and configuration** — secure defaults; configuration changes authorized and recorded.
- **Security testing and architecture approval** — security-relevant changes pass the approval gates in `AGENTS.md`.

## 6. Domain-specific security boundaries

These remain owned by their architectures and are **referenced, not restated or overridden**:

- **Branding isolation** — no arbitrary CSS/JS injection surface; a branding setting may never suppress a required disclaimer, notice, or security requirement (`docs/architecture/10_White_Label_Platform_Architecture.md`).
- **Communications confidentiality** — Firm-scoped threads; internal annotations structurally distinct from counterparty-visible content; legal hold over retention (`docs/architecture/11_Communications_Hub_Architecture.md`).
- **Client Portal deny-by-default resource visibility** — authentication is not visibility; what a client sees comes from the owning domain (`docs/architecture/12_Website_Client_Portal_Architecture.md`).
- **Practice Management Ethical Walls** — the sole wall authority, via `CheckEthicalWallAccess`; emergency override is recorded and audited (`docs/architecture/13_Practice_Management_Architecture.md`).
- **Document confidentiality, legal holds, and permission inheritance** — private storage, short-lived authorized delivery, most-restrictive-wins, derivatives inheriting source boundaries (`docs/architecture/14_Document_Knowledge_Management_Architecture.md`).
- **Knowledge retrieval filtering before retrieval** — Firm-then-permission filtering, retrieval eligibility, no cross-Firm embeddings (same document, Part II; Constitution Article 21).
- **Billing and trust-accounting segregation** — client money segregated from operating funds; immutable posted history; deny-by-default financial visibility; co-client isolation (`docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md`).
- **Financial AI and all other AI restrictions** — advisory only, provenance-bearing, permission-filtered, never an authority (`docs/architecture/05_AI_Architecture.md`, unchanged by ARCH-008).

## 7. Data classification

A conceptual model. **Domain and professional restrictions may impose stricter handling than the general label**, and a classification never substitutes for an authorization check.

| Class | Examples | Handling posture |
|---|---|---|
| **Public** | Published website content, public knowledge articles | Freely readable once published |
| **Internal** | Firm operational configuration, non-sensitive internal notes | Firm-scoped; staff access under normal authorization |
| **Confidential** | Client and Matter data, communications, most documents, financial records | Firm-scoped, least-privilege, audited; deny-by-default externally |
| **Privileged / Restricted** | Privileged work product, walled-Matter data, billing narratives, client-money records | Confidential plus wall/audience/segregation constraints; restricted in exports and AI processor eligibility |
| **Security Secret** | Credentials, tokens, recovery material, signing keys, provider secrets | Never retrievable, never logged, never in events or audit payloads |

## 8. Security failure handling

Deliberately nuanced — availability and authorization are not traded off uniformly.

- **New authentication, privilege elevation, and sensitive operations requiring fresh authoritative decisions fail closed** when the required authority is unavailable.
- **An already-issued session may continue only within its validity**, and only where its locally verifiable policy and revocation requirements remain satisfied.
- **High-risk operations fail closed** if current membership, role, policy, Ethical Wall, or revocation state cannot be confirmed.
- **No stale cached Ethical Wall or positive authorization result may be used to broaden access.**
- **Notification, rendering, or analytics failure does not roll back an already committed security change** — a revoked session stays revoked even if its notification failed, exactly as a rendering failure never rewrites an issued invoice.
- **Security events and revocation propagation use approved reliable delivery mechanisms when implemented.**
- **Availability never outranks Firm isolation, authorization, Ethical Walls, financial safeguards, or privilege protection.**

## 9. Compliance posture

**No certification or legal-compliance claim is made by this story or this document.**

- This architecture establishes **control requirements**, not attested controls.
- **Jurisdiction-specific privacy, cybersecurity, professional-conduct, retention, and breach-notification obligations require authoritative legal review and separately approved policy**, consistent with the platform's Thailand-first posture (Constitution Articles 1–4) and the effective-dated policy discipline already established for financial and retention rules.
- **No ISO, SOC, PDPA, GDPR, or other certification or compliance claim is made.** Any such claim requires evidence, assessment, and approval entirely outside this document.
- Security-relevant implementation remains subject to the approval gates in `AGENTS.md`.

## 10. Relationship to other documents

| Document | Relationship |
|---|---|
| `docs/architecture/01_OneLegalPro_Constitution.md` | Constitutional security articles prevail over this document |
| `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` | Defines the IdentityAccess bounded context this baseline assumes |
| `docs/adr/ADR-009-Identity-Security-Access-Control.md` | The decision record that populated this document |
| `docs/architecture/05_AI_Architecture.md` | AI governance, unchanged by ARCH-008 |
| Per-domain architectures (ARCH-009 through ARCH-015) | Own their domain security rules; referenced in §6 |
| `docs/architecture/07_API_Standards.md` | Empty placeholder; API and integration security is reserved for ARCH-009 |
| `AGENTS.md` | Enforceable day-to-day rules derived from these principles |
