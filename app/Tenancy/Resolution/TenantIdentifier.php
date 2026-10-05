<?php

declare(strict_types=1);

namespace App\Tenancy\Resolution;

use App\Models\Tenant;
use App\Tenancy\Contracts\TenantResolver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use InvalidArgumentException;

final readonly class TenantIdentifier
{
    /** @var array<string, class-string<TenantResolver>> */
    private const array RESOLVERS = [
        'subdomain' => SubdomainResolver::class,
        'domain' => CustomDomainResolver::class,
        'header' => HeaderResolver::class,
    ];

    public function __construct(private Container $container) {}

    /**
     * @param  list<string>  $strategies  em ordem de prioridade
     */
    public function identify(Request $request, array $strategies): ?Tenant
    {
        foreach ($strategies as $strategy) {
            $class = self::RESOLVERS[$strategy] ?? throw new InvalidArgumentException("Resolver [{$strategy}] desconhecido.");

            $tenant = $this->container->make($class)->resolve($request);

            if ($tenant !== null) {
                return $tenant;
            }
        }

        return null;
    }
}
