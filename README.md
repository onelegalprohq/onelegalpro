# OneLegalPro

OneLegalPro is a white-label legal practice management platform built primarily for Thai law firms, Thai lawyers, foreign-owned law firms operating in Thailand, and foreign lawyers working with Thai law. It combines practice management, client communications, a public/client-facing digital presence, and Thailand-first legal intelligence behind a single, Firm-brandable product surface.

This description reflects the approved architecture in [`docs/architecture/`](docs/architecture) and [`docs/adr/`](docs/adr). It is a description of what OneLegalPro is being built to become, not a claim about what currently runs in production — see [Current project state](#current-project-state) below.

## Current project state

OneLegalPro is in its **architecture-first foundation phase**. Concretely, as of this writing:

- Ten architecture tracks are **approved and merged**: ARCH-001 (Thailand-First Legal Intelligence), ARCH-002 (White-Label Platform), ARCH-003 (Communications Hub), ARCH-004 (Website & Client Portal / Digital Presence), ARCH-005 (Practice Management Core), ARCH-006 (Document & Knowledge Management), ARCH-007 (Billing, Trust Accounting & Finance), ARCH-008 (Identity, Security & Access Control), ARCH-009 (API & Integration Platform), and ARCH-010 (AI Copilot & Workflow Automation). See [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) for the authoritative, up-to-date record of what each covers.
- Repository and governance foundation work is **in progress**: Git and repository standards (PF-002), repository documentation (PF-003), a Docker development environment (PF-010), standardized local environment configuration (PF-011), development tooling readiness (PF-012), Laravel Pint configuration (PF-020), Larastan-backed PHPStan static analysis (PF-021), a reviewed Rector deferral decision (PF-022), local Git hooks (PF-023), GitHub Actions continuous integration (PF-030), and required-status-check quality gates (PF-031) are **complete** — see [Local development (PF-011)](#local-development-pf-011), [Development tooling readiness (PF-012)](#development-tooling-readiness-pf-012), [Code style: Laravel Pint (PF-020)](#code-style-laravel-pint-pf-020), [Static analysis: Larastan/PHPStan (PF-021)](#static-analysis-larastanphpstan-pf-021), [Local Git hooks (PF-023)](#local-git-hooks-pf-023), [Continuous integration (PF-030)](#continuous-integration-pf-030), and [Quality gates: required status checks (PF-031)](#quality-gates-required-status-checks-pf-031) below.
- Architecture approval does not imply scheduled implementation. **No business module (Legal Intelligence, White-Label rendering, Communications, Digital Presence/Client Portal, or Practice Management) has been implemented yet**, and there is **no production deployment**. Implementation for each epic requires its own approved story in [`docs/implementation/03_Engineering_Backlog.md`](docs/implementation/03_Engineering_Backlog.md).
- Laravel Pint and Larastan/PHPStan are configured, checked by local, optional Git hooks (PF-023), and run automatically in CI on every pull request (PF-030). The local hooks remain fast feedback only and are never the merge gate; since PF-031 the three CI checks are **required** by the active `Protect main` ruleset, which is the authoritative enforcement boundary for `main` — PF-032 still owns security scanning. Rector remains deliberately deferred (not installed or configured, per PF-022's review) — see [Development tooling readiness (PF-012)](#development-tooling-readiness-pf-012) below.

For the current story, next story, and full completed/in-progress record, [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) is authoritative and updated after every completed story.

## Target architecture

The approved architecture commits OneLegalPro to:

- An **enterprise Laravel modular monolith**: business capabilities live in `app/Modules`, shared technical primitives live in `app/Foundation` — see [`docs/domain/06_Laravel_Module_Blueprint.md`](docs/domain/06_Laravel_Module_Blueprint.md).
- **Domain-Driven Design and Clean Architecture**: a strict `Interface → Application → Domain` dependency direction, framework-independent domain objects, and Eloquent used only for persistence — never for business logic.
- **Law-firm multi-tenancy and Firm isolation**: an explicit `FirmContext` (Firm ID, Actor ID, membership, correlation ID) scopes firm-owned data everywhere, enforced in application logic and repositories, not by global query scopes alone.
- **Security, audit, Ethical Wall, and AI-governance guardrails**: cross-module access only through published contracts, commands, queries, or events; Ethical Wall access checked only through Practice Management's published query; AI output is always advisory, provenance-tagged, and never allowed to silently change authoritative records, bypass access controls, or send client communications without configured human authorization.

The full constitutional rules behind these commitments are in [`docs/architecture/01_OneLegalPro_Constitution.md`](docs/architecture/01_OneLegalPro_Constitution.md).

## Repository map

| Document | Purpose |
|---|---|
| [`AGENTS.md`](AGENTS.md) | Required reading for any AI coding assistant before changing this repository; lists the source-of-truth documents and per-module rules. |
| [`CONTRIBUTING.md`](CONTRIBUTING.md) | Git workflow, branch/commit/versioning conventions, PR process, and security/secret-handling rules. |
| [`.github/workflows/ci.yml`](.github/workflows/ci.yml) | PF-030 continuous integration: the PHP Code Quality, Frontend Build, and Application Tests checks that run on every pull request targeting `main` — all three required to merge since PF-031. |
| [`docs/README.md`](docs/README.md) | Full, navigable documentation index. |
| [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) | Authoritative, continuously updated record of current/next story and completed work. |
| [`docs/architecture/`](docs/architecture) | Approved architecture documents (Constitution, AI Architecture, Roadmap, and the per-epic architecture documents). |
| [`docs/adr/`](docs/adr) | Architecture Decision Records. |
| [`docs/domain/06_Laravel_Module_Blueprint.md`](docs/domain/06_Laravel_Module_Blueprint.md) | How approved architecture maps onto the Laravel modular-monolith structure. |
| [`docs/implementation/01_Implementation_Sprint_Plan.md`](docs/implementation/01_Implementation_Sprint_Plan.md) | Approved delivery sequence (sprints and phases). |
| [`docs/implementation/03_Engineering_Backlog.md`](docs/implementation/03_Engineering_Backlog.md) | Story-level backlog and status within the current epic. |

## Documentation precedence

When documents disagree, `AGENTS.md` establishes the source-of-truth order this repository follows:

1. [`docs/architecture/01_OneLegalPro_Constitution.md`](docs/architecture/01_OneLegalPro_Constitution.md) is constitutional — it prevails over any lower-level architecture, ADR, domain, or implementation document.
2. The rest of [`docs/architecture/`](docs/architecture) and [`docs/adr/`](docs/adr) govern epic-level and per-decision architecture.
3. [`docs/domain/06_Laravel_Module_Blueprint.md`](docs/domain/06_Laravel_Module_Blueprint.md) governs how that architecture is expressed in code structure.
4. [`docs/implementation/01_Implementation_Sprint_Plan.md`](docs/implementation/01_Implementation_Sprint_Plan.md) and [`docs/implementation/03_Engineering_Backlog.md`](docs/implementation/03_Engineering_Backlog.md) govern approved, scheduled delivery.
5. [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) reflects current status against that plan.

If a request conflicts with approved architecture, the correct response is to stop and request an architecture decision — not to resolve the conflict unilaterally. See `AGENTS.md` for the full rule set.

## Technology

Verified from [`composer.json`](composer.json) and [`package.json`](package.json):

- **PHP** `^8.3` with **Laravel Framework** `^13.8` and **Laravel Tinker** `^3.0`.
- Development tooling: FakerPHP, Laravel Pail, Laravel Pint, Mockery, Collision, and **PHPUnit** `^12.5.12`.
- Frontend build: **Vite** `^8.0`, **Tailwind CSS** `^4.0`, and the Laravel Vite plugin.
- Composer scripts: `composer run setup` (install, environment bootstrap, key generation, migration, and frontend build), `composer run dev` (concurrent local server, queue listener, log tailing, and Vite), and `composer run test` (config clear plus `artisan test`).
- npm scripts: `npm run dev` (Vite dev server) and `npm run build` (production frontend build).

These host-native Composer/npm scripts remain available for a developer who prefers installing PHP, Composer, and Node directly on the host, but they are **not** the authoritative PF-011 onboarding workflow. The Docker Compose workflow below is the standardized, documented route from a clean clone to a running environment.

## Local development (PF-011)

PF-010 provides a reproducible Docker Compose development environment, and PF-011 standardizes how a developer actually uses it: safe local environment values, a first-time onboarding sequence, and a repeatable daily workflow — so the application, PostgreSQL, Redis, the queue worker, and frontend asset building all run **without installing PHP, Composer, Node, PostgreSQL, or Redis directly on the host**. Git and Docker (Docker Desktop or another compatible Docker Engine with Compose) are the only prerequisites. The stack runs on both Apple Silicon and amd64 machines without a hardcoded platform.

**This is local development configuration only.** It is not production deployment guidance, and it does not configure a public domain, DNS, SSL, cloud infrastructure, or production secrets. Development tooling (Pint, PHPStan, Rector, Git hooks) is not configured as part of this Docker environment itself — see [Development tooling readiness (PF-012)](#development-tooling-readiness-pf-012) below for the current tooling inventory and which story configured or owns each tool.

Services: `app` (PHP-FPM 8.4 + Composer), `web` (Nginx, serving `public/` only), `postgres`, `redis`, `vite` (Node, frontend dev server and build), and `queue` (the Laravel queue worker, reusing the `app` image). PostgreSQL and Redis publish no host ports — they are reachable only from other containers on the internal Docker network. All published ports bind to `127.0.0.1`.

### Prerequisites

- Git
- Docker Desktop, or another Docker Engine with Compose, compatible with `compose.yaml`
- No host PHP, Composer, Node, PostgreSQL, or Redis installation is required

### First-time onboarding

From a clean clone, in exactly this order:

```bash
git clone <repository-url>
cd OneLegalPro
cp .env.example .env
docker compose build
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose up -d queue
docker compose ps
curl -fsS http://127.0.0.1:8080/up
docker compose exec app php artisan test
```

Notes on this sequence:

- **Migrations are explicit and never automatic.** No container runs `artisan migrate` on its own; it is always a deliberate command a developer runs.
- **On a completely fresh database, the queue container may stop right after `docker compose up -d`**, because its database-backed tables (`cache`, `jobs`) do not exist yet until migrations run. This is expected, not a failure to troubleshoot.
- `docker compose up -d queue`, run again after `artisan migrate` completes, starts the queue worker normally against the now-migrated database.
- `docker compose ps` should show all **six** services (`app`, `web`, `postgres`, `redis`, `vite`, `queue`) running (and `postgres`/`redis` healthy) once this sequence completes.
- Application URL: `http://127.0.0.1:8080` · Health URL: `http://127.0.0.1:8080/up` · Vite dev server: `http://127.0.0.1:5173`.

### Existing local `.env`

`.env` is **never** overwritten automatically by any command in this workflow — `cp .env.example .env` only applies on a clean clone where no `.env` exists yet. A developer upgrading from an older local setup keeps their existing `.env` and must manually reconcile its non-secret configuration against the current `.env.example`, in particular:

- `APP_NAME`
- `APP_URL`
- PostgreSQL connection values (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
- The `database`-driven cache/session/queue configuration (`CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`)
- Redis hostname and port (`REDIS_HOST`, `REDIS_PORT`)

Never paste, publish, commit, or display the contents of `.env` — including in a pull request, an issue, or a chat transcript — and never run a command whose purpose is to print the whole file. If a value needs to be shared for troubleshooting, share only the specific key name and a description of the problem, never the file contents.

### Daily workflow

```bash
docker compose up -d
docker compose ps
curl -fsS http://127.0.0.1:8080/up
docker compose exec app php artisan test
docker compose down
```

`docker compose down` (without `-v`) stops and removes the containers but **preserves named volumes**, including the Postgres data volume — your local database survives between sessions unless you explicitly remove volumes yourself.

### Additional checks

```bash
docker compose exec app php artisan migrate:status
docker compose exec app composer validate --strict
docker compose exec vite npm run build
docker compose logs queue --tail=50
```

Postgres and Redis defaults (`onelegalpro` / `onelegalpro_dev_only`) in `compose.yaml` and `.env.example` are clearly-labelled local-development-only credentials, not secrets — they are meaningless outside a developer's own local Docker network.

## Development tooling readiness (PF-012)

PF-012 (Development Tooling) inventoried the repository's tooling state and clarified which story owns each tool. PF-020 has since configured Laravel Pint, PF-021 has configured Larastan-backed PHPStan, PF-022 reviewed Rector and deliberately deferred it, and PF-023 has configured local, optional Git hooks. The remaining inventory:

- **Laravel Pint** is configured — see [Code style: Laravel Pint (PF-020)](#code-style-laravel-pint-pf-020) below.
- **Larastan/PHPStan** is configured — see [Static analysis: Larastan/PHPStan (PF-021)](#static-analysis-larastanphpstan-pf-021) below.
- **Rector** is not installed, configured, runnable, or enforced. PF-022 evaluated it and found insufficient current value to justify another dependency and an automated PHP-rewriting surface: the repository currently contains only a small, modern Laravel skeleton, and Pint and Larastan/PHPStan already pass cleanly with nothing left for Rector's dead-code/modernization rules to find. This is a deliberate deferral, not a permanent rejection — Rector should be reconsidered once approved business-module implementation creates meaningful `app/Modules/` code, and that reconsideration requires a fresh analysis against the real code present at that time.
- **Git hooks** are configured, tracked, and installable — see [Local Git hooks (PF-023)](#local-git-hooks-pf-023) below. Installation is an intentional, separate developer action; nothing installs them automatically.
- **GitHub Actions** are configured — see [Continuous integration (PF-030)](#continuous-integration-pf-030) below. The checks run automatically on pull requests and, since PF-031, all three are **required** by the `Protect main` ruleset; see [`CONTRIBUTING.md`](CONTRIBUTING.md) for that ruleset's full current state.
- **Required status checks** are configured — see [Quality gates: required status checks (PF-031)](#quality-gates-required-status-checks-pf-031) below. **Coverage thresholds, a PHPStan level increase, frontend linting, and test-count thresholds are deliberately not configured**, and neither is commit signing.

Future ownership, per [`docs/implementation/03_Engineering_Backlog.md`](docs/implementation/03_Engineering_Backlog.md):

- **PF-031** configured automated quality gates and made the three CI checks required. It deliberately introduced no new threshold; any future PHPStan rule-level increase remains its documented ownership area and requires a fresh decision against the code present at that time.
- **PF-032** will configure security scanning.

No developer should treat security scanning as active or enforced until PF-032 is completed and merged, and no disabled ruleset option (commit signing, linear history, required deployments, merge queue, code scanning, code quality, coverage) should be treated as active. **Local Git hooks (PF-023) are fast local feedback, not authoritative CI or security controls** — they are bypassable with `--no-verify` by design and are never the merge gate. The PF-030 CI checks are unbypassable by `--no-verify`, run independently of local hooks, and are required to pass by the `Protect main` ruleset.

## Code style: Laravel Pint (PF-020)

[`pint.json`](pint.json) explicitly pins the stock **Laravel** preset — no custom rules, exclusions, or cache settings. Two Composer scripts wrap it:

- `composer pint:test` — checks formatting and reports violations **without modifying any PHP file**.
- `composer pint` — applies formatting corrections.

Canonical Docker commands (the standardized workflow from [Local development (PF-011)](#local-development-pf-011)):

```bash
docker compose exec app composer pint:test
docker compose exec app composer pint
```

Host alternatives, when PHP and Composer dependencies are already installed locally:

```bash
composer pint:test
composer pint
```

Pint is configured and runnable. `composer pint:test` also runs automatically in the `pre-commit` hook once a developer opts in via [Local Git hooks (PF-023)](#local-git-hooks-pf-023) — but that local hook is optional, bypassable with `--no-verify`, and not a CI/security control. `composer pint:test` additionally runs in CI's **PHP Code Quality** check on every pull request — see [Continuous integration (PF-030)](#continuous-integration-pf-030) — and PF-031 made that check a required, merge-blocking gate. PF-031 changed no Pint rule: [`pint.json`](pint.json) still pins the stock `laravel` preset.

## Static analysis: Larastan/PHPStan (PF-021)

[`phpstan.neon.dist`](phpstan.neon.dist) configures **Larastan** (`larastan/larastan`), which brings in PHPStan (`phpstan/phpstan`) and provides Laravel-aware static analysis on top of it — understanding Eloquent models, relations, facades, and other Laravel conventions that plain PHPStan cannot resolve on its own. Both packages are development-only dependencies (`require-dev`) and are never installed in a production runtime.

- **Analysis level:** 5 — Larastan's own documented starting point for a fresh Laravel project.
- **Analyzed paths:** `app/`, `bootstrap/`, `config/`, `database/`, `routes/`.
- **Excluded:** generated files under `bootstrap/cache/` are excluded from analysis (`excludePaths`). These files are machine- and run-generated (Laravel's cached package/service manifests), so their presence and exact contents vary between environments and executions — excluding them keeps the analysis scope limited to tracked, reviewable source rather than local generated state.
- **Not analyzed:** `tests/` and Blade templates remain outside PF-021's initial scope.
- **No baseline, suppressions, or ignored errors are configured** — the current codebase passes level 5 cleanly with no exceptions carved out.

Canonical Docker command:

```bash
docker compose exec app composer phpstan
```

Host alternative, when PHP and Composer dependencies are already installed locally:

```bash
composer phpstan
```

If analysis fails with a genuine out-of-memory error, the standard Composer script is not changed — instead, run PHPStan directly with a higher memory limit as a troubleshooting step:

```bash
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G
```

PHPStan is not a security scanner — it checks type and code correctness, not vulnerabilities. Larastan/PHPStan is configured and runnable. `composer phpstan` also runs automatically in the `pre-push` hook once a developer opts in via [Local Git hooks (PF-023)](#local-git-hooks-pf-023) — but that local hook is optional, bypassable with `--no-verify`, and not a CI/security control. `composer phpstan` additionally runs in CI's **PHP Code Quality** check on every pull request — see [Continuous integration (PF-030)](#continuous-integration-pf-030) — and PF-031 made that check required to merge. **PF-031 left the analysis level at 5** and added no baseline, suppression, or ignored error; raising the level against the current small skeleton would produce a gate that certifies nothing, so it stays a future decision to be made against real module code. PF-032 owns security scanning. PF-022 (Rector) was reviewed and deliberately deferred — see [Development tooling readiness (PF-012)](#development-tooling-readiness-pf-012) above.

## Local Git hooks (PF-023)

Tracked, reviewable Git hooks live in [`.githooks/`](.githooks) and run the already-configured Pint and Larastan/PHPStan checks (and the test suite) locally, using Docker first with a host-Composer fallback. **Installation is intentional** — nothing installs these hooks automatically, not during `composer install`, not on Docker startup.

Install, check, and remove them with plain shell commands run in your normal Terminal at the repository root — **no host PHP or Composer is required to install or manage the hooks themselves**, only to run the checks they invoke (which Docker already provides):

```bash
sh .githooks/install.sh
sh .githooks/status.sh
sh .githooks/uninstall.sh
```

- `install.sh` sets this repository's **local** `core.hooksPath` to `.githooks` — but only when nothing else already claims that configuration. It refuses to overwrite a different repository-local custom `core.hooksPath`, and it also refuses to override an **inherited/effective** hooks path already configured outside this repository (global, system, or worktree Git configuration), reporting that value's origin when available. It never touches global, system, worktree, or user Git configuration itself, and is idempotent (safe to re-run once installed).
- `status.sh` distinguishes four states without changing anything: PF-023 hooks installed locally (`core.hooksPath=.githooks`); a different repository-local custom hooks path; no local value but an inherited/effective hooks path from outside this repository (reported as not owned by PF-023, with its origin when available); or nothing configured at any scope.
- `uninstall.sh` unsets `core.hooksPath` only when the **repository-local** value is exactly `.githooks` — the value PF-023 itself installed. It refuses to alter a different repository-local custom value, and if no local value exists but an inherited/effective one does, it leaves that untouched and reports that PF-023 was never installed locally. It never deletes `.git/hooks/`, `.githooks/`, or any file of yours.

**What each hook checks:**

- **`pre-commit`** — `git diff --cached --check` (native Git whitespace/conflict-marker check), then check-only `composer pint:test` against the full configured Pint scope. Never runs `composer pint` (the fixing command) and never modifies any file.
- **`commit-msg`** — validates the commit subject against the Conventional Commits format documented in [`CONTRIBUTING.md`](CONTRIBUTING.md) (`type(scope)?!?: description`, an allowed type, a non-empty description, no trailing period). `Merge`, `Revert "`, `fixup! ` (with the trailing space), and `squash! ` (with the trailing space) subjects are exempt — `fixup!`/`squash!` without the following space are not exempt and are validated as ordinary Conventional Commit subjects.
- **`pre-push`** — `composer phpstan`, then `composer test` (the project's test suite runs against SQLite `:memory:` per `phpunit.xml`, so this does not depend on a migrated PostgreSQL database). Both are blocking.

If Docker is not installed, not running, or the `app` service isn't up, and no usable host Composer environment (`vendor/autoload.php` present) is found either, a hook **fails with a clear, actionable message** telling you to start Docker Desktop, run `docker compose up -d`, ensure dependencies are installed, and re-run — it never silently skips a check. `git commit --no-verify` / `git push --no-verify` remain available as an intentional emergency bypass; using them does not mean the code is clean, only that local validation was skipped.

**These are local, optional hooks — not authoritative security or CI controls, and never the merge gate.** They are easily bypassed and only run on the machine where they're installed. They remain genuinely useful for catching a problem before you push, but the authoritative boundary is the active `Protect main` ruleset: the same checks run remotely and unbypassably in CI — see [Continuous integration (PF-030)](#continuous-integration-pf-030) — and since PF-031 all three are *required* to merge, as described in [Quality gates: required status checks (PF-031)](#quality-gates-required-status-checks-pf-031). PF-032 remains responsible for security scanning. None of that is implemented by PF-023, and CI never invokes these hook scripts.

**Supported environments:** macOS, Linux, WSL, and native Windows with Git for Windows (which bundles Git Bash) — the hook scripts are POSIX `sh` and Git invokes them through whichever POSIX-compatible shell it ships with on each platform. **Not supported:** a raw Windows shell (`cmd.exe`/PowerShell) with no Git-for-Windows or WSL POSIX shell available.

## Continuous integration (PF-030)

A single tracked workflow, [`.github/workflows/ci.yml`](.github/workflows/ci.yml) (named `CI`), runs the repository's already-configured checks automatically on GitHub. It triggers on **pull requests targeting `main`** and on manual **`workflow_dispatch`** — there is no push, scheduled, or deployment trigger.

Three stable checks appear on every pull request:

| Check name | Job ID | What it runs |
|---|---|---|
| **PHP Code Quality** | `quality` | `composer validate --strict`, `composer install`, check-only `composer pint:test`, `composer phpstan` |
| **Frontend Build** | `frontend` | `npm ci`, `npm run build`, verifies `public/build/manifest.json`, uploads `public/build/` as a 1-day artifact |
| **Application Tests** | `tests` | `composer install`, `.env` from `.env.example` + `php artisan key:generate`, downloads the frontend artifact, `composer test` |

- **Application Tests depends on Frontend Build.** A fresh CI runner has no Vite dev server and therefore no `public/hot` file, so Laravel's `@vite` directive resolves assets through `public/build/manifest.json` rather than the dev server. That manifest must exist before the test suite renders a view, so the tests job consumes the frontend job's build output instead of rebuilding it.
- **Runtimes:** `ubuntu-24.04`, **PHP 8.4**, **Node 22**, Composer v2, coverage disabled, no version matrix — matching the Docker development environment. Tests run against SQLite `:memory:` per [`phpunit.xml`](phpunit.xml), so **no PostgreSQL, Redis, queue worker, or Docker service is required**.
- **Every action is pinned to a full 40-character commit SHA**, with its human-readable release tag in an inline comment. Keeping those references updated automatically is PF-032 scope.
- **Read-only and secretless:** the workflow declares `permissions: contents: read`, uses no repository secret, and uses `pull_request` rather than `pull_request_target` — so pull requests from forks run with read-only access and never gain privileged base-repository permissions. It never comments on a pull request, pushes commits or tags, publishes an artifact externally, or deploys anything.
- **CI is independent of local Git hooks.** It calls the Composer/npm scripts directly and never invokes `.githooks/` scripts, so it runs whether or not a developer opted into [Local Git hooks (PF-023)](#local-git-hooks-pf-023).
- **PF-030 made these checks visible; PF-031 made them required.** A failing or missing check now blocks the merge — see [Quality gates: required status checks (PF-031)](#quality-gates-required-status-checks-pf-031) below. PF-032 owns security scanning, which is not implemented here, and **there is no production deployment**.

## Quality gates: required status checks (PF-031)

PF-031 made the three PF-030 checks **mandatory before `main` may be updated**, by adding a required-status-check rule to the already-active `Protect main` GitHub ruleset. **The ruleset is the authoritative enforcement boundary** — it lives in GitHub's repository settings, not in this repository, and no local tool or hook substitutes for it. PF-031 added no CI job, changed no check name, and changed no CI execution behavior; the only change inside [`.github/workflows/ci.yml`](.github/workflows/ci.yml) was correcting a descriptive header comment that had said the checks were not yet required.

| Required check | Source | Job ID |
|---|---|---|
| **`PHP Code Quality`** | GitHub Actions | `quality` |
| **`Frontend Build`** | GitHub Actions | `frontend` |
| **`Application Tests`** | GitHub Actions | `tests` |

- **All three are required, and every one must pass.** `Application Tests` declares `needs: frontend`, so when the frontend build fails GitHub Actions reports the tests job as `skipped` — a conclusion GitHub's required-check evaluation does not treat as blocking. Requiring only the final test job would therefore leave no reliable independent frontend gate, since a broken production build could skip the tests and still merge. Requiring `Frontend Build` on its own merits closes that path. **No aggregate gate job was added**, and none is needed for three stable job names in one workflow.
- **Required checks are matched by exact job/check name.** Renaming any of the three without a corresponding, human-reviewed ruleset update makes the repository **fail closed**: GitHub keeps waiting for a required check name that no longer reports, and pull requests stay blocked as "Expected". A job rename is always a two-part change — workflow *and* ruleset.
- **"Require branches to be up to date before merging" is deliberately OFF**, so a stale-but-green pull request may still merge. Enabling it would invalidate every other open pull request on each merge to `main`; that trade-off is not worth making while pull requests are authored one at a time. Revisit it when a second contributor joins or business-module implementation begins.
- **The rest of the `Protect main` ruleset is unchanged and still enforced**: pull requests required, conversation resolution required, branch deletion restricted, force pushes blocked, and **no bypass actor configured**. Required formal approving reviews remain temporarily **0** because this is a single-owner repository and GitHub does not let an author approve their own pull request — **explicit human review and an approval comment on the pull request remain mandatory**.
- **PF-031 introduced no new threshold.** PHPStan stays at **level 5** with no baseline or suppression, **no code-coverage collection or threshold** was added, **no frontend linter or type checker** was added, and **no test-count or assertion-count threshold** was added. Against the current small Laravel skeleton, any of these would be a cosmetic gate that certifies nothing.
- **Commit signing remains deferred**, pending a later ownership and signing-policy decision. Linear history, required deployments, a merge queue, required code-scanning results, required GitHub code-quality results, required code-coverage thresholds, and automatic Copilot review are all **not enabled** — do not treat any of them as an active control.
- **Code scanning and dependency/security automation are PF-032 scope**, still unconfigured. PF-031 introduced no application code, business module, deployment pipeline, production environment, repository secret, or new dependency.

## Development workflow

Full detail is in [`CONTRIBUTING.md`](CONTRIBUTING.md). In summary:

- Work happens on short-lived feature branches created from `main`; `main` is protected and never receives direct commits.
- **One approved story per pull request** — a PR implements exactly one story ID from [`docs/implementation/03_Engineering_Backlog.md`](docs/implementation/03_Engineering_Backlog.md).
- Every PR uses the [pull request template](.github/PULL_REQUEST_TEMPLATE.md) with its sections intact.
- **All three required CI checks must pass before a PR can merge** — `PHP Code Quality`, `Frontend Build`, and `Application Tests` — enforced by the `Protect main` ruleset since PF-031.
- Human review and approval is required before merge. GitHub's formal approval-count requirement is currently 0 because this is a single-owner repository (GitHub cannot let an author approve their own PR); until a second authorized reviewer exists, human approval is recorded as an explicit review comment on the pull request instead. Conversation resolution is required, and no bypass actor exists.

## Security and confidentiality

- Never commit credentials, API keys, tokens, secrets, client or matter information, privileged legal content, or production data. `.gitignore` excludes `.env.*` (except `.env.example`) for this reason.
- Never weaken authentication, authorization, Firm isolation, Ethical Walls, encryption, or auditability to make a change easier.
- If a secret is committed accidentally, treat it as compromised, rotate it immediately, and notify the repository owner — a history rewrite alone is not sufficient remediation.
- Report suspected security issues privately to the repository owner rather than in a public issue, per [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Current limitations and next foundation work

- **Laravel Pint (PF-020) and Larastan/PHPStan (PF-021) are configured**, checked by local, optional Git hooks (PF-023) once installed, and run automatically in CI (PF-030). The local hooks remain feedback only, bypassable with `--no-verify`, and are not authoritative controls. Rector (PF-022) was reviewed and deliberately deferred rather than installed — see [Development tooling readiness (PF-012)](#development-tooling-readiness-pf-012) above for the current inventory.
- **CI is now an enforced merge gate, but a deliberately narrow one.** PF-031 requires all three PF-030 checks through the `Protect main` ruleset. **Commit signing, code scanning, dependency/security automation, coverage thresholds, frontend linting, and test-count thresholds remain unconfigured** and must not be treated as enforced — commit signing awaits a later ownership and signing-policy decision, and security scanning is PF-032 — see [Quality gates: required status checks (PF-031)](#quality-gates-required-status-checks-pf-031) above and [`CONTRIBUTING.md`](CONTRIBUTING.md).
- **There is no production deployment.** CI builds and tests the application; it deploys nothing, and no deployment pipeline, environment, or infrastructure exists.

Track exactly which story is current and what comes next in [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md).

## Where to go next

- Read [`docs/README.md`](docs/README.md) for the full documentation index.
- Read [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) for the current story and completed work.
- Read [`CONTRIBUTING.md`](CONTRIBUTING.md) before opening a pull request.
