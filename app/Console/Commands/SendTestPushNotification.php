<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sends a test push to every registered device and prints the answer of the
 * push service for each one, to find out why a phone receives nothing.
 */
class SendTestPushNotification extends Command
{
    protected $signature = 'push:test';

    protected $description = 'Invia una notifica push di prova a tutti i dispositivi registrati e mostra l\'esito';

    public function handle(): int
    {
        if (blank(config('webpush.vapid.public_key')) || blank(config('webpush.vapid.private_key'))) {
            $this->components->error('Chiavi VAPID mancanti nella configurazione: controlla il .env ed esegui php artisan optimize:clear.');

            return self::FAILURE;
        }

        $users = User::query()->whereHas('pushSubscriptions')->with('pushSubscriptions')->get();

        if ($users->isEmpty()) {
            $this->components->warn('Nessun dispositivo registrato: dal telefono apri l\'app, menu utente > "Attiva notifiche push".');

            return self::FAILURE;
        }

        // Built only now: the channel reads the VAPID keys checked above.
        $channel = app(WebPushChannel::class);
        $notification = $this->testNotification();

        foreach ($users as $user) {
            foreach ($channel->send($user, $notification) as $report) {
                $device = "{$user->email} · ".parse_url($report->getEndpoint(), PHP_URL_HOST);

                $report->isSuccess()
                    ? $this->components->info("{$device}: consegnata al servizio push")
                    : $this->components->error("{$device}: rifiutata ({$report->getResponse()?->getStatusCode()}) {$report->getReason()}");
            }
        }

        return self::SUCCESS;
    }

    private function testNotification(): Notification
    {
        return new class extends Notification
        {
            public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
            {
                return (new WebPushMessage)
                    ->title('Notifica di prova')
                    ->body('Le notifiche push di Admin Leonfrutta funzionano su questo dispositivo. ✅')
                    ->icon(asset('pwa/icons/icon-192.png'))
                    ->data(['url' => url('/admin')])
                    ->options(['TTL' => 3600, 'urgency' => 'high']);
            }
        };
    }
}
