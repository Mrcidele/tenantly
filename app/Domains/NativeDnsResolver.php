<?php

declare(strict_types=1);

namespace App\Domains;

final class NativeDnsResolver implements DnsResolver
{
    public function txt(string $host): array
    {
        return $this->records($host, DNS_TXT, 'txt');
    }

    public function cname(string $host): array
    {
        return array_map(static fn (string $target): string => rtrim(strtolower($target), '.'), $this->records($host, DNS_CNAME, 'target'));
    }

    /**
     * @return list<string>
     */
    private function records(string $host, int $type, string $field): array
    {
        $records = @dns_get_record($host, $type);

        if (! is_array($records)) {
            return [];
        }

        $values = [];

        foreach ($records as $record) {
            if (is_string($record[$field] ?? null)) {
                $values[] = $record[$field];
            }
        }

        return $values;
    }
}
