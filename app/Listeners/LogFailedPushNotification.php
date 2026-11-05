<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\Events\NotificationFailed;

/**
 * Push services (Google, Apple, Mozilla) answer with an error instead of
 * throwing: without this listener a rejected push would leave no trace.
 */
class LogFailedPushNotification
{
    public function handle(NotificationFailed $event): void
    {
        Log::warning('Notifica push non consegnata.', [
            'push_service' => parse_url($event->report->getEndpoint(), PHP_URL_HOST),
            'status' => $event->report->getResponse()?->getStatusCode(),
            'reason' => $event->report->getReason(),
            'response' => $event->report->getResponse()?->getBody()->__toString(),
            'subscription_removed' => $event->report->isSubscriptionExpired(),
            'user_id' => $event->subscription->subscribable_id,
        ]);
    }
}
