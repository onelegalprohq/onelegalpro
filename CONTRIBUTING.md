# Contributing to OneLegalPro

This document defines the Git workflow, commit, versioning, and review standards for this repository. It complements — and never overrides — [`AGENTS.md`](AGENTS.md) and the approved documents in `docs/implementation/` and `docs/domain/`. If anything here conflicts with those documents, the approved architecture documents govern; stop and request an architecture decision rather than resolving the conflict unilaterally.

## Branch strategy

This repository uses a **main plus feature-branch** strategy:

- `main` is always deployable and protected. No direct commits to `main`.
- All work happens on a short-lived feature branch created from `main` and merged back via pull request.
- Delete feature branches after merge.

### Protect main (GitHub ruleset)

`main` is protected by an active GitHub branch ruleset (`Protect main`), verified at PF-002 closure:

- A pull request is required before merging to `main`.
- Branch deletion and force-pushes to `main` are blocked.
- Review conversations must be resolved before a pull request can merge.
- No bypass actor is configured — the ruleset applies to everyone, with no exceptions.
- The required formal GitHub approval count is temporarily **0**, because this is currently a single-owner repository and GitHub does not allow an author to approve their own pull request. Human review and approval is still mandatory: record it as an explicit review comment on the pull request until a second authorized reviewer is available. Formal approving reviews (a required approval count above zero) must be configured as soon as a second authorized reviewer exists.
- **No status check is currently required by the ruleset.** PF-030 added a GitHub Actions workflow whose checks now run automatically on every pull request targeting `main` (see [Continuous integration (PF-030)](#continuous-integration-pf-030) below), so their results are visible on the pull request — but the ruleset does not yet require any of them to pass before merging. Making specific checks required is PF-031 (Quality Gates) scope.
- Commit signing, code scanning, code quality, and coverage requirements are **not currently enabled**. They are deferred to their own approved future stories (PF-031 Quality Gates, PF-032 Security Scanning) and must not be treated as active until those stories configure and verify them.

This ruleset does not weaken the rule above: `main` remains deployable and protected at all times.

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

## Testing and documentation requirements

Every pull request must, per the Sprint Plan's Definition of Done:

- Include the unit, application, integration, feature, security, or architecture tests appropriate to the change.
- State the exact test commands executed and their results — never claim tests passed without having actually run them.
- Update relevant documentation (module `README.md`, `docs/PROJECT_STATUS.md`, or architecture docs) whenever behavior, scope, or story status changes.
- Pass static analysis where configured for the affected code.

## Continuous integration (PF-030)

A single tracked workflow, [`.github/workflows/ci.yml`](.github/workflows/ci.yml) (named `CI`), runs the repository's already-configured checks automatically. It triggers on `pull_request` targeting `main` and on manual `workflow_dispatch` — there is no `push`, scheduled, or deployment trigger.

Three stable checks appear on every pull request:

| Check name | Job ID | What it runs |
|---|---|---|
| **PHP Code Quality** | `quality` | `composer validate --strict`, `composer install`, check-only `composer pint:test`, `composer phpstan` |
| **Frontend Build** | `frontend` | `npm ci`, `npm run build`, then uploads `public/build/` as a short-retention artifact |
| **Application Tests** | `tests` | `composer install`, `.env` from `.env.example` + `php artisan key:generate`, downloads the frontend artifact, `composer test` |

- **Application Tests depends on Frontend Build.** A fresh CI runner has no Vite dev server and therefore no `public/hot` file, so Laravel's `@vite` directive resolves assets through `public/build/manifest.json` instead. That manifest must exist before the suite runs, so the tests job consumes the frontend job's build output rather than rebuilding it.
- CI uses **PHP 8.4** and **Node 22** on `ubuntu-24.04`, matching the Docker development environment. Tests run against SQLite `:memory:` per [`phpunit.xml`](phpunit.xml) — no PostgreSQL, Redis, queue worker, or Docker service is involved.
- **Every action is pinned to a full commit SHA**, with its human-readable release tag in an inline comment. Automated updating of those references is PF-032 scope.
- The workflow uses **read-only permissions (`contents: read`) and no secrets**, and uses `pull_request` rather than `pull_request_target`, so pull requests from forks run safely without privileged access.
- **These checks are visible but not yet required.** Until PF-031 configures the `Protect main` ruleset, a pull request can still be merged with a failing check — treat the results as authoritative feedback, not an enforced gate.
- CI calls the Composer and npm scripts directly. It never invokes the PF-023 Git hooks below, and it runs regardless of whether a developer has installed them.

## Local Git hooks (PF-023)

Tracked, reviewable Git hooks live in [`.githooks/`](.githooks) and check Conventional Commit formatting plus the already-configured Pint and Larastan/PHPStan commands (and the test suite) before a commit or push completes locally.

- **Installation is intentional, never automatic.** Nothing installs these hooks during `composer install`, `git clone`, or Docker startup. Opt in yourself with `sh .githooks/install.sh`; check status with `sh .githooks/status.sh`; remove with `sh .githooks/uninstall.sh`. Installation only ever sets this repository's **local** `core.hooksPath` — it never modifies global, system, worktree, or user Git configuration, and it also protects any **inherited/effective** custom hooks path already configured outside this repository (global, system, or worktree) by refusing to install over it rather than silently overriding it.
- **Status distinguishes ownership.** `sh .githooks/status.sh` reports whether PF-023 hooks are installed via this repository's local configuration, whether a different repository-local custom path is configured, or whether no local value exists but an inherited/effective hooks path applies from outside the repository (clearly reported as not owned by PF-023) — versus nothing configured anywhere.
- **Uninstall only removes what PF-023 installed.** `sh .githooks/uninstall.sh` unsets the repository-local `core.hooksPath` only when it is exactly `.githooks`; it never touches a different repository-local value and never touches an inherited/effective value from outside the repository.
- **Check mapping:** `pre-commit` runs `git diff --cached --check` and check-only `composer pint:test`; `commit-msg` validates the commit subject against this document's Conventional Commits rule below; `pre-push` runs `composer phpstan` and `composer test`.
- **Conventional Commit enforcement:** the `commit-msg` hook enforces the `<type>(<scope>): <description>` format and allowed types defined above, with `Merge`, `Revert "`, `fixup! ` (space required), and `squash! ` (space required) subjects exempted since they are Git- or tooling-generated, not authored against this convention — `fixup!`/`squash!` without the trailing space are validated as ordinary commit subjects instead.
- **Bypass acknowledgement:** `git commit --no-verify` / `git push --no-verify` skip these hooks by design — that is an intentional emergency escape hatch, not a statement that the skipped code is clean.
- **Local hooks are not a substitute for CI.** These are fast, bypassable, machine-local feedback only. The `Protect main` branch ruleset above and the PF-030 GitHub Actions checks run independently of them — this section does not weaken any existing branch, PR, review, security, or approval rule. Note that the PF-030 checks, while unbypassable by `--no-verify`, are not yet *required* by the ruleset; PF-031 (Quality Gates) owns that enforcement.
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
