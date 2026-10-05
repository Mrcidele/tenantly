<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * @return list<mixed>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::min(10)->letters()->numbers(), 'confirmed'];
    }
}
