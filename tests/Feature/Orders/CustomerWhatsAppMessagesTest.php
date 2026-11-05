<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Services\Orders\OrderService;
use App\Services\WhatsApp\WahaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerWhatsAppMessagesTest extends TestCase
{
    use RefreshDatabase;

    private const WAHA_SEND_TEXT_URL = 'http://waha.test:3000/api/sendText';

    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.waha.url' => 'http://waha.test:3000/',
            'services.waha.api_key' => 'secret-key',
            'services.waha.session' => 'leonfrutta',
        ]);
        $this->app->forgetInstance(WahaClient::class);

        $this->withoutDefer();

        $this->orderService = app(OrderService::class);
    }

    /**
     * Http::fake() keeps the first matching stub, so every test registers only the WAHA answer it needs.
     */
    private function fakeWahaAnswer(int $status = 201): void
    {
        Http::fake([self::WAHA_SEND_TEXT_URL => Http::response(['id' => 'message-id'], $status)]);
    }

    private function assertWhatsAppSent(string $chatId, string $title, string $detail): void
    {
        Http::assertSent(fn (Request $request): bool => $request->url() === self::WAHA_SEND_TEXT_URL
            && $request->method() === 'POST'
            && $request->hasHeader('X-Api-Key', 'secret-key')
            && $request['session'] === 'leonfrutta'
            && $request['chatId'] === $chatId
            && str_starts_with($request['text'], $title)
            && str_contains($request['text'], $detail));
    }

    #[Test]
    public function the_customer_is_told_the_order_was_received(): void
    {
        $this->fakeWahaAnswer();

        $this->postJson('/api/v1/orders', [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'business_name' => 'Pizzeria Da Mario',
            'phone' => '333 123 4567',
            'message' => 'Vorrei 10 kg di pomodori.',
        ])->assertCreated();

        $this->assertWhatsAppSent('393331234567@c.us', '🍊 *LeonFrutta*', '🧾 *Richiesta n. '.Order::sole()->id.'*');
    }

    #[Test]
    public function the_customer_is_told_the_order_was_confirmed_with_the_total(): void
    {
        $this->fakeWahaAnswer();

        $order = Order::factory()->create(['first_name' => 'Mario', 'phone' => '+447911123456']);

        $this->orderService->confirm($order, '45.50');

        $this->assertWhatsAppSent('447911123456@c.us', '✅ *Ordine confermato!*', "🧾 *Ordine n. {$order->id}*");
        $this->assertWhatsAppSent('447911123456@c.us', '✅ *Ordine confermato!*', '💶 *Totale: 45,50');
    }

    #[Test]
    public function the_customer_is_told_the_payment_was_registered(): void
    {
        $this->fakeWahaAnswer();

        $order = Order::factory()->confirmed('30.00')->create(['first_name' => 'Mario', 'phone' => '+393331234567']);

        $this->orderService->markAsPaid($order);

        $this->assertWhatsAppSent('393331234567@c.us', '💳 *Pagamento ricevuto*', '💶 Importo: *30,00');
    }

    #[Test]
    public function the_customer_is_told_the_order_was_cancelled(): void
    {
        $this->fakeWahaAnswer();

        $order = Order::factory()->create(['first_name' => 'Mario', 'phone' => '+393331234567']);

        $this->orderService->cancel($order);

        $this->assertWhatsAppSent('393331234567@c.us', '❌ *Richiesta annullata*', "*n. {$order->id}* è stata annullata.");
    }

    #[Test]
    public function correcting_the_amount_or_removing_a_payment_sends_nothing(): void
    {
        $this->fakeWahaAnswer();

        $order = Order::factory()->paid()->create();

        $this->orderService->markAsUnpaid($order);
        $this->orderService->updateAmount($order, '50.00');

        Http::assertNothingSent();
    }

    #[Test]
    public function a_refused_transition_sends_nothing(): void
    {
        $this->fakeWahaAnswer();

        $order = Order::factory()->confirmed()->create();

        try {
            $this->orderService->cancel($order);
        } catch (\DomainException) {
            //
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function a_waha_error_never_breaks_the_order_and_is_logged(): void
    {
        $this->fakeWahaAnswer(422);
        Log::spy();

        $order = Order::factory()->create();
        $this->orderService->confirm($order, '45.50');

        $this->assertTrue($order->fresh()->isConfirmed());
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $context['order_id'] === $order->id);
    }

    #[Test]
    public function nothing_is_sent_while_waha_is_not_configured(): void
    {
        $this->fakeWahaAnswer();

        config(['services.waha.url' => null]);
        $this->app->forgetInstance(WahaClient::class);

        $this->orderService->confirm(Order::factory()->create(), '45.50');

        Http::assertNothingSent();
    }
}
