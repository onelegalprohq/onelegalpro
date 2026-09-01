# ADR-027 — Public Website Hosting and Launch Operations

**Status:** Accepted
**Decision date:** 1 September 2026

**Approval record:** On 1 September 2026, the repository owner explicitly
approved ADR-027 for hosting the public OneLegalPro operator website through
OpenAI Sites on Cloudflare-compatible infrastructure. The approval is limited
to the public website and does not authorize public access, DNS changes, or
Matter Desk production infrastructure.

## Context

Release 0.1 requires a public operator website, inquiry path, Privacy Notice, Terms, and pilot agreement. The operator website is outside `app/Modules`, is not a Firm tenant website or Client Portal, and must not imply that the Matter Desk is generally available or production-ready.

The approved deployment architecture deliberately selected no vendor and requires a separate owner-approved procurement decision entangled with jurisdiction, data residency, and subprocessor review. This ADR is that proposed decision for the public website only. It does not select the Matter Desk application-production platform.

## Proposed decision

1. Host the static operator website through OpenAI Sites on Cloudflare-compatible infrastructure, using a private owner-only deployment for review and an explicit separate approval for public access.
2. Use `onelegalpro.com` as the canonical public hostname. DNS changes and custom-domain activation require separate explicit authorization after the final private version passes review.
3. Keep the operator site physically and logically separate under `website/`; it owns no domain data and imports no Laravel application or bounded-context model.
4. Launch without accounts, authentication, cookies, behavioural advertising, AI, chat, uploads, payment, portal access, or persistent website storage.
5. The inquiry form prepares an email in the visitor's own email application. The website does not persist the form values. Email processing remains subject to the approved email-provider and legal-review decisions.
6. Publish no price. Pilot availability, fit, terms, and timing are confirmed individually.
7. Public copy states the four absent pilot capabilities and labels every interface preview illustrative.
8. The site may be public only after build validation, Thai-qualified legal approval of the exact version, correction verification, owner publication approval, and confirmed rollback access.

## Security and operations

- HTTPS only; canonical-domain redirect; no secret in source or build output.
- Content Security Policy and other response headers must be validated against the chosen host before launch.
- Dependency and build findings are resolved or explicitly risk-accepted before publication.
- Availability monitoring, certificate monitoring, incident ownership, and a rollback procedure are recorded before launch.
- Public website availability grants no Matter Desk production access.

## Consequences

The marketing surface can be reviewed and launched independently of the Laravel pilot application. The absence of persistent form storage reduces data handling but depends on the visitor having an email client. A future server-side inquiry path requires its own approved processor, retention, abuse, audit, and privacy decisions.

## Approval required

Approval of this ADR authorizes only the named website-hosting procurement decision. It authorizes no Matter Desk production infrastructure, AWS expenditure, credential, production database, deployment, or production access.
