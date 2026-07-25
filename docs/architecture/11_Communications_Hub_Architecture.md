# ARCH-011 — Communications Hub Architecture

**Status:** Approved (conceptual architecture) — implementation stories are proposed, not scheduled; see `docs/architecture/08_Roadmap.md`.

## Purpose and scope

This document defines the conceptual domain and system architecture for OneLegalPro's Communications Hub, implementing `docs/adr/ADR-004-Communications-Hub.md` and the relevant articles of `docs/architecture/01_OneLegalPro_Constitution.md`. It covers every inbound and outbound communication a Firm has with a Lead, Client, or prospective client — across website chat, client portal chat, email, messaging platforms, and SMS — unified into one timeline and connected to the Firm's business objects (Leads, Clients, Matters, Tasks, Appointments, Invoices, Knowledge items).

This document describes **conceptual models only**. It does not define migrations, Eloquent schemas, or implementation code — those belong to future, separately approved implementation stories (see `docs/architecture/08_Roadmap.md`).

## 1. Purpose

Communications are a first-class business domain, not a messaging feature bolted onto other modules, because a single inbound message can become almost any business object OneLegalPro already models:

- A website chat enquiry becomes a **Lead**.
- A returning enquiry from a known email address or phone number becomes tied to an existing **Client**.
- A message referencing an ongoing dispute becomes evidence or context on a **Matter**.
- "Can we move Thursday's call" becomes a change to an **Appointment**.
- "Please send the signed NDA" becomes a **Task**.
- "When is this due" against an outstanding balance becomes context on an **Invoice**.
- A recurring question answered well becomes a candidate **Knowledge item**.

If communications were owned by whichever module happened to receive them first (Leads owning website chat, Matters owning matter email, Billing owning invoice replies), the same client's history would fragment across modules, human handoff would restart context, and no single timeline could answer "what has this client told us, anywhere, ever." Communications must therefore be modeled as its own bounded context that every other module *references*, never one that is owned piecemeal by the modules it eventually informs.

## 2. Supported Channels

The architecture supports channel adapters for:

**Current**

| Channel | Category |
|---|---|
| Website AI Chat | Conversational |
| Client Portal Chat | Conversational |
| Email — Google Workspace | Email |
| Email — Microsoft 365 | Email |
| Email — IMAP (generic) | Email |
| WhatsApp Business | Messaging |
| LINE Official Account | Messaging |
| Telegram | Messaging |
| SMS | Messaging |

**Future**

| Channel | Category |
|---|---|
| Slack | Messaging |
| Microsoft Teams | Messaging |
| Voice | Voice |
| Video | Video |

Every channel is reached through a **Channel Adapter** (see API Architecture, below). Provider-specific behavior — authentication method, message format, attachment handling, template mechanics, delivery/read-receipt semantics — is isolated entirely behind the adapter. The Domain and Application layers work only against the published `ChannelAdapter` contract and the channel-neutral `Message`/`CommunicationThread` model; adding a new provider (or a future channel such as Slack or Voice) requires a new adapter implementation, never a change to domain logic.

## 3. Unified Communication Timeline

One counterparty (a Lead or a Client), many channels, one timeline:

```text
Website
  ↓
Email
  ↓
WhatsApp
  ↓
Phone
  ↓
Portal
  ↓
Matter Timeline
```

Every inbound and outbound `Message`, regardless of channel, is appended to a single `CommunicationThread` for that counterparty within a Firm. A channel switch is modeled as simply the next `Message` on the same thread carrying a different `Channel` value — not a new aggregate, not a new conversation — so conversation continuity is a structural property of the model, not a reconciliation step performed after the fact.

**Thread identity resolution.** Not every message arrives with a known counterparty. An anonymous website-chat visitor has no Lead or Client ID yet; a first-time WhatsApp message has only a phone number. Threads therefore support progressive identity resolution:

1. A thread starts against whatever identity signal is available (session ID, phone number, email address, LINE/Telegram user ID).
2. As stronger signals arrive (an email address given in chat, a phone number confirmed by SMS), the thread is matched or merged against an existing counterparty.
3. Once a Lead or Client record is confirmed, the thread is linked to it (see Matter Integration, below); low-confidence matches require human confirmation rather than silent auto-merging, to avoid conflating two different people's history.

## 4. Website AI Receptionist

The Website AI Chat channel is staffed by an AI receptionist with the following capabilities:

- Greeting and general service information.
- Collecting intake information (name, contact details, matter description).
- Detecting practice area from the visitor's description.
- Detecting urgency (for example, statutory deadlines, arrest, asset freeze language) and prioritizing escalation accordingly.
- Booking a consultation (creating or proposing an Appointment).
- Creating a Lead from the conversation.
- Escalating to a human when requested, when urgency is detected, or when the AI's confidence in continuing safely is low.
- Multilingual support, consistent with the Firm's configured languages.

The AI receptionist must never imply legal advice. It gathers facts and routes; it does not answer "am I liable" or "what should I do" as if it were counsel. See AI Rules, below, and `docs/architecture/01_OneLegalPro_Constitution.md`, Article 6.

## 5. Human Handoff

A conversation started by the AI receptionist (or any AI-assisted channel) can be handed to a lawyer or staff member without restarting the conversation. The receiving human sees the full prior thread — messages, AI annotations, and any collected intake data — because handoff is a change of *who is responding next* on the same `CommunicationThread`, not a transfer to a different system or record. Handoff is itself an auditable event (`ConversationEscalatedToHuman`) carrying the reason (explicit request, detected urgency, low AI confidence, or configured policy).

## 6. Email Intelligence

Email is supported through three adapters — Google Workspace, Microsoft 365, and generic IMAP — behind the same `ChannelAdapter` contract as every other channel, so downstream capabilities are provider-agnostic:

- AI summaries of long or multi-participant threads.
- Suggested replies, presented as drafts, never sent automatically.
- Matter detection (does this email relate to an existing Matter).
- Deadline detection (statutory or contractual dates mentioned in the body or attachments).
- Attachment extraction (making attached documents available to the Documents domain, subject to that module's own rules).
- Client matching (resolving the sender/recipients against existing Client records).
- Task suggestions (for example, "file a response by [date]").

**Lawyer approval is required before any outbound action** — sending a suggested reply, creating a task from a suggestion, or linking an email to a Matter at low confidence all require explicit human confirmation. Email Intelligence produces suggestions and annotations; it does not act unilaterally.

## 7. Messaging Integrations

WhatsApp Business, LINE Official Account, and Telegram are supported as first-class messaging channels, each behind its own adapter, with:

- Inbound and outbound messaging.
- Attachments and images.
- Message templates, where the provider requires pre-approved templates for business-initiated conversations (notably WhatsApp Business).
- Read status, where the provider exposes it.
- Voice notes — flagged as a future capability, not initial scope.

**Provider capability differences must be documented, not assumed uniform.** Representative differences the architecture must accommodate rather than paper over:

| Capability | WhatsApp Business | LINE OA | Telegram | SMS |
|---|---|---|---|---|
| Rich attachments | Yes | Yes | Yes | No (link-out only) |
| Pre-approved templates required for business-initiated messages | Yes | Yes (per LINE policy) | No | Carrier-dependent |
| Read receipts | Yes (per user privacy setting) | Yes | Yes (per chat type) | No |
| Voice notes | Future | Future | Future | No |

A `ChannelCapabilities` value object (see Communication Aggregate, below) makes these differences explicit data the Application layer can query, rather than logic scattered across call sites.

## 8. AI Assistance

Across every channel, AI assistance is available for:

- Summaries of a thread or message.
- Translation (for multilingual counterparties).
- Classification (practice area, urgency, sentiment).
- Entity extraction (names, dates, amounts, references).
- Draft replies.
- Suggested tags.
- Suggested lawyer (based on practice area, workload, and prior relationship).
- Reminder suggestions (for example, "this enquiry has been unanswered for 48 hours").

**AI never sends a communication without configured authorization.** The default posture is draft-and-suggest; auto-send is an explicit, Firm-configured exception (for example, a fully scripted, disclosed AI-only acknowledgment message), never the implicit default for substantive replies. See AI Rules, below.

## 9. Communication Aggregate

The **Communication Aggregate** is `CommunicationThread` — the aggregate root representing one counterparty's unified, cross-channel conversation with a Firm.

**Entities**

- `CommunicationThread` (aggregate root) — one per counterparty per Firm; owns an ordered sequence of `Message` entities and zero or more `CommunicationLink` entities.
- `Message` — a single inbound or outbound communication unit within a thread; immutable once recorded (see Audit).
- `CommunicationLink` — a link from the thread (or a specific message) to a business object (Lead, Client, Matter, Task, Appointment, Invoice, Knowledge item), carrying a confidence level and who/what established it.

**Value objects**

- `Channel` — the channel a message arrived or was sent on (Website AI Chat, Client Portal Chat, Email, WhatsApp, LINE, Telegram, SMS, and future channels).
- `Direction` — `Inbound` or `Outbound`.
- `MessageContent` — text body and/or attachment references; never mutated after recording, only superseded by a new `Message` (for example, a correction is a new message, not an edit).
- `ProviderMessageReference` — the originating provider's message/thread identifiers, used for deduplication and idempotency.
- `DeliveryStatus` — `Queued` / `Sent` / `Delivered` / `Read` / `Failed`, populated only to the extent the channel supports it.
- `ChannelCapabilities` — the capability profile (attachments, templates, read receipts, voice notes) of the channel a message travelled on, resolved from the adapter (see Messaging Integrations, above).
- `AIAnnotation` — any AI-produced summary, classification, suggested reply, suggested tag, or suggested lawyer attached to a message or thread, carrying model/version, timestamp, and a confidence score (provenance — see AI Rules).
- `MatchConfidence` — the confidence score behind a `CommunicationLink`, with a threshold below which the link is provisional and requires human confirmation.
- `ConsentRecord`, `RetentionPolicy` — privacy-governing value objects attached to a thread (see Privacy).

**Events** (past tense, per `docs/domain/06_Laravel_Module_Blueprint.md` naming convention)

`MessageReceived`, `MessageSent`, `MessageDeliveryFailed`, `ConversationEscalatedToHuman`, `LeadCreatedFromConversation`, `MatterMatchSuggested`, `MatterMatchConfirmed`, `CommunicationLinked`, `AIReplyDrafted`, `AIReplyApproved`, `AIReplyRejected`, `LegalHoldApplied`, `LegalHoldReleased`, `RetentionPolicyApplied`.

**Lifecycle and state transitions**

- `CommunicationThread`: `Open` → `AwaitingHuman` (after escalation) → `Open` (after handoff) → `Linked` (once a confirmed `CommunicationLink` to a Matter/Client exists) → `Archived` (retention/closure). A thread under legal hold cannot transition to a deletion-bound state regardless of retention policy (see Security, Privacy).
- `Message`: `Queued` → `Sent` → `Delivered` → `Read` (where supported) → terminal, or `Queued` → `Failed` → retried (bounded) → `Failed` terminal or `Sent` (see Offline Handling). Once recorded, a `Message`'s content is immutable; correction is a new message referencing the prior one.
- `CommunicationLink`: `Suggested` (AI or system-proposed, `MatchConfidence` below threshold) → `Confirmed` (human-confirmed or high-confidence automatic) → `Rejected` (human dismisses a suggestion). Only `Confirmed` links are treated as authoritative for reporting, billing association, or Matter Timeline inclusion.

## 10. Matter Integration

Communications relate to Lead, Client, Matter, Task, Appointment, Billing, and Knowledge exclusively through `CommunicationLink` — a thread or message never becomes owned by another module's aggregate, and another module never reaches into `Communications`' Eloquent records directly (see Domain boundaries, Ownership boundary).

**Low-confidence matches require confirmation.** Any automatic or AI-suggested link (matching an email to a Matter by subject-line heuristics, matching a phone number to a Client) below the configured `MatchConfidence` threshold is recorded as `Suggested`, surfaced to a human (typically in the Communication Inbox, below), and only becomes authoritative once `Confirmed`. This prevents a wrong guess from silently attaching a stranger's message to the wrong Client's Matter Timeline — a correctness and confidentiality requirement, not just a UX nicety.

## 11. Security

- **Tenant isolation.** Every `CommunicationThread`, `Message`, and `CommunicationLink` is firm-scoped data isolated by `FirmContext`, enforced at the application, repository, and database-policy layers, never by global scopes alone, per `docs/domain/06_Laravel_Module_Blueprint.md`.
- **Permissions.** Read/write access to a thread follows the same authorization model as the business objects it is linked to; Ethical Walls (`docs/domain/06_Laravel_Module_Blueprint.md`) apply to Communications the same as to Matters, Documents, and Tasks — a staff member walled off a Matter is walled off its linked communications.
- **Audit.** See Audit, below.
- **Encryption.** Message content and attachments are encrypted at rest and in transit; provider credentials (OAuth tokens, API keys for Google Workspace, Microsoft 365, WhatsApp Business, LINE, Telegram, SMS providers) are stored as secrets, never in plain configuration.
- **Retention.** Retention is policy-driven per Firm and per jurisdiction, not a fixed platform default (see Privacy).
- **Legal hold.** A thread or message under legal hold is exempt from retention-driven deletion regardless of policy, mirroring the platform's existing "never edit historical migrations" / immutability discipline applied here to communications evidence.
- **Message visibility.** Internal-only annotations (AI drafts, internal notes on a thread) are visibly and structurally distinct from counterparty-visible messages, so an internal note can never be accidentally sent — the same "never blend distinct categories into one undifferentiated stream" discipline `docs/architecture/01_OneLegalPro_Constitution.md`, Article 7, applies to Legal Intelligence content.

## 12. Privacy

- **PDPA** (Thailand's Personal Data Protection Act) and **GDPR**, where applicable to a Firm's clients, govern the lawful basis, consent, and data-subject-rights handling for communications data, consistent with the platform's Thailand-first posture (`docs/architecture/01_OneLegalPro_Constitution.md`, Article 1).
- **Consent** — where a channel or jurisdiction requires opt-in for business messaging (for example, WhatsApp Business template messaging, SMS marketing), consent state is tracked as a `ConsentRecord` on the counterparty, checked before outbound messaging on that channel.
- **Retention** — `RetentionPolicy` is configurable per Firm, defaulting to the minimum period defensible for legal-services record-keeping, and is what drives thread/message archival — never immediate, irreversible deletion as the default behavior.
- **Deletion** — a data-subject deletion request is honored subject to legal hold and any statutory retention obligation that overrides it; deletion is itself an auditable event, not a silent purge.
- **Export** — a counterparty's communication history is exportable to support data-subject access requests, in a structured, channel-neutral format (the unified timeline model already gives this for free).
- **Regional compliance** — because `Jurisdiction` is already a first-class concept in the platform (`docs/architecture/09_Legal_Intelligence_Architecture.md`), regional privacy variation (PDPA vs. GDPR vs. a future jurisdiction's regime) is modeled as jurisdiction-scoped policy configuration on top of the same `CommunicationThread`/`ConsentRecord`/`RetentionPolicy` model, not a redesign per region.

## 13. Audit

- Every `Message` is immutable once recorded and every state transition (delivery status, escalation, linking, AI annotation) is a discrete, append-only domain event — the communications record is a ledger, not a mutable row.
- **AI involvement is recorded**, not inferred: every AI-produced summary, classification, draft, or suggestion carries its `AIAnnotation` provenance (model/version, timestamp, confidence) so it is always attributable and distinguishable from human-authored content.
- **Edits are tracked**: because `Message` content is immutable, an apparent "edit" is always a new `Message` referencing the one it supersedes — there is no silent in-place content mutation to audit around.
- Audit events carry Firm ID and Actor ID (human or AI-system actor, distinctly identified) like any other firm-scoped audit record, per `docs/domain/06_Laravel_Module_Blueprint.md`.

## 14. Offline Handling

- **Queue** — outbound messages are queued (not sent synchronously inline), reusing the platform's existing transactional outbox and event infrastructure (`docs/implementation/01_Implementation_Sprint_Plan.md`, Sprint 0.4: PF-090 through PF-093), so a message is durably recorded before an external provider call is attempted.
- **Retry** — delivery failures are retried with bounded attempts and backoff; consumers (adapter dispatch) must be idempotent and support retries, per the platform's existing event/consumer discipline.
- **Failure notifications** — exhausted retries surface a `MessageDeliveryFailed` event, visible in the Communication Inbox, rather than failing silently.
- **Provider outages** — an adapter reporting sustained failure is treated as a provider-outage state, not repeated indefinitely as ordinary per-message failure; outbound messages queue rather than drop during an outage.
- **Idempotency** — inbound webhook redelivery and outbound retry are deduplicated using `ProviderMessageReference`, so a provider's at-least-once delivery guarantee never produces a duplicate `Message` in the thread.

## 15. Analytics

Analytics are derived, read-model projections over Communication events — never a reason to add reporting-specific fields or logic to the `CommunicationThread` aggregate itself:

- Response time (first-response and resolution time, per channel and per thread).
- AI resolution rate (conversations the AI receptionist/assistant resolved without human escalation).
- Lead conversion (threads that produced a `LeadCreatedFromConversation` and their downstream outcome).
- Channel usage (volume and trend per channel).
- Missed enquiries (threads awaiting a response beyond a configured threshold).
- Lawyer workload (open/assigned threads per lawyer, informing Suggested lawyer in AI Assistance).

## 16. API Architecture

- **Provider adapters** — each channel is implemented as a `ChannelAdapter` in the `Communications` module's Infrastructure layer, translating provider-specific APIs (Google Workspace, Microsoft 365, IMAP, WhatsApp Business, LINE, Telegram, SMS gateways, and future Slack/Teams/Voice/Video providers) into the channel-neutral `Message`/`ChannelCapabilities` model.
- **Published contracts** — Application and Domain code depend only on the `ChannelAdapter` contract and the `CommunicationThread` aggregate; other modules (Leads, Clients, Matters, Tasks, Appointments, Billing, Knowledge) reach Communications data only through published queries/commands, never through `Communications`' Eloquent records, per `docs/domain/06_Laravel_Module_Blueprint.md`.
- **No provider-specific logic in the business layer** — practice-area detection, urgency detection, matter matching, and every other business rule operate on the channel-neutral model; they must not branch on "if WhatsApp" or "if this is a Microsoft 365 email." Only the adapter itself knows it is talking to a specific provider.

## 17. Future Expansion

- Voice AI (transcription and AI assistance over the Voice channel).
- Meeting transcription (turning a recorded consultation into thread-attached, searchable content).
- Court hearing assistant (real-time or post-hearing assistance, scoped separately from client communications).
- Real-time translation (beyond the async translation already in AI Assistance).
- Video consultation (the Video channel, with the same `CommunicationThread` linkage as any other channel).
- Slack and Microsoft Teams channel adapters (already named as future channels in Supported Channels).

Every item above is designed to be a new `ChannelAdapter` and, where relevant, new `AIAnnotation` types — additive to the existing `CommunicationThread`/`Message` model, not a redesign of it, mirroring the extension discipline `docs/architecture/09_Legal_Intelligence_Architecture.md` applies to future jurisdictions and `docs/architecture/10_White_Label_Platform_Architecture.md` applies to future locales.

## 18. Failure Modes

Conceptual failure modes the architecture must account for (mitigations are implementation-story-level detail):

- **Provider unavailable** — outbound messages queue and retry rather than fail immediately; inbound webhooks from a recovering provider must be processed without assuming in-order delivery.
- **Duplicate messages** — mitigated by `ProviderMessageReference`-based idempotency on both inbound webhook processing and outbound retry (see Offline Handling); a duplicate delivery must never create a second `Message`.
- **Incorrect matter match** — mitigated by `MatchConfidence` thresholds and mandatory human confirmation for low-confidence `CommunicationLink`s (see Matter Integration); an incorrect *confirmed* link is corrected by rejecting and re-linking, never by silently overwriting history.
- **AI uncertainty** — low-confidence AI output (classification, suggested reply, suggested match) must be surfaced as uncertain, not silently upgraded to a confident-looking suggestion, consistent with the confidence-handling principle in `docs/architecture/05_AI_Architecture.md`.
- **Retry failures** — exhausted retries produce a visible failure state and notification (see Offline Handling), never a silently dropped message.
- **Partial outages** — a single channel adapter failing (for example, WhatsApp API degraded) must not affect other channels' threads or the Communication Inbox's availability for unaffected channels.
- **Cross-tenant leakage** — mitigated by the same published-contract-only, `FirmContext`-enforced isolation rule applied throughout the platform; any direct cross-Firm access to a thread is a defect, not a variant.
- **AI-authored message sent without authorization** — must be structurally impossible; outbound send requires either explicit human approval or a Firm-configured, scoped auto-send exception (see AI Assistance, AI Rules).

## Communication Inbox

The **Communication Inbox** is the lawyer's (and firm staff's) primary daily workspace — a single, permission-aware, Firm-scoped view assembled from `CommunicationThread`, `CommunicationLink`, and `AIAnnotation` data, grouped for triage:

```text
Today's Inbox
  • Website enquiries
  • Email
  • WhatsApp
  • LINE
  • Telegram
  • AI Drafts
  • Urgent Matters
  • Follow-ups
```

**Why this replaces multiple disconnected communication tools.** Without a Communications Hub, a Firm's staff would otherwise operate a separate webmail client, a separate WhatsApp Business app, a separate LINE Official Account console, a separate Telegram client, and a separate lead-intake inbox — each with its own notification stream, its own read/unread state, and no shared view of a given client's full history. That fragmentation is precisely what causes missed enquiries, duplicated follow-ups, and context loss on handoff. The Inbox is a read-model projection over the same `CommunicationThread` aggregate that already unifies the timeline (see Unified Communication Timeline); it does not require a second source of truth, only a triage-oriented view over the one that exists — grouped by channel for familiarity, but also by urgency (Urgent Matters), AI involvement (AI Drafts awaiting approval), and staleness (Follow-ups), which no single provider's native inbox can offer across channels. Access to any given entry respects the same tenant isolation, permissions, and Ethical Walls as the underlying thread (see Security).

## AI Rules

Every AI capability described in this document — the Website AI Receptionist, Email Intelligence, and general AI Assistance — is bound by the following rules, extending `docs/architecture/01_OneLegalPro_Constitution.md`, Article 6, and `docs/architecture/05_AI_Architecture.md` to Communications specifically:

1. **Identify itself.** The AI must be clearly identified as AI, not presented as, or confusable with, a human staff member.
2. **No attorney-client relationship merely through interaction.** Engaging with the AI receptionist or assistant does not, by itself, create an attorney-client relationship; this must be structurally clear in how the AI presents itself and in the AI-authored content it produces.
3. **Avoid legal advice.** The AI gathers information, classifies, drafts, and routes; it does not tell a visitor or client what their legal position is or what they should do about it.
4. **Recommend lawyer consultation.** Where a question approaches legal-advice territory, the AI's response is to recommend consultation with a lawyer, not to attempt an answer.
5. **Record provenance.** Every AI-produced summary, classification, draft, or suggestion carries `AIAnnotation` provenance (model/version, timestamp, confidence) — the same provenance discipline `docs/architecture/05_AI_Architecture.md` requires of Legal Intelligence retrieval, applied here to communications AI output.
6. **Support human review.** AI drafts and suggestions are presented for human review and, for any outbound action, explicit approval (see AI Assistance, Email Intelligence) — the AI's output is a proposal a human acts on, not a final action, except within a narrowly scoped, Firm-configured auto-send exception.

## Domain boundaries

Communications is a distinct domain from Firm/Matter management, Documents, Billing, and Legal Intelligence. It must never mix:

1. **Raw communication content** — the `Message` record of what was actually said, on which channel, by whom.
2. **AI-generated annotations** — summaries, classifications, suggested replies, suggested links — attached to, but structurally distinct from, the raw content they annotate (mirroring the Constitution's separation of AI-generated material from source content, Article 7).
3. **Business-object links** — `CommunicationLink` connections to Lead/Client/Matter/Task/Appointment/Invoice/Knowledge, owned by Communications as the linking record, never duplicated into the linked module's own schema.
4. **Provider/channel mechanics** — authentication, delivery mechanics, and provider-specific payloads, confined entirely to the Infrastructure-layer adapters (see API Architecture).

Each is a distinct concept in the domain model, even where they share infrastructure (message queue, storage, search index).

## Ownership boundary

Communications data has no platform-global subdomain — every `CommunicationThread`, `Message`, and `CommunicationLink` belongs to exactly one Firm, the same wholly firm-scoped shape `docs/architecture/10_White_Label_Platform_Architecture.md` establishes for Branding, and distinct from `docs/architecture/09_Legal_Intelligence_Architecture.md`'s platform-global/firm-scoped split:

| Subdomain | Scope | Ownership boundary |
|---|---|---|
| Channel adapter contracts and capability schema (`ChannelCapabilities` shape) | Platform-global | Not `FirmContext` — static configuration shared by every Firm |
| `CommunicationThread`, `Message`, `CommunicationLink` | Firm-scoped | `FirmContext` |
| Provider credentials (per-Firm OAuth tokens, API keys) | Firm-scoped | `FirmContext`, stored as secrets |
| AI annotations (`AIAnnotation`) attached to firm-scoped threads | Firm-scoped | `FirmContext` |

A rendering surface (the Communication Inbox, a Matter Timeline view) may read the platform-global adapter/capability schema, but every actual communication it displays for a given Firm is that Firm's own data, reached only through Communications' published queries, never through another module's Eloquent records directly.

## Proposed module structure

**Unresolved implementation choice:** exact module name. This document proposes `Communications`, consistent with the module-per-bounded-context pattern already established for `LegalIntelligence` and `Branding`.

```text
Communications/
├── Application/
│   ├── Threads/          (StartThread, AppendMessage, EscalateToHuman, ArchiveThread, ...)
│   ├── Links/            (SuggestCommunicationLink, ConfirmCommunicationLink, RejectCommunicationLink, ...)
│   ├── AI/               (RequestSummary, RequestDraftReply, RequestClassification,
│   │                       ApproveAIReply, RejectAIReply, ...)
│   └── Privacy/          (RecordConsent, ApplyRetentionPolicy, ApplyLegalHold, ReleaseLegalHold,
│                           ExportCommunicationHistory, ...)
├── Domain/
│   ├── CommunicationThread   (aggregate root)
│   ├── Message, CommunicationLink   (entities)
│   ├── Channel, Direction, MessageContent, ProviderMessageReference,
│   │   DeliveryStatus, ChannelCapabilities, AIAnnotation, MatchConfidence,
│   │   ConsentRecord, RetentionPolicy   (value objects)
├── Infrastructure/        (channel adapters: WebsiteChat, ClientPortalChat,
│                            GoogleWorkspaceEmail, Microsoft365Email, IMAPEmail,
│                            WhatsAppBusiness, LineOfficialAccount, Telegram, SMS;
│                            Eloquent adapters, outbox/queue integration)
├── Interface/              (Communication Inbox read model, timeline API, webhook endpoints)
├── Database/               (new migrations only — no historical migrations touched)
├── Routes/
├── Tests/
├── Config/                 (channel capability schema, match-confidence thresholds,
│                            default retention policy, provider credentials references)
├── ModuleServiceProvider.php
└── README.md
```

Dependency direction and cross-module rules follow `docs/domain/06_Laravel_Module_Blueprint.md` unchanged: Interface → Application → Domain; Infrastructure depends on Application/Domain contracts; Domain never depends on Laravel/Eloquent/HTTP/provider SDKs; every other module reaches Communications data only through published queries/commands, never through `Communications`' Eloquent records directly.

## Channel Adapter contract

The `ChannelAdapter` contract is the single published interface each provider integration implements: send a `Message`, receive/normalize an inbound `Message`, and expose that provider's `ChannelCapabilities`. It is the only sanctioned integration point for a provider SDK anywhere in the module — no Application or Domain code may call a provider SDK directly, and no provider-specific conditional logic may appear outside the adapter that owns that provider.

## Storage strategy

**Unresolved implementation choice:** exact storage backend split, to be finalized in a future implementation-focused ADR once message volume and search requirements are known. Conceptually:

- Structured thread/message/link metadata (channel, direction, timestamps, delivery status, link confidence) belongs in PostgreSQL, consistent with the platform's existing database rule and UUIDv7 identity.
- Large attachment binaries are better suited to object storage referenced by the `Message` record, keeping the relational schema focused on queryable metadata rather than blobs — the same pattern `docs/architecture/09_Legal_Intelligence_Architecture.md` and `docs/architecture/10_White_Label_Platform_Architecture.md` apply to large captured text and binary assets respectively.
- Search/indexing infrastructure (for the Communication Inbox and cross-thread search) is a separate concern layered on top of the canonical store, not the source of truth itself.

## Access-control boundaries

- Thread and message read/write access follows the authorization and Ethical Wall rules of whatever business object the thread is linked to; an unlinked thread (not yet confirmed to a Lead/Client) is visible only to staff with general intake access.
- Firm-scoped isolation is enforced at the application, repository, and database-policy layers, never by global scopes alone, per `docs/domain/06_Laravel_Module_Blueprint.md`.
- Any write path that could send an AI-authored message without the required approval, or that could bypass legal hold, requires the same elevated approval discipline as other high-impact changes in `AGENTS.md`.

## Phased implementation guidance

See `docs/architecture/08_Roadmap.md`, the proposed Communications Hub epic, for staged delivery order (channel-neutral thread/message foundation and adapter contract → email adapters → messaging-platform adapters → website/portal chat and AI receptionist → human handoff → matter/business-object linking → AI assistance and provenance → Communication Inbox → privacy/retention/legal hold → analytics). That staging is proposed only; formal scheduling requires entries in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` and separate story-level approval before implementation begins.
