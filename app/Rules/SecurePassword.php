<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SecurePassword implements ValidationRule
{
    /**
     * At least 8 characters, one digit and one special (non-alphanumeric, non-space) character.
     */
    public const PATTERN = '/^(?=.*\d)(?=.*[^A-Za-z0-9\s]).{8,}$/';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail(__('La contraseña debe tener al menos 8 caracteres, un número y un carácter especial.'));
        }
    }
}
