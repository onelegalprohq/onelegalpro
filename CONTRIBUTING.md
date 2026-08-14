# Contributing to OneLegalPro

This document defines the Git workflow, commit, versioning, and review standards for this repository. It complements — and never overrides — [`AGENTS.md`](AGENTS.md) and the approved documents in `docs/implementation/` and `docs/domain/`. If anything here conflicts with those documents, the approved architecture documents govern; stop and request an architecture decision rather than resolving the conflict unilaterally.

## Branch strategy

This repository uses a **main plus feature-branch** strategy:

- `main` is always deployable and protected. No direct commits to `main`.
- All work happens on a short-lived feature branch created from `main` and merged back via pull request.
- Delete feature branches after merge.

### Protect main (GitHub ruleset)

`main` is protected by an active GitHub branch ruleset (`Protect main`), verified at PF-002 closure and extended with required status checks at PF-031 closure. **This ruleset is the authoritative enforcement boundary for `main`** — no local tool, hook, or convention substitutes for it:

- A pull request is required before merging to `main`.
- Branch deletion and force-pushes to `main` are blocked.
- Review conversations must be resolved before a pull request can merge.
- No bypass actor is configured — the ruleset applies to everyone, with no exceptions.
- The required formal GitHub approval count is temporarily **0**, because this is currently a single-owner repository and GitHub does not allow an author to approve their own pull request. Human review and approval is still mandatory: record it as an explicit review comment on the pull request until a second authorized reviewer is available. Formal approving reviews (a required approval count above zero) must be configured as soon as a second authorized reviewer exists.
- **All three PF-030 status checks are required (PF-031).** PF-030 added the GitHub Actions workflow that makes these checks *visible* on every pull request targeting `main`; PF-031 made them *mandatory*, so `main` cannot be updated until **`PHP Code Quality`**, **`Frontend Build`**, and **`Application Tests`** have all passed. GitHub Actions is their configured source. See [Continuous integration (PF-030)](#continuous-integration-pf-030) and [Required status checks (PF-031)](#required-status-checks-pf-031) below.
- **A fourth status check is required (PF-032).** PF-032 added the `Security` workflow's **`Dependency Audit`** check, and the repository owner added it to this same ruleset from GitHub Actions. `main` therefore cannot be updated until **all four** of `PHP Code Quality`, `Frontend Build`, `Application Tests`, and `Dependency Audit` have passed. See [Security scanning (PF-032)](#security-scanning-pf-032) below.
- **"Require branches to be up to date before merging" is deliberately OFF.** A stale-but-green pull request may still merge. This is a considered choice for a single-owner repository with serialized pull requests, not an oversight — see [Required status checks (PF-031)](#required-status-checks-pf-031).
- "Do not require status checks on creation" is **off**, so the requirement applies from the moment a pull request is created.
- **Commit signing is not required.** It remains deferred pending a later ownership and signing-policy decision, and is not PF-031 scope.
- **Linear history is not required**, deployment success is not required, no merge queue is configured, code-scanning results are not required, GitHub code-quality results are not required, code-coverage thresholds are not required, and automatic Copilot review is not enabled. None of these is active — do not treat any of them as an enforced control.

This ruleset does not weaken the rule above: `main` remains deployable and protected at all times.

### Required status checks (PF-031)

PF-031 (Quality Gates) configured the `Protect main` ruleset to require exactly the three checks the PF-030 workflow already produced. No CI job, check name, or execution behavior changed — only the workflow's own descriptive header comment was corrected to match reality.

| Required check | Source | Job ID in [`ci.yml`](.github/workflows/ci.yml) |
|---|---|---|
| `PHP Code Quality` | GitHub Actions | `quality` |
| `Frontend Build` | GitHub Actions | `frontend` |
| `Application Tests` | GitHub Actions | `tests` |

- **All three are required — not a subset.** `Application Tests` declares `needs: frontend`, and GitHub Actions reports a job whose dependency failed with the conclusion `skipped`, which GitHub's required-status-check evaluation does not treat as blocking. Requiring only the final test job would therefore leave no reliable independent frontend gate: a broken production build would skip `Application Tests` and the pull request could still merge. Requiring `Frontend Build` in its own right closes that path.
- **No aggregate or umbrella gate job exists**, and none should be added. Three hand-written, stable job names in one workflow do not need that indirection, and a naive aggregate job reintroduces the same skip-treated-as-satisfied hole.
- **Required checks are matched by exact check name.** Renaming `PHP Code Quality`, `Frontend Build`, or `Application Tests` without a corresponding, human-reviewed update to the ruleset makes the repository **fail closed**: GitHub keeps waiting for a required check name that no longer reports, and every pull request stays blocked as "Expected". Treat a job rename as a two-part change — the workflow edit *and* the ruleset update — never one without the other.
- **The up-to-date-branch requirement stays off for now.** Enabling it would invalidate every other open pull request on each merge to `main` and force an update-and-rerun cycle, which buys little while pull requests are authored one at a time. Revisit it alongside the second-reviewer decision above, or when approved business-module implementation makes semantic conflicts realistic.
- **PF-031 changed no quality threshold.** PHPStan stays at level 5, no code-coverage collection or threshold is introduced, no frontend linter or type checker is introduced, and no test-count or assertion-count threshold is introduced. Introducing a threshold against the current small Laravel skeleton would create a cosmetic gate that certifies nothing.
- **Dependency and security automation arrived in PF-032, not PF-031.** At PF-031 closure, `composer audit`, `npm audit`, dependency review, CodeQL, secret scanning, container scanning, and action-update automation were all unconfigured. PF-032 has since configured dependency auditing, weekly Dependabot update proposals, and the required `Dependency Audit` check, and recorded GitHub's native protections — see [Security scanning (PF-032)](#security-scanning-pf-032) below. **CodeQL/SAST, dependency review, SARIF upload, container scanning, scheduled scanning, and third-party secret scanners remain deliberately deferred** and must not be treated as active.
- PF-031 introduced no application code, business module, deployment pipeline, production environment, repository secret, or new dependency.

### Branch naming

Branches are named `<type>/<story-id>-<short-description>`, using the story IDs from `docs/implementation/03_Engineering_Backlog.md`:

- `feature/pf-002-git-repository-standards`
- `fix/pf-014-tenant-scope-leak`
- `docs/pf-003-repository-documentation`
- `chore/pf-021-phpstan-baseline`

Allowed types: `feature`, `fix`, `docs`, `chore`, `refactor`, `test`, `ci`.

## Commit messages: Conventional Commits

All commits follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

- **type**: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`
- **scope**: the story ID or module name, e.g. `feat(pf-002): add pull request template`
- Description is imperative mood, no trailing period.
- Breaking changes are flagged with `!` after the type/scope, or a `BREAKING CHANGE:` footer, and require the approval gate below.

## Versioning: Semantic Versioning

Releases follow [Semantic Versioning](https://semver.org/) (`MAJOR.MINOR.PATCH`):

- **MAJOR** — incompatible public API or data-contract changes (requires human approval per `AGENTS.md`).
- **MINOR** — backward-compatible functionality.
- **PATCH** — backward-compatible fixes.

No release has been tagged yet. While `EPIC-001 Platform Foundation` is in progress, any tags are pre-release (`0.x.y`). The first `1.0.0` tag requires explicit human approval once a stable, deployable baseline exists.

## Pull requests

- **One story per pull request.** A PR implements exactly one approved story ID from the Engineering Backlog. Do not bundle unrelated stories, refactors, or dependency upgrades into the same PR.
- Use the [pull request template](.github/PULL_REQUEST_TEMPLATE.md) and do not delete its sections.
- Reference the story ID in the PR title and description.
- Keep changes scoped to the files required by the approved story, per `AGENTS.md`.

### Dependabot maintenance pull requests (narrow exception)

GitHub-generated Dependabot update pull requests (PF-032) are **maintenance pull requests**, not product-story work, and are the single narrow exception to the story-ID rule above:

- A Dependabot PR is **not manually assigned a product-story ID**. It is identified by the generated dependency update itself.
- Each Dependabot PR **must remain narrowly scoped to the generated dependency update** it was opened for. Do not add unrelated changes, refactors, documentation rewrites, or a second dependency bump to it — open a normal story PR for that instead.
- Each Dependabot PR **must pass all four required status checks and receive human review before merge**, exactly like any other pull request. Nothing merges automatically.
- A dependency change that is not a routine version bump still needs its own approval. A **new** runtime dependency, or a change that alters authentication/authorization, database design, public API contracts, billing, or AI governance, requires the explicit human approval gate below regardless of which tool opened the pull request.

**This exception does not weaken the one-approved-story-per-human-authored-PR rule.** Every human-authored pull request still implements exactly one approved story ID from the Engineering Backlog.

## Testing and documentation requirements

Every pull request must, per the Sprint Plan's Definition of Done:

- Include the unit, application, integration, feature, security, or architecture tests appropriate to the change.
- State the exact test commands executed and their results — never claim tests passed without having actually run them.
- Update relevant documentation (module `README.md`, `docs/PROJECT_STATUS.md`, or architecture docs) whenever behavior, scope, or story status changes.
- Pass static analysis where configured for the affected code.

## Continuous integration (PF-030)

A single tracked workflow, [`.github/workflows/ci.yml`](.github/workflows/ci.yml) (named `CI`), runs the repository's already-configured checks automatically. It triggers on `pull_request` targeting `main` and on manual `workflow_dispatch` — there is no `push`, scheduled, or deployment trigger.

Three stable checks from the `CI` workflow appear on every pull request; PF-032's separate `Security` workflow supplies the fourth required check, `Dependency Audit`:

| Check name | Job ID | What it runs |
|---|---|---|
| **PHP Code Quality** | `quality` | `composer validate --strict`, `composer install`, check-only `composer pint:test`, `composer phpstan` |
| **Frontend Build** | `frontend` | `npm ci`, `npm run build`, then uploads `public/build/` as a short-retention artifact |
| **Application Tests** | `tests` | provisions PostgreSQL 16 with separate migration, runtime, and outbox roles, migrates through the owning migration connection, grants narrowly enumerated framework access, then runs the fail-closed guards and `composer test` through the non-owning runtime role |

- **Application Tests depends on Frontend Build.** A fresh CI runner has no Vite dev server and therefore no `public/hot` file, so Laravel's `@vite` directive resolves assets through `public/build/manifest.json` instead. That manifest must exist before the suite runs, so the tests job consumes the frontend job's build output rather than rebuilding it.
- CI uses **PHP 8.4**, **Node 22**, and an ephemeral **PostgreSQL 16** service on `ubuntu-24.04`, matching the development environment's database engine. The required `Application Tests` job creates separate non-superuser, `NOBYPASSRLS` migration/runtime/outbox roles and a disposable database. Migrations run only as the owning migration role; tests run as the non-owning runtime role. The engine and role-separation guards fail closed when that posture is absent. Redis, a queue worker, production credentials, persistent volumes, and deployment services are not involved.
- [`phpunit.xml`](phpunit.xml) intentionally retains its SQLite `:memory:` fallback for ordinary fast local runs. That fallback is **not PF-033 completion evidence**. Canonical local PostgreSQL validation uses the running Compose services and explicit overrides:

  ```sh
  # PF-074 provisioning runs only when PostgreSQL initializes an empty volume.
  # This intentionally deletes all disposable local database data.
  docker compose down -v
  docker compose up -d postgres redis app
  docker compose exec -T app sh -c 'test -f .env || cp .env.example .env'
  docker compose exec -T app php artisan key:generate --ansi
  docker compose exec -T app php artisan migrate --force --no-interaction --database=pgsql_migration
  docker compose exec -T postgres sh /docker-entrypoint-initdb.d/10-provision-roles.sh grants
  docker compose exec -T -e REQUIRE_POSTGRESQL_TEST_DATABASE=true app composer test
  ```

  Three caveats for the commands above. `php artisan key:generate` **rewrites `APP_KEY` in your local `.env`** each time it runs — omit it if you need your existing local key preserved. `Tests\Feature\ExampleTest` renders a view containing `@vite(...)`, so it needs either the Compose `vite` service running (which writes `public/hot`) or a previously built `public/build/manifest.json`; with neither present that test fails for a frontend reason unrelated to the database. And **the recipe is safe only when run whole**: the leading `docker compose down -v` is what makes the target database disposable, so re-running only the final `composer test` line points the suite at `onelegalpro`, your working local database, and `migrate:fresh`-style suites will drop what is in it.

  These are local-development-only defaults from the Compose environment, never production credentials. PF-074 verifies the development/CI role split only; it does not provision production, create a Row-Level Security policy, or prove tenant isolation end to end.

  **Manual teardown of the local roles** is destructive and therefore documented rather than automated. Run it per database, in this order, connected as the bootstrap superuser:

  1. `REASSIGN OWNED BY <migration role> TO <new owner>` — every object the migration role owns must be transferred before the role can be dropped. **Choose the new owner deliberately:** reassigning to the bootstrap superuser recreates exactly the superuser-owns-every-relation arrangement PF-074 exists to remove, so do this only when the database itself is about to be dropped.
  2. `DROP OWNED BY <role>` for each of the three roles — this removes the privileges granted to them, which `REASSIGN OWNED BY` does not touch.
  3. `DROP ROLE <role>` for each of the three roles.

  `REASSIGN OWNED BY` and `DROP OWNED BY` act only on the database you are connected to, so steps 1 and 2 must be repeated in **every** database the roles own objects in; `DROP ROLE` is cluster-wide and fails until they are clean everywhere.
- **Every action is pinned to a full commit SHA**, with its human-readable release tag in an inline comment. PF-032 added Dependabot's `github-actions` ecosystem to propose updates to those references *without* unpinning them — see [Security scanning (PF-032)](#security-scanning-pf-032) below.
- The workflow uses **read-only permissions (`contents: read`) and no secrets**, and uses `pull_request` rather than `pull_request_target`, so pull requests from forks run safely without privileged access.
- **All three of this workflow's checks are required to merge (PF-031).** PF-030 made them visible; PF-031 made them mandatory through the `Protect main` ruleset, so a pull request with a failing or missing check cannot be merged into `main` — see [Required status checks (PF-031)](#required-status-checks-pf-031) above. PF-032 later added a fourth required check, `Dependency Audit`, from a separate workflow — see [Security scanning (PF-032)](#security-scanning-pf-032) below.
- CI calls the Composer and npm scripts directly. It never invokes the PF-023 Git hooks below, and it runs regardless of whether a developer has installed them.

## Security scanning (PF-032)

A second tracked workflow, [`.github/workflows/security.yml`](.github/workflows/security.yml) (named `Security`), audits the committed dependency lock files. It triggers on `pull_request` targeting `main` and on manual `workflow_dispatch` — there is no `push`, `schedule`, `pull_request_target`, `workflow_run`, deployment, or path-filter trigger.

| Check name | Job ID | What it runs |
|---|---|---|
| **Dependency Audit** | `dependencies` | `composer audit --locked --abandoned=report --no-interaction`, then `npm audit --audit-level=high` |

- **`Dependency Audit` is a required status check.** The repository owner added it to the active `Protect main` ruleset from GitHub Actions, so it joins `PHP Code Quality`, `Frontend Build`, and `Application Tests` — all four must pass before `main` may be updated. Renaming the job is a two-part change (workflow *and* ruleset), exactly as for the other three.
- **The audit reads lock files and never remediates.** `--locked` audits `composer.lock` directly and `npm audit` reads `package-lock.json` directly, so no `composer install` or `npm ci` runs, no `vendor/` or `node_modules/` directory exists in the job, and no third-party dependency code or package lifecycle script executes. `composer.json`, `composer.lock`, `package.json`, and `package-lock.json` are never written; `npm audit fix`, `composer update`, and `npm update` are never invoked.
- **Failures are not ignored.** `--ignore-unreachable` is deliberately not used, so an unreachable advisory database fails the check rather than passing as though nothing were found. `--abandoned=report` is pinned explicitly so a future Composer default change cannot silently turn an abandoned package into a merge blocker. `--omit=dev` is deliberately not used: `package.json` declares no production dependencies, so omitting development dependencies would audit nothing.
- **The job is independent.** It declares no `needs:` and no `if:` condition, so it can never report the `skipped` conclusion that GitHub's required-check evaluation does not treat as blocking.
- **Read-only and secretless:** `permissions: contents: read`, no repository secret, `persist-credentials: false` on checkout, no SARIF upload, and no artifact upload or download. Every action is pinned to a full 40-character commit SHA with its release tag in an inline comment, and no new third-party action was introduced — all three are already used by [`ci.yml`](.github/workflows/ci.yml) at the same SHAs.
- **`.github/dependabot.yml` configures weekly update proposals** for three ecosystems — **Composer**, **npm**, and **GitHub Actions** — each with an open-pull-request limit of 5 and no grouping. The GitHub Actions entry keeps action references pinned to full commit SHAs: Dependabot proposes a new SHA and rewrites the trailing release-tag comment, never a mutable version tag.
- **Dependabot merges nothing.** Every proposal is an ordinary pull request that must pass all four required checks and receive human review before merge, exactly like any other change.

### GitHub-native protections (verified enabled)

These live in GitHub's repository settings, not in this repository. The repository owner verified each one as **enabled** at PF-032 closure:

- Dependency graph
- Dependabot alerts
- Dependabot security updates
- Secret Protection
- Push Protection

Push Protection means a push containing a detected secret pattern is rejected by GitHub. That is a prevention control, not a status check, and it never affects whether a pull request may merge. The secret-handling rules below still apply in full: a committed secret is compromised and must be rotated, and a history rewrite alone is not sufficient remediation.

### Deliberately deferred (not enabled, not required)

Do not treat any of the following as an active control:

- **CodeQL/SAST** — not configured. CodeQL does not support PHP, and this repository's entire JavaScript surface is a placeholder, so it would certify nothing today. Reconsider against real `app/Modules/` code.
- **GitHub Dependency Review** — not configured. Its vulnerability coverage is a subset of the lock-file audit above, which already audits the whole dependency tree rather than only the pull request's additions.
- **SARIF upload** — nothing produces or uploads SARIF, and no workflow is granted `security-events` permission.
- **Container scanning** — not configured. The Docker files are development-only and there is no production deployment.
- **Scheduled scanning** — no workflow has a `schedule` trigger. Advisory drift against unchanged `main` is covered by Dependabot alerts instead.
- **Third-party secret scanners** — none. GitHub's native Secret Protection above is the only secret-scanning control.
- **Automatic dependency submission** — disabled.
- **Grouped security updates** — disabled, and no Dependabot `groups` configuration exists.
- **AI findings** — off.

Everything the `Protect main` ruleset still does not enforce is listed under [Protect main (GitHub ruleset)](#protect-main-github-ruleset) above and remains accurate: commit signing, linear history, required deployments, a merge queue, required code-scanning results, required GitHub code-quality results, code-coverage thresholds, and automatic Copilot review are all still not enabled.

## Local Git hooks (PF-023)

Tracked, reviewable Git hooks live in [`.githooks/`](.githooks) and check Conventional Commit formatting plus the already-configured Pint and Larastan/PHPStan commands (and the test suite) before a commit or push completes locally.

- **Installation is intentional, never automatic.** Nothing installs these hooks during `composer install`, `git clone`, or Docker startup. Opt in yourself with `sh .githooks/install.sh`; check status with `sh .githooks/status.sh`; remove with `sh .githooks/uninstall.sh`. Installation only ever sets this repository's **local** `core.hooksPath` — it never modifies global, system, worktree, or user Git configuration, and it also protects any **inherited/effective** custom hooks path already configured outside this repository (global, system, or worktree) by refusing to install over it rather than silently overriding it.
- **Status distinguishes ownership.** `sh .githooks/status.sh` reports whether PF-023 hooks are installed via this repository's local configuration, whether a different repository-local custom path is configured, or whether no local value exists but an inherited/effective hooks path applies from outside the repository (clearly reported as not owned by PF-023) — versus nothing configured anywhere.
- **Uninstall only removes what PF-023 installed.** `sh .githooks/uninstall.sh` unsets the repository-local `core.hooksPath` only when it is exactly `.githooks`; it never touches a different repository-local value and never touches an inherited/effective value from outside the repository.
- **Check mapping:** `pre-commit` runs `git diff --cached --check` and check-only `composer pint:test`; `commit-msg` validates the commit subject against this document's Conventional Commits rule below; `pre-push` runs `composer phpstan` and `composer test`.
- **Conventional Commit enforcement:** the `commit-msg` hook enforces the `<type>(<scope>): <description>` format and allowed types defined above, with `Merge`, `Revert "`, `fixup! ` (space required), and `squash! ` (space required) subjects exempted since they are Git- or tooling-generated, not authored against this convention — `fixup!`/`squash!` without the trailing space are validated as ordinary commit subjects instead.
- **Bypass acknowledgement:** `git commit --no-verify` / `git push --no-verify` skip these hooks by design — that is an intentional emergency escape hatch, not a statement that the skipped code is clean.
- **Local hooks are not a substitute for CI, and never the merge gate.** These are fast, bypassable, machine-local feedback only — genuinely useful for catching a problem before you push, but they decide nothing about whether a change may reach `main`. The active `Protect main` ruleset above is the authoritative enforcement boundary: it requires the three PF-030 GitHub Actions checks (since PF-031) plus PF-032's `Dependency Audit`, all of which run independently of these hooks and cannot be bypassed with `--no-verify`. **No hook runs any audit or security command** — dependency auditing exists only in CI. This section does not weaken any existing branch, PR, review, security, or approval rule.
- **Supported environments:** macOS, Linux, WSL, and native Windows with Git for Windows (Git Bash bundled). A raw Windows shell (`cmd.exe`/PowerShell) without Git for Windows or WSL is not supported.

## AI-assisted development requirements

Any AI coding assistant (Claude Code, Codex, or similar) working in this repository must follow [`AGENTS.md`](AGENTS.md) and `docs/implementation/02_AI_Developer_Playbook.md`:

- Read the source-of-truth documents before making changes.
- Identify the story ID, module, use case, and required tests before writing code.
- Modify only files required by the approved story — no unrelated refactors, reformatting, or dependency upgrades.
- Never invent missing legal, business, or architecture rules; stop and request human approval when a request conflicts with approved architecture.
- Never fabricate or claim test results that were not actually executed.
- Human review and approval is required before merge — AI-authored commits do not bypass code review, architecture review, or QA.

## Security and secret-handling rules

- Never commit `.env`, credentials, API keys, tokens, or confidential client/matter content. `.gitignore` excludes `.env.*` (except `.env.example`) for this reason — do not override or bypass it.
- Never weaken authentication, authorization, Firm isolation, Ethical Walls, encryption, or auditability to make a change easier.
- If a secret is committed accidentally, treat it as compromised: rotate it immediately and notify the repository owner. A history rewrite alone is not sufficient remediation.
- Report suspected security issues privately to the repository owner rather than in a public issue.

## Review and approval gates

Per `AGENTS.md`, the following changes require explicit human approval before merge, regardless of who authored them:

- Authentication or authorization changes
- Database redesign
- Public API breaking changes
- New runtime dependencies
- Billing or payment changes
- AI governance changes
- Destructive operations
- Removing tests or security controls
