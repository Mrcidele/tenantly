<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Gateways\AsaasGateway;
use App\Billing\Gateways\FakeGateway;
use App\Billing\Gateways\StripeGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final readonly class GatewayManager
{
    /** @var array<string, class-string<BillingGateway>> */
    private const array GATEWAYS = [
        'fake' => FakeGateway::class,
        'asaas' => AsaasGateway::class,
        'stripe' => StripeGateway::class,
    ];

    public function __construct(private Container $container) {}

    public function default(): BillingGateway
    {
        $name = config('billing.gateway');

        return $this->get(is_string($name) ? $name : 'fake');
    }

    public function get(string $name): BillingGateway
    {
        $class = self::GATEWAYS[$name] ?? throw new InvalidArgumentException("Gateway [{$name}] desconhecido.");

        return $this->container->make($class);
    }
}
