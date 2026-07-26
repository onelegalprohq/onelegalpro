#!/usr/bin/env sh
# .githooks/lib/common.sh
#
# Shared helper functions for OneLegalPro's tracked Git hooks (PF-023).
# This file is sourced by the hook scripts in .githooks/ - it is not meant
# to be executed directly and performs no action on its own.
#
# POSIX sh only: no Bash-specific syntax, no eval.

# Resolves the repository root and cds into it, so a check behaves the same
# regardless of the working directory the Git action was triggered from.
hooks_cd_repo_root() {
    hooks_repo_root="$(git rev-parse --show-toplevel 2>/dev/null)" || {
        printf 'error: not inside a Git working tree.\n' >&2
        return 1
    }
    cd "${hooks_repo_root}" || {
        printf 'error: could not enter repository root "%s".\n' "${hooks_repo_root}" >&2
        return 1
    }
}

# True (0) when Docker and Docker Compose are available and a running "app"
# service is defined.
hooks_docker_app_running() {
    command -v docker >/dev/null 2>&1 || return 1
    docker compose version >/dev/null 2>&1 || return 1
    docker compose ps --status running --services 2>/dev/null | grep -qx "app"
}

# Prints actionable guidance when neither Docker nor a usable host Composer
# environment is available. Always returns non-zero - never a silent skip.
hooks_fail_no_environment() {
    hooks_check_name="$1"
    printf '%s\n' "----------------------------------------------------------------------" >&2
    printf 'Cannot run check: %s\n' "${hooks_check_name}" >&2
    printf '%s\n' "" >&2
    printf '%s\n' "Neither a running Docker \"app\" service nor a usable host Composer" >&2
    printf '%s\n' "environment was found. To fix this:" >&2
    printf '%s\n' "" >&2
    printf '%s\n' "  1. Start Docker Desktop (or your Docker engine)." >&2
    printf '%s\n' "  2. Run: docker compose up -d" >&2
    printf '%s\n' "  3. Ensure dependencies are installed: docker compose exec app composer install" >&2
    printf '%s\n' "  4. Re-run the Git action that triggered this check." >&2
    printf '%s\n' "" >&2
    printf '%s\n' "\"--no-verify\" exists only as an intentional emergency bypass and does" >&2
    printf '%s\n' "not replace this validation." >&2
    printf '%s\n' "----------------------------------------------------------------------" >&2
    return 1
}

# Runs an existing Composer script (e.g. "pint:test", "phpstan", "test"),
# preferring Docker and falling back to a usable host environment. Never
# silently skips, and always returns the invoked command's exit status.
hooks_run_composer_check() {
    hooks_check_name="$1"

    hooks_cd_repo_root || return 1

    printf 'Running: composer %s\n' "${hooks_check_name}"

    if hooks_docker_app_running; then
        docker compose exec -T app composer "${hooks_check_name}"
        return $?
    fi

    if command -v composer >/dev/null 2>&1 && [ -f "vendor/autoload.php" ]; then
        composer "${hooks_check_name}"
        return $?
    fi

    hooks_fail_no_environment "composer ${hooks_check_name}"
}
