<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePushSubscriptionRequest;
use Illuminate\Http\JsonResponse;

/**
 * Saves the push subscription of the device the administrator is using,
 * so new order notifications reach it.
 */
class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $request->user()->updatePushSubscription(
            endpoint: $request->input('endpoint'),
            key: $request->input('keys.p256dh'),
            token: $request->input('keys.auth'),
            contentEncoding: $request->input('content_encoding', 'aes128gcm'),
        );

        return response()->json(['success' => true, 'message' => 'Notifiche attivate su questo dispositivo.']);
    }
}
