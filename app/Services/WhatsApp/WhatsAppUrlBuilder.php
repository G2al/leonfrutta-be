<?php

namespace App\Services\WhatsApp;

use App\Exceptions\InvalidPhoneNumberException;
use App\Services\Phone\PhoneNumberNormalizer;

/**
 * Builds official WhatsApp click-to-chat links (https://wa.me).
 *
 * Nothing is sent: the link only opens a chat with a prefilled message
 * that the administrator reviews and sends manually.
 */
class WhatsAppUrlBuilder
{
    private const BASE_URL = 'https://wa.me/';

    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {}

    /**
     * @throws InvalidPhoneNumberException
     */
    public function build(string $phone, ?string $message = null): string
    {
        $url = self::BASE_URL.$this->phoneNumberNormalizer->toInternationalDigits($phone);

        if (blank($message)) {
            return $url;
        }

        return $url.'?text='.rawurlencode($message);
    }
}
