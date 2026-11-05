<?php

namespace App\Services\WhatsApp;

use App\Exceptions\InvalidPhoneNumberException;
use App\Models\Order;

/**
 * The wa.me link the administrator uses to chat with the customer of an
 * order by hand (automatic messages are sent through WAHA instead).
 */
class OrderWhatsAppLink
{
    public function __construct(
        private readonly WhatsAppUrlBuilder $urlBuilder,
    ) {}

    /**
     * Null when the stored number cannot be turned into a safe URL,
     * so the admin panel can hide the link instead of breaking.
     */
    public function chatUrl(Order $order): ?string
    {
        try {
            return $this->urlBuilder->build($order->phone);
        } catch (InvalidPhoneNumberException) {
            return null;
        }
    }
}
