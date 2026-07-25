# ADR-004 — Communications Hub

## Status

Accepted. The human owner has explicitly approved this decision.

## Context

OneLegalPro's Firms — Thai law firms, foreign-owned firms operating in Thailand, and foreign lawyers working with Thai law — reach and are reached by Leads and Clients across many channels: their website, the client portal, email (Google Workspace, Microsoft 365, or generic IMAP), WhatsApp Business, LINE Official Account, Telegram, and SMS, with Slack, Microsoft Teams, Voice, and Video anticipated as future channels. Prior to this ADR, no communications, messaging, or omnichannel model existed anywhere in the approved architecture. Without one, the natural implementation path is for each module that happens to touch communication — Leads capturing website chat, Matters recording matter-related email, Billing handling invoice queries — to build its own ad hoc messaging logic, provider integration, and history, exactly the fragmentation `docs/architecture/09_Legal_Intelligence_Architecture.md` and `docs/architecture/10_White_Label_Platform_Architecture.md` already rejected in their own domains (a single undifferentiated content stream; per-surface branding reinvented independently).

A single inbound message can become a Lead, attach to an existing Client or Matter, trigger a Task, reschedule an Appointment, relate to an Invoice, or seed a Knowledge item — but it is none of those things natively; it is a communication that *may relate to* any of them. Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, this needs an approved architecture before Digital Presence, Client Portal, or any messaging-integration implementation work begins.

## Decision

1. **Communications is its own bounded context**, implemented as a proposed `Communications` module, owning the `CommunicationThread` aggregate (and its `Message`/`CommunicationLink` entities) independently of Leads, Clients, Matters, Tasks, Appointments, Billing, and Knowledge. Those modules *reference* communications through published links; they do not own or duplicate communication history into their own schemas.
2. **One counterparty, one omnichannel timeline is preferred over per-channel conversation records.** A channel switch (website → email → WhatsApp → phone → portal) is modeled as the next message on the same thread, not a new aggregate or a reconciliation step performed after the fact. This is a deliberate rejection of the alternative — separate per-channel conversation objects stitched together by a downstream view — because stitching is lossy and delays context at the exact moment (human handoff, matter linking) it matters most.
3. **Every channel and future channel is reached through a published `ChannelAdapter` contract.** Google Workspace, Microsoft 365, IMAP, WhatsApp Business, LINE Official Account, Telegram, SMS, and future Slack, Microsoft Teams, Voice, and Video integrations are Infrastructure-layer adapters implementing one channel-neutral interface. Provider-specific behavior and capability differences (templates, read receipts, attachment support) are isolated behind the adapter and exposed as data (`ChannelCapabilities`), never as conditional logic in the Application or Domain layers.
4. **AI remains assistive, never autonomous, across every AI-touched surface** — the Website AI Receptionist, Email Intelligence, and general AI Assistance. AI drafts, summarizes, classifies, suggests links, and can escalate to a human; it does not send a substantive communication, confirm a low-confidence Matter link, or imply legal advice without configured human authorization, extending `docs/architecture/01_OneLegalPro_Constitution.md`, Article 6, and `docs/architecture/05_AI_Architecture.md` to communications specifically.
5. **Business-object relationships are explicit, confidence-scored links (`CommunicationLink`), never silent auto-association.** A communication may relate to a Lead, Client, Matter, Task, Appointment, Invoice, or Knowledge item; low-confidence matches are suggested, not authoritative, until a human confirms them.
6. **Communications data is wholly firm-scoped**, isolated by `FirmContext` at the application, repository, and database-policy layers, with no platform-global content subdomain — only the channel adapter contract and capability schema are platform-global, static configuration.
7. **The Communication Inbox is the lawyer's primary daily workspace**, a permission- and Ethical-Wall-aware read-model projection over the same thread/link/annotation data, replacing the fragmentation of separately checking webmail, WhatsApp Business, LINE OA, Telegram, and a disconnected lead-intake tool.

Full conceptual detail is recorded in `docs/architecture/11_Communications_Hub_Architecture.md`.

## Why Communications is its own bounded context

Communication content, AI annotations on that content, and links to business objects are three distinct concerns that must never be conflated — the same discipline the Constitution already applies to official legal sources vs. AI-generated explanations (Article 7), applied here to raw messages vs. AI summaries vs. business linkage. Letting each consuming module own its own slice of communication history would mean a Client's full history is only ever visible by querying every module that happens to have touched them, defeating the unified-timeline goal in Decision 2 and making Ethical Wall enforcement (`docs/domain/06_Laravel_Module_Blueprint.md`) inconsistent across fragments of the same conversation.

## Why the omnichannel timeline is preferred

Law firm client relationships are not channel-loyal: a prospective client may start on the website, continue by email, and finish by phone or WhatsApp. A per-channel model forces staff to reconstruct continuity manually at exactly the moments — human handoff, matter intake, urgent escalation — where losing context is most costly. A single `CommunicationThread` per counterparty makes continuity structural rather than procedural.

## Why provider adapters are required

Nine current channels and at least four anticipated future channels (Slack, Microsoft Teams, Voice, Video) is already too many provider integrations to hardcode into business logic without the same redesign risk `docs/architecture/09_Legal_Intelligence_Architecture.md` rejected for jurisdiction and `docs/architecture/10_White_Label_Platform_Architecture.md` rejected for arbitrary per-tenant styling. A published `ChannelAdapter` contract confines provider-specific mechanics and capability variance to Infrastructure, so a new provider — or a materially different one, like Voice — is a new adapter, not a redesign of `CommunicationThread`.

## Why AI remains assistive

Communications is the surface most likely to reach a prospective client before any lawyer has reviewed anything — the Website AI Receptionist may be the first contact a Firm's prospective client ever has. That makes the existing Constitutional AI-governance posture (advisory only, human review, never implying legal advice) more, not less, load-bearing here than in most other domains: an AI receptionist that oversteps into legal advice, or an AI assistant that sends an unapproved reply, creates business and professional-conduct risk at the most exposed point in the client relationship.

## Alternatives considered

- **Let each module (Leads, Matters, Billing) own its own communication logic and history.** Rejected — fragments a single client's history across modules, duplicates provider-integration effort, and makes consistent AI governance and Ethical Wall enforcement structurally difficult, the same failure mode Decision 1 addresses.
- **Model conversations as separate, per-channel records, unified only by a downstream reporting view.** Rejected — defeats "conversation continuity must be preserved" at the moment it matters (handoff, escalation), and would require a second source of truth (the unifying view) to reconstruct what a single-aggregate model gives natively.
- **Integrate each provider's SDK directly wherever a feature needs it, without an adapter abstraction.** Rejected — nine current and at least four future providers would otherwise leak provider-specific conditionals throughout the Application and Domain layers, directly violating the "no provider-specific logic in the business layer" requirement.
- **Allow the AI receptionist or AI assistant to send substantive replies autonomously by default, with human review as an opt-in safeguard.** Rejected — inverts the platform's existing AI-governance default (advisory, human-reviewed) specifically at the highest-exposure entry point in the client relationship; auto-send is a narrow, explicit, Firm-configured exception, never the default.
- **Treat Communications as platform-global reference data, mirroring Legal Intelligence's official-source subdomain.** Rejected — unlike legislation, which is genuinely the same for every Firm, every communication is a private exchange belonging to exactly one Firm and its counterparty; there is no shared, non-tenant-specific communications content to host platform-globally.

## Consequences

- A new domain concept, `CommunicationThread`, becomes the canonical home for any inbound or outbound exchange with a Lead or Client, even before a Matter or Client record formally exists.
- A new module, tentatively `Communications`, is wholly firm-scoped — the second module (after `Branding`) with no platform-global content subdomain, reinforcing that pattern as a recognized alternative to Legal Intelligence's dual-boundary model.
- The Digital Presence and Client Portal epics gain a dependency on Communications' website-chat and portal-chat adapters and the AI receptionist/handoff model before those epics can deliver a client-facing chat experience.
- Nine provider integrations (plus anticipated future ones) must each be built as a `ChannelAdapter`, which is more upfront integration surface than a single-channel MVP, but avoids the redesign cost of retrofitting an adapter boundary later.
- `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` need a proposed Communications Hub epic entry (see `docs/architecture/08_Roadmap.md`) before any implementation story can begin; this ADR approves the architecture, not an implementation schedule.
- `docs/architecture/02_Product_Requirements.md` and `docs/architecture/04_Security_Architecture.md` remain empty placeholders; this ADR does not populate them, consistent with the precedent set by `docs/adr/ADR-002-Thailand-First-Legal-Intelligence.md` and `docs/adr/ADR-003-White-Label-Platform.md`.

## Trade-offs

- A single omnichannel aggregate is more complex to design correctly (identity resolution across anonymous and known counterparties, cross-channel continuity) than independent per-channel records would have been, but the alternative pushes that same complexity onto every downstream consumer instead of solving it once.
- Requiring human confirmation for low-confidence Matter/Client links adds a manual step compared to always trusting automatic matching, in exchange for avoiding confidentiality and correctness risk from a wrong automatic match.
- Keeping AI strictly assistive (draft-and-suggest by default) is slower for the Firm than autonomous AI handling of routine enquiries would be, in exchange for keeping the platform's AI-governance posture intact at the most exposed point in the client relationship.
- A wholly firm-scoped ownership model means there is no shared, platform-level communications analytics baseline across Firms out of the box — each Firm's analytics (Analytics, in the architecture document) are computed independently from its own data.

## Future extensibility

The architecture is designed so that Slack, Microsoft Teams, Voice, and Video channels; voice AI and meeting transcription; a court hearing assistant; real-time translation; and video consultation can each be added as a new `ChannelAdapter` and, where relevant, new `AIAnnotation` types layered on the existing `CommunicationThread`/`Message` model, without redesigning the aggregate. See `docs/architecture/11_Communications_Hub_Architecture.md`, Future Expansion.
