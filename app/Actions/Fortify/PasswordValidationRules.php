<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Brief §5: Argon2id (config/hashing.php), min 12 characters, breached-password check.
     *
     * @return array<int, Rule|array|string>
     */
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            Password::min(12)->uncompromised(),
            'confirmed',
        ];
    }
}
