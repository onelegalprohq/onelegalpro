# OneLegalPro

OneLegalPro is a white-label legal practice management platform built primarily for Thai law firms, Thai lawyers, foreign-owned law firms operating in Thailand, and foreign lawyers working with Thai law. It combines practice management, client communications, a public/client-facing digital presence, and Thailand-first legal intelligence behind a single, Firm-brandable product surface.

This description reflects the approved architecture in [`docs/architecture/`](docs/architecture) and [`docs/adr/`](docs/adr). It is a description of what OneLegalPro is being built to become, not a claim about what currently runs in production — see [Current project state](#current-project-state) below.

## Current project state

OneLegalPro is in its **architecture-first foundation phase**. Concretely, as of this writing:

- Ten architecture tracks are **approved and merged**: ARCH-001 (Thailand-First Legal Intelligence), ARCH-002 (White-Label Platform), ARCH-003 (Communications Hub), ARCH-004 (Website & Client Portal / Digital Presence), ARCH-005 (Practice Management Core), ARCH-006 (Document & Knowledge Management), ARCH-007 (Billing, Trust Accounting & Finance), ARCH-008 (Identity, Security & Access Control), ARCH-009 (API & Integration Platform), and ARCH-010 (AI Copilot & Workflow Automation). See [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) for the authoritative, up-to-date record of what each covers.
- Repository and governance foundation work is **in progress**: Git and repository standards (PF-002) and repository documentation (PF-003) are **complete**, and a Docker development environment (PF-010) is **complete** — see [Docker development environment](#docker-development-environment) below.
- Architecture approval does not imply scheduled implementation. **No business module (Legal Intelligence, White-Label rendering, Communications, Digital Presence/Client Portal, or Practice Management) has been implemented yet**, and there is **no production deployment**. Implementation for each epic requires its own approved story in [`docs/implementation/03_Engineering_Backlog.md`](docs/implementation/03_Engineering_Backlog.md).
- A standardized local-development configuration workflow (PF-011) and development tooling such as Pint/PHPStan/Rector/Git hooks (PF-012) do not exist yet — see [Current limitations](#current-limitations-and-next-foundation-work) below.

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

`composer run setup` is a provisional bootstrap script, not a documented, standardized onboarding workflow — see [Current limitations](#current-limitations-and-next-foundation-work).

## Docker development environment

PF-010 provides a reproducible Docker Compose development environment so the application, PostgreSQL, Redis, the queue worker, and frontend asset building can run without installing those runtimes directly on the host. It runs on both Apple Silicon and amd64 machines without a hardcoded platform.

**This is development infrastructure only.** It does not deploy OneLegalPro to production, configure a public domain/DNS/SSL, or implement PF-011 (Local Environment & Configuration) or PF-012 (Development Tooling) — see [Current limitations](#current-limitations-and-next-foundation-work).

Services: `app` (PHP-FPM 8.4 + Composer), `web` (Nginx, serving `public/` only), `postgres`, `redis`, `vite` (Node, frontend dev server and build), and `queue` (the Laravel queue worker, reusing the `app` image). PostgreSQL and Redis publish no host ports — they are reachable only from other containers on the internal Docker network. All published ports bind to `127.0.0.1`.

```bash
# Build the images
docker compose build

# Start the stack (detached)
docker compose up -d

# Inspect service and health status
docker compose ps

# One-time per environment: create your local .env and application key
cp .env.example .env
docker compose exec app php artisan key:generate

# Apply database migrations (never run automatically by the containers)
docker compose exec app php artisan migrate

# On a completely fresh database, the queue container may have exited because
# its database-backed tables (cache/jobs) didn't exist yet at first start.
# Start it now that migrations have run, and confirm it stays up:
docker compose up -d queue
docker compose ps

# Run the test suite
docker compose exec app php artisan test

# Application: http://127.0.0.1:8080  |  Vite dev server: http://127.0.0.1:5173

# Stop the stack (keeps named volumes, e.g. Postgres data)
docker compose down
```

`docker compose exec app composer validate --strict` and `docker compose exec vite npm run build` are also available for verifying dependency and frontend-build health. Postgres and Redis defaults (`onelegalpro` / `onelegalpro_dev_only`) in `compose.yaml` are clearly-labelled local-development-only credentials, not secrets.

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

- **No standardized local-development configuration yet.** The Docker environment above (PF-010) and the `composer run setup` script provide a provisional bootstrap only; a documented, standardized local-development and configuration workflow is a separate upcoming story — PF-011 (Local Environment & Configuration).
- **No development tooling yet.** Laravel Pint, PHPStan, Rector, and Git hooks are deferred to PF-012 (Development Tooling) and are not configured today.
- **No CI or status-check gates are active.** Status checks, commit signing, code scanning, and coverage/quality gates are deferred to their own approved future stories and must not be treated as enforced until those stories configure and verify them — see [`CONTRIBUTING.md`](CONTRIBUTING.md).

Track exactly which story is current and what comes next in [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md).

## Where to go next

- Read [`docs/README.md`](docs/README.md) for the full documentation index.
- Read [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) for the current story and completed work.
- Read [`CONTRIBUTING.md`](CONTRIBUTING.md) before opening a pull request.
