# Tenantly

SaaS multi-tenant em Laravel cujo princípio é: **o isolamento entre tenants é imposto pela
infraestrutura** (scopes globais, Row Level Security no Postgres e testes automáticos), de modo
que esquecer um `where` não vaza dados.

## Stack

PHP 8.4 · Laravel 13 · PostgreSQL 17 · Redis 7 · Octane (FrankenPHP) · Pest · Larastan (nível máximo) · Pint

## Rodando com Docker

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

## Qualidade

```bash
composer lint        # Pint
composer analyse     # Larastan (level max)
composer test        # Pest
composer ci          # tudo acima
```
