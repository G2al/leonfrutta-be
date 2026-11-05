<?php

namespace App\Services\WhatsApp;

use App\Exceptions\InvalidPhoneNumberException;
use App\Models\Order;

/**
 * The wa.me links the administrator uses to talk to the customer of an order.
 *
 * Both methods return null when the stored number cannot be turned into a
 * safe URL, so the admin panel can hide the link instead of breaking.
 */
class OrderWhatsAppLink
{
    public function __construct(
        private readonly WhatsAppUrlBuilder $urlBuilder,
        private readonly OrderWhatsAppMessage $message,
    ) {}

    /**
     * Chat with the prefilled confirmation message.
     */
    public function confirmationUrl(Order $order): ?string
    {
        return $this->buildOrNull($order->phone, $this->message->confirmationFor($order));
    }

    /**
     * Plain chat with the customer, without any prefilled text.
     */
    public function chatUrl(Order $order): ?string
    {
        return $this->buildOrNull($order->phone);
    }

    private function buildOrNull(string $phone, ?string $message = null): ?string
    {
        try {
            return $this->urlBuilder->build($phone, $message);
        } catch (InvalidPhoneNumberException) {
            return null;
        }
    }
}
