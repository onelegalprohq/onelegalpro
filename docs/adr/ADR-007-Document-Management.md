# ADR-007 — Document Management

## Status

Accepted. The human owner has explicitly approved this decision.

## Context

Documents are named as an external dependency by nearly every architecture approved so far, yet no document defines what a document *is*, who owns it, or how access to it is authorized:

- Practice Management (`docs/adr/ADR-006-Practice-Management-Core.md`, `docs/architecture/13_Practice_Management_Architecture.md`) names "Documents" as a future bounded context in its Integration Boundaries, its Matter Timeline, and its Matter Dashboard, and explicitly states that Practice Management "never stores or versions document content."
- Communications (`docs/architecture/11_Communications_Hub_Architecture.md` §6) already anticipates "attachment extraction (making attached documents available to the Documents domain, subject to that module's own rules)" — a dependency on rules that do not yet exist.
- Digital Presence (`docs/architecture/12_Website_Client_Portal_Architecture.md` §4, §8, §12) surfaces Documents in the Client Portal and ships a Document Upload Widget, while stating that the Portal "owns none of this data" and naming the owning module as an explicit unresolved dependency.
- Branding (`docs/architecture/10_White_Label_Platform_Architecture.md` §9) applies letterhead and theme tokens to "generated documents" as "a presentation wrapper around canonical document content" — canonical content whose owner is undefined.
- Legal Intelligence (`docs/architecture/09_Legal_Intelligence_Architecture.md`) owns platform-global official legal sources and explicitly distinguishes itself from "Documents" in its Domain boundaries.

Documents are also where a legal platform's confidentiality risk concentrates most sharply. A law firm's document store holds privileged communications, work product, client identity material, and evidence under litigation hold. An access-control mistake here is not a UX defect — it is a professional-responsibility and privilege-waiver event. The co-client case is particularly unforgiving: a Matter with more than one `MatterClient` (`docs/architecture/13_Practice_Management_Architecture.md`, Matter Clients) can contain a document that one co-client must see and another must never see, and no architecture currently states how that is resolved for documents.

Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, this needs an approved architecture before any Documents implementation work begins, and before the dependent stages already staged in `docs/architecture/08_Roadmap.md` for Communications (attachment handling), Digital Presence (Client Portal Documents surface, Document Upload Widget), and Practice Management (Matter Timeline, Matter Dashboard) can be implemented.

## Decision

1. **Documents is its own bounded context**, proposed module `Documents`. It owns the canonical document record, all immutable document versions, file metadata, classifications, retention state, legal holds, and document-access policy. No other module owns any part of that.

2. **Practice Management owns Clients, Matters, `MatterClient`, `MatterTeam`, and Ethical Walls; Documents references those concepts by identifier and published contract only.** Documents never re-implements Matter membership, team roles, or wall logic, and never stores a copy of a `Client` or `Matter` record. Ethical Wall authorization is obtained exclusively through Practice Management's published `CheckEthicalWallAccess` query, per `docs/adr/ADR-006-Practice-Management-Core.md`, Decision 4.

3. **Legal Intelligence owns official legal sources; Documents must not duplicate that ownership.** A statute, regulation, or court decision ingested as platform-global authoritative content is a `LegalSource`/`CourtDecision` (`docs/architecture/09_Legal_Intelligence_Architecture.md`), not a `Document`. A Firm's own copy, exhibit, or annotated print of a legal text is firm work product and *is* a `Document` — the two are distinct records with distinct authority, and Documents never claims authority over official law.

4. **Billing owns invoice and payment records; Documents may store the rendered artifact but never the commercial meaning.** A rendered PDF invoice may live as a `Document` version so it can be retained, versioned, and surfaced in the Client Portal like any other file, but the invoice's amount, status, due date, and payment lifecycle remain owned by a future Billing bounded context. Deleting, archiving, or superseding the artifact never changes the invoice's commercial state, and vice versa.

5. **Branding provides presentation configuration for generated documents but never owns or alters canonical content.** Documents composes Branding's `PDFBrandingConfig` through the Branding Resolver (`docs/architecture/10_White_Label_Platform_Architecture.md` §9) at generation time. Branding is a presentation wrapper; no branding setting may change substantive document content, suppress a required disclaimer, or alter authoritative metadata.

6. **Communications may submit attachments through a published Documents command; it must never write to Documents tables or object storage directly.** This resolves the dependency `docs/architecture/11_Communications_Hub_Architecture.md` §6 already named. The `Message` retains its own attachment reference; the file itself becomes a `Document` version created through `UploadDocumentVersion`, subject to Documents' own validation, scanning, classification, and access rules.

7. **Digital Presence and the Client Portal access documents only through permission-aware published queries.** The Portal is a rendering surface (`docs/architecture/12_Website_Client_Portal_Architecture.md` §4); it never queries Documents' records directly, never receives a storage path, and never performs its own audience resolution.

8. **Document versions are immutable.** Replacing content creates a new version; historical bytes, checksums, authorship, timestamps, and provenance remain preserved. This is the same discipline `docs/architecture/09_Legal_Intelligence_Architecture.md` applies to published `LegalSource` versions and `docs/architecture/11_Communications_Hub_Architecture.md` applies to `Message` content, applied here to files.

9. **Document content is private by default, and Client Portal visibility is explicit and deny-by-default.** Internal availability never implies portal visibility. Publishing a document to the portal is a distinct, auditable human decision, never a side effect of uploading, classifying, or associating a document with a Matter.

10. **Matter-linked portal visibility resolves against a specific `MatterClient`, never against the Matter as an undifferentiated audience.** A document visible to one co-client must never become visible to another merely because both are attached to the same Matter. This carries `docs/architecture/13_Practice_Management_Architecture.md`'s Matter-Linked Item Client Scope invariant into the document domain, where its consequences are most severe.

11. **Matter permissions and Ethical Walls are inherited as mandatory restrictions.** Document-level controls may further restrict access; they may never widen it beyond the Matter boundary. Where a document is associated with more than one restricted Matter, the most restrictive outcome applies.

12. **AI/OCR extraction, summaries, classifications, and suggestions are derived annotations, not authoritative document content.** They carry model/source provenance and confidence, remain structurally separate from the source version, are removable without altering it, and never silently overwrite source content or authoritative metadata.

13. **Files remain private in object storage.** Access occurs only through authorized, short-lived delivery mechanisms issued after Firm, actor, Matter, Ethical Wall, and audience checks have all passed. There are no public, permanent, or guessable file URLs anywhere in the architecture.

14. **Legal holds override ordinary deletion and retention processing until explicitly released by an authorized human.** A hold is not a flag retention logic may weigh — it is a gate retention logic cannot pass.

15. **This architecture is conceptual only and schedules no implementation.** Full conceptual detail is recorded in `docs/architecture/14_Document_Management_Architecture.md`. EPIC-007 — Documents is proposed in `docs/architecture/08_Roadmap.md`; every stage requires its own approved story entry in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before implementation begins.

## Rationale

### Why Documents is its own bounded context

No existing or planned module is a plausible owner. Practice Management would have to absorb file storage, versioning, virus scanning, and retention — precisely the ownership `docs/adr/ADR-006-Practice-Management-Core.md`, Decision 1, refuses. Communications would own only the subset of files that happened to arrive as attachments, fragmenting the rest. Digital Presence would own only what a client can see. Billing would own only rendered invoices. Each would need its own notion of "a file, its versions, and who may read it," which is the duplication `docs/domain/06_Laravel_Module_Blueprint.md` exists to prevent. A single bounded context gives every one of those consumers one place to ask.

### Why immutable versions rather than mutable files

A document's evidentiary and professional value depends on being able to answer "what exactly did this say when we relied on it, and who put it there." Mutable file replacement destroys that answer silently. Immutability makes the answer a structural property rather than an operational discipline, consistent with how this platform already treats legal sources, messages, notes, and migrations.

### Why deny-by-default portal audience resolution

The failure mode being designed against is concrete: a co-client on a jointly represented Matter sees another co-client's confidential document because visibility was modeled at Matter granularity. Any default other than deny makes that leak a configuration oversight rather than an impossibility. Requiring an explicit, auditable audience decision per document makes the safe outcome the automatic one.

### Why access inheritance narrows but never widens

Two authorities govern a matter document: the Matter's own access rules (team membership, Ethical Walls) and the document's own controls. If document-level controls could widen access, every Ethical Wall in the platform would be one document setting away from being bypassed — the exact drift risk `docs/adr/ADR-006-Practice-Management-Core.md` centralized wall checking to prevent. Restriction-only composition makes the wall a floor that document settings cannot dig beneath.

### Why AI output is a separate annotation layer

OCR text is a lossy, probabilistic reconstruction of a source document. Treating it as content — or letting it write back into authoritative metadata — would let a recognition error become the record. Keeping derived output structurally separate, provenance-tagged, and removable preserves `docs/architecture/01_OneLegalPro_Constitution.md`, Article 7's separation discipline and `docs/architecture/05_AI_Architecture.md`'s provenance requirements in a domain where the source artifact is the evidence.

### Why fail-closed malware scanning

An unavailable scanner is not a reason to accept a file into a law firm's document store; it is a reason to hold it. Failing open would make the platform's safety depend on a third-party service's uptime. Quarantine until positively cleared is the only posture consistent with the fail-closed principle `docs/architecture/13_Practice_Management_Architecture.md`, Failure Modes, already applies to authorization checks.

## Alternatives considered

- **Fold Documents into Practice Management as a Matter sub-capability.** Rejected — makes `Matter` an unbounded aggregate owning years of file versions, contradicts `docs/adr/ADR-006-Practice-Management-Core.md`, Decision 1's explicit refusal to absorb Documents, and leaves firm-level (non-Matter) documents without an owner.
- **Let each consuming module store its own files (Communications its attachments, Digital Presence its portal uploads, Billing its invoices).** Rejected — produces four incompatible versioning, retention, scanning, and access models, no single answer to "every document on this Matter," and no coherent place to apply a legal hold.
- **Model documents as mutable files with an edit history sidecar.** Rejected — the audit record and the artifact would be separately falsifiable, and "what did the version we filed actually contain" becomes a reconstruction rather than a retrieval.
- **Treat official legal texts as Documents so there is one file model platform-wide.** Rejected — collapses the authoritative/non-authoritative distinction that `docs/architecture/01_OneLegalPro_Constitution.md`, Articles 2 and 7, make constitutional. A firm's exhibit copy of a statute and the official statute are different records with different authority.
- **Resolve portal visibility at Matter granularity, with per-client exceptions.** Rejected — makes exposure the default and confidentiality the exception, exactly inverted from the professional-responsibility requirement; a missed exception becomes a privilege incident.
- **Let OCR text replace or backfill document metadata automatically when confidence is high.** Rejected — high confidence is not authority, and a silent overwrite of authoritative metadata by a probabilistic process has no acceptable failure mode. Suggestions require human acceptance.
- **Issue long-lived or public URLs for portal document delivery, for CDN cacheability.** Rejected — a guessable or shareable permanent URL to privileged material defeats every access control above it; short-lived, authorization-gated delivery is the only sanctioned mechanism.
- **Specify a storage provider, malware scanner, OCR engine, or e-signature vendor in this architecture.** Rejected — provider selection is an implementation decision requiring its own approval; the architecture defines Infrastructure-layer adapter boundaries so any provider choice remains substitutable.

## Consequences

- Every Matter-linked document read requires a synchronous authorization dependency on Practice Management's published Ethical Wall and Matter-access contracts — a coupling cost accepted in exchange for uniform, non-drifting enforcement, the same trade-off `docs/adr/ADR-006-Practice-Management-Core.md` accepted.
- Immutable versioning means storage grows monotonically with every correction; archival tiering and retention policy manage cost, and deletion remains a governed, auditable path rather than an ordinary operation.
- Deny-by-default audience resolution means a Firm must take an explicit action for a client to see anything in the portal. This is deliberate friction, and it is the correct default for privileged material.
- Fail-closed scanning means a scanner outage blocks new uploads from becoming available rather than degrading silently — an availability cost accepted for a confidentiality and integrity guarantee.
- Documents becomes a dependency of Communications' attachment stage, Digital Presence's portal-documents and upload-widget stages, Practice Management's Timeline/Dashboard, and any future Billing artifact rendering. Those stages' sequencing must account for EPIC-007's foundation stages.
- A document associated with multiple restricted Matters resolves to the most restrictive outcome, which can make a legitimately-needed document unreadable to a user with access to only one of those Matters. This is the intended behavior, not a defect.

## Security implications

- Every document belongs to exactly one Firm; `FirmContext` isolation is enforced at application and repository layers, never by global scopes alone, per `docs/domain/06_Laravel_Module_Blueprint.md`.
- A caller denied access at any required boundary receives nothing — no content, metadata, search hit, preview, thumbnail, or existence confirmation. Existence disclosure is itself a confidentiality breach in a conflicts-sensitive domain.
- Preview images, thumbnails, extracted text, and any other derivative inherit the security boundary of the version they derive from; a derivative is never a weaker-protected copy.
- Encryption in transit and at rest is mandatory; storage credentials are secrets, never configuration.
- Uploads are constrained by size limits, extension and media-type validation, filename normalization, and malware scanning, and remain quarantined until positively accepted.
- Revoking portal visibility takes effect immediately for new access and never rewrites the audit history of prior authorized access.
- Legal hold cannot be bypassed by retention policy, archival, deletion request, or redaction.

## AI implications

Extending `docs/architecture/01_OneLegalPro_Constitution.md`, Article 6, and `docs/architecture/05_AI_Architecture.md` to this domain:

**AI may** perform OCR, classify document type, suggest metadata and tags, extract entities/dates/obligations/deadlines, summarize content, suggest Matter or Client links, detect possible duplicates, and suggest redactions.

**AI may not, without explicit human authorization,** change canonical document content, finalize/sign/file/send/publish/delete a document, change legal-hold or retention state, broaden internal or portal access, confirm a low-confidence Matter or Client association, treat OCR or extraction as authoritative over the source, remove or obscure provenance, or send privileged content to an unapproved model or processor.

The last prohibition is specific to this domain: document content is the platform's densest concentration of privileged material, so which processor sees it is itself an access-control decision, not merely an infrastructure choice.

## Future expansion

E-signature integration, court e-filing, advanced document automation, collaborative editing, full-text semantic search, redaction workflows, and OCR/conversion provider selection are all describable as future capabilities layered additively on the `Document`/`DocumentVersion`/`DocumentAccessPolicy` model. **None of them is claimed as implemented, architected in detail, or scheduled by this ADR.** Each requires its own architecture pass and its own approved implementation stories.

## Implementation status

This ADR and `docs/architecture/14_Document_Management_Architecture.md` are **conceptual architecture only**. They authorize no application code, migrations, schemas, dependencies, infrastructure, Docker configuration, CI changes, environment changes, storage-provider configuration, or runtime implementation. EPIC-007 — Documents is recorded as **proposed, not scheduled** in `docs/architecture/08_Roadmap.md`. `PF-*` story numbering and the approved repository implementation sequence are unchanged by this decision.
