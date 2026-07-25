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

## Article 10 — White-label by design

OneLegalPro is a white-label platform, not a co-branded one. Every Firm-facing and client-facing surface — website, client portal, email, generated documents, and the AI assistant — must present as the Firm's own product. "OneLegalPro" is the platform operator's identity, never a mark a Firm's client is required to see. See `docs/architecture/10_White_Label_Platform_Architecture.md` and `docs/adr/ADR-003-White-Label-Platform.md`.

## Article 11 — Branding ownership and isolation

A Firm's brand — theme values, assets, domain, sender identity, and AI assistant persona — is firm-scoped data isolated by `FirmContext`, on the same discipline as any other firm-owned data (Article 5). Branding is a presentation concern only: it must never alter legal-content substance, AI governance disclosures (Article 6), citation and provenance requirements, or the mandatory disclaimer (Articles 3–4). No branding configuration may suppress a requirement established elsewhere in this Constitution.

## Article 12 — Communications AI conduct

Every AI capability that touches a communication with a Lead or Client — the Website AI Receptionist, Email Intelligence, and general AI Assistance — must identify itself as AI, must never imply that interacting with it creates an attorney-client relationship, must never give legal advice, and must recommend consultation with a lawyer where a question approaches legal-advice territory. This extends Article 6 to the Communications Hub specifically. See `docs/architecture/11_Communications_Hub_Architecture.md` and `docs/adr/ADR-004-Communications-Hub.md`.

## Article 13 — Communications data ownership

Every communication thread, message, and business-object link is firm-scoped data isolated by `FirmContext`, on the same discipline as any other firm-owned data (Article 5). There is no platform-global communications content subdomain — only channel adapter contracts and capability schemas are platform-global, static configuration. AI-generated annotations on a communication (summaries, classifications, suggested replies, suggested links) must remain structurally distinct from the raw communication content they annotate, and no AI-authored communication may be sent to a Lead or Client without configured human authorization.

## Article 14 — Digital Presence composition discipline

Digital Presence (public websites, the Client Portal, embedded widgets, and booking) must consume the Branding Engine (Articles 10–11) and the Communications Hub (Articles 12–13) through their published contracts rather than reimplementing branding or messaging logic. The AI capability embedded in any Digital Presence surface is the Communications Hub's AI receptionist, not a separate AI system, and remains bound by Article 12 in full. See `docs/architecture/12_Website_Client_Portal_Architecture.md` and `docs/adr/ADR-005-Website-Client-Portal.md`.

## Article 15 — Digital Presence data ownership

Every content item, client portal identity, availability schedule, booking request, and widget embed is firm-scoped data isolated by `FirmContext`, on the same discipline as any other firm-owned data (Article 5). Client Portal surfaces (Matters, Documents, Invoices, Payments, Tasks, Appointments) are read/write views over their owning modules' published contracts, never duplicated into Digital Presence's own schema. Firm-published Knowledge Publishing content (articles, FAQs, legal updates) is firm-owned editorial material under Article 7's separation-of-official-law framework and must never be presented as, or conflated with, an official legal source.

## Article 16 — Practice Management as the platform's core domain

`Matter` is the platform's central aggregate. Every bounded context that needs to know about a Client, Matter, Task, or Appointment reaches Practice Management only through its published contracts (extending Article 5's cross-module access principle to this specifically central context) and never duplicates that data into its own schema. Practice Management, in turn, must never absorb ownership of Communications, Documents, Billing, Legal Intelligence, or Branding — those remain separate bounded contexts it integrates with, not modules it subsumes. See `docs/architecture/13_Practice_Management_Architecture.md` and `docs/adr/ADR-006-Practice-Management-Core.md`.

## Article 17 — Ethical Walls and conflict integrity

Restricted-Matter access must be authorized only through Practice Management's published Ethical Wall check; no other bounded context may implement its own wall logic. Any emergency override of an Ethical Wall requires an explicit recorded justification and produces its own auditable event — never a silent bypass. AI may summarize a Matter and suggest tasks, timelines, practice area, lawyer, and deadlines, but must never change matter status, assign lawyers, close matters, or override Ethical Walls without explicit human authorization. This extends Article 6 with the explicit, professional-responsibility-driven prohibitions Practice Management requires.

## Article 18 — Documents as the canonical owner of legal work product

Documents is the sole owner of the canonical document record and of every stored document version representing a Firm's legal work product. Stored versions are immutable: a correction or replacement creates a new version, and historical bytes, checksums, authorship, timestamps, and provenance remain preserved — the same immutability discipline Article 8 establishes for published legal sources, applied here to firm work product. No other bounded context stores, versions, or claims ownership of document content, and Documents in turn never claims ownership of official legal sources (Articles 2 and 7), Client or Matter records (Article 16), communications content (Article 13), branding configuration (Article 11), or the commercial meaning of an invoice. A rendered artifact stored through Documents is a file, never the business record it depicts. See `docs/architecture/14_Document_Management_Architecture.md` and `docs/adr/ADR-007-Document-Management.md`.

## Article 19 — Document confidentiality, access inheritance, and preservation

Document content is firm-scoped, private by default, and never reachable through a public, permanent, or guessable location; delivery occurs only through authorized, short-lived mechanisms after every applicable Firm, actor, Matter, Ethical Wall, and audience check has passed, and a denied caller receives no content, metadata, preview, search result, or confirmation that the document exists.

Matter permissions and Ethical Walls (Article 17) are inherited as mandatory restrictions: document-level controls may narrow access further but may never widen it beyond the Matter boundary, and where a document is reachable from more than one restricted Matter the most restrictive outcome governs. Client Portal visibility is explicit and deny-by-default — internal availability never implies client visibility, publication is a distinct recorded decision, and audience resolution names specific `MatterClient`s so that one co-client on a jointly represented Matter never sees another's confidential document (extending Article 15's read-surface discipline to the domain where its failure is a privilege incident).

Retention policy is Firm- and jurisdiction-aware rather than a single platform-wide period, archival is distinct from deletion, and a legal hold overrides ordinary deletion, retention, and destructive-redaction processing until an authorized human explicitly releases it. Deleting content never destroys the audit fact that the document existed.

AI and OCR output over documents — extracted text, summaries, classifications, and suggestions — is derived annotation, never authoritative content or metadata: it remains structurally separate from the source version, carries model provenance and confidence, and is removable without altering the source. AI may never, without explicit human authorization, alter canonical content, finalize/sign/file/send/publish/delete a document, change legal-hold or retention state, broaden internal or portal access, confirm a low-confidence Matter or Client association, or send privileged content to an unapproved model or processor. This extends Article 6 with the confidentiality-driven prohibitions document handling requires.

## Amendment

Amendments to this Constitution require an approved ADR and explicit human sign-off, consistent with the approval gates in `AGENTS.md`.
