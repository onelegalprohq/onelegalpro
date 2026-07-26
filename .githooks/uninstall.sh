#!/usr/bin/env sh
# .githooks/uninstall.sh
#
# PF-023 hook uninstaller. Unsets this repository's LOCAL core.hooksPath,
# but only when it is exactly ".githooks" - never touches an unrelated
# custom hooks path, and never deletes .git/hooks/ or any user files.
#
# Usage: sh .githooks/uninstall.sh
set -eu

repo_root="$(git rev-parse --show-toplevel 2>/dev/null)" || {
    printf 'error: not inside a Git working tree.\n' >&2
    exit 1
}
cd "${repo_root}"

current="$(git config --local --get core.hooksPath 2>/dev/null || true)"

if [ -z "${current}" ]; then
    printf 'Git hooks: already not installed (core.hooksPath is unset). No change made.\n'
elif [ "${current}" = ".githooks" ]; then
    git config --local --unset core.hooksPath
    printf 'Git hooks: uninstalled (core.hooksPath unset).\n'
else
    printf 'error: a custom core.hooksPath is configured: "%s"\n' "${current}" >&2
    printf 'Refusing to alter it - this was not set by .githooks/install.sh.\n' >&2
    exit 1
fi
