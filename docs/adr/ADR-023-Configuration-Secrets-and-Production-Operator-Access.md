# ADR-023 — Configuration, Secrets, and Production Operator Access

## Status

**Proposed.** Not approved, and it authorizes nothing. Acceptance requires explicit owner approval recorded on the pull request; acceptance would authorize the architectural decision only, never implementation, deployment, provider engagement, credential issuance, or production access.

Authored by story **ARCH-013 — Deployment & Operations Architecture**, alongside `docs/architecture/20_Deployment_Operations_Architecture.md`, `docs/adr/ADR-022-…`, and `docs/adr/ADR-024-…` through `ADR-026-…`.

**Depends on `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…` through `ADR-021-…`, which are Approved and Accepted** — explicit repository-owner approval recorded on PR #34 on 1 August 2026, alongside the eight Thai-qualified legal-review decisions in `docs/legal/ARCH-012-Thai-Legal-Review.md`. **ARCH-013 is synchronized with that approved baseline.** ARCH-012's approval schedules no implementation and authorizes no deployment or production access, and neither does this ADR.

**This ADR extends `docs/adr/ADR-014-Operator-Assisted-Onboarding-and-Privileged-Access.md`. It does not amend, weaken, reinterpret, or create an exception to it, and it creates no second privileged-access mechanism.**

## Context

Two problems converge here, and the second is the hardest question in ARCH-013.

**Configuration and secrets.** The repository's only configuration record is `.env.example`, which PF-011 established as **local-development defaults** — literal development credentials matching `compose.yaml`, `APP_DEBUG=true`, a blank `APP_KEY`, a non-sending mail driver. `CONTRIBUTING.md` and `README.md` both forbid committing secrets and require treating an accidentally committed secret as compromised. `docs/architecture/04_Security_Architecture.md` §5 requires secrets never in plain configuration, logs, events, or audit, with rotation under bounded overlap; §7 classifies credentials, tokens, recovery material, and signing keys as **Security Secret** — never retrievable, never logged. `docs/architecture/07_API_Standards.md` §14 requires secret material in "an approved, encrypted secret-management boundary with tightly restricted runtime access", storing only opaque references elsewhere, and explicitly selects no product. Constitution Article 29 forbids credentials, reusable secrets, recovery material, and full session tokens from appearing in logs, analytics, events, or audit payloads. **Nothing states where a production secret actually lives.**

**Production operator access, and the reconciliation this ADR exists for.** `docs/adr/ADR-014-…` Decision 1 states that operator-assisted onboarding and recovery run **only** through IdentityAccess's `PrivilegedAccessGrant`, and that **"no second privileged-access mechanism exists anywhere in Release 0.1"**. Constitution Articles 29 and 48 say the same. `docs/architecture/19_Platform_Administration_Architecture.md` §15 repeats it.

But a production service needs administration. Somebody deploys it, runs a migration (`docs/adr/ADR-026-…`), reads a log, restarts a stuck queue worker, and restores a backup (`docs/adr/ADR-024-…`). **An engineer holding a production shell or a `psql` prompt can read every Firm's Clients, Matters, and Tasks, and ADR-014's grant mechanism does not constrain them at all.** That is precisely the second privileged-access mechanism ADR-014 says does not exist — arriving not by design but by omission.

ADR-014's own Context paragraph names both failure modes: helping the pilot Firm quietly becoming standing operator access to confidential and privileged matter data, and the opposite failure of refusing to design any operator path so the first real incident is handled through a database console with no auditable record. **The second failure is the one this repository is currently heading toward**, because infrastructure administration has never been designed at all.

This is not resolvable by asserting that operators will be careful. It requires stating what infrastructure administration may reach, what it must be structurally unable to reach, and what happens when the two genuinely collide.

## Decision

### Configuration

1. **Configuration is layered, and only one layer is committed.** Tracked defaults in the repository are **non-secret, non-production values only**, exactly as `.env.example` already is. Every environment-specific and every secret value is supplied at runtime from outside the repository and outside the artefact.

2. **`.env.example` remains a local-development artefact and is never a production template.** **The literal PF-011 values are local defaults and carry no production authority**, and none is inherited by staging or production. Stated explicitly so silence cannot be read as approval: `APP_DEBUG=true`, `display_errors = On` (`docker/php/php.dev.ini`), the literal `onelegalpro`/`onelegalpro_dev_only` database credentials, and `MAIL_MAILER=log` have **no production standing whatever**. Production values are set explicitly by the deploying story, not inherited.

   **This is a statement about authority, not about which driver is correct.** Where a production value happens to coincide with a local one, it does so because **`docs/adr/ADR-024-…` Decision 9a selects it for production on its own reasoning** — not because `.env.example` contained it.

3. **No production artefact contains a secret.** A built image, a repository, a configuration file baked into an image, a CI artefact, and a source file each contain **no** credential, key, token, connection string with an embedded password, or `.env` file. `APP_KEY` in particular is a production secret, not a build input.

4. **Production secrets are held in an external managed secrets boundary**, outside the repository, outside the artefact, and outside ordinary configuration — the "approved, encrypted secret-management boundary with tightly restricted runtime access" `docs/architecture/07_API_Standards.md` §14 already requires. **No secret-management or key-management product, service, or vendor is selected here** (`docs/adr/ADR-022-…` Decision 1); selection requires the owner's provider decision.

   Everything outside that boundary holds an **opaque reference and safe metadata only** — never a secret value.

5. **Prefer short-lived workload identity where the selected platform supports it**, so a running process obtains a credential that is automatically scoped, automatically expiring, and never written down. **Where it is not supported, use rotatable, least-privilege, purpose-bound credentials with bounded rotation overlap** (`docs/architecture/04_Security_Architecture.md` §5). A long-lived, non-rotatable production credential is prohibited, on the same footing as `docs/architecture/07_API_Standards.md`'s prohibition on a long-lived non-rotatable API secret.

6. **Every credential is scoped to exactly one environment and one role.** No credential is shared between local development, CI/test, staging, and production (`docs/adr/ADR-022-…` Decision 2), and **no credential spans the application runtime role and the migration role** (`docs/adr/ADR-024-…` Decision 3; `docs/architecture/03_Database_Design.md` §4.1). A credential that would work in two of those places is a defect.

   **The migration-role credential is non-standing**: it is issued for a named, authorized migration operation, bounded to it, and never left in place afterwards (`docs/adr/ADR-026-…` Decision 5). It is the highest-privilege database credential the platform has — it owns the relations — which is why it exists only for the duration of an operation somebody authorized.

7. **Secrets never enter telemetry.** No credential, key, token, session identifier, recovery material, or connection string appears in a log line, metric label, trace attribute, exception message, error page, event payload, or audit record (Constitution Articles 29, 30; `docs/adr/ADR-025-…` Decision 4). This is enforced by redaction at the emission boundary, not by reviewing logs afterwards.

8. **A leaked or suspected-leaked production secret is treated as compromised and rotated immediately**, and the rotation is recorded. A history rewrite, a revoked commit, or a private repository is **not** remediation — `CONTRIBUTING.md` and `README.md` already state this rule, and it applies unchanged to a secrets boundary, a CI variable, and a provider console. A suspected leak triggers the incident procedure (`docs/adr/ADR-025-…`).

9. **Configuration changes to a deployed environment are authorized, recorded, and attributable** — never an undocumented console edit. Secure defaults apply: the safe configuration is the default one, and opting into risk is explicit, authorized, and recorded (`docs/architecture/04_Security_Architecture.md` §1, §5).

### Production operator access

10. **Production operators have zero standing access to Firm data. This is the governing rule of this ADR, and every decision below serves it.**

    An infrastructure operator holds **no** standing ability to read, export, aggregate, or infer a Firm's `Client`, `Matter`, `MatterClient`, `MatterTeam`, `Task`, conflict-attestation, or activity content. Owning, deploying, or administering the platform confers no access to what is inside a Firm — the same rule `docs/architecture/19_Platform_Administration_Architecture.md` §15 and `docs/adr/ADR-014-…` Decision 2 already state for `PlatformAdministration` and for the operator realm, applied here to infrastructure.

11. **Firm-data access is available through exactly one path: IdentityAccess's `PrivilegedAccessGrant`.** Purpose-bound, time-limited, strongly authenticated with step-up, dually attributed with **no silent impersonation**, and recorded in a **Firm-visible, append-only support-access history** (`docs/adr/ADR-014-…` Decisions 1, 3, 4, 5; Constitution Article 29).

    **Infrastructure administration is not that path and never becomes it.** There is no infrastructure route to Firm data — not a shell, not a database prompt, not a log, not a backup, not a metrics dashboard, not a support tool.

12. **Infrastructure administration is defined by what it operates on, and it operates on the platform, never on Firm content.** Legitimately in scope: deploying and rolling back an application artefact; executing a migration under `docs/adr/ADR-026-…`; restarting, scaling, or supervising a process; reading operational telemetry that carries no Firm content by construction (`docs/adr/ADR-025-…`); managing certificates, networking, and platform configuration; and executing a backup or a restore under `docs/adr/ADR-024-…`.

    **Structurally out of scope: reading Firm business rows.** The separation is made real by the same mechanisms the persistence architecture already establishes, not by policy alone:

    - the **application runtime role** is not the owner, holds no DDL, no `BYPASSRLS`, and no superuser capability (`docs/architecture/03_Database_Design.md` §4.1);
    - **`FORCE ROW LEVEL SECURITY` removes the owner's ordinary exemption from its own policies** (§3.3.2), so even the migration role — which owns the relations — cannot read every Firm's rows in one pass (`docs/adr/ADR-024-…` Decision 3a);
    - **Firm context is transaction-scoped and built only from verified identity and membership** (§4.2, §4.3), so no operator can synthesize it from a header, a parameter, or a console;
    - **unset or malformed Firm context fails closed and fails loudly** (§3.4);
    - **no reporting or analytics role exists** (§4.1), so there is no cross-Firm read role to inherit.

    **These are ARCH-012's approved controls, and this ADR neither creates nor weakens any of them.**

13. **A production database credential capable of reading Firm business rows is a privileged-access mechanism, and therefore may not exist as a standing operator credential.** Concretely:

    - **No human holds a standing PostgreSQL superuser credential, a standing `BYPASSRLS` credential, a standing migration-role credential, or a standing credential able to disable Row-Level Security, drop a policy, or alter a grant.**
    - The **migration-role credential is non-standing**: issued for a named, authorized migration operation and bounded to it (`docs/adr/ADR-026-…` Decision 5). Because that role owns the relations, a standing copy of it would be the most privileged persistent credential in the platform.
    - **The migration role is never used to serve a request, and never used for operational inspection** (`docs/architecture/03_Database_Design.md` §4.1). "Connect as the migration role to look at something" is not a permitted use, whatever the urgency.
    - Where a platform's construction makes some superuser-equivalent capability unavoidable — a managed-database provider's own administrative account, a host root account — **that capability is named explicitly, its holder is named, its use requires authorization and produces a record, and it is never used to read Firm business content.** It is recorded as a residual risk (Decision 17), not concealed.

14. **A genuine operational need to inspect Firm data does not create a new path; it uses the existing one or it is refused.** If diagnosing an incident requires seeing a Firm's actual content, the correct response is an IdentityAccess `PrivilegedAccessGrant` under ADR-014 — purpose-bound, time-limited, Firm-visible. **"It was faster through the database" is not an authorization.** Where the grant mechanism does not yet exist because Release 0.1 has not been built, the correct response is that the inspection does not happen and the limitation is recorded in the incident record (`docs/adr/ADR-025-…` Decision 10).

15. **Prohibited during infrastructure administration, on the same footing as `docs/adr/ADR-014-…` Decision 8 and `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §39** — and none of these becomes permissible through urgency, purpose wording, or a Firm's own request:

    - reading, exporting, copying, or transmitting a Firm's business content;
    - disabling Row-Level Security, dropping a policy, granting `BYPASSRLS`, or otherwise removing the isolation boundary (`docs/adr/ADR-020-…` Decision 4 already prohibits this for migrations; it is prohibited for operations generally);
    - editing, deleting, or truncating audit, support-access history, or any append-only record, or disabling a trigger or grant that enforces append-only behaviour;
    - granting, broadening, or self-granting any role, capability, membership, session, delegation, or privileged grant;
    - creating or using any impersonation, "log in as", or session-forging capability;
    - changing a Firm's `SubscriptionEntitlement`, seat limit, or lifecycle state outside `PlatformAdministration`'s own authorized commands;
    - moving client money — should that capability ever exist (`Billing`/EPIC-008 is out of Release 0.1 scope entirely).

16. **Break-glass remains excluded as a capability, and this ADR does not introduce one.** `docs/adr/ADR-014-…` Decision 6 excludes the capability while Constitution Article 29's break-glass rules remain fully in force and unamended. **Infrastructure administration is never used for break-glass purposes**, and the absence of a break-glass capability is never a reason to route an emergency through a database prompt. Any future break-glass capability requires its own separately approved decision satisfying Article 29 in full.

17. **Residual risk is stated rather than concealed, exactly as `docs/adr/ADR-018-…` and `docs/adr/ADR-020-…` Decision 7 do for audit enforcement.** The controls in Decision 12 bind every actor operating through the application, and the role separation in Decision 13 binds every ordinary operational path. **They are not claimed to restrain an actor holding a platform's own root or provider-administrator capability**, who could alter grants, disable a trigger, or read storage directly. What restrains that actor is role separation, the approval gates in `AGENTS.md`, the recorded-and-authorized-use rule in Decision 13, and organizational control — **procedural controls, which this ADR does not represent as technically enforced.** Naming this honestly is the point: an overstated control is a safety hazard.

18. **Every access decision here is an authorization matter and hits the `AGENTS.md` approval gate.** Operator access, privileged credentials, and role or grant changes are authentication/authorization changes requiring explicit human approval before merge, independently of any database gate — the same rule `docs/adr/ADR-020-…` Decision 5 applies to policy and grant migrations.

19. **AI holds no authority anywhere in this ADR.** AI never holds, reads, retrieves, rotates, or issues a secret or credential; never receives shell, database, or console access (Constitution Article 40's structural prohibition on unrestricted shell, database, or credential access); never authorizes, approves, or performs operator access; never approves its own proposal; and is never an authorization authority. **Release 0.1 contains no AI capability.**

20. **This ADR asserts no story status and schedules nothing.** The PostgreSQL CI requirement now carries the identifier **`PF-033`**; this ADR asserts no status for it and neither schedules nor renumbers it. `docs/PROJECT_STATUS.md` is authoritative. The four required `Protect main` check names are preserved exactly.

## Alternatives considered

- **Grant the on-call engineer a read-only production database credential "for debugging".** Rejected, and this is the central rejection of this ADR. A read-only credential over a shared-schema database reads **every Firm's** Clients, Matters, and conflict attestations — parties who may be adverse to one another (`docs/architecture/03_Database_Design.md` §20.1). It is standing, unbounded by purpose, invisible to the Firm, and outside the support-access history Constitution Article 29 requires. It is the second privileged-access mechanism `docs/adr/ADR-014-…` Decision 1 says does not exist, with none of the review that would justify one.
- **Say nothing about infrastructure access and let implementation decide.** Rejected — this is the failure `docs/adr/ADR-014-…`'s Context paragraph names explicitly: the first real support incident handled through a database console, leaving no auditable record. Silence is not neutrality here; it is the worst available outcome.
- **Route infrastructure administration through `PrivilegedAccessGrant` as well**, making one mechanism cover both. Rejected — it would misdescribe what is happening. A `PrivilegedAccessGrant` is Firm-scoped, purpose-bound, and **Firm-visible**; restarting a queue worker concerns no Firm and would pollute a Firm's support-access history with events that are not about them. Decision 12 separates the two by what they operate on, which is the honest distinction, and Decision 14 sends any genuine Firm-data need back to the grant.
- **Create a separate "infrastructure break-glass" credential** for emergencies. Rejected — `docs/adr/ADR-014-…` Decision 6 excludes break-glass as a capability, and a correct break-glass mechanism requires independent approval, an exceptional-use policy, immutable audit separation, and Firm surfacing that a founding-firm pilot cannot meaningfully exercise. Building a weak version would create a bypass with none of the review that justifies one.
- **Store production secrets in CI repository secrets and inject them at deploy time.** Rejected as the primary boundary. CI secrets are readable by whoever can modify a workflow, are not purpose-scoped per runtime role, have no rotation discipline of their own, and would put production credentials one workflow edit away from exfiltration. The repository's own posture already reflects this concern: both existing workflows declare `permissions: contents: read` and use **no secrets at all**.
- **Keep a production `.env` file on the host, restricted by file permissions.** Rejected — it puts plaintext secrets on disk in the environment with the widest operator population, survives in backups and images, and makes rotation a manual file edit. Decision 4 puts them behind a managed boundary; Decision 3 keeps them out of the artefact.
- **Select a specific secrets-management product now.** Rejected for the same reason `docs/adr/ADR-022-…` Decision 1 defers provider selection: it is entangled with the owner's hosting-jurisdiction and subprocessor decisions. Decision 4 states the required properties so the selection is a comparison rather than a rediscovery.
- **Rely on audit logging alone to make operator database access safe.** Rejected — logging an access does not prevent it, does not make it purpose-bound, does not surface it to the Firm, and (per `docs/architecture/03_Database_Design.md` §20.10 and `docs/adr/ADR-020-…` Decision 7) an actor able to alter grants is an actor able to affect the record. Prevention by role separation comes first; the record supports it rather than replacing it.
- **Claim the role separation fully prevents operator access to Firm data.** Rejected as an overstatement. A provider-administrator or host-root capability exists on any real platform. Decision 17 states the residual honestly, matching the precedent `docs/adr/ADR-018-…` Decision 2a and `docs/adr/ADR-020-…` Decision 7 set for audit enforcement.

## Consequences

- **Debugging production is materially harder.** An engineer cannot look at the data. Diagnosis relies on telemetry that carries no Firm content, synthetic reproduction, and — where genuinely necessary — an ADR-014 grant with a Firm-visible record. This is a real cost, deliberately accepted for a platform holding privileged legal work.
- **Some incidents will not be fully diagnosable**, and `docs/adr/ADR-025-…` requires recording that as a limitation rather than resolving it by exception.
- **A secrets boundary must exist and be selected before production**, which is blocked on the owner's provider decision. That dependency is visible now rather than at the target date.
- **Credential count grows**: per environment, per role, non-shared. That is more issuance and rotation work, and it is what makes any single leak bounded.
- **Rotation becomes an operational obligation** with bounded overlap, needing a procedure that does not exist yet.
- **The residual risk in Decision 17 does not go away.** It is bounded and named, not eliminated, and any organization-level control over it sits outside this repository.
- **Infrastructure administration is deliberately narrow**, which will occasionally feel obstructive. That friction is the control working.

## Security and professional-responsibility consequences

1. **An operator reading a Firm's Matters is a confidentiality and privilege event, not a support convenience.** `docs/adr/ADR-014-…` Decision 2 and Constitution Article 29 already establish it for the operator realm; Decision 10 closes the infrastructure route that would otherwise bypass both. In a legal-practice platform the affected parties may be adverse to one another, and the disclosure is unremediable once made.

2. **A standing credential is the specific failure mode.** Purpose-bound and time-limited are what make privileged access reviewable; a credential that always works is reviewable by nobody and is one compromise away from every Firm's data.

3. **Silent access defeats the Firm-visible support-access history Constitution Article 29 requires.** A Firm that can see when an operator entered can ask why; a Firm that cannot see it has no accountability at all. An infrastructure path is invisible to that history by construction, which is exactly why Decision 11 refuses to let one exist.

4. **Secrets in telemetry are a compounding failure.** A credential in a log line is copied into every log sink, backup, and aggregation index, and Constitution Article 29 forbids it precisely because the copies outlive the incident. Decision 7 redacts at emission rather than trusting review.

5. **Disabling Row-Level Security "temporarily" is the highest-severity prohibited action in Decision 15.** `docs/adr/ADR-020-…` Decision 4 and `docs/architecture/03_Database_Design.md` §15.5 already forbid it for migrations, and the reason generalizes: a failed or abandoned operation can leave the isolation control off, silently, with nothing failing.

6. **Stating the residual risk honestly is itself a control.** `docs/adr/ADR-020-…`'s Security consequences make the same point about reversibility: an overstated claim is a safety hazard, because it stops people from applying the compensating control they would otherwise apply.

7. **No certification, compliance, or legal-sufficiency conclusion is asserted.** The **ARCH-012 Thai-qualified persistence review was completed and approved on 1 August 2026** (`docs/legal/ARCH-012-Thai-Legal-Review.md`); the **separate Thai-qualified review required by `docs/adr/ADR-012-…` Decision 8** — covering the Privacy Notice, Terms, pilot agreement, and required disclosures — **has not occurred.** **No production access has been authorized, and the complete production-access gate is not satisfied**; of its seven evidence items only the **approved database-design** item is presently satisfied, and approval of this document would satisfy the **deployment-architecture** item only. No control described here is claimed to be implemented, tested, or effective.

## Integration consequences

- **`docs/adr/ADR-014-…`** is extended, not amended. `PrivilegedAccessGrant` remains IdentityAccess's sole mechanism for Firm-data access; break-glass remains excluded as a capability with Constitution Article 29 in force; the Firm-visible append-only support-access history remains IdentityAccess's.
- **`docs/adr/ADR-022-…`** supplies the four ordinary environment classes and the ephemeral recovery boundary this ADR scopes credentials to, and Decision 6's no-secret-in-artefact rule pairs with ADR-022 Decision 6's production build.
- **`docs/adr/ADR-024-…`** inherits Decisions 12, 13, and 15 for database roles, and its restore procedure inherits the prohibition on using a restore as a data-inspection path.
- **`docs/adr/ADR-025-…`** inherits Decision 7's redaction rule as a telemetry design constraint and Decision 14's limitation for incident investigation.
- **`docs/adr/ADR-026-…`** inherits Decision 13's non-standing migration credential and Decision 18's approval gate.
- **IdentityAccess (EPIC-009)** remains the sole owner of principals, credentials, sessions, and privileged-access grants. Nothing here creates an identity, a session, an authenticator, or an authorization decision.
- **`PlatformAdministration` (EPIC-012)** is unaffected: it still authenticates nothing, authorizes nothing, defines no operator access path, and never reads Firm business data.
- **Practice Management** remains the sole Ethical Wall authority; Release 0.1 has no Ethical Walls (`docs/adr/ADR-015-…`), and **support access gains nothing from that absence** (`docs/adr/ADR-014-…` Decision 7).

## Explicit non-goals

This ADR does **not**: implement, provision, configure, issue, rotate, or store any credential, key, secret, token, or configuration value; create or modify any environment file, configuration file, Docker file, `compose.yaml`, CI workflow, source file, test, migration, schema, role, grant, policy, dependency, or GitHub setting; select, endorse, evaluate, price, or recommend a secret-management product, key-management service, HSM, identity provider, hosting provider, or any other vendor, provider, product, or package; decide hosting jurisdiction, data residency, or subprocessor approval; create, define, approve, or imply a second privileged-access mechanism, an impersonation or "log in as" capability, a session-forging capability, a support console, a platform back office, or an administrative override of any kind; create or approve a break-glass capability, or read `docs/adr/ADR-014-…` Decision 6 as permitting one; amend, weaken, reinterpret, or create an exception to `docs/adr/ADR-014-…`, `docs/adr/ADR-009-…`, or Constitution Articles 26–30 and 45–48; define authentication, authorization, session, MFA, recovery, invitation, membership, or entitlement behaviour, all of which remain IdentityAccess's and `PlatformAdministration`'s; define or approve a Firm-level suspension or emergency-disable capability; create a cross-Firm read role, view, projection, report, or analytics capability, or a Reporting bounded context — which Constitution Article 44 reserves and which this ADR **neither approves nor permanently prohibits**; authorize any restore of production data into a non-production environment; define, execute, schedule, or claim a backup or restore test; define or claim an RPO or RTO value; supply any parameter, procedure, or evidence that `docs/legal/ARCH-012-Thai-Legal-Review.md` leaves to separately approved follow-up, or assert any notification outcome or timeline; assert any story's status, or schedule, rename, or renumber `PF-033` or any operations requirement; change `phpunit.xml`, any CI workflow, or the four required `Protect main` check names; introduce an AI capability, grant AI any authority, or modify `docs/architecture/05_AI_Architecture.md`; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness; claim that any described control is implemented, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

**Proposed conceptual architecture only.** It authorizes no application code, infrastructure, credential, secret store, environment, deployment, or production access. **No secrets boundary exists, no production credential has been issued, no operator access path has been built, and no control described here is claimed to be implemented, tested, or effective.**

**No story's status is asserted here; `docs/PROJECT_STATUS.md` is the authoritative record.** The story ID is **ARCH-013**; the accompanying architecture document is `docs/architecture/20_Deployment_Operations_Architecture.md`.
