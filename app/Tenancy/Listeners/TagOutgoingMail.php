<?php

declare(strict_types=1);

namespace App\Tenancy\Listeners;

use App\Tenancy\TenantContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * E-mails enviados com tenant ativo (inclusive de jobs) saem com o nome da
 * organização e um header para rastreio/roteamento.
 */
final readonly class TagOutgoingMail
{
    public function __construct(private Container $container) {}

    public function handle(MessageSending $event): void
    {
        $tenant = $this->container->make(TenantContext::class)->current();

        if ($tenant === null) {
            return;
        }

        $message = $event->message;
        $message->getHeaders()->addTextHeader('X-Tenant-Id', $tenant->id);

        $from = $message->getFrom()[0] ?? null;

        if ($from !== null) {
            $appName = config('app.name');
            $message->from(new Address($from->getAddress(), $tenant->name.' via '.(is_string($appName) ? $appName : 'Tenantly')));
        }
    }
}
