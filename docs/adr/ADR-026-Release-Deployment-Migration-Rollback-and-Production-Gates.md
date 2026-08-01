# ADR-026 — Release Deployment, Migration Execution, Rollback, and Production Gates

## Status

**Proposed.** Not approved, and it authorizes nothing. Acceptance requires explicit owner approval recorded on the pull request; acceptance would authorize the architectural decision only, never implementation, deployment, migration execution, or production access.

Authored by story **ARCH-013 — Deployment & Operations Architecture**, alongside `docs/architecture/20_Deployment_Operations_Architecture.md` and `docs/adr/ADR-022-…` through `ADR-025-…`.

**Depends on `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…` through `ADR-021-…`, which are Approved and Accepted** — explicit repository-owner approval recorded on PR #34 on 1 August 2026, alongside the eight Thai-qualified legal-review decisions in `docs/legal/ARCH-012-Thai-Legal-Review.md`. **ARCH-013 is synchronized with that approved baseline.** ARCH-012's approval schedules no implementation and authorizes no deployment or production access, and neither does this ADR.

**This ADR extends `docs/adr/ADR-020-Migration-Rollback-and-Schema-Evolution.md`. It does not amend, weaken, reinterpret, or create an exception to it.** ADR-020 decided migration *policy* — forward-only, expand/contract, role separation, prohibited statements. This ADR decides the *operational procedure* ADR-020 Decision 9 requires but deliberately did not design.

## Context

`docs/adr/ADR-020-…` Decision 9 states: **"No automatic migration on deploy without an approved operational procedure."** No such procedure exists. Decision 10 requires that **"drift between recorded migration state and actual schema is detected, not discovered later during an incident"** — and its Consequences section records plainly that this needs "a mechanism nobody has built". Decision 6 ends with **"No zero-downtime claim is made"**, and the Consequences add that "every migration needs an operational plan that the deployment architecture — not yet approved — must accommodate."

Alongside that, `docs/architecture/03_Database_Design.md` §15.2 and ADR-020 Decision 2 establish a distinction that is easy to lose and expensive to lose: across the expand/migrate/contract boundaries the property that holds is **compatibility, not reversibility**. Both the previous and the next application version work against the live schema — "**which is what makes an application-level rollback possible, and that is a different claim from the migration being undoable.**" A deployment procedure that treats "roll back" as one undifferentiated action erases exactly that distinction, and the erasure is silent until the first bad release.

Finally, `docs/architecture/02_Product_Requirements.md` §8 and `docs/adr/ADR-012-…` Decision 10 enumerate seven pieces of evidence required before production access, and `docs/implementation/01_Implementation_Sprint_Plan.md` makes **production deployment changes** an owner approval gate. The list exists; **nothing turns it into an operable gate** with named artefacts, a place they are recorded, and a decision point.

The repository's current state is relevant and favourable: both CI workflows are `pull_request`-triggered with `permissions: contents: read`, use **no secrets**, and contain **no deployment trigger or step of any kind**. There is nothing to undo — only something to design.

## Decision

### Release and deployment

1. **A release is an identified, immutable artefact promoted through environments — never a rebuild per environment.** The artefact verified in staging is promoted to production **by digest** (`docs/adr/ADR-022-…` Decision 6). A rebuild between staging verification and production deployment invalidates the verification and is prohibited.

2. **Deployment is a human-authorized, recorded operation.** No deployment to staging or production is triggered automatically by a merge, a tag, a schedule, or an external event. Each deployment records: the artefact digest, the source commit, the target environment, the authorizing human, the executing operator, the start and end time, and the outcome.

   **CI builds and verifies; it does not deploy.** The existing workflows contain no deployment trigger, no deployment step, no environment, no secret, and no write permission, and **this ADR adds none.** Introducing any deployment automation later is a **production deployment change** requiring the owner approval gate in `docs/implementation/01_Implementation_Sprint_Plan.md`, and — where it would touch workflow permissions or required checks — the security-control gate in `AGENTS.md`. **The four required `Protect main` check names are preserved exactly.**

3. **A deployment to production is preceded by the same deployment to staging, using the same artefact and the same procedure.** Staging is where the procedure itself is verified, not only the code. A production deployment that skipped staging has verified neither.

### Migration execution

4. **Migrations run as an explicit, controlled operation. They never run automatically on application startup, on container start, on merge, or as a side effect of deployment.**

   This is ADR-020 Decision 9 made operational, and it inherits its reasoning: an automatic migration makes the highest-risk operation on the platform an unattended side effect, with no approval, no plan, and no human watching the lock. `PF-010` already established "no automatic migrations" as the repository's precedent.

5. **A migration operation has a defined procedure, and every element is required:**

   | Element | Requirement |
   |---|---|
   | **Named operator** | A specific accountable human executes it. Not a pipeline, not a container entrypoint. |
   | **Explicit authorization** | Recorded before execution. A schema change is a **database change**, and where it touches policies, roles, or grants it is additionally an **authorization change** hitting that `AGENTS.md` gate (ADR-020 Decision 5). Destructive changes hit the destructive-operations gate (ADR-020 Decision 7). |
   | **Migration identity** | Executed as the **migration role**, which owns the relations (`docs/architecture/03_Database_Design.md` §4.1). **Never the application runtime role, never a superuser, never a `BYPASSRLS` role.** Its credential is **non-standing** — issued for this operation and bounded to it (`docs/adr/ADR-023-…` Decision 13; `docs/adr/ADR-024-…` Decision 3). **Forced Row-Level Security applies to it throughout**, exactly as to every other role (`docs/adr/ADR-024-…` Decision 3a). |
   | **Preflight checks** | Run and pass before the first statement (Decision 6). |
   | **Lock and timeout criteria** | A `lock_timeout` with bounded retry, never an unbounded wait (ADR-020 Decision 6). |
   | **Abort criteria** | Defined in advance: what is observed, what threshold aborts, and what the state is after an abort. |
   | **Recorded result** | Success or abort, the statements applied, the elapsed time, the resulting migration state, and the post-migration verification outcome. |

6. **Preflight checks, run before every migration operation and each individually blocking:**

   - **Schema drift check** — the actual schema agrees with the recorded migration state. **Drift aborts the operation.** This is ADR-020 Decision 10's "mechanism nobody has built", specified here as a mandatory preflight rather than an aspiration; building it belongs to the approved story that introduces it.
   - **No long-running open transaction** is in flight. Row-Level Security and policy DDL take `ACCESS EXCLUSIVE` and queue behind any weaker lock already held, then block everything arriving after them (ADR-020 Decision 6). Starting behind an open long transaction stalls the whole relation.
   - **Backup currency** — a recent backup exists and its age is known (`docs/adr/ADR-024-…` Decision 15), because restore-and-roll-forward is the recovery path for a destructive change and a stale backup silently shortens it.
   - **Role verification** — the connected role is the migration role, not the application runtime role; it is not a superuser and holds no `BYPASSRLS`; and `FORCE ROW LEVEL SECURITY` is confirmed still enabled on every Firm-scoped relation **before the first statement**, since that setting is what denies the owning role its ordinary policy exemption (`docs/adr/ADR-024-…` Decision 3a).
   - **Environment confirmation** — the target is the intended environment. A production migration is never a mistyped staging one.
   - **Phase declaration** — the migration declares which expand/contract phase it is (ADR-020 Decision 2), and **contract phases additionally confirm that no running code path references the object being removed.**

7. **Post-migration verification is part of the operation, not a follow-up.** After the final statement: migration state is re-read and recorded; the **schema-level guard test** asserting that every Firm-scoped relation declares its class and has Row-Level Security **enabled and forced** with a policy carrying `USING` and `WITH CHECK` is re-run (`docs/architecture/03_Database_Design.md` §16.3; ADR-020 Decision 13); and the application's readiness signal is confirmed (`docs/adr/ADR-025-…`).

   **A migration that completed but left Row-Level Security disabled or a policy dropped is a failed migration**, whatever its exit status. That is the specific hazard ADR-020 Decision 6's final bullet exists for: the isolation control absent, silently, with nothing failing.

8. **A failed or aborted policy migration never leaves Row-Level Security disabled or a policy dropped.** Where an abort could leave that state, restoring the isolation control is the **first** recovery action, ahead of restoring service. `docs/adr/ADR-023-…` Decision 15 already prohibits disabling Row-Level Security as an operational act; this is its migration-time counterpart.

9. **Firm-spanning backfills follow ADR-020 Decision 4 unchanged** — per-Firm iteration establishing each Firm's context with `SET LOCAL` (preferred), or a narrowly scoped, explicitly authorized, recorded, time-bounded maintenance path. **Never** by disabling Row-Level Security, dropping a policy, or granting `BYPASSRLS`. Backfills are batched, idempotent, restartable, and interruptible, and never share a transaction with the schema change that enabled them.

10. **No zero-downtime claim is made for any migration** (ADR-020 Decision 6), and none is made for any deployment. Where a migration requires a service interruption, it is a **planned platform maintenance window** under `docs/adr/ADR-022-…` Decision 9 — platform availability management, **never a Firm-level suspended state and never a per-Firm disable.**

### Rollback

11. **Application rollback and schema rollback are different operations with different permissions, and they are never described by one word.**

12. **Application rollback is permitted only within an explicitly verified compatibility window.** Redeploying a previous artefact digest is safe **only** where that version is known to work against the **live schema** — which is the property expand/contract preserves at each phase boundary (ADR-020 Decision 2; §15.2).

    Concretely:
    - Every release records **which prior artefact digests are compatible with the schema as currently deployed**. That is a recorded fact established before deployment, not a judgement made during an incident.
    - Rolling back **past a completed contract phase is prohibited**, because the old code references an object that no longer exists.
    - Rolling back **into a half-completed migrate phase** is permitted only where the older version tolerates partially populated data — which ADR-020 Decision 2 warns is exactly what a half-finished backfill leaves behind.
    - **Where no compatibility window is recorded, application rollback is not available**, and the response is forward correction. "We think the old version probably works" is not a compatibility window.

13. **Schema rollback is prohibited. There is no production down-migration, ever.**

    - A `down()` method may exist for local development convenience. **It is never the production recovery plan**, and a story documenting `migrate:rollback` as its recovery path has documented nothing (ADR-020 Decision 1).
    - **Historical migrations are never edited.**
    - A reverse migration is untested code running against a state it has never seen, and it routinely cannot restore what the forward migration destroyed — a dropped column's data, a narrowed type's precision, a deleted row.

14. **A failed schema change is recovered by forward correction, or by restore under a separately authorized recovery procedure.**

    - **Default: stop, assess, roll forward** with a corrective migration under Decision 5's full procedure.
    - **Where data was lost**, recovery is restore-and-roll-forward under `docs/adr/ADR-024-…` Decisions 11–14 — a destructive, separately authorized operation, with the whole-database restore consequence, the audit-continuity verification, and the replay and duplicate-side-effect consequences all applying.
    - **A restore is never chosen because it is faster than thinking.** It restores every Firm to the target instant (`docs/adr/ADR-024-…` Decision 14), so it trades one Firm's problem against every other Firm's data.

15. **Reversibility is never claimed for what is merely compatible.** Expand is safely abandonable; migrate is safe to interrupt but leaves written data written; contract is not reversible at all (ADR-020 Decision 2). **A deployment record states which of the three a change is, and no release note, runbook, or gate artefact describes a migration as "reversible" or "rollback-able".** An overstated reversibility claim is itself a safety hazard.

### Production-readiness gates

16. **Production access requires recorded evidence of every item in `docs/architecture/02_Product_Requirements.md` §8 and `docs/adr/ADR-012-…` Decision 10. This ADR turns that list into an operable gate by naming, for each item, what counts as evidence and where it is recorded.**

    | # | Evidence item | What counts | Current state |
    |---|---|---|---|
    | 1 | **Approved database design** | `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…`–`ADR-021-…` Approved/Accepted with owner approval recorded on the pull request | **Satisfied** — approval recorded on PR #34, 1 August 2026 |
    | 2 | **Approved deployment architecture** | `docs/architecture/20_Deployment_Operations_Architecture.md` and `docs/adr/ADR-022-…`–`ADR-026-…` Approved/Accepted with owner approval recorded | **Not satisfied** — Proposed |
    | 3 | **Executed and recorded restore test** | An **executed** restore meeting `docs/adr/ADR-024-…` Decision 15, with operator, date, backup identity, target instant, verification outcomes, and elapsed time recorded | **Not satisfied** — **no restore test has been executed** |
    | 4 | **Operational monitoring** | The minimum signals in `docs/adr/ADR-025-…` Decision 15 collected and alerting configured, with gaps recorded as gaps | **Not satisfied** — no monitoring exists |
    | 5 | **Documented incident procedure** | A written procedure meeting `docs/adr/ADR-025-…` Decisions 17–22, with a named responder | **Not satisfied** — none written |
    | 6 | **Completed Thai-qualified legal review** | The review required by `docs/adr/ADR-012-…` Decision 8 — the Privacy Notice, Terms, pilot agreement, and the `docs/architecture/02_Product_Requirements.md` §6 disclosures — reviewed by a Thai-qualified lawyer and approved by the owner | **Not satisfied.** That review **has not occurred**, and the approved Ethical Wall/conflict-checking disclosure wording is **not yet placed** (`docs/legal/ARCH-012-Thai-Legal-Review.md` follow-up item 4) |
    | 7 | **Every applicable `AGENTS.md` approval gate** | Recorded human approval for each authentication/authorization, database, security-control, destructive-operation, new-runtime-dependency, and production-deployment change | **Not satisfied** |

    **Each row's "current state" is a statement of fact at the time of drafting, not a target.** The table is a checklist to be satisfied by evidence, never by assertion.

**Authoritative context, not partial satisfaction of row 6.** The **ARCH-012 Thai-qualified persistence review was completed and approved on 1 August 2026** (`docs/legal/ARCH-012-Thai-Legal-Review.md`), answering the eight persistence questions and binding this architecture throughout. **It is a different review, of different material, and it does not satisfy row 6 in whole or in part.** Row 6 concerns the Privacy Notice, Terms, pilot agreement, and required disclosures under `docs/adr/ADR-012-…` Decision 8; that review has not occurred, and row 6 is **Not satisfied**.

17. **Prerequisites outside the evidence list that nonetheless block production**, recorded so the gate is honest rather than merely complete:

    - **`PF-033` PostgreSQL Continuous Integration** — its story contract is defined in `docs/implementation/03_Engineering_Backlog.md` and it **must land before `PF-080` begins** (`docs/architecture/02_Product_Requirements.md` §8; `docs/architecture/03_Database_Design.md` §16.1). **This ADR asserts no status for it and neither schedules nor renumbers it**; `docs/PROJECT_STATUS.md` is authoritative. **ARCH-012 §22.1 still records this identifier as unassigned — that entry is superseded by the identifier's own approved story contract, and correcting it is ARCH-012's tracking-synchronization item, not this ADR's.**
    - **`PF-080` Firm Context, `PF-081` Tenant Resolver, `PF-082` Tenant Middleware** — mandatory for every Firm-bound Release 0.1 capability.
    - **Every Release 0.1 requirement** recorded in `docs/implementation/03_Engineering_Backlog.md`, including the backup-and-restore-test, monitoring, and incident-procedure operations requirements and the in-product and contractual disclosure requirements.
    - **The owner/legal decisions recorded as open** across `docs/adr/ADR-022-…` Decision 12, `ADR-024-…` Decision 16, and `ADR-025-…` Decisions 10, 11, 19, and 21.

    **No story's status is asserted here; `docs/PROJECT_STATUS.md` is the authoritative record**, and nothing in this ADR schedules, renames, renumbers, or reschedules any `PF-*` story.

18. **Missing evidence blocks release, regardless of date.** A target date is not evidence, and no item may be waived, deferred past first production access, marked "in progress" as though satisfied, or satisfied by a plan to satisfy it later. Constitution Article 47 is explicit: **a date is never a reason to move a control**, and **no release document makes a production-readiness, certification, or compliance claim.**

    **15 September 2026 is a target, not a contractual commitment** (`docs/adr/ADR-012-…` Decision 10). **31 August 2026 is a public-website and sales-readiness target** and confers no production access: a sellable public website is not a deployed service, and no marketing, sales, or website statement may assert or imply that the product is available, secure, compliant, certified, or production-ready. All such copy remains **draft** pending Thai-qualified legal review (`docs/architecture/02_Product_Requirements.md` §8).

19. **The gate is a decision point with a recorded outcome**, not a document review. Production access is granted by an explicit, recorded owner decision naming the evidence relied on. **Nothing in this ADR grants it, and this ADR's own approval would satisfy exactly one row of the table in Decision 16.**

20. **AI holds no authority here.** AI never authorizes, executes, approves, or aborts a deployment, migration, rollback, restore, or release; never satisfies or signs off a gate item; never approves its own proposal; and is never an authorization authority (Constitution Articles 6, 26, 28, 39, 40). **Release 0.1 contains no AI capability.**

## Alternatives considered

- **Run migrations automatically on deploy or container start**, as most Laravel deployments do. Rejected — ADR-020 Decision 9 already rejects it, and its reasoning is unchanged: it makes the highest-risk operation on the platform an unattended side effect with no approval, no plan, and nobody watching the lock. On a shared-schema database holding multiple Firms' matters, an unattended `ACCESS EXCLUSIVE` lock is an outage for every Firm at once.
- **Allow automatic migrations in staging only**, for convenience. Rejected — staging is where the migration *procedure* is verified (Decision 3). Automating it there means the procedure that runs in production has never actually been rehearsed.
- **Treat "rollback" as one operation** covering both the application and the schema. Rejected, and this is the central rejection here. It is the mental model ADR-020 Decision 2 explicitly guards against, and it fails at the worst moment: someone reaches for `migrate:rollback` during an incident, running untested reverse code against a state it has never seen, and destroys what was recoverable. Decision 11 separates them by name.
- **Maintain `down()` methods so production rollback stays available "just in case".** Rejected — ADR-020 Decision 1 permits a local `down()` and forbids relying on it. Maintaining one *for production* creates precisely the false comfort that encourages destructive forward steps.
- **Determine the compatibility window during an incident**, by inspection. Rejected — that is a judgement made under time pressure, by whoever is awake, about code they may not have written. Decision 12 requires the window to be a recorded fact established before deployment.
- **Permit rollback past a contract phase** by re-adding the dropped object. Rejected — that is a new forward migration, made under incident pressure, on a relation whose data is already gone. It is forward correction, and calling it rollback misdescribes both the risk and the outcome.
- **Prefer restore over forward correction** because it "puts things back". Rejected as a default — a restore is whole-database (`docs/adr/ADR-024-…` Decision 14), so it resolves one Firm's problem by rewinding every other Firm's data, and it can replay delivered events and permit duplicate side effects. Decision 14 keeps it available and puts it behind explicit authorization.
- **Treat the §8 evidence list as a checklist of documents.** Rejected — three of the seven items (executed restore test, operational monitoring, documented incident procedure) are satisfied only by something having actually happened. A document describing a backup plan is not an executed restore, and `docs/architecture/02_Product_Requirements.md` §8 says **executed**.
- **Allow a conditional or partial go-live** with some evidence outstanding and a commitment to complete it. Rejected — Constitution Article 47 forecloses it, and the practical form is worse than the principle: the outstanding item is always the one nobody returns to once real Firm data is in the system.
- **Set up a deployment pipeline now** so it is ready when the gate is satisfied. Rejected as premature and as a scope violation: it is a production deployment change requiring the owner gate, it would need production secrets in CI (which `docs/adr/ADR-023-…` rejects as a primary boundary), and the existing workflows deliberately hold no secrets and no write permissions.
- **Omit the 31 August target from the gate discussion**, since it concerns the website rather than the service. Rejected — a sales-readiness date is exactly when readiness language starts appearing in copy. Decision 18 states plainly that it confers no production access and that the copy is draft.

## Consequences

- **Every migration costs a scheduled human operation** with preflight, execution, verification, and a record. That is materially slower than `php artisan migrate` in a deploy script, and it is the trade ADR-020 already accepted.
- **Drift detection must be built** before the first production migration, because Decision 6 makes it a blocking preflight rather than an aspiration. It belongs to its own approved story.
- **Compatibility windows must be recorded per release**, which is new discipline and new metadata that does not exist today.
- **Rollback is sometimes unavailable**, and the honest answer during an incident is "roll forward". Better said now than discovered then.
- **Deployment stays manual**, which is slower and, at pilot scale, acceptable. Automating it later is a gated change with a clear approval path.
- **The gate table in Decision 16 will read as uncomfortably empty** for some time. That is its value: it makes the distance to production visible at a glance, at 1 August 2026, rather than at the 15 September target.
- **Some gate items are outside engineering's control** — legal review in particular. The gate makes that dependency explicit instead of allowing it to be discovered late.

## Security and professional-responsibility consequences

1. **A migration is the most privileged operation in the system** (ADR-020's Security consequences). It runs DDL, can alter policies and grants, and can destroy data — which is why Decision 5 requires a named operator, an explicit authorization, and a non-standing migration credential, and why Decision 6 refuses to start until preflight passes.

2. **A migration that leaves Row-Level Security off is a cross-Firm disclosure waiting to happen, with nothing failing.** Decision 7 makes verification part of the operation and Decision 8 makes restoring the isolation control the first recovery action — ahead of restoring service, because an outage is recoverable and a disclosure between potentially adverse parties is not.

3. **An automatic migration removes the human who would notice.** The lock, the abort criterion, and the decision to stop all require somebody watching. Decision 4 keeps them.

4. **Documenting `migrate:rollback` as a recovery path is a false assurance about data that is already gone**, and in a legal practice the lost data is a Matter, a deadline, or a conflict attestation — a client-facing professional failure, not a data-quality metric.

5. **An overstated reversibility claim is itself a hazard**, because it invites abandoning a backfill on the belief that nothing was left behind. Decision 15 keeps the language exact.

6. **Deploying without the evidence would be the precise failure Constitution Article 47 anticipates** — a date moving a control. The controls at risk are Firm isolation, immutable audit, and the ability to recover a Firm's data at all.

7. **A sales-readiness date is when readiness language appears**, and a marketing claim of security, compliance, or readiness would be false in a way a pilot Firm would reasonably rely on. Decision 18 forecloses it, and the copy remains draft pending the Thai-qualified review required by `docs/adr/ADR-012-…` Decision 8 — **a review distinct from the completed ARCH-012 persistence review**.

8. **AI never authorizes, executes, or signs off any operation in this ADR.**

9. **No certification, compliance, or legal-sufficiency conclusion is asserted.** The **ARCH-012 Thai-qualified persistence review was completed and approved on 1 August 2026** (`docs/legal/ARCH-012-Thai-Legal-Review.md`); the **separate Thai-qualified review required by `docs/adr/ADR-012-…` Decision 8** — covering the Privacy Notice, Terms, pilot agreement, and required disclosures — **has not occurred.** **No production access has been authorized, and the complete production-access gate is not satisfied**; of its seven evidence items only the **approved database-design** item is presently satisfied, and approval of this document would satisfy the **deployment-architecture** item only. **No deployment has occurred and no migration has been executed against any environment.**

## Integration consequences

- **`docs/adr/ADR-020-…`** is extended, not amended: forward-only, expand/contract, role separation, prohibited statements, and the destructive-operations gate are consumed unchanged. This ADR supplies the operational procedure Decision 9 required and the drift mechanism Decision 10 named.
- **`docs/adr/ADR-022-…`** supplies the environments, the single-digest promotion, and the maintenance-window boundary Decision 10 relies on.
- **`docs/adr/ADR-023-…`** supplies the non-standing migration credential and the authorization gate on role, grant, and policy changes.
- **`docs/adr/ADR-024-…`** supplies backup currency as a preflight input, and the restore procedure and its three limitations for Decision 14.
- **`docs/adr/ADR-025-…`** supplies the drift signal, backup-age signal, and readiness surface Decisions 6 and 7 consume, and the incident procedure a failed migration escalates into.
- **`PF-033` PostgreSQL Continuous Integration**, whose story contract is defined in `docs/implementation/03_Engineering_Backlog.md`, verifies forward-only migration runs, restartable backfills, and the schema-level guard test on PostgreSQL as the application runtime role (ADR-020 Decision 14). **No status is asserted for it.** **This ADR changes no CI configuration, no `phpunit.xml`, and no test.**
- **Every future module story** inherits Decision 5's procedure and Decision 12's compatibility-window obligation alongside ADR-020's existing inheritance.
- **The backup-and-restore-test, monitoring, and incident-procedure operations requirements** recorded in `docs/implementation/03_Engineering_Backlog.md` supply gate rows 3, 4, and 5. None carries a story identifier, and this ADR neither schedules nor identifies them.
- **The in-product and contractual disclosure requirements** sit under gate rows 6 and 7 and are similarly untouched.

## Explicit non-goals

This ADR does **not**: implement, configure, provision, or deploy anything; create, edit, run, squash, revert, or delete any migration, schema, table, column, index, constraint, policy, role, grant, source file, test, configuration file, Docker file, `compose.yaml`, CI workflow, dependency, or GitHub setting; create a deployment pipeline, deployment automation, release automation, environment, or deployment credential; select, endorse, evaluate, price, or recommend a deployment tool, orchestrator, migration tool, schema-diff or declarative-state tool, artefact registry, hosting provider, or any other vendor, provider, product, or package; **execute, authorize, or schedule any deployment, migration, backfill, rollback, restore, or restore test**, or claim that any has occurred or succeeded; grant, imply, or schedule production access; claim zero downtime, any availability target, uptime figure, RPO, or RTO; claim that any gate item other than the recorded approved database-design item is satisfied, or claim any item is waived, partially satisfied, or on track; define or approve a Firm-level suspension, emergency-disable, or per-Firm maintenance capability, or read a maintenance window as constituting one; create a second operator or privileged-access path, or authorize access to Firm data outside `docs/adr/ADR-014-…`'s `PrivilegedAccessGrant`; authorize any restore of production data into a non-production environment; permit disabling Row-Level Security, dropping a policy, or granting `BYPASSRLS` for any operational or migration purpose; authorize any change that would destroy an audit fact, which no approval gate can authorize; define an audit-purge, retention, or partition-detachment path, or assert any retention period; supply any parameter, procedure, or evidence that `docs/legal/ARCH-012-Thai-Legal-Review.md` leaves to separately approved follow-up, or assert any notification outcome or timeline; assert any story's status, or schedule, rename, or renumber `PF-033` or any operations requirement; change `phpunit.xml`, any CI workflow, workflow permission, pinned action, or the four required `Protect main` check names; alter the `Protect main` ruleset; introduce an AI capability, grant AI any authority, or modify `docs/architecture/05_AI_Architecture.md`; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; claim that any described procedure, control, or mechanism is implemented, built, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

**Proposed conceptual architecture only.** It authorizes no application code, schema, migration, deployment pipeline, environment, credential, infrastructure, deployment, or production access.

**No deployment procedure exists. No migration has been executed against any deployed environment. No drift-detection mechanism has been built. No compatibility window has been recorded. No production-readiness gate has been run, and no production access has been authorized.** Of the seven evidence items, only the **approved database-design** item is presently satisfied (Decision 16); **the complete production-access gate is not satisfied**, and approval of this ADR would satisfy the **deployment-architecture** item only. **No procedure described here is claimed to be implemented, tested, or effective.**

**No story's status is asserted here; `docs/PROJECT_STATUS.md` is the authoritative record.** The story ID is **ARCH-013**; the accompanying architecture document is `docs/architecture/20_Deployment_Operations_Architecture.md`.
