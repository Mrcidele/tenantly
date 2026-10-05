<?php

declare(strict_types=1);

namespace App\Domains;

interface DnsResolver
{
    /** @return list<string> */
    public function txt(string $host): array;

    /** @return list<string> alvos CNAME, sem ponto final */
    public function cname(string $host): array;
}
