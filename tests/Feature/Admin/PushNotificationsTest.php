<?php

namespace Tests\Feature\Admin;

use App\Events\OrderSubmitted;
use App\Filament\Resources\Orders\OrderResource;
use App\Listeners\SendNewOrderPushNotification;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Js;
use NotificationChannels\WebPush\WebPushChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private const SUBSCRIPTION = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/device-token-123',
        'keys' => [
            'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
            'auth' => 'tBHItJI5svbpez7KI4CCXg',
        ],
        'content_encoding' => 'aes128gcm',
    ];

    private function subscribedUser(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->updatePushSubscription('https://push.example.com/'.$email, 'public-key', 'auth-token', 'aes128gcm');

        return $user;
    }

    private function orderPayload(): array
    {
        return [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'business_name' => 'Pizzeria Da Mario',
            'phone' => '333 123 4567',
            'message' => 'Vorrei 10 kg di pomodori e 5 kg di patate.',
        ];
    }

    #[Test]
    public function a_new_order_from_the_api_notifies_the_subscribed_administrators(): void
    {
        Notification::fake();
        $this->withoutDefer();

        $admin = $this->subscribedUser('admin@leonfrutta.it');
        $adminWithoutDevices = User::factory()->create(['email' => 'altro@leonfrutta.it']);
        $outsider = $this->subscribedUser('someone@example.com');

        $this->postJson('/api/v1/orders', $this->orderPayload())->assertCreated();

        $order = Order::sole();

        Notification::assertSentTo($admin, NewOrderReceived::class, fn (NewOrderReceived $notification): bool => $notification->order->is($order));
        Notification::assertNotSentTo($adminWithoutDevices, NewOrderReceived::class);
        Notification::assertNotSentTo($outsider, NewOrderReceived::class);
    }

    #[Test]
    public function the_order_is_saved_even_when_sending_the_push_fails(): void
    {
        $this->withoutDefer();
        $this->subscribedUser('admin@leonfrutta.it');

        Notification::shouldReceive('send')->andThrow(new \RuntimeException('Push service unreachable'));

        $this->postJson('/api/v1/orders', $this->orderPayload())->assertCreated();

        $this->assertDatabaseCount('orders', 1);
    }

    #[Test]
    public function the_listener_is_registered_for_new_orders(): void
    {
        Event::fake();

        Event::assertListening(OrderSubmitted::class, SendNewOrderPushNotification::class);
    }

    #[Test]
    public function the_push_message_describes_the_order_and_opens_it(): void
    {
        $order = Order::factory()->create([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'business_name' => 'Pizzeria Da Mario',
            'message' => "10 kg di pomodori\n5 kg di patate",
        ]);
        $notification = new NewOrderReceived($order);

        $this->assertSame([WebPushChannel::class], $notification->via(new User));

        $message = $notification->toWebPush(new User, $notification);
        $payload = $message->toArray();

        $this->assertSame('Nuova richiesta d\'ordine', $payload['title']);
        $this->assertSame("Pizzeria Da Mario · Mario Rossi\n10 kg di pomodori 5 kg di patate", $payload['body']);
        $this->assertSame("order-{$order->id}", $payload['tag']);
        $this->assertSame(OrderResource::getUrl('view', ['record' => $order], panel: 'admin'), $payload['data']['url']);
        $this->assertStringEndsWith('/pwa/icons/icon-192.png', $payload['icon']);
        $this->assertSame('high', $message->getOptions()['urgency']);
    }

    #[Test]
    public function an_administrator_can_register_the_device_for_notifications(): void
    {
        $admin = User::factory()->create(['email' => 'admin@leonfrutta.it']);

        $this->actingAs($admin)
            ->postJson('/admin/push-subscriptions', self::SUBSCRIPTION)
            ->assertOk()
            ->assertJsonPath('success', true);

        // Sending the same device again updates it instead of duplicating it.
        $this->actingAs($admin)->postJson('/admin/push-subscriptions', self::SUBSCRIPTION)->assertOk();

        $this->assertSame(1, $admin->pushSubscriptions()->count());
        $this->assertSame(self::SUBSCRIPTION['endpoint'], $admin->pushSubscriptions()->sole()->endpoint);
    }

    #[Test]
    public function guests_and_outsiders_cannot_register_devices(): void
    {
        $this->postJson('/admin/push-subscriptions', self::SUBSCRIPTION)->assertUnauthorized();

        $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
            ->postJson('/admin/push-subscriptions', self::SUBSCRIPTION)
            ->assertForbidden();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    #[Test]
    public function invalid_subscriptions_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'admin@leonfrutta.it']))
            ->postJson('/admin/push-subscriptions', [
                'endpoint' => 'http://not-https.example.com/push',
                'keys' => [],
                'content_encoding' => 'rot13',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth', 'content_encoding']);
    }

    #[Test]
    public function the_panel_offers_notifications_only_to_logged_in_administrators(): void
    {
        config(['webpush.vapid.public_key' => 'BPublicVapidKeyForTests']);

        $this->get('/admin/login')->assertOk()->assertDontSee('id="lf-push-card"', escape: false);

        $this->actingAs(User::factory()->create(['email' => 'admin@leonfrutta.it']))
            ->get('/admin')
            ->assertOk()
            ->assertSee('id="lf-push-card"', escape: false)
            ->assertSee('BPublicVapidKeyForTests')
            ->assertSee(Js::from(route('push-subscriptions.store'))->toHtml(), escape: false);
    }

    #[Test]
    public function the_service_worker_shows_push_notifications_and_opens_the_order(): void
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("addEventListener('push'", $serviceWorker);
        $this->assertStringContainsString('showNotification(', $serviceWorker);
        $this->assertStringContainsString("addEventListener('notificationclick'", $serviceWorker);
    }
}
