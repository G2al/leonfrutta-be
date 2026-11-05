<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\WhatsApp\OrderWhatsAppMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OrderWhatsAppMessageTest extends TestCase
{
    private OrderWhatsAppMessage $messages;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messages = new OrderWhatsAppMessage(companyName: 'LeonFrutta');

        $this->order = new Order(['first_name' => 'Mario']);
        $this->order->id = 12;
        $this->order->amount = '1234.5';
    }

    #[Test]
    public function the_received_message_tells_the_customer_the_request_number(): void
    {
        $this->assertSame(
            "Ciao Mario, abbiamo ricevuto la tua richiesta d'ordine n. 12. Ti risponderemo a breve. LeonFrutta",
            $this->messages->received($this->order),
        );
    }

    #[Test]
    public function the_confirmed_message_contains_the_total_in_euro(): void
    {
        $this->assertSame(
            "Ciao Mario, il tuo ordine n. 12 è confermato. Totale: 1.234,50\u{a0}€. Grazie! LeonFrutta",
            $this->messages->confirmed($this->order),
        );
    }

    #[Test]
    public function the_paid_message_contains_the_amount_received(): void
    {
        $this->assertSame(
            "Ciao Mario, abbiamo registrato il pagamento di 1.234,50\u{a0}€ per l'ordine n. 12. Grazie! LeonFrutta",
            $this->messages->paid($this->order),
        );
    }

    #[Test]
    public function the_cancelled_message_invites_the_customer_to_reply(): void
    {
        $this->assertSame(
            "Ciao Mario, la tua richiesta d'ordine n. 12 è stata annullata. Per informazioni puoi rispondere a questo messaggio. LeonFrutta",
            $this->messages->cancelled($this->order),
        );
    }
}
