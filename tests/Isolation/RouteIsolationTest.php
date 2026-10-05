<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Support\TenantModels;

/*
 * Varre TODAS as rotas GET de tenant (rota nova entra automaticamente):
 * 1. acessando como dono do tenant A, nenhuma resposta pode conter IDs,
 *    e-mails ou nomes do tenant B;
 * 2. usando IDs do tenant B nos parâmetros, a resposta deve ser 404/403.
 */

/**
 * Rotas que não podem ser chamadas genericamente. Toda exceção precisa de motivo.
 *
 * @var array<string, string>
 */
const ROUTE_SWEEP_SKIP = [
    'tenant.files.show' => 'Exige URL assinada; isolamento coberto em ContextPropagationTest.',
];

/**
 * @return array<string, Route>
 */
function tenantGetRoutes(): array
{
    $routes = [];

    foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
        $isTenantRoute = collect($route->gatherMiddleware())->contains(
            fn (mixed $middleware): bool => is_string($middleware) && ($middleware === 'tenant' || str_starts_with($middleware, 'tenant:')),
        );

        if (! $isTenantRoute || ! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $name = $route->getName() ?? $route->uri();

        if (! array_key_exists($name, ROUTE_SWEEP_SKIP)) {
            $routes[$name] = $route;
        }
    }

    return $routes;
}

/**
 * @return array{owner: User, records: array<class-string<Model>, list<Model>>}
 */
function seedTenant(Tenant $tenant): array
{
    return inTenant($tenant, function (): array {
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->role(MembershipRole::Owner)->create();

        $records = [];
        foreach (TenantModels::tenantScoped() as $class) {
            $records[$class] = $class::factory()->count(2)->create()->all();
        }

        return ['owner' => $owner, 'records' => $records];
    });
}

/**
 * @param  array<class-string<Model>, list<Model>>  $records
 * @return array<string, string>|null
 */
function routeParameters(Route $route, array $records): ?array
{
    $parameters = [];

    foreach ($route->signatureParameters(['subClass' => Model::class]) as $parameter) {
        $class = $parameter->getType()?->getName();

        if (! is_string($class) || ! in_array(BelongsToTenant::class, class_uses_recursive($class), true) || ! isset($records[$class][0])) {
            return null;
        }

        $parameters[$parameter->getName()] = (string) $records[$class][0]->getRouteKey();
    }

    return count($parameters) === count($route->parameterNames()) ? $parameters : null;
}

/**
 * @param  array{owner: User, records: array<class-string<Model>, list<Model>>}  $seed
 * @return list<string>
 */
function sensitiveValuesOf(Tenant $tenant, array $seed): array
{
    $values = [$tenant->id, $tenant->name, $seed['owner']->id, $seed['owner']->email];

    foreach ($seed['records'] as $records) {
        foreach ($records as $record) {
            $values[] = (string) $record->getKey();
        }
    }

    return $values;
}

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->seedA = seedTenant($this->a);
    $this->seedB = seedTenant($this->b);
});

it('encontra rotas de tenant para varrer', function (): void {
    expect(tenantGetRoutes())->not->toBeEmpty();
});

it('não vaza dados do outro tenant em nenhuma rota GET', function (): void {
    $forbidden = sensitiveValuesOf($this->b, $this->seedB);
    $checked = 0;

    foreach (tenantGetRoutes() as $name => $route) {
        $parameters = routeParameters($route, $this->seedA['records']);

        if ($parameters === null) {
            $this->fail("Rota [{$name}] tem parâmetros que a varredura não sabe preencher; adicione-a a ROUTE_SWEEP_SKIP com o motivo.");
        }

        $uri = 'http://'.$this->a->subdomainHost().'/'.ltrim(strtr($route->uri(), collect($parameters)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => $v])->all()), '/');
        $guard = in_array('auth:sanctum', $route->gatherMiddleware(), true) ? 'sanctum' : 'web';

        $response = $this->actingAs($this->seedA['owner'], $guard)->get($uri);

        expect($response->getStatusCode())->toBeLessThan(500, "Rota [{$name}] falhou: ".$response->getContent());

        foreach ($forbidden as $value) {
            expect(str_contains((string) $response->getContent(), $value))
                ->toBeFalse("Rota [{$name}] expôs [{$value}] do outro tenant.");
        }

        $checked++;
    }

    expect($checked)->toBeGreaterThan(0);
});

it('responde 404/403 quando a rota recebe IDs do outro tenant', function (): void {
    $this->addToAssertionCount(1);

    foreach (tenantGetRoutes() as $name => $route) {
        if ($route->parameterNames() === []) {
            continue;
        }

        $parameters = routeParameters($route, $this->seedB['records']);

        if ($parameters === null) {
            continue;
        }

        $uri = 'http://'.$this->a->subdomainHost().'/'.ltrim(strtr($route->uri(), collect($parameters)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => $v])->all()), '/');
        $guard = in_array('auth:sanctum', $route->gatherMiddleware(), true) ? 'sanctum' : 'web';

        $status = $this->actingAs($this->seedA['owner'], $guard)->get($uri)->getStatusCode();

        expect($status)->toBeIn([403, 404], "Rota [{$name}] respondeu {$status} para ID de outro tenant.");
    }
});
