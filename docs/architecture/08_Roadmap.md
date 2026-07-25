# ARCH-008 — Roadmap

**Status:** Approved (Epic sequencing) — see note on story status below

## Purpose

Sequence OneLegalPro's major epics at the architecture level. This document sits above `docs/implementation/01_Implementation_Sprint_Plan.md`, which governs approved sprint-level delivery of `PF-*` stories. Where the two differ on delivery order, the Implementation Sprint Plan governs day-to-day work; this Roadmap governs epic-level sequencing.

## Epic sequence

1. **EPIC-001 — Platform Foundation** (in progress — see `docs/implementation/01_Implementation_Sprint_Plan.md` and `docs/implementation/03_Engineering_Backlog.md` for approved `PF-*` stories).
2. **EPIC-002 — Legal Intelligence** (proposed — see below). Begins after Platform Foundation's repository, environment, quality/CI, and Foundation Library sprints are far enough along to support a new module (see `docs/domain/06_Laravel_Module_Blueprint.md`).
3. **EPIC-003 — White-Label Platform** (proposed — see below). Must reach at least its token-schema and `BrandProfile` foundation stages before the Digital Presence or Client Portal epics can implement any client-facing rendering surface, since both depend on the Branding Resolver contract.
4. Later epics: Platform Core; Legal Practice Core; Documents; Commercial Operations; Digital Presence; Client Portal; Workflow; Integrations; Reporting; Academic Edition (unchanged from `docs/implementation/01_Implementation_Sprint_Plan.md`), with Digital Presence and Client Portal each depending on EPIC-003 as noted above.

## EPIC-002 — Legal Intelligence (proposed)

> **Status note:** The *architecture* for Legal Intelligence is approved — see `docs/adr/ADR-002-Thailand-First-Legal-Intelligence.md` and `docs/architecture/09_Legal_Intelligence_Architecture.md`. The stage list below is a **proposed** staging of future implementation stories. None of these stages are approved, scheduled, or numbered `PF-*` stories. They require separate entry into `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before any implementation work begins.

Proposed staged delivery:

1. **Jurisdiction foundation** — `Jurisdiction` value object, `PlatformDefaultJurisdictionPolicy` (Thailand as default), jurisdiction/language metadata primitives.
2. **Legal-source aggregate and metadata** — `LegalSource` aggregate, authority/version/provenance metadata (see `docs/architecture/09_Legal_Intelligence_Architecture.md`).
3. **Ingestion pipeline** — intake of official Thai statutory, regulatory, and judicial texts, with integrity hashing and provenance capture.
4. **Translation linking** — `Translation` entity linked to its canonical `LegalSource`, non-authoritative by construction.
5. **Citation engine** — `LegalCitation` model supporting Thai canonical citation and optional English reference citation.
6. **Disclaimer enforcement** — centrally governed disclaimer rendering wherever a translation is presented, in web, API, and AI-generated output.
7. **Thai legal search** — search across official sources and linked translations, authority-aware ranking.
8. **AI retrieval** — authority-aware RAG per `docs/architecture/05_AI_Architecture.md`.
9. **Court-decision ingestion** — court decisions modeled distinctly from legislation/regulations, including licensing and source-rights metadata.
10. **Firm annotations and saved research** — firm-scoped notes, bookmarks, saved research, private AI context, strictly isolated by `FirmContext`.
11. **Amendment tracking and legal graph** — supersession history, amendment records, and cross-reference graph between legal sources.

## Future multi-jurisdiction expansion

Every stage above is designed so that adding a jurisdiction beyond Thailand is a configuration and data exercise, not a redesign of the `LegalSource`, `Translation`, `LegalCitation`, or retrieval models. See `docs/architecture/09_Legal_Intelligence_Architecture.md` for the extension model.

## EPIC-003 — White-Label Platform (proposed)

> **Status note:** The *architecture* for the White-Label Platform is approved — see `docs/adr/ADR-003-White-Label-Platform.md` and `docs/architecture/10_White_Label_Platform_Architecture.md`. The stage list below is a **proposed** staging of future implementation stories. None of these stages are approved, scheduled, or numbered `PF-*` stories. They require separate entry into `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before any implementation work begins.

Proposed staged delivery:

1. **Theme token schema and Branding Resolver foundation** — platform-global token schema, platform default token values, and the Branding Resolver contract other modules will consume.
2. **`BrandProfile` aggregate and asset management** — per-Firm theme values, `BrandAsset` upload/versioning, object storage integration.
3. **Email and PDF branding integration** — `EmailBrandingConfig` and `PDFBrandingConfig` wired into transactional email and generated-document rendering.
4. **AI assistant branding** — `AIPersonaConfig`, with the AI governance rules in `docs/architecture/05_AI_Architecture.md` preserved unchanged under any persona.
5. **Custom domain registration and verification** — `TenantDomain` aggregate, DNS-based verification challenge.
6. **Automated SSL provisioning** — certificate issuance and renewal wired to `TenantDomain` activation.
7. **Accessibility guardrails** — contrast validation on custom theme token values.
8. **Theme marketplace integration** — dependent on a future, separately approved pass on `docs/architecture/06_Marketplace.md`.

## Future sub-firm and marketplace expansion

Every stage above is designed so that department/office-level branding inheritance and theme marketplace integration are additive to `BrandProfile` and `TenantDomain`, not a redesign of either aggregate. See `docs/architecture/10_White_Label_Platform_Architecture.md`, Future expansion, for the extension model.
