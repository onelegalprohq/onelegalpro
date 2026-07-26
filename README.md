# OneLegalPro

OneLegalPro is a white-label legal practice management platform built primarily for Thai law firms, Thai lawyers, foreign-owned law firms operating in Thailand, and foreign lawyers working with Thai law. It combines practice management, client communications, a public/client-facing digital presence, and Thailand-first legal intelligence behind a single, Firm-brandable product surface.

This description reflects the approved architecture in [`docs/architecture/`](docs/architecture) and [`docs/adr/`](docs/adr). It is a description of what OneLegalPro is being built to become, not a claim about what currently runs in production — see [Current project state](#current-project-state) below.

## Current project state

OneLegalPro is in its **architecture-first foundation phase**. Concretely, as of this writing:

- Ten architecture tracks are **approved and merged**: ARCH-001 (Thailand-First Legal Intelligence), ARCH-002 (White-Label Platform), ARCH-003 (Communications Hub), ARCH-004 (Website & Client Portal / Digital Presence), ARCH-005 (Practice Management Core), ARCH-006 (Document & Knowledge Management), ARCH-007 (Billing, Trust Accounting & Finance), ARCH-008 (Identity, Security & Access Control), ARCH-009 (API & Integration Platform), and ARCH-010 (AI Copilot & Workflow Automation). See [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) for the authoritative, up-to-date record of what each covers.
- Repository and governance foundation work is **in progress**: Git and repository standards (PF-002), repository documentation (PF-003), a Docker development environment (PF-010), standardized local environment configuration (PF-011), development tooling readiness (PF-012), Laravel Pint configuration (PF-020), Larastan-backed PHPStan static analysis (PF-021), a reviewed Rector deferral decision (PF-022), and local Git hooks (PF-023) are **complete** — see [Local development (PF-011)](#local-development-pf-011), [Development tooling readiness (PF-012)](#development-tooling-readiness-pf-012), [Code style: Laravel Pint (PF-020)](#code-style-laravel-pint-pf-020), [Static analysis: Larastan/PHPStan (PF-021)](#static-analysis-larastanphpstan-pf-021), and [Local Git hooks (PF-023)](#local-git-hooks-pf-023) below.
- Architecture approval does not imply scheduled implementation. **No business module (Legal Intelligence, White-Label rendering, Communications, Digital Presence/Client Portal, or Practice Management) has been implemented yet**, and there is **no production deployment**. Implementation for each epic requires its own approved story in [`docs/implementation/03_Engineering_Backlog.md`](docs/implementation/03_Engineering_Backlog.md).
- Laravel Pint and Larastan/PHPStan are configured and now checked by local, optional Git hooks (PF-023) — but these are local feedback only, not authoritative CI or security controls. Rector remains deliberately deferred (not installed or configured, per PF-022's review) — see [Development tooling readiness (PF-012)](#development-tooling-readiness-pf-012) below.

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
- **GitHub Actions and other automated quality gates** are not configured — see [`CONTRIBUTING.md`](CONTRIBUTING.md) for the current, unenforced state of the `Protect main` ruleset's status-check requirements.

Future ownership, per [`docs/implementation/03_Engineering_Backlog.md`](docs/implementation/03_Engineering_Backlog.md):

- **PF-030** will configure GitHub Actions.
- **PF-031** will configure automated quality gates and own any future PHPStan rule-level increase.
- **PF-032** will configure security scanning.

No developer should treat CI, quality gates, or security scanning as active or enforced until installed/configured and its owning story above is completed and merged. **Local Git hooks (PF-023) are fast local feedback, not authoritative CI or security controls** — they are bypassable with `--no-verify` by design, and the `Protect main` ruleset plus PF-030/PF-031's future CI checks remain the authoritative gate.

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

Pint is configured and runnable. `composer pint:test` also runs automatically in the `pre-commit` hook once a developer opts in via [Local Git hooks (PF-023)](#local-git-hooks-pf-023) — but that local hook is optional, bypassable with `--no-verify`, and not a CI/security control. Authoritative enforcement remains PF-030/PF-031 (GitHub Actions and quality gates) scope.

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

PHPStan is not a security scanner — it checks type and code correctness, not vulnerabilities. Larastan/PHPStan is configured and runnable. `composer phpstan` also runs automatically in the `pre-push` hook once a developer opts in via [Local Git hooks (PF-023)](#local-git-hooks-pf-023) — but that local hook is optional, bypassable with `--no-verify`, and not a CI/security control. PF-030 (GitHub Actions) and PF-031 (quality gates, and any future increase to the analysis level) remain the authoritative enforcement; PF-032 owns security scanning. PF-022 (Rector) was reviewed and deliberately deferred — see [Development tooling readiness (PF-012)](#development-tooling-readiness-pf-012) above.

## Local Git hooks (PF-023)

Tracked, reviewable Git hooks live in [`.githooks/`](.githooks) and run the already-configured Pint and Larastan/PHPStan checks (and the test suite) locally, using Docker first with a host-Composer fallback. **Installation is intentional** — nothing installs these hooks automatically, not during `composer install`, not on Docker startup.

Install, check, and remove them with plain shell commands run in your normal Terminal at the repository root — **no host PHP or Composer is required to install or manage the hooks themselves**, only to run the checks they invoke (which Docker already provides):

```bash
sh .githooks/install.sh
sh .githooks/status.sh
sh .githooks/uninstall.sh
```

- `install.sh` sets this repository's **local** `core.hooksPath` to `.githooks`. It never touches global Git configuration, is idempotent (safe to re-run), and **refuses to overwrite** a pre-existing custom `core.hooksPath` — it prints the existing value and asks you to resolve it manually instead.
- `status.sh` reports whether hooks are installed, not installed, or whether a custom hooks path is configured — it never changes anything.
- `uninstall.sh` unsets `core.hooksPath` only if it is exactly `.githooks`; it never touches an unrelated custom value and never deletes `.git/hooks/` or any file of yours.

**What each hook checks:**

- **`pre-commit`** — `git diff --cached --check` (native Git whitespace/conflict-marker check), then check-only `composer pint:test` against the full configured Pint scope. Never runs `composer pint` (the fixing command) and never modifies any file.
- **`commit-msg`** — validates the commit subject against the Conventional Commits format documented in [`CONTRIBUTING.md`](CONTRIBUTING.md) (`type(scope)?!?: description`, an allowed type, a non-empty description, no trailing period). `Merge`, `Revert "`, `fixup!`, and `squash!` subjects are exempt.
- **`pre-push`** — `composer phpstan`, then `composer test` (the project's test suite runs against SQLite `:memory:` per `phpunit.xml`, so this does not depend on a migrated PostgreSQL database). Both are blocking.

If Docker is not installed, not running, or the `app` service isn't up, and no usable host Composer environment (`vendor/autoload.php` present) is found either, a hook **fails with a clear, actionable message** telling you to start Docker Desktop, run `docker compose up -d`, ensure dependencies are installed, and re-run — it never silently skips a check. `git commit --no-verify` / `git push --no-verify` remain available as an intentional emergency bypass; using them does not mean the code is clean, only that local validation was skipped.

**These are local, optional hooks — not authoritative security or CI controls.** They are easily bypassed and only run on the machine where they're installed. PF-030 (GitHub Actions) and PF-031 (Quality Gates) remain responsible for the platform's authoritative, unbypassable checks; PF-032 remains responsible for security scanning; none of that is implemented by PF-023.

**Supported environments:** macOS, Linux, WSL, and native Windows with Git for Windows (which bundles Git Bash) — the hook scripts are POSIX `sh` and Git invokes them through whichever POSIX-compatible shell it ships with on each platform. **Not supported:** a raw Windows shell (`cmd.exe`/PowerShell) with no Git-for-Windows or WSL POSIX shell available.

## Development workflow

Full detail is in [`CONTRIBUTING.md`](CONTRIBUTING.md). In summary:

- Work happens on short-lived feature branches created from `main`; `main` is protected and never receives direct commits.
- **One approved story per pull request** — a PR implements exactly one story ID from [`docs/implementation/03_Engineering_Backlog.md`](docs/implementation/03_Engineering_Backlog.md).
- Every PR uses the [pull request template](.github/PULL_REQUEST_TEMPLATE.md) with its sections intact.
- Human review and approval is required before merge. GitHub's formal approval-count requirement is currently 0 because this is a single-owner repository (GitHub cannot let an author approve their own PR); until a second authorized reviewer exists, human approval is recorded as an explicit review comment on the pull request instead.

## Security and confidentiality

- Never commit credentials, API keys, tokens, secrets, client or matter information, privileged legal content, or production data. `.gitignore` excludes `.env.*` (except `.env.example`) for this reason.
- Never weaken authentication, authorization, Firm isolation, Ethical Walls, encryption, or auditability to make a change easier.
- If a secret is committed accidentally, treat it as compromised, rotate it immediately, and notify the repository owner — a history rewrite alone is not sufficient remediation.
- Report suspected security issues privately to the repository owner rather than in a public issue, per [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Current limitations and next foundation work

- **Laravel Pint (PF-020) and Larastan/PHPStan (PF-021) are configured and checked by local, optional Git hooks (PF-023) once installed** — but those hooks are local feedback only, bypassable with `--no-verify`, and not authoritative CI or security controls. Rector (PF-022) was reviewed and deliberately deferred rather than installed — see [Development tooling readiness (PF-012)](#development-tooling-readiness-pf-012) above for the current inventory.
- **No CI or status-check gates are active.** Status checks, commit signing, code scanning, and coverage/quality gates are deferred to their own approved future stories (PF-030, PF-031, PF-032) and must not be treated as enforced until those stories configure and verify them — see [`CONTRIBUTING.md`](CONTRIBUTING.md).

Track exactly which story is current and what comes next in [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md).

## Where to go next

- Read [`docs/README.md`](docs/README.md) for the full documentation index.
- Read [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) for the current story and completed work.
- Read [`CONTRIBUTING.md`](CONTRIBUTING.md) before opening a pull request.
