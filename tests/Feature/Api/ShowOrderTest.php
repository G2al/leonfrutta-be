<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowOrderTest extends TestCase
{
    use RefreshDatabase;

    private function endpoint(string $token): string
    {
        return "/api/v1/orders/{$token}";
    }

    #[Test]
    public function the_customer_can_read_a_new_order_with_its_tracking_token(): void
    {
        $order = Order::factory()->create();

        $this->getJson($this->endpoint($order->tracking_token))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.status_label', 'Nuovo')
            ->assertJsonPath('data.amount', null)
            ->assertJsonPath('data.payment_status', null);
    }

    #[Test]
    public function a_confirmed_order_shows_its_amount_and_payment_status(): void
    {
        $order = Order::factory()->confirmed('45.50')->create();

        $this->getJson($this->endpoint($order->tracking_token))
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.status_label', 'Confermato')
            ->assertJsonPath('data.amount', '45.50')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.payment_status_label', 'Da pagare')
            ->assertJsonPath('data.confirmed_at', $order->confirmed_at->toIso8601String())
            ->assertJsonPath('data.paid_at', null);
    }

    #[Test]
    public function a_paid_order_shows_when_it_was_paid(): void
    {
        $order = Order::factory()->paid()->create();

        $this->getJson($this->endpoint($order->tracking_token))
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payment_status_label', 'Pagato')
            ->assertJsonPath('data.paid_at', $order->paid_at->toIso8601String());
    }

    #[Test]
    public function the_phone_number_is_never_exposed(): void
    {
        $order = Order::factory()->create(['phone' => '+393331234567']);

        $this->getJson($this->endpoint($order->tracking_token))
            ->assertOk()
            ->assertJsonMissingPath('data.phone')
            ->assertDontSee('3331234567');
    }

    #[Test]
    public function orders_cannot_be_read_by_their_sequential_id(): void
    {
        $order = Order::factory()->create();

        $this->getJson($this->endpoint((string) $order->id))
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'La risorsa richiesta non esiste.']);
    }

    #[Test]
    public function unknown_tracking_tokens_return_not_found(): void
    {
        Order::factory()->create();

        $this->getJson($this->endpoint('0195f4a2-7b1c-7d3e-9f00-000000000000'))
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function reading_orders_is_rate_limited(): void
    {
        config(['leonfrutta.orders.tracking_rate_limit_per_minute' => 2]);
        $order = Order::factory()->create();

        $this->getJson($this->endpoint($order->tracking_token))->assertOk();
        $this->getJson($this->endpoint($order->tracking_token))->assertOk();
        $this->getJson($this->endpoint($order->tracking_token))->assertTooManyRequests();
    }
}
