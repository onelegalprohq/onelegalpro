# ARCH-012 — Website & Client Portal Architecture

**Status:** Approved (conceptual architecture) — implementation stories are proposed, not scheduled; see `docs/architecture/08_Roadmap.md`.

## Purpose and scope

This document defines the conceptual domain and system architecture for OneLegalPro's Digital Presence Platform, implementing `docs/adr/ADR-005-Website-Client-Portal.md` and the relevant articles of `docs/architecture/01_OneLegalPro_Constitution.md`. "Website & Client Portal" names the two flagship surfaces; the bounded context itself — proposed module `DigitalPresence` — is broader, and also covers Embedded Components, Booking, AI Receptionist integration, Intake, Client Authentication, and Knowledge Publishing, all as described below.

This document describes **conceptual models only**. It does not define migrations, Eloquent schemas, or implementation code — those belong to future, separately approved implementation stories (see `docs/architecture/08_Roadmap.md`).

## 1. Purpose

Digital Presence is its own bounded context because it is the composition layer where several already-architected capabilities — Branding (`docs/architecture/10_White_Label_Platform_Architecture.md`), Communications (`docs/architecture/11_Communications_Hub_Architecture.md`), and future Practice Management capabilities (Matters, Documents, Invoices, Tasks, Appointments) — become a single client-facing product surface, without any one of those capabilities owning the composition itself.

No existing or planned module is a natural owner of "the public website" or "the client portal": Branding owns identity, not page structure; Communications owns messages, not booking or content; a future Practice Management module owns Matters and Invoices, not how they're presented to a client. If Digital Presence were not its own bounded context, its responsibilities (content publishing, client authentication, booking, embeddable widgets) would either fragment across those modules — each reinventing a slice of "the website" — or accumulate as an unowned pile of presentation logic with no aggregate boundary of its own. A dedicated bounded context gives this composition a name, an owner, and a boundary, while every underlying capability it composes remains owned exactly where it already is.

## 2. Supported Deployment Models

Digital Presence supports three deployment models simultaneously, not as mutually exclusive product tiers but as configurations of the same underlying platform:

1. **Fully hosted website** — OneLegalPro hosts the Firm's entire public website (Website Builder, Content Management, custom domain via `docs/architecture/10_White_Label_Platform_Architecture.md`'s `TenantDomain`).
2. **Existing website integration** — the Firm keeps its own, independently hosted website and embeds OneLegalPro capabilities into it via Embedded Widgets, without migrating hosting.
3. **Enterprise CMS integration** — the Firm's existing enterprise CMS (or in-house web team) consumes OneLegalPro's Public APIs directly — pulling Knowledge Publishing content, posting booking requests, or linking to the Client Portal — without using Website Builder or a widget embed at all.

See Digital Presence Strategy, below, for the rationale and how a Firm chooses or transitions between models.

## 3. Website Builder

For Firms using the fully hosted deployment model, Website Builder manages the following page and content types, all firm-scoped and rendered through the Branding Resolver:

- Practice Areas
- Lawyer Profiles
- Office Locations
- News
- Articles
- FAQs
- Contact
- SEO (see SEO Strategy, below)
- Multilingual (per-page translations, distinct from Legal Intelligence's language/authority model — this is interface content translation, not legal-source translation)
- Accessibility (see Accessibility, below)

Every one of these page/content types is a `ContentItem` (see Communication Aggregate analogue in Content Management and Aggregates, entities, and value objects, below) — Website Builder is the authoring and rendering surface over `ContentItem`, not a separate content model.

## 4. Client Portal

The Client Portal is the authenticated, client-facing dashboard surfacing:

- Client dashboard (a summary view, not a data owner)
- Matters
- Documents
- Invoices
- Payments
- Appointments
- Tasks
- Messages
- Notifications

With the sole exception of authentication state and portal-specific display preferences, **the Client Portal owns none of this data**. Matters, Documents, Invoices, Payments, Tasks, and Appointments are surfaced through Practice Management Integration (below); Messages and Notifications are surfaced through Communications Integration (below). The Portal is a permission-aware aggregation view, the same "read-model projection, not a second source of truth" pattern the Communication Inbox already establishes in `docs/architecture/11_Communications_Hub_Architecture.md`.

## 5. Authentication

Client-facing authentication is distinct from staff/lawyer authentication (out of scope here) and supports, per Firm policy:

- Password
- Passwordless
- Magic Link
- MFA
- Future SSO
- Firm-controlled policies — a Firm configures which methods are permitted, whether MFA is required, and session timeout, via a `PortalAuthPolicy` value object on its `DigitalPresenceProfile` (see Aggregates, entities, and value objects). The platform does not impose one fixed authentication posture on every Firm.

A `ClientPortalIdentity` is the authentication identity for a Client interacting with the portal; it references the underlying Client business record (owned by a future Practice Management/CRM module) by identifier only, and never duplicates that record's data.

## 6. AI Receptionist Integration

Digital Presence does not implement its own AI. The Website AI Chat and Client Portal Chat surfaces are embed points for the Website AI Receptionist already architected in `docs/architecture/11_Communications_Hub_Architecture.md` §4, providing:

- Embedded AI assistant (rendered via the AI Chat Widget — see Embedded Widgets)
- Practice-area detection
- Consultation booking (handed to the Booking System, below)
- Lead creation (via Communications' `LeadCreatedFromConversation`)
- Human escalation (via Communications' handoff model)

The AI **never provides legal advice** — see AI Rules, below, which restates `docs/architecture/01_OneLegalPro_Constitution.md`, Article 12, in the Digital Presence context.

## 7. Booking System

The Booking System manages scheduling for consultations and other appointments:

- Calendars (per lawyer and/or per office)
- Availability (`AvailabilitySchedule` — recurring rules plus one-off exceptions)
- Time zones (every `TimeSlot` is timezone-aware; a Firm's office timezone and a client's browser timezone are both tracked to avoid ambiguity)
- Modality: Office / Video / Phone
- Reminder workflow (reuses Communications' outbound-message infrastructure to send reminders across the client's preferred channel, rather than Booking implementing its own notification pipeline)

**Unresolved implementation choice:** a confirmed `BookingRequest` produces or updates an `Appointment`, but the `Appointment` aggregate itself belongs to a future Practice Management architecture pass not yet written (`docs/architecture/02_Product_Requirements.md` remains an empty placeholder). This document defines `BookingRequest` and `AvailabilitySchedule` as Digital Presence's own scheduling-request concepts and treats the eventual `Appointment` aggregate as an external dependency reached only through a published command, consistent with the platform's "never reach into another module's Eloquent records" rule.

## 8. Embedded Widgets

Digital Presence exposes the following embeddable components:

- Booking Widget
- Client Login Widget
- Matter Status Widget
- AI Chat Widget
- Contact Widget
- Payment Widget
- Document Upload Widget

**Widgets must work on third-party websites** — this is a hard requirement, not an aspiration: every widget is designed to be embedded into a page OneLegalPro does not control (the "existing website integration" deployment model depends on it). See Embedded Component Framework, below, for how this is achieved without duplicating logic per widget.

## 9. Content Management

Content Management is the shared authoring pipeline behind Website Builder's Articles, FAQs, Practice Pages, and Knowledge Publishing content:

- Articles
- FAQs
- Practice Pages
- SEO Metadata
- Media Library
- Lifecycle: **Draft → Review → Publish**

Every content type is a `ContentItem` aggregate instance distinguished by `ContentType`. Unlike a published `LegalSource` (`docs/architecture/09_Legal_Intelligence_Architecture.md`), a published `ContentItem` is **not immutable** — it is Firm-authored marketing/educational material, not an official legal source, and may be edited in place with a revision history for audit and rollback. This is a deliberate, narrower rule than Legal Intelligence's strict immutability, and the distinction matters precisely because the two must never be confused (see Knowledge Publishing, below, and `docs/architecture/01_OneLegalPro_Constitution.md`, Article 7).

## 10. Branding

Digital Presence uses the Branding Engine defined in `docs/architecture/10_White_Label_Platform_Architecture.md` for every rendering surface — Website Builder pages, the Client Portal, and every Embedded Widget resolve identity through the Branding Resolver contract. **There is no duplicated branding logic in Digital Presence**: no separate theme system, no separate asset store, no separate domain/SSL handling. `TenantDomain` (Branding's custom-domain aggregate) is the same aggregate that binds a Firm's website and portal domains; Digital Presence does not define its own domain-verification or SSL model.

## 11. Communications Integration

Digital Presence uses the Communications Hub defined in `docs/architecture/11_Communications_Hub_Architecture.md` for every message-producing surface — the AI Chat Widget, Contact Widget, and Client Portal Messages all read and write through Communications' published contracts. **Messages appear in the unified `CommunicationThread` timeline** alongside email, WhatsApp, LINE, Telegram, and every other channel; Digital Presence does not maintain a separate message store for portal or widget conversations.

## 12. Practice Management Integration

The Client Portal surfaces Matters, Documents, Invoices, Tasks, and Appointments by reading through each owning module's published queries. Digital Presence does not own, cache authoritatively, or duplicate this data into its own schema — the same discipline `docs/architecture/09_Legal_Intelligence_Architecture.md` requires between platform-global and firm-scoped Legal Intelligence data, applied here between Digital Presence and the (largely not-yet-architected) Practice Management capabilities. Where the owning module for one of these business objects does not yet have an approved architecture (Matters, Documents, Invoices, Tasks), Digital Presence's dependency on it is an explicit, named unresolved dependency, not an assumption this document resolves.

## 13. Security

- **Tenant isolation** — every Digital Presence aggregate is firm-scoped, isolated by `FirmContext`, enforced at the application, repository, and database-policy layers, never by global scopes alone.
- **Permissions** — a Client's portal session is authorized against only their own linked Matters/Documents/Invoices/Tasks/Appointments; staff permissions and Ethical Walls (`docs/domain/06_Laravel_Module_Blueprint.md`) govern staff-side access to the same data through other surfaces.
- **Encryption** — client credentials, session tokens, and portal data are encrypted at rest and in transit, consistent with `docs/architecture/11_Communications_Hub_Architecture.md`'s security posture.
- **Audit** — authentication events, content publish actions, booking confirmations/cancellations, and widget-embed issuance/revocation are auditable domain events.
- **Session management** — portal sessions are bound to `PortalAuthPolicy`'s configured timeout and re-authentication rules; widget-issued sessions (see Embedded Component Framework) are scoped more narrowly than a full portal session.
- **CSRF** — every state-changing portal and widget request is CSRF-protected; widget embeds additionally validate origin against the `WidgetEmbed`'s configured allowed origins.
- **Rate limiting** — unauthenticated surfaces (Contact Widget, AI Chat Widget, public booking requests) are rate-limited per origin/IP/embed-key to prevent spam Lead flooding and abuse, since these are the platform's most exposed unauthenticated attack surface.

## 14. Privacy

- **PDPA / GDPR** — as with Communications (`docs/architecture/11_Communications_Hub_Architecture.md` §12), regional privacy regimes apply to any personal data collected through public-facing Digital Presence surfaces, consistent with the platform's Thailand-first posture.
- **Consent** — cookie/tracking consent on public-facing pages, and data-collection consent on intake forms and booking requests, are tracked per visitor/client, distinct from Communications' messaging-channel consent (WhatsApp/SMS opt-in) but following the same `ConsentRecord` pattern.
- **Cookie preferences** — a visitor's cookie/tracking preference is itself firm-scoped configuration (a Firm may run its own analytics) layered on a platform-provided consent-banner mechanism, not a hardcoded platform default.
- **Data export / Deletion** — a Client's portal and public-site interaction data is exportable and deletable on request, subject to the same legal-hold override Communications defines, since Digital Presence data (intake, booking history) is frequently linked to Communications threads.

## 15. Accessibility

- **WCAG support** — Website Builder output, the Client Portal, and every Embedded Widget target WCAG AA, extending the contrast-token discipline `docs/architecture/10_White_Label_Platform_Architecture.md` §15 already applies to Branding tokens across full page/component structure.
- **Keyboard navigation** — every interactive surface, including widgets embedded in a third-party page, must be fully operable by keyboard alone.
- **Contrast** — inherits Branding's `ContrastPolicy` validation; a Firm's chosen colors are checked, not merely styled.
- **Screen readers** — semantic HTML and ARIA attribution are required across Website Builder templates, Portal views, and widgets; a widget embedded in a third-party page is a harder accessibility surface than a fully-hosted page precisely because Digital Presence does not control the surrounding DOM, and this must be accounted for in the Embedded Component Framework's design, not treated as the host site's problem.

## 16. API Architecture

- **Public APIs** — read access to published Knowledge Publishing content and write access for booking/intake, used by the enterprise CMS integration deployment model and by any third-party consumer a Firm authorizes.
- **Embedded APIs** — scoped, embed-key-authenticated endpoints used exclusively by Embedded Widgets; narrower in permission surface than the Public API or a full Client Portal session (see Embedded Component Framework).
- **Authentication** — three distinct authentication boundaries: Client Portal session (full client identity, `ClientPortalIdentity`), widget embed key (site-level, not client-level, identity), and Public API credentials (Firm-level, for enterprise/CMS consumers) — never conflated with one another.
- **Versioning** — exact conventions belong to `docs/architecture/07_API_Standards.md`, currently an empty placeholder; this document assumes Digital Presence's Public and Embedded APIs will follow whatever versioning discipline that document eventually establishes, and does not invent a competing one here.

## 17. Future Expansion

- Mobile app reuse (the same Public/Embedded API surface consumed by a future native app rather than a browser).
- Progressive Web App (installable, offline-capable Client Portal).
- Client mobile app (a dedicated native app beyond a PWA).
- Partner portals (a Firm's referral partners, distinct identity/permission model from Clients).
- Court portals (integration with external court e-filing/portal systems, distinct from the Firm's own Client Portal).

Every item above is additive on top of the existing `DigitalPresenceProfile`/`ContentItem`/`ClientPortalIdentity`/`BookingRequest` model and the Public/Embedded API boundary, not a redesign of it — mirroring the extension discipline established in `docs/architecture/09_Legal_Intelligence_Architecture.md`, `docs/architecture/10_White_Label_Platform_Architecture.md`, and `docs/architecture/11_Communications_Hub_Architecture.md`.

## 18. Failure Modes

Conceptual failure modes the architecture must account for (mitigations are implementation-story-level detail):

- **Authentication failures** — repeated failed logins lock the `ClientPortalIdentity` per `PortalAuthPolicy` rather than allowing unlimited attempts; MFA failure has a distinct, auditable failure path from password failure.
- **Portal downtime** — a Client Portal outage must not take down public-facing Website Builder pages or the AI Chat/Contact widgets, since they are independent rendering surfaces over the same platform, not a single monolithic page.
- **Booking conflicts** — two booking requests for the same slot are resolved by an explicit conflict check against `AvailabilitySchedule` and existing confirmed `BookingRequest`s at confirmation time (`BookingConflictDetected`), never by silently double-booking and resolving later.
- **Widget failures** — a widget failing to load or erroring on a third-party page must fail in isolation (sandboxed embed) without breaking the host page around it; this is a requirement of the Embedded Component Framework's design, not an incidental property.
- **Communication outages** — Digital Presence's AI Chat, Contact Widget, and Portal Messages inherit Communications' offline handling (queue, retry, idempotency — `docs/architecture/11_Communications_Hub_Architecture.md` §14) rather than defining a second, competing offline-handling model.

## Digital Presence Strategy

The three deployment models (see Supported Deployment Models) exist because OneLegalPro's Firms are not at the same starting point:

- A newly formed or small Firm often has **no website at all** — the fully hosted model gives them one, with Website Builder, Content Management, Booking, the Client Portal, and every widget available from day one, on a OneLegalPro-provisioned or Firm-bound custom domain (`docs/architecture/10_White_Label_Platform_Architecture.md`, Custom domains).
- A Firm with an **existing website** it does not want to migrate away from — for brand, SEO history, or agency-relationship reasons — adopts Embedded Widgets instead, gaining the AI receptionist, booking, client login, and portal linkage without a hosting migration.
- A **larger or enterprise Firm** with its own CMS and web team integrates at the API layer, pulling Knowledge Publishing content or posting booking/intake data programmatically, with no dependency on Website Builder's templates or a widget's embedded UI at all.

These are not permanent, mutually exclusive tiers: a Firm can start on Existing Website Integration (widgets bolted onto its current site) and later migrate to Fully Hosted once it decides to consolidate, without losing its `DigitalPresenceProfile` configuration, `ContentItem` history, `ClientPortalIdentity` records, or Communications history — because none of that data is deployment-model-specific. The deployment model governs *how* Digital Presence is surfaced, not what data exists underneath it.

## Embedded Component Framework

Each Embedded Widget (Booking, Client Login, Matter Status, AI Chat, Contact, Payment, Document Upload) is built once, as a single, brand-resolved, sandboxed component, and rendered identically whether it appears on a OneLegalPro-hosted page or embedded in a third-party site. This is preferred over building each widget as a bespoke, standalone integration for the following reasons:

- **Single source of truth for behavior.** A booking-conflict fix, an accessibility improvement, or an AI-governance change (Article 12) needs to be made once and takes effect everywhere the widget is embedded, rather than being reimplemented — and potentially inconsistently reimplemented — per integration.
- **Consistent composition, not duplication, of Branding and Communications.** A widget resolves branding through the same Branding Resolver contract (Branding, above) and produces/consumes messages through the same Communications contracts (Communications Integration, above) regardless of where it's embedded; a bespoke per-site integration would otherwise need its own branding and messaging logic, directly contradicting Decisions 10–11.
- **A narrow, well-defined security boundary.** Each `WidgetEmbed` carries its own `EmbedKey` and `AllowedOrigin` list, scoping what a widget can do (and which Firm's data it can touch) independently of a full Client Portal session or Public API credential — this is only tractable because there is one component contract to secure, not one per widget-per-Firm-per-site combination.
- **Third-party-site safety.** Because the component is sandboxed (isolated rendering context, scoped API surface, CSRF/origin validation — see Security), a failure or compromise in one embedded widget cannot cascade into the host page around it or into another Firm's data, satisfying the "widgets must work on third-party websites" requirement without trusting the third-party page.

Reusability here is not a code-quality preference; it is what makes the "existing website integration" and "enterprise CMS integration" deployment models viable at all without duplicating Branding, Communications, and security logic per widget.

## SEO Strategy

SEO is a first-class property of `ContentItem` and Website Builder output, not an afterthought layered on at render time:

- Every `ContentItem` carries `SEOMetadata` (title, meta description, canonical URL, and structured-data references) as part of its own aggregate, authored during the Draft → Review → Publish workflow, not generated ad hoc by the rendering layer.
- Fully hosted websites benefit from platform-managed technical SEO (sitemap generation, canonical URL handling per `TenantDomain`, structured data for Lawyer Profiles/Office Locations/Articles).
- For Existing Website Integration and Enterprise CMS Integration, SEO ownership is split deliberately: the Firm's own site retains its existing SEO equity, while any Digital-Presence-hosted content (for example, a Knowledge Publishing article not mirrored on the Firm's own site) carries its own correct metadata rather than inheriting none.
- Multilingual content (Website Builder, above) carries per-language SEO metadata and canonical/alternate-language linking, distinct from — and never a substitute for — Legal Intelligence's jurisdiction/language authority model (`docs/architecture/09_Legal_Intelligence_Architecture.md`).

## Knowledge Publishing

Knowledge Publishing is the subset of Content Management dedicated to legal-adjacent educational content a Firm authors and publishes:

- Articles
- Legal updates
- FAQs
- Educational content

Every Knowledge Publishing item is a `ContentItem` with `ContentType` in this category, following the same Draft → Review → Publish lifecycle as any other content type (Content Management, above). It is **firm-owned editorial material**, not an official legal source — the same category Article 7 of `docs/architecture/01_OneLegalPro_Constitution.md` already names as "Legal commentary" and "Firm-owned research and annotations," which must never be mixed with, or presented as, official law.

**Future integration with Legal Intelligence.** Published Knowledge content may later be linked to `docs/architecture/09_Legal_Intelligence_Architecture.md`'s `LegalSource` and `CrossReference` model — for example, a Firm's "legal update" article referencing the statute it discusses — but such a link is a `CrossReference`-style citation from firm-owned commentary *to* an official source, never an absorption of the commentary *into* the platform-global legal-source subdomain. This is an explicit, deliberate boundary: Knowledge Publishing content remains firm-scoped and editorial even where it cites or will eventually be indexed alongside platform-global Legal Intelligence content for AI retrieval purposes (`docs/architecture/05_AI_Architecture.md`'s authority-aware ranking already requires distinguishing official sources from commentary in retrieval, which extends naturally to Knowledge Publishing content without any change to that ranking model).

## AI Rules

The Website AI Receptionist embedded in Digital Presence (see AI Receptionist Integration, above) is the same AI system architected in `docs/architecture/11_Communications_Hub_Architecture.md`, bound by `docs/architecture/01_OneLegalPro_Constitution.md`, Article 12, in full. Restated in the Digital Presence context:

1. **Identify itself.** Every AI-driven surface embedded in a website or portal (AI Chat Widget, Website AI Chat, Client Portal Chat) must be clearly identified as AI.
2. **Never imply an attorney-client relationship.** Interacting with the embedded AI, on a Firm's own fully hosted site or embedded in a third-party page, does not by itself create an attorney-client relationship.
3. **Never provide legal advice.** The AI gathers information, detects practice area and urgency, and routes; it does not answer what a visitor's legal position is or what they should do about it.
4. **Escalate appropriately.** Where urgency is detected, confidence is low, or a human is explicitly requested, the conversation is escalated to a human without restarting it, per the handoff model in `docs/architecture/11_Communications_Hub_Architecture.md` §5.
5. **Record provenance.** Every AI-produced output on a Digital Presence surface carries `AIAnnotation` provenance (model/version, timestamp, confidence), the same discipline required of every other AI surface on the platform.

## Domain boundaries

Digital Presence is a distinct domain from Branding, Communications, and future Practice Management. It must never mix:

1. **Public content** — `ContentItem` (Website Builder pages, Knowledge Publishing) — authored and published by the Firm, distinct from official legal sources (Article 7).
2. **Portal presentation** — the Client Portal's aggregation of Matters/Documents/Invoices/Tasks/Appointments/Messages, which it reads but never owns.
3. **Client authentication identity** — `ClientPortalIdentity`, distinct from the underlying Client business record it references.
4. **Embedded component configuration and security** — `WidgetEmbed`, distinct from the widget's rendered content, which is composed from Branding/Communications/Content/Booking data at render time.
5. **Scheduling requests** — `BookingRequest`/`AvailabilitySchedule`, distinct from the `Appointment` aggregate they ultimately produce or update in a future Practice Management module.

Each is a distinct concept in the domain model, even where they share infrastructure (hosting, CDN, rendering pipeline).

## Ownership boundary

Digital Presence has no platform-global content subdomain — the third module, after `Branding` and `Communications`, to take this wholly firm-scoped shape, reinforcing it as the platform's normal pattern for client/firm-facing product surfaces, in contrast to Legal Intelligence's exceptional platform-global/firm-scoped split:

| Subdomain | Scope | Ownership boundary |
|---|---|---|
| Widget component contract and capability schema | Platform-global | Not `FirmContext` — static configuration shared by every Firm |
| `DigitalPresenceProfile`, `ContentItem`, `ClientPortalIdentity`, `AvailabilitySchedule`, `BookingRequest`, `WidgetEmbed`, `MediaAsset` | Firm-scoped | `FirmContext` |

A rendering surface may read the platform-global widget contract/schema, but every actual piece of content, identity, or booking data it renders for a given Firm is that Firm's own data, reached only through Digital Presence's published queries, Branding's Resolver, and Communications' published contracts — never through another module's Eloquent records directly.

## Proposed module structure

**Unresolved implementation choice:** exact module name. This document proposes `DigitalPresence`, consistent with the module-per-bounded-context pattern already established for `LegalIntelligence`, `Branding`, and `Communications`.

```text
DigitalPresence/
├── Application/
│   ├── Content/          (CreateContentItem, SubmitContentForReview, PublishContentItem,
│   │                       ReviseContentItem, ArchiveContentItem, ...)
│   ├── Portal/            (RegisterClientPortalIdentity, AuthenticateClient, EnableMFA,
│   │                        IssueMagicLink, SetPortalAuthPolicy, ...)
│   ├── Booking/           (DefineAvailability, RequestBooking, ConfirmBooking, CancelBooking, ...)
│   └── Widgets/           (IssueWidgetEmbed, RevokeWidgetEmbed, ConfigureWidget, ...)
├── Domain/
│   ├── DigitalPresenceProfile   (aggregate root)
│   ├── ContentItem              (aggregate root)
│   ├── ClientPortalIdentity     (aggregate root)
│   ├── AvailabilitySchedule     (aggregate root)
│   ├── BookingRequest           (aggregate root)
│   ├── WidgetEmbed, MediaAsset  (entities)
│   ├── DeploymentModel, ContentType, ContentStatus, SEOMetadata, PortalAuthPolicy,
│   │   AuthMethod, TimeSlot, TimeZone, Modality, EmbedKey, AllowedOrigin   (value objects)
├── Infrastructure/        (hosting/CDN adapter, enterprise CMS connector adapters,
│                            calendar/timezone library adapters; reuses Branding's
│                            TenantDomain/SSL infrastructure rather than its own)
├── Interface/              (public website rendering, Client Portal API, Embedded
│                            Component API, public content API for CMS pull)
├── Database/               (new migrations only — no historical migrations touched)
├── Routes/
├── Tests/
├── Config/                 (widget capability schema, default `PortalAuthPolicy`,
│                            booking conflict rules, rate-limit thresholds)
├── ModuleServiceProvider.php
└── README.md
```

Dependency direction and cross-module rules follow `docs/domain/06_Laravel_Module_Blueprint.md` unchanged: Interface → Application → Domain; Infrastructure depends on Application/Domain contracts; Domain never depends on Laravel/Eloquent/HTTP; every other module reaches Digital Presence data only through published queries/commands, and Digital Presence itself reaches Branding and Communications only through their published contracts, never through Eloquent records directly.

## Aggregates, entities, and value objects

**Aggregates**

- `DigitalPresenceProfile` — per-Firm configuration: enabled deployment model(s), `PortalAuthPolicy`, booking configuration reference. One per Firm.
- `ContentItem` — a single piece of published or draft content (practice page, lawyer profile, office location, news item, article, FAQ, legal update), carrying `SEOMetadata` and `MediaAsset` references, following Draft → Review → Publish, editable in place with revision history (not immutable, unlike `LegalSource`).
- `ClientPortalIdentity` — a Client's portal authentication identity: credential/passwordless method, MFA configuration; references the Client business record by ID only.
- `AvailabilitySchedule` — a lawyer's or office's bookable availability (recurring rules, exceptions, timezone).
- `BookingRequest` — a requested or confirmed booking, ultimately producing/updating an `Appointment` in a future Practice Management module (see Booking System, Unresolved implementation choice).

**Entities** (owned by an aggregate, identity matters, mutable within the aggregate's lifecycle rules)

- `WidgetEmbed` — owned by `DigitalPresenceProfile`; one per enabled widget type, holding its `EmbedKey`, `AllowedOrigin` list, and widget-specific configuration.
- `MediaAsset` — owned by a `ContentItem` or the Firm's shared Media Library; a logo-independent image/file used in published content (distinct from `BrandAsset`, which is Branding's identity asset).

**Value objects** (immutable, no identity)

- `DeploymentModel` — `FullyHosted` / `ExistingWebsiteIntegration` / `EnterpriseCMSIntegration`.
- `ContentType` — `PracticePage` / `LawyerProfile` / `OfficeLocation` / `News` / `Article` / `FAQ` / `LegalUpdate` / `ContactPage`.
- `ContentStatus` — `Draft` / `InReview` / `Published` / `Archived`.
- `SEOMetadata` — title, meta description, canonical URL, structured-data references.
- `PortalAuthPolicy` — allowed `AuthMethod`s, MFA requirement, session timeout, future SSO configuration.
- `AuthMethod` — `Password` / `Passwordless` / `MagicLink` / `MFA` / `SSO` (future).
- `TimeSlot`, `TimeZone`, `Modality` (`Office` / `Video` / `Phone`) — Booking value objects.
- `EmbedKey`, `AllowedOrigin` — widget security value objects.

**Explicit non-aggregate: Intake.** Intake is not modeled as its own aggregate or data store. Intake data collected through the Contact Widget, Booking Widget, or AI Chat Widget flows directly into a Communications `CommunicationThread` (as message content and `AIAnnotation`) and/or a `BookingRequest`; Digital Presence composes Communications and Booking to realize Intake rather than duplicating a third data model for the same information.

**Events** (past tense, per `docs/domain/06_Laravel_Module_Blueprint.md` naming convention)

`ContentDrafted`, `ContentSubmittedForReview`, `ContentPublished`, `ContentRevised`, `ContentArchived`, `ClientPortalIdentityCreated`, `ClientAuthenticated`, `ClientAuthenticationFailed`, `MFAEnabled`, `PortalAuthPolicyUpdated`, `AvailabilityDefined`, `BookingRequested`, `BookingConfirmed`, `BookingCancelled`, `BookingConflictDetected`, `WidgetEmbedIssued`, `WidgetEmbedRevoked`, `DigitalPresenceProfileUpdated`.

**Lifecycle and state transitions**

- `ContentItem`: `Draft` → `InReview` → `Published` → (`Published` may be revised in place, recording a new revision) → `Archived`.
- `ClientPortalIdentity`: `Invited` → `Activated` → `Active` (or `Locked` after repeated failed authentication, or `MFAPending` where MFA is required) → `Deactivated`.
- `BookingRequest`: `Requested` → `Confirmed` (conflict-checked against `AvailabilitySchedule` and existing confirmed requests at this transition) → `Completed` / `Cancelled` / `NoShow`.
- `WidgetEmbed`: `Issued` → `Active` → `Revoked` (immediate, invalidating the `EmbedKey`).

## Storage strategy

**Unresolved implementation choice:** exact storage backend split, to be finalized in a future implementation-focused ADR once content volume and rendering-performance requirements are known. Conceptually:

- Structured configuration and metadata (`DigitalPresenceProfile`, `ContentItem` metadata/status, `ClientPortalIdentity`, `AvailabilitySchedule`, `BookingRequest`, `WidgetEmbed`) belongs in PostgreSQL, consistent with the platform's existing database rule and UUIDv7 identity.
- `MediaAsset` binaries belong in object storage, referenced by identifier, the same pattern `docs/architecture/10_White_Label_Platform_Architecture.md` applies to `BrandAsset`.
- A CDN or edge cache may sit in front of published `ContentItem` output for performance; cache invalidation on publish/revision is an implementation-level requirement, not a schema concern.

## Access-control boundaries

- `ContentItem` authoring (Draft/Review/Publish) requires Firm-staff authorization; public read access applies only to `Published` items.
- `ClientPortalIdentity` mutations require either the Client's own authenticated action or Firm-admin action (for account recovery/deactivation).
- Widget requests are authorized by `EmbedKey` + `AllowedOrigin` validation, a narrower boundary than a full Client Portal session or Public API credential (API Architecture, above).
- Firm-scoped isolation is enforced at the application, repository, and database-policy layers, never by global scopes alone, per `docs/domain/06_Laravel_Module_Blueprint.md`.
- Any write path that could let Digital Presence bypass Branding's or Communications' own access-control rules (for example, a widget reading another Firm's `BrandProfile` or `CommunicationThread`) is a defect, not a variant, per the published-contract-only rule those architectures already establish.

## Phased implementation guidance

See `docs/architecture/08_Roadmap.md`, the proposed Digital Presence Platform epic, for staged delivery order (`DigitalPresenceProfile` and deployment-model foundation → Content Management and Website Builder → Client Portal authentication → Practice Management read-surfaces as their owning modules become available → Booking System → AI Receptionist/widget integration → Embedded Component Framework and third-party embedding → Knowledge Publishing → SEO and accessibility hardening → enterprise API/CMS integration). That staging is proposed only; formal scheduling requires entries in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` and separate story-level approval before implementation begins.
