#!/usr/bin/env bash
# ########## INICIO CAMBIO POSTGRESQL 17
set -euo pipefail
: "${DB_DATABASE:?}" "${DB_USERNAME:?}" "${DB_PASSWORD:?}" "${DB_MIGRATOR_USERNAME:?}" "${DB_MIGRATOR_PASSWORD:?}" "${DB_PROVISIONER_USERNAME:?}" "${DB_PROVISIONER_PASSWORD:?}"
for value in "$DB_DATABASE" "$DB_USERNAME" "$DB_MIGRATOR_USERNAME" "$DB_PROVISIONER_USERNAME"; do
    if [[ ! "$value" =~ ^[a-zA-Z_][a-zA-Z0-9_-]{0,62}$ ]]; then
        echo 'Los nombres PostgreSQL deben tener entre 1 y 63 bytes ASCII válidos.' >&2
        exit 1
    fi
done
if [[ "$DB_USERNAME" == "$DB_MIGRATOR_USERNAME" || "$DB_USERNAME" == "$DB_PROVISIONER_USERNAME" || "$DB_MIGRATOR_USERNAME" == "$DB_PROVISIONER_USERNAME" ]]; then
    echo 'Los roles de operación, propiedad y aprovisionamiento deben ser distintos.' >&2
    exit 1
fi
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres <<'SQL'
\getenv db DB_DATABASE
\getenv runtime DB_USERNAME
\getenv runtime_password DB_PASSWORD
\getenv owner DB_MIGRATOR_USERNAME
\getenv owner_password DB_MIGRATOR_PASSWORD
\getenv provisioner DB_PROVISIONER_USERNAME
\getenv provisioner_password DB_PROVISIONER_PASSWORD
SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD %L', :'owner', :'owner_password') \gexec
SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD %L', :'runtime', :'runtime_password') \gexec
SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER CREATEDB CREATEROLE NOREPLICATION PASSWORD %L', :'provisioner', :'provisioner_password') \gexec
SELECT format('CREATE DATABASE %I OWNER %I TEMPLATE template0 ENCODING %L', :'db', :'owner', 'UTF8') \gexec
SELECT format('REVOKE ALL ON DATABASE %I FROM PUBLIC', :'db') \gexec
SELECT format('GRANT CONNECT ON DATABASE %I TO %I', :'db', :'runtime') \gexec
\connect :db
REVOKE ALL ON SCHEMA public FROM PUBLIC;
SELECT format('GRANT USAGE ON SCHEMA public TO %I', :'runtime') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %I', :'owner', :'runtime') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO %I', :'owner', :'runtime') \gexec
SQL
touch "${PGDATA}/sab-bootstrap-complete"
# ######### FIN CAMBIO POSTGRESQL 17
