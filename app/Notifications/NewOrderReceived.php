<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent to every administrator when a new order request arrives:
 * - always in the Filament notification bell (database channel);
 * - as a push notification on the devices where push has been enabled.
 */
class NewOrderReceived extends Notification
{
    private const TITLE = 'Nuova richiesta d\'ordine';

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
        $hasPushDevices = method_exists($notifiable, 'pushSubscriptions')
            && $notifiable->pushSubscriptions()->exists();

        return $hasPushDevices ? ['database', WebPushChannel::class] : ['database'];
    }

    /**
     * Filament's own format, shown in the bell next to the user menu.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(self::TITLE)
            ->body("{$this->order->business_name} · {$this->order->full_name}")
            ->icon(OrderStatus::New->getIcon())
            ->iconColor(OrderStatus::New->getColor())
            ->actions([
                Action::make('view')
                    ->label('Apri richiesta')
                    ->button()
                    ->url($this->orderUrl())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $preview = Str::limit(Str::squish($this->order->message), self::MESSAGE_PREVIEW_LENGTH, '…');

        return (new WebPushMessage)
            ->title(self::TITLE)
            ->body("{$this->order->business_name} · {$this->order->full_name}\n{$preview}")
            ->icon(asset('pwa/icons/icon-192.png'))
            ->lang('it')
            ->tag("order-{$this->order->getKey()}")
            ->data(['url' => $this->orderUrl()])
            ->options(['TTL' => self::TIME_TO_LIVE_SECONDS, 'urgency' => 'high']);
    }

    private function orderUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->order], panel: 'admin');
    }
}
