<?php

namespace App\Listeners;

use App\Events\OrderSubmitted;
use App\Models\User;
use App\Notifications\NewOrderReceived;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Notifies every administrator device subscribed to push notifications.
 *
 * Sending is deferred until the response has been returned to the customer:
 * the order is confirmed immediately, no queue worker or scheduler is needed,
 * and a push failure can never make the order request fail.
 */
class SendNewOrderPushNotification
{
    public function handle(OrderSubmitted $event): void
    {
        defer(function () use ($event): void {
            try {
                Notification::send($this->subscribedAdministrators(), new NewOrderReceived($event->order));
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function subscribedAdministrators(): Collection
    {
        $adminPanel = Filament::getPanel('admin');

        return User::query()
            ->whereHas('pushSubscriptions')
            ->with('pushSubscriptions')
            ->get()
            ->filter(fn (User $user): bool => $user->canAccessPanel($adminPanel))
            ->values()
            ->toBase();
    }
}
