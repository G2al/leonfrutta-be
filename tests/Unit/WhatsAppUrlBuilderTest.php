<?php

namespace Tests\Unit;

use App\Exceptions\InvalidPhoneNumberException;
use App\Models\Order;
use App\Services\Phone\PhoneNumberNormalizer;
use App\Services\WhatsApp\OrderWhatsAppLink;
use App\Services\WhatsApp\OrderWhatsAppMessage;
use App\Services\WhatsApp\WhatsAppUrlBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WhatsAppUrlBuilderTest extends TestCase
{
    private WhatsAppUrlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new WhatsAppUrlBuilder(new PhoneNumberNormalizer(defaultCountryCode: '39'));
    }

    #[Test]
    public function it_builds_a_wa_me_link_with_the_international_number(): void
    {
        $this->assertSame('https://wa.me/393331234567', $this->builder->build('333 123 4567'));
        $this->assertSame('https://wa.me/447911123456', $this->builder->build('+44 7911 123456'));
    }

    #[Test]
    public function it_url_encodes_spaces_accents_and_line_breaks(): void
    {
        $url = $this->builder->build('3331234567', "Ciao Niccolò,\n10 kg di pomodori & 5 kg di patate?");

        $this->assertSame(
            'https://wa.me/393331234567?text=Ciao%20Niccol%C3%B2%2C%0A10%20kg%20di%20pomodori%20%26%205%20kg%20di%20patate%3F',
            $url,
        );
    }

    #[Test]
    public function the_encoded_text_decodes_back_to_the_original_message(): void
    {
        $message = "Buongiorno Mario,\nperò domani è festa! 100% #ok";

        $query = parse_url($this->builder->build('3331234567', $message), PHP_URL_QUERY);
        parse_str($query, $parameters);

        $this->assertSame($message, $parameters['text']);
    }

    #[Test]
    public function it_omits_the_text_parameter_when_there_is_no_message(): void
    {
        $this->assertSame('https://wa.me/393331234567', $this->builder->build('3331234567', ''));
    }

    #[Test]
    public function it_refuses_to_build_links_for_invalid_numbers(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);

        $this->builder->build('javascript:alert(1)', 'ciao');
    }

    #[Test]
    public function the_order_confirmation_link_contains_the_prefilled_message(): void
    {
        $order = new Order([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'business_name' => 'Pizzeria Da Mario',
            'phone' => '+393331234567',
            'message' => '10 kg di pomodori',
        ]);

        $link = new OrderWhatsAppLink($this->builder, new OrderWhatsAppMessage('LeonFrutta', maxSummaryLength: 1000));

        $query = parse_url($link->confirmationUrl($order), PHP_URL_QUERY);
        parse_str($query, $parameters);

        $this->assertStringStartsWith('https://wa.me/393331234567?text=', $link->confirmationUrl($order));
        $this->assertSame(
            'Buongiorno Mario, siamo LeonFrutta. Ti confermiamo la tua richiesta di ordine. Riepilogo: 10 kg di pomodori. '
            .'Per qualsiasi dettaglio possiamo continuare qui su WhatsApp. Grazie!',
            $parameters['text'],
        );
        $this->assertSame('https://wa.me/393331234567', $link->chatUrl($order));
    }

    #[Test]
    public function the_confirmation_message_contains_the_order_total_in_euro(): void
    {
        $order = new Order(['first_name' => 'Mario', 'message' => '10 kg di pomodori']);
        $order->amount = '1234.5';

        $message = (new OrderWhatsAppMessage('LeonFrutta', maxSummaryLength: 1000))->confirmationFor($order);

        $this->assertStringContainsString("Riepilogo: 10 kg di pomodori. Totale: 1.234,50\u{a0}€.", $message);
    }

    #[Test]
    public function long_order_texts_are_shortened_to_keep_the_url_small(): void
    {
        $order = new Order(['first_name' => 'Mario', 'message' => str_repeat('pomodori ', 500)]);

        $message = (new OrderWhatsAppMessage('LeonFrutta', maxSummaryLength: 100))->confirmationFor($order);

        $this->assertLessThan(300, mb_strlen($message));
        $this->assertStringContainsString('…', $message);
    }

    #[Test]
    public function order_links_are_null_when_the_stored_number_is_invalid(): void
    {
        $order = new Order(['first_name' => 'Mario', 'phone' => 'non valido', 'message' => 'ciao']);

        $link = new OrderWhatsAppLink($this->builder, new OrderWhatsAppMessage('LeonFrutta', maxSummaryLength: 1000));

        $this->assertNull($link->confirmationUrl($order));
        $this->assertNull($link->chatUrl($order));
    }
}
