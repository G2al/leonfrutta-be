<?php

namespace App\Services\WhatsApp;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Texts of the automatic WhatsApp messages sent to the customer at every
 * step of the order: received, confirmed, paid, cancelled.
 *
 * They use WhatsApp formatting (*bold*, _italic_), emoji and blank lines
 * between sections, and they all end with the same company signature.
 */
class OrderWhatsAppMessage
{
    /** Longest part of the customer's own order text repeated in the "received" message. */
    private const ORDER_TEXT_PREVIEW_LENGTH = 500;

    public function __construct(
        private readonly string $companyName,
        private readonly string $timezone,
    ) {}

    public function received(Order $order): string
    {
        return $this->compose(
            "🍊 *{$this->companyName}*",
            "Ciao *{$this->firstName($order)}*! 👋\nAbbiamo ricevuto la tua richiesta d'ordine. ✅",
            $this->lines(
                "🧾 *Richiesta n. {$order->getKey()}*",
                "🏪 {$this->plain($order->business_name)}",
                "📅 {$this->dateTime($order->created_at)}",
            ),
            "📝 *La tua richiesta:*\n{$this->orderTextPreview($order)}",
            '⏳ La stiamo verificando: ti scriveremo a breve con la conferma e il totale.',
            $this->signature(),
        );
    }

    public function confirmed(Order $order): string
    {
        return $this->compose(
            '✅ *Ordine confermato!*',
            "Ciao *{$this->firstName($order)}*, ottime notizie: il tuo ordine è stato confermato. 🎉",
            $this->lines(
                "🧾 *Ordine n. {$order->getKey()}*",
                "🏪 {$this->plain($order->business_name)}",
                "💶 *Totale: {$this->euro($order)}*",
            ),
            '💬 Per qualsiasi domanda rispondi pure a questo messaggio.',
            $this->signature(),
        );
    }

    public function paid(Order $order): string
    {
        return $this->compose(
            '💳 *Pagamento ricevuto*',
            "Ciao *{$this->firstName($order)}*, abbiamo registrato il tuo pagamento. Grazie! 🙏",
            $this->lines(
                "🧾 *Ordine n. {$order->getKey()}*",
                "💶 Importo: *{$this->euro($order)}*",
                "📅 Pagato il {$this->dateTime($order->paid_at ?? now())}",
            ),
            'A presto! 👋',
            $this->signature(),
        );
    }

    public function cancelled(Order $order): string
    {
        return $this->compose(
            '❌ *Richiesta annullata*',
            "Ciao *{$this->firstName($order)}*, ti informiamo che la tua richiesta d'ordine *n. {$order->getKey()}* è stata annullata.",
            $this->lines(
                "🏪 {$this->plain($order->business_name)}",
                "📅 Inviata il {$this->dateTime($order->created_at)}",
            ),
            '💬 Se pensi si tratti di un errore o vuoi fare un nuovo ordine, rispondi pure a questo messaggio.',
            $this->signature(),
        );
    }

    /**
     * Sections separated by a blank line.
     */
    private function compose(string ...$sections): string
    {
        return implode("\n\n", $sections);
    }

    private function lines(string ...$lines): string
    {
        return implode("\n", $lines);
    }

    private function signature(): string
    {
        return "🍊 *{$this->companyName}* · _Frutta e verdura_";
    }

    private function firstName(Order $order): string
    {
        return $this->plain(Str::title($order->first_name));
    }

    private function orderTextPreview(Order $order): string
    {
        return Str::limit($this->plain(trim($order->message)), self::ORDER_TEXT_PREVIEW_LENGTH, '…');
    }

    private function euro(Order $order): string
    {
        return Number::currency((float) $order->amount, in: 'EUR', locale: 'it');
    }

    private function dateTime(CarbonInterface $moment): string
    {
        return $moment->copy()->setTimezone($this->timezone)->format('d/m/Y \a\l\l\e H:i');
    }

    /**
     * Customer text must not switch WhatsApp formatting on or off by accident.
     */
    private function plain(string $text): string
    {
        return str_replace(['*', '_', '~', '`'], '', $text);
    }
}
