<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreOrderTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/orders';

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'business_name' => 'Pizzeria Da Mario',
            'phone' => '333 123 4567',
            'message' => 'Buongiorno, vorrei ordinare 10 kg di pomodori e 5 kg di patate.',
            ...$overrides,
        ];
    }

    #[Test]
    public function a_customer_can_submit_an_order_request(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->validPayload());

        $order = Order::sole();

        $response
            ->assertCreated()
            ->assertExactJson([
                'success' => true,
                'message' => 'La tua richiesta è stata inviata con successo.',
                'data' => [
                    'id' => $order->id,
                    'tracking_token' => $order->tracking_token,
                    'status' => 'new',
                    'status_label' => 'Nuovo',
                    'business_name' => 'Pizzeria Da Mario',
                    'message' => 'Buongiorno, vorrei ordinare 10 kg di pomodori e 5 kg di patate.',
                    'amount' => null,
                    'payment_status' => null,
                    'payment_status_label' => null,
                    'created_at' => $order->created_at->toIso8601String(),
                    'confirmed_at' => null,
                    'cancelled_at' => null,
                    'paid_at' => null,
                ],
            ]);

        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame('Mario', $order->first_name);
        $this->assertSame('Pizzeria Da Mario', $order->business_name);
        $this->assertSame('+393331234567', $order->phone);
        $this->assertNull($order->confirmed_at);
        $this->assertNull($order->cancelled_at);
    }

    #[Test]
    public function international_phone_numbers_are_accepted(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload(['phone' => '+44 7911 123456']))
            ->assertCreated();

        $this->assertSame('+447911123456', Order::sole()->phone);
    }

    #[Test]
    public function all_fields_are_required(): void
    {
        $this->postJson(self::ENDPOINT, [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'business_name', 'phone', 'message']);

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPhoneNumbers(): array
    {
        return [
            'letters' => ['chiamami'],
            'too short' => ['12345'],
            'too long' => ['+39 333 123 4567 8901 2345'],
            'link' => ['https://evil.example'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPhoneNumbers')]
    public function invalid_phone_numbers_are_rejected(string $phone): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload(['phone' => $phone]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function empty_or_blank_messages_are_rejected(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload(['message' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message']);

        $this->postJson(self::ENDPOINT, $this->validPayload(['message' => '      ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message']);

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function messages_must_respect_length_limits(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload(['message' => 'ciao']))
            ->assertJsonValidationErrors(['message']);

        $this->postJson(self::ENDPOINT, $this->validPayload(['message' => str_repeat('a', 3001)]))
            ->assertJsonValidationErrors(['message']);
    }

    #[Test]
    public function the_client_cannot_set_the_status_or_the_admin_timestamps(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload([
            'status' => 'confirmed',
            'confirmed_at' => '2026-01-01 10:00:00',
            'cancelled_at' => '2026-01-01 10:00:00',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'confirmed_at', 'cancelled_at']);

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function unknown_fields_are_never_mass_assigned(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload(['id' => 999, 'created_at' => '2000-01-01']))
            ->assertCreated();

        $order = Order::sole();

        $this->assertNotSame(999, $order->id);
        $this->assertTrue($order->created_at->isToday());
    }

    #[Test]
    public function validation_errors_are_returned_as_a_consistent_json_envelope(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload(['first_name' => '']))
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => 'I dati inviati non sono validi. Controlla i campi evidenziati.',
                'errors' => [
                    'first_name' => ['Il campo nome è obbligatorio.'],
                ],
            ]);
    }

    #[Test]
    public function requests_are_rate_limited_per_ip_address(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson(self::ENDPOINT, $this->validPayload())->assertCreated();
        }

        $this->postJson(self::ENDPOINT, $this->validPayload())
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertExactJson([
                'success' => false,
                'message' => 'Hai inviato troppe richieste. Riprova tra qualche istante.',
            ]);

        $this->assertDatabaseCount('orders', 5);
    }

    #[Test]
    public function unexpected_errors_do_not_leak_internal_details(): void
    {
        config(['app.debug' => false]);

        Order::creating(fn () => throw new \RuntimeException('SQLSTATE secret details'));

        $this->postJson(self::ENDPOINT, $this->validPayload())
            ->assertInternalServerError()
            ->assertExactJson([
                'success' => false,
                'message' => 'Si è verificato un errore inatteso. Riprova più tardi.',
            ]);
    }

    #[Test]
    public function orders_cannot_be_listed_changed_or_deleted_publicly(): void
    {
        $order = Order::factory()->create();

        $this->getJson(self::ENDPOINT)->assertMethodNotAllowed()->assertJsonPath('success', false);
        $this->patchJson(self::ENDPOINT."/{$order->tracking_token}", ['status' => 'confirmed'])->assertMethodNotAllowed();
        $this->deleteJson(self::ENDPOINT."/{$order->tracking_token}")->assertMethodNotAllowed();

        $this->assertSame(OrderStatus::New, $order->fresh()->status);
    }

    #[Test]
    public function the_nextjs_development_origin_is_allowed_by_cors(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:3000'])
            ->postJson(self::ENDPOINT, $this->validPayload())
            ->assertCreated()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }
}
