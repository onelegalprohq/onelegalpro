#!/usr/bin/env sh
# .githooks/uninstall.sh
#
# PF-023 hook uninstaller. Unsets this repository's LOCAL core.hooksPath,
# but only when it is exactly ".githooks" - never touches an unrelated
# repository-local custom hooks path, never touches an inherited/effective
# hooks path from global, system, or worktree configuration, and never
# deletes .git/hooks/, .githooks/, or any user files.
#
# Usage: sh .githooks/uninstall.sh
set -eu

repo_root="$(git rev-parse --show-toplevel 2>/dev/null)" || {
    printf 'error: not inside a Git working tree.\n' >&2
    exit 1
}
cd "${repo_root}"

local_value="$(git config --local --get core.hooksPath 2>/dev/null || true)"

if [ "${local_value}" = ".githooks" ]; then
    git config --local --unset core.hooksPath
    printf 'Git hooks: uninstalled (repository-local core.hooksPath unset).\n'
    exit 0
fi

if [ -n "${local_value}" ]; then
    printf 'error: a custom repository-local core.hooksPath is configured: "%s"\n' "${local_value}" >&2
    printf 'Refusing to alter it - this was not set by .githooks/install.sh.\n' >&2
    exit 1
fi

effective_value="$(git config --get core.hooksPath 2>/dev/null || true)"

if [ -n "${effective_value}" ]; then
    printf 'Git hooks: PF-023 is not installed locally (no repository-local core.hooksPath).\n'
    printf 'An inherited hooks path is configured outside this repository and is left untouched.\n'
    exit 0
fi

printf 'Git hooks: already not installed at any scope. No change made.\n'
