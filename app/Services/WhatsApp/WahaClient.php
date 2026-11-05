<?php

namespace App\Services\WhatsApp;

use App\Exceptions\InvalidPhoneNumberException;
use App\Services\Phone\PhoneNumberNormalizer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Sends WhatsApp text messages through WAHA, the self-hosted WhatsApp HTTP API
 * (POST {WAHA_URL}/api/sendText).
 */
class WahaClient
{
    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
        private readonly ?string $baseUrl,
        private readonly ?string $apiKey,
        private readonly ?string $session,
        private readonly int $timeoutSeconds,
    ) {}

    /**
     * Sending is switched off until WAHA_URL and WAHA_SESSION are set.
     */
    public function isConfigured(): bool
    {
        return filled($this->baseUrl) && filled($this->session);
    }

    /**
     * @throws InvalidPhoneNumberException when the number cannot be turned into a WhatsApp chat
     * @throws RequestException when WAHA answers with an error
     */
    public function sendText(string $phone, string $text): void
    {
        Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withHeaders(array_filter(['X-Api-Key' => $this->apiKey]))
            ->acceptJson()
            ->timeout($this->timeoutSeconds)
            ->post('/api/sendText', [
                'session' => $this->session,
                'chatId' => $this->chatIdFor($phone),
                'text' => $text,
            ])
            ->throw();
    }

    /**
     * WAHA addresses a private chat as "<international number>@c.us".
     */
    private function chatIdFor(string $phone): string
    {
        return $this->phoneNumberNormalizer->toInternationalDigits($phone).'@c.us';
    }
}
