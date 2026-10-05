<?php

declare(strict_types=1);

namespace App\Events;

use App\Tenancy\TenantContext;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base para eventos broadcast: o canal é sempre "tenant.{id}" do tenant
 * ativo no momento em que o evento foi criado.
 */
abstract class TenantBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $tenantId;

    public function __construct()
    {
        $this->tenantId = app(TenantContext::class)->get()->id;
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->tenantId)];
    }
}
