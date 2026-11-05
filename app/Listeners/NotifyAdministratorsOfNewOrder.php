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
 * Tells every administrator about the new order: in the Filament bell and,
 * on the devices where it is enabled, with a push notification.
 *
 * Sending is deferred until the response has been returned to the customer:
 * the order is confirmed immediately, no queue worker or scheduler is needed,
 * and a notification failure can never make the order request fail.
 */
class NotifyAdministratorsOfNewOrder
{
    public function handle(OrderSubmitted $event): void
    {
        defer(function () use ($event): void {
            try {
                Notification::send($this->administrators(), new NewOrderReceived($event->order));
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function administrators(): Collection
    {
        $adminPanel = Filament::getPanel('admin');

        return User::query()
            ->get()
            ->filter(fn (User $user): bool => $user->canAccessPanel($adminPanel))
            ->values()
            ->toBase();
    }
}
