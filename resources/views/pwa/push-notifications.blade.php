@php
    $vapidPublicKey = config('webpush.vapid.public_key');
    $cspNonce = \Illuminate\Support\Facades\Vite::cspNonce();
@endphp

{{--
    Push notifications for new orders, offered only inside the installed app
    (on iPhone web push works only from the Home Screen app, iOS 16.4+).
    - permission not asked yet: card with the "Attiva notifiche" button (a tap is required by browsers);
    - permission granted: the device subscription is silently re-sent, so the server always has it.
--}}
@if (auth()->check() && filled($vapidPublicKey))
    <div id="lf-push-card" class="lf-install" role="dialog" aria-live="polite" aria-label="Notifiche" hidden>
        <img class="lf-install__icon" src="{{ asset('pwa/icons/icon-192.png') }}" alt="" width="48" height="48">

        <div class="lf-install__body">
            <p class="lf-install__title" data-title>Attiva le notifiche</p>
            <p class="lf-install__text" data-text>Ricevi un avviso su questo dispositivo appena arriva una nuova richiesta d'ordine.</p>
            <button type="button" class="lf-install__button" data-enable>Attiva notifiche</button>
        </div>

        <button type="button" class="lf-install__close" data-dismiss aria-label="Chiudi">&times;</button>
    </div>

    <script @if (filled($cspNonce)) nonce="{{ $cspNonce }}" @endif>
        (() => {
            const card = document.getElementById('lf-push-card');
            const VAPID_PUBLIC_KEY = @js($vapidPublicKey);
            const SUBSCRIBE_URL = @js(route('push-subscriptions.store'));
            const DISMISSED_UNTIL_KEY = 'leonfrutta:push-card:dismissed-until';
            const DISMISS_DAYS = 7;

            const isInstalledApp = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;

            const isSupported = 'serviceWorker' in navigator
                && 'PushManager' in window
                && 'Notification' in window
                && window.isSecureContext;

            if (! card || ! isInstalledApp || ! isSupported || Notification.permission === 'denied') {
                return;
            }

            // The VAPID key is base64url: PushManager wants raw bytes.
            const toUint8Array = (base64Url) => {
                const base64 = (base64Url + '='.repeat((4 - (base64Url.length % 4)) % 4))
                    .replace(/-/g, '+')
                    .replace(/_/g, '/');

                return Uint8Array.from(window.atob(base64), (character) => character.charCodeAt(0));
            };

            const saveSubscription = async () => {
                const registration = await navigator.serviceWorker.ready;

                const subscription = await registration.pushManager.getSubscription()
                    ?? await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: toUint8Array(VAPID_PUBLIC_KEY),
                    });

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

            if (Notification.permission === 'granted') {
                saveSubscription().catch(() => {});

                return;
            }

            const wasDismissedRecently = () => {
                try {
                    return Number(localStorage.getItem(DISMISSED_UNTIL_KEY) || 0) > Date.now();
                } catch {
                    return false;
                }
            };

            const dismiss = () => {
                try {
                    localStorage.setItem(DISMISSED_UNTIL_KEY, String(Date.now() + DISMISS_DAYS * 24 * 60 * 60 * 1000));
                } catch {
                    // Private browsing: the card simply comes back on the next visit.
                }

                card.hidden = true;
            };

            const showResult = (title, text) => {
                card.querySelector('[data-title]').textContent = title;
                card.querySelector('[data-text]').textContent = text;
                card.querySelector('[data-enable]').hidden = true;
            };

            if (wasDismissedRecently()) {
                return;
            }

            card.hidden = false;

            card.querySelector('[data-enable]').addEventListener('click', async () => {
                const permission = await Notification.requestPermission();

                if (permission !== 'granted') {
                    showResult('Notifiche non attive', 'Puoi abilitarle in seguito dalle impostazioni del telefono.');
                    setTimeout(dismiss, 4000);

                    return;
                }

                try {
                    await saveSubscription();
                    showResult('Notifiche attive', 'Riceverai un avviso a ogni nuova richiesta d\'ordine.');
                    setTimeout(() => { card.hidden = true; }, 3000);
                } catch {
                    showResult('Attivazione non riuscita', 'Controlla la connessione e riprova più tardi.');
                }
            });

            card.querySelector('[data-dismiss]').addEventListener('click', dismiss);
        })();
    </script>
@endif
