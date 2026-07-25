# ARCH-014 — Document Management Architecture

**Status:** Approved (conceptual architecture) — implementation stories are proposed, not scheduled; see `docs/architecture/08_Roadmap.md`.

## Purpose and scope

This document defines the conceptual domain and system architecture for OneLegalPro's Document Management capability, implementing `docs/adr/ADR-007-Document-Management.md` and the relevant articles of `docs/architecture/01_OneLegalPro_Constitution.md`. It covers the lifecycle of firm and matter work-product documents: their creation, immutable versioning, classification, association with Matters and Clients, internal and client-facing access, retention, legal holds, and the derived AI/OCR annotations layered over them.

This document describes **conceptual models only**. It does not define migrations, Eloquent schemas, table names, provider-specific storage APIs, or implementation code — those belong to future, separately approved implementation stories (see `docs/architecture/08_Roadmap.md`).

## 1. Purpose and scope

Documents are the artifacts a law firm's work product actually consists of: engagement letters, pleadings, contracts, evidence, correspondence, court filings, identity documents, and the generated invoices and reports the platform itself produces. Nearly every other bounded context already assumes their existence:

- Practice Management (`docs/architecture/13_Practice_Management_Architecture.md`) shows Documents on the Matter Timeline and Matter Dashboard, and states plainly that it "never stores or versions document content."
- Communications (`docs/architecture/11_Communications_Hub_Architecture.md` §6) extracts email attachments and makes them "available to the Documents domain, subject to that module's own rules."
- Digital Presence (`docs/architecture/12_Website_Client_Portal_Architecture.md` §4, §8) surfaces Documents in the Client Portal and offers a Document Upload Widget.
- Branding (`docs/architecture/10_White_Label_Platform_Architecture.md` §9) wraps "canonical document content" in firm letterhead.

This document defines what that canonical content is, who owns it, and how access to it is authorized.

**In scope:** the canonical document record, immutable versions, checksums and provenance, classification, Matter/Client association, internal and portal access control, upload validation and quarantine, private storage and secure delivery, metadata/search/organization, templates and generation, retention/archival/deletion/legal hold, auditability, and AI/OCR governance.

**Out of scope:** everything listed in Explicit non-goals (§32).

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

- **`DocumentTemplate`** is a firm-scoped aggregate with its own lifecycle (draft, active, versioned, retired) — deliberately **outside** the `Document` aggregate, because a template's lifecycle is independent of any document produced from it, and a template change must never retroactively alter documents already generated (§23).
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

**Proposed module structure** (unresolved implementation choice: exact module name; this document proposes `Documents`, consistent with the module-per-bounded-context pattern already established for `LegalIntelligence`, `Branding`, `Communications`, `DigitalPresence`, and `PracticeManagement`):

```text
Documents/
├── Application/
│   ├── Documents/     (CreateDocument, UploadDocumentVersion, ClassifyDocument,
│   │                    AssociateDocumentWithMatter, SupersedeDocument, ArchiveDocument, ...)
│   ├── Access/         (RestrictDocumentAccess, PublishDocumentToPortal,
│   │                     RevokePortalDocumentAccess, CheckDocumentAccess, ...)
│   ├── Retention/      (PlaceLegalHold, ReleaseLegalHold, RequestDocumentDeletion,
│   │                     ExecuteApprovedDocumentDeletion, ApplyRetentionPolicy, ...)
│   ├── Templates/      (GenerateDocumentFromTemplate, PublishTemplate, RetireTemplate, ...)
│   └── Intelligence/   (RecordDocumentAnnotation, AcceptSuggestedClassification,
│                         RemoveDocumentAnnotation, ...)
├── Domain/
│   ├── Document                     (aggregate root)
│   ├── DocumentAccessPolicy, PortalDocumentAudience   (aggregate roots)
│   ├── LegalHold, DocumentRetentionPolicy             (aggregate roots)
│   ├── DocumentTemplate, DocumentCollection           (aggregate roots)
│   ├── DocumentVersion, DocumentAssociation, DocumentAnnotation   (entities)
│   ├── DocumentClassification, DocumentType, ConfidentialityClassification,
│   │   DocumentChecksum, DocumentSource, StorageObjectReference, MediaType,
│   │   ByteSize, RetentionRule, AudienceEntry, AIAnnotation, MatchConfidence  (value objects)
├── Infrastructure/    (object storage adapter, malware-scanning adapter, OCR adapter,
│                        document-conversion adapter, Eloquent adapters, search index adapter)
├── Interface/          (permission-aware document queries, authorized delivery endpoints,
│                        portal document API)
├── Database/           (new migrations only — no historical migrations touched)
├── Routes/
├── Tests/
├── Config/             (seeded document-type taxonomy, size/media-type limits,
│                        retention policy defaults, approved AI processor policy)
├── ModuleServiceProvider.php
└── README.md
```

## 29. Multi-tenancy and Firm isolation

- Every `Document`, `DocumentVersion`, annotation, policy, audience, hold, template, and collection is **firm-scoped**, isolated by `FirmContext`.
- Firm identity is **explicit** on every record and enforced at the **application and repository** layers — never by global query scopes alone, per `docs/domain/06_Laravel_Module_Blueprint.md`.
- Storage isolation mirrors record isolation: one Firm's objects are never reachable through another Firm's authorization context, and a delivery grant is scoped to one Firm's object.
- Documents has **no platform-global content subdomain** — the fifth module, after Branding, Communications, Digital Presence, and Practice Management, to take a wholly firm-scoped shape:

| Subdomain | Scope | Ownership boundary |
|---|---|---|
| Seeded `DocumentType` taxonomy, `ConfidentialityClassification` schema, size/media-type limit schema | Platform-global | Not `FirmContext` — static configuration shared by every Firm |
| `Document`, `DocumentVersion`, `DocumentAnnotation`, `DocumentAssociation`, `DocumentAccessPolicy`, `PortalDocumentAudience`, `LegalHold`, `DocumentRetentionPolicy`, `DocumentTemplate`, `DocumentCollection`, firm-custom document types | Firm-scoped | `FirmContext` |
| Storage and provider credentials | Firm-scoped or platform-operated, per deployment | Stored as secrets, never plain configuration |

Legal Intelligence's platform-global legal sources remain the platform's one genuine exception (§19); they are not documents.

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

**Proposed only.** None of these stages is an approved, scheduled, or numbered story. Each requires its own entry in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`, with a Definition of Ready and Definition of Done, before implementation begins.

1. **`Document`/`DocumentVersion` foundation** — aggregate, immutable versioning, checksums, provenance, Firm isolation.
2. **Private storage and secure delivery** — object-storage adapter, `StorageObjectReference`, short-lived authorized delivery.
3. **Upload, validation, and quarantine pipeline** — size/media-type validation, filename normalization, malware-scanning adapter, fail-closed acceptance.
4. **Classification and metadata** — seeded `DocumentType` taxonomy, `ConfidentialityClassification`, firm-scoped extension.
5. **Matter and `MatterClient` association** — `DocumentAssociation`, referential integrity against Practice Management's published contracts.
6. **Internal access control and Ethical Wall integration** — `DocumentAccessPolicy`, restriction-only composition, `CheckEthicalWallAccess` integration, most-restrictive-wins.
7. **Client Portal audience and publication** — `PortalDocumentAudience`, deny-by-default resolution, co-client isolation, revocation.
8. **Communications attachment integration** — inbound attachment ingestion and outbound authorized delivery.
9. **Search, tags, and collections** — permission-aware search at query time, `DocumentCollection`.
10. **Templates, generation, and Branding integration** — `DocumentTemplate`, generation provenance, `PDFBrandingConfig` composition.
11. **Retention, archival, deletion, and legal hold** — `DocumentRetentionPolicy`, `LegalHold`, governed deletion path, tombstones.
12. **AI/OCR document intelligence** — `DocumentAnnotation`, provenance and confidence, approved-processor policy, human acceptance of suggestions.
13. **Audit, export, and Matter Timeline contribution** — audit history queries, authorized export, event projection into Practice Management's Timeline.

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

## Phased implementation guidance

See `docs/architecture/08_Roadmap.md`, the proposed EPIC-007 — Documents epic, for the staged delivery order restated at epic level. That staging is **proposed only**; formal scheduling requires entries in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` and separate story-level approval before implementation begins. No `PF-*` story numbering or approved implementation sequence is changed by this document.
