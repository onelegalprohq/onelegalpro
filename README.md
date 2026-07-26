# OneLegalPro

OneLegalPro is a white-label legal practice management platform built primarily for Thai law firms, Thai lawyers, foreign-owned law firms operating in Thailand, and foreign lawyers working with Thai law. It combines practice management, client communications, a public/client-facing digital presence, and Thailand-first legal intelligence behind a single, Firm-brandable product surface.

This description reflects the approved architecture in [`docs/architecture/`](docs/architecture) and [`docs/adr/`](docs/adr). It is a description of what OneLegalPro is being built to become, not a claim about what currently runs in production — see [Current project state](#current-project-state) below.

## Current project state

OneLegalPro is in its **architecture-first foundation phase**. Concretely, as of this writing:

- Ten architecture tracks are **approved and merged**: ARCH-001 (Thailand-First Legal Intelligence), ARCH-002 (White-Label Platform), ARCH-003 (Communications Hub), ARCH-004 (Website & Client Portal / Digital Presence), ARCH-005 (Practice Management Core), ARCH-006 (Document & Knowledge Management), ARCH-007 (Billing, Trust Accounting & Finance), ARCH-008 (Identity, Security & Access Control), ARCH-009 (API & Integration Platform), and ARCH-010 (AI Copilot & Workflow Automation). See [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) for the authoritative, up-to-date record of what each covers.
- Repository and governance foundation work is **in progress**: Git and repository standards (PF-002), repository documentation (PF-003), a Docker development environment (PF-010), and standardized local environment configuration (PF-011) are **complete** — see [Local development (PF-011)](#local-development-pf-011) below.
- Architecture approval does not imply scheduled implementation. **No business module (Legal Intelligence, White-Label rendering, Communications, Digital Presence/Client Portal, or Practice Management) has been implemented yet**, and there is **no production deployment**. Implementation for each epic requires its own approved story in [`docs/implementation/03_Engineering_Backlog.md`](docs/implementation/03_Engineering_Backlog.md).
- Development tooling such as Pint/PHPStan/Rector/Git hooks (PF-012) does not exist yet — see [Current limitations](#current-limitations-and-next-foundation-work) below.

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

**This is local development configuration only.** It is not production deployment guidance, and it does not configure a public domain, DNS, SSL, cloud infrastructure, or production secrets. Development tooling such as Pint, PHPStan, Rector, and Git hooks remains PF-012 (Development Tooling) — future work, not implemented here.

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

- **No development tooling yet.** Laravel Pint, PHPStan, Rector, and Git hooks are deferred to PF-012 (Development Tooling) — the current repository implementation story — and are not configured today.
- **No CI or status-check gates are active.** Status checks, commit signing, code scanning, and coverage/quality gates are deferred to their own approved future stories and must not be treated as enforced until those stories configure and verify them — see [`CONTRIBUTING.md`](CONTRIBUTING.md).

Track exactly which story is current and what comes next in [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md).

## Where to go next

- Read [`docs/README.md`](docs/README.md) for the full documentation index.
- Read [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) for the current story and completed work.
- Read [`CONTRIBUTING.md`](CONTRIBUTING.md) before opening a pull request.
