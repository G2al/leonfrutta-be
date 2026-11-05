<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\WhatsApp\OrderWhatsAppMessage;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderWhatsAppMessageTest extends TestCase
{
    private OrderWhatsAppMessage $messages;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messages = new OrderWhatsAppMessage(companyName: 'LeonFrutta', timezone: 'Europe/Rome');

        $this->order = new Order([
            'first_name' => 'mario',
            'business_name' => 'Pizzeria Da Mario',
            'message' => "10 kg di pomodori\n5 kg di patate",
        ]);
        $this->order->id = 12;
        $this->order->amount = '1234.5';
        // Stored in UTC: 09:30 UTC is 10:30 in Rome in November.
        $this->order->created_at = Carbon::parse('2026-11-03 09:30:00', 'UTC');
        $this->order->paid_at = Carbon::parse('2026-11-05 14:20:00', 'UTC');
    }

    #[Test]
    public function the_received_message_summarises_the_request(): void
    {
        $this->assertSame(
            "🍊 *LeonFrutta*\n\n"
            ."Ciao *Mario*! 👋\nAbbiamo ricevuto la tua richiesta d'ordine. ✅\n\n"
            ."🧾 *Richiesta n. 12*\n🏪 Pizzeria Da Mario\n📅 03/11/2026 alle 10:30\n\n"
            ."📝 *La tua richiesta:*\n10 kg di pomodori\n5 kg di patate\n\n"
            ."⏳ La stiamo verificando: ti scriveremo a breve con la conferma e il totale.\n\n"
            .'🍊 *LeonFrutta* · _Frutta e verdura_',
            $this->messages->received($this->order),
        );
    }

    #[Test]
    public function the_confirmed_message_highlights_the_total(): void
    {
        $this->assertSame(
            "✅ *Ordine confermato!*\n\n"
            ."Ciao *Mario*, ottime notizie: il tuo ordine è stato confermato. 🎉\n\n"
            ."🧾 *Ordine n. 12*\n🏪 Pizzeria Da Mario\n💶 *Totale: 1.234,50\u{a0}€*\n\n"
            ."💬 Per qualsiasi domanda rispondi pure a questo messaggio.\n\n"
            .'🍊 *LeonFrutta* · _Frutta e verdura_',
            $this->messages->confirmed($this->order),
        );
    }

    #[Test]
    public function the_paid_message_shows_amount_and_payment_time(): void
    {
        $message = $this->messages->paid($this->order);

        $this->assertStringStartsWith("💳 *Pagamento ricevuto*\n\nCiao *Mario*, abbiamo registrato il tuo pagamento. Grazie! 🙏", $message);
        $this->assertStringContainsString("💶 Importo: *1.234,50\u{a0}€*", $message);
        $this->assertStringContainsString('📅 Pagato il 05/11/2026 alle 15:20', $message);
    }

    #[Test]
    public function the_cancelled_message_invites_the_customer_to_reply(): void
    {
        $message = $this->messages->cancelled($this->order);

        $this->assertStringStartsWith('❌ *Richiesta annullata*', $message);
        $this->assertStringContainsString("la tua richiesta d'ordine *n. 12* è stata annullata.", $message);
        $this->assertStringContainsString('📅 Inviata il 03/11/2026 alle 10:30', $message);
        $this->assertStringContainsString('rispondi pure a questo messaggio', $message);
    }

    #[Test]
    public function customer_text_cannot_break_the_whatsapp_formatting(): void
    {
        $this->order->first_name = '*Mario_';
        $this->order->message = '~10~ kg di *pomodori*';

        $message = $this->messages->received($this->order);

        $this->assertStringContainsString('Ciao *Mario*!', $message);
        $this->assertStringContainsString("📝 *La tua richiesta:*\n10 kg di pomodori", $message);
    }

    #[Test]
    public function very_long_requests_are_shortened(): void
    {
        $this->order->message = str_repeat('pomodori ', 200);

        $this->assertStringContainsString('…', $this->messages->received($this->order));
        $this->assertLessThan(900, mb_strlen($this->messages->received($this->order)));
    }
}
