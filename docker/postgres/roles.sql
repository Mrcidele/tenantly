-- Papéis do Tenantly. Executado como superusuário (uma vez por cluster).
--   tenantly_migrator : dono do schema, roda migrations. BYPASSRLS.
--   tenantly_app      : usado pela aplicação. SEM BYPASSRLS (sujeito ao RLS).
--   tenantly_admin    : consultas cross-tenant explícitas e auditadas. BYPASSRLS, só DML.
\set ON_ERROR_STOP on

SELECT format('CREATE ROLE tenantly_migrator LOGIN BYPASSRLS PASSWORD %L', :'migrator_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'tenantly_migrator') \gexec

SELECT format('CREATE ROLE tenantly_app LOGIN NOBYPASSRLS NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD %L', :'app_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'tenantly_app') \gexec

SELECT format('CREATE ROLE tenantly_admin LOGIN BYPASSRLS NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD %L', :'admin_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'tenantly_admin') \gexec

-- Garante os atributos mesmo se os papéis já existiam.
ALTER ROLE tenantly_app NOBYPASSRLS NOSUPERUSER;
ALTER ROLE tenantly_admin BYPASSRLS NOSUPERUSER;
ALTER ROLE tenantly_migrator BYPASSRLS NOSUPERUSER;
