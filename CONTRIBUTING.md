# Contributing to OneLegalPro

This document defines the Git workflow, commit, versioning, and review standards for this repository. It complements — and never overrides — [`AGENTS.md`](AGENTS.md) and the approved documents in `docs/implementation/` and `docs/domain/`. If anything here conflicts with those documents, the approved architecture documents govern; stop and request an architecture decision rather than resolving the conflict unilaterally.

## Branch strategy

This repository uses a **main plus feature-branch** strategy:

- `main` is always deployable and protected. No direct commits to `main`.
- All work happens on a short-lived feature branch created from `main` and merged back via pull request.
- Delete feature branches after merge.

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
