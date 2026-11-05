<?php

namespace App\Services\Phone;

use App\Exceptions\InvalidPhoneNumberException;

/**
 * Turns a phone number typed by a customer into its international form
 * (country code + subscriber number, digits only), e.g. "333 123 4567"
 * becomes "393331234567".
 *
 * Rules:
 *  - "+" or "00" mark an explicit international prefix and are kept as is;
 *  - numbers already starting with the default country code and longer than
 *    any national number are not prefixed twice;
 *  - every other number is national and gets the default country code.
 */
class PhoneNumberNormalizer
{
    /** Separators customers commonly type: spaces, dots, dashes, slashes, brackets. */
    private const SEPARATORS_PATTERN = '/[\s.\-\/()]+/u';

    /** E.164 allows at most 15 digits; shorter than 8 cannot be a reachable number. */
    private const MIN_INTERNATIONAL_DIGITS = 8;

    private const MAX_INTERNATIONAL_DIGITS = 15;

    /**
     * A national number that starts like the country code ("39...") can only be
     * an Italian mobile, which is at most 10 digits: anything longer already
     * carries the country code.
     */
    private const MAX_NATIONAL_DIGITS = 10;

    public function __construct(
        private readonly string $defaultCountryCode,
    ) {}

    /**
     * @throws InvalidPhoneNumberException
     */
    public function toInternationalDigits(string $phone): string
    {
        $compact = preg_replace(self::SEPARATORS_PATTERN, '', trim($phone)) ?? '';

        $digits = match (true) {
            str_starts_with($compact, '+') => substr($compact, 1),
            str_starts_with($compact, '00') => substr($compact, 2),
            default => $this->withDefaultCountryCode($compact),
        };

        if (! $this->isPlausibleInternationalNumber($digits)) {
            throw InvalidPhoneNumberException::for($phone);
        }

        return $digits;
    }

    /**
     * Human friendly storage format, e.g. "+393331234567".
     *
     * @throws InvalidPhoneNumberException
     */
    public function toE164(string $phone): string
    {
        return '+'.$this->toInternationalDigits($phone);
    }

    public function isValid(string $phone): bool
    {
        try {
            $this->toInternationalDigits($phone);

            return true;
        } catch (InvalidPhoneNumberException) {
            return false;
        }
    }

    private function withDefaultCountryCode(string $nationalNumber): string
    {
        $alreadyHasCountryCode = str_starts_with($nationalNumber, $this->defaultCountryCode)
            && strlen($nationalNumber) > self::MAX_NATIONAL_DIGITS;

        return $alreadyHasCountryCode
            ? $nationalNumber
            : $this->defaultCountryCode.$nationalNumber;
    }

    private function isPlausibleInternationalNumber(string $digits): bool
    {
        return ctype_digit($digits)
            && ! str_starts_with($digits, '0')
            && strlen($digits) >= self::MIN_INTERNATIONAL_DIGITS
            && strlen($digits) <= self::MAX_INTERNATIONAL_DIGITS;
    }
}
