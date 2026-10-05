<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Formato DNS (RFC 1123), lista de reservados e unicidade. */
final class ValidSubdomain implements ValidationRule
{
    public const string PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1 || str_contains($value, '--')) {
            $fail('Use de 3 a 63 caracteres: letras minúsculas, números e hífens (sem hífen no início/fim).');

            return;
        }

        /** @var list<string> $reserved */
        $reserved = config('tenancy.reserved_subdomains', []);

        if (in_array($value, $reserved, true)) {
            $fail('Este endereço é reservado.');

            return;
        }

        if (Tenant::query()->where('slug', $value)->exists()) {
            $fail('Este endereço já está em uso.');
        }
    }
}
