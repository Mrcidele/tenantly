<?php

declare(strict_types=1);

use App\Tenancy\Bootstrappers\CacheBootstrapper;
use App\Tenancy\Bootstrappers\FilesystemBootstrapper;
use App\Tenancy\Bootstrappers\ObservabilityBootstrapper;
use App\Tenancy\Bootstrappers\UrlBootstrapper;

return [

    /*
     * Domínio central (landing, cadastro, webhooks). Tenants vivem em
     * subdomínios dele (acme.tenantly.com) ou em domínios customizados.
     */
    'central_domain' => env('TENANCY_CENTRAL_DOMAIN', 'tenantly.localhost'),

    'admin_domain' => env('TENANCY_ADMIN_DOMAIN', 'admin.tenantly.localhost'),

    // Alvo do CNAME exibido para domínios customizados.
    'cname_target' => env('TENANCY_CNAME_TARGET', 'cname.tenantly.localhost'),

    // Header usado pela API para identificar o tenant.
    'header' => 'X-Tenant',

    'connections' => [
        // Conexão do banco central (papel sem BYPASSRLS).
        'central' => 'pgsql',
        // Conexão com BYPASSRLS, usada apenas dentro de withoutTenancy().
        'bypass' => 'pgsql_admin',
        // Conexões que não recebem app.tenant_id (não estão sujeitas ao RLS).
        'unsynchronized' => ['pgsql_admin', 'migrator'],
    ],

    'resolution_cache' => [
        'store' => env('TENANCY_RESOLUTION_CACHE_STORE', 'central'),
        'ttl' => (int) env('TENANCY_RESOLUTION_CACHE_TTL', 300),
        // Hosts desconhecidos ficam em cache por pouco tempo (anti-enumeração/DoS).
        'negative_ttl' => 30,
    ],

    /*
     * Executados (nesta ordem) quando um tenant é ativado e revertidos (na
     * ordem inversa) quando o contexto é limpo.
     */
    'bootstrappers' => [
        CacheBootstrapper::class,
        FilesystemBootstrapper::class,
        UrlBootstrapper::class,
        ObservabilityBootstrapper::class,
    ],

    'cache' => [
        // Stores cujo prefixo passa a ser "tenant:{id}:" com um tenant ativo.
        'stores' => ['redis'],
    ],

    'filesystem' => [
        'disk' => 'tenant',
        'base_disk' => env('TENANCY_FILESYSTEM_BASE_DISK', 'local'),
        'signed_url_ttl' => 15, // minutos
    ],

    'rate_limits' => [
        // Requests por minuto, por tenant (todas as origens) e por tenant + IP.
        'web_per_tenant' => (int) env('TENANCY_WEB_RATE_PER_TENANT', 1200),
        'web_per_ip' => (int) env('TENANCY_WEB_RATE_PER_IP', 300),
        'api_per_tenant' => (int) env('TENANCY_API_RATE_PER_TENANT', 1000),
        'api_per_token' => (int) env('TENANCY_API_RATE_PER_TOKEN', 120),
    ],

    // Hosts inexistentes por IP/minuto antes de 429 (anti-enumeração).
    'unknown_host_limit_per_minute' => 20,

    'reserved_subdomains' => [
        'admin', 'api', 'app', 'assets', 'auth', 'billing', 'blog', 'cdn', 'central',
        'dashboard', 'dev', 'docs', 'email', 'ftp', 'help', 'imap', 'mail', 'mx',
        'ns1', 'ns2', 'pop', 'portal', 'root', 'smtp', 'staging', 'static', 'status',
        'support', 'system', 'tenant', 'tenantly', 'test', 'webmail', 'www', 'cname',
    ],

];
