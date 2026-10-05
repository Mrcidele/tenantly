# ADR 0001 — Estratégia de tenancy

- **Status:** aceito
- **Data:** 2026-10-05

## Contexto

O Tenantly hospeda dados de várias organizações (tenants) na mesma aplicação. O requisito
não negociável é: **o isolamento entre tenants não pode depender da disciplina do desenvolvedor**.
Esquecer um `where tenant_id = ?` não pode vazar dados; precisa falhar de forma fechada.

Opções avaliadas:

| Estratégia | Isolamento | Custo operacional | Migrations | Consultas cross-tenant | Risco principal |
|---|---|---|---|---|---|
| **Coluna `tenant_id`** (banco único) | Lógico | Baixo (1 banco, 1 pool) | Uma vez | Triviais | Vazamento por `where` esquecido |
| **Schema por tenant** (Postgres) | Bom (search_path) | Médio | N schemas, lentas com muitos tenants | Difíceis (`UNION` entre schemas) | `search_path` errado num pool compartilhado; catálogo cresce |
| **Banco por tenant** | Máximo | Alto (N bancos, N pools, backups) | N bancos | Muito difíceis | Custo/complexidade de operação |

## Decisão

1. **Coluna `tenant_id` + Row Level Security (RLS) do PostgreSQL** como estratégia padrão.
2. Três camadas de defesa, todas automáticas:
   - **Aplicação:** trait `BelongsToTenant` aplica um global scope e preenche `tenant_id` no
     `creating`. Sem tenant no contexto, a query **lança exceção** (não devolve "tudo").
   - **Banco:** toda tabela com `tenant_id` tem `ENABLE` + `FORCE ROW LEVEL SECURITY` e a política
     `tenant_id = current_tenant_id()`, onde `current_tenant_id()` lê `current_setting('app.tenant_id')`.
     Sem tenant definido o resultado é `NULL` → nenhuma linha. A aplicação conecta com um papel
     **sem `BYPASSRLS`**; migrations usam outro papel (dono do schema).
   - **Testes:** a suíte verifica pelo catálogo do Postgres que *toda* tabela com `tenant_id` tem RLS
     forçado e política; percorre todos os models e rotas tentando ler/editar/apagar dados de outro
     tenant; e uma regra de arquitetura impede `DB::` fora da camada de infraestrutura.
3. O valor de `app.tenant_id` é sincronizado **antes de cada query** (hook `beforeExecuting` da
   conexão, comparando o estado aplicado por PDO). Isso cobre conexões lazy, reconexões, rollback de
   transações e workers de longa duração (Octane, filas) sem depender de eventos de ciclo de vida.
4. Acesso cross-tenant (painel interno, webhooks) só via `TenantContext::withoutTenancy($motivo, fn)`,
   que troca para uma conexão com papel `BYPASSRLS` **e grava auditoria**. Não existe outro caminho.
5. A estratégia fica atrás da interface `App\Tenancy\Contracts\TenantDatabaseStrategy`.
   `SharedDatabaseStrategy` (RLS) é a padrão; `DedicatedDatabaseStrategy` permite mover os
   **dados de domínio** de um cliente enterprise para um banco próprio (mantendo o RLS lá também),
   enquanto identidade, assinaturas e auditoria continuam no banco central.

## Consequências

**Positivas**
- Um banco, um pool de conexões, migrations únicas, relatórios cross-tenant simples (auditados).
- Um bug no Eloquent (scope removido, `DB::table`, SQL cru) ainda é barrado pelo Postgres.
- Possibilidade de banco dedicado sem reescrever a aplicação.

**Negativas / cuidados**
- Toda tabela de tenant precisa da coluna e da política — mitigado pelo helper de migration
  `TenantSchema::enableRowLevelSecurity()` e pelo teste de catálogo que falha se faltar.
- Índices precisam começar por `tenant_id`; unicidades devem ser compostas (`tenant_id`, …) para
  não revelar existência de dados de outro tenant via erro de unicidade.
- Configurações de sessão do Postgres (`set_config`) não sobrevivem a poolers em modo *transaction*
  (PgBouncer). Usar modo *session* ou conexão direta.
- `withoutTenancy()` é poderoso: só é usado em pontos revisados e sempre auditado.
