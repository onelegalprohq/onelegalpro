# ADR-008 — Billing, Trust Accounting & Finance

## Status

Accepted. The human owner has explicitly approved this decision.

## Context

Every architecture approved so far names a future Billing context and depends on it, but none defines it:

- **Practice Management** (`docs/architecture/13_Practice_Management_Architecture.md`, Integration Boundaries) states that "Invoices reference `Matter`/`Client` by identifier; Practice Management never owns invoices, payments, or billing rates," and its Matter Dashboard and Matter Timeline both display "Billing (from the Billing context)."
- **Documents** (`docs/adr/ADR-007-Document-Knowledge-Management.md`, Decision 4) already decided that "Billing owns invoice and payment records; Documents may store the rendered artifact but never the commercial meaning."
- **Digital Presence** (`docs/architecture/12_Website_Client_Portal_Architecture.md` §4, §8) surfaces Invoices and Payments in the Client Portal and ships a **Payment Widget**, while stating the Portal "owns none of this data."
- **Communications** (`docs/architecture/11_Communications_Hub_Architecture.md` §9, §10) links a `CommunicationThread` to an **Invoice** through `CommunicationLink`, owning the conversation but not the invoice.
- **Branding** (`docs/architecture/10_White_Label_Platform_Architecture.md` §9) applies letterhead to generated invoices as "a presentation wrapper around canonical document content."
- The Roadmap's later-epic list still carries an unarchitected **"Commercial Operations"** name with no owner, no model, and no boundary.

Money is where a legal platform's obligations become hardest and least forgiving. A law firm handles two categorically different kinds of money: its **own** revenue, and **client money it holds on trust** — funds that are not the Firm's, that must not be commingled, that cannot fund the Firm's operations, and whose mishandling is a professional-discipline event rather than a bookkeeping error. No approved document currently states that distinction, which means the natural implementation — one `balance` column on a Matter — is both the obvious design and a professional-responsibility failure.

Three further failure modes are specific to this domain and cannot be corrected after the fact:

1. **Mutable financial history.** If an issued invoice or a posted ledger entry can be edited, the accounting record and the audit record become separately falsifiable, and "what did we actually bill, and when" becomes a reconstruction rather than a retrieval.
2. **Co-client financial disclosure.** A jointly represented Matter (`docs/architecture/13_Practice_Management_Architecture.md`, Matter Clients) can carry one co-client's invoice, payment method, or trust balance that another co-client must never see. Matter membership is not a financial audience.
3. **Autonomous AI financial action.** An AI that can post a journal, allocate a payment, or move client money is an AI with signing authority over funds it does not own.

Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, this needs approved architecture before any Billing implementation begins, and before the dependent stages already staged in `docs/architecture/08_Roadmap.md` — Communications' Invoice linking, Digital Presence's Client Portal invoice/payment surfaces and Payment Widget, Documents' rendered invoice artifacts, and Practice Management's Matter Timeline and Dashboard billing panels — can be implemented.

**No contradiction with prior architecture was found.** Every existing reference names Billing as the future owner of invoices, payments, and billing rates and disclaims that ownership for itself. This ADR resolves those dependencies without rewriting approved history; ARCH-001 through ARCH-014 and ADR-001 through ADR-007 are unmodified.

## Decision

1. **Billing is a separate bounded context**, proposed module `Billing`, and the sole owner of commercial billing records, client-money/trust ledgers, and Firm financial accounting records. No other module owns any part of them.

2. **The bounded context contains three explicitly separated financial domain areas**: **Billing and Accounts Receivable**, **Client Money / Trust Accounting**, and **Firm Finance and Accounting**. They may share carefully governed financial primitives (`Money`, `Currency`, `ExchangeRate`, jurisdiction policy, audit provenance) and integration contracts, but retain distinct aggregates, ledgers, lifecycles, permissions, and accounting meanings. The module keeps the name `Billing` because ARCH-003 through ARCH-006 already depend on "a future Billing bounded context" by that name.

3. **Billing references Practice Management's `Client`, `Matter`, `MatterClient`, and approved actor identifiers through published contracts only.** It never owns, copies, caches-as-authoritative, or duplicates those records.

4. **Practice Management remains the sole source of Matter authorization and Ethical Wall decisions**, obtained exclusively through its published `CheckEthicalWallAccess` query. Billing never implements, approximates, or caches wall logic.

5. **A Primary Client is only the default administrative and billing recipient.** It does not automatically determine the legally liable party, the invoice audience, the payer, a trust beneficiary, or any allocation. Each of those is its own explicit, recorded determination.

6. **Invoice and payment visibility is explicit and deny-by-default.** Joint or co-client Matter membership never discloses one client's invoice, payment method, payment, trust balance, or transaction to another client without an explicit authorized audience decision.

7. **Third-party payment does not grant Matter or Client Portal access.** Paying an invoice makes someone a payer, not a client, and confers no visibility into the Matter, its documents, or its other financial records.

8. **Issued invoices and posted financial entries are immutable.** Corrections use explicit adjustments, reversals, credit notes, debit notes, or replacement records — never rewriting posted history.

9. **Financial values use exact decimal `Money` with an explicit currency.** Floating-point money is prohibited everywhere in the platform.

10. **Multi-currency transactions preserve transaction currency, Firm/base currency, the effective exchange rate, the rate source, and the rate timestamp.** A historical conversion is never silently recalculated with a newer rate.

11. **Tax rules, invoice requirements, withholding treatment, numbering rules, retention periods, and client-money controls are effective-dated jurisdiction policies.** No current VAT rate is hardcoded and no universal compliance is claimed. Any jurisdiction-specific rule requires an official primary source and approval by an authorized human legal or accounting owner before implementation.

12. **Thailand is the platform-default jurisdiction, and official Thai-language law and regulator material remains authoritative.** English translations are reference material only, consistent with Constitution Articles 1–4.

13. **Payment processing stays behind provider adapters.** OneLegalPro stores provider tokens and safe references only — never raw card credentials, CVV data, online-banking secrets, or reusable bank credentials.

14. **Provider callbacks and webhooks are authenticated, idempotent, replay-resistant, and safe under duplication and out-of-order delivery.**

15. **Payment status and settlement status are distinct.** Authorization, capture, settlement, allocation, refund, chargeback, and reconciliation are separate recorded facts, never collapsed into one mutable status field.

16. **Client money and trust funds are segregated from Firm operating funds** in both accounting meaning and access controls.

17. **Client-money balances are derived from append-only, balanced ledger entries.** Negative client or Matter balances, silent commingling, unexplained transfers, and direct balance mutation are prohibited.

18. **Every client-money receipt, disbursement, transfer, refund, and adjustment records** Firm, account, currency, amount, beneficiary/client attribution, Matter attribution where applicable, purpose, source, authorization, and audit provenance.

19. **Trust-to-operating transfers require an eligible commercial obligation, explicit human authorization, and an auditable transfer record.** AI may never initiate or approve one.

20. **Cross-client and cross-Matter trust transfers are deny-by-default** and require explicit authority, referential integrity, authorization, and Ethical Wall checks.

21. **Segregation of duties supports configurable maker, approver, and reconciler roles.** Where policy requires independent review, the person creating a high-risk client-money transaction must not silently self-approve it.

22. **Trust/client-money reconciliation compares the bank balance, the trust control account, and the individual client/Matter subledgers.** Unexplained differences remain visible and cannot be erased by editing history.

23. **Firm financial accounting uses an append-only double-entry general ledger.** Every posted journal balances per currency; posted entries are corrected through reversals or adjustments.

24. **Accounting periods have explicit open, closing, closed, and exceptionally reopened controls.** Closed-period postings and reopening require authorization, a recorded reason, and audit history.

25. **Billing subledgers, trust subledgers, payment-provider settlements, bank records, and general-ledger control accounts are reconcilable**, and no integration is treated as authoritative merely because it is external.

26. **Documents owns rendered invoice, receipt, statement, tax-document, and report artifacts; Billing owns their commercial meaning and lifecycle.** This is the reciprocal of `docs/adr/ADR-007-Document-Knowledge-Management.md`, Decision 4.

27. **Digital Presence and the Client Portal are presentation surfaces.** The Payment Widget initiates provider-mediated actions through Billing's contracts and owns no financial state.

28. **Communications owns delivery and conversation history for invoice notices, reminders, and payment discussions.** A `CommunicationLink` may reference an Invoice; Communications never owns the Invoice or its state.

29. **Branding controls presentation of financial artifacts only.** It never changes amounts, tax disclosures, mandatory notices, ledger meaning, or financial status.

30. **Legal Intelligence owns official law.** Billing owns effective Firm financial and tax *configuration* derived from authorized professional decisions; that configuration never becomes a second source of authoritative law.

31. **Financial AI is advisory only** and must preserve exact source references, currencies, versions, and "as of" timestamps. AI output never becomes a posted or authoritative financial record automatically.

32. **AI may suggest** narratives, time-entry descriptions, billing classifications, anomaly flags, collection priorities, reconciliation candidates, and forecasts.

33. **AI may never, without explicit human authorization,** issue or void invoices, alter rates, post journals, allocate payments, write off balances, move client money, approve disbursements, release refunds, close or reopen accounting periods, reconcile accounts, change tax treatment, or expose restricted financial data.

34. **This architecture is conceptual only.** Full detail is recorded in `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md`. EPIC-008 — Billing, Trust Accounting & Finance is **proposed, not scheduled**; PF-010 remains the current repository implementation story and PF-011 remains next.

## Domain ownership

| Concept | Owner | Billing's relationship |
|---|---|---|
| `BillingArrangement`, `RateSchedule`, `TimeEntry`, `FeeEntry`, `ExpenseEntry`, `DisbursementEntry`, `Invoice`, `InvoiceLine`, `Receivable`, `CreditNote`, `DebitNote`, `WriteOff`, `Payment`, `PaymentAllocation`, `Refund`, `Chargeback` | **Billing** (Accounts Receivable area) | Owner |
| `ClientMoneyBankAccount`, `ClientMoneyLedger`, `ClientMoneySubledger`, `ClientMoneyTransaction`, `ClientMoneyTransfer`, `ClientMoneyAuthorization`, `ClientMoneyReconciliation` | **Billing** (Client Money / Trust area) | Owner — segregated from the areas above |
| `LedgerAccount`, `ChartOfAccounts`, `JournalEntry`, `JournalLine`, `AccountingPeriod`, `BankAccount`, `BankTransaction`, `BankReconciliation` | **Billing** (Firm Finance area) | Owner |
| `Client`, `Matter`, `MatterClient`, `MatterTeam`, `PracticeArea`, `EthicalWall` | **Practice Management** | Referenced by identifier and published contract only |
| Rendered invoice, receipt, statement, tax-document, and report artifacts | **Documents** | Requested and referenced; Billing stores no bytes |
| Client Portal invoice/payment read surfaces, Payment Widget | **Digital Presence** | Serves them through permission-aware Billing queries |
| Invoice notices, reminders, payment conversations, `CommunicationLink` | **Communications** | Requests delivery; owns the Invoice, not the conversation |
| `BrandProfile`, `PDFBrandingConfig`, theme tokens | **Branding** | Composed at rendering time; presentation only |
| Official statutes, regulations, court decisions, translations, citations | **Legal Intelligence** | Cited; never duplicated or overridden |
| Actor identity, permissions, segregation-of-duties assignments | **Future Identity/Security** | Consumed; never owned by Billing |
| Workflow orchestration | **Future Workflow** | May orchestrate; never owns financial state |

## Alternatives considered

- **Fold Billing into Practice Management as a Matter sub-capability.** Rejected — `docs/adr/ADR-006-Practice-Management-Core.md`, Decision 1, explicitly refuses to absorb Billing; it would make `Matter` an unbounded aggregate carrying years of ledger entries, and it leaves Firm-level accounting (which has no Matter) ownerless.
- **Three separate bounded contexts — Billing, Trust, and Finance.** Rejected for now — the three share `Money`, `Currency`, `ExchangeRate`, jurisdiction policy, audit provenance, and, critically, the reconciliation paths that tie a billing subledger, a trust subledger, and a general-ledger control account together. Splitting them would put those reconciliations permanently across module boundaries while duplicating the shared primitives three times. One context with three explicitly separated areas keeps the accounting boundaries real while keeping reconciliation coherent. A future ADR may split them if the internal boundary proves not to hold — the same posture `docs/architecture/09_Legal_Intelligence_Architecture.md` takes toward a possible `LegalResearch` split.
- **One undifferentiated financial ledger for both Firm and client money.** Rejected outright — this is commingling expressed as a data model. Client money is not the Firm's money, and no access control can repair a schema that treats them as one balance.
- **Store balances as mutable columns updated on each transaction.** Rejected — makes the balance and its supporting history separately falsifiable, makes reconciliation discrepancies invisible, and makes a lost update a silent loss of client funds. Balances are derived from immutable ledger entries.
- **Allow editing an issued invoice to fix an error.** Rejected — destroys the evidentiary record of what was billed and when, and in most jurisdictions the tax document itself may not be altered after issue. Corrections are credit notes, debit notes, adjustments, or replacements.
- **Use floating-point (or platform-default numeric) types for money.** Rejected — binary floating point cannot represent ordinary decimal amounts exactly; accumulated representation error in a ledger is unauditable and unacceptable.
- **Recalculate historical foreign-currency amounts using the current rate.** Rejected — silently rewrites reported history. The rate, its source, and its timestamp are recorded with the transaction and never retroactively replaced.
- **Hardcode the current VAT rate and treat the platform as tax-compliant.** Rejected — rates and rules change, vary by jurisdiction and transaction type, and require professional determination. Tax treatment is effective-dated policy backed by an official primary source and approved by an authorized human owner. The platform makes no compliance claim.
- **Assume every Firm bills hourly.** Rejected — fixed-fee, capped, staged/milestone, and retainer-funded arrangements are common in Thai and international practice; `BillingArrangement` is extensible by design and time entry is one input among several.
- **Treat received payment as revenue on receipt.** Rejected — receipt is a cash event; revenue recognition is an accounting-policy determination, and a receipt into client money is not revenue at all. Conflating them is the single most common way client money becomes Firm money by accident.
- **Let a payment provider's status be the authoritative accounting record.** Rejected — a provider status is an external signal about an external system. The platform's authoritative record is its own posted ledger entry, reconciled against the provider, never replaced by it.
- **Have OneLegalPro hold or settle client funds directly.** Rejected and out of scope — the platform is not a bank, escrow provider, custodian, or regulated payment service. Any future design in which OneLegalPro accepts, holds, transfers, or settles funds on another party's behalf requires separate legal, regulatory, security, and architecture approval.
- **Let AI compute and post authoritative totals.** Rejected — a language model is not a calculator and not a ledger. Authoritative arithmetic is deterministic and happens outside the model; AI may explain a computed result, never produce one that is posted.
- **Resolve invoice visibility at Matter granularity.** Rejected — same inversion rejected for documents in `docs/adr/ADR-007-Document-Knowledge-Management.md`: it makes exposure the default and confidentiality an opt-in someone can forget, in a domain where the leak is one co-client's financial affairs.
- **Adopt a named foreign client-money regime (for example IOLTA) as the model.** Rejected — that is a United States construct and does not describe Thai requirements. The architecture uses jurisdiction-neutral client-money/trust-accounting language with jurisdiction policy supplying the specifics.
- **Architect accounts payable, payroll, procurement, fixed assets, and treasury now.** Rejected — out of scope for this story; named as future expansion, with only the minimal general-ledger boundary needed to keep the ledger coherent.

## Consequences

- The Firm gains one coherent financial system where billing subledgers, trust subledgers, and the general ledger reconcile, at the cost of a genuinely larger bounded context than any prior one. The three-area separation is what keeps that size manageable.
- Immutable financial history means corrections are always additive records. The audit trail grows monotonically, and users must be taught that "fix" means "issue a correction," not "edit."
- Derived balances mean every balance is a query over ledger entries rather than a column read. This is a performance and read-model design cost, accepted because a mutable balance column is not auditable.
- Deny-by-default financial visibility means a Firm must take an explicit action for any client to see any invoice. Deliberate friction, correct default.
- Segregation of duties means small Firms may find maker/checker controls burdensome. Roles are configurable, so a Firm may set thresholds — but the architecture supports independent review rather than assuming it away.
- Multi-currency provenance means every foreign-currency amount carries five recorded facts rather than one number, and historical reports remain stable over time.
- EPIC-008 depends on EPIC-006 (Client/Matter/MatterClient, Ethical Walls), EPIC-007 (rendered artifacts), EPIC-005 (Client Portal, Payment Widget), EPIC-004 (Invoice linking and delivery), and a future Identity/Security capability for actor identity and segregation-of-duties assignments. Its sequencing must account for all five.
- Jurisdiction policy being effective-dated and approval-gated means a Firm cannot self-serve a new tax treatment without a recorded professional decision. This is intentional.

## Security and professional-responsibility consequences

- **Client money is not Firm money.** The segregation is structural — separate accounts, separate ledgers, separate permissions — not a reporting convention. Commingling is prohibited by the model, not merely discouraged by policy.
- **A denied caller receives nothing**: no balance, amount, invoice metadata, payment metadata, trust metadata, report, or confirmation that a restricted financial record exists. Existence disclosure is disclosure.
- **Ethical Walls apply to financial records.** A staff member walled off a Matter is walled off its invoices, payments, and trust transactions, with the most restrictive outcome applying where a record touches more than one restricted Matter.
- **Co-client isolation is a professional-responsibility requirement**, not a preference: one client's financial affairs on a shared Matter are not another's to see.
- **Privileged billing narratives** (time-entry descriptions frequently describe the substance of legal work) are treated as privileged content: restricted in exports, in client-facing detail, and in AI processor eligibility.
- **Maker/checker controls and break-glass access** both produce auditable records; break-glass requires a recorded justification and never happens silently.
- **The platform is not a regulated financial institution.** It records and reconciles; it does not hold, custody, or settle funds on another party's behalf.
- **Payment credentials never enter the platform.** Tokens and safe references only; raw card data, CVV, and reusable bank credentials are never stored, logged, or transmitted through platform code.

## Integration consequences

- **Practice Management** gains a Billing panel on the Matter Dashboard and Billing entries on the Matter Timeline, fed by Billing's published events carrying identifiers and safe metadata only.
- **Documents** becomes the store for rendered financial artifacts; Billing requests rendering and holds a reference, never bytes. A failed rendering never rolls back an issued invoice.
- **Digital Presence** gains permission-aware invoice/payment read surfaces and a Payment Widget that initiates provider-mediated actions through Billing contracts, owning no financial state.
- **Communications** gains Invoice-linked notices, reminders, and receipts; a delivery failure never alters invoice state.
- **Branding** composes presentation onto financial artifacts and may never alter their substance.
- **Legal Intelligence** is cited for the legal basis of a jurisdiction policy; it never becomes a second tax-rules engine.
- **A future Identity/Security context** supplies actor identity, permissions, and segregation-of-duties assignments that Billing consumes rather than defines.
- **A future Workflow context** may orchestrate billing processes through published contracts without owning any financial state.

## Explicit non-goals

This ADR does **not**: authorize application implementation, database schemas, or migrations; select or configure a payment provider, bank, accounting system, or e-tax provider; make OneLegalPro a holder or custodian of funds; claim regulatory, tax, or professional-conduct compliance; hardcode any tax rate; introduce cryptocurrency; architect payroll, full accounts payable/procurement, fixed assets, budgeting, advanced treasury, or investment management in implementation detail; grant AI any financial authority; or schedule implementation.

## Implementation status

This ADR and `docs/architecture/15_Billing_Trust_Accounting_Finance_Architecture.md` are **conceptual architecture only**. They authorize no application code, migrations, schemas, dependencies, infrastructure, Docker configuration, CI changes, environment changes, payment-provider configuration, accounting integrations, banking connections, or runtime behavior. EPIC-008 — Billing, Trust Accounting & Finance is recorded as **proposed, not scheduled** in `docs/architecture/08_Roadmap.md`; none of its stages carries a story ID. `PF-*` story numbering and the approved repository implementation sequence are unchanged: **PF-010 remains current and PF-011 remains next.**

The story ID is **ARCH-007** while the architecture document is numbered **15** (`ARCH-015`), continuing the existing document-number sequence rather than the story sequence — the same convention ARCH-006/ARCH-014 established.
