#!/bin/sh
set -eu

: "${POSTGRES_USER:?POSTGRES_USER is required}"
: "${POSTGRES_DB:?POSTGRES_DB is required}"
: "${PF074_MIGRATION_ROLE:?PF074_MIGRATION_ROLE is required}"
: "${PF074_MIGRATION_PASSWORD:?PF074_MIGRATION_PASSWORD is required}"
: "${PF074_RUNTIME_ROLE:?PF074_RUNTIME_ROLE is required}"
: "${PF074_RUNTIME_PASSWORD:?PF074_RUNTIME_PASSWORD is required}"
: "${PF074_OUTBOX_ROLE:?PF074_OUTBOX_ROLE is required}"
: "${PF074_OUTBOX_PASSWORD:?PF074_OUTBOX_PASSWORD is required}"

# Role names and passwords are read from the environment by psql itself, with
# \getenv, and never appear in argv. Passing them as --set arguments would place
# every password in `ps` output and /proc/<pid>/cmdline for the life of the
# process, readable by any local user. \getenv requires psql 14 or newer; both
# supported paths (the postgres:16 container and the CI runner's client) are 16.
# ${psql_base} is intentionally unquoted so it word-splits into arguments.
psql_base="psql --set=ON_ERROR_STOP=1 --username=${POSTGRES_USER}"

bootstrap() {
    ${psql_base} --dbname=postgres <<'SQL'
\getenv database_name POSTGRES_DB
\getenv migration_role PF074_MIGRATION_ROLE
\getenv migration_password PF074_MIGRATION_PASSWORD
\getenv runtime_role PF074_RUNTIME_ROLE
\getenv runtime_password PF074_RUNTIME_PASSWORD
\getenv outbox_role PF074_OUTBOX_ROLE
\getenv outbox_password PF074_OUTBOX_PASSWORD

SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD %L', :'migration_role', :'migration_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'migration_role') \gexec
SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD %L', :'runtime_role', :'runtime_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'runtime_role') \gexec
SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD %L', :'outbox_role', :'outbox_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'outbox_role') \gexec

SELECT format('ALTER ROLE %I LOGIN NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD %L', :'migration_role', :'migration_password') \gexec
SELECT format('ALTER ROLE %I LOGIN NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD %L', :'runtime_role', :'runtime_password') \gexec
SELECT format('ALTER ROLE %I LOGIN NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD %L', :'outbox_role', :'outbox_password') \gexec
SELECT format('REVOKE %I FROM %I', member_role, granted_role)
FROM (VALUES
    (:'migration_role', :'runtime_role'), (:'migration_role', :'outbox_role'),
    (:'runtime_role', :'migration_role'), (:'runtime_role', :'outbox_role'),
    (:'outbox_role', :'migration_role'), (:'outbox_role', :'runtime_role')
) AS memberships(member_role, granted_role) \gexec

SELECT format('CREATE DATABASE %I OWNER %I', :'database_name', :'migration_role')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'database_name') \gexec
SELECT format('ALTER DATABASE %I OWNER TO %I', :'database_name', :'migration_role') \gexec
SELECT format('REVOKE ALL ON DATABASE %I FROM PUBLIC', :'database_name') \gexec
SELECT format('GRANT CONNECT ON DATABASE %I TO %I', :'database_name', :'migration_role') \gexec
-- TEMPORARY is required, not incidental: the PF-073 FirmTransactionManager
-- PostgreSQL suite issues `create temp table` on the runtime connection.
SELECT format('GRANT CONNECT, TEMPORARY ON DATABASE %I TO %I', :'database_name', :'runtime_role') \gexec
SELECT format('GRANT CONNECT ON DATABASE %I TO %I', :'database_name', :'outbox_role') \gexec
SQL

    ${psql_base} --dbname="${POSTGRES_DB}" <<'SQL'
\getenv migration_role PF074_MIGRATION_ROLE
\getenv runtime_role PF074_RUNTIME_ROLE

SELECT format('ALTER SCHEMA public OWNER TO %I', :'migration_role') \gexec
REVOKE ALL ON SCHEMA public FROM PUBLIC;
SELECT format('GRANT USAGE ON SCHEMA public TO %I', :'runtime_role') \gexec

-- No ALTER DEFAULT PRIVILEGES statement appears here, deliberately.
--
-- PostgreSQL unions the built-in acldefault() into whatever pg_default_acl
-- holds when an object is created, and ALTER DEFAULT PRIVILEGES computes its
-- new access control list starting from an empty one rather than from
-- acldefault(). A `REVOKE ... FROM PUBLIC` therefore removes nothing, yields an
-- empty list, and is never stored -- and even a stored entry created with GRANT
-- still has PUBLIC's built-in EXECUTE on functions and USAGE on types merged
-- back in at creation time. No formulation of ALTER DEFAULT PRIVILEGES, issued
-- by a superuser FOR ROLE or by the migration role itself, can express "PUBLIC
-- gets nothing" for functions or types on PostgreSQL 16.
--
-- The revocation is therefore made against the objects themselves, in the
-- grants phase below, which runs after every migration. Tables and sequences
-- need no default-privilege entry: their built-in default already grants the
-- runtime and outbox roles nothing, and the grants phase revokes every relation
-- from both roles before granting the approved set.
--
-- EXPOSURE WINDOW, stated plainly: because the revocation is applied to objects
-- rather than to defaults, a function or type is executable/usable by PUBLIC
-- from the moment the migration role creates it until the grants phase next
-- runs. That window is inherent to PostgreSQL 16 and cannot be closed here --
-- closing it would need a DDL event trigger, which this story's accepted
-- exclusions forbid. The mitigation is operational and is documented in
-- README.md and CONTRIBUTING.md: run the grants phase after every migration.
-- This is a known, accepted limitation, not a closed control.
SQL
}

grant_framework_relations() {
    ${psql_base} --dbname="${POSTGRES_DB}" <<'SQL'
\getenv migration_role PF074_MIGRATION_ROLE
\getenv runtime_role PF074_RUNTIME_ROLE
\getenv outbox_role PF074_OUTBOX_ROLE

SELECT format('ALTER %s %I.%I OWNER TO %I',
    CASE c.relkind WHEN 'S' THEN 'SEQUENCE' WHEN 'v' THEN 'VIEW' WHEN 'm' THEN 'MATERIALIZED VIEW' ELSE 'TABLE' END,
    n.nspname, c.relname, :'migration_role')
FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p', 'S', 'v', 'm') \gexec

SELECT format('REVOKE ALL ON TABLE %I.%I FROM %I, %I', n.nspname, c.relname, :'runtime_role', :'outbox_role')
FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p', 'v', 'm') \gexec
SELECT format('REVOKE ALL ON SEQUENCE %I.%I FROM %I, %I', n.nspname, c.relname, :'runtime_role', :'outbox_role')
FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public' AND c.relkind = 'S' \gexec

-- Deny-by-default for functions, procedures, and types. See the bootstrap
-- comment above for why this cannot be expressed as a default privilege.
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM PUBLIC;
REVOKE ALL ON ALL ROUTINES IN SCHEMA public FROM PUBLIC;
SELECT format('REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM %I, %I', :'runtime_role', :'outbox_role') \gexec
SELECT format('REVOKE ALL ON ALL ROUTINES IN SCHEMA public FROM %I, %I', :'runtime_role', :'outbox_role') \gexec
-- Types have no ALL ... IN SCHEMA form, so the catalogue is iterated. Array
-- types and the composite types PostgreSQL creates for relations are excluded:
-- they carry no independent access control list.
SELECT format('REVOKE ALL ON TYPE %I.%I FROM PUBLIC, %I, %I', n.nspname, t.typname, :'runtime_role', :'outbox_role')
FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
WHERE n.nspname = 'public'
  AND t.typtype IN ('c', 'd', 'e', 'r', 'm')
  AND (t.typrelid = 0 OR (SELECT c.relkind FROM pg_class c WHERE c.oid = t.typrelid) = 'c')
  AND NOT EXISTS (SELECT 1 FROM pg_type el WHERE el.oid = t.typelem AND el.typarray = t.oid) \gexec

-- The enumerated, closed list of technical decision 7. `users` and
-- `password_reset_tokens` are deliberately absent: they are stock Laravel
-- scaffolding that no current test or route uses, and a later story needing
-- them declares its own grant in the migration that needs it. Extending this
-- list requires a reviewed contract correction, not an edit in passing.
SELECT format('GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.%I TO %I', table_name, :'runtime_role')
FROM (VALUES ('sessions'), ('cache'), ('cache_locks'), ('jobs'), ('job_batches'), ('failed_jobs')) AS approved(table_name)
WHERE to_regclass(format('public.%I', table_name)) IS NOT NULL \gexec
-- Sequence privileges are derived from catalogue ownership, not from schema
-- membership: only a sequence owned by a column of an approved table above is
-- granted. The previous `relkind = 'S'` wildcard also handed the runtime role
-- public.migrations_id_seq, and would have auto-granted every future business
-- sequence.
SELECT format('GRANT USAGE, SELECT ON SEQUENCE %I.%I TO %I', n.nspname, s.relname, :'runtime_role')
FROM (VALUES ('sessions'), ('cache'), ('cache_locks'), ('jobs'), ('job_batches'), ('failed_jobs')) AS approved(table_name)
JOIN pg_class t ON t.oid = to_regclass(format('public.%I', approved.table_name))
JOIN pg_depend d ON d.refobjid = t.oid
    AND d.refclassid = 'pg_class'::regclass
    AND d.classid = 'pg_class'::regclass
    AND d.deptype IN ('a', 'i')
JOIN pg_class s ON s.oid = d.objid AND s.relkind = 'S'
JOIN pg_namespace n ON n.oid = s.relnamespace \gexec
SQL
}

case "${1:-bootstrap}" in
    bootstrap) bootstrap ;;
    grants) grant_framework_relations ;;
    *) printf 'Usage: %s [bootstrap|grants]\n' "$0" >&2; exit 64 ;;
esac
