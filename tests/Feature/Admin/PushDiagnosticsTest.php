<?php

namespace Tests\Feature\Admin;

use App\Listeners\LogFailedPushNotification;
use App\Models\User;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PushDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function rejected_pushes_are_written_to_the_log(): void
    {
        Event::fake([NotificationFailed::class]);
        Event::assertListening(NotificationFailed::class, LogFailedPushNotification::class);

        $user = User::factory()->create(['email' => 'admin@leonfrutta.it']);
        $subscription = $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc', 'key', 'token', 'aes128gcm');

        $report = new MessageSentReport(
            new Request('POST', 'https://fcm.googleapis.com/fcm/send/abc'),
            new Response(403, [], 'the key in the authorization header does not correspond to the sender ID'),
            false,
            'Forbidden',
        );

        Log::spy();

        (new LogFailedPushNotification)->handle(new NotificationFailed($report, $subscription, new WebPushMessage));

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['push_service'] === 'fcm.googleapis.com'
            && $context['status'] === 403
            && str_contains($context['response'], 'does not correspond')
            && $context['user_id'] === $user->id);
    }

    #[Test]
    public function the_test_command_explains_missing_keys_or_devices(): void
    {
        config(['webpush.vapid.public_key' => null]);
        $this->artisan('push:test')->expectsOutputToContain('Chiavi VAPID mancanti')->assertFailed();

        config(['webpush.vapid.public_key' => 'public', 'webpush.vapid.private_key' => 'private']);
        $this->artisan('push:test')->expectsOutputToContain('Nessun dispositivo registrato')->assertFailed();
    }

    #[Test]
    public function the_test_command_prints_the_result_of_every_device(): void
    {
        config(['webpush.vapid.public_key' => 'public', 'webpush.vapid.private_key' => 'private']);

        $user = User::factory()->create(['email' => 'admin@leonfrutta.it']);
        $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc', 'key', 'token', 'aes128gcm');

        $this->mock(WebPushChannel::class)->shouldReceive('send')->once()->andReturn([
            new MessageSentReport(new Request('POST', 'https://fcm.googleapis.com/fcm/send/abc'), new Response(201), true, 'OK'),
        ]);

        $this->artisan('push:test')
            ->expectsOutputToContain('admin@leonfrutta.it · fcm.googleapis.com: consegnata al servizio push')
            ->assertSuccessful();
    }

    #[Test]
    public function the_panel_replaces_subscriptions_made_with_an_old_key(): void
    {
        config(['webpush.vapid.public_key' => 'BPublicVapidKeyForTests']);

        $this->actingAs(User::factory()->create(['email' => 'admin@leonfrutta.it']))
            ->get('/admin')
            ->assertOk()
            ->assertSee('isSubscribedWithServerKey(existing)', escape: false)
            ->assertSee('existing?.unsubscribe()', escape: false);
    }
}
