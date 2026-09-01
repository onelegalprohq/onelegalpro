# OneLegalPro public website

This directory contains Jand Corp Company Limited's public operator website for OneLegalPro. It is separate from the Laravel Matter Desk application and from every Firm-facing or client-facing bounded-context surface.

The launch candidate includes the homepage, founding-firm pilot boundaries, email-based inquiry flow, Privacy Notice, Website Terms, metadata, sitemap, and social preview asset. The legal documents and marketing copy remain drafts until the review in `docs/legal/WEBSITE_LAUNCH_LEGAL_REVIEW.md` is completed and recorded.

The website stores no inquiry form values. Submitting the form prepares a message in the visitor's email application. It provides no authentication, account, portal, AI, upload, payment, tracking, or persistent-data capability.

## Local commands

- `npm run dev` — private local preview.
- `npm run lint` — source lint and accessibility rules.
- `npm test` — deployment build plus rendered-route assertions.
- `npm audit --omit=dev` — production dependency audit.

Public deployment, custom-domain activation, and hosting procurement each require the approvals recorded in `docs/adr/ADR-027-Public-Website-Hosting-and-Launch-Operations.md`.
