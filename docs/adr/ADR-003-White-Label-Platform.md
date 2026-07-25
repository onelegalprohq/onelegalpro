# ADR-003 — White-Label Platform

## Status

Accepted. The human owner has explicitly approved this decision.

## Context

OneLegalPro is sold to Thai law firms, foreign-owned law firms operating in Thailand, and foreign lawyers working with Thai law, who in turn present a client-facing product — website, client portal, email correspondence, generated documents, and an AI assistant — to their own clients. Law firms compete on their own brand, not on the name of the software vendor behind it. Prior to this ADR, no branding, theming, or tenant-identity model existed anywhere in the approved architecture; `docs/architecture/02_Product_Requirements.md` and `docs/architecture/04_Security_Architecture.md` remain empty placeholders pending their own dedicated stories, and every prior architecture document (`docs/architecture/01_OneLegalPro_Constitution.md`, `docs/architecture/05_AI_Architecture.md`, `docs/architecture/09_Legal_Intelligence_Architecture.md`) is silent on how a Firm's identity is presented to its clients.

Upcoming epics already named in `docs/implementation/01_Implementation_Sprint_Plan.md` — Digital Presence and Client Portal — cannot be implemented safely without a prior, approved answer to: whose brand appears on these surfaces, how is that brand configured, and how is it kept out of another Firm's way. Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, that answer must exist as approved architecture before implementation begins.

## Decision

1. **OneLegalPro is white-label by design, not as an add-on.** Every Firm-facing and client-facing surface (public website, client portal, transactional and marketing email, generated PDFs, AI assistant persona) is built from day one to present as the Firm's own product. "OneLegalPro" is the platform operator's name for its own infrastructure and go-to-market, never a mark that a Firm's client is required to see.
2. **Branding data belongs to each tenant.** A Firm's brand — theme tokens, logo and asset files, sender identity, custom domain, AI assistant persona, PDF letterhead — is firm-scoped data isolated by `FirmContext`, on the same isolation discipline as any other firm-owned data in `docs/domain/06_Laravel_Module_Blueprint.md`. Unlike `docs/architecture/09_Legal_Intelligence_Architecture.md`'s platform-global/firm-scoped split, branding has no platform-global content subdomain: only the token *schema* (the shape a theme must take) is platform-global, static configuration; every token *value* and every asset is owned by exactly one Firm.
3. **Theme tokens are the only sanctioned styling mechanism, never hardcoded UI values.** Interfaces consume a semantic token layer (`color.primary`, `typography.heading`, `radius.*`, and similar) resolved per Firm at render time. No module may hardcode a brand-specific color, font, or asset path in template or component code. This is the same discipline the platform already applies to the Constitution's disclaimer rule (`docs/architecture/01_OneLegalPro_Constitution.md`, Article 4) — a presentation concern is centrally modeled and driven by data, not scattered across screens by convention.
4. **Branding is isolated from business logic and from legal-content integrity.** Branding may change how a document, portal, or message looks; it must never change substantive legal content, AI governance disclosures, citation/provenance requirements, or the disclaimer rules established in the Constitution and in `docs/architecture/05_AI_Architecture.md`. An AI assistant may be renamed and re-skinned per Firm, but its advisory-only framing, human-review posture, and citation obligations are constitutional and cannot be branded away.
5. **A new module, tentatively `Branding`,** owns `BrandProfile` (per-Firm aggregate root: theme tokens, assets, sender identity, AI persona configuration, PDF/email branding config) and `TenantDomain` (a separate aggregate for custom-domain lifecycle: pending verification → verified → SSL issued → active), consistent with the module-per-bounded-context pattern already established for `LegalIntelligence`.
6. **Custom domains require verification and automated SSL before activation.** No tenant content may be served on a custom domain that has not completed DNS-based ownership verification, and no custom domain may be marked active without a valid, platform-managed SSL certificate. Unverified or certificate-less domains fail closed, never open.
7. **Multi-tenant security constraints apply to branding the same as to any other tenant data.** Asset uploads are validated, custom styling is restricted to the token system (no arbitrary CSS/JS injection surface across tenants), and every branding mutation, domain verification event, and certificate issuance is an auditable domain event, per the platform's existing event-driven and audit discipline.

Full conceptual detail is recorded in `docs/architecture/10_White_Label_Platform_Architecture.md`. Constitutional grounding is recorded as a new article in `docs/architecture/01_OneLegalPro_Constitution.md`.

## Alternatives considered

- **Treat white-labeling as a premium add-on retrofitted later, with a hardcoded "OneLegalPro" brand as the default UI.** Rejected — retrofitting branding into UI code that assumes a single fixed brand would require rewriting every Firm-facing surface later, and risks the platform name leaking into client-facing surfaces by omission rather than by decision, the same failure mode Article 4 of the Constitution already rejects for the disclaimer.
- **Model branding as platform-global, curated "themes" a Firm merely selects from, rather than firm-owned data.** Rejected — Firms need their own logo, domain, and identity, not a choice among a fixed palette; a firm-owned model is required regardless of whether a future theme marketplace (see `docs/architecture/06_Marketplace.md`, currently an empty stub) is layered on top later.
- **Allow direct CSS/JS customization per Firm instead of a token system.** Rejected — arbitrary CSS/JS is a cross-tenant security and maintainability risk (style/script injection, inconsistent accessibility, brittle upgrades), and would make the "no hardcoded UI values" discipline in Decision 3 unenforceable.
- **Let each rendering surface (web, email, PDF, AI) invent its own branding configuration independently.** Rejected — would fragment a Firm's identity across four disconnected configuration surfaces and make consistent branding (and consistent enforcement of governance rules Firms cannot brand away) structurally difficult, mirroring why Legal Intelligence rejected a single undifferentiated content stream.
- **Require Firms to manage their own SSL certificates for custom domains.** Rejected as the default — raises the operational burden for law firms who are not expected to run infrastructure; platform-managed automated SSL is the default, with "bring your own certificate" left as a possible future enterprise option (see the architecture document's Future expansion section).

## Consequences

- A new domain concept, `BrandProfile`, becomes mandatory context for every Firm-facing and client-facing rendering surface, even for Firms that never customize beyond platform defaults (a default `BrandProfile` still resolves).
- A new module, tentatively `Branding`, is wholly firm-scoped — the first module in the platform with no platform-global content subdomain, which is itself a useful contrast case against `LegalIntelligence`'s split model when writing future module-boundary guidance.
- The Digital Presence and Client Portal epics in `docs/implementation/01_Implementation_Sprint_Plan.md` gain a hard dependency: they cannot be implemented until Branding's foundational aggregates (`BrandProfile`, `TenantDomain`) exist, the same way Legal Intelligence implementation stories depend on the Jurisdiction foundation stage.
- Custom domain support introduces new operational concerns (DNS verification, ACME/SSL automation, certificate renewal monitoring) that did not previously exist anywhere in the approved architecture.
- `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` need a proposed White-Label Platform epic entry (see `docs/architecture/08_Roadmap.md`) before any implementation story can begin; this ADR approves the architecture, not an implementation schedule.
- `docs/architecture/02_Product_Requirements.md` and `docs/architecture/04_Security_Architecture.md` remain empty placeholders; this ADR does not populate them, consistent with the precedent set by `docs/adr/ADR-002-Thailand-First-Legal-Intelligence.md`, which also left them untouched. A future story populating those documents should incorporate the white-label requirements and security boundaries established here.

## Firm ownership and isolation decision

Every `BrandProfile`, `TenantDomain`, brand asset, and branding-derived configuration is firm-scoped data isolated by `FirmContext`, enforced at the application, repository, and database-policy layers, never by global scopes alone — the same discipline `docs/domain/06_Laravel_Module_Blueprint.md` requires generally. There is no platform-global branding content subdomain; only the token schema and platform default token values are platform-global, static configuration, not tenant data.

## Theme token rationale

Hardcoded UI values make consistent, safe, per-tenant customization impossible without touching template or component code per Firm. A semantic token layer, resolved per Firm at render time from `BrandProfile`, keeps styling data-driven, auditable, and centrally enforceable (including accessibility minimums), while keeping component code brand-agnostic.

## Business-logic isolation

Branding governs presentation only. It must never alter legal-content substance, AI governance disclosures, citation and provenance obligations, or any rule established in `docs/architecture/01_OneLegalPro_Constitution.md` or `docs/architecture/05_AI_Architecture.md`. Any implementation that allows a branding configuration to suppress a mandatory disclaimer, citation, or AI advisory notice is a defect, not a customization.

## Benefits

- Firms retain full control of their client-facing identity, which is a competitive requirement for law-firm software, not a cosmetic preference.
- A single token schema and Branding Resolver contract keeps every rendering surface (web, portal, email, PDF, AI) consistent and centrally auditable, rather than independently maintained.
- Isolating branding from business logic means legal-content integrity, AI governance, and disclaimer rules cannot be weakened by a branding change, protecting the guarantees established in ADR-002.
- A firm-scoped-only ownership model keeps the isolation story simple relative to Legal Intelligence's dual-boundary model, reducing the risk of cross-tenant boundary defects in this module specifically.

## Trade-offs

- A token-only styling system is less flexible than free-form CSS/JS; some Firm branding requests (highly bespoke layouts) will not be satisfiable without a future, carefully scoped extension mechanism.
- Automated custom-domain verification and SSL provisioning add operational complexity and new failure modes (DNS propagation delays, certificate renewal failures) that must be monitored.
- A wholly firm-scoped ownership model means there is no shared "starter theme library" out of the box; a theme marketplace (see Future extensibility) is required to give Firms a non-blank starting point at scale.
- Making white-labeling a day-one architectural commitment, rather than a later add-on, means every Firm-facing surface built from this point forward carries branding-resolution complexity even before most Firms customize anything.

## Future extensibility

The architecture is designed so that department- or office-level branding inheritance, a theme marketplace (`docs/architecture/06_Marketplace.md`), bring-your-own-certificate support, white-label mobile apps, and additional locale-specific branding variants can be added as new configuration and data on top of `BrandProfile` and `TenantDomain`, without redesigning either aggregate. See `docs/architecture/10_White_Label_Platform_Architecture.md`, Future expansion.
