# ADR-002 — Thailand-First Legal Intelligence Architecture

## Status

Accepted. The human owner has explicitly approved this decision.

## Context

OneLegalPro's product focus is Thai law firms, Thai lawyers, foreign-owned law firms operating in Thailand, and foreign lawyers working with Thai law. The platform must treat official Thai-language legal text as authoritative, treat translations (particularly English) as non-authoritative reference material, and support a distinct "Legal Intelligence" domain covering official sources, translations, court decisions, commentary, AI-generated explanations, citations, cross-references, and firm-owned research — without ever conflating AI or editorial material with official law.

Prior to this ADR, `docs/architecture/01_OneLegalPro_Constitution.md`, `docs/architecture/05_AI_Architecture.md`, and `docs/architecture/08_Roadmap.md` were empty placeholders, and no jurisdiction, legal-source, or translation model existed anywhere in the approved architecture. This ADR is the first populated architecture decision record for the platform (ADR-001 remains an empty stub).

## Decision

1. Adopt Thailand as the platform's first and default jurisdiction, modeled as a first-class, policy-configured domain concept — never a hardcoded conditional.
2. Adopt official Thai-language statutory, regulatory, and judicial text as the sole authoritative legal source for Thailand. Translations are reference-only and must never be presented as, or substituted for, official text. Where an English translation conflicts with the official Thai text, the Thai text prevails.
3. Require a mandatory, centrally governed disclaimer on every presentation of translated legal text, and require the corresponding official Thai text to be available and clearly identified as authoritative wherever a translation is shown.
4. Establish "Legal Intelligence" as the strategic domain name, with an initial Laravel module named `LegalIntelligence`, distinguishing official sources, reference translations, court decisions, legal commentary, AI-generated explanations, citations, cross-references, and firm-owned research and annotations as separate concepts that must never be mixed or presented as one another.
5. Treat official legislation, regulations, official publications, reference translations, and licensed court decisions as platform-global legal reference data, explicitly not owned by `FirmContext`. Treat firm notes, annotations, bookmarks, internal precedents, templates, matter links, saved research, and private AI context as firm-scoped, strictly isolated by `FirmContext`. Cross-boundary access occurs only through published contracts or queries, never shared Eloquent models.
6. Require jurisdiction and language metadata on every legal source, and design the aggregate model so future jurisdictions can be added through configuration and data, not redesign.
7. Require immutable published legal-source versions; corrections and amendments always create new versions or amendment records, with full provenance, source references, effective dates, amendment history, supersession history, and integrity hashes preserved.
8. Require AI retrieval to prioritize authoritative Thai-language sources, preserve provenance/jurisdiction/language/authority/version metadata through every retrieved chunk, cite the canonical Thai source in any answer involving Thai law, and remain advisory and subject to human professional review at all times.
9. Establish `LegalCitation` as a first-class concept supporting the canonical Thai title, provision/section identifier, official source or Royal Gazette reference, effective date, version/amendment status, Thai citation format, and an optional English reference citation — extensible to future citation styles without changing the canonical model.
10. Model court decisions distinctly from legislation and regulations, with independently modeled authority, court, case number, decision date, publication source, and precedential status, and with licensing/source-rights metadata preserved rather than assumed.

Full conceptual detail is recorded in `docs/architecture/09_Legal_Intelligence_Architecture.md`. Implementation-facing rules are recorded in `docs/architecture/05_AI_Architecture.md` (AI retrieval/generation) and `docs/architecture/01_OneLegalPro_Constitution.md` (governing principles).

## Alternatives considered

- **Treat English translations as co-equal or primary content, with Thai as a secondary reference.** Rejected — inverts the legal reality that only the Thai text has legal effect in Thailand, and risks users relying on non-authoritative text in proceedings before Thai courts or authorities.
- **Model legal sources as firm-scoped data, duplicated per Firm.** Rejected — official legislation and licensed court decisions are the same for every Firm; firm-scoped duplication would waste storage, create update-consistency risk across Firms, and blur the Firm-isolation boundary that is supposed to protect firm-private data, not shared reference data.
- **Hardcode Thailand-specific logic directly into a general "legal document" module rather than introducing jurisdiction as a first-class concept.** Rejected — would require redesigning the aggregate model to add any future jurisdiction, contradicting the platform's stated goal of remaining extensible.
- **Treat AI-generated legal explanations as part of the same content stream as official law (single unified "legal content" feed).** Rejected — directly conflicts with the requirement that AI-generated or editorial material must never be presented as official law, and would make disclaimer and authority enforcement structurally difficult.
- **Defer disclaimer enforcement to the UI layer only, as a per-screen convention rather than a structural rule.** Rejected — would allow future screens or API consumers to omit the disclaimer by omission rather than by explicit decision; disclaimer propagation must be driven by document/chunk metadata so it cannot be silently bypassed.

## Consequences

- A new domain concept, `Jurisdiction`, becomes mandatory on every legal source from the first implementation story, even though only Thailand exists initially.
- A new module, tentatively `LegalIntelligence`, will be platform-global for its official-source and translation aggregates, and will need an explicit exception or clarification against the default Firm-scoped assumption in `docs/domain/06_Laravel_Module_Blueprint.md`.
- AI retrieval and generation pipelines must carry provenance metadata through every stage (retrieval, ranking, generation, presentation), which is more complex than plain-text RAG and must be designed in from the start rather than retrofitted.
- A centrally governed, versioned disclaimer string becomes a piece of legal/compliance-owned configuration, not a hardcoded UI string.
- `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` will need a proposed Legal Intelligence epic entry (see `docs/architecture/08_Roadmap.md`) before any implementation story can begin; this ADR approves the architecture, not an implementation schedule.

## Platform-global versus firm-scoped decision

Official legislation, regulations, official publications, reference translations, and licensed court decisions are platform-global legal reference data and must not use `FirmContext` as their ownership boundary. Firm notes, annotations, bookmarks, internal precedents, templates, matter links, saved research, and private AI context are firm-scoped and must remain strictly isolated by `FirmContext`. Any module needing both must access the other's data only through published contracts or queries, never shared Eloquent models, per `docs/domain/06_Laravel_Module_Blueprint.md`.

## Authority and translation rules

Official Thai-language text is authoritative. Translations are reference-only, never authoritative, and always require the mandatory disclaimer (see `docs/architecture/01_OneLegalPro_Constitution.md`, Articles 2–4, for exact wording requirements and `docs/architecture/09_Legal_Intelligence_Architecture.md` for the disclaimer-rendering mechanism). Translation-only presentation is prohibited where an official source is available.

## AI implications

AI retrieval must prioritize authoritative Thai-language sources, preserve provenance through the full pipeline, and cite the canonical Thai source for any answer involving Thai law. AI output remains advisory and subject to human professional review; it must never be presented as, or imply that it constitutes, official law or a substitute for lawyer judgment. Full detail in `docs/architecture/05_AI_Architecture.md`.

## Future multi-jurisdiction implications

Jurisdiction is modeled as a first-class, policy-configured concept from the first implementation story. Adding a jurisdiction beyond Thailand should require adding a `Jurisdiction` record and its associated policy configuration (default-source authority rules, disclaimer templates, citation format), not a redesign of the `LegalSource`, `Translation`, `LegalCitation`, or AI retrieval models.

## Security and licensing considerations

Licensed court decisions and other licensed source material carry source-rights and licensing metadata that must be preserved and enforced — the system must not assume all Thai court decisions are freely reusable. Integrity hashes protect published legal-source versions against undetected tampering or corruption. Access boundaries between platform-global legal data and firm-scoped data must be enforced at the application and repository layers, not by convention alone, consistent with the tenancy and security rules in `docs/domain/06_Laravel_Module_Blueprint.md`.
