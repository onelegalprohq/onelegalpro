# ARCH-014 — Document & Knowledge Management Architecture

**Status:** Approved (conceptual architecture) — implementation stories are proposed, not scheduled; see `docs/architecture/08_Roadmap.md`.

## Purpose and scope

This document defines the conceptual domain and system architecture for OneLegalPro's Document & Knowledge Management capability, implementing `docs/adr/ADR-007-Document-Knowledge-Management.md` and the relevant articles of `docs/architecture/01_OneLegalPro_Constitution.md`.

It covers **two explicitly separated domain areas within one bounded context**:

- **Document Management** (§1–§34) — firm and matter work-product documents: creation, immutable versioning, classification, association with Matters and Clients, internal and client-facing access, retention, legal holds, and derived AI/OCR annotations.
- **Knowledge Management** (§K1–§K34) — curated, approved, reusable Firm know-how: precedents, clauses, templates, playbooks, practice notes, research notes, and internal guidance, together with their approval, ownership, review, curation-from-Matter, and permission-aware retrieval governance.

The two areas are deliberately separated because a **source `Document`** and a **curated `KnowledgeItem`** are distinct records with different authority, lifecycle, audience, and governance (§K2). The proposed Laravel module remains `Documents` — existing approved architectures already depend on that name — with these two domain areas bounded internally by distinct aggregates, lifecycles, access policies, and retrieval eligibility rather than by a module wall. See `docs/adr/ADR-007-Document-Knowledge-Management.md`, Decision 15 and Why one module with two domain areas.

This document describes **conceptual models only**. It does not define migrations, Eloquent schemas, table names, provider-specific storage APIs, retrieval/embedding infrastructure, or implementation code — those belong to future, separately approved implementation stories (see `docs/architecture/08_Roadmap.md`).

## 1. Purpose and scope

Documents are the artifacts a law firm's work product actually consists of: engagement letters, pleadings, contracts, evidence, correspondence, court filings, identity documents, and the generated invoices and reports the platform itself produces. Nearly every other bounded context already assumes their existence:

- Practice Management (`docs/architecture/13_Practice_Management_Architecture.md`) shows Documents on the Matter Timeline and Matter Dashboard, and states plainly that it "never stores or versions document content."
- Communications (`docs/architecture/11_Communications_Hub_Architecture.md` §6) extracts email attachments and makes them "available to the Documents domain, subject to that module's own rules."
- Digital Presence (`docs/architecture/12_Website_Client_Portal_Architecture.md` §4, §8) surfaces Documents in the Client Portal and offers a Document Upload Widget.
- Branding (`docs/architecture/10_White_Label_Platform_Architecture.md` §9) wraps "canonical document content" in firm letterhead.

This document defines what that canonical content is, who owns it, and how access to it is authorized.

**In scope for Document Management:** the canonical document record, immutable versions, checksums and provenance, classification, Matter/Client association, internal and portal access control, upload validation and quarantine, private storage and secure delivery, metadata/search/organization, templates and generation, retention/archival/deletion/legal hold, auditability, and AI/OCR governance.

**In scope for Knowledge Management:** see §K1. **Out of scope:** everything listed in Explicit non-goals (§32 and §K32).

## 2. Why Documents is its own bounded context

No existing or planned module is a plausible owner:

| Candidate owner | Why it fails |
|---|---|
| Practice Management | Would absorb file storage, versioning, scanning, and retention — the ownership `docs/adr/ADR-006-Practice-Management-Core.md`, Decision 1, explicitly refuses. Leaves firm-level (non-Matter) documents ownerless. |
| Communications | Would own only files that happened to arrive as attachments; everything uploaded, generated, or scanned in would need a second home. |
| Digital Presence | Would own only what a client can see, which is a small and deliberately restricted subset. |
| Billing | Would own only rendered invoices, and would conflate a commercial record with a file. |
| Legal Intelligence | Owns platform-global *official* sources; firm work product is categorically different data with different authority (`docs/architecture/01_OneLegalPro_Constitution.md`, Articles 2 and 7). |

Each candidate would need its own notion of "a file, its versions, and who may read it" — the duplication `docs/domain/06_Laravel_Module_Blueprint.md` exists to prevent. A dedicated bounded context gives every consumer one place to ask, and gives legal hold, retention, and Ethical Wall enforcement a single point of application. Documents is a **Supporting subdomain** around Practice Management's Core Domain: it serves the Matter without owning it.

## 3. Domain boundaries and ownership

Documents must never mix these five concepts, even where they share infrastructure:

1. **Canonical document content** — the immutable bytes of a `DocumentVersion`, with its checksum and provenance.
2. **Document metadata** — title, classification, associations, tags, retention state; authoritative, human- or system-authored, and distinct from content.
3. **Derived AI/OCR annotations** — extracted text, summaries, suggested classifications and links; structurally separate, provenance-tagged, and removable without touching 1 or 2 (`docs/architecture/01_OneLegalPro_Constitution.md`, Article 7).
4. **Access and audience decisions** — `DocumentAccessPolicy` and `PortalDocumentAudience`; who may read, distinct from what the document is.
5. **Provider/storage mechanics** — object-storage paths, presigned-delivery mechanics, scanner and OCR provider payloads; confined entirely to Infrastructure-layer adapters.

**Ownership, at a glance**

| Concept | Owned by | Referenced by identifier only |
|---|---|---|
| `Document`, `DocumentVersion`, classifications, retention state, legal holds, access policy | **Documents** | — |
| `Client`, `Matter`, `MatterClient`, `MatterTeam`, `EthicalWall` | **Practice Management** (`docs/architecture/13_Practice_Management_Architecture.md`) | Documents references these |
| Official `LegalSource`, `CourtDecision`, `Translation` | **Legal Intelligence** (`docs/architecture/09_Legal_Intelligence_Architecture.md`) | Documents may cite these |
| Invoice and payment records | **Billing** (future bounded context) | Documents may store a rendered artifact |
| `BrandProfile`, `PDFBrandingConfig`, `BrandAsset` | **Branding** (`docs/architecture/10_White_Label_Platform_Architecture.md`) | Documents composes at generation time |
| `CommunicationThread`, `Message` | **Communications** (`docs/architecture/11_Communications_Hub_Architecture.md`) | Documents links attachments back |
| `ClientPortalIdentity`, portal rendering | **Digital Presence** (`docs/architecture/12_Website_Client_Portal_Architecture.md`) | Documents answers portal queries |

**No module writes directly to another module's tables or object-storage paths, in either direction.** Every cross-context interaction is a published command, query, or event, per `docs/domain/06_Laravel_Module_Blueprint.md`.

## 4. Document types and classifications

Classification is a taxonomy, not a fixed enum — the same platform-seeded-default-plus-firm-scoped-extension shape already established for Legal Intelligence's `Jurisdiction`, Branding's theme tokens, Communications' channel capabilities, and Practice Management's `PracticeArea`.

**Platform-seeded `DocumentType` defaults:** Engagement Letter, Pleading, Court Filing, Contract, Correspondence, Evidence, Identity Document, Invoice Artifact, Report, Memorandum, Note Attachment, Template Output, Other. A Firm may define additional first-class types; a custom type is never a lesser "other" bucket.

**`ConfidentialityClassification`** is an independent dimension from `DocumentType`, expressing sensitivity rather than kind: for example Standard, Confidential, Highly Confidential, and Privileged. Classification informs handling (retention defaults, portal-publication friction, AI-processor eligibility) but **never substitutes for an authorization check** — a Standard-classified document on a walled Matter remains inaccessible to a non-allow-listed actor.

`DocumentClassification` bundles `DocumentType`, `ConfidentialityClassification`, and optional practice-area context. It is authoritative metadata: AI may *suggest* a classification, but only a human-authorized `ClassifyDocument` command sets it (§22).

## 5. Document lifecycle

The architecture distinguishes **document lifecycle state** from **version state**. They are different axes and must not be collapsed.

```text
Document lifecycle:
  Draft → Active ⇄ Superseded → Archived → PendingDeletion → Deleted (record tombstone)
  Any state → (Quarantined, if its latest version fails intake validation)
  Any state → LegalHold overlay (a gate, not a lifecycle state — see §20)

Version state:
  Uploaded → Scanning → Quarantined | Accepted → Current ⇄ Historical
```

- **Draft** — created, possibly without an accepted version yet.
- **Active** — has at least one Accepted version and is available subject to access rules.
- **Superseded** — a newer document (not merely a newer version) replaces it; both remain retrievable.
- **Archived** — retained, read-only, out of active working views. **Archival is not deletion** (§20).
- **PendingDeletion** — an approved deletion request is in flight but not executed; a legal hold blocks progression.
- **Deleted** — content is destroyed per an approved policy path; an auditable tombstone recording that the document existed and was deleted **remains**, because destroying the audit fact is never part of deleting content.

**Legal hold is an overlay, not a state.** A document may be Active, Archived, or PendingDeletion while held; the hold blocks every destructive transition regardless of which state it is in.

## 6. Immutable versioning and provenance

**Stored version bytes are immutable.** A correction, replacement, re-scan, or re-render always creates a **new** `DocumentVersion`. Nothing overwrites an accepted version's bytes, checksum, or provenance — the same discipline `docs/architecture/09_Legal_Intelligence_Architecture.md` applies to published `LegalSource` versions and `docs/architecture/11_Communications_Hub_Architecture.md` applies to `Message` content.

Every `DocumentVersion` records, at minimum:

- `DocumentChecksum` — a content hash sufficient to detect tampering or corruption, computed at intake before acceptance.
- Media type and byte size (both validated, not merely declared by the uploader).
- Author/uploader Actor identity (human or system actor, distinctly identified).
- Creation timestamp.
- `DocumentSource` — how this version came to exist: Direct Upload, Email Attachment (via Communications), Portal Upload (via Digital Presence), Template Generation, System Render (for example a Billing invoice artifact), or Scan/Import.
- Provenance for that source — for example the originating `Message` identifier for an attachment, or the `DocumentTemplate` and generation parameters for generated output.

**Superseding never rewrites history.** Marking a version Historical, superseding a document, archiving it, or deleting its content never alters or removes the audit facts already recorded about it. Retrieval of a Historical version remains possible for anyone authorized to read the document, subject to the same access checks as the Current version.

## 7. Matter, Client, and MatterClient associations

A `Document` is either **Firm-level** (not tied to any Matter — firm policies, templates, administrative records) or associated with **one or more Matters** via `DocumentAssociation`.

- `DocumentAssociation` references a `Matter` by identifier and may additionally reference a specific `MatterClient` when the document genuinely concerns only that client. It never embeds or copies Practice Management data.
- **Referential integrity:** any specific `Client`/`MatterClient` reference must identify a `MatterClient` **already attached to that Matter** in Practice Management (`docs/architecture/13_Practice_Management_Architecture.md`, Matter Clients). A reference to a client not on the Matter is invalid.
- **Matter-wide by default:** a Matter association without a specific `MatterClient` reference means the document concerns the Matter as a whole. This governs *internal* scope only — it grants **no** portal visibility to anyone (§9).
- **Multiple Matters:** a document may legitimately relate to more than one Matter (a shared exhibit, a master agreement). Where any associated Matter is restricted, the **most restrictive** access outcome applies across all of them (§10).

Association is a recorded operation producing `DocumentAssociatedWithMatter`, never a silent field update.

## 8. Internal staff access

Internal access composes three authorities, in this order:

1. **Firm boundary** — the document's owning Firm must match the caller's `FirmContext`. No exceptions, ever.
2. **Matter-derived access** — for Matter-associated documents, the caller's access derives from Practice Management: `MatterTeam` membership and role, plus the mandatory `CheckEthicalWallAccess` result for every associated Matter (§10). For Firm-level documents, firm-wide staff roles apply.
3. **Document-level policy** — `DocumentAccessPolicy` may **narrow** the set produced by (2): a need-to-know allow-list, a confidentiality-classification restriction, or an explicit exclusion.

**`DocumentAccessPolicy` can only restrict, never widen.** A document policy cannot grant access to a Matter the caller cannot access, cannot add someone excluded by an Ethical Wall, and cannot cross a Firm boundary. Composition is intersection, never union.

**Deny is total.** A caller who fails any required boundary receives no content, no metadata, no search hit, no preview, no thumbnail, and **no existence confirmation**. In a conflicts-sensitive domain, "a document exists on that Matter" is itself confidential.

**Fail closed.** If any authorization input cannot be positively resolved — the Ethical Wall check is unavailable, Matter membership is ambiguous — access is denied, matching `docs/architecture/13_Practice_Management_Architecture.md`, Failure Modes.

## 9. Client Portal access and deny-by-default audience resolution

This is the architecture's most consequential rule set.

- **Internal availability never implies portal visibility.** Uploading, classifying, or associating a document with a Matter grants a client nothing.
- **Publication is an explicit, auditable decision.** `PublishDocumentToPortal` is a distinct human-authorized command producing `DocumentPublishedToPortal`. There is no configuration that makes publication automatic for a document class, a Matter, or a folder.
- **Audience resolution is deny-by-default.** `PortalDocumentAudience` enumerates exactly which `MatterClient`(s) may see the document. An empty or unresolved audience means **nobody**.
- **Audience references a specific `MatterClient` already attached to the Matter.** Publishing to "the Matter" is not an expressible operation — the audience is always a specific, enumerated set of clients.
- **Co-clients are isolated.** A document visible to one co-client is never visible to another merely because both are on the same Matter. This carries `docs/architecture/13_Practice_Management_Architecture.md`'s Matter-Linked Item Client Scope invariant into the domain where a mistake is a privilege incident.
- **One decision governs every surface.** Download, preview, thumbnail, extracted-text search, metadata display, and export all apply the same audience decision. A client must never reach through a preview or a search result what the download path would deny.
- **Revocation is immediate.** `RevokePortalDocumentAccess` prevents new access at once — including invalidating any outstanding short-lived delivery grant (§12) — and never rewrites the audit history of access that already legitimately occurred.
- **Portal access is additionally gated by everything internal access is gated by.** A published document on a Matter later placed under an Ethical Wall, or belonging to another Firm, is not deliverable regardless of its audience record.

## 10. Ethical Walls and restricted Matters

- Ethical Wall authorization comes **exclusively** from Practice Management's published `CheckEthicalWallAccess` query (`docs/adr/ADR-006-Practice-Management-Core.md`, Decision 4). Documents never implements, caches as authoritative, or approximates wall logic.
- Every read path — metadata, download, preview, search, export, Timeline contribution — checks the wall for **every** associated Matter, not just the one the caller navigated from.
- **Most-restrictive-wins across multiple Matters.** A document associated with Matters A and B, where B is walled and the caller is not allow-listed for B, is inaccessible — even though the caller has full access to A. This is intended: the document's content is reachable from a restricted Matter, so it inherits that restriction.
- Wall denials on documents are auditable events, consistent with Practice Management's requirement that every access attempt against a walled Matter, granted or denied, is recorded.
- An emergency override is Practice Management's mechanism, requiring recorded justification and producing its own auditable event; Documents honors the override's result but never originates one.

## 11. Upload, ingestion, validation, and quarantine

Every inbound file — direct upload, portal upload, email attachment, or import — passes the same pipeline. There is no privileged intake path.

```text
Intake → Validation → Checksum → Quarantine store → Malware scan → Accept | Reject
```

1. **Intake** — the file is received with a declared filename, media type, and its `DocumentSource`.
2. **Validation** — enforced size limits; extension **and** content-sniffed media-type validation (a declared type is never trusted alone); filename normalization (path separators, control characters, homoglyph and double-extension handling, length limits) before the name is ever stored or echoed.
3. **Checksum** — `DocumentChecksum` computed over the received bytes, recorded before acceptance.
4. **Quarantine** — the file is held in a quarantined location, unreadable through any delivery path, until positively cleared. It is not "available but flagged."
5. **Malware scan** — performed through an Infrastructure-layer adapter.
6. **Accept or reject** — only a positively clean scan produces an Accepted version. A rejection records the outcome and never silently discards the fact that an upload was attempted.

**Fail closed.** A scanner that is unavailable, times out, or returns an indeterminate result leaves the content **quarantined**. Newly uploaded content is never accepted on the assumption that an unavailable scanner would have passed it.

**Derivatives are generated only from accepted content.** No preview, thumbnail, OCR pass, or text extraction runs against quarantined bytes.

## 12. Private object storage and secure delivery

- **Objects are private.** No document object is ever publicly readable, and security never depends on a path being unguessable. A leaked or guessed identifier must be worthless without authorization.
- **Storage layout is Infrastructure's concern**, reached only through a `StorageObjectReference` held by the version record. No other module — and no other layer — constructs, stores, or receives a storage path. Provider SDKs and payloads never enter the Domain layer.
- **Delivery is authorization-gated and short-lived.** Every download or preview requires a fresh authorization pass (Firm → actor → Matter → Ethical Wall → audience, as applicable) and yields a **short-lived, single-purpose** delivery grant. Grants are not shareable credentials, are not reissued without re-authorization, and are invalidated by revocation (§9).
- **Encryption in transit and at rest is mandatory.** Storage credentials and scanner/OCR provider credentials are secrets, never plain configuration — the same discipline `docs/architecture/11_Communications_Hub_Architecture.md` §11 applies to provider credentials.
- **Derivatives inherit the source's boundary.** Previews, thumbnails, extracted text, and converted renditions are stored privately and delivered under exactly the same checks as the version they derive from. A derivative is never a weaker-protected copy, and never a way to reach content the source path would deny.
- **Every delivery is audited** (§21), including who, when, which version, and through which surface.

## 13. Search, metadata, tags, and collections

- **Search is permission-aware at query time, not filtered after the fact.** A result the caller may not access is never produced — not produced-then-hidden — so that result counts, pagination, and relevance scoring cannot leak existence.
- **Metadata** — title, `DocumentClassification`, associations, `DocumentSource`, dates, author, retention state — is authoritative and human/system-authored. AI-suggested metadata remains a suggestion until accepted (§22).
- **Tags** are lightweight, firm-scoped, free-form labels, distinct from `DocumentClassification`'s governed taxonomy. Tags never grant or restrict access.
- **`DocumentCollection`** is the organizational concept (folders, matter document sets, saved groupings). Collections are a **navigational convenience only**: membership in a collection never grants access, and a collection view shows a caller only the documents they could already reach. Access always derives from §8–§10, never from where a document sits.
- **Full-text search over OCR/extracted text** is search over *derived* content (§22). A hit in extracted text is subject to the same access checks as the source document, and extracted text is never treated as authoritative over the source.

## 14. Templates, document generation, and Branding integration

- **`DocumentTemplate`** is a firm-scoped aggregate with its own lifecycle (draft, active, versioned, retired) — deliberately **outside** the `Document` aggregate, because a template's lifecycle is independent of any document produced from it, and a template change must never retroactively alter documents already generated (§23). A template is also **a governed knowledge asset**: it is authored, reviewed, approved, owned, and periodically reviewed as a `KnowledgeItem` of type Template, while remaining the generation input that produces `Document` versions. §K12 defines that dual role precisely.
- **Generation produces an ordinary `DocumentVersion`** with `DocumentSource = Template Generation`, recording the template identity and version plus the generation parameters, so any generated artifact is reproducible and attributable.
- **Branding is composed, never owned.** Generation resolves `PDFBrandingConfig` through Branding's published Resolver (`docs/architecture/10_White_Label_Platform_Architecture.md` §9). Branding supplies letterhead, logo placement, accent colors, and footer as a presentation wrapper.
- **Branding never alters canonical content.** No branding configuration may change substantive document text, suppress a mandatory disclaimer or AI-advisory notice, or modify authoritative metadata — `docs/architecture/01_OneLegalPro_Constitution.md`, Article 11, applied to documents.
- Once generated and accepted, a generated version is immutable exactly like an uploaded one. Regenerating produces a new version.

## 15. Communications attachment integration

This resolves the dependency `docs/architecture/11_Communications_Hub_Architecture.md` §6 already named.

- Communications submits an attachment through the published `UploadDocumentVersion` command (or `CreateDocument` where no document exists yet). It **never** writes to Documents' records or object storage.
- The attachment enters the standard intake pipeline (§11) — the same validation, checksum, quarantine, and scanning as any other upload. An email attachment receives no privileged path.
- The resulting version records `DocumentSource = Email Attachment` with the originating `Message`/thread identifier as provenance; the `Message` retains its own reference to the resulting document identifier. Neither module duplicates the other's record.
- **Access is not inherited from the message.** Being able to read a thread does not grant access to the document; document access is resolved independently under §8–§10. Conversely, a document under an Ethical Wall is not readable through its originating message.
- Outbound attachments follow the reverse path: Communications requests an authorized delivery for a version it is permitted to send, rather than reaching into storage.

## 16. Practice Management integration

- Documents references `Matter`, `Client`, `MatterClient`, `MatterTeam`, and `EthicalWall` **by identifier and published contract only**, never by copy, cache-as-authoritative, or direct record access.
- `CheckEthicalWallAccess` and Practice Management's Matter-access contracts are the authorities for Matter-derived permissions (§8, §10).
- Documents contributes to the **Matter Timeline** (`docs/architecture/13_Practice_Management_Architecture.md`) by publishing its own events; Practice Management projects them into an `Activity` entry that links back to the owning document rather than copying its content. The Matter Dashboard's "Documents" panel reads through Documents' published queries.
- **Practice Management never stores or versions document content**, exactly as `docs/architecture/13_Practice_Management_Architecture.md`, Integration Boundaries, states. Documents never owns Matter lifecycle, team membership, or wall definitions.
- A Matter reaching Closed or Archived does not delete or archive its documents; Documents' retention lifecycle is its own (§20).

## 17. Digital Presence and Client Portal integration

- The Client Portal's Documents surface (`docs/architecture/12_Website_Client_Portal_Architecture.md` §4) reads exclusively through `ListClientVisibleDocuments` and related permission-aware queries. It receives resolved, already-authorized results — never a raw document list to filter, and never a storage path.
- **Digital Presence performs no audience resolution.** It renders what Documents authorizes for the authenticated `ClientPortalIdentity`'s underlying Client; the deny-by-default resolution in §9 happens inside Documents.
- The **Document Upload Widget** (`docs/architecture/12_Website_Client_Portal_Architecture.md` §8) submits through the published upload command into the same intake pipeline (§11), with widget-scoped authorization narrower than a full portal session, per that document's Embedded Component Framework.
- Portal delivery uses the same short-lived, authorization-gated mechanism as internal delivery (§12). There is no separate, weaker portal delivery path.
- Portal document activity (view, download) is audited under both Documents' audit rules (§21) and Digital Presence's own portal audit events.

## 18. Billing integration

- A future Billing bounded context owns invoices, payments, amounts, statuses, and due dates. Documents owns none of that.
- A **rendered invoice artifact** may be stored as a `Document` with `DocumentSource = System Render`, so it can be versioned, retained, audited, and surfaced in the portal like any other file, carrying a reference to the invoice identifier it renders.
- **The artifact is not the invoice.** Archiving, superseding, or deleting the artifact never changes the invoice's commercial state; a change in the invoice's state never mutates a stored artifact (a re-issued invoice produces a **new** version or a new document, per §6).
- Portal visibility of an invoice artifact is resolved by Documents' audience rules (§9), independently of whatever Billing decides to display about the invoice record itself.

## 19. Legal Intelligence and citation integration

- **Documents never duplicates official legal-source ownership.** A statute, regulation, or court decision as authoritative platform-global content is a `LegalSource`/`CourtDecision` (`docs/architecture/09_Legal_Intelligence_Architecture.md`), not a `Document`.
- A Firm's own copy, exhibit, filed bundle, or annotated print of a legal text **is** firm work product and is a `Document`. The two records are distinct, with distinct authority; a `Document` is never presented as, or ranked as, an official source (`docs/architecture/01_OneLegalPro_Constitution.md`, Articles 2 and 7).
- A `Document` may **cite** a `LegalSource` using Legal Intelligence's `LegalCitation` model, in the same firm-scoped-record-references-platform-global-record direction that document already establishes for `MatterLegalLink`. The citation is firm-owned; the cited source is not.
- Where document content contains or reproduces a translation of legal text, the mandatory disclaimer policy (`docs/architecture/01_OneLegalPro_Constitution.md`, Articles 3–4) applies at presentation, driven by the cited source's metadata — never suppressed by document classification or branding.

## 20. Retention, archival, deletion, and legal hold

**Retention**

- `DocumentRetentionPolicy` is **Firm- and jurisdiction-aware**, layered on the platform's first-class `Jurisdiction` concept, consistent with `docs/architecture/11_Communications_Hub_Architecture.md` §12 and `docs/architecture/13_Practice_Management_Architecture.md` §14.
- **This architecture deliberately states no universal Thai-law retention period.** Professional-conduct, regulatory, contractual, and limitation-period obligations vary by matter type, jurisdiction, and Firm; asserting a single number here without an approved legal basis would be inventing a legal rule, which `AGENTS.md` prohibits. Concrete default periods require a separately approved decision grounded in a qualified legal source.

**Archival vs. deletion — distinct operations**

- **Archival** retains content, makes the document read-only, and removes it from active working views. Nothing is destroyed. Archived documents remain retrievable and auditable.
- **Deletion** destroys content. It requires an approved, auditable policy path — never an ordinary user operation — and must respect professional, regulatory, contractual, and litigation obligations. `RequestDocumentDeletion` produces a request; execution is a separate, authorized step.
- **A tombstone always survives deletion**: the fact that a document existed, its identifiers, and the deletion authorization remain as audit record. Destroying the audit fact is never part of deleting content.

**Legal hold**

- `LegalHold` is an **independent aggregate** (§23) with its own long-running lifecycle, applied to documents individually or by scope (for example, all documents associated with a Matter).
- While any hold is active, deletion, purge, retention-driven expiry, and destructive redaction are **blocked** — the hold is a gate retention logic cannot pass, not a factor it weighs.
- Releasing a hold requires explicit human authorization and produces its own auditable `LegalHoldReleased` event. AI may never place or release a hold (§22).
- A hold placed while a document is in `PendingDeletion` halts progression; the document does not proceed to Deleted while held.

## 21. Auditability, confidentiality, legal privilege, and export

- **Every consequential action is an auditable domain event**: creation, version upload and acceptance/rejection, classification, association, access-policy change, portal publication and revocation, every delivery (download/preview), archival, hold placement/release, deletion request and execution, and every denied access attempt against a walled Matter.
- Audit events carry Firm ID and Actor ID, with human and AI-system actors **distinctly identified**, per `docs/domain/06_Laravel_Module_Blueprint.md` and the discipline `docs/architecture/11_Communications_Hub_Architecture.md` §13 establishes.
- **Audit records are append-only.** Superseding, archiving, revoking, or deleting never rewrites an audit fact.
- **Privilege** — privileged material is identified through `ConfidentialityClassification` and handled accordingly (portal-publication friction, AI-processor restriction per §22, export flagging). Classification is a handling input; it never replaces an authorization check (§4).
- **Export** — a document export (for a data-subject request, a client file transfer, or a matter handover) applies the same access and audience resolution as any other read, is itself audited, and is subject to legal hold. A privileged or walled document is never included in an export the requester is not authorized to receive.
- **Data-subject requests** — a Client's document data is exportable subject to the audience rules in §9: never a document scoped specifically to a different co-client on a shared Matter.

## 22. AI, OCR, and document-intelligence rules

Extending `docs/architecture/01_OneLegalPro_Constitution.md`, Article 6, and `docs/architecture/05_AI_Architecture.md` to this domain.

**AI may:**

- Perform OCR on document content
- Classify document type
- Suggest metadata and tags
- Extract entities, dates, obligations, and deadlines
- Summarize content
- Suggest links to a Matter or Client
- Detect possible duplicates
- Suggest redactions

**AI may never, without explicit human authorization:**

- Change canonical document content
- Finalize, sign, file, send, publish, or delete a document
- Change legal-hold or retention state
- Broaden internal or portal access
- Confirm a low-confidence Matter or Client association
- Treat OCR or extraction output as authoritative over the source
- Remove or obscure provenance
- Send privileged content to an unapproved model or processor

**Structural requirements for all AI-derived output:**

- **Separate.** `DocumentAnnotation` records are structurally distinct from `DocumentVersion` content and from authoritative metadata — never blended into either (`docs/architecture/01_OneLegalPro_Constitution.md`, Article 7).
- **Provenance-tagged.** Every annotation carries model/version, timestamp, source version identifier, and confidence, matching `AIAnnotation` discipline elsewhere on the platform.
- **Confidence-aware.** Low-confidence output is surfaced as uncertain, never silently upgraded; low-confidence Matter/Client link suggestions require human confirmation, mirroring Communications' `MatchConfidence` model.
- **Removable without altering the source.** Deleting every annotation on a document leaves its versions, checksums, metadata, and audit history untouched.

**Processor restriction.** Which model or processor may receive document content is an **access-control decision**, not merely an infrastructure choice — document content is the platform's densest concentration of privileged material. Sending privileged content to an unapproved processor is a defect, not a configuration preference.

## 23. Aggregates, entities, and value objects

**Aggregates** (each with its own consistency and lifecycle boundary)

- **`Document`** — the aggregate root: identity, owning Firm, `DocumentClassification`, lifecycle state, and its `DocumentVersion` and `DocumentAssociation` entities.
- **`DocumentAccessPolicy`** — the restriction set governing one document. Kept as its own aggregate because access decisions change on a different cadence than content, must be evaluated without loading version history, and carry their own audit lifecycle.
- **`PortalDocumentAudience`** — the deny-by-default client-audience record for one document. Separate from `DocumentAccessPolicy` because internal restriction and client-facing publication are genuinely different decisions with different authorization requirements, and conflating them is precisely how a co-client leak happens.
- **`LegalHold`** — an independent aggregate with a long-running, human-authorized lifecycle that may span many documents and outlive any individual document's active life. Placing it inside `Document` would make a Matter-wide hold a fan-out mutation across every document aggregate, with no single object to authorize, audit, or release.
- **`DocumentRetentionPolicy`** — firm- and jurisdiction-scoped policy, independent of any document it governs; policies change and are versioned on their own schedule.
- **`DocumentTemplate`** — independent lifecycle (§14); a template change must never retroactively alter already-generated documents.
- **`DocumentCollection`** — an organizational grouping with its own lifecycle; a document may belong to several, and collection membership is navigational only (§13).

**Entities** (owned by an aggregate, identity matters)

- **`DocumentVersion`** — owned by `Document`; immutable once accepted. Carries `DocumentChecksum`, media type, byte size, author, creation time, `DocumentSource`, `StorageObjectReference`, and version state.
- **`DocumentAssociation`** — owned by `Document`; references a `Matter` and optionally a specific `MatterClient` (§7).
- **`DocumentAnnotation`** — owned by `Document`, referencing the specific `DocumentVersion` it derives from; AI/OCR-derived, provenance-tagged, removable (§22).

**Value objects** (immutable, no identity)

`DocumentClassification`, `DocumentType`, `ConfidentialityClassification`, `DocumentChecksum`, `DocumentSource`, `StorageObjectReference`, `MediaType`, `ByteSize`, `RetentionRule`, `AIAnnotation` provenance (model/version, timestamp, confidence), `MatchConfidence`, `AudienceEntry` (a `MatterClient` reference within a `PortalDocumentAudience`).

**Why version history stays inside `Document`.** A version's meaning is inseparable from its document's identity and classification, and acceptance of a new version is exactly the invariant boundary the aggregate exists to protect. The unbounded-growth concern that justified splitting `Task`/`Appointment`/`Note` out of `Matter` (`docs/adr/ADR-006-Practice-Management-Core.md`) does not apply equally here: version counts per document are bounded by human editing behavior, not by years of accumulated independent activity. Where a specific document accumulates an unusually large history, lazy version loading is an implementation concern, not a reason to fracture the invariant.

## 24. Aggregate invariants

- A `Document` belongs to **exactly one** Firm, for its entire life.
- An accepted `DocumentVersion`'s bytes, checksum, media type, byte size, author, creation time, and `DocumentSource` are **immutable**.
- A `Document` in Active state has **at least one** Accepted version.
- **Exactly one** Accepted version is Current at a time; all others are Historical. Neither may be destroyed by ordinary operation.
- No version becomes Accepted without a completed, positively clean malware scan and a recorded checksum.
- A `DocumentAssociation`'s specific `MatterClient` reference must identify a `MatterClient` already attached to that `Matter`.
- `DocumentAccessPolicy` may only **narrow** Matter-derived access; a policy that would widen it is invalid.
- A `PortalDocumentAudience` entry must reference a `MatterClient` attached to an associated Matter; an empty audience means no client access.
- Portal visibility requires an explicit publication decision; it is never derivable from lifecycle state, classification, association, or collection membership.
- While any `LegalHold` covering a document is active, no destructive transition (delete, purge, destructive redaction, retention expiry) may proceed.
- A `Deleted` document retains its audit tombstone.
- A `DocumentAnnotation` never mutates the `DocumentVersion` or authoritative metadata it derives from, and its removal leaves both unchanged.

## 25. Commands

Conceptual published commands (names indicative, not an API specification):

`CreateDocument`, `UploadDocumentVersion`, `ClassifyDocument`, `AssociateDocumentWithMatter`, `RestrictDocumentAccess`, `PublishDocumentToPortal`, `RevokePortalDocumentAccess`, `ArchiveDocument`, `PlaceLegalHold`, `ReleaseLegalHold`, `RequestDocumentDeletion`.

Supporting commands the model implies: `SupersedeDocument`, `AcceptQuarantinedVersion`/`RejectQuarantinedVersion`, `AddDocumentToCollection`, `RecordDocumentAnnotation`, `RemoveDocumentAnnotation`, `AcceptSuggestedClassification`, `GenerateDocumentFromTemplate`, `ExecuteApprovedDocumentDeletion`.

Every command is authorized against `FirmContext` plus the access rules in §8–§10. `PublishDocumentToPortal`, `PlaceLegalHold`, `ReleaseLegalHold`, `RequestDocumentDeletion`, and `ExecuteApprovedDocumentDeletion` additionally require explicit human authorization and are never AI-executable (§22).

## 26. Queries

Conceptual published queries:

`GetDocumentMetadata`, `DownloadDocumentVersion`, `PreviewDocumentVersion`, `SearchDocuments`, `ListMatterDocuments`, `ListClientVisibleDocuments`, `CheckDocumentAccess`, `GetDocumentAuditHistory`.

**Every query is permission-aware at evaluation time**, not a raw read the caller filters afterward. `ListClientVisibleDocuments` applies §9's deny-by-default audience resolution for a specific client and is the **only** sanctioned path for portal document listing. `CheckDocumentAccess` is the published authorization check other contexts call rather than re-deriving document permissions themselves.

## 27. Domain and integration events

Conceptual events (past tense, per `docs/domain/06_Laravel_Module_Blueprint.md`):

`DocumentCreated`, `DocumentVersionAdded`, `DocumentClassified`, `DocumentAssociatedWithMatter`, `DocumentAccessRestricted`, `DocumentPublishedToPortal`, `PortalDocumentAccessRevoked`, `DocumentArchived`, `LegalHoldPlaced`, `LegalHoldReleased`, `DocumentDeletionRequested`, `DocumentDeleted`.

Supporting events the model implies: `DocumentVersionQuarantined`, `DocumentVersionRejected`, `DocumentSuperseded`, `DocumentAccessDenied`, `DocumentDelivered`, `DocumentAnnotationRecorded`.

**Events carry identifiers and safe metadata only — never raw document bytes, extracted privileged text, or content excerpts.** An event says *that* a version was added to a document on a Matter, with its checksum and source; it never carries what the document says. Consumers that need content must issue an authorized query, so that every content access passes the access checks in §8–§10 rather than riding along inside an event payload.

## 28. API and cross-module contracts

- **Published contracts only.** Communications, Digital Presence, Practice Management, Billing, and any future consumer reach Documents exclusively through the commands, queries, and events above. No module queries or writes Documents' records, and Documents reaches no other module's records directly.
- **No storage paths cross a module boundary.** Consumers receive document and version identifiers and request authorized delivery; they never receive, store, or construct a `StorageObjectReference`.
- **Provider integrations live behind Infrastructure-layer adapters** — object storage, malware scanning, OCR, document conversion, and future e-signature providers. Provider SDKs, payloads, and provider-specific conditional logic never enter the Domain or Application layers, matching the `ChannelAdapter` discipline in `docs/architecture/11_Communications_Hub_Architecture.md`.
- **Dependency direction** follows `docs/domain/06_Laravel_Module_Blueprint.md` unchanged: Interface → Application → Domain; Infrastructure depends on Application/Domain contracts; Domain never depends on Laravel, Eloquent, HTTP, or provider SDKs.

**Proposed module structure.** The module is named `Documents` — existing approved architectures already depend on that name (`docs/adr/ADR-007-Document-Knowledge-Management.md`, Decision 15) — and contains **two explicitly separated domain areas**, `Documents/` and `Knowledge/`, at every layer:

```text
Documents/
├── Application/
│   ├── Documents/     (CreateDocument, UploadDocumentVersion, ClassifyDocument,
│   │                    AssociateDocumentWithMatter, SupersedeDocument, ArchiveDocument, ...)
│   ├── Access/         (RestrictDocumentAccess, PublishDocumentToPortal,
│   │                     RevokePortalDocumentAccess, CheckDocumentAccess, ...)
│   ├── Retention/      (PlaceLegalHold, ReleaseLegalHold, RequestDocumentDeletion,
│   │                     ExecuteApprovedDocumentDeletion, ApplyRetentionPolicy, ...)
│   ├── Templates/      (GenerateDocumentFromTemplate, ...)
│   ├── Intelligence/   (RecordDocumentAnnotation, AcceptSuggestedClassification,
│   │                     RemoveDocumentAnnotation, ...)
│   └── Knowledge/      (CreateKnowledgeItem, CreateKnowledgeVersion,
│                         SubmitKnowledgeForReview, ApproveKnowledgeItem,
│                         RejectKnowledgeItem, PublishKnowledgeItem,
│                         SupersedeKnowledgeItem, RetireKnowledgeItem,
│                         CurateKnowledgeFromDocument, RestrictKnowledgeAccess,
│                         ScheduleKnowledgeReview, MarkKnowledgeReviewComplete,
│                         RevokeKnowledgeRetrievalEligibility, ...)
├── Domain/
│   ├── Documents/
│   │   ├── Document                                   (aggregate root)
│   │   ├── DocumentAccessPolicy, PortalDocumentAudience   (aggregate roots)
│   │   ├── LegalHold, DocumentRetentionPolicy          (aggregate roots)
│   │   ├── DocumentCollection                          (aggregate root)
│   │   ├── DocumentVersion, DocumentAssociation, DocumentAnnotation   (entities)
│   │   └── DocumentClassification, DocumentType, ConfidentialityClassification,
│   │       DocumentChecksum, DocumentSource, StorageObjectReference, MediaType,
│   │       ByteSize, RetentionRule, AudienceEntry, AIAnnotation, MatchConfidence
│   │                                                    (value objects)
│   └── Knowledge/
│       ├── KnowledgeItem                               (aggregate root)
│       ├── KnowledgeAccessPolicy                        (aggregate root)
│       ├── KnowledgeCollection, KnowledgeReviewPolicy    (aggregate roots)
│       ├── DocumentTemplate                             (aggregate root — see §K12)
│       ├── KnowledgeVersion, KnowledgeAssociation, KnowledgeSourceReference,
│       │   KnowledgeReview, KnowledgeApproval           (entities)
│       └── KnowledgeClassification, KnowledgeOwner, ReviewDueAt,
│           PublicationEligibility, RetrievalEligibility, CurationDecision,
│           DeIdentificationReview                       (value objects)
├── Infrastructure/    (object storage adapter, malware-scanning adapter, OCR adapter,
│                        document-conversion adapter, Eloquent adapters,
│                        permission-aware search index adapter, embedding/retrieval adapter)
├── Interface/          (permission-aware document and knowledge queries, authorized
│                        delivery endpoints, portal document API, governed retrieval API)
├── Database/           (new migrations only — no historical migrations touched)
├── Routes/
├── Tests/
├── Config/             (seeded document-type and knowledge-type taxonomies,
│                        size/media-type limits, retention policy defaults,
│                        default review intervals, approved AI processor policy)
├── ModuleServiceProvider.php
└── README.md
```

The internal boundary between `Documents/` and `Knowledge/` is enforced by distinct aggregates, lifecycles, access policies, and retrieval eligibility. Knowledge reaches a source Document only through this module's own document contracts and a `KnowledgeSourceReference` — never by sharing a version record, an access policy, or a storage reference.

## 29. Multi-tenancy and Firm isolation

- Every `Document`, `DocumentVersion`, annotation, policy, audience, hold, template, and collection is **firm-scoped**, isolated by `FirmContext`.
- Firm identity is **explicit** on every record and enforced at the **application and repository** layers — never by global query scopes alone, per `docs/domain/06_Laravel_Module_Blueprint.md`.
- Storage isolation mirrors record isolation: one Firm's objects are never reachable through another Firm's authorization context, and a delivery grant is scoped to one Firm's object.
- Documents has **no platform-global content subdomain** — the fifth module, after Branding, Communications, Digital Presence, and Practice Management, to take a wholly firm-scoped shape:

| Subdomain | Scope | Ownership boundary |
|---|---|---|
| Seeded `DocumentType` and `KnowledgeClassification` taxonomies, `ConfidentialityClassification` schema, size/media-type limit schema, default review-interval schema | Platform-global | Not `FirmContext` — static configuration shared by every Firm |
| `Document`, `DocumentVersion`, `DocumentAnnotation`, `DocumentAssociation`, `DocumentAccessPolicy`, `PortalDocumentAudience`, `LegalHold`, `DocumentRetentionPolicy`, `DocumentCollection`, firm-custom document types | Firm-scoped | `FirmContext` |
| `KnowledgeItem`, `KnowledgeVersion`, `KnowledgeAccessPolicy`, `KnowledgeCollection`, `KnowledgeReview`, `KnowledgeApproval`, `KnowledgeSourceReference`, `KnowledgeAssociation`, `KnowledgeReviewPolicy`, `DocumentTemplate`, firm-custom knowledge types | Firm-scoped | `FirmContext` |
| Knowledge search-index entries, embeddings, snippets, and retrieval analytics | Firm-scoped | `FirmContext` — a derived index entry inherits its source's Firm and access boundary (§K21–§K22) |
| Storage and provider credentials | Firm-scoped or platform-operated, per deployment | Stored as secrets, never plain configuration |

Legal Intelligence's platform-global legal sources remain the platform's one genuine exception (§19); they are neither documents nor Firm knowledge. **Cross-Firm retrieval, embedding, caching, evaluation data, or model context is prohibited** — a Firm's know-how never becomes another Firm's context (§K22).

## 30. Security and threat considerations

| Threat | Architectural mitigation |
|---|---|
| Cross-Firm document access | Explicit Firm identity enforced at application and repository layers; storage scoped per Firm |
| Ethical Wall bypass via a document path | Wall checked on every read path via Practice Management's published query, for every associated Matter; most-restrictive-wins |
| Co-client confidentiality leak | Deny-by-default `PortalDocumentAudience` resolving to specific `MatterClient`s; no Matter-granularity publication exists |
| Existence disclosure | Denied callers receive no metadata, search hit, preview, or existence confirmation; search filters at query time |
| Guessable or leaked file URL | Objects always private; delivery requires fresh authorization and is short-lived; identifiers alone are worthless |
| Derivative leakage (preview/thumbnail/extracted text) | Derivatives inherit the source's exact security boundary and delivery checks |
| Malware upload | Extension and content-sniffed validation, size limits, filename normalization, quarantine until positively clean scan |
| Scanner outage exploited to smuggle content | **Fail closed** — indeterminate or unavailable scan leaves content quarantined |
| Stale access after revocation | Revocation invalidates outstanding delivery grants immediately |
| Evidence tampering | Immutable versions with checksums; append-only audit; corrections create new versions |
| Destruction of material under litigation hold | `LegalHold` blocks every destructive path; release requires human authorization and is audited |
| Privileged content sent to an unapproved AI processor | Processor eligibility treated as an access-control decision, governed by approved-processor policy |
| OCR error becoming the record | Annotations structurally separate, never authoritative over source, never auto-overwriting metadata |
| Privileged content leaking through event payloads | Events carry identifiers and safe metadata only — never bytes or content excerpts |
| Storage credential exposure | Credentials are secrets; no module outside Infrastructure adapters handles them |

## 31. Failure handling and operational considerations

Conceptual failure modes the architecture must account for (mitigations are implementation-story-level detail):

- **Storage unavailable** — metadata operations degrade gracefully; delivery fails visibly rather than silently returning empty or stale content. Uploads queue or fail explicitly, never silently drop.
- **Scanner unavailable or indeterminate** — content stays quarantined (fail closed); the pending state is visible, not invisible.
- **Checksum mismatch on later verification** — flags the version for review as a potential integrity failure; never silently repaired or overwritten, matching `docs/architecture/09_Legal_Intelligence_Architecture.md`'s ingestion-integrity posture.
- **Authorization dependency unavailable** — an unreachable `CheckEthicalWallAccess` denies access (fail closed), consistent with `docs/architecture/13_Practice_Management_Architecture.md`, Failure Modes.
- **Partial upload / interrupted transfer** — never produces an Accepted version; incomplete content is discarded from the quarantine path with the attempt recorded.
- **Duplicate upload** — checksum-based duplicate detection surfaces the duplicate for human decision; it never silently overwrites or silently discards.
- **Orphaned storage objects** — an accepted version always has a resolvable object, and object cleanup after deletion is a governed, audited process, never opportunistic garbage collection over live data.
- **Concurrent version upload** — resolved by the `Document` aggregate's own concurrency boundary; two simultaneous uploads produce two versions in a defined order, never a lost or interleaved one.
- **Large-file handling** — size limits are enforced at intake; conversion and preview generation for large files are asynchronous and never block acceptance of the canonical version.
- **Retention job error** — must never delete anything a hold or policy protects; an erroring retention run fails closed, leaving content intact.
- **Cross-module consumer unavailable** — Documents' own operations never depend on Communications, Digital Presence, or Billing being reachable; events queue through the platform's existing outbox/event infrastructure.

## 32. Explicit non-goals

This architecture does **not**:

- Own Clients, Matters, Matter membership, Ethical Wall definitions, or any Practice Management concept.
- Own official legal sources, translations, or court decisions.
- Own invoices, payments, or any commercial billing state.
- Own branding configuration, theme tokens, or brand assets.
- Own communication threads or messages.
- Own client portal identity, authentication, or rendering.
- Define migrations, Eloquent models, table names, storage-provider APIs, or bucket/path layouts.
- Select or endorse a storage, malware-scanning, OCR, conversion, or e-signature provider.
- Specify e-signature, court e-filing, advanced document automation, or collaborative editing (see §34 — future, not implemented).
- Assert a universal Thai-law or other jurisdiction-specific retention period (§20).
- Specify document-similarity, duplicate-matching, or redaction-detection algorithms.
- Schedule any implementation work.

## 33. Proposed implementation stages

**Proposed only.** None of these stages is an approved, scheduled, or numbered story. Each requires its own entry in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`, with a Definition of Ready and Definition of Done, before implementation begins. This is the same ten-stage sequence recorded for EPIC-007 in `docs/architecture/08_Roadmap.md`, covering both domain areas.

1. **Document record, immutable versioning, provenance, and Firm isolation** — the `Document` aggregate, `DocumentVersion` immutability, checksums, `DocumentSource` provenance, explicit Firm scoping.
2. **Private storage, secure delivery, upload validation, scanning, and quarantine** — object-storage adapter, `StorageObjectReference`, short-lived authorized delivery, size/media-type validation, filename normalization, malware-scanning adapter, fail-closed acceptance.
3. **Matter associations, Ethical Walls, document access policy, and co-client portal audience** — `DocumentAssociation` with referential integrity, `CheckEthicalWallAccess` integration, restriction-only `DocumentAccessPolicy`, deny-by-default `PortalDocumentAudience` with co-client isolation and immediate revocation.
4. **Document metadata, search, OCR, and document intelligence** — classification taxonomy, tags, `DocumentCollection`, permission-aware search at query time, `DocumentAnnotation` with provenance/confidence and approved-processor policy.
5. **Knowledge Item, immutable Knowledge version, taxonomy, and collection foundation** — the `KnowledgeItem` aggregate, `KnowledgeVersion` immutability, `KnowledgeClassification` taxonomy, `KnowledgeCollection`.
6. **Precedent, clause, template, playbook, practice-note, and research-note management** — the knowledge types in §K10–§K14, including `DocumentTemplate`'s dual role as governed knowledge and generation input.
7. **Matter-to-knowledge curation, confidentiality review, de-identification, and approval** — the §K17 workflow, `KnowledgeSourceReference` provenance, §K18 confidentiality/privilege/personal-data/contractual/Ethical Wall review, `CurationDecision`.
8. **Knowledge review, supersession, retirement, ownership, and staleness management** — `KnowledgeOwner`, `KnowledgeReviewPolicy`, `ReviewDueAt`, visible overdue status, supersession and retirement.
9. **Permission-aware Knowledge search and governed AI/RAG** — Firm-then-permission filtering before retrieval, access-inheriting index and embedding entries, `RetrievalEligibility`, citation- and provenance-preserving retrieval.
10. **Retention, legal hold, audit, Digital Presence publication, and integration hardening** — `DocumentRetentionPolicy`, `LegalHold`, governed deletion with tombstones, audit/export, the Digital Presence publication contract, and Communications/Practice Management integration hardening.

## 34. Future expansion

Each of the following is **describable as a future capability and is not implemented, not architected in detail, and not scheduled** by this document. Each would require its own architecture pass and its own approved implementation stories:

- **E-signature integration** — signature workflows over an existing `DocumentVersion`, producing a new signed version rather than mutating the original.
- **Court e-filing** — filing a document bundle to an external court system, complementing the court integrations already anticipated as future work in `docs/architecture/11_Communications_Hub_Architecture.md` and `docs/architecture/13_Practice_Management_Architecture.md`.
- **Advanced document automation** — clause libraries and conditional assembly, layered on `DocumentTemplate`.
- **Collaborative editing** — multi-author real-time editing, which must resolve to discrete immutable versions at commit points rather than replacing the immutability model.
- **Semantic and cross-document search** — retrieval over annotations and extracted text, subject unchanged to the access rules in §8–§10 and the authority discipline in `docs/architecture/05_AI_Architecture.md`.
- **Redaction workflows** — producing redacted derivative versions while preserving the unredacted original under access control and legal hold.
- **Document knowledge graph** — relationships between documents, Matters, and legal sources, generalizing naturally from `DocumentAssociation` and citation links.

Every item above is additive to the existing `Document`/`DocumentVersion`/`DocumentAccessPolicy`/`PortalDocumentAudience`/`LegalHold` model, not a redesign of it — the same extension discipline `docs/architecture/09_Legal_Intelligence_Architecture.md` applies to future jurisdictions, `docs/architecture/11_Communications_Hub_Architecture.md` to future channels, and `docs/architecture/13_Practice_Management_Architecture.md` to future practice-management capabilities.

---

# Part II — Knowledge Management

Sections §K1–§K34 define the Knowledge Management domain area. They compose with, and never override, the Document Management rules in §1–§34: everything a `Document` is subject to remains in force for documents, and Knowledge adds its own governance for the curated, reusable assets derived from — or authored independently of — those documents.

## K1. Knowledge Management purpose and scope

A Firm's second asset, after the work product itself, is its **know-how**: the precedent that worked, the clause that survived negotiation, the playbook for a filing, the practice note recording how a tribunal actually behaves. Today that material lives in individual lawyers' inboxes and folders, which means it is invisible to the Firm, unreviewed, and lost when its author leaves.

Knowledge Management makes that know-how a governed, first-class asset: curated deliberately, approved by an accountable human, immutably versioned, owned, periodically reviewed, access-controlled, and retrievable — including by AI — only within the boundaries the Firm authorized.

**In scope:** the `KnowledgeItem` record and its immutable versions; knowledge types (precedents, clauses, templates, playbooks, practice notes, research notes, internal guidance); the draft → review → approval → supersession → retirement lifecycle; ownership and stewardship; review policy, due dates, and staleness; taxonomy, collections, and `PracticeArea` association; Matter-to-knowledge curation with confidentiality, privilege, contractual, personal-data, and Ethical Wall review; de-identification; knowledge access policy and Ethical Wall inheritance; permission-aware full-text and semantic search; governed AI/RAG retrieval; and the boundaries with Legal Intelligence, Digital Presence, Practice Management, Communications, and Branding.

**Out of scope:** everything in §K32.

## K2. Documents versus Knowledge

A source `Document` and a curated `KnowledgeItem` are **distinct records**, never two states of one thing:

| | `Document` | `KnowledgeItem` |
|---|---|---|
| **Answers** | "What did we file, send, or receive, and when" | "How does this Firm do this, as approved guidance" |
| **Authority** | Evidentiary — an artifact of what was actually done | Advisory — approved internal guidance, never official law |
| **Lifecycle** | Draft → Active → Superseded → Archived → deletion, under retention and legal hold | Draft → In Review → Approved → Published/Internal Use → Superseded → Retired |
| **Integrity requirement** | Immutability of the stored artifact and its checksum | Immutability of each approved version, plus an accountable approver |
| **Audience** | Matter-scoped; client-visible only by explicit deny-by-default publication | Deliberately reusable within an authorized internal audience |
| **Governance** | Retention, legal hold, portal audience, malware scanning | Approval, ownership, periodic review, curation and de-identification, retrieval eligibility |
| **Created by** | Upload, email attachment, portal upload, generation, import | Authoring, or curation from a Document/Matter work product |

**Neither is a state of the other.** A `Document` never becomes a `KnowledgeItem`; curation *creates a separate record* that references its source (§K17). Deleting or archiving a source Document does not delete the derived Knowledge Item, and retiring a Knowledge Item does not touch the source Document — but a legal hold or access restriction on the source remains fully effective on the source regardless (§K17, §K18).

## K3. Knowledge ownership boundaries

| Concept | Owned by | Knowledge's relationship |
|---|---|---|
| `KnowledgeItem`, `KnowledgeVersion`, approvals, reviews, access policy, retrieval eligibility | **This context** (Knowledge area) | Owner |
| Source `Document` and `DocumentVersion` | **This context** (Document area) | Referenced by `KnowledgeSourceReference`; never absorbed |
| Official statutes, regulations, court decisions, translations, citations, authority metadata, legal-source provenance | **Legal Intelligence** | Cited by identifier; never duplicated or overridden (§K24) |
| Public `ContentItem`, public editorial lifecycle, publication scheduling, public presentation | **Digital Presence** | Supplied for publication through a contract; never governed here (§K23) |
| `Client`, `Matter`, `MatterClient`, `MatterTeam`, `PracticeArea`, `EthicalWall` | **Practice Management** | Referenced by identifier and published contract only (§K25) |
| `CommunicationThread`, `Message` | **Communications** | May link to a `KnowledgeItem`; stores no canonical knowledge (§K25) |
| `BrandProfile`, theme tokens, `PDFBrandingConfig` | **Branding** | Presentation only; never alters knowledge substance (§K25) |
| Future cross-Firm knowledge/template packages | **Future Marketplace** | Not architected, not implemented, prohibited today (§K34) |

## K4. Knowledge types

`KnowledgeClassification` is a taxonomy on the same platform-seeded-default-plus-firm-extension shape used throughout the platform. Platform-seeded knowledge types:

- **Precedent** — a de-identified, reusable exemplar of completed work product (§K10).
- **Clause** — a reusable contractual or drafting component, typically with negotiation guidance (§K11).
- **Template** — a generation-ready document skeleton; also the input to document generation (§K12).
- **Playbook** — procedural guidance: ordered steps, decision points, escalation rules (§K13).
- **Practice Note** — substantive internal guidance on how something is done in practice (§K14).
- **Research Note** — a recorded research conclusion with its supporting citations (§K14).
- **Internal Guidance** — Firm policy and operational guidance that is not matter-substantive.

A Firm may define additional first-class types. `ConfidentialityClassification` (§4) applies to knowledge as an independent dimension, exactly as it does to documents: it informs handling and retrieval eligibility but **never substitutes for an authorization check**.

## K5. Knowledge lifecycle

```text
Draft → In Review → Approved → Published/Internal Use → Superseded → Retired
                 ↘ Rejected (returns to Draft, or terminates)

Overlay states (not lifecycle states):
  RetrievalEligibility  — revocable independently of lifecycle (§K22)
  ReviewDueAt / Overdue — visible staleness signal (§K9)
```

- **Draft** — authored or curated, not yet Firm guidance. **Draft knowledge is not approved Firm guidance** and must never be presented, cited, or retrieved as if it were.
- **In Review** — submitted for approval; reviewers recorded.
- **Rejected** — explicitly not approved, with a recorded reason. Rejected content is never retrieval-eligible.
- **Approved** — an authorized human has approved a specific `KnowledgeVersion`. Approval creates an immutable approved version.
- **Published/Internal Use** — approved and in active internal circulation, within its `KnowledgeAccessPolicy` audience. (Public publication is a *separate* Digital Presence concern — see §K23 — and is never implied by this state.)
- **Superseded** — a newer approved version replaces it; the superseded version remains retrievable for audit and citation resolution but is not retrieval-eligible for new AI context by default.
- **Retired** — withdrawn from active use, with reason recorded. Retirement removes an item from new retrieval without destroying audit history.

**Mandatory invariants**

- Draft knowledge is not approved Firm guidance.
- Approval requires an **authorized human**. AI cannot approve or publish (§K22).
- Updating approved content **creates a new immutable `KnowledgeVersion`** — never an in-place edit of an approved version.
- Superseded and retired versions remain auditable and never disappear.
- Approved content has a `KnowledgeOwner` and a `KnowledgeReviewPolicy`.
- Overdue review status is **visible**, not silent.
- Expired or overdue knowledge is **never silently presented as current**.
- Only approved, retrieval-eligible, and access-authorized content enters standard AI/RAG context.
- Revocation or retirement removes an item from new retrieval **without destroying historical audit records**.

## K6. Knowledge versions and provenance

Each `KnowledgeVersion` is immutable once approved and records:

- Its content (or a `StorageObjectReference` where the body is a stored artifact, under the same private-storage rules as §12).
- Author/editor Actor identity and creation timestamp.
- The `KnowledgeApproval` that approved it (approver identity, timestamp, decision, scope of approved reuse).
- `KnowledgeSourceReference`s — provenance links to a source `Document`/`DocumentVersion`, a Matter, a Legal Intelligence `LegalSource` citation, or an external source, as applicable.
- Supersession lineage (`supersedes` / `superseded_by`), so "which version was current when this was relied on" is always answerable.
- Any `AIAnnotation` provenance where AI drafted or contributed to the version (§K22).

**Provenance is itself access-controlled.** A `KnowledgeSourceReference` pointing at a restricted Matter or walled Document must not reveal that Matter's or Document's existence to a caller not authorized for it (§K18, §K20). Provenance is retained for audit; it is not universally readable.

**Knowledge versioning is independent of Document versioning.** A new source-document version does not create a knowledge version, and a new knowledge version does not alter the source. This is deliberate: a curated precedent is a considered, approved artifact whose value is stability, while its source document may continue its own life under Matter, retention, and legal-hold rules. Coupling the two would either force re-approval on every unrelated source change or silently drift approved guidance underneath the approval that authorized it.

## K7. Knowledge ownership and stewardship

- Every approved `KnowledgeItem` has a **`KnowledgeOwner`** — a named, accountable Actor (or a role-held stewardship assignment), never "the Firm" in the abstract and never unowned.
- The owner is responsible for accuracy, currency, and completing scheduled reviews (§K8, §K9).
- Ownership is transferable as a recorded operation, not a silent field update — a departing lawyer's knowledge must be reassigned, not orphaned.
- An approved item without an owner is **invalid**, not merely incomplete (§K27).
- Stewardship may additionally be organized by `PracticeArea` (§K16), so a practice group can hold collective responsibility for its own body of knowledge.

## K8. Knowledge review and approval

- **Approval is a human act.** `ApproveKnowledgeItem` requires an authorized human approver whose authority is recorded; there is no automatic, confidence-based, or AI approval path.
- **The approver is recorded as a `KnowledgeApproval` entity** carrying approver identity, timestamp, the approved `KnowledgeVersion`, the approved reuse scope, and any conditions attached to the approval.
- **Approval is version-specific.** Approving one version never pre-approves a later one; every new version re-enters review.
- **Rejection is explicit and recorded** (`RejectKnowledgeItem`) with a reason, and never silently discards the submission.
- **Separation of concerns:** curation review (§K18 — is this *safe* to reuse) and substantive approval (is this *correct and useful* guidance) are distinct determinations. Both must be satisfied before an item derived from Matter work product becomes approved.
- Every approval, rejection, and review completion is an auditable event (§K30).

## K9. Knowledge review dates, staleness, and expiry

- **`KnowledgeReviewPolicy`** defines how often an item must be re-reviewed. Intervals are firm- and type-configurable (a clause library may need annual review; a procedural playbook may need review whenever a court changes its rules) — the platform seeds defaults but asserts no universal interval.
- **`ReviewDueAt`** is the computed next-review date on the current approved version.
- **Overdue status is visible** wherever the item is displayed, searched, or retrieved. Staleness is surfaced, never hidden.
- **Expired or overdue knowledge is never silently presented as current.** An overdue item surfaces its status alongside its content; retrieval that includes it must carry that signal into the AI context and any citation (§K22).
- A Firm may configure overdue items to lose `RetrievalEligibility` automatically after a threshold — a policy choice, recorded explicitly, not a silent default.
- `ScheduleKnowledgeReview` and `MarkKnowledgeReviewComplete` produce `KnowledgeReview` records, so the review history of any guidance is fully auditable.
- `ListKnowledgeDueForReview` gives owners and practice groups a working queue.

## K10. Precedents

A **Precedent** is a de-identified, approved exemplar of completed work product — the agreement that closed, the pleading that succeeded, the structure that worked.

- A precedent is **always a separate `KnowledgeItem`**, never a flag on the source `Document` (§K2).
- Creation from Matter work product goes through the full curation workflow (§K17), including de-identification (§K18). This is the highest-risk curation path in the platform and has no shortcut.
- A precedent retains `KnowledgeSourceReference` provenance to its source, access-controlled per §K6.
- Precedents carry usage guidance: what the exemplar demonstrates, when it applies, and known limitations — an exemplar without context invites misuse.
- A precedent is never presented as official law (§K24) and never as legal advice for a new matter; it is an internal reference a lawyer exercises judgment over.

## K11. Clause libraries

A **Clause** is a reusable drafting component with negotiation context.

- Each clause is its own `KnowledgeItem`, independently versioned, approved, owned, and reviewed — because clauses change, are approved, and go stale individually, not as a library-wide unit.
- A **clause library** is a `KnowledgeCollection` (§K15) grouping clauses; the collection organizes, it does not own canonical content.
- Clauses commonly carry: purpose, preferred/fallback variants, negotiation guidance, jurisdiction applicability (via Legal Intelligence's `Jurisdiction` where relevant), and known risk notes.
- A clause may **cite** a Legal Intelligence `LegalSource` (for example, the statutory provision it addresses) via `KnowledgeSourceReference`; the citation resolves through Legal Intelligence's authority-aware model and never asserts authority itself (§K24).
- Variant relationships between clauses are `KnowledgeAssociation`s, so "preferred vs. fallback" is explicit structure rather than convention buried in text.

## K12. Document templates

`DocumentTemplate` has a **dual role**, and both halves are governed:

1. **As a knowledge asset** — a template is authored, reviewed, approved, owned, and periodically reviewed under the Knowledge lifecycle (§K5, §K8, §K9). An unapproved template is not Firm-sanctioned, and template content is exactly the kind of guidance that goes dangerously stale.
2. **As a generation input** — an approved template version is what §14 uses to generate a `DocumentVersion` with `DocumentSource = Template Generation`.

Rules:

- **Generation always uses a specific approved template version**, recorded in the generated document's provenance. This is why a template change never retroactively alters already-generated documents (§14): the generated artifact records which version produced it.
- The generated output is an ordinary `Document` under Document Management rules; the template remains a `KnowledgeItem` under Knowledge rules. Generating from a template creates no coupling between the two lifecycles.
- Retiring a template stops new generation from it; it does not invalidate documents already generated.
- Branding composes at generation time and never alters template substance or the generated document's canonical content (§14, §K25).

## K13. Playbooks and procedural guidance

A **Playbook** is ordered procedural guidance: how the Firm performs a recurring process (a filing, an onboarding, a closing, a regulatory submission).

- Modeled as a `KnowledgeItem` whose approved version contains ordered steps, decision points, escalation rules, and references to the clauses, templates, and practice notes each step uses (as `KnowledgeAssociation`s).
- Playbooks are **guidance, not workflow execution.** This architecture defines the knowledge asset; it does not define a workflow engine, task automation, or execution state. A future Workflow context may *consume* playbooks through published contracts to drive automation — that integration is future work (§K34), not part of this document.
- Playbook steps may reference Practice Management concepts (a step that implies a `Task`) by identifier only; creating actual Tasks remains Practice Management's command, invoked by a human or an approved integration, never a silent side effect of reading a playbook.
- Procedural guidance goes stale fastest when external procedure changes, so playbooks are prime candidates for shorter review intervals (§K9).

## K14. Practice notes and research notes

- A **Practice Note** captures substantive internal guidance — how a tribunal actually behaves, how a filing is treated in practice, what a counterparty typically accepts.
- A **Research Note** records a research conclusion together with the citations supporting it.

Both are `KnowledgeItem`s under the full lifecycle. Two rules matter especially here:

- **Research notes cite; they do not become law.** A research note's citations resolve through Legal Intelligence's `LegalCitation` model, and the note itself is firm-owned commentary under Constitution Article 7 — never an authoritative source, never ranked or presented as one (§K24).
- **A research note is a snapshot with an expiry risk.** Its conclusion was correct against the law as it stood; the review policy (§K9) and the citation's own supersession status in Legal Intelligence are what keep that visible. A research note whose cited source has been amended must not be presented as current without that signal.

Practice Management's `Note` aggregate is a *different* concept: a `Note` is matter-scoped commentary owned by Practice Management (`docs/architecture/13_Practice_Management_Architecture.md` §11). A Practice Note here is Firm-wide curated guidance. Neither is a state of the other, and a matter `Note` becoming Firm guidance goes through curation (§K17) exactly as a Document does.

## K15. Knowledge collections and taxonomy

- **`KnowledgeCollection`** organizes knowledge (clause libraries, practice-area handbooks, onboarding sets, curated reading lists). It is its own aggregate with its own lifecycle.
- **Collections never own canonical content.** A `KnowledgeItem` may belong to several collections and exists independently of all of them; removing a collection never deletes its items.
- **Collection membership never grants access.** A collection view shows a caller only the items they could already reach under §K19–§K20. This is the same navigational-only rule §13 applies to `DocumentCollection`, and for the same reason: if placement granted access, filing would become an authorization mechanism.
- **Taxonomy** (`KnowledgeClassification`, §K4) is governed and drives handling; **tags** are lightweight firm-scoped labels that never grant or restrict access.
- A collection whose items are all inaccessible to a caller must not disclose its own contents or item count — existence disclosure through aggregation is still disclosure (§K21).

## K16. PracticeArea associations

- A `KnowledgeItem` may be associated with one or more `PracticeArea`s, referenced **by identifier** from Practice Management's taxonomy (`docs/architecture/13_Practice_Management_Architecture.md` §5). Knowledge never defines its own parallel practice-area list.
- Practice-area association drives discovery (`ListPracticeAreaKnowledge`), stewardship assignment (§K7), and retrieval relevance — but **never access**. Belonging to a practice area a caller works in does not by itself authorize an item; access always resolves through §K19–§K20.
- Firm-custom practice areas are first-class here exactly as they are in Practice Management.

## K17. Matter-to-knowledge curation

This is the workflow that turns confidential Matter work product into reusable Firm knowledge. It is the highest-risk operation in this context and is **always explicit and human-gated**.

```text
1. Identify   — an authorized user identifies a source Document or Matter work product
                as a possible knowledge source.
2. Draft      — a SEPARATE draft KnowledgeItem is created. The source Document is
                not modified, reclassified, or re-scoped.
3. Provenance — KnowledgeSourceReference records the source Document/version, Matter,
                and curating actor. Provenance is retained and access-controlled.
4. Review     — confidentiality, privilege, personal data, contractual restrictions,
                Ethical Walls, and reuse rights are reviewed (§K18).
5. De-identify— de-identification is performed where appropriate, against
                re-identification risk, not names alone (§K18).
6. Decide     — an authorized human confirms whether Firm-wide or restricted reuse is
                permitted, recording a CurationDecision and the resulting audience.
7. Submit     — the KnowledgeItem enters review (§K8).
8. Approve    — approval creates an immutable approved KnowledgeVersion.
9. Independent— the source Document remains governed independently by its own access,
                retention, and legal hold rules.
```

**Mandatory rules**

- **No automatic promotion** from Document to Knowledge. No classification, tag, folder, collection, usage count, or AI confidence level promotes a document. Automatic promotion is prohibited, not unimplemented.
- **No automatic Firm-wide access.** The curation decision establishes the audience explicitly; the default in the absence of a decision is the *source's* restriction, never Firm-wide.
- **A restricted Matter's existence must not be exposed** through the derived item's title, tags, snippets, embeddings, search results, citations, or provenance.
- **Source restrictions remain effective** until an explicit, authorized curation and declassification decision establishes a different safe audience. Curation does not implicitly declassify.
- **A legal hold on the source remains fully effective** regardless of the derived Knowledge Item. The derived item is never a route around a hold on the source.
- **De-identification must consider re-identification risk**, not names alone (§K18).
- Curation is auditable end to end: `KnowledgeCuratedFromDocument` records the source, the decision, the reviewer, and the resulting audience.

## K18. Confidentiality, privilege, and de-identification review

Curation review must reach an explicit, recorded determination on each of five independent questions:

1. **Confidentiality** — is this the client's confidential information, and does any engagement term, duty, or expectation restrict internal reuse?
2. **Privilege** — does reuse risk waiver, or expose privileged analysis beyond those entitled to it?
3. **Personal data** — does PDPA/GDPR (or another applicable regime) permit this secondary use, consistent with the platform's Thailand-first posture and the privacy models in `docs/architecture/11_Communications_Hub_Architecture.md` §12 and `docs/architecture/13_Practice_Management_Architecture.md` §15?
4. **Contractual restrictions** — does an NDA, engagement letter, or third-party licence restrict reuse of this material?
5. **Ethical Walls** — is the source Matter walled, and does the proposed audience cross that wall?

**A "no" on any of the five blocks Firm-wide reuse.** The outcome may still be a *restricted* Knowledge Item with a narrower audience — but never a broader one than the review supports.

**De-identification**

- Removing names is **not** sufficient and must never be treated as such.
- Review must consider **re-identification risk from context**: distinctive transaction values, dates, jurisdictions, party roles, industry specifics, procedural posture, and unusual drafting can identify a source matter with every proper noun removed.
- De-identification is recorded as a `DeIdentificationReview` on the curation record — what was assessed, what was removed or generalized, and the residual risk accepted — so the judgment is auditable rather than assumed.
- Where de-identification cannot reduce risk sufficiently, the correct outcome is a restricted audience or no Knowledge Item at all — not a broader audience with a caveat.

## K19. Knowledge access policy

`KnowledgeAccessPolicy` governs who may read a `KnowledgeItem`. It composes exactly like `DocumentAccessPolicy` (§8):

1. **Firm boundary** — the item's owning Firm must match the caller's `FirmContext`. No exceptions.
2. **Audience** — the authorized internal audience established at curation or authoring (Firm-wide, practice-area-scoped, role-scoped, or an explicit allow-list).
3. **Matter-derived restrictions**, where the item carries `KnowledgeSourceReference`s to restricted Matters — including the mandatory Ethical Wall check (§K20).

**Knowledge access may narrow Matter-derived access but never override an Ethical Wall.** Composition is intersection, never union — the same restriction-only rule §8 establishes for documents.

**Deny is total.** A caller denied access receives no content, metadata, title, snippet, tag, citation, provenance, search hit, or existence confirmation (§K21).

**Fail closed.** An authorization input that cannot be positively resolved denies access.

**Access-policy changes propagate to retrieval.** Restricting, revoking, superseding, or retiring an item invalidates or removes its search-index and embedding entries from future retrieval (§K21–§K22), without rewriting the audit record of prior authorized access.

## K20. Ethical Walls and restricted knowledge

- Ethical Wall authorization for knowledge comes **exclusively** from Practice Management's published `CheckEthicalWallAccess` query, exactly as for documents (§10). Knowledge never implements or approximates wall logic.
- A `KnowledgeItem` carrying provenance to a walled Matter is subject to that wall for any caller not allow-listed — **including through its provenance and citations**, not only its body.
- **Most-restrictive-wins** across multiple source references: an item derived from two Matters, one of them walled and inaccessible to the caller, is inaccessible.
- **Successful de-identification and an authorized declassification decision can change this** — that is precisely what §K17 step 6 exists to determine. A properly de-identified, explicitly declassified precedent no longer carries the source Matter's restriction, because an authorized human determined it no longer reveals that matter. That determination is recorded and auditable; it is never inferred.
- Wall denials on knowledge are auditable events, consistent with Practice Management's requirement that every access attempt against a walled Matter, granted or denied, is recorded.

## K21. Permission-aware full-text and semantic search

- **Firm filtering happens first**, before any search or vector retrieval executes. Cross-Firm search is structurally impossible, not merely filtered out.
- **Permission filtering happens before results are assembled** — never as a post-processing pass over a completed result set. Result counts, pagination, facets, and relevance scores must not reflect items the caller cannot access.
- **Search must not expose** inaccessible titles, snippets, tags, embeddings, metadata, provenance, citations, or existence.
- **Index and embedding entries inherit their source's access boundary.** An embedding is a derivative of the content it encodes and is protected exactly as the content is — the same rule §12 applies to document previews and extracted text.
- **Access-policy change, revocation, supersession, or retirement triggers removal or invalidation** of the corresponding index and embedding entries from future retrieval.
- **Draft, rejected, superseded, retired, inaccessible, and quarantined content is excluded from approved-knowledge search by default.** A user with authority to see drafts may search them explicitly; they never appear in default approved-guidance results.
- **Overdue items carry their staleness signal into results** (§K9) rather than appearing indistinguishable from current guidance.

## K22. Governed AI/RAG retrieval

This is where knowledge governance meets the platform's largest confidentiality risk surface. The rules extend `docs/architecture/05_AI_Architecture.md` to knowledge retrieval.

**Retrieval pipeline requirements**

- **Firm filtering before retrieval.** Always first, always structural.
- **Permission filtering before chunks or items enter AI context.** Not after retrieval, and never after generation.
- **Post-generation filtering is never the primary security control.** Once inaccessible content enters context it has already influenced the output — as paraphrase, structure, or inference — and no downstream filter reliably detects or unwinds that influence.
- **Only approved, retrieval-eligible, access-authorized content enters standard AI/RAG context.** `RetrievalEligibility` is explicit and revocable (`RevokeKnowledgeRetrievalEligibility`), not inferred from lifecycle state alone.
- **Retrieval preserves**, for every retrieved item: `KnowledgeItem` identity, `KnowledgeVersion`, approval status, source references, the access decision that permitted it, and its citations.
- **Generated synthesis stays structurally distinct from quoted knowledge content**, per Constitution Article 7 — a reader must always be able to tell what the Firm approved from what the model composed.
- **Official legal claims still resolve through Legal Intelligence** and its authority-aware citation rules. Firm know-how may inform drafting; it never becomes the citation for what the law says.
- **Firm know-how is never presented as official law** (§K24).
- **Cross-Firm retrieval, embeddings, caching, evaluation data, and model context are prohibited** — without exception and regardless of anonymization claims.

**AI may:**

- Suggest classifications, tags, summaries, related items, clauses, precedents, templates, and playbook steps
- Identify potentially stale or conflicting knowledge
- Propose Matter documents as candidates for human curation
- Draft a knowledge revision
- Retrieve approved, retrieval-eligible, access-authorized knowledge for drafting assistance

**AI may not, without explicit human authorization:**

- Approve or publish knowledge
- Make Matter-derived knowledge Firm-wide
- Remove confidentiality, privilege, contractual, personal-data, or Ethical Wall restrictions
- Change an approved knowledge version
- Retrieve inaccessible content
- Mix Firms' knowledge or embeddings
- Treat Draft or AI-generated output as approved Firm guidance
- Present Firm know-how as an official legal source
- Send privileged content to an unapproved processor

**Every AI output over knowledge retains** model/run provenance, source citations, timestamp, confidence where applicable, and the specific authorized `KnowledgeVersion`s used — so any AI-assisted draft can be traced to exactly the approved guidance that informed it.

## K23. Digital Presence publication boundary

- **Digital Presence owns public content.** The public `ContentItem` lifecycle, public presentation, and publication scheduling belong to `docs/architecture/12_Website_Client_Portal_Architecture.md` (Content Management, Knowledge Publishing) — not here.
- An approved `KnowledgeItem` may be **proposed or supplied** for public publication through a published contract. `PublicationEligibility` records whether an item is *eligible* to be offered; it does not publish anything.
- **Publishing publicly must not silently change the internal item's access policy.** The internal `KnowledgeAccessPolicy` is unaffected by public publication, and the resulting public `ContentItem` is a separate record on Digital Presence's own lifecycle (editable in place with revision history, unlike an immutable approved `KnowledgeVersion`).
- Public publication requires its own authorization — internal approval is necessary but never sufficient. Content approved for internal reuse is not thereby approved for the world.
- Published public content remains firm-owned editorial material under Constitution Article 7 and is never presented as official law, exactly as `docs/architecture/12_Website_Client_Portal_Architecture.md`, Knowledge Publishing, already requires.

## K24. Legal Intelligence boundary

- **Legal Intelligence owns** authoritative statutes, regulations, court decisions, translations, citations, authority metadata, and legal-source provenance (`docs/architecture/09_Legal_Intelligence_Architecture.md`).
- **Knowledge may reference and cite** Legal Intelligence identifiers and `LegalCitation`s via `KnowledgeSourceReference`. It **never duplicates, replaces, or overrides** authoritative ownership.
- **Firm know-how is never authoritative.** A precedent, clause, practice note, or research note is firm-owned commentary under Constitution Article 7 — retrieval ranking, citation rendering, and AI presentation must all keep it structurally distinct from official sources, exactly as `docs/architecture/05_AI_Architecture.md`'s authority-aware ranking already requires.
- Where knowledge reproduces or discusses a translated legal provision, the mandatory disclaimer policy (Constitution Articles 3–4) applies at presentation, driven by the cited source's metadata.
- A cited source that has been amended or superseded in Legal Intelligence must surface that status when the knowledge item is presented (§K9) — knowledge does not get to be silently stale about the law it cites.

## K25. Communications and Practice Management integration

**Communications**

- May **link** a `CommunicationThread` or `Message` to a `KnowledgeItem` through published contracts (`CommunicationLink` on its side, `KnowledgeAssociation` on this side).
- **Does not store or govern canonical knowledge content.** A thread referencing a clause holds a reference, never a copy.
- Access is not inherited from the link: being able to read a thread does not authorize the linked knowledge, and vice versa. Each resolves independently.

**Practice Management**

- Owns `Client`, `Matter`, `MatterClient`, `MatterTeam`, `PracticeArea`, and `EthicalWall`. Knowledge references all of them **by identifier and published contract only**.
- `CheckEthicalWallAccess` is the sole authority for wall decisions affecting knowledge (§K20).
- Knowledge may contribute to the Matter Timeline (for example, "a precedent was curated from this matter") through published events carrying identifiers and safe metadata only — never knowledge body text or source excerpts.
- Practice Management never stores, versions, approves, or governs knowledge.

**Branding**

- Controls **presentation only** for any rendered knowledge artifact, through the Branding Resolver.
- **Must not alter** knowledge substance, approval status, provenance, confidentiality classification, or citations — Constitution Article 11 applied to knowledge.

## K26. Knowledge aggregates, entities, and value objects

**Aggregates** (each with its own consistency and lifecycle boundary)

- **`KnowledgeItem`** — the aggregate root: identity, owning Firm, `KnowledgeClassification`, `KnowledgeOwner`, lifecycle state, and its `KnowledgeVersion`, `KnowledgeSourceReference`, `KnowledgeAssociation`, `KnowledgeReview`, and `KnowledgeApproval` entities.
- **`KnowledgeAccessPolicy`** — the audience and restriction set for one item. Its own aggregate for the same reason `DocumentAccessPolicy` is: access decisions change on a different cadence than content, must be evaluated without loading version history, and carry their own audit lifecycle. It is also the object whose change must invalidate index and embedding entries (§K21).
- **`KnowledgeCollection`** — an organizational grouping with its own lifecycle; navigational only, never an access grant (§K15).
- **`KnowledgeReviewPolicy`** — firm- and type-scoped review cadence, independent of any item it governs; policies change on their own schedule, exactly like `DocumentRetentionPolicy`.
- **`DocumentTemplate`** — a knowledge asset with a generation role (§K12); already its own aggregate in §23, and governed under the Knowledge lifecycle.

**Entities** (owned by `KnowledgeItem`, identity matters)

- **`KnowledgeVersion`** — immutable once approved; carries content or a private `StorageObjectReference`, author, timestamp, supersession lineage, and any AI provenance.
- **`KnowledgeApproval`** — the recorded approval of a specific version: approver, timestamp, decision, approved reuse scope, conditions.
- **`KnowledgeReview`** — a completed or scheduled review event: reviewer, date, outcome, next `ReviewDueAt`.
- **`KnowledgeSourceReference`** — provenance to a source `Document`/`DocumentVersion`, Matter, Legal Intelligence citation, or external source; itself access-controlled (§K6).
- **`KnowledgeAssociation`** — a typed relationship to a `PracticeArea`, another `KnowledgeItem` (variant, supersedes, relates-to), a `Matter`, or a `CommunicationThread`, always by identifier.

**Value objects** (immutable, no identity)

`KnowledgeClassification` (type + confidentiality + practice context), `KnowledgeOwner`, `ReviewDueAt`, `PublicationEligibility`, `RetrievalEligibility`, `CurationDecision` (the recorded reuse determination and resulting audience), `DeIdentificationReview` (what was assessed, what was changed, residual risk accepted), `AIAnnotation` provenance, `MatchConfidence`.

**`Precedent`, `Clause`, `Playbook`, `PracticeNote`, `ResearchNote` are `KnowledgeClassification` values, not separate aggregates.** They share one lifecycle, one approval model, one ownership and review model, and one access model; distinguishing them by classification rather than by aggregate keeps that governance in exactly one place instead of five near-identical copies. Their type-specific structure (a clause's variants, a playbook's ordered steps) lives in the version content and in typed `KnowledgeAssociation`s, not in divergent aggregate shapes.

**Why `KnowledgeVersion` stays inside `KnowledgeItem`** — same reasoning as `DocumentVersion` (§23): a version's meaning is inseparable from its item's identity and classification, and approval of a new version is precisely the invariant the aggregate exists to protect.

**Why `KnowledgeAccessPolicy` is separate but `KnowledgeApproval` is not** — approval is a fact *about a version*, meaningless outside it, and immutable once recorded. Access policy is a living decision *about the item* that changes independently of content and must be readable and revocable without touching versions.

## K27. Knowledge invariants

- A `KnowledgeItem` belongs to **exactly one** Firm, for its entire life.
- An **approved** `KnowledgeVersion`'s content, author, timestamp, and approval record are **immutable**.
- Approval requires an **authorized human**; no AI or automated path can approve or publish.
- An item in Approved, Published/Internal Use, or Superseded state has **at least one** approved version.
- Updating approved content **creates a new version**; it never edits an approved one in place.
- An **approved item must have** a Firm, a `KnowledgeOwner`, an immutable approved version, provenance, a `KnowledgeApproval`, a `KnowledgeAccessPolicy`, and a `KnowledgeReviewPolicy`. Missing any of these makes it invalid, not merely incomplete.
- `KnowledgeAccessPolicy` may only **narrow** Matter-derived access; a policy widening it, or overriding an Ethical Wall, is invalid.
- A `KnowledgeItem` derived from Matter work product exists **only** via a recorded `CurationDecision`; there is no automatic promotion path.
- Firm-wide reuse of Matter-derived content requires an explicit authorized decision; absent one, the source's restriction applies.
- Draft, rejected, superseded, retired, inaccessible, and quarantined items are **not retrieval-eligible by default**.
- Index and embedding entries **inherit** their source item's Firm and access boundary; cross-Firm entries are invalid.
- Revocation, supersession, retirement, and access-policy change **remove the item from future retrieval** without deleting audit history.
- A legal hold on a source `Document` remains effective on that Document regardless of any derived `KnowledgeItem`.
- Collection membership and practice-area association **never** grant access.
- Overdue review status is **always visible** wherever the item is presented or retrieved.

## K28. Knowledge commands

`CreateKnowledgeItem`, `CreateKnowledgeVersion`, `SubmitKnowledgeForReview`, `ApproveKnowledgeItem`, `RejectKnowledgeItem`, `PublishKnowledgeItem`, `SupersedeKnowledgeItem`, `RetireKnowledgeItem`, `CurateKnowledgeFromDocument`, `RestrictKnowledgeAccess`, `ScheduleKnowledgeReview`, `MarkKnowledgeReviewComplete`, `RevokeKnowledgeRetrievalEligibility`.

Supporting commands the model implies: `AssignKnowledgeOwner`, `TransferKnowledgeOwnership`, `AddKnowledgeToCollection`, `AssociateKnowledgeWithPracticeArea`, `RecordDeIdentificationReview`, `OfferKnowledgeForPublicPublication`.

Every command is authorized against `FirmContext` plus §K19–§K20. **`ApproveKnowledgeItem`, `PublishKnowledgeItem`, `CurateKnowledgeFromDocument`, `RestrictKnowledgeAccess`, `RetireKnowledgeItem`, and `RevokeKnowledgeRetrievalEligibility` additionally require explicit human authorization and are never AI-executable** (§K22).

## K29. Knowledge queries

`GetKnowledgeItem`, `SearchKnowledge`, `ListApprovedPrecedents`, `ListApprovedClauses`, `ListPracticeAreaKnowledge`, `GetKnowledgeProvenance`, `GetKnowledgeReviewHistory`, `CheckKnowledgeAccess`, `RetrieveAuthorizedKnowledgeContext`, `ListKnowledgeDueForReview`.

- **Every query is permission-aware at evaluation time**, never a raw read the caller filters afterward.
- `RetrieveAuthorizedKnowledgeContext` is the **only** sanctioned path for assembling AI/RAG context, applying §K22's Firm-then-permission-then-eligibility filtering and returning item identity, version, approval status, source references, access decision, and citations alongside content.
- `CheckKnowledgeAccess` is the published authorization check other contexts call rather than re-deriving knowledge permissions.
- `GetKnowledgeProvenance` returns only provenance the caller is authorized to see (§K6) — it is never a back door to a restricted Matter's existence.

## K30. Knowledge events

`KnowledgeItemCreated`, `KnowledgeVersionCreated`, `KnowledgeSubmittedForReview`, `KnowledgeItemApproved`, `KnowledgeItemRejected`, `KnowledgeItemPublished`, `KnowledgeItemSuperseded`, `KnowledgeItemRetired`, `KnowledgeCuratedFromDocument`, `KnowledgeAccessRestricted`, `KnowledgeReviewDue`, `KnowledgeRetrievalEligibilityRevoked`.

Supporting events the model implies: `KnowledgeOwnerAssigned`, `KnowledgeReviewCompleted`, `KnowledgeAccessDenied`, `KnowledgeRetrieved`, `KnowledgeOfferedForPublicPublication`.

**Events carry identifiers and safe metadata only — never privileged content, knowledge body text, document bytes, or embeddings.** `KnowledgeCuratedFromDocument` records *that* an item was curated from a source, by whom, and under what decision; it never carries the source's content, and its source identifiers are subject to the same provenance access control as §K6 when projected into any read model.

## K31. Knowledge failure modes

Conceptual failure modes the architecture must account for (mitigations are implementation-story-level detail):

- **Stale approved guidance relied on as current** — mitigated by mandatory ownership, review policy, `ReviewDueAt`, and always-visible overdue status; never by assuming approval is permanent.
- **Draft or AI-drafted content treated as approved** — Draft is structurally distinct, excluded from approved-knowledge search and standard retrieval by default, and never citable as Firm guidance.
- **Confidential Matter content leaking into Firm-wide knowledge** — mitigated by mandatory human curation with five-question review (§K18), no automatic promotion, and source-restriction-by-default.
- **Re-identification of a de-identified precedent** — mitigated by requiring re-identification-risk review rather than name removal, and by recording residual risk accepted.
- **Restricted Matter existence leaking through provenance, titles, tags, snippets, or embeddings** — mitigated by access-controlled provenance and by index/embedding entries inheriting the source access boundary.
- **Inaccessible content entering AI context** — mitigated by Firm-then-permission filtering *before* retrieval; post-generation filtering is explicitly not a control.
- **Cross-Firm embedding or cache bleed** — structurally prohibited; index and embedding entries are Firm-scoped, and shared caches or evaluation datasets across Firms are defects, not optimizations.
- **Stale index after access change** — access-policy change, revocation, supersession, and retirement must invalidate or remove index and embedding entries; an index that lags an access revocation is a confidentiality failure, not a performance detail.
- **Orphaned knowledge after an owner departs** — ownership transfer is a recorded operation; unowned approved items are invalid and must surface for reassignment.
- **Approval bypass** — every approval path requires a recorded human approver; an approval without one is invalid.
- **Conflicting knowledge** — two approved items giving contradictory guidance are surfaced for human resolution (AI may detect the conflict, §K22); the system never silently picks one.
- **Cited law changed underneath a research note** — supersession status from Legal Intelligence surfaces on presentation (§K24); the note is never silently presented as current.
- **Retrieval dependency unavailable** — an unresolvable permission or eligibility check denies retrieval (fail closed); degraded retrieval never falls back to unfiltered retrieval.

## K32. Knowledge explicit non-goals

The Knowledge area does **not**:

- Own official legal sources, translations, court decisions, or authoritative citations (Legal Intelligence).
- Own public website content, its editorial lifecycle, presentation, or publication scheduling (Digital Presence).
- Own Clients, Matters, `MatterClient`s, `MatterTeam`s, `PracticeArea` taxonomy, or Ethical Wall definitions (Practice Management).
- Own communication threads or messages (Communications).
- Own branding configuration (Branding).
- Define a workflow or automation engine — playbooks are guidance, not execution (§K13).
- Define migrations, Eloquent models, table names, vector-store schemas, index structures, or embedding formats.
- Select or endorse an embedding model, vector database, search engine, or AI processor.
- Specify chunking strategy, embedding dimensionality, similarity thresholds, ranking algorithms, or retrieval tuning.
- Specify de-identification, re-identification-risk-scoring, clause-conflict, or duplicate-detection algorithms.
- Assert a universal review interval for any knowledge type.
- Authorize cross-Firm knowledge sharing, distribution, or a Marketplace capability (§K34).
- Schedule any implementation work.

## K33. Knowledge proposed implementation stages

Knowledge delivery is stages 5–10 of the single EPIC-007 sequence in §33 and `docs/architecture/08_Roadmap.md`: Knowledge Item and version foundation → typed knowledge management (precedents, clauses, templates, playbooks, notes) → Matter-to-knowledge curation with confidentiality review and de-identification → review, supersession, retirement, ownership, and staleness → permission-aware search and governed AI/RAG → retention, legal hold, audit, Digital Presence publication, and integration hardening.

**Proposed only.** None of these stages is an approved, scheduled, or numbered story. Each requires its own entry in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`, with a Definition of Ready and Definition of Done, before implementation begins. Knowledge's curation and access stages depend on this context's own document foundation and on EPIC-006's `Matter`/`MatterClient` and Ethical Wall stages.

## K34. Knowledge future expansion

Each of the following is **describable as a future capability and is not implemented, not architected in detail, and not scheduled** by this document. Each would require its own architecture pass and its own approved implementation stories:

- **Knowledge analytics and reuse metrics** — which guidance is actually used, as read-model projections over knowledge events, never as fields on the aggregate.
- **Cross-matter precedent comparison** — structured comparison across precedents, subject unchanged to §K19–§K22.
- **Automated clause-conflict detection** — detection surfaces conflicts for human resolution; it never auto-resolves or auto-supersedes.
- **Multilingual knowledge** — per-language knowledge with Legal Intelligence-linked citations, distinct from legal-source translation authority (Constitution Article 3).
- **Expertise location** — "who in the Firm knows this," derived from authorship and review history, subject to the same permission filtering.
- **Knowledge graph** — generalizing `KnowledgeAssociation` and `KnowledgeSourceReference` into a broader relationship graph, complementing the document knowledge graph in §34 and Practice Management's `ConflictRelationship` graph.
- **Workflow consumption of playbooks** — a future Workflow context driving automation from approved playbooks through published contracts (§K13).
- **Marketplace distribution of approved template or knowledge packages** — a future Marketplace may distribute separately governed, approved packages. This would require its own architecture pass against `docs/architecture/06_Marketplace.md`, which **remains an empty placeholder and is not populated by this document**. **No Marketplace capability is claimed, architected, or implemented**, and cross-Firm knowledge distribution remains prohibited (§K22, §K27) until a separately approved architecture establishes how such packages are governed.

Every item above is additive to the existing `KnowledgeItem`/`KnowledgeVersion`/`KnowledgeAccessPolicy`/`RetrievalEligibility` model, not a redesign of it — the same extension discipline applied throughout this platform's architecture.

## Phased implementation guidance

See `docs/architecture/08_Roadmap.md`, the proposed EPIC-007 — Documents & Knowledge Management epic, for the staged delivery order restated at epic level, covering both domain areas. That staging is **proposed only**; formal scheduling requires entries in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` and separate story-level approval before implementation begins. No `PF-*` story numbering or approved implementation sequence is changed by this document, and PF-010 remains the current repository implementation story.
