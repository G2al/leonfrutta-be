<?php

namespace App\Rules;

use App\Services\Phone\PhoneNumberNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts Italian numbers with or without prefix and international numbers
 * written with "+" or "00", using the same normalizer used for WhatsApp links.
 */
class PhoneNumber implements ValidationRule
{
    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->phoneNumberNormalizer->isValid($value)) {
            $fail('Il campo :attribute non contiene un numero di telefono valido.');
        }
    }
}
