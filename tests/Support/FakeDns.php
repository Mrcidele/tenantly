<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\DnsResolver;

final class FakeDns implements DnsResolver
{
    /** @var array<string, list<string>> */
    public array $txt = [];

    /** @var array<string, list<string>> */
    public array $cname = [];

    public function txt(string $host): array
    {
        return $this->txt[$host] ?? [];
    }

    public function cname(string $host): array
    {
        return $this->cname[$host] ?? [];
    }
}
