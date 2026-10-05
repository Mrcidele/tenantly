# Operação em produção

## Topologia

- **app**: imagem `prod` (FrankenPHP + Octane). Sem estado local; sessões, cache e filas no Redis.
- **horizon**: mesmos binários, `php artisan horizon`. Supervisores `supervisor-default`
  (default, billing, notifications) e `supervisor-tenants-large` (fila dedicada).
- **scheduler**: `php artisan schedule:work` (prazos de cobrança, contadores de uso,
  verificação de domínios, purga LGPD).
- **caddy**: TLS automático (on-demand) para subdomínios e domínios customizados, perguntando ao
  app via `/internal/tls/ask` (acessível só pela rede privada).
- **Postgres** com três papéis (`docker/postgres/roles.sql`): a aplicação usa `tenantly_app`
  (sem `BYPASSRLS`); migrations usam `tenantly_migrator`; `tenantly_admin` só é usado por
  `withoutTenancy()`, sempre auditado. Use conexão direta ou PgBouncer em modo *session*
  (o modo *transaction* descarta `set_config` entre transações).

## Octane e estado entre requests

Tudo que guarda tenant é revertido a cada request, por três mecanismos independentes:

1. `TenantContext` é *scoped* (descartado pelo Octane/fila ao fim da request/job);
2. o middleware global `ResetTenancy` e o listener `ResetTenancyState` (RequestReceived e
   RequestTerminated) revertem prefixo de cache, disco, URL raiz e contexto de log;
3. o `DatabaseSessionSynchronizer` compara, antes de **cada** query, o `app.tenant_id`
   aplicado em cada conexão PDO com o contexto atual — mesmo uma conexão persistente nunca
   executa com o tenant da request anterior.

## Observabilidade

- Logs JSON em stderr (`LOG_STACK=json`). `tenant_id` e `tenant_slug` entram em todo registro
  via Laravel Context, inclusive em jobs (o Context é propagado no payload).
- Sentry: exceções com as tags `tenant_id`/`tenant_slug` (definidas ao ativar o tenant).
- Horizon (`admin.<domínio>/horizon`) e Pulse (`admin.<domínio>/pulse`), só para o guard `admin`.
  O card **Uso por tenant** mostra requests (quantidade, média e máximo de duração) e jobs por
  tenant para identificar vizinhos barulhentos.
- Vizinho barulhento confirmado: no painel, mova o tenant para a fila `tenants-large`
  (jobs pesados passam a rodar em workers próprios). Para isolamento total, migre-o para a
  estratégia de banco dedicado (ADR 0001).
- Health check `/up`: falha se Postgres ou Redis não responderem.

## Migrations sem downtime (expand/contract)

Durante o rolling update, a versão antiga e a nova rodam ao mesmo tempo contra o mesmo schema.
Toda mudança incompatível é dividida em deploys:

| Mudança | Deploy N (expand) | Deploy N+1 | Deploy N+2 (contract) |
|---|---|---|---|
| Renomear coluna | adicionar a nova; código grava nas duas | backfill em lotes; código lê a nova | remover a antiga |
| Tornar coluna NOT NULL | adicionar `CHECK (...) NOT VALID`; código sempre preenche | backfill + `VALIDATE CONSTRAINT` | `SET NOT NULL` (usa a constraint validada) |
| Remover coluna/tabela | código para de usar | — | `DROP` |
| Índice em tabela grande | `CREATE INDEX CONCURRENTLY` (migration com `$withinTransaction = false`) | — | — |
| Nova tabela de tenant | tabela + `TenantSchema::enableRowLevelSecurity()` no mesmo deploy | — | — |

Regras adicionais:

- `lock_timeout` curto nas migrations (ex.: `SET lock_timeout = '3s'`) para falhar rápido em vez
  de enfileirar tráfego atrás de um lock.
- Backfills em lotes, por tenant, em jobs (nunca um `UPDATE` gigante dentro da migration).
- Tabelas novas com `tenant_id` **sem RLS** quebram o CI (`RowLevelSecurityCatalogTest`); tabelas
  novas sem `tenant_id` precisam ser classificadas como centrais no mesmo teste.

## Backups e restauração

- `docker/backup/backup.sh`: `pg_dump` (formato custom) validado com `pg_restore --list`.
  Agende diariamente, envie para armazenamento externo com retenção e criptografia.
- Teste de restauração **mensal** e documentado: `docker/backup/restore.sh <dump> tenantly_restore`
  num banco separado.
- Restauração de um único tenant: configure a conexão `restore_source` para o banco restaurado
  e rode `php artisan tenants:restore <tenant-id>`. A escrita acontece sob RLS com o contexto do
  tenant, então nenhum outro tenant pode ser alterado.
