#!/usr/bin/env sh
# .githooks/status.sh
#
# PF-023 hook status. Reports this repository's LOCAL core.hooksPath.
# Never changes configuration.
#
# Usage: sh .githooks/status.sh
set -eu

repo_root="$(git rev-parse --show-toplevel 2>/dev/null)" || {
    printf 'error: not inside a Git working tree.\n' >&2
    exit 1
}
cd "${repo_root}"

current="$(git config --local --get core.hooksPath 2>/dev/null || true)"

if [ -z "${current}" ]; then
    printf 'Git hooks: not installed (core.hooksPath is unset)\n'
elif [ "${current}" = ".githooks" ]; then
    printf 'Git hooks: installed (core.hooksPath=.githooks)\n'
else
    printf 'Git hooks: custom hooks path configured (core.hooksPath=%s)\n' "${current}"
fi

exit 0
