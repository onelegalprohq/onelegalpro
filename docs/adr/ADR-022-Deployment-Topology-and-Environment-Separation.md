# ADR-022 — Deployment Topology and Environment Separation

## Status

**Accepted.** Explicit repository-owner approval was recorded on PR #35 on 1 August 2026 after independent review and all four required `Protect main` checks passed on commit `a163fce`. Acceptance authorizes this architectural decision only, never implementation, deployment, provider engagement, expenditure, or production access.

Authored by story **ARCH-013 — Deployment & Operations Architecture**, alongside `docs/architecture/20_Deployment_Operations_Architecture.md` and `docs/adr/ADR-023-…` through `ADR-026-…`.

**Depends on `docs/architecture/03_Database_Design.md` and `docs/adr/ADR-016-…` through `ADR-021-…`, which are Approved and Accepted** — explicit repository-owner approval recorded on PR #34 on 1 August 2026, alongside the eight Thai-qualified legal-review decisions in `docs/legal/ARCH-012-Thai-Legal-Review.md`. **ARCH-013 is synchronized with that approved baseline.** ARCH-012's approval schedules no implementation and authorizes no deployment or production access, and neither does this ADR.

## Context

`docs/architecture/02_Product_Requirements.md` §8 and `docs/adr/ADR-012-Release-0-1-Product-Scope-and-Matter-Desk-Slice.md` Decision 10 make **an approved deployment architecture** one of seven recorded evidence items required before production access. No document owns it. `docs/architecture/03_Database_Design.md` §19 and §22.1 explicitly hand six items to "the deployment architecture" by name, and `docs/adr/ADR-020-Migration-Rollback-and-Schema-Evolution.md` names four constraints "the future deployment architecture must accommodate". `docs/architecture/04_Security_Architecture.md` §5 names **environment separation** and **secure deployment and configuration** as control families with no design behind them.

Four things about the repository's current state make this decision necessary now rather than later.

1. **The only deployment-shaped artefacts that exist are explicitly development-only.** `compose.yaml`, `Dockerfile`, `docker/php/php.dev.ini`, `docker/nginx/default.conf`, and `.env.example` each say so in their own header comments. They carry literal development credentials, `APP_DEBUG=true`, `display_errors = On`, development Composer dependencies, and no container health check. **If this architecture is silent, those values become production by default** — not by decision, but by inheritance.

2. **There is no staging environment and no concept of one.** `docs/architecture/07_API_Standards.md` §14 requires "sandbox/production isolation with no default copy of production Firm data into a sandbox", which presumes at least two deployed environments. `docs/architecture/03_Database_Design.md` §16.4 and §17.5 establish **synthetic data only** in ordinary non-production, and `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 6 now confirms it while carving out one narrow, controlled exception for restore testing. Neither can be satisfied by an environment set that does not exist.

3. **Provider selection has been refused by every approved architecture to date**, deliberately and repeatedly (`docs/architecture/03_Database_Design.md` §19; `docs/adr/ADR-020-…` Explicit non-goals; `docs/architecture/16_Identity_Security_Access_Control_Architecture.md` §656; `docs/architecture/19_Platform_Administration_Architecture.md`). A deployable Release 0.1 nonetheless requires one eventually. The question is whether *this* document makes that selection.

4. **Constitution Article 46 forbids inventing a Firm-level suspended state**, and `docs/architecture/02_Product_Requirements.md` §5 repeats it. A deployment architecture that introduces "maintenance mode" without stating what it is not would create exactly the capability Article 46 reserves for its own separately approved decision.

## Decision

1. **This architecture is vendor-neutral. No hosting provider, cloud platform, managed database service, region, container orchestrator, connection pooler, backup product, secret-management product, key-management service, monitoring or observability product, log-aggregation service, CDN, or any other vendor, provider, or package is selected, endorsed, or evaluated here.** It defines **properties, classes, roles, and constraints** that a future selection must satisfy.

   **Provider procurement and vendor selection require their own separately approved owner decision.** That decision is additionally constrained by two items this ADR records as open (Decision 12): hosting jurisdiction and data residency, and subprocessor approval. **No expenditure, contract, trial, or account creation is authorized by this ADR.**

2. **Four ordinary environment classes are defined.**

   | Environment | Purpose | Firm data | Provisioning |
   |---|---|---|---|
   | **Local development** | A developer's own machine | **Never** — synthetic only | The existing `compose.yaml` stack (`PF-010`, `PF-011`) |
   | **CI/test** | Automated verification on pull requests | **Never** — synthetic only | Ephemeral, created and destroyed per job |
   | **Staging** | Pre-production verification of a release candidate and of the deployment procedure itself | **Never** — synthetic only | Deployed, persistent, separately credentialed |
   | **Production** | The service a pilot Firm uses | Authoritative | Deployed, persistent, separately credentialed |

   **An environment is not a configuration flag**: each has its own credentials, its own secrets boundary entry (`docs/adr/ADR-023-…`), its own database, and its own operator access posture. **No credential, secret, key, database, backup, connection string, or service account is shared between any two environments** — including between local development and CI/test.

   **Separately, one ephemeral production-controlled recovery boundary may be provisioned solely for an authorized restore test** (Decision 3a). It is **not an ordinary environment class**: it is **not a reusable application environment**, **not staging**, and **not a fifth ordinary class**. It **inherits production classification and controls**, exists only for the duration of the authorized test, and **must be destroyed under the approved retention and deletion procedure**. Adding an ordinary environment class beyond the four requires its own approved decision.

3. **Ordinary non-production is synthetic data only.** **No production database, production backup, production export, or production log may be restored, copied, imported, or streamed into local development, CI/test, or staging.** This is the approved rule, not a placeholder: `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 6 confirms that ordinary non-production environments use synthetic data only, and that a restored copy **must not populate a general development, test, demonstration, or analytics environment.**

   This is not a hygiene preference. A restore of production into staging places Confidential and Privileged/Restricted material (`docs/architecture/04_Security_Architecture.md` §7) into an environment with weaker controls, for clients who are not party to that decision.

3a. **The one approved exception is a restore test, and it does not run in any of the four ordinary environment classes.** Where a restore test necessarily uses production data, `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 6 permits it **only in an isolated, production-controlled recovery environment**, under **every** one of the following, none severable: **explicit authorization**; **least-privilege access**; **access logging**; **encryption**; **an approved retention and deletion procedure**; and **recorded completion**. **Masking, pseudonymization, or subsetting is not presumed sufficient without a separately recorded assessment.**

   Two consequences follow, and both are load-bearing:

   - **The recovery boundary is not an ordinary environment class.** It is *production-controlled* — provisioned, credentialed, access-logged, and destroyed under production's own controls and classification — **ephemeral**, and **never** reachable from, credentialed alongside, or reused as staging, CI/test, or local development. It is **not a reusable application environment**, and it does not become one by being provisioned twice.
   - **Neither staging nor any other environment gains a production-data path from this.** Decision 4's prohibition on reproducing an incident with production data is unchanged, and `docs/adr/ADR-024-…` Decision 15 governs what the restore test must demonstrate.

   **The approved retention and deletion procedure for a restored copy does not yet exist** — `docs/legal/ARCH-012-Thai-Legal-Review.md` follow-up items 1 and 5 record it, and it must be approved before a production-data restore test is run.

4. **Staging is a verification environment, never a demonstration or support environment.** It never holds a real Firm's data, never serves a real Firm's users, and is never used to reproduce a production incident with production data. Where an incident cannot be reproduced on synthetic data, that is recorded as a limitation of the incident investigation (`docs/adr/ADR-025-…`), not resolved by relaxing Decision 3.

5. **The production topology is defined by role, not by product.** Release 0.1 requires exactly these runtime roles, and no more:

   | Role | Responsibility | Notes |
   |---|---|---|
   | **HTTP ingress / TLS termination** | Terminates TLS, forwards to the web role | Serves `public/` only; never exposes `storage/`, `vendor/`, dotfiles, or `.env` |
   | **Web application** | Serves authenticated Firm requests | Stateless with respect to Firm data; holds no authoritative state |
   | **Queue worker** | Processes queued work, including outbox publication when `PF-091` exists | Supervised per `docs/adr/ADR-025-…` Decision 7 |
   | **Scheduled task runner** | Runs time-triggered work, where an approved story introduces any | **None exists in Release 0.1**; the role is named so it is not improvised later |
   | **PostgreSQL** | The single authoritative store | `docs/adr/ADR-024-…` |
   | **Migration execution context** | Runs schema changes as an explicit controlled operation | Never a running application process; `docs/adr/ADR-026-…` |

   **Redis is not a Release 0.1 production runtime role** (`docs/adr/ADR-024-…` Decision 9). **No object storage, search engine, vector database, message broker, or AI processor is a Release 0.1 runtime role**, because no Release 0.1 capability requires one — Documents, Legal Intelligence, Integrations, and every AI capability are out of scope (`docs/architecture/02_Product_Requirements.md` §3).

6. **The production application artefact is distinct from the development image.** The existing `Dockerfile` is development-only by its own declaration and **must never be deployed to staging or production**. A production artefact is a separate, separately reviewed build that at minimum:

   - installs **no development dependencies**;
   - sets `APP_DEBUG=false` and disables `display_errors`, so a stack trace, query, or configuration value never reaches an HTTP response (`docs/architecture/07_API_Standards.md` §19 prohibits exposing a stack trace);
   - runs application processes as a **non-root** user;
   - contains **no `.env` file, no secret, and no credential** (`docs/adr/ADR-023-…` Decision 3);
   - excludes `docs/`, `.git`, tests, and development tooling from the image context;
   - is **immutable and identified by digest**, so what was verified in staging is byte-identical to what runs in production.

   **The same artefact digest is promoted through staging to production.** A rebuild between staging verification and production deployment invalidates the verification.

7. **Ingress is deny-by-default at the network boundary.** PostgreSQL and every internal service accept connections only from the roles that need them, and are **never publicly reachable**. The existing development stack already establishes this precedent — `compose.yaml` publishes no port at all for PostgreSQL and Redis and binds every other published port to `127.0.0.1`. Production preserves the property; it does not weaken it because production is "behind a firewall".

8. **Encryption in transit is mandatory on every hop that leaves a host**, including application-to-database, and encryption at rest is mandatory for the database and for every backup (`docs/architecture/04_Security_Architecture.md` §5; `docs/architecture/03_Database_Design.md` §17.1). **No key-management product is selected** (`docs/adr/ADR-023-…`).

9. **Planned platform maintenance is platform availability management. It is never a Firm-level suspended state and never a per-Firm disable.**

   - It applies to the **whole platform**, never to one Firm or a subset of Firms.
   - It **creates, changes, and reads no `Firm` state**, no `FirmProvisioning` state, and no `SubscriptionEntitlement` state.
   - It is never described, labelled, surfaced, or audited as a Firm suspension, and never used to achieve the effect of one.
   - **Constitution Article 46 is unamended.** A Firm-level suspension or emergency-disable capability remains reserved for its own separately approved decision, which would have to define the authorizing authority, session effects, recovery path, Firm notification, the treatment of queued and in-flight work, and audit semantics distinguishing it from both entitlement lapse and membership revocation. **Nothing in this ADR may be read as authorizing one.**
   - Maintenance affecting availability is a **planned, announced, human-authorized operation with a recorded window**, and the announcement is an operator communication, not a product state.

10. **A deployment is a human-authorized, recorded operation.** No deployment is triggered automatically by a merge, a schedule, or an external event without an approved operational procedure (`docs/adr/ADR-026-…`). The existing CI workflows contain no deployment trigger or step, and this ADR adds none.

11. **No availability, capacity, scaling, or continuity property is claimed.** Specifically: **no high-availability posture, no failover, no autoscaling, no zero-downtime deployment, no zero-downtime migration, no multi-region or multi-zone posture, no throughput or latency target, and no uptime figure** is designed, promised, or implied. Release 0.1 is a founding-firm pilot; a single modest deployment is the honest description, and stating it plainly is preferable to implying resilience that does not exist. Any of these properties would require its own approved decision and its own budget (Decision 12).

12. **The following are recorded as open and are the owner's, not the CTO's**, and **no value, provider, or jurisdiction is invented, defaulted, or implied anywhere in this ADR**:

    - **hosting jurisdiction and data residency** for Thai law-firm client and matter data;
    - **provider and subprocessor approval** — which organizations may process, store, or transit Firm data;
    - **availability or uptime commitments** to a pilot Firm, which are contractual acts belonging to the pilot agreement (draft pending Thai-qualified legal review, `docs/adr/ADR-012-…` Decision 8);
    - **budget, and whether any paid high-availability posture is procured at all.**

    Each is marked *Not decided — owner/legal decision required* in `docs/architecture/20_Deployment_Operations_Architecture.md` §17.

13. **This ADR asserts no story status and schedules nothing.** The PostgreSQL continuous-integration requirement now carries the identifier **`PF-033`**, whose story contract is defined in `docs/implementation/03_Engineering_Backlog.md` and which must land before `PF-080`. **No status is asserted for it, and it is neither scheduled nor renumbered here.** **ARCH-012 §22.1 still records that identifier as unassigned — superseded by the identifier's own approved story contract, and correcting it is ARCH-012's tracking-synchronization item, not this ADR's.** `docs/PROJECT_STATUS.md` remains the authoritative record of what is current, next, and complete. **The four required `Protect main` check names — `PHP Code Quality`, `Frontend Build`, `Application Tests`, `Dependency Audit` — are preserved exactly.**

## Alternatives considered

- **Select a hosting provider now, so Release 0.1 can actually be deployed.** Rejected for this ADR, not permanently. Provider selection is entangled with two owner/legal questions that are open (hosting jurisdiction, subprocessor approval) and with budget. Making it here would either pre-empt the owner or silently assume a jurisdiction — and for a Thai legal-practice platform, where client data sits is a professional-responsibility question before it is an engineering one. Decision 1 keeps the constraint set complete so the selection, when made, is a comparison rather than a rediscovery.
- **Three environments (local, CI, production), with staging dropped.** Rejected. Without staging there is nowhere to verify the deployment procedure, the migration procedure (`docs/adr/ADR-026-…`), or a release candidate before a Firm's data is exposed to it. The first execution of a never-rehearsed migration would be against live client matters. `docs/architecture/07_API_Standards.md` §14 already presumes sandbox/production isolation.
- **Five or more environments** (adding QA, demo, or per-developer cloud environments). Rejected as unjustified for a founding-firm pilot: each additional environment is another credential set, another secrets-boundary entry, another drift surface, and another place a production restore could be requested "just this once".
- **Allow a masked or subsetted production restore into staging.** Rejected, and now on approved grounds rather than deferral. `docs/legal/ARCH-012-Thai-Legal-Review.md` Decision 6 records that **masking, pseudonymization, or subsetting is not presumed sufficient without a separately recorded assessment**, and that a restored copy **must not populate a general development, test, demonstration, or analytics environment**. Decision 3a therefore routes the only permitted production-data restore into an isolated, production-controlled recovery environment — not into staging.
- **Reuse the development `Dockerfile` in production with environment variables flipped.** Rejected. It installs development dependencies (PHPUnit, Pint, Faker) into the runtime, copies the entire build context, and pairs with a php.ini that turns `display_errors` on. Each is a distinct exposure, and "we set `APP_DEBUG=false`" addresses none of them.
- **Rebuild the artefact for production after staging verification.** Rejected — it means the verified artefact and the deployed artefact are different objects, which makes staging verification evidence of nothing. Decision 6 promotes one digest.
- **Introduce a per-Firm maintenance or "read-only" mode** for operational convenience during migrations. Rejected outright: it is a Firm-level disable in everything but name, and Constitution Article 46 reserves that capability for its own approved decision with an explicitly enumerated design. Decision 9 states what maintenance is *not*, precisely so this does not arrive later as an implementation detail.
- **Claim a modest high-availability posture** ("two web processes behind a load balancer") as though it were HA. Rejected — two application processes in front of a single database is not high availability, and describing it as such would be a false assurance to a Firm relying on it. Decision 11 refuses the claim rather than qualifying it.
- **Defer environment separation until a provider is chosen.** Rejected — the separation rules are provider-independent, and they are what a provider selection must satisfy. Deciding them after selection means retrofitting them onto whatever the provider made easy.

## Consequences

- **A staging environment must exist and be paid for** before the production-readiness gate can be satisfied, because the deployment and migration procedures are verified there (`docs/adr/ADR-026-…`). That is a real cost, and it is the owner's budget decision (Decision 12).
- **Synthetic data becomes load-bearing.** Realistic Thai-text fixtures, realistic Matter volumes, and realistic Firm counts have to be constructed deliberately, because production data can never substitute for them. This is more work than a restore, and it is the correct trade.
- **Incident reproduction is harder.** An investigation that would be trivial with a production copy is bounded to synthetic reproduction plus telemetry. `docs/adr/ADR-025-…` accepts and records this rather than resolving it by exception.
- **A second, production-grade build path must be created and maintained**, distinct from the development image, with its own review.
- **Vendor-neutrality defers a decision that eventually blocks deployment.** Nothing deploys until the owner selects providers. Recording that plainly is the point: the gap is visible now rather than at the 15 September target date.
- **Four ordinary environment classes mean four credential sets and four secrets-boundary entries**, all non-shared, plus a separately credentialed recovery boundary whenever an authorized restore test runs. That is more configuration surface, and it is what makes a leaked staging credential worthless against production.
- **No HA means a single database is a single point of failure.** Accepted and stated, not concealed. Whether to buy resilience is Decision 12's budget question.

## Security and professional-responsibility consequences

1. **Environment separation is a confidentiality control, not an engineering convention.** In a legal-practice platform, a production restore into staging exposes privileged client work product to an environment with weaker access control, weaker audit, and a wider operator population — for clients who never consented and are not told. Decision 3 is why that requires legal review and an explicit authorization rather than an engineer's judgement.

2. **A shared credential between environments collapses the separation entirely.** If staging can reach production's database, staging *is* production for confidentiality purposes, whatever the diagram says. Decision 2's no-sharing rule is what makes the boundary real.

3. **The development artefact is an exposure if deployed.** `display_errors = On` plus `APP_DEBUG=true` turns any unhandled exception into a disclosure of configuration, query text, and file paths, and `docs/architecture/07_API_Standards.md` §19 already prohibits exposing a stack trace through a public response. Decision 6 removes the possibility structurally rather than relying on an environment variable being set correctly.

4. **Maintenance mode is one careless design away from becoming the Firm-level disable Constitution Article 46 forbids inventing.** The dangerous version is not malicious — it is a well-meant "let us pause Firm X while we fix their data", which is a Firm-wide disable with no authorizing authority, no defined session effect, no recovery path, no notification, and no audit semantics. Decision 9 forecloses it by stating what maintenance is not.

5. **Deny-by-default network exposure is defence in depth behind Firm isolation, never a substitute for it.** `docs/architecture/03_Database_Design.md` §3.1 and §20.2 are explicit that Row-Level Security is defence in depth and never the primary control; the same logic applies here in the other direction. A network boundary does not authorize anything, and no application-layer check may be relaxed because a service is "internal".

6. **Refusing an availability claim is a professional-responsibility position, not modesty.** A Firm that believes the platform is highly available makes different decisions about where its deadline data lives. Decision 11 refuses the claim so the Firm's reliance matches reality, on the same discipline `docs/adr/ADR-015-Deferred-Professional-Responsibility-Controls.md` applies to absent controls.

7. **AI holds no authority here.** AI never selects, provisions, configures, deploys, or destroys an environment; never authorizes a data movement between environments; never approves a maintenance window; and is never an authorization authority (Constitution Articles 6, 26, 28, 39, 40). **Release 0.1 contains no AI capability.**

8. **No certification, compliance, or legal-sufficiency conclusion is asserted.** The **ARCH-012 Thai-qualified persistence review was completed and approved on 1 August 2026** (`docs/legal/ARCH-012-Thai-Legal-Review.md`); the **separate Thai-qualified review required by `docs/adr/ADR-012-…` Decision 8** — covering the Privacy Notice, Terms, pilot agreement, and required disclosures — **has not occurred.** **No production access has been authorized, and the complete production-access gate is not satisfied**; two of its seven evidence items are presently satisfied: the **approved database design** and this **approved deployment architecture**.

## Integration consequences

- **`docs/adr/ADR-023-…`** inherits the four-environment set as the unit of secret scoping and operator-access scoping.
- **`docs/adr/ADR-024-…`** inherits the environment set for database provisioning, and the encryption-at-rest and no-public-exposure requirements.
- **`docs/adr/ADR-025-…`** inherits the environment set for telemetry separation, and Decision 4's constraint on incident reproduction.
- **`docs/adr/ADR-026-…`** inherits Decision 6's single-digest promotion, Decision 10's human-authorized deployment, and staging as the environment where a release candidate and its migration are verified.
- **`docs/architecture/03_Database_Design.md` §16.4 and §17.5** are satisfied by Decision 3 rather than restated by it; the conservative rule those sections establish is carried, not weakened.
- **The PostgreSQL CI requirement** (`PF-033`, story contract defined in `docs/implementation/03_Engineering_Backlog.md`) operates in the CI/test environment defined by Decision 2 and is unchanged by this ADR. **No status is asserted for it.**
- **The operator's public marketing website** (`docs/architecture/02_Product_Requirements.md` §7; backlog EPIC-012 story 32) is an **operator surface outside `app/Modules` and outside the Digital Presence bounded context**. It is not a Firm tenant website and not the Client Portal. Whether it shares any deployment infrastructure with the application is not decided here and requires its own recorded decision; it creates no Client Portal principal, no `ClientPortalAccessProfile`, and no Firm membership either way.

## Explicit non-goals

This ADR does **not**: implement, provision, configure, purchase, contract for, or deploy anything; create or modify any Docker file, `compose.yaml`, CI workflow, environment file, configuration file, migration, schema, source file, test, dependency, or GitHub setting; select, endorse, evaluate, benchmark, price, or recommend a hosting provider, cloud platform, region, managed database, container orchestrator, connection pooler, load balancer, CDN, secret-management or key-management product, monitoring or observability product, log-aggregation service, backup product, or any other vendor, provider, product, or package; decide hosting jurisdiction, data residency, or subprocessor approval; authorize any expenditure, contract, trial, or account creation; define capacity, sizing, throughput, latency, scaling, autoscaling, high availability, failover, replication, multi-region, or disaster-recovery topology; claim zero downtime, any availability target, any uptime figure, or any service level; define or claim an RPO or RTO value; define, execute, schedule, or claim a backup or restore test; authorize any restore of production data into a non-production environment; define or approve a Firm-level suspension, emergency-disable, per-Firm maintenance, or per-Firm read-only capability, or read Constitution Article 46 as permitting one; create a second operator or privileged-access path (`docs/adr/ADR-023-…`, `docs/adr/ADR-014-…`); create a cross-Firm read role, view, projection, report, or analytics capability, or a Reporting bounded context — which Constitution Article 44 reserves and which this ADR **neither approves nor permanently prohibits**; supply any parameter, procedure, or evidence that `docs/legal/ARCH-012-Thai-Legal-Review.md` leaves to separately approved follow-up, or assert any notification outcome or timeline; assert any story's status, or schedule, rename, or renumber `PF-033` or any operations requirement; change `phpunit.xml`, any CI workflow, or the four required `Protect main` check names; introduce an AI capability or modify `docs/architecture/05_AI_Architecture.md`; assert a legal, tax, regulatory, or compliance conclusion; claim any certification (ISO, SOC, PDPA, GDPR, or other); claim production readiness or that the production-access evidence list is satisfied; claim that any described property is implemented, tested, or effective; weaken or create an exception to Constitution Articles 1–48; alter any bounded context's ownership; schedule any EPIC; or add, rename, renumber, merge, split, delete, or reschedule any `PF-*` story.

## Implementation status

**Accepted conceptual architecture only.** It authorizes no application code, infrastructure, environment, container image, network configuration, provider engagement, expenditure, deployment, or production access. **No environment described here exists**, no artefact described here has been built, and **no property described here is claimed to be implemented, tested, or effective.**

**No story's status is asserted here; `docs/PROJECT_STATUS.md` is the authoritative record.** The story ID is **ARCH-013**; the accompanying architecture document is `docs/architecture/20_Deployment_Operations_Architecture.md`.
