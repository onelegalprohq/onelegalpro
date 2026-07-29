# ADR-015 — Deferred Professional-Responsibility Controls

## Status

**Accepted.** Explicit owner approval recorded on PR #30 on 29 July 2026. Acceptance authorizes the architectural decision only; it does not authorize implementation, deployment, or production access.

## Context

Two of the platform's approved controls exist specifically because legal practice requires them, and **neither is delivered in Release 0.1**:

- **Ethical Walls.** Constitution Article 17 requires that restricted-Matter access be authorized only through Practice Management's published `CheckEthicalWallAccess` query, and forbids any other bounded context from implementing its own wall logic. `docs/adr/ADR-006-Practice-Management-Core.md` Decision 4 places wall ownership in Practice Management for exactly that reason.
- **Automated conflict checking.** `docs/architecture/13_Practice_Management_Architecture.md` defines `PartyReference`, `ConflictRelationship` as an independent Firm-scoped aggregate root, and the `SearchConflicts` published query — while deliberately leaving matching and scoring algorithms unspecified.

Release 0.1 (`docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md`) delivers neither. But `docs/architecture/13_Practice_Management_Architecture.md` §3 states an invariant that Release 0.1 *does* deliver a `Matter` against: **"Reaching Opened requires a conflict check outcome to exist (clear, or overridden with justification) — a `Matter` cannot silently skip Conflict Checking."**

That leaves two questions that must be answered in architecture rather than discovered in implementation. **First:** how does a Release 0.1 `Matter` satisfy an invariant that names a capability Release 0.1 does not have? **Second:** what happens to a firm-wide Matter Worklist when no wall exists to restrict it?

The dangerous answers are the plausible ones. A checkbox that records nothing would satisfy the letter of the invariant and destroy its purpose. A "basic" name-match conflict search would look like conflict checking and would be relied on as conflict checking. A hidden-from-view flag would look like a wall and would be relied on as a wall. In legal practice, **a control that is approximated is more dangerous than a control that is absent and disclosed**, because only the second one tells the firm what it must do for itself.

Per `docs/adr/ADR-001-Architecture-First.md` and `AGENTS.md`, this needs an approved architecture decision before any Release 0.1 Matter implementation begins.

## Decision

1. **Ethical Walls are absent from Release 0.1 and are never stubbed, approximated, simulated, partially implemented, or renamed.** No `EthicalWall` aggregate, no allow-list, no restricted-Matter flag, no "private matter" toggle, no hidden-from-worklist mechanism, and no per-user Matter visibility rule of any kind exists in Release 0.1. **Constitution Article 17 is not amended, narrowed, or excepted** — it continues to govern in full, and it continues to mean that when walls are built, `CheckEthicalWallAccess` in Practice Management is their sole enforcement point.

2. **Automated conflict checking is absent from Release 0.1 and is never approximated.** No `ConflictRelationship` aggregate, no `PartyReference` model, no `SearchConflicts` query, and **no name matching, fuzzy matching, similarity scoring, party-graph traversal, or automated conflict suggestion of any kind** exists in Release 0.1. A partial implementation would invite exactly the reliance it cannot support.

3. **Release 0.1 satisfies the `Opened` gate with a manual conflict attestation — a recorded professional determination by a human, not a system finding.** The attestation is a first-class, immutable record carrying at minimum:

   - the **attesting actor's identity** (a specific, accountable human — never a system, service, or AI actor);
   - the **timestamp** of the attestation;
   - the **`Matter` it applies to**, and the parties it was made against as recorded on that Matter at that time;
   - the **outcome** — clear, or proceeding with recorded justification;
   - the **justification text**, mandatory whenever the outcome is not clear.

   It is **append-only**: a changed determination is a new attestation, never an edit, and the original remains auditable.

4. **The `Opened` gate is preserved exactly, not relaxed.** A Release 0.1 `Matter` cannot reach `Opened` without a conflict attestation record existing. The invariant `docs/architecture/13_Practice_Management_Architecture.md` §3 states is satisfied in substance — a recorded outcome, clear or justified — and **an unrecorded checkbox, a default-true field, a nullable flag, or any silent-pass path is prohibited.**

5. **Manual conflict attestation must never be described, labelled, presented, documented, or marketed as a conflict check performed by the system.** No product surface, notification, export, audit label, help text, marketing copy, or pilot document may state or imply that OneLegalPro searched for, detected, screened for, cleared, or found no conflicts. Permitted framing states plainly that **a named person recorded their own determination on a date**. This is a naming and presentation constraint with the same force as the modelling constraints above.

6. **The absence of both controls is disclosed in-product and in the pilot agreement.** In-product disclosure is visible where it matters — where a Matter is opened, and where the firm-wide Worklist presents every Matter to every member. It is not buried in a settings page or a footnote.

7. **Release 0.1's pilot disclosures cover four absent capabilities explicitly**, since each is one a legal practice product is normally assumed to have:

   - **Ethical Walls** — no matter-level access restriction exists; **every Firm member with Worklist access can see every Matter in the Firm.**
   - **Automated conflict checking** — no conflict search, database, or detection exists; conflict clearance is entirely the firm's own professional process, recorded only as an attestation.
   - **Automated reminders and notifications** — deadlines and tasks are recorded and displayed; **nothing notifies, reminds, escalates, or alerts.** A recorded deadline is not a reminder, and missing one is not detected by the product.
   - **Document storage** — **no document, file, attachment, or upload capability exists.** Nothing may be stored in the product, and no Matter record is a document repository or a substitute for one.

8. **External Thai-qualified legal review of these disclosures is mandatory** before the pilot agreement is executed or the disclosures are published, per `docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md` Decision 8. Until that review and the owner's approval are recorded, **all disclosure wording is draft.** This ADR records the dependency only: **no reviewer is engaged, no review has occurred, and no legal or professional-conduct conclusion is asserted here.**

9. **Both controls are re-introduced additively, without redesign.** `EthicalWall` enters as an independent aggregate whose decisions flow only through `CheckEthicalWallAccess`, exactly as `docs/adr/ADR-006-Practice-Management-Core.md` Decision 4 already defines. `PartyReference`/`ConflictRelationship`/`SearchConflicts` enter exactly as `docs/architecture/13_Practice_Management_Architecture.md` already defines them. **Manual conflict attestation is not replaced by automated conflict checking when it arrives** — an automated search produces candidate findings; a human determination remains a distinct recorded professional act, and the attestation record remains part of the Matter's permanent history.

10. **Nothing else may compensate for the absence.** No other bounded context, no capability bundle, no operator support path, and no configuration option may implement matter-level access restriction or conflict detection in place of Practice Management. Constitution Article 17's prohibition on other contexts implementing wall logic applies with full force to a period in which no wall exists.

11. **Architecture approval does not schedule implementation.** EPIC-012 is **proposed, not scheduled**; every story is **Backlog**. **`PF-040` remains the next code story and remains Backlog.** No `PF-*` story is added, renamed, renumbered, merged, split, deleted, or rescheduled.

## Ownership

| Concept | Owner | Release 0.1 status |
|---|---|---|
| `EthicalWall`, `CheckEthicalWallAccess`, emergency override | **Practice Management** (ADR-006 Decision 4, Constitution Article 17) | **Absent.** Never stubbed, approximated, or relocated. Rule fully in force. |
| `PartyReference`, `ConflictRelationship`, `SearchConflicts` | **Practice Management** (ARCH-013) | **Absent.** No matching, scoring, or detection of any kind. |
| Manual conflict attestation record | **Practice Management** | **Present.** Actor-attributed, timestamped, append-only; a human determination, never a system finding. |
| `Matter` lifecycle and the `Opened` gate | **Practice Management** (ARCH-013 §3) | **Present and unrelaxed**, on the reduced state subset (ADR-012 Decision 4). |
| In-product and pilot-agreement disclosures | Operator, subject to Thai-qualified legal review (ADR-012 Decision 8) | **Draft** until reviewed and approved. |
| AI authority over walls, conflicts, or attestation | **Nobody** | No AI capability exists in Release 0.1; AI never decides an Ethical Wall outcome under any configuration (Constitution Article 40). |

## Alternatives considered

- **Ship a simplified Ethical Wall — a per-Matter "restricted" flag hiding it from the Worklist.** Rejected — a firm would reasonably treat it as a wall, and it would be one: no allow-list model, no audited override, no consuming-context enforcement, no `CheckEthicalWallAccess`, and no coverage of the other surfaces a wall must reach. An approximated wall invites reliance it cannot support, and Constitution Article 17 forbids wall logic outside the sanctioned enforcement point.
- **Ship "basic" conflict checking — exact or fuzzy client-name matching.** Rejected — it would be labelled conflict checking and relied on as conflict checking, while missing opposing parties, related parties, organizations, lateral-hire lawyer history, and historical relationships, which ARCH-013 explicitly requires the model to support. A partial conflict search is a false negative generator with a reassuring interface.
- **Skip the `Opened` conflict gate entirely for Release 0.1.** Rejected — the gate is a professional-responsibility control, not a lifecycle convenience. `docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md` Decision 4 preserves every Matter invariant precisely because a reduced state machine does not license a weaker one.
- **Satisfy the gate with an unrecorded checkbox.** Rejected — it satisfies the letter of the invariant and destroys its purpose. The value of the gate is the accountable, attributable, permanent record; without attribution and timestamp there is no record at all.
- **Let the attestation be made by a system or service actor, or defaulted at Matter creation.** Rejected — an attestation is a professional determination. A determination with no accountable human is not a determination, and a default value is an assertion nobody made.
- **Allow the attestation to be edited or overwritten.** Rejected — the platform's audit discipline (Constitution Articles 8, 18, 23; ARCH-016 §40) makes correction a new record, never a rewrite. A revisable attestation would let the record of what was known at `Opened` change after the fact.
- **Disclose only in the pilot agreement, not in-product.** Rejected — the person opening a Matter or reading the firm-wide Worklist is often not the person who signed the agreement. The disclosure has to reach the point of reliance.
- **Disclose only in-product, not in the pilot agreement.** Rejected — the firm's decision to adopt the product at all depends on knowing what it does not do; that decision is made at contracting time.
- **Defer the disclosures until Thai-qualified review is complete.** Rejected — the *requirement* to disclose is an architecture decision recordable now; only the *wording* depends on review, and it is marked draft accordingly.
- **Treat the absence as temporary and therefore not worth documenting.** Rejected — "temporary" is exactly when undocumented gaps become permanent assumptions. A firm that relies on an absent control during the pilot suffers the consequence whether or not the control arrives later.

## Consequences

- **Release 0.1 is not suitable for a firm that needs matter-level access restriction.** Every Firm member with Worklist access sees every Matter in the Firm. That is a real product limitation and the single most consequential fact in the pilot disclosures.
- The pilot firm carries its conflict clearance process entirely outside the product. The product records that a determination was made and by whom; it contributes nothing to reaching it.
- Recording attestations from Release 0.1 onward means that when automated conflict checking arrives, the Matter history already contains the human determinations, and the two remain distinguishable — a system finding and a professional judgement are not the same record and must never merge.
- The naming constraint in Decision 5 binds product, help, audit, marketing, and sales copy alike. It will occasionally make a screen or a sales page read less impressively; that is the intended trade.
- Because no wall exists, operator support access has no wall to respect (`docs/adr/ADR-014-Operator-Assisted-Onboarding-and-Privileged-Access.md` Decision 7). This compounds the same limitation rather than adding a new one, and is one more reason the absence is disclosed.
- Re-introducing walls later will narrow visibility for users who currently see everything. That is a correct and expected change, and it is easier to explain when the absence was disclosed from the beginning than when it was implied to have been present.
- The four disclosures in Decision 7 set an expectation for future releases: an absent, normally assumed capability is disclosed rather than left to inference.

## Security and professional-responsibility consequences

- **Constitution Article 17 is unamended and fully in force.** Nothing here weakens, narrows, excepts, or reinterprets it, and no other bounded context may implement wall logic during the period in which no wall exists.
- **When Ethical Walls are built, `CheckEthicalWallAccess` remains their sole enforcement point**, called by every consuming context and reimplemented by none (ADR-006 Decision 4, ADR-009 Decision 6).
- **The `Opened` conflict gate is preserved in substance**, satisfied only by an actor-attributed, timestamped, append-only record — never by a silent pass.
- **A conflict attestation is never a professional opinion, clearance, or legal conclusion by OneLegalPro.** The product records a human's determination; it makes none of its own and asserts none.
- **Every attestation is attributable to an accountable human**, and human, system, and operator actors remain distinguishable in audit.
- **Attestation records are append-only and not editable by the actor being audited**, consistent with the platform's audit discipline.
- **Firm isolation, authorize-before-retrieval, and denied-existence confidentiality apply unchanged** to Matters, attestations, and the Worklist. The absence of walls narrows nothing about Firm-level isolation; a Firm's Matters remain invisible to every other Firm without exception.
- **Existence disclosure is disclosure.** The absence of intra-Firm walls does not license any cross-Firm leakage of Matter or party existence, including through counts, aggregates, exports, or error responses.
- **No AI capability exists in Release 0.1**, and AI never decides, overrides, or influences an Ethical Wall outcome or a conflict determination under any Firm configuration — a structural, absolute prohibition (Constitution Article 40).
- **No certification, compliance, or professional-conduct compliance claim is made** by this ADR or by any Release 0.1 disclosure, consistent with `docs/architecture/04_Security_Architecture.md` §9.

## Integration consequences

- **Practice Management** owns the manual conflict attestation record as part of the `Matter` lifecycle, and remains the sole future owner of `EthicalWall`, `ConflictRelationship`, and `SearchConflicts`. Its approved model is unchanged; only delivery order is affected.
- **IdentityAccess** composes authorization as it always does. Because no wall exists in Release 0.1, there is no wall result to compose — **not a wall result that defaults to allow.** When walls arrive, the composition in ARCH-016 §24–§25 applies unchanged, and IdentityAccess never becomes a second wall authority.
- **`PlatformAdministration`** has no relationship to walls, conflicts, or attestations, and never gains one.
- **Documents, Billing, Communications, and Digital Presence** do not exist in Release 0.1. When built, each obtains wall decisions only from `CheckEthicalWallAccess`, exactly as their approved architectures already require.
- **`Workflow` and the AI Copilot** do not exist in Release 0.1. Their absolute prohibition on deciding or overriding an Ethical Wall outcome is unaffected.

## Explicit non-goals

This ADR does **not**: implement anything; create modules, source files, schemas, migrations, or tests; design an Ethical Wall model, allow-list, override procedure, or any matter-level access restriction; design a conflict-matching, scoring, similarity, or party-graph algorithm; create any partial, simplified, advisory, or "basic" version of either control; author, approve, or publish the Privacy Notice, Terms, pilot agreement, or disclosure wording; engage, name, or represent a legal reviewer; assert a legal, professional-conduct, or regulatory conclusion; claim any certification or compliance; amend, narrow, or create an exception to Constitution Article 17 or to any of Articles 1–44; move Ethical Wall or conflict ownership out of Practice Management; introduce an AI capability; schedule EPIC-012; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

This ADR is **Accepted conceptual architecture only**. It authorizes no application code, schema, migration, dependency, package, infrastructure, Docker change, CI change, environment change, production configuration, or GitHub setting. **No control described here is claimed to be implemented, and the two controls it concerns are explicitly absent from Release 0.1.**

The disclosure wording referenced throughout is **draft** and remains draft until Thai-qualified human legal review and the owner's approval are recorded.

EPIC-012 is recorded as **proposed, not scheduled** in `docs/architecture/08_Roadmap.md`; every story is **Backlog**. **`PF-040` — AggregateRoot remains the next code story and remains Backlog. No story is In Progress.**

The story ID is **ARCH-011** while the new sequential architecture document is numbered **19** (`ARCH-019`), continuing the established distinction between story numbering and architecture-document numbering.
