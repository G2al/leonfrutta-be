<?php

namespace Tests\Feature\Admin;

use App\Events\OrderSubmitted;
use App\Filament\Resources\Orders\OrderResource;
use App\Listeners\NotifyAdministratorsOfNewOrder;
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

    private function admin(string $email = 'admin@leonfrutta.it'): User
    {
        return User::factory()->create(['email' => $email]);
    }

    private function withPushDevice(User $user): User
    {
        $user->updatePushSubscription("https://push.example.com/{$user->id}", 'public-key', 'auth-token', 'aes128gcm');

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
    public function a_new_order_from_the_api_notifies_every_administrator(): void
    {
        Notification::fake();
        $this->withoutDefer();

        $adminWithPhone = $this->withPushDevice($this->admin());
        $adminWithoutPhone = $this->admin('altro@leonfrutta.it');
        $outsider = $this->withPushDevice($this->admin('someone@example.com'));

        $this->postJson('/api/v1/orders', $this->orderPayload())->assertCreated();

        $order = Order::sole();
        $isThisOrder = fn (NewOrderReceived $notification): bool => $notification->order->is($order);

        // Bell for everybody, push only where a device has been enabled.
        Notification::assertSentTo($adminWithPhone, NewOrderReceived::class, fn (NewOrderReceived $notification, array $channels): bool => $isThisOrder($notification)
            && $channels === ['database', WebPushChannel::class]);
        Notification::assertSentTo($adminWithoutPhone, NewOrderReceived::class, fn (NewOrderReceived $notification, array $channels): bool => $isThisOrder($notification)
            && $channels === ['database']);
        Notification::assertNotSentTo($outsider, NewOrderReceived::class);
    }

    #[Test]
    public function the_new_order_appears_in_the_filament_notification_bell(): void
    {
        $this->withoutDefer();
        $admin = $this->admin();

        $this->postJson('/api/v1/orders', $this->orderPayload())->assertCreated();

        $notification = $admin->notifications()->sole();

        $this->assertSame('filament', $notification->data['format']);
        $this->assertSame('Nuova richiesta d\'ordine', $notification->data['title']);
        $this->assertSame('Pizzeria Da Mario · Mario Rossi', $notification->data['body']);
        $this->assertSame(OrderResource::getUrl('view', ['record' => Order::sole()], panel: 'admin'), $notification->data['actions'][0]['url']);
        $this->assertNull($notification->read_at);
    }

    #[Test]
    public function the_order_is_saved_even_when_notifications_fail(): void
    {
        $this->withoutDefer();
        $this->admin();

        Notification::shouldReceive('send')->andThrow(new \RuntimeException('Push service unreachable'));

        $this->postJson('/api/v1/orders', $this->orderPayload())->assertCreated();

        $this->assertDatabaseCount('orders', 1);
    }

    #[Test]
    public function the_listener_is_registered_for_new_orders(): void
    {
        Event::fake();

        Event::assertListening(OrderSubmitted::class, NotifyAdministratorsOfNewOrder::class);
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
        $admin = $this->admin();

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

        $this->actingAs($this->admin('someone@example.com'))
            ->postJson('/admin/push-subscriptions', self::SUBSCRIPTION)
            ->assertForbidden();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    #[Test]
    public function invalid_subscriptions_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/admin/push-subscriptions', [
                'endpoint' => 'http://not-https.example.com/push',
                'keys' => [],
                'content_encoding' => 'rot13',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth', 'content_encoding']);
    }

    #[Test]
    public function the_panel_has_the_notification_bell_and_the_push_menu_item_without_popups(): void
    {
        config(['webpush.vapid.public_key' => 'BPublicVapidKeyForTests']);

        $this->get('/admin/login')->assertOk()->assertDontSee('leonfruttaEnablePush');

        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('fi-topbar-database-notifications-btn', escape: false)
            ->assertSee('Attiva notifiche push')
            ->assertSee('window.leonfruttaEnablePush = async', escape: false)
            ->assertSee('BPublicVapidKeyForTests')
            ->assertSee(Js::from(route('push-subscriptions.store'))->toHtml(), escape: false)
            ->assertDontSee('lf-push-card');
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
