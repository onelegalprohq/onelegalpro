# ARCH-010 — White-Label Platform Architecture

**Status:** Approved (conceptual architecture) — implementation stories are proposed, not scheduled; see `docs/architecture/08_Roadmap.md`.

## Purpose and scope

This document defines the conceptual domain and system architecture for OneLegalPro's white-label platform capability, implementing `docs/adr/ADR-003-White-Label-Platform.md` and the relevant articles of `docs/architecture/01_OneLegalPro_Constitution.md`. It covers how every Firm-facing and client-facing surface — website, client portal, email, generated PDFs, and the AI assistant — presents as the Firm's own product rather than as OneLegalPro.

This document describes **conceptual models only**. It does not define migrations, Eloquent schemas, or implementation code — those belong to future, separately approved implementation stories (see `docs/architecture/08_Roadmap.md`).

## 1. White-label vision

OneLegalPro is white-label by design, not as a retrofitted add-on. A Firm's clients should never need to know, and should not be told by default, that OneLegalPro is the underlying platform. Every surface a client can reach — marketing website, client portal, transactional and marketing email, generated PDFs, and the AI assistant — presents under the Firm's own name, logo, colors, and domain. "OneLegalPro" is the platform operator's name for its own infrastructure and commercial relationship with the Firm; it is not a mark a Firm's client is required to see. This vision governs every section below: nothing in this architecture may reintroduce a hardcoded, non-brandable OneLegalPro identity into a client-facing surface.

## 2. Branding engine

The **Branding Resolver** is the single contract every rendering surface uses to obtain a Firm's identity. Given a resolution context (request domain, authenticated Firm, or explicit Firm ID), it deterministically resolves a `BrandProfile`:

1. Custom domain match (`TenantDomain`, active and SSL-verified) → owning Firm's `BrandProfile`.
2. Firm-issued subdomain or authenticated Firm context → that Firm's `BrandProfile`.
3. No Firm resolvable (pre-onboarding, platform operator surfaces, error pages) → platform default `BrandProfile`, which carries OneLegalPro's own identity and is the only context in which the platform's own brand may appear on a response.

No rendering surface may bypass the Branding Resolver to assemble brand identity from ad hoc lookups; this keeps resolution order, fallback behavior, and audit logging in one place.

## 3. Theme token system

Styling is expressed through a semantic **theme token** layer, never through hardcoded colors, fonts, spacing, or asset paths in template or component code. Representative token families:

- `color.*` (`color.primary`, `color.surface`, `color.on-primary`, semantic status colors)
- `typography.*` (heading and body font family, scale)
- `radius.*`, `spacing.*` (shape and layout primitives)
- `asset.*` (logo, favicon, letterhead references — resolved to `BrandAsset` records, not raw URLs baked into markup)

The **token schema** (the set of token names and their expected type/shape) is platform-global, static configuration, versioned alongside the platform itself. **Token values** are entirely firm-scoped: every Firm's `BrandProfile` supplies its own values for the schema, falling back to platform default values for any token it does not override. Component and template code reads only token names, never brand-specific literals, so a new Firm requires zero code changes to render correctly.

## 4. Website branding

A Firm's public marketing/website surface (Digital Presence epic, `docs/architecture/08_Roadmap.md`) resolves its `BrandProfile` for logo, theme tokens, custom domain, favicon, and page metadata (title, meta description, social preview identity). The website module owns page content; it consumes, but does not own, branding.

## 5. Client portal branding

The Client Portal epic's logged-in client experience — login screen, portal chrome, navigation, in-app notifications — resolves branding fully from the authenticated Firm's `BrandProfile`. Whether a minimal platform-attribution footer (for example, an optional "secured by OneLegalPro" mark) is shown is a per-Firm, per-plan configuration flag on `BrandProfile`, defaulting to hidden; it is a licensing/business decision, not an architectural requirement, and is called out as an unresolved implementation choice below.

## 6. Custom domains

Each Firm may bind one or more custom domains (for example, `portal.firmname.com`) to their `BrandProfile` via `TenantDomain`. Binding requires:

1. DNS delegation (CNAME or A record) pointed at the platform.
2. A verification challenge (see Domain verification) proving control of the domain.
3. Automated SSL certificate issuance (see SSL) before the domain is marked active.

A `TenantDomain` may be primary or secondary, and its lifecycle (`pending` → `verified` → `ssl_issued` → `active`, with `failed`/`revoked` terminal-ish states) is tracked explicitly rather than inferred.

## 7. Branding inheritance

Initial scope defines a single inheritance level: **platform default → Firm-level `BrandProfile`**. Token resolution is deterministic — a Firm's own value wins if set; otherwise the platform default value applies — with no ambiguous specificity rules (unlike CSS cascade resolution). Sub-firm scopes (for example, per-office or per-department branding within one Firm) are explicitly out of initial scope; see Future expansion.

## 8. Email branding

Transactional and marketing email templates resolve the sending Firm's `BrandProfile` for sender display name, reply-to address, logo, and theme tokens rendered through an email-safe CSS subset. Required legal/compliance content (the Firm's registered legal name, unsubscribe mechanism, any jurisdiction-mandated notices) is layered into the template independently of branding tokens and is never a token a Firm can override away.

## 9. PDF branding

Generated documents (invoices, reports, client-facing contracts and correspondence) apply firm branding — letterhead, logo placement, accent colors, footer — as a presentation wrapper around canonical document content. PDF branding must never alter the substantive content of a legal or business document; this mirrors the Constitution's separation of official content from presentation and editorial material (`docs/architecture/01_OneLegalPro_Constitution.md`, Article 7) applied here to branding rather than AI-generated text.

## 10. AI assistant branding

A Firm may configure the AI assistant's display name and avatar via `AIPersonaConfig` on its `BrandProfile`, so clients see "Firm X Assistant" rather than a platform-branded assistant. Branding is strictly cosmetic: the advisory-only framing, human-review posture, citation and provenance requirements, and disclaimer propagation rules defined in `docs/architecture/05_AI_Architecture.md` and `docs/architecture/09_Legal_Intelligence_Architecture.md` apply identically regardless of persona configuration. No branding setting may suppress a required disclaimer, citation, or AI-advisory notice.

## 11. Multi-tenant security

Branding configuration is firm-scoped data under the same `FirmContext` isolation discipline as any other tenant data (`docs/domain/06_Laravel_Module_Blueprint.md`). Specific constraints particular to branding:

- No arbitrary CSS or JavaScript injection surface: customization is limited to the token system and validated asset uploads, precisely because free-form code injection is a cross-tenant security risk.
- A custom domain never serves another Firm's content, and never serves the platform default content, before its `TenantDomain` reaches `active` (verified and SSL-issued).
- Brand asset uploads are validated (file type, size, dimensions) and access-controlled per Firm before acceptance.
- Every branding mutation, domain-verification event, and certificate lifecycle event is an auditable domain event, consistent with the platform's event-driven integration model and the audit rule in `AGENTS.md`.

## 12. Asset management

Logos, favicons, and letterhead images are stored in object storage and referenced from `BrandProfile`/`BrandAsset` records by identifier, not by raw mutable URL. Re-uploads create a new versioned `BrandAsset` rather than overwriting in place, avoiding stale-CDN-cache and version-confusion failure modes. Assets are validated for file type, size, and dimensions on upload and are access-controlled per Firm.

## 13. Domain verification

Before a `TenantDomain` may serve content, the Firm must complete a DNS-based ownership challenge (TXT record or equivalent file-based token). Unverified domains never serve tenant content under any circumstance. Verification is re-checked periodically after activation, so that a DNS change, domain expiry, or domain takeover attempt after initial verification is detected rather than silently trusted forever.

## 14. SSL

SSL certificates for verified custom domains are issued and renewed automatically by the platform (ACME-style automated issuance), with no manual certificate handling required from the Firm by default. A domain is never marked `active` without a currently valid certificate; certificate issuance or renewal failure keeps the domain in a non-active state rather than serving over an invalid or expired certificate. A future "bring your own certificate" option for enterprise Firms is a possible extension, not part of the initial model (see Future expansion).

## 15. Accessibility

Theme tokens carry (or are checked against) minimum contrast requirements consistent with WCAG AA. When a Firm sets custom color token values, the system validates the resulting foreground/background pairings and surfaces a warning — or blocks activation, per implementation policy still to be decided — rather than silently shipping an inaccessible theme. Accessibility guardrails are a property of the token system itself, not a per-surface convention.

## 16. Localization

Branding-adjacent copy (email footers, portal chrome strings, PDF boilerplate) follows the platform's general interface-localization approach and is a distinct concern from the legal-source language/authority rules in `docs/architecture/09_Legal_Intelligence_Architecture.md`. Interface localization determines what language UI text renders in; it never affects which legal source text is authoritative. The two localization concerns must not be conflated in implementation.

## 17. Marketplace themes

`docs/architecture/06_Marketplace.md` is currently an empty placeholder. This document anticipates, without specifying, that a future theme marketplace could let the platform or third parties publish installable token-set "starter themes" that a Firm can adopt and then override, giving Firms a non-blank starting point. This is a proposed integration point for a future architecture pass once `docs/architecture/06_Marketplace.md` is populated, not a commitment made here.

## 18. Future expansion

- Sub-firm (office/department-level) branding inheritance beyond the single Firm-level override defined in Branding inheritance.
- Bring-your-own-certificate support for custom domains, alongside the default platform-managed SSL.
- Theme marketplace integration (see Marketplace themes).
- White-label mobile applications carrying per-Firm branding and app-store presence.
- Firm-owned email sending domains with Firm-managed DKIM/SPF, beyond the platform-managed sender identity in initial scope.
- Additional locale-specific branding variants beyond the single localization model in initial scope.

## 19. Non-goals

- This is not a general-purpose CMS or page builder; branding governs identity and theming, not arbitrary page content authoring.
- Branding never alters legal-content substance or the authoritative-source presentation rules in `docs/architecture/01_OneLegalPro_Constitution.md`, Articles 2–9; those rules apply identically under every brand skin.
- Initial scope supports exactly one `BrandProfile` per Firm; multi-brand-per-Firm matrixing is out of scope (see Future expansion for sub-firm inheritance as a distinct, later concern).
- Branding customization never weakens `FirmContext` isolation, audit requirements, or AI governance requirements established elsewhere in the architecture; it is a presentation-layer concern only.
- This document does not define the exact accessibility enforcement mechanism (warn-vs-block), the attribution-footer default policy per plan tier, or the theme marketplace data model — these are called out as unresolved implementation choices below.

## Domain boundaries

Branding is a distinct domain from Firm/Matter management, Documents, Billing, and Legal Intelligence. It must never mix:

1. **Brand identity configuration** — theme tokens, assets, sender identity, AI persona, PDF/email branding config (`BrandProfile`).
2. **Custom domain lifecycle** — verification, SSL issuance, activation (`TenantDomain`).
3. **Rendering-surface content** — website copy, portal features, document substance, AI-generated answers — owned by their respective modules, which *consume* branding but never own it.

Each is a distinct concept in the domain model, even where they share infrastructure (object storage, CDN, rendering pipeline).

## Ownership boundary

Unlike `docs/architecture/09_Legal_Intelligence_Architecture.md`'s platform-global/firm-scoped split, Branding has no platform-global content subdomain:

| Subdomain | Scope | Ownership boundary |
|---|---|---|
| Theme token schema (names, types, platform default values) | Platform-global | Not `FirmContext` — static configuration shared by every Firm |
| `BrandProfile` (token values, assets, sender identity, AI persona, PDF/email config) | Firm-scoped | `FirmContext` |
| `TenantDomain` (custom domain, verification, SSL state) | Firm-scoped | `FirmContext` |
| `BrandAsset` (logo, favicon, letterhead files) | Firm-scoped | `FirmContext` |

A rendering surface may read the platform-global token schema and default values, but every actual brand value it renders for a given Firm is that Firm's own data, reached only through the Branding Resolver contract, never through another module's Eloquent records directly.

## Proposed module structure

**Unresolved implementation choice:** exact module name. This document proposes `Branding` as the simplest starting point, consistent with the module-per-bounded-context pattern already established for `LegalIntelligence` (`docs/architecture/09_Legal_Intelligence_Architecture.md`).

```text
Branding/
├── Application/
│   ├── BrandProfile/   (CreateBrandProfile, UpdateThemeTokens, UploadBrandAsset,
│   │                     SetAIPersonaConfig, SetEmailBrandingConfig, SetPDFBrandingConfig, ...)
│   └── Domains/         (RegisterCustomDomain, VerifyCustomDomain, ProvisionSSLCertificate,
│                          RenewSSLCertificate, RevokeCustomDomain, ...)
├── Domain/
│   ├── BrandProfile     (aggregate root)
│   ├── TenantDomain     (aggregate root)
│   ├── BrandAsset       (entity)
│   ├── ThemeTokenSet, ColorToken, ContrastPolicy   (value objects)
│   ├── AIPersonaConfig, EmailBrandingConfig, PDFBrandingConfig   (value objects)
│   └── DomainVerificationChallenge, SSLCertificateStatus   (value objects)
├── Infrastructure/      (Eloquent adapters, object storage adapter, DNS verification adapter,
│                          ACME/SSL provider adapter)
├── Interface/            (Branding Resolver implementation, theme token API, admin endpoints)
├── Database/             (new migrations only — no historical migrations touched)
├── Routes/
├── Tests/
├── Config/               (platform default token set, attribution-footer policy, SSL provider config)
├── ModuleServiceProvider.php
└── README.md
```

Dependency direction and cross-module rules follow `docs/domain/06_Laravel_Module_Blueprint.md` unchanged: Interface → Application → Domain; Domain never depends on Laravel/Eloquent/HTTP; every other module reaches branding data only through the Branding Resolver and published queries, never through `Branding`'s Eloquent records directly.

## Aggregates, entities, and value objects

**Aggregates**

- `BrandProfile` — the aggregate root for a Firm's identity: theme token values, AI persona configuration, email and PDF branding configuration, and references to its `BrandAsset` records. One `BrandProfile` per Firm in initial scope.
- `TenantDomain` — a separate aggregate for custom-domain lifecycle (not owned inside `BrandProfile`), reflecting its distinct, asynchronous, long-running verification and certificate lifecycle — the same reasoning `docs/architecture/09_Legal_Intelligence_Architecture.md` applies to keeping `CourtDecision` separate from `LegalSource`.

**Entities** (owned by an aggregate, identity matters, mutable within the aggregate's lifecycle rules)

- `BrandAsset` — a logo, favicon, or letterhead file belonging to a `BrandProfile`; versioned on re-upload rather than overwritten in place.

**Value objects** (immutable, no identity)

- `ThemeTokenSet` — the resolved set of token values for a Firm (own values layered over platform defaults).
- `ColorToken`, `ContrastPolicy` — a token's color value and the accessibility rule it must satisfy.
- `AIPersonaConfig` — assistant display name and avatar reference.
- `EmailBrandingConfig`, `PDFBrandingConfig` — sender identity/footer configuration and letterhead/footer configuration respectively.
- `DomainVerificationChallenge` — the verification token/method and its status.
- `SSLCertificateStatus` — issuance/renewal/expiry state for a `TenantDomain`'s certificate.

**Unresolved implementation choice:** whether `EmailBrandingConfig` and `PDFBrandingConfig` are modeled as value objects embedded in `BrandProfile` (as assumed above) or as their own entities with independent lifecycles, if email/PDF branding requirements grow complex enough to warrant independent versioning. This document assumes the simpler embedded-value-object model as the starting point.

## Branding Resolver contract

The Branding Resolver (see Branding engine, above) is the single published contract other modules use to obtain a `ThemeTokenSet` and `BrandProfile` summary for a given resolution context. It is the only sanctioned cross-module read path into Branding data; no module may query `BrandProfile` or `TenantDomain` records directly.

## Storage strategy

**Unresolved implementation choice:** exact storage backend split, to be finalized in a future implementation-focused ADR once rendering-surface and CDN requirements are known. Conceptually:

- Structured configuration (token values, sender identity, persona config, domain verification/SSL state) belongs in PostgreSQL, consistent with the platform's existing database rule and UUIDv7 identity.
- Binary assets (logo, favicon, letterhead files) belong in object storage, referenced by `BrandAsset` records, keeping the relational schema focused on queryable configuration rather than binary blobs — the same pattern `docs/architecture/09_Legal_Intelligence_Architecture.md` applies to large captured legal-source text.
- A CDN or edge cache may sit in front of resolved theme output and assets for performance; cache invalidation on `BrandProfile` update is an implementation-level requirement, not a schema concern.

## Access-control boundaries

- `BrandProfile` and `TenantDomain` mutations require Firm-admin-level authorization; read access for rendering purposes goes only through the Branding Resolver.
- Firm-scoped isolation is enforced at the application, repository, and database-policy layers, never by global scopes alone, per `docs/domain/06_Laravel_Module_Blueprint.md`.
- A custom domain never serves content — of any Firm, including the platform default — until its `TenantDomain` is `active`.
- Any write path that could make a `BrandProfile` or `TenantDomain` bypass the accessibility, disclaimer, or AI-governance constraints described above requires the same elevated approval discipline as other high-impact changes in `AGENTS.md`.

## Audit requirements

- Every `BrandProfile` creation and mutation (token changes, asset uploads, persona/email/PDF configuration changes) is an auditable domain event.
- Every `TenantDomain` lifecycle transition (registration, verification success/failure, SSL issuance/renewal/failure, activation, revocation) is an auditable domain event.
- Audit events follow the platform's existing event-driven integration and tenancy/audit discipline; branding events carry Firm ID and Actor ID like any other firm-scoped audit record.

## Failure modes

Conceptual failure modes the architecture must account for (mitigations are implementation-story-level detail):

- **Unverified domain begins serving tenant content** — must never happen; activation is gated strictly on `verified` + `ssl_issued` state, not attempted opportunistically.
- **SSL issuance or renewal failure** — the domain must remain non-active (or revert from active) rather than serve without a valid certificate or with an expired one.
- **Custom color tokens violate minimum contrast** — must be surfaced as a warning or block (policy to be decided), never silently shipped.
- **Malicious or oversized asset upload** — must be validated and rejected at upload time, not discovered later at render time.
- **DNS change or domain takeover after initial verification** — mitigated by periodic re-verification; stale verification state must not be trusted indefinitely.
- **Cross-tenant branding or domain leakage** — mitigated by the Branding Resolver being the only read path and by the same published-contract-only rule `docs/architecture/09_Legal_Intelligence_Architecture.md` applies to its own platform-global/firm-scoped boundary; any direct cross-Firm Eloquent access is a defect, not a variant.
- **A branding configuration suppresses a required legal disclaimer, citation, or AI-advisory notice** — must be structurally impossible; those elements are rendered independently of brand tokens, per Business-logic isolation in `docs/adr/ADR-003-White-Label-Platform.md`.

## Future multi-jurisdiction and multi-locale extension

Interface localization (see Localization, above) is additive: a new locale requires new translated copy and, where relevant, locale-specific legal/compliance boilerplate for email and PDF footers, but does not require changes to the `BrandProfile`, `TenantDomain`, or `ThemeTokenSet` shapes. This mirrors the extension model `docs/architecture/09_Legal_Intelligence_Architecture.md` applies to adding a new legal jurisdiction.

## Phased implementation guidance

See `docs/architecture/08_Roadmap.md`, the proposed White-Label Platform epic, for staged delivery order (token schema and Branding Resolver foundation → `BrandProfile` aggregate and asset management → email/PDF branding integration → AI persona branding → custom domain verification → automated SSL → accessibility guardrails → theme marketplace integration). That staging is proposed only; formal scheduling requires entries in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` and separate story-level approval before implementation begins.
