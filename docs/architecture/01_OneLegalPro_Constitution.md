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

Documents is the sole owner of the canonical document record and of every stored document version representing a Firm's legal work product. Stored versions are immutable: a correction or replacement creates a new version, and historical bytes, checksums, authorship, timestamps, and provenance remain preserved — the same immutability discipline Article 8 establishes for published legal sources, applied here to firm work product. No other bounded context stores, versions, or claims ownership of document content, and Documents in turn never claims ownership of official legal sources (Articles 2 and 7), Client or Matter records (Article 16), communications content (Article 13), branding configuration (Article 11), or the commercial meaning of an invoice. A rendered artifact stored through Documents is a file, never the business record it depicts. See `docs/architecture/14_Document_Knowledge_Management_Architecture.md` and `docs/adr/ADR-007-Document-Knowledge-Management.md`.

## Article 19 — Document confidentiality, access inheritance, and preservation

Document content is firm-scoped, private by default, and never reachable through a public, permanent, or guessable location; delivery occurs only through authorized, short-lived mechanisms after every applicable Firm, actor, Matter, Ethical Wall, and audience check has passed, and a denied caller receives no content, metadata, preview, search result, or confirmation that the document exists.

Matter permissions and Ethical Walls (Article 17) are inherited as mandatory restrictions: document-level controls may narrow access further but may never widen it beyond the Matter boundary, and where a document is reachable from more than one restricted Matter the most restrictive outcome governs. Client Portal visibility is explicit and deny-by-default — internal availability never implies client visibility, publication is a distinct recorded decision, and audience resolution names specific `MatterClient`s so that one co-client on a jointly represented Matter never sees another's confidential document (extending Article 15's read-surface discipline to the domain where its failure is a privilege incident).

Retention policy is Firm- and jurisdiction-aware rather than a single platform-wide period, archival is distinct from deletion, and a legal hold overrides ordinary deletion, retention, and destructive-redaction processing until an authorized human explicitly releases it. Deleting content never destroys the audit fact that the document existed.

AI and OCR output over documents — extracted text, summaries, classifications, and suggestions — is derived annotation, never authoritative content or metadata: it remains structurally separate from the source version, carries model provenance and confidence, and is removable without altering the source. AI may never, without explicit human authorization, alter canonical content, finalize/sign/file/send/publish/delete a document, change legal-hold or retention state, broaden internal or portal access, confirm a low-confidence Matter or Client association, or send privileged content to an unapproved model or processor. This extends Article 6 with the confidentiality-driven prohibitions document handling requires.

## Article 20 — Firm Knowledge Governance

A Firm's curated know-how — precedents, clauses, templates, playbooks, practice notes, research notes, and internal guidance — is a governed asset distinct from both the source documents it derives from (Article 18) and the official legal sources Legal Intelligence owns (Articles 2 and 7). A source document and a curated knowledge item are separate records with different authority, lifecycle, audience, and governance; neither is a state of the other.

Knowledge becomes Firm guidance only by the act of an authorized human. Approval is version-specific and always human — no automated, confidence-based, or AI approval path exists. Each approved version is immutable: updating approved content creates a new version, and superseded and retired versions remain auditable rather than disappearing. Every approved knowledge item has an owning Firm, a named accountable owner, provenance, an approval record, an access policy, and a review policy; an approved item lacking any of these is invalid. Overdue review status is always visible, and expired or overdue guidance is never silently presented as current.

A Matter document never automatically becomes Firm-wide knowledge. Promotion requires an explicit human curation workflow that creates a separate knowledge record, retains access-controlled provenance to its source, and reaches recorded determinations on confidentiality, privilege, personal-data, contractual restriction, and Ethical Wall exposure before any reuse audience is established. Removing names does not make content safely de-identified — re-identification risk from context must be assessed, and where it cannot be sufficiently reduced the correct outcome is a narrower audience or no knowledge item at all. Source restrictions, and any legal hold on the source, remain fully effective until and unless an explicit, authorized declassification decision establishes a different safe audience.

Firm know-how is never authoritative law and is never presented as such. Internal knowledge and public editorial content remain distinct: public presentation and its lifecycle belong to Digital Presence (Article 15), and publishing publicly never silently changes an internal item's access policy.

## Article 21 — Permission-Aware Knowledge Retrieval

Knowledge search and AI retrieval are filtered by Firm and then by permission **before** results are assembled or any content enters model context — never after retrieval, and never repaired after generation. Post-generation filtering is not a valid security control: content that has entered context has already influenced the output.

Search and retrieval must never disclose inaccessible titles, snippets, tags, metadata, provenance, citations, embeddings, or the existence of an item. Search-index entries and embeddings are derivatives that inherit the Firm and access boundary of the content they encode; a change of access policy, a revocation, a supersession, or a retirement removes or invalidates them for future retrieval without rewriting the audit record of access that legitimately occurred before. Knowledge access may narrow Matter-derived access but never overrides an Ethical Wall (Article 17), and only approved, retrieval-eligible, access-authorized content enters standard retrieval — draft, rejected, superseded, retired, inaccessible, and quarantined content is excluded by default.

Retrieval preserves item identity, version, approval status, source references, the access decision that permitted it, and citations. Generated synthesis remains structurally distinct from quoted knowledge content (Article 7), and any claim about official law resolves through Legal Intelligence's authority-aware citation rules rather than through Firm know-how. A Firm's knowledge, embeddings, caches, evaluation data, and model context are never shared across Firms.

AI may suggest, draft, propose candidates for curation, identify stale or conflicting guidance, and retrieve authorized knowledge for drafting assistance. AI may never, without explicit human authorization, approve or publish knowledge, make Matter-derived knowledge Firm-wide, remove confidentiality/privilege/contractual/personal-data/Ethical Wall restrictions, change an approved version, retrieve inaccessible content, mix Firms' knowledge, treat draft or AI-generated output as approved Firm guidance, present Firm know-how as an official legal source, or send privileged content to an unapproved processor. This extends Articles 6 and 19 to knowledge retrieval specifically.

## Article 22 — Billing and financial-domain ownership

Billing is the sole owner of the Firm's commercial billing records, its client-money/trust ledgers, and its financial accounting records. It contains three explicitly separated financial domain areas — Billing and Accounts Receivable, Client Money/Trust Accounting, and Firm Finance and Accounting — which may share governed financial primitives but retain distinct aggregates, ledgers, lifecycles, permissions, and accounting meanings.

Billing never owns Clients, Matters, `MatterClient`s, Matter teams, or Ethical Wall definitions (Articles 16–17); it never stores document bytes or rendered artifacts (Article 18); it never owns Client Portal presentation (Article 15), communications content (Article 13), branding configuration (Article 11), or official law (Articles 2 and 7). Conversely, no other bounded context owns an invoice, a payment, a client-money balance, or a ledger entry. Documents owns the rendered invoice, receipt, statement, and tax-document artifact; Billing owns its commercial meaning and lifecycle — a rendered artifact depicts a financial record and is never itself that record. Every other module reaches Billing only through published contracts, and Billing reaches every other context only through theirs. Billing's effective financial and tax configuration is a Firm's authorized professional determination and never becomes a second source of authoritative law. See `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` and `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md`.

## Article 23 — Financial precision, immutability, and auditability

Financial values are exact decimal amounts with an explicit currency. Floating-point money is prohibited platform-wide. This money representation is a **Foundation Library primitive** — shared technical primitives belong in `app/Foundation`, and the platform's approved Foundation Library reserves it — so it is owned once, platform-wide, and consumed by every module that handles an amount. No bounded context, Billing included, may define a second or incompatible money or currency type. What Billing owns is the financial-domain meaning layered over that primitive, including exchange-rate provenance, tax treatment, and accounting policy. Every multi-currency amount preserves its transaction currency, base currency, effective exchange rate, rate source, and rate timestamp, and a historical conversion is never silently recalculated with a newer rate.

Issued invoices and posted ledger entries are immutable, on the same discipline Article 8 establishes for published legal sources and Article 18 for document versions. **Posted financial history is corrected, never rewritten**: a correction is an adjustment, reversal, credit note, debit note, or replacement record, and the original remains. **All financial balances are derived from immutable, append-only ledger entries; direct mutation of a balance is prohibited** — there is no editable balance anywhere in the platform. Accounting periods have explicit open, closing, closed, and exceptionally reopened controls, and any closed-period posting or reopening requires authorization, a recorded reason, and audit history. Every consequential financial action, and every denied access to a restricted financial record, is an auditable event; audit records are append-only, and deleting a record never destroys the audit fact that it existed.

## Article 24 — Client-money segregation and fiduciary control

Client money means money held by the Firm that is not the Firm's own money. It is never the Firm's revenue. Funds held for or on behalf of a client or another entitled party are segregated from Firm operating funds in accounting meaning, in ledgers, and in access controls; silent commingling is prohibited by the model, not merely discouraged. A client-money balance is never an invoice balance, and a payment does not become revenue merely because it was received.

Beneficial entitlement to a held balance is an explicitly recorded fact, never an inference. A balance may be beneficially owned by the client on the Matter, by an identified third-party beneficiary, or by a party pending an authorized entitlement determination, and entitlement must never be presumed from the depositor, the payer, the Primary `MatterClient`, or Matter membership. Accountable client and Matter subledger structure is retained in full; beneficial entitlement is recorded alongside it, not in place of it. Being named as a beneficiary or payer in a financial record confers no Matter, Client Portal, invoice, or document access.

Client-money balances derive from append-only, balanced ledger entries. Every receipt, disbursement, transfer, refund, and adjustment records the Firm, account, currency, amount, beneficiary attribution and entitlement basis, Matter attribution where applicable, purpose, source, authorization, and audit provenance; an unexplained client-money transaction is not a permitted state. No client or Matter balance may go negative, and one client's money may never fund another's obligation. A transfer from client money to the Firm's operating funds requires an eligible commercial obligation, explicit human authorization, and an auditable transfer record; cross-client and cross-Matter transfers are deny-by-default and require explicit authority, referential integrity, authorization, and Ethical Wall checks. Segregation of duties supports configurable maker, approver, and reconciler roles, and where policy requires independent review the creator of a high-risk client-money transaction must not silently self-approve it. Client-money reconciliation compares the bank balance, the trust control account, and the individual client and Matter subledgers; an unexplained difference remains visible and can never be erased by editing history.

## Article 25 — Financial authorization, jurisdiction policy, and Financial AI

Financial visibility is explicit and deny-by-default. Matter permissions and Ethical Walls (Article 17) are inherited as mandatory restrictions on financial records, with the most restrictive outcome governing where a record touches more than one restricted Matter, and a denied caller receives no balance, amount, invoice or payment or trust metadata, report, aggregate, or confirmation that a restricted record exists. A Primary `MatterClient` is only the default administrative and billing recipient — it never by itself determines the legally liable party, the invoice audience, the payer, a trust beneficiary, or any allocation. One client on a jointly represented Matter never sees another's invoice, payment method, payment, trust balance, or transaction absent an explicit authorized audience decision, and paying an invoice as a third party confers no Matter or Client Portal access.

Tax treatment, invoice and receipt requirements, withholding, numbering, retention, and client-money controls are effective-dated jurisdiction policy. No tax rate is hardcoded and no compliance is claimed; any jurisdiction-specific legal or tax treatment requires an official primary source and approval by an authorized human legal or accounting owner, with official Thai-language law authoritative for Thailand (Articles 1–4). Payment processing stays behind provider adapters: the platform stores tokenized or provider-held references only, never raw card credentials, CVV data, or reusable banking secrets, and a provider's status is never the platform's authoritative accounting entry. OneLegalPro is not a bank, escrow provider, custodian, or regulated payment service, and any future design in which it holds or settles funds on another party's behalf requires separate legal, regulatory, security, and architecture approval.

**AI exercises no financial authority.** Financial AI is advisory only, carries source identity, version, currency, "as of" timestamp, and provenance, and is filtered by Firm and permission before any financial content enters model context. Authoritative totals, balances, tax amounts, allocations, conversions, and aging are computed deterministically outside the language model; AI may explain such a calculation but may never produce one that is stored, posted, or relied on. AI may never, without explicit human authorization, issue or void an invoice, alter a rate, post a journal, allocate a payment, write off a balance, move client money, approve a disbursement, release a refund, close or reopen an accounting period, reconcile an account, change tax treatment, or expose restricted financial data. This extends Article 6 with the fiduciary prohibitions financial handling requires.

## Article 26 — Identity and actor ownership

Identity is owned by one bounded context. IdentityAccess owns Firm-scoped principals and actor identifiers, identity lifecycle, Firm membership, credentials and authenticators, sessions, invitations, roles and permission grants, delegations, service principals, privileged-access grants, and security events. No other bounded context stores credentials, authenticators, recovery material, or authoritative session state — including Digital Presence, whose Client Portal is a presentation surface and not an authentication authority (Article 15).

A principal is an authenticated subject, not a business record. It is never a `Client`, `Contact`, `Organization`, lawyer profile, or `MatterClient`. Business records reference an actor identifier and never own authentication. Practice Management retains ownership of Client, Matter, Matter Team, and the professional meaning of Matter Team roles (Article 16); a role name in IdentityAccess is an access construct and never itself proves professional qualification, which remains a separate approved business determination.

Human, system, integration, and AI actors must remain distinguishable in every audit record. An AI-assisted operation records the initiating human actor, the AI or system actor involved, the authorization it acted under, and any required human approval. **AI is never an authorization authority** and may never grant itself or anyone else a role, permission, membership, session, delegation, or privileged grant. Service principals are distinct from human principals, cannot authenticate through human login paths, and cannot silently obtain interactive privileges. See `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` and `docs/adr/ADR-009-Identity-Security-Access-Control.md`.

## Article 27 — Firm-scoped identity and tenant binding

A principal exists within a Firm security realm, and Firm access requires an active, verified Firm membership. Authentication occurs inside an explicitly resolved realm; every interactive session is bound to exactly one Firm context; and switching Firms requires an explicit, authorized transition that carries no resource authorization across.

A hostname, custom domain, email address, or client-supplied Firm identifier may identify a candidate Firm but never proves membership and never grants access. `FirmContext` is constructed only from verified identity and membership, never trusted because it arrived in a request parameter, header, hostname, cookie, or route. Email addresses are not globally unique business identities and must never be used to automatically merge or link identities across Firms; the same address may be independently invited by multiple Firms without either learning of the other, and no account-discovery or recovery response may reveal whether an identity exists in another Firm. Automatic cross-Firm account linking is prohibited, and any future linking capability requires its own approved ADR with explicit verification and consent, without weakening Firm isolation or white-label presentation (Articles 10–11). Platform-operator identities exist in a separate platform realm and hold no Firm access by default.

## Article 28 — Authentication, authorization, and domain-owned resource rules

Authentication and authorization are distinct, and **successful authentication is never resource authorization**. Access to a protected resource is a composition of the authenticated principal, active Firm membership, a Firm-bound session or service context, an assigned capability, the owning domain's action and resource semantics, Practice Management's Ethical Wall decision where Matter-linked data is involved, any narrower domain restriction, required step-up or segregation-of-duties policy, and explicit deny rules.

Authorization is **deny by default** and server-side; hiding a control is never authorization. Every repository and query is Firm-scoped, and permission filtering happens **before** retrieval, search, export, aggregation, AI context construction, and retrieval-augmented generation — never after (extending Article 21 to every domain). Bulk operations, reports, exports, background jobs, and AI retrieval receive no weaker treatment than interactive requests, and background work preserves the initiating actor and authorization provenance rather than becoming an indefinite permission grant.

**Resource-owning modules may narrow access; IdentityAccess may never widen it**, and the most restrictive applicable decision governs. **Practice Management alone owns Ethical Wall decisions** (Article 17): no role, permission, delegation, privileged grant, or break-glass grant overrides a wall, and no other context reimplements the check. Denied callers receive no protected content, metadata, count, search result, aggregate, or confirmation that a record exists. Revocation, suspension, role removal, wall changes, and security-policy changes constrain access promptly, and **no stale positive authorization may broaden access**.

## Article 29 — Credential, session, and privileged-access protection

Credentials, reusable secrets, recovery material, and full session tokens never appear in logs, analytics, events, or audit payloads. Passwords are stored only through an approved adaptive hashing mechanism; public-key authenticators store key material and never biometric data. Magic links and recovery flows are single-use, short-lived, purpose- and Firm-bound, and never permit open redirects. Account recovery is a privileged workflow that never silently bypasses MFA or Firm policy, authentication responses resist account enumeration, session identifiers rotate after authentication and privilege elevation, and high-risk actions require step-up authentication.

Platform operators hold no Firm-data access by default. **Silent impersonation is prohibited**: acting-as and acting-on-behalf-of identities are both recorded, and Firms must be able to see appropriate support-access history. Support access requires an explicit, purpose-bound, time-limited grant with strong authentication. Break-glass access is exceptional, time-limited, justified, visible, and immutably audited; it never disables Firm isolation, Ethical Walls, legal holds, financial segregation, or audit, and it never permits changing security ownership, silently broadening permissions, moving client money, or destroying audit history.

## Article 30 — Security audit and fail-closed behavior

Security events are append-only and record safe actor, Firm, event, result, timestamp, correlation, and provenance information without storing credentials, recovery tokens, session secrets, payment secrets, or privileged content. Audit history is not editable by the actor being audited, and operational logs are never the sole authoritative security record.

New authentication, privilege elevation, and sensitive operations requiring a fresh authoritative decision **fail closed** when the required authority is unavailable. An already-issued session may continue only within its validity and only where its locally verifiable policy and revocation requirements remain satisfied. High-risk operations fail closed where current membership, role, policy, Ethical Wall, or revocation state cannot be confirmed, and no stale cached Ethical Wall or positive authorization result may be used to broaden access. A notification, rendering, or analytics failure never rolls back an already committed security change.

**Availability never outranks Firm isolation, authorization, Ethical Walls, financial safeguards, or privilege protection.** The platform-wide security baseline is `docs/architecture/04_Security_Architecture.md`; it establishes control requirements and makes no certification or legal-compliance claim, and jurisdiction-specific privacy, cybersecurity, professional-conduct, retention, and breach-notification obligations require authoritative legal review and separately approved policy.

## Article 31 — External contract stability and versioning

Every external representation OneLegalPro exposes — a public API resource, an outbound integration event, a webhook payload — is an explicit, stable, versioned contract, never an internal model or internal domain event exposed as a byproduct of convenience. No Eloquent record, internal identifier, or internal event structure is presented externally. A breaking change to an external contract requires a new major version, a published deprecation notice, and a supported migration window before the prior version sunsets; the platform never silently reshapes an external contract to match an internal refactor. Public API breaking changes require explicit human approval before merge, consistent with the approval gates in `AGENTS.md`. See `docs/architecture/07_API_Standards.md` and `docs/architecture/17_API_Integration_Platform_Architecture.md`.

## Article 32 — No bypass of Firm or domain authorization through an API or integration

An API, webhook, connector, or integration is never a lower-authorization path than an equivalent interactive one. Every external request is Firm-scoped, with `FirmContext` constructed only from authenticated identity and an authorized installation — never from a hostname, header, request parameter, or body field (extending Article 27 to the platform's edge without exception). A requested or granted API scope narrows what its holder may reach; it never widens it, and it never overrides an Ethical Wall (Article 17) or any domain-owned restriction. Authorization is composed and evaluated before retrieval, search, count, export, or AI context construction, and a denied external caller receives no protected content, metadata, count, aggregate, search result, or confirmation that a restricted resource exists. Rate limiting and quotas are operational safeguards, never a substitute for authorization.

## Article 33 — Domains retain ownership of business truth

An API and Integration Platform translates a stable external contract into an owning domain module's published command or query; it never becomes a second owner of that domain's data or business rules, never writes directly to another module's tables, and never recreates a domain's authorization or invariants. Practice Management alone continues to authorize Matter-linked access through its published Ethical Wall check; each domain module continues to define the meaning of its own actions, resources, and restrictions. Provider-specific business semantics remain with the domain that owns the relationship they serve — payment-provider semantics with Billing, messaging-provider semantics with Communications, document semantics with Documents, identity-provider semantics with IdentityAccess — never absorbed into a generic integration layer.

## Article 34 — Retryable, idempotent, and auditable external delivery

External delivery is at-least-once; no mechanism in this architecture claims exactly-once delivery, and no claim of global ordering is made — ordering is guaranteed only per subject, and only where explicitly stated. Every retryable external command accepts an idempotency key, and every inbound webhook is authenticated, replay-resistant, and idempotent before any business meaning is attached to it. External calls are never made inside a domain database transaction. Every consequential action in the API and Integration Platform — application registration, installation, scope grant, credential-reference rotation, webhook subscription and delivery, connector connection and reconciliation, and import/export job request and retrieval — is an append-only, auditable event, and revoking an integration installation promptly stops its API access, webhook delivery, and connector activity without deleting the audit history of what already occurred under it.

## Article 35 — Actor provenance across external and AI-assisted operations

Human, service, integration, and AI actors remain distinguishable in every audit record produced through an API, webhook, connector, or integration, on the same discipline Article 26 establishes internally. A service principal authenticates only through IdentityAccess and never through a human login path, and an integration acting for a Firm records both its service principal and the Firm, plus the initiating human where one exists. AI holds no authority to grant, widen, or approve API access, scopes, installations, or privileged integration operations; where an AI-assisted process initiates or consumes an external call, the initiating human, the AI or system actor, the authorization relied on, and any required human approval are all recorded.

## Article 36 — Integration failure never rewrites domain state

A failure in rendering, delivery, a provider, or an integration dependency is visible and retried; it never rolls back, rewrites, or silently alters an already-committed business record, extending the discipline Articles 23 and 30 already establish to every external and integration path. New authentication, authorization, or Ethical Wall decisions required for an external request fail closed when the required authority is unavailable; an already-issued session or delivery in progress continues only within its own validity and only where its locally verifiable revocation state is satisfied. Availability never outranks Firm isolation, authorization, Ethical Walls, financial safeguards, or privilege protection at the platform's external edge, exactly as it does not internally.

## Article 37 — Deferred scope of orchestration and distribution

The API and Integration Platform coordinates external contracts, installations, delivery, and bulk operations; it does not orchestrate multi-step business processes across domains, and it does not govern public marketplace publication, monetization, partner certification, or cross-Firm distribution of integrations. Workflow orchestration and Marketplace distribution each require their own separately approved architecture before any such capability exists.

## Article 38 — Workflow as a supporting bounded context

`Workflow` is a supporting bounded context that owns orchestration state — reusable workflow definitions, immutable published versions, workflow runs, step-execution state, timers, wait conditions, approvals, and automation policy — and never a second owner of any other bounded context's business data or business rules. Published workflow versions are immutable; a running workflow remains bound to the exact version it started with, and a newer published version never silently alters an already-running instance. Every domain action a workflow step invokes passes through the owning module's published command or query and that module's own current authorization; same-process execution or an already-authenticated request is never license to bypass it, extending Article 28's authorization-composition principle to orchestrated, multi-step, and long-running work specifically. See `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md` and `docs/adr/ADR-011-AI-Copilot-Workflow-Automation.md`.

## Article 39 — AI Copilot is assistive, never an authorization or approval authority

The OneLegalPro Copilot is a governed internal professional-assistance capability. It is never a lawyer, an authorization authority, a domain-data owner, a ledger or calculator, an approver, a workflow definition, a workflow engine, a privileged system administrator, an autonomous agent with unrestricted tools, or a substitute for professional review. The Copilot may understand a request, retrieve authorized context, and propose a workflow action; it has no effect on any domain until that proposal passes the same authorization and approval composition (Article 28) any other action would, and it never writes another module's tables, imports another module's Eloquent model, holds provider credentials, executes arbitrary code, calls an unrestricted tool, bypasses Firm isolation, bypasses an Ethical Wall, or treats its own generated output as authoritative domain truth. This extends Article 6's human-oversight principle with the specific, enumerated prohibitions internal cross-domain AI assistance requires.

## Article 40 — Human approval, segregation of duties, and non-delegable actions

Human review is the default for any consequential action a workflow or the Copilot proposes. An approval is bound to the exact Firm, workflow run, workflow version, action, target resource, material-input fingerprint, approving actor, automation-policy version, and validity window; a change to the material input invalidates the approval, and a stale or expired approval may never be executed. Where policy requires separation of duties, the actor who proposed an action is never the actor who approves it, and **AI may never approve its own proposal or tool call under any configuration.** A Firm-configured `AutomationPolicy` may narrowly pre-authorize only explicitly eligible, bounded, low-risk actions; it may never become a blanket grant, and it may never reach a non-delegable action. AI may never, without explicit human authorization and regardless of any automation-policy configuration: present generated content as legal advice or official law; create an attorney-client relationship; change Matter status; assign or remove lawyers; override an Ethical Wall; broaden permissions or Client Portal audiences; approve or publish Knowledge; remove confidentiality, privilege, retention, or legal-hold restrictions; finalize, sign, file, send, publish, or delete a legal document; send a substantive client communication outside an already-approved, narrow Communications policy; issue or void an invoice; alter rates or tax treatment; post journals; allocate payments; write off balances; reconcile accounts; move, release, or disburse client money; approve a refund or trust-to-operating transfer; change identity, permissions, MFA, delegation, or privileged access; expose restricted data; or perform a destructive operation. Financial calculations remain deterministic outside the model (Article 25), and client-money movement remains categorically non-delegable to AI.

## Article 41 — Actor provenance, Firm isolation, and revalidation for orchestrated and AI-assisted work

Every workflow and Copilot operation preserves the initiating principal, the effective actor, Firm context, delegation or service-principal provenance, correlation and causation identifiers, the workflow definition and version, approval provenance, automation-policy version, and relevant authorization-decision provenance, extending Articles 26 and 35's actor-provenance discipline to orchestrated and AI-assisted work specifically. A client-supplied Firm or Actor identifier is never trusted for a workflow, trigger, or Copilot operation (Article 27). Long-running and queued workflow or Copilot work never treats the authorization captured when it started as an indefinite grant: current membership, role, revocation, and Ethical Wall status are revalidated before every protected retrieval or consequential action, including on resumption from a wait or approval state, extending Article 28 to orchestrated work that may span an arbitrarily long duration. Practice Management alone remains the Ethical Wall authority (Article 17) for every workflow step, approval screen, and Copilot answer; the most restrictive applicable decision governs, and a denied caller receives no protected content, metadata, count, workflow title, approval detail, step output, search result, citation, or existence confirmation.

## Article 42 — AI context construction, retrieval discipline, and prompt-injection safety

Firm and permission filtering occur before retrieval and before any material enters Copilot model context — never after, and never as a post-generation repair, extending Article 21's retrieval discipline to cross-domain Copilot context construction. Every AI output carries provenance — model and version, provider or processor reference, timestamp, prompt-template version, policy version, source identifiers and versions, access-decision provenance, workflow/run correlation, confidence or uncertainty where meaningful, citations, and required disclaimers. Documents, web content, email, messages, uploaded files, retrieved knowledge, and external payloads are treated as untrusted data, never as trusted instructions; the Copilot operates under tool allowlists, least-privilege tool access, schema-validated tool inputs and outputs, and never chooses its own expanded permissions or tool inventory. There is no cross-Firm Copilot context, memory, embedding, cache, evaluation, or training use, without exception and regardless of anonymization claims, extending the cross-Firm prohibition Articles 5, 20, and 21 already establish.

## Article 43 — Idempotency, cancellation, and compensation for orchestrated work

Workflow retry and deduplication prevent a duplicate consequential effect within a defined boundary; they never constitute a claim that execution or delivery is exactly-once (extending Article 34's delivery discipline to internal orchestration). Cancelling a workflow stops future work; it never erases or rewrites a business fact an owning domain has already committed. Undoing a completed action's effect requires an explicit, separately authorized compensating command to the owning domain, never a rollback achieved by editing workflow state, extending Article 36's failure-handling principle to compensation specifically. External calls made by a workflow step never occur inside a database transaction.

## Article 44 — Deferred scope of Reporting and Marketplace distribution for Workflow and AI Copilot

`Workflow` and the AI Copilot may expose operational projections of run status, wait time, failure rate, and approval workload; they do not define or absorb a future Reporting bounded context's domain analytics or financial reporting. Marketplace publication, monetization, partner certification, and cross-Firm workflow-template distribution remain out of scope of this Constitution's Workflow and AI Copilot articles and require their own separately approved architecture, consistent with Article 37's deferred-scope principle, before any such capability exists. A workflow definition is Firm-owned and never becomes cross-Firm merely because its structure appears reusable.

## Amendment

Amendments to this Constitution require an approved ADR and explicit human sign-off, consistent with the approval gates in `AGENTS.md`. This requirement applies in full to Articles 22–25: no financial, client-money, or Financial AI rule may be weakened without an approved ADR and recorded human approval. It applies equally to Articles 26–30: no identity, authentication, authorization, Ethical Wall, privileged-access, or security-audit rule may be weakened without an approved ADR and recorded human approval. It applies equally to Articles 31–37: no external-contract, API-authorization, domain-ownership, delivery, actor-provenance, failure-handling, or scope-boundary rule governing the API and Integration Platform may be weakened without an approved ADR and recorded human approval. It applies equally to Articles 38–44: no Workflow bounded-context, AI Copilot governance, human-approval, automation-policy, non-delegable-action, actor-provenance, Firm-isolation, Ethical Wall, retrieval-safety, prompt-injection, idempotency, compensation, or scope-boundary rule governing Workflow or the AI Copilot may be weakened without an approved ADR and recorded human approval.
