# ARCH-015 — Billing, Trust Accounting & Finance Architecture

**Status:** Approved (conceptual architecture) — implementation stories are proposed, not scheduled; see `docs/architecture/08_Roadmap.md`.

## 1. Purpose and scope

This document defines the conceptual domain and system architecture for OneLegalPro's Billing, Trust Accounting, and Finance capability, implementing `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md` and the relevant articles of `docs/architecture/01_OneLegalPro_Constitution.md`.

It covers **one bounded context containing three explicitly separated financial domain areas**:

| Area | Sections | Answers |
|---|---|---|
| **A — Billing and Accounts Receivable** | §8–§23 | "What did we agree to charge, what have we billed, and what are we owed?" |
| **B — Client Money / Trust Accounting** | §31–§43 | "Whose money are we holding, on what terms, and does it reconcile?" |
| **C — Firm Finance and Accounting** | §44–§54 | "What is the Firm's own financial position, in double-entry terms?" |

Tax and statutory financial documents (§24–§29) and Payments (§30, §30.1–§30.12) span areas A and C and are documented once. Cross-context integration (§55–§62), security (§63–§70), Financial AI (§71–§74), the domain model and contracts (§75–§82), operations (§83), and non-goals/future expansion (§84–§86) apply to all three.

This document describes **conceptual models only**. It does not define migrations, Eloquent schemas, table names, provider APIs, accounting-system integrations, chart-of-accounts content, or implementation code — those belong to future, separately approved implementation stories (see `docs/architecture/08_Roadmap.md`).

## 2. Why Billing is its own bounded context

Every architecture approved so far names a future Billing context and disclaims ownership of it:

| Candidate owner | Why it fails |
|---|---|
| Practice Management | `docs/adr/ADR-006-Practice-Management-Core.md`, Decision 1, explicitly refuses to absorb Billing; would make `Matter` an unbounded aggregate carrying years of ledger entries; leaves Firm-level accounting (which has no Matter) ownerless. |
| Documents | Owns rendered artifacts. `docs/adr/ADR-007-Document-Knowledge-Management.md`, Decision 4, already states Documents "may store the rendered artifact but never the commercial meaning." |
| Digital Presence | Owns the Client Portal presentation surface and the Payment Widget; explicitly "owns none of this data" (`docs/architecture/12_Website_Client_Portal_Architecture.md` §4). |
| Communications | Owns the conversation about an invoice, never the invoice (`docs/architecture/11_Communications_Hub_Architecture.md` §10). |
| Branding | Presentation only (`docs/architecture/10_White_Label_Platform_Architecture.md` §9). |
| Legal Intelligence | Owns official law, not a Firm's financial configuration. |

Each would need its own notion of "an amount owed, by whom, in what currency, and whether it has been paid" — the duplication `docs/domain/06_Laravel_Module_Blueprint.md` exists to prevent. A dedicated bounded context gives every consumer one place to ask, and gives immutability, reconciliation, segregation of duties, and Ethical Wall enforcement a single point of application. Billing is a **Supporting subdomain** around Practice Management's Core Domain: it serves the Matter without owning it, and it additionally owns Firm-level financial records that no Matter contains.

## 3. Why the three areas are separated within it

The three areas answer different questions, obey different rules, and carry different consequences when confused:

- **Client money is not Firm money.** Area B holds funds the Firm does not own. Its ledgers, bank accounts, and permissions are segregated from Area C in accounting meaning and in access control. A single undifferentiated ledger is commingling expressed as a data model.
- **A receivable is not cash, and cash is not revenue.** Area A tracks what is owed; a receipt is a cash event; revenue recognition is an Area C accounting-policy determination. Collapsing these is how a payment silently becomes revenue.
- **A billing subledger is not a general ledger.** Area A's detail (invoice lines, allocations, aging) rolls into Area C's control accounts. They must reconcile precisely because they are separate.

They are not separate bounded contexts because they share two categories of common concept — the **Foundation `Money`/`Currency` contract (PF-045)** that Billing consumes but does not own (§5.1), and **Billing-owned financial-domain concepts** such as `ExchangeRate` provenance, jurisdiction and accounting policy, authorization records, and audit provenance — and, decisively, because the reconciliations that tie a billing subledger, a trust subledger, and a general-ledger control account together would otherwise live permanently across module boundaries. One context, three enforced areas, keeps the accounting boundaries real and the reconciliation coherent. See `docs/adr/ADR-008-Billing-Trust-Accounting-Finance.md`, Alternatives considered.

## 4. Domain boundaries and ownership

| Concept | Owned by | Billing's relationship |
|---|---|---|
| Billing arrangements, rates, time/fee/expense/disbursement entries, invoices, receivables, credit/debit notes, write-offs, payments, allocations, refunds, chargebacks | **Billing — Area A** | Owner |
| Client-money bank accounts, ledgers, subledgers, transactions, transfers, authorizations, reconciliations | **Billing — Area B** | Owner, segregated from A and C |
| Chart of accounts, ledger accounts, journals, accounting periods, bank accounts/transactions/reconciliations | **Billing — Area C** | Owner |
| `Client`, `Matter`, `MatterClient`, `MatterTeam`, `PracticeArea`, `EthicalWall` | **Practice Management** | By identifier and published contract only |
| Rendered invoice/receipt/statement/tax-document/report artifacts | **Documents** | Requested and referenced; **Billing stores no bytes** |
| Client Portal invoice/payment surfaces, Payment Widget | **Digital Presence** | Served through permission-aware Billing queries |
| Invoice notices, reminders, receipts, payment conversations | **Communications** | Delivery requested; Billing owns the Invoice |
| `BrandProfile`, `PDFBrandingConfig` | **Branding** | Composed at rendering; presentation only |
| Official statutes, regulations, court decisions, translations, citations | **Legal Intelligence** | Cited; never duplicated or overridden |
| `Money`, `Currency` — the platform-wide exact-decimal money primitive | **Foundation Library (PF-045)** | **Consumed, never owned** (§5.1) |
| Actor identity, permissions, SoD assignments | **Future Identity/Security** | Consumed, never owned |
| Process orchestration | **`Workflow`** (ARCH-010, `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md`) | May orchestrate; owns no financial state |

## 5. Ubiquitous language

| Term | Meaning in this architecture |
|---|---|
| **Money** | The Foundation Library's exact-decimal amount plus explicit `Currency` (PF-045). Never a float. **Owned by Foundation, consumed by Billing** (§5.1). |
| **Billable** | Work eligible to be charged under a `BillingArrangement`; distinct from *billed*. |
| **Billed** | Included on an issued `Invoice`. |
| **Receivable** | An amount owed to the Firm arising from an issued invoice. |
| **Payment** | A received amount, in a stated currency, from a stated payer. Not revenue by itself. |
| **Allocation** | The recorded application of a payment to one or more invoices or to unapplied cash. |
| **Settlement** | A provider's transfer of funds to the Firm's account; distinct from payment capture. |
| **Client money / trust money** | Funds held for or on behalf of a client. Never the Firm's money, never revenue. |
| **Operating funds** | The Firm's own money. |
| **Posting** | Recording an immutable entry in a ledger. |
| **Reversal** | A new posted entry that negates a prior one; the prior entry remains. |
| **Adjustment** | A new posted entry correcting a prior one without negating it wholesale. |
| **Control account** | A general-ledger account whose balance must equal the sum of a subledger. |
| **Subledger** | Detailed per-client, per-Matter, or per-invoice records rolling into a control account. |
| **Effective-dated policy** | Configuration valid over a date range, so historical treatment stays reproducible. |
| **As of** | The date/time at which a balance or report is computed; always explicit. |

Jurisdiction-neutral terminology is used deliberately: **client money** and **trust accounting**, never a foreign-regime term such as IOLTA, which is a United States construct and does not describe Thai requirements.

## 5.1 The Foundation boundary: `Money` and `Currency` are not Billing's

`AGENTS.md` and `docs/domain/06_Laravel_Module_Blueprint.md` place shared technical primitives in `app/Foundation`, and the approved Sprint Plan and Engineering Backlog already reserve **PF-045 — Money** in Sprint 0.3 (Foundation Library). Money is not a billing concept — Practice Management, Documents, Digital Presence, and any future module that quotes, displays, or reports an amount needs the same representation, and two incompatible money types on one platform is a defect class, not a design choice.

Therefore:

- **The Foundation Library owns `Money` and its explicit `Currency` representation**, governed by PF-045. Exact-decimal representation, mandatory currency, and the prohibition on floating-point money are **platform-wide Foundation guarantees**, not rules this architecture invents.
- **Billing consumes that Foundation contract and must never define a second, incompatible `Money` or `Currency` type.** Neither may any other module.
- **Billing owns the financial-domain meaning layered over that primitive** — `ExchangeRate` provenance and application (§28), `TaxTreatment` and `TaxIdentifier` (§24–§26), invoice numbering (§19), allocation instructions (§30.7), authorization records (§41), beneficiary attribution (§33, §33.1), financial classification (§63), and accounting and jurisdiction policy versions (§24, §54). These are Billing's, and the three domain areas share them as Billing-owned concepts.
- **This document does not schedule, renumber, duplicate, or complete PF-045.** It records a dependency on an existing approved backlog item. PF-010 remains the current repository implementation story and PF-011 remains next.

## 6. Dependency direction

Per `docs/domain/06_Laravel_Module_Blueprint.md`, unchanged: **Interface → Application → Domain**; Infrastructure depends on Application/Domain contracts; the Domain layer never depends on Laravel, Eloquent, HTTP, provider SDKs, or accounting-system SDKs. Payment, banking, e-tax, and accounting-system integrations live behind Infrastructure-layer adapters. No provider-specific conditional logic appears outside the adapter that owns that provider.

Other modules reach Billing only through published commands, queries, and events; Billing reaches Practice Management, Documents, Communications, Branding, and Legal Intelligence only through theirs. **No direct Eloquent imports, cross-module table access, or direct writes in either direction.**

## 7. Explicit ownership and non-ownership

**Billing owns:** commercial billing records and their lifecycle; client-money/trust ledgers and balances; the Firm's general ledger, chart of accounts, and accounting periods; payment records and allocations; effective-dated Firm financial and tax *configuration*; financial audit trails and reconciliations.

**Billing does not own:** the platform-wide `Money`/`Currency` primitive (Foundation Library, PF-045 — §5.1); Clients, Matters, `MatterClient`s, Matter teams, or Ethical Wall definitions; document bytes or rendered artifacts; Client Portal identity, session, or presentation; communication threads or messages; branding configuration; official law; actor identity or role definitions; workflow orchestration.

## 8. Area A — Billing arrangements

`BillingArrangement` is the agreement governing how work on a Matter (or for a Client) is charged. **Time-based billing is one option among several, never an assumption.** Platform-seeded arrangement types, extensible per Firm:

- **Hourly** — charged at applicable rates from a `RateSchedule`.
- **Fixed fee** — an agreed amount for a defined scope, independent of time recorded.
- **Capped** — hourly up to a ceiling, with defined treatment beyond it.
- **Staged / milestone** — amounts become billable on defined events or deliverables.
- **Retainer-funded** — work drawn against client money held on trust (Area B), which becomes billable only through the trust-to-operating transfer discipline in §40.
- **Contingency / success-based, subscription, blended, and hybrid** — extensible types; the model must not assume a fixed enumeration.

Rules:

- An arrangement references a `Matter` and/or `Client` by identifier and carries its own effective dates. Changing terms creates a **new effective-dated arrangement version**; work already billed under prior terms is unaffected.
- A Matter may carry more than one arrangement over time, and different work types may be governed differently.
- **A retainer-funded arrangement never makes client money the Firm's money.** It defines the obligation against which an authorized transfer may later be made (§40).

## 9. Rate schedules and effective-dated rates

`RateSchedule` holds rates by role, individual, practice area, client, matter, or currency, each with an effective date range.

- Rate resolution for a `TimeEntry` uses the rate **effective at the work date**, not the current rate. Recording the resolved rate on the entry makes historical billing reproducible.
- A rate change is a new effective-dated entry; it never retroactively re-prices recorded work, and it never alters an issued invoice.
- Client-negotiated and discounted rates are first-class effective-dated rates, not ad-hoc line discounts, so their basis is auditable.

## 10. Time, fee, expense, and disbursement entries

Four distinct entry types, deliberately not one generic "charge":

- **`TimeEntry`** — recorded duration, work date, actor, Matter, activity classification, narrative, billable flag, resolved rate. Duration and rate are exact decimals.
- **`FeeEntry`** — a fee amount not derived from time (fixed-fee instalment, milestone amount, agreed charge).
- **`ExpenseEntry`** — a cost the **Firm** incurred and seeks to recover (courier, printing, travel). It affects the Firm's own accounts.
- **`DisbursementEntry`** — an amount paid to a **third party on the client's behalf** (court fee, official fee, expert). Its accounting and tax treatment differ from a Firm expense, and it may be funded from client money (Area B) — in which case the trust disbursement rules in §39 apply in full.

Common rules:

- Every entry references its Firm explicitly, and its `Matter`/`Client` by identifier.
- **Billable ≠ billed.** An entry may be billable, non-billable (recorded for utilization but not charged), or write-off-marked. Non-billable time is still recorded — a Firm needs it for utilization and profitability analysis.
- Entries may require **approval before invoicing** (§11). Approval state is recorded with actor and timestamp.
- An entry included on an issued invoice becomes **locked**; correcting it requires a credit note, adjustment, or reversal (§15, §17), never an edit of billed history.

## 11. Approval before invoicing

Firms differ on whether entries require review before billing. The architecture supports a configurable approval gate:

- Entries may pass through recorded review (approve, adjust, reject, defer) before becoming eligible for an invoice.
- Approval is an authorization-checked, recorded act with an actor and timestamp.
- A supervising lawyer may adjust an entry **before** it is billed; after issue, only the correction instruments apply.
- Approval configuration is Firm policy, not a platform-wide assumption.

## 12. Billing narratives and privileged information

A time-entry narrative frequently describes the substance of legal work and is therefore **potentially privileged content**, not neutral metadata.

- Narratives inherit the Matter's access rules and Ethical Wall restrictions (§65).
- Client-facing invoice detail is a deliberate disclosure decision: a Firm may issue summary-level or detail-level invoices, and the detail level is recorded, not incidental.
- On a multi-client Matter, narrative visibility resolves through the invoice audience (§18) — never by Matter membership alone.
- Narratives are restricted in exports (§69) and in AI processor eligibility (§73).

## 13. Discounts

- A discount is an explicit, recorded, attributable reduction with a reason — never a silent amount change.
- Discounts may apply at entry, line, or invoice level; each is recorded as its own fact so reporting can distinguish "billed less" from "collected less."
- A discount on an **issued** invoice is a credit note (§15), not an edit.

## 14. Invoices and invoice lines

`Invoice` is the aggregate representing a commercial demand for payment; `InvoiceLine` are its entity components, each traceable to the entries it bills.

**Lifecycle**

```text
Draft → Pending Approval → Issued → (Partially Paid ⇄ Paid) → Closed
Issued → Overdue (derived, date-based)
Issued → Disputed ⇄ Issued
Issued → Credited (partial or full, via CreditNote)
Draft / Pending Approval → Cancelled (terminal, never issued)
Issued → Voided (exceptional, authorized, auditable — never deletion)
```

- **Draft** — freely editable; not a financial record and never a tax document.
- **Pending Approval** — submitted for authorization where Firm policy requires it.
- **Issued** — **immutable** (§16). A number is assigned (§19), tax treatment is fixed at the effective policy (§24), a receivable arises (§20), and the accounting entries post (§48).
- **Partially Paid / Paid** — derived from allocations (§30.7), not a hand-set flag.
- **Overdue** — derived from due date and outstanding balance; never a stored mutable state that can drift.
- **Disputed** — a recorded client dispute; suspends dunning (§22) without altering the invoice.
- **Credited** — reduced by a credit note; the original invoice is unchanged.
- **Voided** — an exceptional, authorized, fully audited state for an invoice that should never have been issued; jurisdiction rules may require a credit note instead, so voiding is policy-gated (§24), and it is **never** deletion.
- **Closed** — settled, credited, or written off to zero; no further activity expected.

## 15. Credit notes, debit notes, and write-offs

- **`CreditNote`** — reduces an issued invoice or creates a client credit. Its own record with its own number, date, tax treatment, and reason. The referenced invoice is untouched.
- **`DebitNote`** — increases an amount owed where jurisdiction practice uses one rather than a new invoice.
- **`WriteOff`** — an authorized recognition that a receivable will not be collected. Distinct from a credit note: a credit note says "we are not charging this"; a write-off says "we charged it and will not collect it." They have different accounting and tax consequences and are never interchangeable.
- All three are additive, authorized, auditable records. None edits history.

## 16. Why issued invoices are immutable

Three independent reasons, any one sufficient:

1. **Evidentiary.** "What did we bill, when, and on what basis" must be a retrieval, not a reconstruction.
2. **Statutory.** In many jurisdictions a tax invoice may not be altered after issue; corrections use prescribed instruments.
3. **Accounting integrity.** An issued invoice has posted to the ledger and a receivable; editing it would silently desynchronize subledger and control account.

This is the same immutability discipline Constitution Article 8 applies to published legal sources and Article 18 applies to document versions, applied here to financial records.

## 17. Payments, allocation, and adjustment relationships

An `Invoice` is reduced only by: an allocated `Payment` (§30.7), a `CreditNote`, or a `WriteOff`. Never by editing an amount. A `Refund` (§30.9) and a `Chargeback` (§30.10) reverse or claw back a payment and are themselves recorded events with their own ledger consequences.

## 18. Invoice audience versus payer

Three separable concepts that a naive model collapses into one:

- **Bill-to party** — who the invoice is addressed to.
- **Invoice audience** — who may *see* the invoice and its detail. **Deny-by-default and explicit.**
- **Payer** — who actually pays. May be a third party (§21).

Rules:

- **A Primary `MatterClient` is only the default administrative/billing recipient** (`docs/architecture/13_Practice_Management_Architecture.md`, Matter Clients). It does not automatically determine the legally liable party, the audience, the payer, a trust beneficiary, or any allocation. Each is its own recorded determination.
- **Co-client isolation:** on a jointly represented Matter, one client never sees another's invoice, payment method, payment, trust balance, or transaction absent an explicit authorized audience decision. Matter membership is not a financial audience.
- Audience applies uniformly to invoice display, line detail, narratives, rendered artifacts, statements, payment history, and reminders. There is no surface where a stricter decision is bypassed by a laxer one.

## 19. Invoice numbering

- Numbering is **Firm-configurable and jurisdiction-aware** (§24), supporting sequential, date-prefixed, series-per-entity, and branch/place-of-business-scoped schemes.
- A number is assigned **at issue** and is **immutable** thereafter.
- **Uniqueness is enforced per Firm per series at assignment**; concurrent issuance must not produce a collision or a silently skipped number (§83).
- Where jurisdiction rules require unbroken sequences, gaps must be explainable — a cancelled draft never consumes a number, and a voided invoice retains its number with a recorded void.
- Credit notes, debit notes, and receipts carry their own numbering series.

## 20. Receivables and accounts-receivable aging

- `Receivable` represents an outstanding amount arising from an issued invoice, reduced by allocations, credit notes, and write-offs.
- **Balances are derived** from those immutable records, never stored as a directly editable field (§46).
- **Aging** (current / 30 / 60 / 90+ or Firm-configured buckets) is a read-model computed **as of** an explicit date, per currency. Aging is never recomputed retroactively with today's exchange rate (§28).
- Aging is reported per Firm, and may be broken down per client, Matter, responsible lawyer, or practice area — always subject to the access rules in §63–§66.

## 21. Third-party payers

- A third party (an insurer, employer, parent company, or funder) may be recorded as payer for an invoice.
- **Paying an invoice grants no Matter access, no Client Portal access, and no visibility into anything beyond the payment transaction itself.** A payer is not a client.
- Third-party payment does not change who the client is, who is legally liable, or the confidentiality of the underlying work.
- A payment link issued to a third party is confidentiality-scoped (§30.11): it exposes the payable amount and reference needed to pay, never Matter detail or narratives.

## 22. Dunning and collections boundaries

- Billing owns **collection state**: what is overdue, what stage of reminder applies, what promise-to-pay or hold has been recorded, and what escalation policy applies.
- Billing does **not** own delivery. Reminder and notice delivery goes through Communications' published contracts (§58); a delivery failure never alters invoice or receivable state (§83).
- Dunning suspends automatically on a recorded **dispute** (§14) and on any Firm-configured hold.
- Advanced collections (scoring, automated escalation ladders, agency handoff) is named as future expansion (§86), not architected here.

## 23. Engagement and Matter closure independence

- **A Matter reaching Closed or Archived does not close its financial records.** Trailing invoices, receivables, credit notes, refunds, and trust balances continue under Billing's own lifecycles, exactly as `docs/architecture/13_Practice_Management_Architecture.md` §3 anticipates.
- Conversely, a zero balance does not close a Matter. The two lifecycles are independent and neither gates the other.
- **A Matter must not be treated as fully concluded while client money remains held for it**; residual trust balances require an explicit disbursement or authorized treatment decision (§42).

## 24. Tax and statutory financial documents — policy model

Tax treatment is modeled as **effective-dated, jurisdiction-scoped policy**, never as hardcoded values.

`TaxPolicy` (effective-dated, per jurisdiction) may govern: applicable tax types and their treatment; taxable/exempt/zero-rated/out-of-scope classification per service or disbursement type; withholding treatment; rounding rules; required document particulars; numbering rules; place-of-business requirements; and retention periods.

**Mandatory constraints:**

- **No tax percentage is stated, implied, or hardcoded anywhere in this architecture.**
- **No compliance claim is made.** The platform provides configuration and record-keeping; it does not certify that a Firm's tax treatment is correct.
- **Every jurisdiction-specific rule must be backed by an official primary source and approved by an authorized human legal or accounting owner before implementation.** Consistent with Constitution Articles 1–4, official Thai-language law and regulator material is authoritative for Thailand; English translations are reference material only and carry the mandatory disclaimer.
- Tax treatment is **fixed at issue** using the policy effective on the document date. A later policy change never retroactively alters an issued document (§25).

## 25. Statutory financial documents

Distinct document kinds with distinct meanings, numbering, and rules — never one generic "invoice PDF":

- **Tax invoice** — where jurisdiction rules define one, with its own required particulars.
- **Receipt** — acknowledging payment received; distinct from an invoice.
- **Credit note / debit note** — the prescribed correction instruments (§15).
- **Withholding tax certificate or equivalent**, where applicable.
- **Statements of account.**

Each carries: issuing Firm identity and tax identifiers, counterparty identity and tax identifiers, document number and series, document date, currency, amounts, applicable tax presentation, and any jurisdiction-mandated particulars — all resolved from the effective policy. **Rendered artifacts are stored by Documents (§57); Billing owns the record and its meaning.**

## 26. Tax profiles

- **Firm tax profile** — legal name, tax identifiers, registration status, places of business, applicable jurisdictions, effective dates.
- **Client tax profile** — counterparty tax identifiers, status, and any treatment relevant to invoicing and withholding.

Both are effective-dated so historical documents remain reproducible against the profile in force at issue.

## 27. E-tax and e-invoice integration boundary

Where a jurisdiction operates an electronic tax-invoice or e-invoice regime, integration is an **Infrastructure-layer adapter** behind a provider-neutral contract. **No provider is selected, endorsed, or configured here.** Submission results, acknowledgements, and reference identifiers are recorded as facts against the document; a submission failure never alters the underlying financial record (§83).

## 28. Currency and exchange rates

- `Money` is the **Foundation Library contract (PF-045)**: an exact decimal amount plus an explicit `Currency`. Floating-point money is prohibited platform-wide. Billing consumes this contract and defines no money type of its own (§5.1).
- Every multi-currency transaction records: **transaction currency, transaction amount, Firm/base currency, base amount, the effective exchange rate, the rate source, and the rate timestamp.**
- **A historical conversion is never silently recalculated with a newer rate.** Reports "as of" a past date reproduce the rates recorded then.
- Rounding is an explicit, policy-defined rule (§24), applied consistently and recorded; rounding differences are posted, not absorbed silently (§83).
- Gains and losses on exchange are Area C accounting outcomes (§53), never adjustments to an issued invoice.
- **Currencies are never mixed within a balance or a journal without explicit conversion provenance.** A journal balances per currency (§48).

## 29. Record retention

Financial-record retention is Firm- and jurisdiction-aware effective-dated policy (§24), coordinated with — not duplicating — Documents' retention and legal-hold rules (`docs/architecture/14_Document_Knowledge_Management_Architecture.md` §20). A legal hold on a financial artifact or its underlying record blocks destructive processing exactly as it does for documents. **No universal retention period is asserted.**

## 30. Payments

### 30.1 Provider-neutral initiation

`PaymentIntent` is the provider-neutral model of "an attempt to collect a stated amount, in a stated currency, against a stated obligation." It records the requesting Firm, target invoice(s) or purpose, amount, currency, payer reference, method type, status, and provider correlation identifiers. **Domain and Application layers work only against this model**, never a provider's own object.

### 30.2 Provider adapters

Each payment provider is an Infrastructure-layer adapter implementing one published contract: initiate, capture, cancel, refund, query status, verify webhook, and expose capabilities. **No provider is selected or configured by this architecture.** Provider-specific payloads and SDKs never enter Domain or Application code — the same discipline `docs/architecture/11_Communications_Hub_Architecture.md` applies to `ChannelAdapter`.

### 30.3 Distinct payment states

Authorization, capture, failure, cancellation, settlement, allocation, refund, chargeback, and reconciliation are **separate recorded facts**, never one mutable status:

```text
PaymentIntent:  Created → Authorized → Captured → (Settled) → Reconciled
                Created → Failed | Cancelled | Expired
Captured →      Refunded (partial/full) | ChargedBack
```

- **Payment status** describes the transaction with the provider.
- **Settlement status** describes whether funds reached the Firm's account.
- **Allocation status** describes whether the received amount has been applied.
- A captured payment that has not settled is not cash in the bank; a settled payment that has not been allocated is unapplied cash (§30.8).

### 30.4 Payment methods

Stored as **tokenized or provider-held references only**. OneLegalPro never stores, logs, or transmits raw card numbers, CVV data, online-banking secrets, or reusable bank credentials. A stored method is a token plus safe display metadata (brand, last digits, expiry) and nothing more.

### 30.5 Webhook verification

Every provider callback is **authenticated** (signature/secret verification through the adapter) before any processing. An unverified callback is rejected and recorded as a security event, never processed optimistically.

### 30.6 Idempotency, replay, duplication, and ordering

- Every inbound provider event carries a provider reference used for **idempotent** processing: redelivery never produces a second payment, allocation, or ledger entry.
- **Replay resistance** is required: a previously processed or expired event is rejected.
- **Out-of-order delivery is expected**, not exceptional. A "settled" event arriving before "captured" must reconcile to a consistent state rather than corrupt one.
- Outbound initiation carries an idempotency key so a retried request never double-charges.

### 30.7 Allocation

`PaymentAllocation` records how a payment is applied — to one invoice, across several, or to unapplied cash. Allocation is an **explicit recorded act**, not an implicit side effect of receipt, and it is reversible only by a further recorded allocation change, never by editing history. Partial payment is normal and fully supported.

### 30.8 Overpayment and unapplied cash

An overpayment or unmatched receipt becomes **unapplied cash** with an explicit owner and purpose. It is never silently treated as revenue, never silently offset against an unrelated Matter or client, and never absorbed. Its resolution (allocation, refund, or — where genuinely client money — transfer into Area B under §36) is a recorded, authorized decision.

### 30.9 Refunds

`Refund` reverses value to the payer. It requires authorization, records its reason and originating payment, posts its own ledger consequences, and never edits the original payment. A refund of client money follows Area B rules (§39) in addition.

### 30.10 Chargebacks

`Chargeback` records a payer-initiated reversal through the provider. It is recorded as its own event with its own accounting consequences (including provider fees), and it never rewrites the original payment or the invoice.

### 30.11 Client Portal and Payment Widget integration

The Payment Widget (`docs/architecture/12_Website_Client_Portal_Architecture.md` §8) initiates provider-mediated actions through Billing's published contracts and **owns no financial state**. Digital Presence performs no allocation, no ledger posting, and no audience resolution of its own.

**Payment-link confidentiality:** a payment link exposes only what is needed to pay — payable amount, currency, and reference. It never exposes Matter detail, narratives, other invoices, other clients, or balances, is scoped and expiring, and is resistant to enumeration (§68).

### 30.12 Failure and retry

Provider timeouts, declines, and outages are expected. Failed initiation never creates a payment record implying receipt; retries are idempotent; and an indeterminate provider result resolves through status query and reconciliation rather than optimistic assumption (§83).

### 30.13 The platform is not a financial institution

**OneLegalPro is not a bank, escrow provider, custodian, or regulated payment service, and this architecture makes no such claim.** Funds move between the payer, the provider, and the Firm's own bank accounts. Any future design in which OneLegalPro directly accepts, holds, transfers, or settles funds on behalf of another party requires **separate legal, regulatory, security, and architecture approval**.

## 31. Area B — Client Money / Trust Accounting: purpose

A Firm frequently holds money that is not its own: advance funds for costs, settlement proceeds, funds held pending completion, retainer balances. **Client money means money held by the Firm that is not the Firm's own money.** The Firm holds it subject to fiduciary and professional-conduct obligations, and mishandling it is a professional-discipline matter rather than a bookkeeping error.

**Who is beneficially entitled to a given balance is a separate, explicitly recorded fact.** Held funds may be beneficially owned by the client on the Matter, by an identified third-party beneficiary (a counterparty awaiting completion, a lienholder, a person entitled to settlement proceeds), or by a party not yet determined pending an authorized entitlement decision. **This architecture does not assume that every balance is beneficially owned by the client named on the Matter** — see §33.1. What is invariant is the negative: it is not the Firm's money, and it does not become the Firm's money except through the authorized transfer in §40.

**Jurisdiction-neutral terminology is used deliberately.** The requirements applying to a Thai law firm are set by Thai law and professional regulation; no foreign regime (such as the United States IOLTA construct) is assumed to apply. Specific controls — permitted account types, interest treatment, reconciliation frequency, reporting — are **jurisdiction policy** (§24), backed by an official primary source and approved by an authorized human owner.

## 32. Client-money conceptual model

- **`ClientMoneyBankAccount`** — a real bank account holding client money, **segregated from Firm operating accounts**, with its own currency, jurisdiction, and permissions.
- **`ClientMoneyLedger`** — the append-only ledger for one client-money bank account. Its balance is derived from entries.
- **`ClientMoneySubledger`** — the per-client and, where applicable, per-Matter **accountable** position within that ledger. The sum of subledgers must equal the ledger, which must reconcile to the bank (§43). Accountability (whose subledger holds the balance) and beneficial entitlement (who is entitled to it) are distinct recorded facts — see §33.1.
- **`ClientMoneyTransaction`** — an immutable receipt, disbursement, or adjustment entry.
- **`ClientMoneyTransfer`** — a movement between subledgers, between client-money accounts, or from client money to operating funds (§40).
- **`ClientMoneyAuthorization`** — the recorded authorization for a transaction or transfer, including the maker/approver identities where segregation of duties applies (§41).
- **`ClientMoneyReconciliation`** — a periodic three-way reconciliation record (§43).

## 33. Required attribution on every client-money movement

Every receipt, disbursement, transfer, refund, and adjustment records: **Firm; account; currency; amount; `BeneficiaryAttribution` (§33.1); Matter attribution where applicable; purpose; source; authorization; and audit provenance** (actor, timestamp, and the instruction or document relied on).

A movement missing any of these is invalid. "Unexplained transaction" is not a state the model permits.

## 33.1 Beneficial entitlement is recorded, never inferred

`BeneficiaryAttribution` records **who is beneficially entitled** to an amount. It supports:

- **The accountable client** — the `Client` whose subledger the amount sits in, referenced by identifier.
- **An authorized third-party beneficiary reference**, where the beneficial owner is someone other than that client.
- **Optional Matter attribution**, referenced by identifier.
- **Purpose** — why the money is held (advance on costs, settlement proceeds, funds pending completion, retainer).
- **`EntitlementBasis`** — the recorded basis for the attribution: the instruction, undertaking, court order, settlement agreement, or engagement term relied on, or an explicit *pending determination* state where entitlement is genuinely not yet resolved.

**Entitlement is never inferred.** It must not be derived merely from the depositor, the payer, the Primary `MatterClient`, or Matter membership. Any of those may in fact be the beneficiary — but that must be a recorded determination, not an assumption the model makes on the Firm's behalf. Where entitlement is unresolved, the balance is held with an explicit pending-determination state (compare the unidentified-receipt handling in §36) and is neither disbursed nor transferred until an authorized human resolves it.

**Attribution and accountability are distinct facts, and both are kept.** The client/Matter subledger structure in §32 remains exactly as specified — every balance still sits in an accountable subledger that reconciles to the ledger and the bank (§43). Beneficial entitlement is recorded *alongside* that accountability, not instead of it: a balance may sit in Client A's subledger while an identified third party is beneficially entitled to part or all of it, and both facts are visible.

**Reference confers no access.** A third-party beneficiary or payer named in a financial record gains **no** Matter access, Client Portal access, invoice visibility, or document access merely by being referenced (§21, §65). Being named in an attribution is not an authorization.

**Every prohibition in §34 applies unchanged**, whoever is beneficially entitled: no Firm appropriation, no cross-client funding, no negative balance, no unexplained transaction, no silent commingling, no unauthorized cross-Matter transfer, and no AI-controlled movement.

## 34. Client-money invariants

These are absolute:

- **No direct balance editing.** Balances are derived from immutable entries only.
- **No unexplained transaction.** Every entry carries the attribution in §33.
- **No negative client or Matter balance.** A disbursement exceeding a client's held funds is rejected — one client's money can never fund another's shortfall.
- **No use of one client's money for another.**
- **No silent commingling with Firm funds.** Client money and operating funds are segregated in accounts, ledgers, and permissions.
- **No AI authorization.** AI may never initiate, approve, or release any client-money movement (§74).
- **No payment becomes revenue merely because it was received.** Receipt into client money is not income; recognition is an Area C decision (§52).
- **No trust-to-operating transfer without a valid obligation and explicit human approval** (§40).
- **No invisible reconciliation discrepancy.** Differences remain visible until explained (§43).
- **No unauthorized cross-Matter or cross-client transfer** (§42).
- **Most-restrictive Ethical Wall outcome applies** to every client-money record (§65).

## 35. Balanced, immutable journal behavior

Client-money entries are **balanced and append-only**: every movement records both sides (account and subledger attribution) so the ledger reconciles by construction. A correction is a **reversal or adjusting entry**, never an edit or deletion. Posted history is preserved in full, including entries later reversed.

## 36. Receipts into client money

- A receipt is attributed to an accountable client and, where applicable, Matter, and carries a `BeneficiaryAttribution` with its purpose and `EntitlementBasis` recorded (§33.1). **The payer is not assumed to be the beneficiary, and the Primary `MatterClient` is not assumed to be entitled** — either may be, but only as a recorded determination.
- A receipt of mixed funds (partly the Firm's, partly held for another) is recorded with an explicit split decision, not defaulted; where jurisdiction policy requires, the whole receipt enters client money and the Firm's portion is transferred out under §40.
- An unidentified receipt is held as **unattributed client money** with an explicit investigation state — never allocated by guess and never taken to revenue.
- A receipt whose accountable client is known but whose **beneficial entitlement is not yet resolved** is held with an explicit pending-determination `EntitlementBasis` (§33.1) and is neither disbursed nor transferred until an authorized human resolves it.

## 37. Interest and bank charges

Whether interest on client money accrues to the client, to the Firm, or elsewhere, and how bank charges are borne, is **jurisdiction policy and Firm configuration** (§24), effective-dated, and requires an approved professional determination. **This architecture asserts no treatment.** Whatever the policy, the resulting movements are ordinary client-money transactions subject to §33–§35.

## 38. Dormant and unclaimed balances

Long-held balances with no activity, and balances whose beneficiary cannot be located, are subject to jurisdiction-specific rules. This is named as **future policy work**: the architecture requires that such balances remain visible, attributed, and non-absorbable, and that any prescribed treatment be implemented as approved jurisdiction policy rather than invented here.

## 39. Disbursements and refunds from client money

- A disbursement requires: sufficient attributed funds, a recorded purpose, a recorded payee, authorization (§41), and Ethical Wall clearance for the Matter.
- **Insufficient funds is a hard rejection**, never an overdraft against another client (§34).
- A refund or payment out of client money to the party recorded as beneficially entitled (§33.1) follows the same discipline and is recorded as its own transaction. Where entitlement is in a pending-determination state, no disbursement proceeds until an authorized human resolves it.
- A `DisbursementEntry` in Area A (§10) that is funded from client money produces a corresponding Area B transaction; the two are linked, not merged.

## 40. Trust-to-operating transfer

The most sensitive movement in the platform. Required conditions, all of them:

1. **An eligible commercial obligation exists** — an issued invoice or another Firm-configured, jurisdiction-permitted basis. Not "the Firm needs funds."
2. **Sufficient attributed funds** are held for that specific client and Matter.
3. **Explicit human authorization**, recorded with actor and timestamp, subject to segregation of duties (§41).
4. **An auditable transfer record** linking the client-money transaction, the operating receipt, and the obligation relied on.
5. **Ethical Wall clearance** for the Matter.

**AI may never initiate, approve, or release a trust-to-operating transfer** (§74). A transfer without a valid obligation, or without explicit approval, is a defect — not a configurable convenience.

## 41. Segregation of duties

- Configurable **maker**, **approver**, and **reconciler** roles, assigned from actor identity supplied by a future Identity/Security context (§60).
- Where policy requires independent review, **the creator of a high-risk client-money transaction must not silently self-approve it.** Where a Firm is too small to separate roles, an explicit, recorded policy exception is required — the control is never simply absent.
- Thresholds (amount, transaction type, cross-client movement) are Firm-configurable policy.
- Every maker, approver, and reconciler action is an auditable event.

## 42. Cross-Matter and cross-client transfers

**Deny-by-default.** A transfer between Matters, or between clients, requires: explicit authority for the movement, referential integrity (both sides valid and attributed), authorization under §41, and Ethical Wall checks on both sides with the **most restrictive outcome applying**. Absent all four, the transfer is rejected.

## 43. Three-way reconciliation

`ClientMoneyReconciliation` compares, **as of** an explicit date and per currency:

```text
Bank statement balance
   ⇅
Client-money control account (Area C general ledger)
   ⇅
Sum of individual client/Matter subledgers
```

- All three must agree. **Any difference remains visible as an open reconciling item until explained** — it can never be cleared by editing history, adjusting a balance, or deleting an entry.
- Explaining a difference produces a recorded reconciling item or an authorized adjusting entry (§35), both auditable.
- Reconciliation frequency and reporting are jurisdiction policy and Firm configuration (§24).
- A completed reconciliation is itself an immutable record with its actor, timestamp, and outcome.

## 44. Area C — Firm Finance and Accounting: purpose

Area C is the Firm's own double-entry accounting: the general ledger into which billing and trust activity post, against which financial statements are produced, and through which bank activity is reconciled.

## 45. Chart of accounts and ledger accounts

- **`ChartOfAccounts`** — the Firm's account structure. Platform-seeded templates may exist; the content is **Firm configuration**, not platform-mandated, and this architecture prescribes no specific accounts.
- **`LedgerAccount`** — an account with a type (asset, liability, equity, income, expense), currency handling, and status. Effective-dated so structure changes do not corrupt history.
- **Control accounts** link Area C to the subledgers in Areas A and B (§51).
- **The client-money control account is distinct from every operating account** and reconciles to Area B (§43).

## 46. Journals and posting

- **`JournalEntry`** with **`JournalLine`** components; every posting is double-entry.
- **Every posted journal balances per currency.** Multi-currency entries carry conversion provenance (§28); currencies are never netted implicitly.
- **Posted entries are immutable.** Corrections are **reversals** (a new entry negating the original) or **adjustments** (a new entry correcting it), never edits or deletions.
- Every journal records its source: the originating business event (invoice issue, payment allocation, trust transfer, bank transaction) and its identifiers, so any ledger line traces back to what caused it.
- **Balances are always derived** from posted lines. There is no directly mutable balance anywhere in the model.

## 47. Accounting periods

`AccountingPeriod` has explicit states:

```text
Open → Closing → Closed → (Reopened, exceptionally) → Closed
```

- **Open** — normal posting permitted.
- **Closing** — restricted posting during close procedures.
- **Closed** — no ordinary posting. A closed-period posting requires **authorization, a recorded reason, and audit history**, or is directed to the current open period per Firm policy.
- **Reopened** — exceptional, requiring authorization and a recorded reason, and retaining full history of the reopening.
- Period state never erases or rewrites posted entries.

## 48. What posts, and when

Indicative, policy-configurable posting points — the architecture fixes the discipline, not a Firm's specific accounts:

| Business event | Ledger consequence |
|---|---|
| Invoice issued | Receivable and income/tax consequences per policy |
| Payment captured/settled | Cash/clearing and receivable consequences |
| Payment allocated | Receivable relief per allocation |
| Credit note issued | Reversal consequences per policy |
| Write-off authorized | Receivable relief and expense/loss consequences |
| Client money received | Client-money asset and corresponding client-money liability — **never income** |
| Trust-to-operating transfer | Client-money reduction and operating receipt against the obligation |
| Refund / chargeback | Reversal consequences, including provider fees |
| Bank transaction reconciled | Clearing-account relief |

**A payment never posts to income merely because it was received** (§52). **Client money posts as an asset with an equal and opposite liability to the client — never as Firm revenue** (§34).

## 49. Bank accounts, transactions, and reconciliation

- **`BankAccount`** — a Firm bank account, explicitly typed as operating or client-money (§32), never ambiguous.
- **`BankTransaction`** — an imported or recorded bank line, an **external signal**, not an authoritative accounting entry until matched and posted.
- **`BankReconciliation`** — the periodic matching of bank transactions to posted ledger entries, **as of** an explicit date, with unmatched items remaining visible.
- **No external feed is authoritative merely because it is external.** The platform's ledger is authoritative; the bank record is reconciled against it.

## 50. Tax payable and receivable

Tax balances arising from invoicing, withholding, and payments are ordinary ledger accounts governed by effective-dated tax policy (§24). **No rate, filing obligation, or compliance outcome is asserted here.**

## 51. Subledger reconciliation

Structurally required, all **as of** an explicit date and per currency:

| Subledger | Reconciles to |
|---|---|
| Accounts-receivable detail (Area A) | AR control account |
| Client-money subledgers (Area B) | Client-money control account **and** the bank (§43) |
| Payment-provider settlements | Clearing/settlement control account |
| Bank transactions | Cash accounts (§49) |

Any difference is a **visible reconciling item**, never a silently absorbed adjustment.

## 52. Revenue recognition policy boundary

Recognition is an **accounting-policy determination**, effective-dated and Firm-configured, requiring an authorized professional decision. It is explicitly **not** implied by cash receipt, by invoice issue alone, or by trust receipt. This architecture defines the boundary and the provenance requirement; it prescribes no recognition standard.

## 53. Financial statements and management reports

- Statements and reports are **read-model projections** over posted ledger entries, computed **as of** an explicit date, in an explicit currency, under an explicit accounting-policy version. Never a second source of truth.
- A report reproduced for the same "as of" date and policy version must produce the same result — this is why conversions are never retroactively recalculated (§28) and posted entries are never edited (§46).
- Management reporting (profitability, realization, utilization, aging) is likewise derived, and always subject to the access rules in §63–§66.

## 54. Accounting-policy versioning

Chart-of-accounts structure, posting rules, recognition policy, rounding, and tax treatment are all **effective-dated and versioned**, so a historical report can state which policy produced it. Policy changes are authorized, recorded, and never retroactively applied to closed periods without the controls in §47.

## 55. Cross-context integration — Practice Management

- Billing references `Client`, `Matter`, `MatterClient`, and `MatterTeam` **by identifier through published contracts only**; it never owns, copies, or caches-as-authoritative any of them.
- **`CheckEthicalWallAccess` is the sole authority** for Matter-restriction decisions affecting financial records (§65).
- Billing publishes events carrying identifiers and safe metadata for the Matter Timeline and the Matter Dashboard's Billing panel (`docs/architecture/13_Practice_Management_Architecture.md`); Practice Management projects them without copying financial content.
- **Practice Management never owns invoices, payments, or billing rates** — exactly as its own architecture states — and Billing never owns Matter lifecycle, membership, or wall definitions.
- Matter closure and financial closure are independent (§23).

## 56. Cross-context integration — Documents

- **Documents owns every rendered financial artifact** — invoice, receipt, statement, tax document, report — as an immutable `DocumentVersion`, per `docs/adr/ADR-007-Document-Knowledge-Management.md`, Decision 4.
- **Billing stores no document bytes.** It requests rendering through Documents' published contracts and holds a reference.
- **Billing owns the commercial meaning and lifecycle**; the artifact depicts a record, it is not the record. Archiving or superseding an artifact never changes financial state, and a financial correction produces a *new* artifact rather than mutating an existing one.
- A **rendering failure never rolls back or rewrites an issued financial record** (§83).
- Client Portal delivery of a financial artifact uses Documents' authorization-gated, short-lived delivery, and the audience decision is Billing's (§18).

## 57. Cross-context integration — Digital Presence and Client Portal

- The Client Portal surfaces invoices and payments through **permission-aware Billing queries**, receiving already-authorized results — never a raw list to filter.
- **Digital Presence performs no audience resolution, no allocation, and no ledger posting**, and owns no financial state.
- The **Payment Widget** initiates provider-mediated actions through Billing contracts (§30.11).
- Payment links are confidentiality-scoped, expiring, and enumeration-resistant (§68).

## 58. Cross-context integration — Communications

- Communications owns **delivery and conversation history** for invoice notices, reminders, receipts, and payment discussions.
- A `CommunicationLink` may reference an `Invoice` (`docs/architecture/11_Communications_Hub_Architecture.md` §9); **Communications never owns the Invoice or its state.**
- **A delivery failure never alters invoice, receivable, or ledger state** (§83); it is a visible delivery failure, not a financial event.
- Access is not inherited from the link: reading a thread does not authorize the invoice, and an invoice restricted by an Ethical Wall is not readable through its notice.

## 59. Cross-context integration — Branding and Legal Intelligence

- **Branding** composes presentation onto financial artifacts through the Branding Resolver. It **never** changes amounts, tax disclosures, mandatory notices, ledger meaning, or financial status — Constitution Article 11 applied to financial documents.
- **Legal Intelligence** owns official law. A jurisdiction tax or client-money policy may **cite** a `LegalSource` as its basis; Billing's effective configuration is a Firm's authorized professional determination, **never a second source of authoritative law**, and is never presented as such (Constitution Articles 2 and 7).

## 60. Cross-context integration — future Identity/Security and Workflow

- A **future Identity/Security context** owns actor identity, authentication, permissions, and segregation-of-duties role assignments. Billing **consumes** these; it defines financial permissions in terms of them but never owns identity.
- **`Workflow`** (ARCH-010, `docs/architecture/18_AI_Copilot_Workflow_Automation_Architecture.md`) may orchestrate billing processes (approval routing, dunning ladders, month-end sequences) through published contracts. **It owns no financial state and can perform no action Billing's own authorization would refuse.** Client-money movement, invoice issuance/voiding, rate/tax changes, journal posting, payment allocation, write-offs, and reconciliation remain categorically non-delegable to AI regardless of any Workflow automation policy.

## 61. Integration rules common to all contexts

- Published commands, queries, and events only. **No direct Eloquent imports, cross-module table access, or direct writes**, in either direction.
- **No storage paths, provider credentials, or raw financial payloads cross a module boundary.**
- Consumers receive identifiers and authorized projections, never raw ledgers.

**Dependency unavailability is not uniform — authorization outages and presentation outages are treated oppositely.**

- **A Practice Management outage fails closed.** Practice Management is the sole authority for Matter validation, Matter authorization, `MatterClient` audience resolution, and `CheckEthicalWallAccess` (§55, §65). When it is unavailable, **every new operation requiring any of those decisions is rejected or left explicitly pending** — reads and state-changing operations alike. Billing must **never** approximate an authorization decision, infer one, or fall back on a stale cached Ethical Wall result. Availability never outranks authorization, Ethical Walls, or trust-account safeguards.
- **A Documents, Communications, or Digital Presence outage must never rewrite or roll back an already issued or posted Billing record.** Rendering, delivery, and integration work queues and retries; the financial record stands and the failure is visible (§56, §58, §83).
- **Outbound events may wait** in the platform's approved outbox/event mechanism without affecting recorded financial state.

The precise fail-closed scope, and the narrow set of locally valid operations that may continue, are specified in §83.

## 62. What Billing must never do

Never own Clients/Matters/`MatterClient`s/Ethical Walls; never store document bytes; never render or own portal presentation; never own communication threads; never own branding; never assert official law; never define actor identity; never treat a provider status as a posted entry; never mutate posted history; never use floating-point money; never mix currencies without conversion provenance; never let AI post, move, approve, or reconcile.

## 63. Security — Firm isolation and least privilege

- **Every financial record belongs to exactly one Firm.** Firm identity is explicit on every record and enforced at the **application and repository** layers, never by global query scopes alone (`docs/domain/06_Laravel_Module_Blueprint.md`).
- Financial permissions are **least-privilege and role-derived**: viewing a Matter does not imply viewing its invoices; viewing invoices does not imply viewing client money; viewing client money does not imply authorizing movements; and none of them implies general-ledger access.
- **Financial-data classification** distinguishes, at minimum: billing narratives (potentially privileged), client-money records (fiduciary), Firm accounting (commercially sensitive), and payment metadata (regulated). Classification informs handling and export rules but **never substitutes for an authorization check**.

## 64. Security — deny is total

A caller denied access at any required boundary receives **no balance, amount, invoice metadata, payment metadata, trust metadata, report, aggregate, search hit, or confirmation that a restricted record exists.** Existence disclosure is disclosure. Aggregates and counts must not leak the presence of records the caller cannot see, and authorization failures **fail closed**.

## 65. Security — Matter authorization, Ethical Walls, and co-client isolation

- Matter-linked financial records inherit Matter access rules, and **Ethical Wall authorization comes only from Practice Management's `CheckEthicalWallAccess`** (Constitution Article 17). Billing never implements or approximates wall logic.
- Where a financial record touches more than one restricted Matter, the **most restrictive outcome applies**.
- Billing-level controls may **narrow** Matter-derived access; they may never widen it.
- **Co-client isolation:** one client on a jointly represented Matter never sees another's invoice, payment method, payment, trust balance, or transaction absent an explicit authorized audience decision (§18). This is a professional-responsibility requirement, not a preference.
- Wall denials on financial records are auditable events.

## 66. Security — invoice audience and privileged narratives

The audience decision in §18 governs **every** surface uniformly: listing, detail, line narratives, rendered artifacts, statements, payment history, reminders, exports, and AI context. Privileged billing narratives are additionally restricted in client-facing detail, in exports, and in AI processor eligibility (§73).

## 67. Security — maker/checker, audit, and break-glass

- **Maker/checker** controls (§41) are auditable on both sides.
- **Every consequential financial action is an auditable event**: entry creation and approval, invoice issue and void, credit/debit note, write-off, payment capture/allocation/refund/chargeback, every client-money movement and authorization, journal posting and reversal, period open/close/reopen, reconciliation completion, policy change, export, and every denied access attempt against a restricted record.
- Audit records are **append-only** and carry Firm and actor identity, with human and AI actors distinctly identified.
- **Break-glass access** requires a recorded justification, produces its own distinctly auditable event, and is never silent — the same discipline Practice Management applies to Ethical Wall emergency override.

## 68. Security — payment, secret, and endpoint handling

- **Tokenized/provider-held payment references only.** No raw card numbers, CVV, online-banking secrets, or reusable bank credentials are stored, logged, or transmitted through platform code.
- Provider and banking credentials are **secrets**, never plain configuration, and are handled only inside Infrastructure adapters.
- **Webhook authenticity** is verified before processing (§30.5); **idempotency and replay prevention** are mandatory (§30.6).
- **Enumeration resistance**: invoice numbers, payment links, and reference identifiers must not be guessable into disclosure; a valid identifier alone is never sufficient authorization.
- **Rate limiting** applies to payment initiation, payment-link access, and public-facing financial endpoints.

## 69. Security — exports, anomaly detection, and incident investigation

- **Exports are authorized, audience-resolved, audited, and classification-aware.** A privileged narrative or a restricted Matter's records never appear in an export the requester is not authorized to receive.
- **Suspicious-activity and anomaly flags** (unusual disbursement patterns, round-number anomalies, out-of-hours authorizations, repeated failed payments, reconciliation drift) are surfaced for human review. They are **signals, never automatic actions**, and AI-generated flags are advisory only (§74).
- **Incident investigation** is supported by append-only audit trails sufficient to reconstruct who did what, when, under what authorization, and against which records.

## 70. Security — retention and deletion boundaries

Financial records are frequently subject to statutory and professional retention obligations that **override an ordinary deletion request** (§29). Deletion, where permitted at all, is a governed, authorized, audited path that never destroys the audit fact that a record existed — the same discipline Constitution Article 19 establishes for documents. **A legal hold blocks destructive processing entirely.**

## 71. Financial AI — governing principle

Financial AI is **advisory only**. It never becomes the accounting calculator, the ledger, or an authorizing party. This extends Constitution Article 6 and `docs/architecture/05_AI_Architecture.md` to the financial domain, where an error is not a bad suggestion but a misstated balance or a misdirected payment.

## 72. Financial AI — required output properties

Every financial AI output must carry:

- **Source record identity and version** — which invoice, entry, ledger line, or policy version it drew on.
- **Currency**, explicitly, for every amount referenced.
- **"As of" timestamp** — balances and aging are meaningless without one.
- **Provenance and model identity**, plus prompt/output audit metadata where permitted.
- **Confidence and explicit uncertainty**, surfaced rather than smoothed away.

## 73. Financial AI — context and processor rules

- **Firm filtering and permission filtering happen before any financial content enters model context** — never after retrieval, and never repaired after generation, consistent with Constitution Article 21.
- **No cross-Firm context, caching, evaluation, or training use.** One Firm's financial data never becomes another's context.
- **No privileged billing narrative is sent to an unapproved processor.** Processor eligibility is an access-control decision, not an infrastructure convenience.
- Retrieval preserves record identity, version, currency, "as of" date, and the access decision that permitted it.

## 74. Financial AI — permitted and prohibited actions

**AI may:** suggest narratives and time-entry descriptions; suggest billing classifications; flag anomalies; suggest collection priorities; propose reconciliation candidates; produce forecasts and analyses; and **explain** an authoritative deterministic calculation.

**AI may never, without explicit human authorization:** issue or void invoices; alter rates; post journals; allocate payments; write off balances; move client money; approve disbursements; release refunds; close or reopen accounting periods; reconcile accounts; change tax treatment; or expose restricted financial data.

**Deterministic calculation rule:** all authoritative totals, balances, tax amounts, allocations, currency conversions, and aging are computed **deterministically outside the language model**. AI may explain a computed result; **it may never produce one that is stored, posted, presented as authoritative, or relied on.** Hallucinated totals, exchange rates, tax treatments, and account balances are prevented by construction — the model is never asked to be the arithmetic.

## 75. Aggregates

**Area A**

- **`BillingArrangement`** — the effective-dated charging agreement (§8).
- **`RateSchedule`** — effective-dated rates (§9). Separate because rates change on their own cadence and are shared across arrangements.
- **`TimeEntry`, `FeeEntry`, `ExpenseEntry`, `DisbursementEntry`** — independent aggregates. Each has its own lifecycle (record → approve → bill → lock) and is created, corrected, and queried independently; nesting them inside `Invoice` would make invoicing a precondition for recording work.
- **`Invoice`** — aggregate root owning `InvoiceLine` entities. The issue transition is exactly the invariant boundary the aggregate protects (§14, §16).
- **`CreditNote`, `DebitNote`, `WriteOff`** — independent aggregates. Each is its own authorized instrument with its own number, date, tax treatment, and audit trail; they are not states of `Invoice`.
- **`Payment`** — aggregate root owning `PaymentAllocation` entities; a payment exists independently of any invoice (unapplied cash, §30.8).
- **`Refund`, `Chargeback`** — independent aggregates with their own authorization and accounting consequences.
- **`Receivable`** — a derived position over `Invoice`, allocations, credit notes, and write-offs (§20); modeled as a read-model, not a stored mutable balance.

**Area B**

- **`ClientMoneyBankAccount`** — the account and its segregation properties.
- **`ClientMoneyLedger`** — the append-only ledger for one account, owning `ClientMoneyTransaction` entries.
- **`ClientMoneySubledger`** — the per-client/per-Matter derived position; a projection over ledger entries, not an independently mutable balance.
- **`ClientMoneyTransfer`** — an independent aggregate because a transfer spans two positions and carries its own authorization and audit lifecycle (§40, §42).
- **`ClientMoneyAuthorization`** — the recorded maker/approver decision; separate so authorization is auditable independently of the movement it permits.
- **`ClientMoneyReconciliation`** — an independent, immutable periodic record (§43).

**Area C**

- **`ChartOfAccounts`** owning **`LedgerAccount`** entries.
- **`JournalEntry`** owning **`JournalLine`** entities — the balance-per-currency invariant is exactly this aggregate's boundary.
- **`AccountingPeriod`** — its own lifecycle and authorization controls (§47).
- **`BankAccount`**, **`BankTransaction`**, **`BankReconciliation`**.

**Cross-area**

- **`JurisdictionFinancialPolicy`** (tax, numbering, client-money controls, retention) and **`AccountingPolicyVersion`** — effective-dated policy aggregates governing many records without being owned by any (§24, §54).

## 76. Entities and value objects

**Entities:** `InvoiceLine`; `PaymentAllocation`; `JournalLine`; `LedgerAccount`; `ClientMoneyTransaction`; `RateScheduleEntry`; `ReconcilingItem`.

**Consumed from the Foundation Library (PF-045), not owned by Billing (§5.1):** `Money` (exact decimal + `Currency`); `Currency`.

**Value objects owned by Billing (immutable, no identity):** `ExchangeRate` (rate, source, timestamp, base and transaction currencies); `TaxTreatment`; `TaxIdentifier`; `InvoiceNumber` and `DocumentNumberSeries`; `BillingArrangementTerms`; `AllocationInstruction`; `AuthorizationRecord` (actor, role, timestamp, basis); `BeneficiaryAttribution` (see §33.1); `EntitlementBasis`; `PeriodState`; `AsOfDate`; `FinancialClassification`; `ProviderReference`; `AIAnnotation` provenance.

**Why `Money` carries a mandatory currency:** an amount without a currency is not a financial fact, and permitting a bare number invites exactly the implicit-currency arithmetic that produces unauditable errors. That guarantee is a **Foundation** property under PF-045, available to every module, which is precisely why Billing must not shadow it with a local type — two money representations on one platform would let an amount cross a module boundary and lose its currency or its precision.

## 77. Aggregate-boundary reasoning

- **Invoice lines live inside `Invoice`** because immutability at issue is the invariant the aggregate exists to protect — the same reasoning that keeps `DocumentVersion` inside `Document`.
- **Time/fee/expense/disbursement entries live outside `Invoice`** because they exist and are managed long before any invoice, and would otherwise make `Invoice` an unbounded aggregate.
- **Correction instruments are their own aggregates** because each is an independently authorized, separately numbered financial document.
- **`ClientMoneyTransfer` and `ClientMoneyAuthorization` are separate** because a transfer spans two positions and its authorization must be auditable on its own.
- **Balances are never aggregates.** They are projections over immutable entries (§46), which is what makes direct mutation structurally impossible rather than merely forbidden.
- **`Money`/`Currency` are outside every Billing aggregate boundary** because they are a Foundation primitive (§5.1, PF-045), not a Billing concept. Billing aggregates *hold* `Money` values; they do not define, version, or govern the type. `ExchangeRate`, by contrast, **is** Billing's: converting between currencies with recorded provenance is a financial-domain decision, not a representation concern, which is why the rate, its source, and its timestamp live with the transaction that used them (§28).

## 78. Invariants

- Every financial record belongs to **exactly one Firm**.
- **`Money` is the Foundation contract (PF-045)**: exact decimal with explicit currency; floating point is prohibited, and Billing defines no competing money or currency type.
- An **issued** `Invoice` is immutable; its number is immutable and unique per Firm per series.
- Every **posted** journal balances **per currency**; posted entries are immutable.
- Every multi-currency amount carries **rate, source, and timestamp**; historical conversions are never recalculated.
- **All balances are derived** from immutable entries; no directly mutable balance exists in Areas A, B, or C.
- **Client money is segregated** from operating funds in accounts, ledgers, and permissions.
- **No negative client or Matter client-money balance**; no cross-client funding; no silent commingling.
- Every client-money movement carries **full attribution and authorization** (§33), including a `BeneficiaryAttribution` whose entitlement is **recorded, never inferred** from depositor, payer, Primary `MatterClient`, or Matter membership (§33.1).
- **Trust-to-operating transfer requires an eligible obligation and explicit human authorization.**
- **Cross-client/cross-Matter transfers are deny-by-default** and Ethical-Wall-checked on both sides.
- **Invoice/payment/trust visibility is deny-by-default**; co-client isolation holds absolutely.
- **A payment never implies revenue**; **client money never becomes Firm money** without §40.
- **A provider status is never a posted ledger entry.**
- **Reconciliation differences remain visible** until explained.
- **Closed-period posting and period reopening require authorization, reason, and audit.**
- **Tax treatment comes from effective-dated approved policy**; no rate is hardcoded.
- **AI never posts, moves, approves, allocates, reconciles, or authorizes.**
- **Corrections are always additive**: adjustment, reversal, credit note, debit note, or replacement — never a rewrite.

## 79. Commands

**Area A:** `CreateBillingArrangement`, `PublishRateSchedule`, `RecordTimeEntry`, `RecordFeeEntry`, `RecordExpenseEntry`, `RecordDisbursementEntry`, `ApproveEntryForBilling`, `CreateDraftInvoice`, `SubmitInvoiceForApproval`, `IssueInvoice`, `VoidInvoice`, `IssueCreditNote`, `IssueDebitNote`, `AuthorizeWriteOff`, `RecordInvoiceDispute`, `ResolveInvoiceDispute`, `SetInvoiceAudience`, `RevokeInvoiceAudience`.

**Payments:** `InitiatePaymentIntent`, `CapturePayment`, `CancelPaymentIntent`, `RecordProviderCallback`, `AllocatePayment`, `ReallocatePayment`, `RecordUnappliedCash`, `IssueRefund`, `RecordChargeback`, `RecordProviderSettlement`.

**Area B:** `OpenClientMoneyAccount`, `RecordClientMoneyReceipt`, `AttributeUnidentifiedReceipt`, `AuthorizeClientMoneyDisbursement`, `RecordClientMoneyDisbursement`, `RequestTrustToOperatingTransfer`, `ApproveTrustToOperatingTransfer`, `RequestCrossMatterTransfer`, `ApproveCrossMatterTransfer`, `RecordClientMoneyAdjustment`, `PerformClientMoneyReconciliation`, `RecordReconcilingItem`.

**Area C:** `DefineLedgerAccount`, `PostJournalEntry`, `ReverseJournalEntry`, `PostAdjustingEntry`, `OpenAccountingPeriod`, `BeginPeriodClose`, `CloseAccountingPeriod`, `ReopenAccountingPeriod`, `ImportBankTransactions`, `MatchBankTransaction`, `CompleteBankReconciliation`, `PublishAccountingPolicyVersion`, `PublishJurisdictionFinancialPolicy`.

Every command is authorized against `FirmContext` plus §63–§66. **`IssueInvoice`, `VoidInvoice`, `AuthorizeWriteOff`, `IssueRefund`, every `Approve*` command, all Area B movement commands, `PostJournalEntry`, `ReverseJournalEntry`, period open/close/reopen, and every policy publication require explicit human authorization and are never AI-executable** (§74).

## 80. Queries

`GetInvoice`, `ListMatterInvoices`, `ListClientVisibleInvoices`, `GetReceivableBalance`, `GetAgingReport`, `GetPayment`, `ListPaymentAllocations`, `GetUnappliedCash`, `GetClientMoneyBalance`, `GetClientMoneySubledger`, `ListClientMoneyTransactions`, `GetClientMoneyReconciliation`, `GetLedgerAccountBalance`, `GetTrialBalance`, `GetFinancialStatement`, `GetBankReconciliationStatus`, `CheckFinancialAccess`, `GetFinancialAuditHistory`, `GetEffectiveTaxPolicy`, `GetExchangeRateUsed`.

- **Every query is permission-aware at evaluation time**, never a raw read the caller filters afterward.
- **Every balance and report query takes an explicit "as of" date and returns an explicit currency.**
- `ListClientVisibleInvoices` applies the deny-by-default audience resolution in §18 and is the **only** sanctioned path for Client Portal invoice listing.
- `CheckFinancialAccess` is the published authorization check other contexts call rather than re-deriving financial permissions.

## 81. Domain and integration events

**Area A:** `BillingArrangementCreated`, `RateSchedulePublished`, `TimeEntryRecorded`, `EntryApprovedForBilling`, `InvoiceDrafted`, `InvoiceIssued`, `InvoiceVoided`, `InvoiceDisputed`, `InvoiceDisputeResolved`, `CreditNoteIssued`, `DebitNoteIssued`, `WriteOffAuthorized`, `InvoiceAudienceSet`, `InvoiceAudienceRevoked`, `ReceivableOverdue`.

**Payments:** `PaymentIntentCreated`, `PaymentAuthorized`, `PaymentCaptured`, `PaymentFailed`, `PaymentSettled`, `PaymentAllocated`, `PaymentReallocated`, `UnappliedCashRecorded`, `RefundIssued`, `ChargebackRecorded`, `ProviderSettlementReconciled`.

**Area B:** `ClientMoneyAccountOpened`, `ClientMoneyReceived`, `ClientMoneyDisbursed`, `ClientMoneyTransferRequested`, `ClientMoneyTransferApproved`, `ClientMoneyTransferCompleted`, `TrustToOperatingTransferApproved`, `ClientMoneyAdjustmentRecorded`, `ClientMoneyReconciliationCompleted`, `ReconciliationDiscrepancyDetected`.

**Area C:** `JournalEntryPosted`, `JournalEntryReversed`, `AccountingPeriodClosed`, `AccountingPeriodReopened`, `BankReconciliationCompleted`, `AccountingPolicyVersionPublished`, `JurisdictionFinancialPolicyPublished`.

**Safe-payload rule.** Financial events carry **identifiers and safe metadata only**. They must **never** carry payment secrets or tokens, bank credentials, full account numbers, privileged billing narratives, cross-client financial content, or another Firm's data. An event says *that* an invoice was issued on a Matter, with its identifiers, currency, and status; consumers needing amounts or detail must issue an authorized query, so every disclosure passes the checks in §63–§66 rather than riding inside an event payload.

## 82. API and cross-module contracts

Published commands, queries, and events only (§61). Consumers receive identifiers and authorized projections; they never receive ledgers, provider payloads, tokens, or storage paths. Provider, banking, e-tax, and accounting-system SDKs stay inside Infrastructure adapters.

**Proposed module structure** — the module is named `Billing` because ARCH-003 through ARCH-006 already depend on that name, and contains the three areas as explicitly separated domain areas at every layer:

```text
Billing/
├── Application/
│   ├── Receivables/    (arrangements, rates, entries, approval, invoices,
│   │                     credit/debit notes, write-offs, audience)
│   ├── Payments/       (intents, capture, callbacks, allocation, refunds,
│   │                     chargebacks, settlement reconciliation)
│   ├── ClientMoney/    (receipts, disbursements, transfers, authorizations,
│   │                     reconciliation)
│   ├── Ledger/         (journal posting, reversals, periods, bank reconciliation)
│   └── Policy/         (jurisdiction financial policy, accounting policy versions)
├── Domain/
│   ├── Receivables/    (BillingArrangement, RateSchedule, TimeEntry, FeeEntry,
│   │                     ExpenseEntry, DisbursementEntry, Invoice[+InvoiceLine],
│   │                     CreditNote, DebitNote, WriteOff, Payment[+PaymentAllocation],
│   │                     Refund, Chargeback)
│   ├── ClientMoney/    (ClientMoneyBankAccount, ClientMoneyLedger[+Transaction],
│   │                     ClientMoneyTransfer, ClientMoneyAuthorization,
│   │                     ClientMoneyReconciliation)
│   ├── Ledger/         (ChartOfAccounts[+LedgerAccount], JournalEntry[+JournalLine],
│   │                     AccountingPeriod, BankAccount, BankTransaction,
│   │                     BankReconciliation)
│   └── Shared/         (ExchangeRate, TaxTreatment, TaxIdentifier, InvoiceNumber,
│                         AllocationInstruction, AuthorizationRecord,
│                         BeneficiaryAttribution, EntitlementBasis, AsOfDate,
│                         FinancialClassification, ProviderReference)
│                        ↑ Billing-owned financial-domain concepts, shared by all
│                          three areas. Money and Currency are NOT here — they are
│                          imported from the Foundation Library (PF-045); see §5.1.
├── Infrastructure/     (payment-provider adapters, banking/statement-import adapters,
│                         e-tax/e-invoice adapters, accounting-export adapters,
│                         Eloquent adapters, deterministic calculation services)
├── Interface/          (permission-aware financial queries, portal invoice/payment API,
│                         webhook endpoints, reporting read models)
├── Database/           (new migrations only — no historical migrations touched)
├── Routes/
├── Tests/
├── Config/             (jurisdiction policy schema, numbering scheme templates,
│                         SoD threshold defaults, approved AI processor policy)
├── ModuleServiceProvider.php
└── README.md

Imported Foundation contracts (app/Foundation, NOT defined by this module):
    Money, Currency            ← PF-045; see §5.1
    AggregateRoot, Entity, ValueObject, DomainEvent, BusinessIdentifier,
    Result, Clock, UUIDv7      ← PF-040–PF-049, per the approved Foundation Library
```

The internal boundary between `Receivables/`, `ClientMoney/`, and `Ledger/` is enforced by distinct aggregates, ledgers, permissions, and accounting meanings. Client money reaches billing only through the authorized transfer in §40; billing reaches the general ledger only through posting (§48). **`Money` and `Currency` appear nowhere in Billing's own `Domain/` tree** — they are Foundation Library contracts consumed by every area (§5.1), and defining a Billing-local money or currency type would be a defect, not a variant.

## 83. Operations and failure handling

Conceptual failure modes the architecture must account for (mitigations are implementation-story-level detail):

- **Duplicate submission** — idempotency keys on commands and provider calls; a resubmitted invoice issue, payment, or posting never produces a second record.
- **Provider timeout / indeterminate result** — never assumed successful or failed; resolved by status query and reconciliation. No payment record implying receipt is created on an unconfirmed result.
- **Delayed settlement** — captured-but-unsettled is a distinct, visible state (§30.3); it is never reported as cash in the bank.
- **Duplicate or out-of-order webhooks** — deduplicated by provider reference and reconciled to a consistent state regardless of arrival order (§30.6).
- **Failed document rendering** — **never rolls back or rewrites an issued financial record.** The record stands; the artifact is retried, and its absence is visible.
- **Failed Communications delivery** — never alters invoice, receivable, or ledger state; it is a visible delivery failure (§58).
- **Partial payment** — normal; allocation records what was applied and what remains outstanding.
- **Refund failure** — recorded as a failed refund attempt; the original payment is untouched and the failure is visible for human resolution.
- **Chargeback** — recorded as its own event with its own accounting consequences, including provider fees; it never rewrites history.
- **Unreconciled provider settlement** — remains a visible open item; never force-matched or silently written off.
- **Bank-feed delay or gap** — reconciliation reports the gap; missing days are visible, never assumed empty.
- **Reconciliation discrepancy** — remains visible until explained by a recorded reconciling item or an authorized adjusting entry (§43, §51).
- **Posting into a closed period** — rejected, or routed to the current open period, or permitted only with authorization, reason, and audit (§47). Never silent.
- **Exchange-rate unavailability** — the transaction is held or recorded with an explicitly flagged provisional rate that must be resolved; a rate is never invented, and a missing rate never defaults to 1.
- **Rounding** — governed by explicit policy (§28); residual rounding differences are posted to a designated account, never silently absorbed or distributed.
- **Tax-policy version change** — effective-dated; issued documents retain the treatment in force at issue and are never retroactively re-rated (§24).
- **Concurrent numbering** — uniqueness enforced at assignment; a collision is rejected and retried, never resolved by suffixing or by silently skipping a number (§19).
- **Concurrent ledger posting** — resolved by aggregate concurrency control; two simultaneous postings produce two entries in a defined order, never a lost or interleaved one.
- **Practice Management outage — fails closed, without exception.** Practice Management is the sole authority for Matter validation, Matter authorization, `MatterClient` audience resolution, and `CheckEthicalWallAccess`. While it is unavailable, every operation depending on one of those decisions is **rejected or left explicitly pending**, covering both reads and state-changing operations, and specifically including: invoice-audience creation or change; disclosure of any Matter-linked financial information; client-money receipt attribution where Matter verification is required; disbursements; trust-to-operating transfers; cross-Matter and cross-client transfers; refunds and allocations requiring Matter authorization; exports; and AI context construction. **Billing must never approximate an authorization decision, infer one, or use a stale cached Ethical Wall result.** A pending operation surfaces as pending; it never completes optimistically and never degrades into an unauthorized disclosure.
- **What may continue during that outage** — only independently valid local bookkeeping that records an **already-authorized or already-committed** financial fact and **preserves the authorization provenance that permitted it** (for example, posting the ledger consequences of an invoice already issued under a recorded authorization, or recording a provider-confirmed settlement against an already-authorized payment). Anything requiring a *fresh* Matter, audience, or Ethical Wall decision does not qualify.
- **Documents, Communications, or Digital Presence outage — never rewrites or rolls back an issued or posted record.** Rendering, delivery, and portal integration queue and retry; the financial record stands and the failure is visible. A rendering or notification failure is never a financial event (§56, §58).
- **Outbound events** may wait in the platform's approved outbox/event infrastructure without affecting recorded financial state.
- **Stale read models** — balances, aging, and reports carry their "as of" basis so staleness is visible rather than misleading; a read model is never a source of truth.
- **Backup, recovery, and audit integrity** — recovery must preserve append-only audit continuity; a restore that would silently drop posted entries or audit records is a defect, not an acceptable recovery outcome.

## 84. Explicit non-goals

This architecture does **not**:

- Authorize any application implementation, executable code, database schema, or migration.
- Select, endorse, or configure any payment provider, bank, accounting system, or e-tax/e-invoice provider.
- Make OneLegalPro a bank, escrow provider, custodian, or regulated payment service, or authorize direct custody of funds.
- Claim regulatory, tax, or professional-conduct compliance for any jurisdiction.
- Hardcode any tax rate, or assert any jurisdiction-specific tax or client-money rule without an approved, sourced policy.
- Introduce cryptocurrency or digital-asset handling.
- Architect payroll, full accounts payable, or full procurement in implementation detail.
- Architect investment or treasury management.
- Grant AI any financial authority, or let AI compute or post authoritative totals.
- Define a workflow or automation engine.
- Prescribe a Firm's chart of accounts, revenue-recognition standard, or reconciliation frequency.
- Schedule any implementation work or allocate any `PF-*` story number.

## 85. Proposed implementation stages

**Proposed only.** None of these stages is an approved, scheduled, or numbered story. Each requires its own entry in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md`, with a Definition of Ready and Definition of Done, before implementation begins. This is the same ten-stage sequence recorded for EPIC-008 in `docs/architecture/08_Roadmap.md`.

1. **Adoption of the PF-045 Foundation `Money`/`Currency` contract, Billing-specific `ExchangeRate` provenance, Firm accounting configuration, and financial-policy versioning.** This stage *consumes* PF-045; it does not reimplement, reschedule, or renumber it (§5.1).
2. **Billing arrangements, rates, time, fees, expenses, and approval.**
3. **Invoices, tax documents, credit/debit notes, and accounts receivable.**
4. **Payments, allocations, refunds, chargebacks, and provider reconciliation.**
5. **Client-money/trust account, ledger, and subledger foundation.**
6. **Trust transactions, authorization, segregation of duties, and three-way reconciliation.**
7. **General ledger, chart of accounts, journal posting, and accounting periods.**
8. **Bank reconciliation, multi-currency, tax configuration, and financial reporting.**
9. **Practice Management, Documents, Communications, and Client Portal integration.**
10. **Financial AI, analytics, audit, security, and operational hardening.**

## 86. Future expansion

Each of the following is **describable as a future capability and is not implemented, not architected in detail, and not scheduled** by this document. Each would require its own architecture pass and its own approved implementation stories:

- **Accounts payable** and **procurement** — beyond the minimal expense/disbursement boundary needed for a coherent ledger.
- **Payroll** and **fixed assets**.
- **Budgeting** and **advanced treasury/cash management**.
- **External accountant or auditor portal** — scoped, read-only, audited access for a Firm's professional advisers.
- **Banking feeds and open banking** — automated statement and transaction ingestion behind adapters.
- **E-tax/e-invoice provider integrations** — per jurisdiction, behind the §27 adapter boundary.
- **Jurisdiction packages** — packaged, sourced, approved policy sets for additional jurisdictions beyond the Thailand-first default.
- **Financial analytics and profitability analysis** — realization, utilization, matter and client profitability, as read-model projections.
- **Advanced collections** — scoring, escalation ladders, and agency handoff.
- **Mobile payment experiences.**

Every item above is additive to the existing `Invoice`/`Payment`/`ClientMoneyLedger`/`JournalEntry` model, not a redesign of it — the same extension discipline applied throughout this platform's architecture. **No cryptocurrency, no direct custody of funds by OneLegalPro, and no autonomous AI financial authority is contemplated by any of them.**

## Phased implementation guidance

See `docs/architecture/08_Roadmap.md`, the proposed EPIC-008 — Billing, Trust Accounting & Finance epic, for the staged delivery order restated at epic level. That staging is **proposed only**; formal scheduling requires entries in `docs/implementation/03_Engineering_Backlog.md` and `docs/implementation/01_Implementation_Sprint_Plan.md` and separate story-level approval before implementation begins. No `PF-*` story numbering or approved implementation sequence is changed by this document: **PF-010 remains the current repository implementation story and PF-011 remains next.**

The story ID is **ARCH-007** while this architecture document is numbered **15** (`ARCH-015`), continuing the existing document-number sequence rather than the story sequence — the same convention ARCH-006/ARCH-014 established.
