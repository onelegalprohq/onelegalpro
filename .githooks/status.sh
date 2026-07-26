#!/usr/bin/env sh
# .githooks/status.sh
#
# PF-023 hook status. Reports this repository's LOCAL core.hooksPath, and
# distinguishes it from an inherited/effective value coming from global,
# system, or worktree Git configuration. Never changes configuration.
#
# Usage: sh .githooks/status.sh
set -eu

repo_root="$(git rev-parse --show-toplevel 2>/dev/null)" || {
    printf 'error: not inside a Git working tree.\n' >&2
    exit 1
}
cd "${repo_root}"

local_value="$(git config --local --get core.hooksPath 2>/dev/null || true)"

if [ "${local_value}" = ".githooks" ]; then
    printf 'Git hooks: installed (repository-local core.hooksPath=.githooks)\n'
    exit 0
fi

if [ -n "${local_value}" ]; then
    printf 'Git hooks: custom repository-local hooks path configured (core.hooksPath=%s)\n' "${local_value}"
    exit 0
fi

effective_value="$(git config --get core.hooksPath 2>/dev/null || true)"

if [ -n "${effective_value}" ]; then
    effective_origin_line="$(git config --show-origin --get core.hooksPath 2>/dev/null || true)"
    printf 'Git hooks: not installed locally by PF-023.\n'
    if [ -n "${effective_origin_line}" ]; then
        printf 'An inherited hooks path is configured outside this repository: %s\n' "${effective_origin_line}"
    else
        printf 'An inherited hooks path is configured outside this repository: core.hooksPath=%s\n' "${effective_value}"
    fi
    exit 0
fi

printf 'Git hooks: not installed (no core.hooksPath configured at any scope)\n'
exit 0
