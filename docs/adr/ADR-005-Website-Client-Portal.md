# ADR-005 — Website & Client Portal

## Status

Accepted. The human owner has explicitly approved this decision.

**Amended by ARCH-008 (`docs/adr/ADR-009-Identity-Security-Access-Control.md`) on the authentication boundary only** — see Authentication boundary correction, below. Every other decision in this ADR stands unchanged, and no approved Client Portal capability is removed.

## Context

OneLegalPro's Firms need a public website, a client-facing portal, embeddable widgets for firms that already have their own site, a booking system, an AI receptionist, intake collection, authenticated client access, and a place to publish articles and FAQs. Prior to this ADR, none of this had an approved architecture. Two of these — "Digital Presence" and "Client Portal" — already appeared as unarchitected later-phase names in `docs/implementation/01_Implementation_Sprint_Plan.md` and `docs/architecture/08_Roadmap.md`, but neither had a domain model, and nothing addressed how a website and a portal, or the widgets a Firm might embed on its own separate site, would relate to each other or to the platform's already-approved Branding (`docs/adr/ADR-003-White-Label-Platform.md`) and Communications Hub (`docs/adr/ADR-004-Communications-Hub.md`) architectures.

OneLegalPro must serve three materially different starting points: Firms with **no website at all**, Firms with an **existing website** they will not migrate away from, and **enterprise** Firms whose own CMS or web team wants to integrate programmatically. Any architecture that assumes only one of these (typically "we host everything") fails the other two. Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, this needs an approved architecture before any Digital Presence or Client Portal implementation work begins.

## Decision

1. **Digital Presence is its own bounded context** (proposed module `DigitalPresence`), composing already-architected capabilities — Branding and Communications — into public websites, a client portal, embedded widgets, booking, client portal access, and content publishing, **without owning identity or messaging itself**. (This ADR originally listed "client authentication" among the composed capabilities while also stating that Digital Presence does not own identity; ARCH-008 resolves that tension in favor of the latter — see Authentication boundary correction, below.)
2. **Digital Presence supports three deployment models simultaneously**: fully hosted website, existing website integration (via Embedded Widgets), and enterprise CMS integration (via Public APIs) — addressing Firms without a website, Firms with one already, and enterprise integrations, respectively, as three configurations of one platform rather than three separate products.
3. **Every widget is one reusable, sandboxed Embedded Component**, not a bespoke integration per Firm or per site. A widget resolves branding and messaging through the same published contracts Digital Presence uses everywhere else, and is authorized through a narrow `WidgetEmbed`/`EmbedKey`/`AllowedOrigin` boundary distinct from a full Client Portal session or Public API credential.
4. **Websites and the Client Portal share the same underlying infrastructure**: the same Branding Resolver, the same Communications integration, the same `TenantDomain`/SSL handling, the same accessibility and SEO discipline. Neither surface reimplements what the other already has.
5. **The Client Portal is a presentation surface, not a data owner**, for Matters, Documents, Invoices, Payments, Tasks, and Appointments — it reads through each owning module's published queries and never duplicates that data into its own schema.
6. **The embedded AI receptionist is the Communications Hub's AI, not a new AI system.** Digital Presence provides the embed point (Website AI Chat, Client Portal Chat, AI Chat Widget); the AI governance rules already established in `docs/architecture/01_OneLegalPro_Constitution.md`, Article 12, and `docs/architecture/05_AI_Architecture.md`'s Communications Hub AI section apply unchanged.
7. **Digital Presence data is wholly firm-scoped**, isolated by `FirmContext`, with no platform-global content subdomain — the third module, after Branding and Communications, to take this shape.

Full conceptual detail is recorded in `docs/architecture/12_Website_Client_Portal_Architecture.md`.

## Authentication boundary correction (ARCH-008)

When this ADR was accepted, no identity architecture existed, so Digital Presence provisionally held the Client Portal's authentication identity (`ClientPortalIdentity`) and authentication policy (`PortalAuthPolicy`). That was always in tension with Decision 5 — the Portal is a presentation surface, not a data owner — and with Decision 1's own statement that Digital Presence does not own identity. **ARCH-008 resolves it:**

- **IdentityAccess owns** the Client Portal principal, credentials, authentication factors, authentication attempts, recovery, and sessions.
- **Practice Management continues to own the underlying `Client`.**
- **Digital Presence owns** the portal surface, presentation, portal-specific preferences, and permission-aware composition, and **invokes IdentityAccess contracts** for invitation, authentication, MFA, session, and recovery operations. It **must not store passwords, authenticators, recovery secrets, or session authority.**
- **`ClientPortalIdentity` is replaced by `ClientPortalAccessProfile`** — a presentation/access concept linked by identifier to an IdentityAccess principal, the Practice Management `Client`, and the Firm, carrying portal status and preferences but **no credentials, MFA secrets, passwordless tokens, recovery data, or authoritative session state**.
- **`PortalAuthPolicy` is reinterpreted** as a reference to (and presentation of) the Firm's authoritative, IdentityAccess-owned channel `AuthenticationPolicy`. Digital Presence is not the policy authority.
- **Authentication events originate from IdentityAccess** and may be consumed by Digital Presence.
- **Successful authentication is not resource visibility** — what a client sees still comes from the owning domain's audience and authorization rules.
- **`WidgetEmbed` and `AllowedOrigin` remain Digital Presence concepts.** Embedded-widget capability security stays narrower than an authenticated client session and never becomes a Client identity.

**No approved Client Portal capability is removed by this correction** — password, passwordless, magic link, MFA, future SSO, Firm-configurable policy, lockout, and recovery all remain available, sourced from IdentityAccess instead of implemented locally. Decisions 2, 3, 4, 6, and 7 are unaffected. See `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §28.

## Why Digital Presence is its own bounded context

No existing or planned module naturally owns "the website" or "the portal": Branding owns identity, Communications owns messages, and a future Practice Management module will own Matters and Invoices, but none of them owns how those pieces come together into a client-facing product surface. Absent a dedicated bounded context, that composition responsibility would either fragment across the modules it composes (each reinventing a slice of "the website") or accumulate as unowned presentation logic with no aggregate boundary. Giving it a name and a boundary, while leaving every underlying capability owned exactly where it already is, is the same reasoning `docs/adr/ADR-004-Communications-Hub.md` applied to keeping Communications distinct from the modules that consume it.

## Why embedded components are preferred

Building each widget (Booking, Client Login, Matter Status, AI Chat, Contact, Payment, Document Upload) as a bespoke, standalone integration per Firm or per third-party site would mean re-solving branding resolution, message handling, and security boundary enforcement once per widget instead of once for the platform — precisely the duplication `docs/architecture/10_White_Label_Platform_Architecture.md` already rejected for arbitrary per-tenant styling and `docs/architecture/11_Communications_Hub_Architecture.md` already rejected for per-provider business logic. A single, reusable, sandboxed component contract makes the "existing website integration" and "enterprise CMS integration" deployment models viable without trusting or duplicating logic into a third-party page.

## Why websites and portals share infrastructure

A public website and an authenticated client portal look different, but the underlying needs are identical: resolve the Firm's brand, integrate with the Firm's communications, run on the Firm's domain, meet the same accessibility bar. Treating them as two unrelated products would mean building — and keeping in sync — two branding integrations, two messaging integrations, two domain/SSL configurations, and two accessibility postures, with every future fix needing to land twice. A single bounded context sharing one set of integrations avoids that drift entirely.

## Alternatives considered

- **Split "Website" and "Client Portal" into two separate modules.** Rejected — both need identical Branding resolution, Communications integration, `TenantDomain`/SSL infrastructure, and accessibility discipline; splitting would duplicate that plumbing and risk drift, such as the portal missing a disclaimer rule the website enforces.
- **Only support the fully hosted deployment model, leaving Firms with existing websites to integrate however they can.** Rejected — directly fails two of the three required starting points (Firms with existing websites, enterprise integrations) named in the objective.
- **Build each Embedded Widget as an independent, standalone product with its own auth and data model.** Rejected — would duplicate Branding, Communications, and security-boundary logic once per widget, the exact problem the Embedded Component Framework is designed to avoid.
- **Let the Client Portal maintain its own cached copy of Matters/Documents/Invoices/Tasks/Appointments for performance.** Rejected as the default — introduces a second source of truth and staleness risk; the Portal reads through published queries, with caching (if needed) treated as an implementation-level concern of the read path, not a reason to duplicate ownership.
- **Give the embedded AI receptionist its own, portal-specific governance rules distinct from Communications' AI rules.** Rejected — the AI is the same system regardless of where it's embedded; a second, portal-specific rule set would risk drifting from `docs/architecture/01_OneLegalPro_Constitution.md`, Article 12, and create exactly the kind of AI-governance inconsistency the Constitution's amendment discipline is meant to prevent.

## Trade-offs

- Consolidating website, portal, widgets, booking, and client authentication into one bounded context is a larger module than a narrowly scoped one would be, but avoids the cross-module plumbing duplication the alternatives above would otherwise require.
- Supporting three deployment models simultaneously, rather than shipping one and adding the others later, is more upfront design work (Website Builder, the Embedded Component Framework, and the Public API must all stay consistent with each other), but is required by the stated objective rather than optional scope.
- A widget sandboxed for safety on a third-party page is harder to fully control and style than a fully-hosted page — Branding token resolution and accessibility must work within the constraints of an embed the Firm does not fully own.
- Allowing `ContentItem` (unlike `LegalSource`) to be edited in place with revision history, rather than strictly immutable, is the right call for marketing/educational content, but requires ongoing care that this leniency is never mistaken for, or extended to, actual legal-source content.
- Treating `Appointment` as an external, not-yet-architected dependency (owned by a future Practice Management module) means Booking's design is necessarily provisional at its edges until that architecture exists.

## Future extensibility

The architecture is designed so that mobile app reuse, a Progressive Web App, a dedicated client mobile app, partner portals, and court portals can each be added as new consumers of the existing Public/Embedded API surface and the existing `DigitalPresenceProfile`/`ContentItem`/`ClientPortalAccessProfile`/`BookingRequest` model (with authentication supplied by IdentityAccess), without redesigning any of them. See `docs/architecture/12_Website_Client_Portal_Architecture.md`, Future Expansion.
