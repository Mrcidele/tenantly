-- Privilégios por banco. Executado como superusuário conectado ao banco alvo.
\set ON_ERROR_STOP on

REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO tenantly_app, tenantly_admin;
GRANT ALL ON SCHEMA public TO tenantly_migrator;

-- Tudo que o migrator criar fica acessível (apenas DML) para app e admin.
ALTER DEFAULT PRIVILEGES FOR ROLE tenantly_migrator IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO tenantly_app, tenantly_admin;
ALTER DEFAULT PRIVILEGES FOR ROLE tenantly_migrator IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO tenantly_app, tenantly_admin;
ALTER DEFAULT PRIVILEGES FOR ROLE tenantly_migrator IN SCHEMA public
    GRANT EXECUTE ON FUNCTIONS TO tenantly_app, tenantly_admin;
