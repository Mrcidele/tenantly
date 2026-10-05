<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Support\Str;

/** Broadcaster que executa a autorização real dos canais, sem serviço externo. */
final class TestingBroadcaster extends Broadcaster
{
    public function auth($request): mixed
    {
        $channel = Str::after((string) $request->input('channel_name'), 'private-');

        return $this->verifyUserCanAccessChannel($request, $channel);
    }

    public function validAuthenticationResponse($request, $result): mixed
    {
        return ['auth' => true];
    }

    public function broadcast(array $channels, $event, array $payload = []): void {}
}
