# ADR-007 — Document & Knowledge Management

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

### The Knowledge gap

Storing a Firm's documents safely solves only half the problem this context exists for. A law firm's second asset is its **know-how**: the precedent that worked, the clause that survived negotiation, the playbook for a filing, the practice note recording how a court actually behaves. Today that material has no owner anywhere in the approved architecture, and the modules that touch adjacent concepts are all the wrong owner:

- **Legal Intelligence** (`docs/architecture/09_Legal_Intelligence_Architecture.md`) owns *official* law — statutes, regulations, court decisions, authoritative translations, citations. Firm know-how is explicitly *not* that; Constitution Article 7 already separates "legal commentary" and "firm-owned research and annotations" from official sources, and collapsing know-how into Legal Intelligence would destroy exactly that boundary.
- **Digital Presence** (`docs/architecture/12_Website_Client_Portal_Architecture.md`, Knowledge Publishing) owns *public* editorial content — website articles, FAQs, legal updates a Firm publishes to the world. Internal precedents and playbooks are the opposite of public, and a model that conflates the two makes accidental publication a one-field mistake.
- **Practice Management** owns the Matter the know-how came *from*, not the reusable asset it becomes.
- **Documents**, as decided above, owns the source artifact — but a source document and a curated, approved, reusable knowledge asset have different authority, different lifecycles, different audiences, and different governance.

The dangerous case is specific and it is the reason this needs architecture before code: **a document that lives on a confidential Matter must not become Firm-wide knowledge merely because someone found it useful.** Promotion from Matter work product to reusable know-how crosses a confidentiality, privilege, contractual, and personal-data boundary in one step. Without an explicit, human-gated curation workflow, the natural implementation — "mark this document as a precedent" — silently republishes one client's confidential material to the whole Firm, and an Ethical Wall becomes a checkbox away from irrelevance.

AI retrieval sharpens this further. The obvious use of Firm know-how is retrieval-augmented drafting, which means knowledge content flows into model context. If retrieval is not permission-filtered *before* results and context are assembled, RAG becomes the single largest confidentiality bypass in the platform — one that no post-generation filter can reliably repair.

This ADR therefore covers **one bounded context with two explicitly separated domain areas**: Document Management (the source artifact) and Knowledge Management (the curated, approved, reusable asset).

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

15. **The proposed Laravel module remains `Documents`, containing two explicitly separated domain areas.** Existing approved architectures (`docs/architecture/11_Communications_Hub_Architecture.md` §6, `docs/architecture/12_Website_Client_Portal_Architecture.md` §4/§8, `docs/architecture/13_Practice_Management_Architecture.md`) already depend on "a future Documents module" by that name; renaming it would invalidate those references for no domain benefit. The module therefore keeps the name `Documents` while the bounded context contains **Document Management** and **Knowledge Management** as separate, explicitly bounded domain areas with their own aggregates, lifecycles, and governance — not one blended store. See Why one module with two domain areas, below.

### Knowledge Management decisions

16. **This bounded context owns both Firm-created legal work product and curated Firm know-how.** Document Management and Knowledge Management are two explicitly separated domain areas within one context, not two contexts and not one undifferentiated store.

17. **A source `Document` and a curated `KnowledgeItem` are distinct records.** They differ in authority (an artifact of what was done vs. approved guidance on how to do it), lifecycle (upload/version/retain vs. draft/review/approve/supersede/retire), audience (Matter-scoped vs. deliberately reusable), and governance (retention and legal hold vs. approval, ownership, and periodic review). Neither is a state of the other, and a `Document` is never silently reinterpreted as knowledge.

18. **Knowledge covers precedents, clauses, templates, playbooks, practice notes, research notes, internal guidance, and approved Firm know-how.** Each is a `KnowledgeItem` distinguished by classification, not a separate parallel store.

19. **Official statutes, regulations, court decisions, citations, and authoritative legal translations remain owned by Legal Intelligence.** Knowledge may reference and cite them by identifier; it never duplicates, replaces, or claims authority over them, and Firm know-how is never presented as official law (Constitution Articles 2 and 7).

20. **Public website articles, FAQs, and legal updates remain owned by Digital Presence as public editorial content.** An approved `KnowledgeItem` may be *proposed or supplied* for public publication through a published contract, but public presentation, scheduling, and the public `ContentItem` lifecycle stay with Digital Presence. Publishing publicly never silently changes the internal item's access policy.

21. **Communications may reference Knowledge Items through published contracts but does not own them.** A thread or message may link to a `KnowledgeItem`; Communications stores and governs no canonical knowledge content.

22. **Practice Management owns Clients, Matters, `MatterClient`s, `PracticeArea`s, `MatterTeam`s, and Ethical Walls.** Knowledge references all of them by identifier and published contract only.

23. **A Matter document never automatically becomes Firm-wide knowledge.** There is no configuration, classification, tag, folder, or AI confidence level that promotes a `Document` into a `KnowledgeItem`. Automatic promotion is not an unimplemented feature; it is prohibited.

24. **Matter-to-knowledge curation requires an explicit human workflow** with confidentiality, privilege, contractual-restriction, personal-data, and Ethical Wall review before any reuse audience is established.

25. **Removing names does not make content safely de-identified.** De-identification review must consider re-identification risk from context — matter facts, dates, amounts, jurisdictions, party roles, and distinctive drafting — not merely the presence of proper nouns.

26. **Every approved `KnowledgeItem` has a Firm, a named owner, an immutable approved version, provenance, an approval record, an access policy, and a review policy.** An approved item missing any of these is invalid, not merely incomplete.

27. **Knowledge access may narrow Matter-derived access but never override Ethical Walls.** The restriction-only composition rule decided above for documents applies identically to knowledge, and wall authorization still comes only from Practice Management's published check.

28. **Knowledge search and AI retrieval are permission-filtered before results or context are returned** — never filtered after retrieval, and never repaired after generation.

29. **Draft, rejected, superseded, retired, inaccessible, and quarantined knowledge is excluded from approved retrieval by default.** `RetrievalEligibility` is an explicit property, not an inference from lifecycle state alone.

30. **AI may suggest and draft knowledge but may never approve, publish, declassify, broaden access, or make Matter-derived content Firm-wide.** Those five actions are human-authorized without exception.

31. **Knowledge embeddings, indexes, snippets, citations, and analytics remain Firm-isolated and permission-aware.** A derived index entry inherits the access boundary of the content it derives from; cross-Firm retrieval, embedding, caching, evaluation data, and model context are prohibited.

32. **This remains conceptual architecture and schedules no implementation.** Full conceptual detail is recorded in `docs/architecture/14_Document_Knowledge_Management_Architecture.md`. EPIC-007 — Documents & Knowledge Management is proposed in `docs/architecture/08_Roadmap.md`; every stage requires its own approved story entry in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` before implementation begins.

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

### Why one module with two domain areas

Three already-approved architectures name "a future Documents module" as their dependency. Renaming the module to something like `KnowledgeAndDocuments` would invalidate those references and force edits to three settled documents in exchange for no domain clarity. Meanwhile, splitting Documents and Knowledge into two separate modules would put the curation workflow — the single most safety-critical operation in this context — permanently across a module boundary, forcing the confidentiality, privilege, and de-identification review to coordinate through published contracts between two modules that must already share provenance, Firm isolation, Ethical Wall integration, and retrieval-eligibility semantics.

Keeping one module named `Documents` with two explicitly separated domain areas gets both: existing references stay valid, and the boundary that actually matters — *source artifact* versus *approved reusable asset* — is enforced by distinct aggregates, distinct lifecycles, distinct access policies, and distinct retrieval eligibility rather than by a module wall. The separation is real and architecturally enforced; it is simply enforced inside one module rather than between two. A future ADR may split them if implementation experience shows the internal boundary is not holding — the same posture `docs/architecture/09_Legal_Intelligence_Architecture.md` takes toward a possible `LegalIntelligence`/`LegalResearch` split.

### Why a Document and a Knowledge Item are separate records

They answer different questions. A `Document` answers "what did we actually file, send, or receive, and when" — its value is evidentiary and its integrity requirement is immutability of the artifact. A `KnowledgeItem` answers "how does this Firm do this, as approved guidance" — its value is reusability and its integrity requirement is that someone accountable approved it and keeps it current. Merging them would force one record to carry two incompatible lifecycles: a document has no meaningful "approved by" or "due for review," and a knowledge item has no meaningful "malware scan" or "client portal audience." More importantly, merging them would make promotion from confidential artifact to Firm-wide guidance a field update rather than a governed workflow — which is precisely the failure this ADR exists to prevent.

### Why curation must be human-gated and cannot be automated

Promotion crosses four independent legal boundaries at once: confidentiality (is this client's information), privilege (does reuse risk waiver), contract (does an engagement letter or NDA restrict reuse), and personal data (does PDPA/GDPR permit this secondary use). No classifier can adjudicate all four, and the cost of a wrong answer is not a bad search result — it is a disclosure of one client's confidential material to everyone in the Firm, potentially including a team walled off from that Matter. Requiring an authorized human decision makes the safe outcome structural rather than probabilistic.

### Why de-identification is not name removal

Legal work product is re-identifiable from context far more often than from names. A precedent retaining an unusual transaction value, a specific filing date, a niche jurisdiction, and a distinctive fact pattern identifies its source matter to anyone in the practice area, with every proper noun stripped. Treating name removal as sufficient would give false assurance at exactly the moment assurance matters most, so the architecture requires re-identification-risk review rather than a redaction pass.

### Why permission filtering must precede retrieval

Post-generation filtering cannot work as a primary control. Once inaccessible content enters model context it has already influenced the output, and no downstream filter can reliably detect or unwind that influence — the leak may surface as paraphrase, structure, or inference rather than quotation. Filtering before retrieval, and treating embeddings and index entries as inheriting their source's access boundary, is the only design where an unauthorized item cannot influence a result it should never have reached.

### Why retrieval eligibility is explicit rather than inferred

Approved-and-current is not the same question as safe-to-retrieve. An item may be approved but overdue for review, approved but derived from a source now under legal hold, or approved but restricted after an access-policy change. Making `RetrievalEligibility` an explicit, revocable property lets any of those conditions remove an item from future retrieval immediately, without needing to reason backwards from lifecycle state — and without rewriting the audit history that shows it was legitimately retrieved before.

## Alternatives considered

- **Fold Documents into Practice Management as a Matter sub-capability.** Rejected — makes `Matter` an unbounded aggregate owning years of file versions, contradicts `docs/adr/ADR-006-Practice-Management-Core.md`, Decision 1's explicit refusal to absorb Documents, and leaves firm-level (non-Matter) documents without an owner.
- **Let each consuming module store its own files (Communications its attachments, Digital Presence its portal uploads, Billing its invoices).** Rejected — produces four incompatible versioning, retention, scanning, and access models, no single answer to "every document on this Matter," and no coherent place to apply a legal hold.
- **Model documents as mutable files with an edit history sidecar.** Rejected — the audit record and the artifact would be separately falsifiable, and "what did the version we filed actually contain" becomes a reconstruction rather than a retrieval.
- **Treat official legal texts as Documents so there is one file model platform-wide.** Rejected — collapses the authoritative/non-authoritative distinction that `docs/architecture/01_OneLegalPro_Constitution.md`, Articles 2 and 7, make constitutional. A firm's exhibit copy of a statute and the official statute are different records with different authority.
- **Resolve portal visibility at Matter granularity, with per-client exceptions.** Rejected — makes exposure the default and confidentiality the exception, exactly inverted from the professional-responsibility requirement; a missed exception becomes a privilege incident.
- **Let OCR text replace or backfill document metadata automatically when confidence is high.** Rejected — high confidence is not authority, and a silent overwrite of authoritative metadata by a probabilistic process has no acceptable failure mode. Suggestions require human acceptance.
- **Issue long-lived or public URLs for portal document delivery, for CDN cacheability.** Rejected — a guessable or shareable permanent URL to privileged material defeats every access control above it; short-lived, authorization-gated delivery is the only sanctioned mechanism.
- **Specify a storage provider, malware scanner, OCR engine, or e-signature vendor in this architecture.** Rejected — provider selection is an implementation decision requiring its own approval; the architecture defines Infrastructure-layer adapter boundaries so any provider choice remains substitutable.

**Knowledge Management alternatives**

- **Model knowledge as a document classification (`DocumentType = Precedent`) rather than its own record.** Rejected — makes promotion a field update on a Matter-scoped, client-confidential record, with no approval, ownership, review, or de-identification step, and no way for the knowledge asset's lifecycle to diverge from the source artifact's retention and legal-hold obligations. This is the single most dangerous design considered.
- **Put Knowledge in Legal Intelligence, since both are "legal content."** Rejected — Legal Intelligence owns *authoritative* law under Constitution Articles 2, 7, and 8; Firm know-how is non-authoritative firm-owned material. Housing them together invites exactly the conflation Article 7 prohibits, and would put firm-scoped content inside a deliberately platform-global subdomain.
- **Put Knowledge in Digital Presence, extending its Knowledge Publishing capability.** Rejected — Digital Presence's `ContentItem` is public-by-purpose and explicitly *not* immutable, while internal precedents are confidential-by-default and must be immutably versioned once approved. Sharing one model would make "internal precedent" and "public article" one field apart.
- **Create a separate `Knowledge` module.** Rejected for now — would place the curation workflow across a module boundary and duplicate Firm isolation, Ethical Wall integration, provenance, and retrieval-eligibility machinery. Revisitable by a future ADR (see Why one module with two domain areas).
- **Allow AI to auto-promote high-value Matter documents into draft knowledge without human review.** Rejected — even a draft carries the source's confidential content into a knowledge-shaped record and a knowledge-shaped index. AI may *propose candidates* for human curation; it may not create the derived record's reuse audience.
- **Filter AI retrieval results after generation instead of before retrieval.** Rejected — see Why permission filtering must precede retrieval. This is a correctness impossibility, not a performance trade-off.
- **Let approved knowledge be Firm-wide by default, with restrictions as exceptions.** Rejected — same inversion rejected for portal audiences: it makes over-exposure the default state and confidentiality an opt-in someone can forget.
- **Treat "approved" as permanently current, with no review policy.** Rejected — stale guidance in a legal practice is actively harmful (a superseded filing procedure, a clause invalidated by a statutory change). Ownership plus a review policy plus visible overdue status is the minimum for guidance a lawyer may rely on.
- **Publish approved knowledge publicly by flipping a visibility flag on the internal item.** Rejected — public presentation is Digital Presence's owned lifecycle, and a shared flag would make internal-to-public a one-field mistake. Publication goes through a contract that leaves the internal item's access policy unchanged.

## Consequences

- Every Matter-linked document read requires a synchronous authorization dependency on Practice Management's published Ethical Wall and Matter-access contracts — a coupling cost accepted in exchange for uniform, non-drifting enforcement, the same trade-off `docs/adr/ADR-006-Practice-Management-Core.md` accepted.
- Immutable versioning means storage grows monotonically with every correction; archival tiering and retention policy manage cost, and deletion remains a governed, auditable path rather than an ordinary operation.
- Deny-by-default audience resolution means a Firm must take an explicit action for a client to see anything in the portal. This is deliberate friction, and it is the correct default for privileged material.
- Fail-closed scanning means a scanner outage blocks new uploads from becoming available rather than degrading silently — an availability cost accepted for a confidentiality and integrity guarantee.
- Documents becomes a dependency of Communications' attachment stage, Digital Presence's portal-documents and upload-widget stages, Practice Management's Timeline/Dashboard, and any future Billing artifact rendering. Those stages' sequencing must account for EPIC-007's foundation stages.
- A document associated with multiple restricted Matters resolves to the most restrictive outcome, which can make a legitimately-needed document unreadable to a user with access to only one of those Matters. This is the intended behavior, not a defect.

**Knowledge Management consequences**

- Building a knowledge base requires deliberate human curation effort per item. A Firm's knowledge base grows slower than an automatic "index everything" approach would produce — accepted, because the automatic approach is the confidentiality failure this ADR exists to prevent.
- Approved knowledge carries an ongoing ownership and review obligation, not a one-time authoring cost. Firms that do not maintain review will accumulate visibly-overdue items; visible staleness is the intended outcome, preferable to silent staleness.
- Permission-filtering before retrieval constrains retrieval architecture: the index must carry access metadata, and access-policy changes must invalidate or re-filter index and embedding entries. This is materially more complex than an unfiltered vector store, and it is not optional.
- Knowledge and Document versioning are independent, so a curated precedent does not change when its source document is superseded, and vice versa. Provenance links the two without coupling their lifecycles.
- Two immutable version chains (documents and knowledge) mean storage and index growth on both axes; retirement removes items from retrieval, not from audit.
- Digital Presence gains a new inbound contract (proposed knowledge for public publication) that it, not this context, governs. Public publication remains its lifecycle.
- The curation workflow is a cross-boundary review touching Practice Management (Matter, Ethical Wall), the source Document (confidentiality, legal hold), and the derived Knowledge Item. Its stages sequence after EPIC-006's Ethical Wall stages and after this context's own document foundation.

## Security implications

- Every document belongs to exactly one Firm; `FirmContext` isolation is enforced at application and repository layers, never by global scopes alone, per `docs/domain/06_Laravel_Module_Blueprint.md`.
- A caller denied access at any required boundary receives nothing — no content, metadata, search hit, preview, thumbnail, or existence confirmation. Existence disclosure is itself a confidentiality breach in a conflicts-sensitive domain.
- Preview images, thumbnails, extracted text, and any other derivative inherit the security boundary of the version they derive from; a derivative is never a weaker-protected copy.
- Encryption in transit and at rest is mandatory; storage credentials are secrets, never configuration.
- Uploads are constrained by size limits, extension and media-type validation, filename normalization, and malware scanning, and remain quarantined until positively accepted.
- Revoking portal visibility takes effect immediately for new access and never rewrites the audit history of prior authorized access.
- Legal hold cannot be bypassed by retention policy, archival, deletion request, or redaction.

**Knowledge Management security implications**

- A restricted Matter's existence must not leak through a derived Knowledge Item's title, tags, snippets, embeddings, search results, provenance, or citations. Provenance is retained for audit but is itself access-controlled.
- Source access restrictions remain effective until an explicit, authorized curation and declassification decision establishes a different, safe audience. Curation does not implicitly declassify.
- A legal hold on a source Document remains fully effective regardless of any derived Knowledge Item; the derived item never becomes a route around the hold on the source.
- Knowledge search, embeddings, and index entries are Firm-isolated and inherit their source's access boundary. Cross-Firm retrieval, embedding, caching, evaluation data, or model context is prohibited without exception.
- Access-policy change, revocation, supersession, or retirement invalidates or removes the item from future retrieval, without rewriting the audit record of prior authorized retrieval.
- Approved knowledge is never a route to content the requester could not otherwise access: knowledge access narrows Matter-derived access and never overrides an Ethical Wall.

## AI implications

Extending `docs/architecture/01_OneLegalPro_Constitution.md`, Article 6, and `docs/architecture/05_AI_Architecture.md` to this domain:

**AI may** perform OCR, classify document type, suggest metadata and tags, extract entities/dates/obligations/deadlines, summarize content, suggest Matter or Client links, detect possible duplicates, and suggest redactions.

**AI may not, without explicit human authorization,** change canonical document content, finalize/sign/file/send/publish/delete a document, change legal-hold or retention state, broaden internal or portal access, confirm a low-confidence Matter or Client association, treat OCR or extraction as authoritative over the source, remove or obscure provenance, or send privileged content to an unapproved model or processor.

The last prohibition is specific to this domain: document content is the platform's densest concentration of privileged material, so which processor sees it is itself an access-control decision, not merely an infrastructure choice.

**Knowledge AI implications**

**AI may** suggest classifications, tags, summaries, related items, clauses, precedents, templates, and playbook steps; identify potentially stale or conflicting knowledge; propose Matter documents as candidates for human curation; draft a knowledge revision; and retrieve approved, retrieval-eligible, access-authorized knowledge for drafting assistance.

**AI may not, without explicit human authorization,** approve or publish knowledge; make Matter-derived knowledge Firm-wide; remove confidentiality, privilege, contractual, personal-data, or Ethical Wall restrictions; change an approved knowledge version; retrieve inaccessible content; mix Firms' knowledge or embeddings; treat Draft or AI-generated output as approved Firm guidance; present Firm know-how as an official legal source; or send privileged content to an unapproved processor.

Every AI output over knowledge retains model/run provenance, source citations, timestamp, confidence where applicable, and the specific authorized knowledge versions used. Generated synthesis remains structurally distinct from quoted knowledge content, and any claim about official law still resolves through Legal Intelligence's authority-aware citation rules (`docs/architecture/05_AI_Architecture.md`) rather than through Firm know-how.

## Future expansion

E-signature integration, court e-filing, advanced document automation, collaborative editing, full-text semantic search, redaction workflows, and OCR/conversion provider selection are all describable as future capabilities layered additively on the `Document`/`DocumentVersion`/`DocumentAccessPolicy` model. **None of them is claimed as implemented, architected in detail, or scheduled by this ADR.** Each requires its own architecture pass and its own approved implementation stories.

On the Knowledge side, the same applies to knowledge analytics and reuse metrics, cross-matter precedent comparison, automated clause-conflict detection, multilingual knowledge with Legal Intelligence-linked citations, expertise location ("who in the Firm knows this"), and a knowledge graph generalizing `KnowledgeAssociation` and `KnowledgeSourceReference`.

A **future Marketplace** may distribute separately governed, approved template or knowledge packages between Firms or from third parties. That capability would require its own architecture pass against `docs/architecture/06_Marketplace.md`, which remains an empty placeholder and is **not** populated by this ADR. No Marketplace capability is claimed, architected, or implemented here, and nothing in this decision authorizes cross-Firm knowledge distribution — which remains prohibited (Decision 31) until a separately approved architecture establishes how such packages are governed.

## Implementation status

This ADR and `docs/architecture/14_Document_Knowledge_Management_Architecture.md` are **conceptual architecture only**, covering both Document Management and Knowledge Management. They authorize no application code, migrations, schemas, dependencies, infrastructure, Docker configuration, CI changes, environment changes, storage-provider configuration, retrieval/embedding infrastructure, or runtime implementation. EPIC-007 — Documents & Knowledge Management is recorded as **proposed, not scheduled** in `docs/architecture/08_Roadmap.md`; none of its stages carries a story ID. `PF-*` story numbering and the approved repository implementation sequence are unchanged by this decision, and PF-010 remains the current repository implementation story.
