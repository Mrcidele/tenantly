<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Composer\Autoload\ClassLoader;
use Tests\Support\ResetsDatabase;
use Tests\TestCase;

// O laravel/pint registra o próprio código sob o prefixo PSR-4 "App\", o que
// faria o Pest Arch analisar classes do Pint como se fossem da aplicação.
foreach (ClassLoader::getRegisteredLoaders() as $loader) {
    $loader->setPsr4('App\\', [dirname(__DIR__).'/app']);
}

pest()->extend(TestCase::class)
    ->use(ResetsDatabase::class)
    ->in('Feature', 'Isolation');

pest()->extend(TestCase::class)->in('Unit', 'Arch');

function tenancy(): TenantContext
{
    return app(TenantContext::class);
}

/**
 * @template T
 *
 * @param  callable(Tenant): T  $callback
 * @return T
 */
function inTenant(Tenant $tenant, callable $callback): mixed
{
    return tenancy()->run($tenant, $callback);
}

function tenantUrl(Tenant $tenant, string $path = '/'): string
{
    return 'http://'.$tenant->subdomainHost().'/'.ltrim($path, '/');
}

function centralUrl(string $path = '/'): string
{
    return 'http://'.Tenant::centralDomain().'/'.ltrim($path, '/');
}

function memberOf(Tenant $tenant, App\Enums\MembershipRole $role = App\Enums\MembershipRole::Owner, array $attributes = []): App\Models\User
{
    return inTenant($tenant, function () use ($role, $attributes): App\Models\User {
        $user = App\Models\User::factory()->create($attributes);
        App\Models\Membership::factory()->for($user)->role($role)->create();

        return $user;
    });
}

/**
 * Tenant ativo com assinatura no plano informado (seed dos planos incluso).
 */
function subscribedTenant(string $plan = 'starter', App\Enums\SubscriptionStatus $status = App\Enums\SubscriptionStatus::Active, array $attributes = []): Tenant
{
    if (! App\Models\Plan::query()->exists()) {
        (new Database\Seeders\PlanSeeder)->run();
    }

    $tenant = Tenant::factory()->create($attributes);
    $tenant->forceFill(['billing_gateway' => 'fake', 'billing_customer_id' => 'cus_'.Illuminate\Support\Str::random(10)])->save();

    inTenant($tenant, fn () => App\Models\Subscription::factory()->status($status)->create([
        'plan_id' => App\Models\Plan::query()->where('code', $plan)->value('id'),
        'gateway_subscription_id' => 'sub_'.Illuminate\Support\Str::random(10),
    ]));

    return $tenant;
}

function fakeWebhook(array $payload): Illuminate\Testing\TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return test()->call('POST', centralUrl('webhooks/billing/fake'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FAKE_SIGNATURE' => hash_hmac('sha256', $body, 'fake-secret'),
    ], $body);
}
