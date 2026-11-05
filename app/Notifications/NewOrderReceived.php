<?php

namespace App\Notifications;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Push notification shown on the administrator's devices when a new order
 * request arrives. Tapping it opens the request in the admin panel.
 */
class NewOrderReceived extends Notification
{
    /** How long the push service keeps trying to deliver to an offline device. */
    private const TIME_TO_LIVE_SECONDS = 24 * 60 * 60;

    private const MESSAGE_PREVIEW_LENGTH = 120;

    public function __construct(
        public readonly Order $order,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $preview = Str::limit(Str::squish($this->order->message), self::MESSAGE_PREVIEW_LENGTH, '…');

        return (new WebPushMessage)
            ->title('Nuova richiesta d\'ordine')
            ->body("{$this->order->business_name} · {$this->order->full_name}\n{$preview}")
            ->icon(asset('pwa/icons/icon-192.png'))
            ->lang('it')
            ->tag("order-{$this->order->getKey()}")
            ->data(['url' => OrderResource::getUrl('view', ['record' => $this->order], panel: 'admin')])
            ->options(['TTL' => self::TIME_TO_LIVE_SECONDS, 'urgency' => 'high']);
    }
}
