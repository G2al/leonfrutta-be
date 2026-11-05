<?php

namespace App\Http\Requests;

use Filament\Facades\Filament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Minishlink\WebPush\ContentEncoding;
use NotificationChannels\WebPush\PushSubscription;

/**
 * The PushSubscription the browser returns after the administrator allows
 * notifications (PushSubscription.toJSON() plus the content encoding).
 */
class StorePushSubscriptionRequest extends FormRequest
{
    /**
     * Only accounts that can open the admin panel receive order notifications.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccessPanel(Filament::getPanel('admin'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'url:https', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', Rule::enum(ContentEncoding::class)],
        ];
    }
}
