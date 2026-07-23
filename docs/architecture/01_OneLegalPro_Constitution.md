# ARCH-001 — OneLegalPro Constitution

**Status:** Approved

## Purpose

Establish the constitutional principles that govern OneLegalPro's product focus, legal-source authority, and platform architecture. These principles are binding on every future story, module, and AI-assisted change. Where a lower-level document (architecture, ADR, domain, implementation) conflicts with this Constitution, this Constitution prevails; stop and request an architecture decision.

## Article 1 — Product focus and jurisdiction

OneLegalPro is designed primarily for Thai law firms, Thai lawyers, foreign-owned law firms operating in Thailand, and foreign lawyers working with Thai law.

Thailand is the platform's first and default jurisdiction. Jurisdiction is a first-class domain concept, not a hardcoded assumption — the architecture must remain extensible to additional jurisdictions without redesigning core domain models. See `docs/architecture/09_Legal_Intelligence_Architecture.md` and `docs/adr/ADR-002-Thailand-First-Legal-Intelligence.md`.

## Article 2 — Authoritative legal sources

Official Thai-language statutory, regulatory, and judicial texts are the authoritative legal sources for Thailand. Where an official English or other-language translation conflicts with the official Thai text, the Thai text prevails.

No translation, AI-generated explanation, or editorial commentary may be represented as, or substituted for, an official legal source.

## Article 3 — Translation limitations

English translations are provided for convenience and reference only. They are never authoritative.

Every presentation of a translated legal provision must display a mandatory disclaimer stating that the translation is not official, is provided for reference only, and must not be relied upon as the authoritative legal text before Thai courts or government authorities.

Where a translation is shown, the corresponding official Thai text must also be available and clearly identified as authoritative. Translation-only presentation is prohibited whenever the official source is available.

## Article 4 — Mandatory disclaimer policy

The disclaimer required by Article 3 is centrally governed — defined once, versioned, and reused everywhere a translation is rendered (web, API, AI-generated answers). No module or interface may render a translated legal provision without it.

## Article 5 — Platform-global versus firm-scoped legal data

Official legislation, regulations, official publications, reference translations, and licensed court decisions are platform-global legal reference data. This data must not use `FirmContext` as its ownership boundary — it exists once, for every Firm, not per Firm.

Firm notes, annotations, bookmarks, internal precedents, templates, matter links, saved research, and private AI context are firm-scoped and must remain strictly isolated by `FirmContext`, consistent with `docs/domain/06_Laravel_Module_Blueprint.md`.

Access between platform-global and firm-scoped data occurs only through published contracts or queries — never through shared internal Eloquent models across modules.

## Article 6 — Human legal oversight

AI output is advisory and remains subject to human professional review. The system must never imply that AI output constitutes legal advice or replaces a lawyer's professional judgment. This extends the AI governance principle already established in `AGENTS.md` and `docs/implementation/02_AI_Developer_Playbook.md` to all Legal Intelligence functionality specifically.

## Article 7 — Separation of official law from AI and editorial material

The architecture must distinguish, and never mix or conflate:

1. Official legal sources
2. Reference translations
3. Court decisions
4. Legal commentary
5. AI-generated explanations
6. Citations
7. Cross-references
8. Firm-owned research and annotations

AI-generated or editorial material must never be presented as official law.

## Article 8 — Immutable legal-source history

Published legal-source versions are immutable. Corrections and amendments create new versions or amendment records — historical versions never disappear and remain available. This mirrors the existing rule that historical migrations are never edited.

## Article 9 — Provenance and citation requirements

Every legal source must carry jurisdiction, language, effective date, amendment history, supersession history, official source reference, version, and an integrity hash sufficient to detect tampering or corruption.

`LegalCitation` is a first-class concept capable of representing the canonical Thai citation and, where available, an English reference citation, without conflating the two.

## Amendment

Amendments to this Constitution require an approved ADR and explicit human sign-off, consistent with the approval gates in `AGENTS.md`.
