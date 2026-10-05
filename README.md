# Tenantly

SaaS multi-tenant em Laravel cujo princípio é: **o isolamento entre tenants é imposto pela
infraestrutura** (scopes globais, Row Level Security no Postgres e testes automáticos), de modo
que esquecer um `where` não vaza dados — falha de forma fechada.

**Stack:** PHP 8.4 · Laravel 13 · PostgreSQL 17 · Redis 7 · Octane (FrankenPHP) · Horizon · Pulse ·
Pennant · Sanctum · Fortify · Inertia + Vue 3 + TypeScript · Pest · Larastan (nível máximo) · Pint

## Como o isolamento funciona

| Camada | O que faz | Onde |
|---|---|---|
| Identificação | subdomínio, domínio customizado verificado ou header `X-Tenant` → `TenantContext` | `app/Tenancy/Resolution`, `IdentifyTenant` |
| Eloquent | `BelongsToTenant`: global scope, `tenant_id` no `creating`, imutável; sem tenant → exceção | `app/Tenancy/Concerns` |
| Postgres | `ENABLE`+`FORCE RLS`, política `tenant_id = current_tenant_id()`; app conecta sem `BYPASSRLS` | `TenantSchema`, `docker/postgres` |
| Sessão do banco | `app.tenant_id`/`app.user_id` sincronizados antes de cada query, por PDO | `DatabaseSessionSynchronizer` |
| Cross-tenant | só `withoutTenancy($motivo, fn)`: conexão com `BYPASSRLS` + auditoria | `TenantContext` |
| Contexto em tudo | filas (payload), cache (prefixo), storage (pasta), URLs, logs, Sentry, broadcast | `app/Tenancy/Bootstrappers`, `Queue` |
| Testes | varredura de models e rotas, catálogo do RLS, arquitetura, dois tenants em fila/cache/storage | `tests/Isolation`, `tests/Arch` |

Decisão e trade-offs em [`docs/adr/0001-estrategia-de-tenancy.md`](docs/adr/0001-estrategia-de-tenancy.md);
operação, Octane, observabilidade e migrations sem downtime em [`docs/operations.md`](docs/operations.md).

## Mapa das etapas

| Etapa | Principais pontos |
|---|---|
| 0 Setup | Docker Compose, papéis do Postgres, Pest/Larastan/Pint, CI |
| 1 Estratégia | ADR; `tenant_id` + RLS atrás de `TenantDatabaseStrategy` (banco dedicado opcional) |
| 2 Identificação | resolvers, cache Redis da resolução, reset por request (Octane) |
| 3 Isolamento | `BelongsToTenant`, RLS, papéis, `withoutTenancy()` auditado |
| 4 Contexto | jobs, cache, storage com URL assinada, e-mails, canais `tenant.{id}` |
| 5 Auth | memberships/papéis/permissões (enums), convites assinados, Sanctum por tenant, 2FA, troca de organização, impersonação auditada |
| 6 Onboarding | cadastro, validação de subdomínio, provisionamento assíncrono idempotente |
| 7 Cobrança | planos, `BillingGateway` (Fake/Asaas/Stripe), webhooks idempotentes, máquina de estados, proração |
| 8 Limites | `Entitlements`, contadores Redis, modo somente leitura, Pennant |
| 9 Painel central | guard `admin`, MRR/churn/uso por plano, suspender/impersonar/ajustar limites |
| 10 Frontend | Inertia + Vue 3 + TS, Pinia, TanStack Query, branding por tenant |
| 11 Domínios | CNAME + TXT, verificação agendada, TLS on-demand (Caddy) |
| 12 Testes de isolamento | ver acima |
| 13 Segurança | rate limit por tenant/IP, anti-enumeração, auditoria, LGPD (exportar/excluir), backup e restauração de um tenant |
| 14 Produção | logs JSON com `tenant_id`, Sentry, Horizon, Pulse (uso por tenant), fila dedicada, deploy |

## Rodando com Docker

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed      # roda como tenantly_migrator
docker compose exec app php artisan admin:create ops@tenantly.localhost "Ops"
```

- Cadastro: `http://tenantly.localhost:8000/signup`
- Tenant: `http://<subdominio>.tenantly.localhost:8000`
- Painel central, Horizon e Pulse: `http://admin.tenantly.localhost:8000`

## Qualidade

```bash
composer lint        # Pint
composer analyse     # Larastan (level max)
composer test        # Pest (precisa de Postgres e Redis; ver docker/postgres/setup.sh)
npm run typecheck    # vue-tsc
composer ci          # lint + análise + testes
```

Para criar os papéis e bancos de teste fora do Docker:

```bash
PGHOST=127.0.0.1 PGUSER=postgres PGPASSWORD=postgres \
TENANTLY_DATABASES="tenantly tenantly_test tenantly_dedicated_test tenantly_restore_test" \
sh docker/postgres/setup.sh
```
