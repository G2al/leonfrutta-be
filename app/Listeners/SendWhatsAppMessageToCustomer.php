<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Events\OrderConfirmed;
use App\Events\OrderPaid;
use App\Events\OrderSubmitted;
use App\Models\Order;
use App\Services\WhatsApp\OrderWhatsAppMessage;
use App\Services\WhatsApp\WahaClient;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Sends the customer an automatic WhatsApp message (through WAHA) when the
 * order is received, confirmed, paid or cancelled.
 *
 * Sending is deferred until the response has been returned, so the customer
 * and the administrator never wait for WhatsApp, no queue worker or scheduler
 * is needed, and a WAHA failure never undoes the order change: it is logged.
 */
class SendWhatsAppMessageToCustomer
{
    public function __construct(
        private readonly WahaClient $waha,
        private readonly OrderWhatsAppMessage $messages,
    ) {}

    public function handle(OrderSubmitted|OrderConfirmed|OrderPaid|OrderCancelled $event): void
    {
        if (! $this->waha->isConfigured()) {
            return;
        }

        $order = $event->order;
        $text = $this->messageFor($event, $order);

        defer(function () use ($order, $text): void {
            try {
                $this->waha->sendText($order->phone, $text);
            } catch (Throwable $exception) {
                Log::error('Invio WhatsApp tramite WAHA non riuscito.', [
                    'order_id' => $order->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    private function messageFor(OrderSubmitted|OrderConfirmed|OrderPaid|OrderCancelled $event, Order $order): string
    {
        return match (true) {
            $event instanceof OrderSubmitted => $this->messages->received($order),
            $event instanceof OrderConfirmed => $this->messages->confirmed($order),
            $event instanceof OrderPaid => $this->messages->paid($order),
            $event instanceof OrderCancelled => $this->messages->cancelled($order),
        };
    }
}
