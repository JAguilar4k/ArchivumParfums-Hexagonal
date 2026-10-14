-- Run once per server as a superuser:
--   psql -U postgres -v owner_password='...' -v app_password='...' -f database/roles.sql

SET password_encryption = 'scram-sha-256';

CREATE ROLE archivum_owner LOGIN PASSWORD :'owner_password';
CREATE ROLE archivum_app LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS PASSWORD :'app_password';

CREATE DATABASE archivum_hexagonal OWNER archivum_owner;
CREATE DATABASE archivum_hexagonal_testing OWNER archivum_owner;

REVOKE ALL ON DATABASE archivum_hexagonal, archivum_hexagonal_testing FROM PUBLIC;
GRANT CONNECT ON DATABASE archivum_hexagonal, archivum_hexagonal_testing TO archivum_app;
