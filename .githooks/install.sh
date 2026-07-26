#!/usr/bin/env sh
# .githooks/install.sh
#
# PF-023 hook installer. Sets this repository's LOCAL core.hooksPath to
# ".githooks". Never touches global, system, worktree, or user Git
# configuration, and never overwrites a pre-existing repository-local OR
# inherited/effective custom hooks path. Run intentionally - never wired
# into Composer or Docker lifecycle events.
#
# Usage: sh .githooks/install.sh
set -eu

repo_root="$(git rev-parse --show-toplevel 2>/dev/null)" || {
    printf 'error: not inside a Git working tree.\n' >&2
    exit 1
}
cd "${repo_root}"

local_value="$(git config --local --get core.hooksPath 2>/dev/null || true)"

if [ "${local_value}" = ".githooks" ]; then
    printf 'Git hooks already installed: core.hooksPath=.githooks (no change made)\n'
    printf 'Check status any time with: sh .githooks/status.sh\n'
    exit 0
fi

if [ -n "${local_value}" ]; then
    printf 'error: a custom repository-local core.hooksPath is already configured: "%s"\n' "${local_value}" >&2
    printf 'Refusing to overwrite it. Resolve manually, then re-run this installer.\n' >&2
    exit 1
fi

# No repository-local value. Before installing, check whether an
# inherited/effective hooks path already applies (global, system, or
# worktree configuration) and refuse to silently override it.
effective_value="$(git config --get core.hooksPath 2>/dev/null || true)"

if [ -n "${effective_value}" ]; then
    effective_origin_line="$(git config --show-origin --get core.hooksPath 2>/dev/null || true)"
    printf 'error: no repository-local core.hooksPath is set, but an inherited hooks path\n' >&2
    printf 'is already configured outside this repository:\n' >&2
    if [ -n "${effective_origin_line}" ]; then
        printf '  %s\n' "${effective_origin_line}" >&2
    else
        printf '  core.hooksPath=%s\n' "${effective_value}" >&2
    fi
    printf 'Refusing to override inherited (global/system/worktree) Git configuration with a\n' >&2
    printf 'repository-local setting. Resolve manually if you want PF-023 hooks installed.\n' >&2
    exit 1
fi

git config --local core.hooksPath .githooks
printf 'Git hooks installed: core.hooksPath=.githooks\n'
printf 'Check status any time with: sh .githooks/status.sh\n'
