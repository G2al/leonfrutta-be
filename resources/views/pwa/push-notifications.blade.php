@php
    $vapidPublicKey = config('webpush.vapid.public_key');
    $cspNonce = \Illuminate\Support\Facades\Vite::cspNonce();
@endphp

{{--
    Push notifications for new orders on this device.
    - window.leonfruttaEnablePush() is called by the "Attiva notifiche push" user menu item
      (browsers only ask for the permission after a tap);
    - when the permission is already granted, the device subscription is silently re-sent,
      so the server always has it.
    Feedback uses Filament's own toast notifications.
--}}
@if (auth()->check() && filled($vapidPublicKey))
    <script @if (filled($cspNonce)) nonce="{{ $cspNonce }}" @endif>
        (() => {
            const VAPID_PUBLIC_KEY = @js($vapidPublicKey);
            const SUBSCRIBE_URL = @js(route('push-subscriptions.store'));

            const isSupported = () => 'serviceWorker' in navigator
                && 'PushManager' in window
                && 'Notification' in window
                && window.isSecureContext;

            const isAppleMobile = /iphone|ipad|ipod/i.test(navigator.userAgent)
                || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

            const notify = (status, title, body = null) => {
                const notification = new window.FilamentNotification().title(title)[status]();

                (body ? notification.body(body) : notification).send();
            };

            // The VAPID key is base64url: PushManager wants raw bytes.
            const toUint8Array = (base64Url) => {
                const base64 = (base64Url + '='.repeat((4 - (base64Url.length % 4)) % 4))
                    .replace(/-/g, '+')
                    .replace(/_/g, '/');

                return Uint8Array.from(window.atob(base64), (character) => character.charCodeAt(0));
            };

            const serverKey = toUint8Array(VAPID_PUBLIC_KEY);

            const isSubscribedWithServerKey = (subscription) => {
                const subscribedKey = subscription.options?.applicationServerKey;

                return subscribedKey instanceof ArrayBuffer
                    && new Uint8Array(subscribedKey).every((byte, index) => byte === serverKey[index])
                    && subscribedKey.byteLength === serverKey.length;
            };

            // A subscription made with an older VAPID key is rejected by the push service: replace it.
            const currentSubscription = async (pushManager) => {
                const existing = await pushManager.getSubscription();

                if (existing && isSubscribedWithServerKey(existing)) {
                    return existing;
                }

                await existing?.unsubscribe();

                return pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: serverKey });
            };

            const saveSubscription = async () => {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await currentSubscription(registration.pushManager);

                const response = await fetch(SUBSCRIBE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        ...subscription.toJSON(),
                        content_encoding: (PushManager.supportedContentEncodings ?? ['aes128gcm'])[0],
                    }),
                });

                if (! response.ok) {
                    throw new Error(`Push subscription not saved (HTTP ${response.status})`);
                }
            };

            window.leonfruttaEnablePush = async () => {
                if (! isSupported()) {
                    isAppleMobile
                        ? notify('warning', 'Installa prima l\'app', 'Su iPhone le notifiche funzionano solo dall\'app aggiunta alla schermata Home: aprila da lì e riprova.')
                        : notify('warning', 'Notifiche non supportate', 'Questo browser non supporta le notifiche push.');

                    return;
                }

                if (Notification.permission === 'denied') {
                    notify('danger', 'Notifiche bloccate', 'Le hai negate in precedenza: abilitale dalle impostazioni del telefono o del browser per questo sito.');

                    return;
                }

                const permission = await Notification.requestPermission();

                if (permission !== 'granted') {
                    notify('warning', 'Notifiche non attivate');

                    return;
                }

                try {
                    await saveSubscription();
                    notify('success', 'Notifiche attive', 'Riceverai un avviso su questo dispositivo a ogni nuova richiesta d\'ordine.');
                } catch {
                    notify('danger', 'Attivazione non riuscita', 'Controlla la connessione e riprova.');
                }
            };

            if (isSupported() && Notification.permission === 'granted') {
                saveSubscription().catch(() => {});
            }
        })();
    </script>
@endif
