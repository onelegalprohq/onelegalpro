# ARCH-009 — Legal Intelligence Architecture

**Status:** Approved (conceptual architecture) — implementation stories are proposed, not scheduled; see `docs/architecture/08_Roadmap.md`.

## Purpose and scope

This document defines the conceptual domain and system architecture for OneLegalPro's Legal Intelligence capability, implementing `docs/adr/ADR-002-Thailand-First-Legal-Intelligence.md` and the relevant articles of `docs/architecture/01_OneLegalPro_Constitution.md`. It covers official legal sources, reference translations, court decisions, legal commentary, AI-generated explanations, citations, cross-references, and firm-owned research for Thailand, designed to extend to future jurisdictions.

This document describes **conceptual models only**. It does not define migrations, Eloquent schemas, or implementation code — those belong to future, separately approved implementation stories (see `docs/architecture/08_Roadmap.md`).

## Domain boundaries

Legal Intelligence is a distinct domain from Firm/Matter management, Documents, and Billing. It must never mix:

1. **Official legal sources** — enacted statutes, regulations, and official publications.
2. **Reference translations** — non-authoritative renderings of an official source in another language.
3. **Court decisions** — judicial rulings, modeled distinctly from legislation/regulations.
4. **Legal commentary** — editorial/secondary material about the law.
5. **AI-generated explanations** — model-generated summaries or answers.
6. **Citations** — structured references to any of the above.
7. **Cross-references** — links between legal sources (e.g., an amending act referencing the act it amends).
8. **Firm-owned research and annotations** — a Firm's private notes, bookmarks, and saved research referencing any of the above.

Each is a distinct concept in the domain model, even where they share infrastructure (storage, search index).

## Platform-global and firm-scoped subdomains

| Subdomain | Scope | Ownership boundary |
|---|---|---|
| Official legal sources, official publications, reference translations, licensed court decisions | Platform-global | Not `FirmContext` — shared reference data across every Firm |
| Legal commentary (platform-curated, if any) | Platform-global | Not `FirmContext` |
| Citations, cross-references between platform sources | Platform-global | Not `FirmContext` |
| Firm notes, annotations, bookmarks | Firm-scoped | `FirmContext` |
| Internal precedents, templates derived from firm work | Firm-scoped | `FirmContext` |
| Matter links (a Firm's Matter referencing a legal source) | Firm-scoped | `FirmContext` — the link is firm-owned; the referenced source is not |
| Saved research, private AI context | Firm-scoped | `FirmContext` |

A firm-scoped record may **reference** a platform-global record (e.g., a saved-research entry pointing at a `LegalSource` by ID), but never **own**, copy into its own schema, or bypass the platform-global module's published contracts to reach it. This is the same discipline `docs/domain/06_Laravel_Module_Blueprint.md` requires between any two modules, applied here to a platform-global-vs-firm-scoped boundary rather than module-to-module.

## Proposed module structure

**Unresolved implementation choice:** exact module name and whether platform-global and firm-scoped concerns are one module or two. This document proposes a single module, `LegalIntelligence`, with an internal split, as the simplest starting point; a future ADR may split it into `LegalIntelligence` (platform-global) and a firm-scoped `LegalResearch` module if the boundary proves easier to enforce as separate modules.

```text
LegalIntelligence/
├── Application/
│   ├── PlatformGlobal/   (RegisterLegalSource, PublishAmendment, LinkTranslation,
│   │                      IngestCourtDecision, GetOfficialLegalText, GetLegalCitation, ...)
│   └── FirmScoped/       (SaveResearchNote, BookmarkLegalSource, LinkSourceToMatter, ...)
├── Domain/
│   ├── PlatformGlobal/   (LegalSource, Translation, CourtDecision, LegalCitation,
│   │                      Jurisdiction, Amendment, CrossReference — see below)
│   └── FirmScoped/       (ResearchNote, Bookmark, MatterLegalLink)
├── Infrastructure/       (Eloquent adapters, ingestion adapters, search index adapters)
├── Interface/            (disclaimer rendering, side-by-side viewer, citation rendering, API)
├── Database/             (new migrations only — no historical migrations touched)
├── Routes/
├── Tests/
├── Config/               (jurisdiction policy, disclaimer text version, citation style config)
├── ModuleServiceProvider.php
└── README.md
```

Dependency direction and cross-module rules follow `docs/domain/06_Laravel_Module_Blueprint.md` unchanged: Interface → Application → Domain; Domain never depends on Laravel/Eloquent/HTTP; firm-scoped Application code reaches platform-global data only through published queries, never through the platform-global module's Eloquent records directly.

## Aggregates, entities, and value objects

**Aggregates**

- `LegalSource` — the aggregate root for an official legislative, regulatory, or judicial text at a given version. Enforces immutability of published versions and records amendments/supersession as domain events.
- `CourtDecision` — a separate aggregate (not a subtype of `LegalSource`) for judicial rulings, reflecting its distinct authority model, licensing terms, and precedential-status concerns.

**Entities** (owned by an aggregate, identity matters, mutable within the aggregate's lifecycle rules)

- `Translation` — belongs to a `LegalSource` via `translation_of`; never authoritative; carries its own language and provenance.
- `Amendment` — an append-only record of a change to a `LegalSource`, linking the prior and resulting versions.
- `CrossReference` — a directional link between two `LegalSource` and/or `CourtDecision` records (see Legal graph, below).

**Value objects** (immutable, no identity)

- `Jurisdiction` — jurisdiction code, name, official language(s); Thailand (`TH`) is the seeded default via `PlatformDefaultJurisdictionPolicy`, not a conditional.
- `LegalSourceAuthority` — whether a given record is authoritative (`true` only for official Thai originals) or reference-only.
- `DocumentType` — `Legislation` / `Regulation` / `Translation` (where modeled as a type rather than a separate entity — see note below) / `Commentary`.
- `LegalCitation` — see Citation model, below.
- `EffectiveDate`, `VersionNumber`, `IntegrityHash`, `SourceReference` (e.g., Royal Gazette reference), `LicensingTerms`.

**Unresolved implementation choice:** whether `Translation` is modeled as its own entity (as described above) or as a `LegalSource` record with `DocumentType = Translation` and `translation_of` set. This document assumes the former (a distinct entity) because a translation's lifecycle, authority, and validation rules differ enough from an authoritative source's to warrant a separate concept; a future ADR may revisit this if implementation experience shows otherwise.

## Official-source and translation relationship

- Every `Translation` references exactly one canonical `LegalSource` via `translation_of`. A `LegalSource` may have zero or more `Translation` records (e.g., one per target language).
- A `Translation` is never authoritative and never stands alone in presentation when its `LegalSource` is available (Constitution Article 3).
- Conflict rule: if a `Translation`'s content appears to diverge from its `LegalSource` (flagged manually or by future automated checks), the `LegalSource` prevails and the divergence should be recorded as metadata on the `Translation`, not resolved by preferring the translation.

## Court-decision model

`CourtDecision` is modeled independently of `LegalSource`, with:

- Court identity, case number, decision date, publication source.
- Precedential status (as a distinct, explicit field — not inferred).
- Licensing/source-rights metadata (see below) — the system must not assume a court decision is freely reusable by default; reusability is an explicit, sourced fact per decision.
- Optional `CrossReference` links to the `LegalSource` records it interprets or applies.

## Citation model

`LegalCitation` is a first-class value object, not a derived string, supporting:

- Canonical Thai title
- Provision or section identifier
- Official source or Royal Gazette reference
- Effective date
- Version or amendment status
- Thai citation format (rendering rule)
- English reference citation, where available (always additional to, never instead of, the Thai citation)

The rendering format (Thai citation style, English reference style) is a presentation concern layered on top of this canonical model, so that **future citation styles can be added without changing the canonical `LegalCitation` structure** — new styles are new renderers, not new data models.

**Unresolved implementation choice:** the exact Thai citation format string/grammar is not specified here, since inventing exact Thai legal citation conventions is out of scope for this architecture document (see Quality requirements in the originating task — exact statutory text, case citations, and citation-format specifics must come from a qualified Thai legal source, not be invented).

## Immutable versioning

- A published `LegalSource` version is immutable once published — the same discipline as historical migrations never being edited.
- Corrections and amendments always produce a new version or an `Amendment` record; they never mutate a published version in place.
- Every version remains retrievable indefinitely — historical versions are not deleted or hidden, only marked superseded.
- Supersession is explicit (`supersedes` / `superseded_by` links), so the current-vs-historical state of any provision is always determinable.

## Provenance

Every `LegalSource`, `Translation`, and `CourtDecision` record carries, at minimum:

- Jurisdiction and language
- Authority status
- Official source reference
- Version and effective date
- Amendment and supersession lineage
- Integrity hash of the captured content
- Ingestion timestamp and last-verified timestamp

Provenance is not just stored — per `docs/architecture/05_AI_Architecture.md`, it must survive retrieval and generation so that any AI-composed answer can be traced back to its source record.

## Ingestion and verification pipeline

Conceptual stages (implementation details are a future story, per the Roadmap):

1. **Intake** — acquisition of official text from its authoritative publication channel (e.g., Royal Gazette or official court registry publication), recorded with its source reference.
2. **Integrity capture** — computation of an integrity hash over the captured content at intake time.
3. **Structuring** — parsing into citable units (e.g., sections/provisions) sufficient to support the citation model, without altering the captured official text.
4. **Verification** — a human or verified-process confirmation step before a record is marked authoritative and published; unverified intake remains in a draft/unpublished state, never presented as authoritative.
5. **Publication** — the version becomes immutable and citable; supersession of any prior version is recorded explicitly.
6. **Translation linking** — translations are linked post-publication via `translation_of`; a translation's own provenance (translator/source) is recorded separately from the original's.

**Unresolved implementation choice:** whether verification requires a specific licensed legal-data provider integration or manual firm/editorial verification, or both. This is a sourcing/licensing decision outside this architecture document's scope.

## Metadata requirements

At minimum, every legal source record requires: jurisdiction, language, document type, authority status, official source reference, version, effective date, amendment/supersession links, integrity hash, ingestion and last-verified timestamps, and (for court decisions and any licensed material) licensing/source-rights metadata. See `docs/architecture/01_OneLegalPro_Constitution.md`, Article 9, for the governing principle.

## Disclaimer rendering policy

- The disclaimer is centrally defined, versioned configuration (see `docs/architecture/01_OneLegalPro_Constitution.md`, Article 4), not a hardcoded string per view.
- Baseline wording (governed centrally, substantially equivalent to):

  > "Reference Translation Notice: This English translation is provided for convenience and general reference only. It is not an official translation of Thai law and must not be relied upon as the authoritative legal text in proceedings before Thai courts or government authorities. In the event of any inconsistency or ambiguity, the official Thai-language text prevails."

- Rendering is driven by record metadata (`is_authoritative = false`, or `document_type = Translation`), not by which screen or endpoint is being used — so it cannot be omitted by adding a new presentation surface.
- Applies uniformly across web UI, API responses, and AI-generated answers that surface translated content (see `docs/architecture/05_AI_Architecture.md`, Disclaimer propagation).
- Translation-only presentation (no accompanying official Thai text, when one exists) is prohibited; the Interface layer must render or link the official text alongside the translation.

## AI retrieval architecture

Defined in full in `docs/architecture/05_AI_Architecture.md`. In summary, from the Legal Intelligence domain's perspective: retrieval indexes are built from provenance-tagged chunks of `LegalSource`, `Translation`, and `CourtDecision` content; ranking prioritizes authoritative Thai sources; generation must preserve and surface provenance for citation and disclaimer purposes; AI-generated explanations are stored/tagged distinctly from retrieved official content so they are never confused with it (Domain boundary #5 above).

## Legal graph and cross-reference direction

`CrossReference` is directional (`from_source_id` → `to_source_id`, with a relationship type such as `amends`, `repeals`, `implements`, `interprets`). Directionality matters for two reasons:

- An amending act's cross-reference to the act it amends is not symmetric with the amended act's (retroactive) awareness of its amendment — the graph must be able to answer "what amended this" and "what did this amend" as distinct traversals.
- Court decisions interpreting legislation are linked `interprets` from the decision to the source, keeping the interpretive relationship distinct from a textual amendment relationship.

The graph is platform-global data (Legal Intelligence domain), separate from any firm-scoped `MatterLegalLink` that references into it.

## Storage strategy

**Unresolved implementation choice:** exact storage backend split. Conceptually:

- Structured metadata (jurisdiction, version, dates, citation fields, authority status, hashes) belongs in PostgreSQL, consistent with the platform's existing database rule (`docs/domain/06_Laravel_Module_Blueprint.md`) and UUIDv7 identity.
- Full original captured text (official scans/PDFs, large judicial-decision text) may be better suited to object storage referenced by the PostgreSQL record, to keep the relational schema focused on citable, queryable metadata rather than large blobs — this split is a candidate, not a final decision, and belongs in a future implementation-focused ADR once ingestion volume and search requirements are known.
- Search/indexing infrastructure (for Thai legal search and AI retrieval) is a separate concern layered on top of the canonical store, not the source of truth itself.

## Access-control boundaries

- Platform-global Legal Intelligence data (official sources, translations, licensed court decisions) is readable across all Firms by design — it is not Firm-isolated, because it is not Firm-owned.
- Firm-scoped data (notes, annotations, saved research, matter links, private AI context) is enforced under the same `FirmContext` discipline as the rest of the platform: explicit context carrying Firm ID/Actor ID/membership, enforced at application, repository, and database-policy layers — global scopes alone are insufficient, per `docs/domain/06_Laravel_Module_Blueprint.md`.
- Ethical Walls, where applicable, apply to firm-scoped research and matter links (since those can reveal a Firm's matter-specific interests), not to the platform-global legal sources themselves.
- Any write path that could alter a published, platform-global `LegalSource` requires the same elevated approval discipline as other destructive/high-impact operations in `AGENTS.md` — ordinary application code and AI-assisted changes must never write to authoritative legal records directly.

## Licensing and source-rights metadata

Court decisions and any third-party-licensed material carry explicit licensing/source-rights metadata (licensor, permitted use, redistribution terms, expiry if any). The system must never assume free reusability by default — absence of recorded licensing terms should be treated as "rights unknown," not "freely reusable."

## Audit requirements

- Every publish, amendment, supersession, and translation-link action is an auditable domain event (consistent with the platform's event-driven integration model).
- AI-generated explanations referencing Legal Intelligence content should be traceable back to the retrieved sources that informed them, supporting the human-review requirement in `docs/architecture/05_AI_Architecture.md`.
- Firm-scoped actions (saving research, linking a matter to a source) are audited under the same tenancy/audit discipline as other firm-scoped data.

## Failure modes

Conceptual failure modes the architecture must account for (mitigations are implementation-story-level detail):

- **Missing official source for an existing translation** — must be surfaced as a gap, not silently treated as if the translation were authoritative.
- **Stale or superseded source served as current** — mitigated by explicit `status`/supersession metadata and effective-date checks; must never fail silently.
- **Ingestion integrity failure** (hash mismatch on re-verification) — must block publication or flag the existing record for review, not be ignored.
- **Retrieval losing provenance mid-pipeline** — must be treated as a retrieval error, not allowed to produce an unattributed answer.
- **Licensing metadata missing for court decisions** — must default to restrictive assumptions (rights unknown), not permissive ones.
- **Cross-Firm data leakage at the platform-global/firm-scoped boundary** — mitigated by the published-contract-only access rule; any direct cross-boundary Eloquent access is a defect, not a variant.

## Future multi-jurisdiction extension

Adding a jurisdiction beyond Thailand should require, at most:

1. A new `Jurisdiction` record and its policy configuration (authoritative-language rule, disclaimer template, citation-format renderer).
2. New `LegalSource`/`Translation`/`CourtDecision` data tagged with the new jurisdiction.

It should **not** require changes to the `LegalSource`, `Translation`, `CourtDecision`, `LegalCitation`, or AI retrieval aggregate/model shapes, since jurisdiction, authority, and language are already first-class fields on every record from the first implementation story.

## Phased implementation guidance

See `docs/architecture/08_Roadmap.md`, EPIC-002, for the proposed staged delivery order (jurisdiction foundation → legal-source aggregate/metadata → ingestion pipeline → translation linking → citation engine → disclaimer enforcement → Thai legal search → AI retrieval → court-decision ingestion → firm annotations/saved research → amendment tracking/legal graph). That staging is proposed only; formal scheduling requires entries in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` and separate story-level approval before implementation begins.
