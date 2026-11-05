<?php

namespace App\Services\WhatsApp;

use App\Models\Order;
use Illuminate\Support\Number;

/**
 * Texts of the automatic WhatsApp messages sent to the customer at every
 * step of the order: received, confirmed, paid, cancelled.
 */
class OrderWhatsAppMessage
{
    public function __construct(
        private readonly string $companyName,
    ) {}

    public function received(Order $order): string
    {
        return "Ciao {$order->first_name}, abbiamo ricevuto la tua richiesta d'ordine n. {$order->getKey()}. "
            ."Ti risponderemo a breve. {$this->companyName}";
    }

    public function confirmed(Order $order): string
    {
        return "Ciao {$order->first_name}, il tuo ordine n. {$order->getKey()} è confermato. "
            ."Totale: {$this->euro($order)}. Grazie! {$this->companyName}";
    }

    public function paid(Order $order): string
    {
        return "Ciao {$order->first_name}, abbiamo registrato il pagamento di {$this->euro($order)} "
            ."per l'ordine n. {$order->getKey()}. Grazie! {$this->companyName}";
    }

    public function cancelled(Order $order): string
    {
        return "Ciao {$order->first_name}, la tua richiesta d'ordine n. {$order->getKey()} è stata annullata. "
            ."Per informazioni puoi rispondere a questo messaggio. {$this->companyName}";
    }

    private function euro(Order $order): string
    {
        return Number::currency((float) $order->amount, in: 'EUR', locale: 'it');
    }
}
