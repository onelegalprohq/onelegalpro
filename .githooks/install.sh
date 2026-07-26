#!/usr/bin/env sh
# .githooks/install.sh
#
# PF-023 hook installer. Sets this repository's LOCAL core.hooksPath to
# ".githooks". Never touches global Git configuration, and never overwrites
# a pre-existing custom hooks path. Run intentionally - never wired into
# Composer or Docker lifecycle events.
#
# Usage: sh .githooks/install.sh
set -eu

repo_root="$(git rev-parse --show-toplevel 2>/dev/null)" || {
    printf 'error: not inside a Git working tree.\n' >&2
    exit 1
}
cd "${repo_root}"

current="$(git config --local --get core.hooksPath 2>/dev/null || true)"

if [ -z "${current}" ]; then
    git config --local core.hooksPath .githooks
    printf 'Git hooks installed: core.hooksPath=.githooks\n'
elif [ "${current}" = ".githooks" ]; then
    printf 'Git hooks already installed: core.hooksPath=.githooks (no change made)\n'
else
    printf 'error: a custom core.hooksPath is already configured: "%s"\n' "${current}" >&2
    printf 'Refusing to overwrite it. Resolve manually, then re-run this installer.\n' >&2
    exit 1
fi

printf 'Check status any time with: sh .githooks/status.sh\n'
