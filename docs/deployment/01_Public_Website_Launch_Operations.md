# Public Website Launch Operations

**Status:** Public launch authorized on 1 September 2026

**Scope:** The OneLegalPro public operator website only. This record does not
authorize or describe Matter Desk production infrastructure, access, data, or
deployment.

## Approved release

- Sites project: `OneLegalPro — Private Launch Review`
- Approved private-review URL:
  `https://onelegalpro-launch-review.jandandco.chatgpt.site`
- Approved Sites version: 3
- Website source commit:
  `125ac967bd2c442bd8d1c709dc67abe95b1128ba`
- Repository correction commit:
  `c0a2eda79924532ee17e5d0c8ce9285ad86450a4`
- Legal verification record commit:
  `267aeef`

The repository owner authorized public launch on 1 September 2026 by stating
"ok launch" in direct response to the proposed operational plan. That approval
covers public access to the exact version above and the monitoring, incident,
and rollback plan below. It does **not** authorize connecting `onelegalpro.com`
or making any DNS change.

## Incident ownership

Michael Jand is the initial public-website incident owner. An availability,
certificate, content-integrity, privacy, or security concern is escalated to
the incident owner. No website incident grants access to the Matter Desk or
authorizes weakening a security, privacy, or legal boundary.

## Monitoring

- An automated availability check runs every 15 minutes against the published
  Sites URL. It verifies an HTTPS success response and recognizable
  OneLegalPro content and reports a failure to the incident owner.
- An automated TLS certificate check runs daily against the published Sites
  URL and reports an invalid, mismatched, or near-expiry certificate.
- Monitoring records operational reachability only. It is not an availability
  guarantee, security certification, or proof that every visitor path works.
- The custom domain is not monitored until it is separately authorized and
  activated. Its checks must be added before it becomes canonical.

## Rollback

The incident owner retains owner access to the Sites project and its immutable
saved versions. For a defective release:

1. return access to owner-only when continued public exposure creates legal,
   privacy, security, or materially misleading content risk;
2. redeploy the most recent known-good saved Sites version;
3. verify HTTPS, the homepage, Privacy Notice, Website Terms, security headers,
   and the disclosed hosting cookie on the restored version; and
4. record the incident, affected version, decision, rollback version, and
   verification result before reopening public access.

If a future custom domain is active and rollback cannot restore a safe site,
remove that domain from the Sites project under explicit owner authority. DNS
or domain removal is not pre-authorized by this launch record.

## Pre-launch verification

Before public access was authorized, version 3 passed source linting, the
production build, rendered-route tests, cookie-disclosure assertions, the
production dependency audit with no reported vulnerability, and repository
whitespace checks. Live validation of the owner-only deployment returned HTTPS
200 and confirmed the Content Security Policy, HSTS, Permissions Policy,
Referrer Policy, MIME-sniffing protection, and frame protections. It also found
the Cloudflare `__cf_bm` cookie, which was disclosed through the separately
approved narrow correction before launch.

## Outstanding custom-domain gate

`onelegalpro.com` is not authorized or connected by this record. Before that
change, the owner must separately authorize the custom domain, the canonical
redirect must be verified, and availability and certificate monitoring must be
extended to the domain.
