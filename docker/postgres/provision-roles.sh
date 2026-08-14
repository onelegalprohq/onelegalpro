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

psql_base="psql --set=ON_ERROR_STOP=1 --username=${POSTGRES_USER}"

bootstrap() {
    ${psql_base} --dbname=postgres \
        --set=database_name="${POSTGRES_DB}" \
        --set=migration_role="${PF074_MIGRATION_ROLE}" \
        --set=migration_password="${PF074_MIGRATION_PASSWORD}" \
        --set=runtime_role="${PF074_RUNTIME_ROLE}" \
        --set=runtime_password="${PF074_RUNTIME_PASSWORD}" \
        --set=outbox_role="${PF074_OUTBOX_ROLE}" \
        --set=outbox_password="${PF074_OUTBOX_PASSWORD}" <<'SQL'
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
SELECT format('GRANT CONNECT, TEMPORARY ON DATABASE %I TO %I', :'database_name', :'runtime_role') \gexec
SELECT format('GRANT CONNECT ON DATABASE %I TO %I', :'database_name', :'outbox_role') \gexec
SQL

    ${psql_base} --dbname="${POSTGRES_DB}" \
        --set=migration_role="${PF074_MIGRATION_ROLE}" \
        --set=runtime_role="${PF074_RUNTIME_ROLE}" \
        --set=outbox_role="${PF074_OUTBOX_ROLE}" <<'SQL'
SELECT format('ALTER SCHEMA public OWNER TO %I', :'migration_role') \gexec
REVOKE ALL ON SCHEMA public FROM PUBLIC;
SELECT format('GRANT USAGE ON SCHEMA public TO %I', :'runtime_role') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public REVOKE ALL ON TABLES FROM %I', :'migration_role', :'runtime_role') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public REVOKE ALL ON TABLES FROM %I', :'migration_role', :'outbox_role') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public REVOKE ALL ON SEQUENCES FROM %I', :'migration_role', :'runtime_role') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public REVOKE ALL ON SEQUENCES FROM %I', :'migration_role', :'outbox_role') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC', :'migration_role') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public REVOKE USAGE ON TYPES FROM PUBLIC', :'migration_role') \gexec
SQL
}

grant_framework_relations() {
    ${psql_base} --dbname="${POSTGRES_DB}" \
        --set=migration_role="${PF074_MIGRATION_ROLE}" \
        --set=runtime_role="${PF074_RUNTIME_ROLE}" \
        --set=outbox_role="${PF074_OUTBOX_ROLE}" <<'SQL'
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

SELECT format('GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.%I TO %I', table_name, :'runtime_role')
FROM (VALUES ('users'), ('password_reset_tokens'), ('sessions'), ('cache'), ('cache_locks'), ('jobs'), ('job_batches'), ('failed_jobs')) AS approved(table_name)
WHERE to_regclass(format('public.%I', table_name)) IS NOT NULL \gexec
SELECT format('GRANT USAGE, SELECT ON SEQUENCE %I.%I TO %I', n.nspname, c.relname, :'runtime_role')
FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public' AND c.relkind = 'S' \gexec
SQL
}

case "${1:-bootstrap}" in
    bootstrap) bootstrap ;;
    grants) grant_framework_relations ;;
    *) printf 'Usage: %s [bootstrap|grants]\n' "$0" >&2; exit 64 ;;
esac
