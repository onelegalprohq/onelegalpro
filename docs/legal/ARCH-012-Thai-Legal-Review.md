# ARCH-012 — Thai-Qualified Legal Review Record

## Record status

**Reviewed and approved on 1 August 2026.** Michael Jand, the repository owner,
acted in the expressly stated capacity of Thai-qualified legal reviewer and
approved all eight decisions below. Repository adoption remains subject to the
normal pull-request, protected-check, and human-approval process.

This is a legal-review decision record for ARCH-012. It is deliberately
separate from the technical architecture and ADRs. It does not certify
compliance, production readiness, or the legal sufficiency of any unimplemented
control, and it is not general legal advice for any person or Firm.

## Scope

This review answers the eight questions recorded in
`docs/architecture/03_Database_Design.md` §22.2. It does not select vendors,
implement controls, execute a restore, set every numeric retention period, or
replace a later matter-specific incident assessment.

## Approved decisions

### 1. Backups, erasure, audit retention, and legal hold

When deletion is authorized, content is removed from live systems. Encrypted
backup generations age out under a fixed, approved schedule rather than being
selectively rewritten. A legal hold suspends deletion and expiry for the data
within its scope. The schedule, authorization evidence, and release of a hold
must be recorded before implementation.

### 2. Audit retention and partition detachment

Each audit stream requires its own approved minimum retention period. Partition
detachment or equivalent purge is prohibited unless the applicable retention
period has expired, no legal hold applies, and an authorized review and decision
are recorded. ARCH-012 approves this decision rule but does not invent or imply
the numeric period for any stream.

### 3. Per-Firm point-in-time restore

Per-Firm point-in-time restore is not required for Release 0.1. Whole-database
physical recovery is the approved technical baseline. A Firm-scoped export is
not to be represented as point-in-time restore. The physical model must be
revisited through the database-redesign approval gate if a later Thai legal,
professional, contractual, or product requirement makes per-Firm restore
necessary.

### 4. Cross-Firm existence disclosure

Every confirmed cross-Firm existence disclosure is treated as a high-severity
security and confidentiality incident. The response must include immediate
containment, evidence preservation, Thai-qualified legal assessment, and a
recorded decision on affected persons, professional-conduct consequences, and
any required notification. No universal notification outcome or timeline is
asserted; those depend on the incident facts and then-applicable duties.

### 5. Duplicate side effects

Technical idempotency is mandatory but is not declared legally or
professionally sufficient by itself. A recovery-driven or crash-driven
duplicate with a client-facing consequence requires recorded assessment,
remediation, and a decision on communication or notification. Exactly-once
execution or delivery must never be claimed.

### 6. Restored production data outside production

Ordinary non-production environments use synthetic data only. A restore test
that necessarily uses production data must run in an isolated,
production-controlled recovery environment with explicit authorization,
least-privilege access, access logging, encryption, an approved retention and
deletion procedure, and recorded completion. It must not populate a general
development, test, demonstration, or analytics environment. Masking,
pseudonymization, or subsetting is not presumed sufficient without a separately
recorded assessment.

### 7. Ethical Wall and conflict-checking disclosure

The approved baseline disclosure is:

> Release 0.1 does not include automated Ethical Walls or automated conflict
> checking. The Firm must operate and document its own manual conflict-review
> procedure before accepting or opening a Matter. A manual attestation is not a
> system-performed conflict check.

This substance must appear in the pilot agreement and wherever in-product
reliance could otherwise arise. Presentation may be adapted to its context, but
it must not weaken, obscure, or contradict the approved substance.

### 8. Platform-realm pre-authentication security events

Collection is minimized. Raw submitted identifiers are avoided where
technically possible; credentials, authentication secrets, recovery material,
and request-body dumps are prohibited. The stream is never Firm-visible and is
restricted to named platform-realm service and security roles. It requires a
short, separately approved retention period, access logging, and a recorded
deletion or expiry process. ARCH-012 approves these constraints but does not
invent or imply the numeric retention period.

## Required follow-up policies and evidence

The legal questions are reviewed at the decision-principle level. The following
work remains before a related capability can be called production-ready:

1. approve numeric backup, audit-stream, and pre-authentication-event retention
   periods;
2. define legal-hold application, release, and backup-expiry procedures;
3. approve the incident assessment and notification decision procedure;
4. place the approved Ethical Wall/conflict-checking disclosure in the pilot
   agreement and relevant product surfaces;
5. approve the isolated restore-test procedure and execute and record a restore
   test; and
6. implement and verify the technical controls through separately approved
   stories.

No open item above reverses an approved decision in this record. It supplies the
parameters, procedure, implementation, or evidence that the decision requires.
