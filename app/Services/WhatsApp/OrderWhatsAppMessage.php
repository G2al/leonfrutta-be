<?php

namespace App\Services\WhatsApp;

use App\Models\Order;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Composes the prefilled WhatsApp text sent to a customer once their order
 * request is confirmed. The order text is shortened so the wa.me URL stays
 * at a size every browser and WhatsApp client can handle.
 */
class OrderWhatsAppMessage
{
    public function __construct(
        private readonly string $companyName,
        private readonly int $maxSummaryLength,
    ) {}

    public function confirmationFor(Order $order): string
    {
        $summary = Str::limit(trim($order->message), $this->maxSummaryLength, '…');

        $total = $order->amount !== null
            ? ' Totale: '.Number::currency((float) $order->amount, in: 'EUR', locale: 'it').'.'
            : '';

        return "Buongiorno {$order->first_name}, siamo {$this->companyName}. "
            ."Ti confermiamo la tua richiesta di ordine. Riepilogo: {$summary}.{$total} "
            .'Per qualsiasi dettaglio possiamo continuare qui su WhatsApp. Grazie!';
    }
}
