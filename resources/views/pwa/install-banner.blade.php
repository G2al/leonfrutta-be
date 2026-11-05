@php
    $pwa = config('leonfrutta.pwa');
    $cspNonce = \Illuminate\Support\Facades\Vite::cspNonce();
@endphp

{{--
    Install prompt, never shown inside the installed app.
    - Android / Chromium: real "Installa" button using the browser install dialog.
    - iPhone / iPad: Safari has no install API, so it explains "Condividi > Aggiungi alla schermata Home".
--}}
<div id="lf-install-banner" class="lf-install" role="dialog" aria-live="polite" aria-label="Installa l'app" hidden>
    <img class="lf-install__icon" src="{{ asset('pwa/icons/icon-192.png') }}" alt="" width="48" height="48">

    <div class="lf-install__body">
        <p class="lf-install__title">Installa {{ $pwa['short_name'] }}</p>

        <p class="lf-install__text lf-install__text--android">
            Aggiungi l'app al telefono per aprire le richieste con un tocco, a schermo intero.
        </p>

        <p class="lf-install__text lf-install__text--ios">
            Tocca
            <svg class="lf-install__share" viewBox="0 0 24 24" aria-label="Condividi" role="img">
                <path d="M12 3v12M12 3 8 7M12 3l4 4M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <strong>Condividi</strong>, poi <strong>«Aggiungi alla schermata Home»</strong>.
        </p>

        <button type="button" class="lf-install__button" data-install>Installa app</button>
    </div>

    <button type="button" class="lf-install__close" data-dismiss aria-label="Chiudi">&times;</button>
</div>

<style>
    .lf-install {
        position: fixed;
        z-index: 60;
        left: 12px;
        right: 12px;
        bottom: calc(12px + env(safe-area-inset-bottom));
        max-width: 28rem;
        margin: 0 auto;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px;
        border-radius: 16px;
        background: #ffffff;
        color: #111827;
        border: 1px solid rgba(17, 24, 39, 0.08);
        box-shadow: 0 10px 30px rgba(17, 24, 39, 0.18);
        font-size: 14px;
        line-height: 1.45;
    }

    .lf-install[hidden] { display: none; }

    .dark .lf-install {
        background: #18181b;
        color: #f4f4f5;
        border-color: rgba(255, 255, 255, 0.1);
    }

    .lf-install__icon { flex: none; width: 48px; height: 48px; border-radius: 12px; background: {{ $pwa['background_color'] }}; }
    .lf-install__body { flex: 1; min-width: 0; }
    .lf-install__title { margin: 0 0 2px; font-weight: 600; font-size: 15px; }
    .lf-install__text { margin: 0; opacity: 0.8; }
    .lf-install__share { display: inline-block; width: 18px; height: 18px; vertical-align: -3px; color: #2563eb; }

    .lf-install__button {
        margin-top: 10px;
        padding: 8px 16px;
        border: 0;
        border-radius: 10px;
        font: inherit;
        font-weight: 600;
        color: #ffffff;
        background: var(--primary-600, #d97706);
        cursor: pointer;
    }

    .lf-install__close {
        flex: none;
        width: 32px;
        height: 32px;
        margin: -6px -6px 0 0;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: inherit;
        font-size: 22px;
        line-height: 1;
        opacity: 0.6;
        cursor: pointer;
    }

    .lf-install[data-mode="android"] .lf-install__text--ios,
    .lf-install[data-mode="ios"] .lf-install__text--android,
    .lf-install[data-mode="ios"] .lf-install__button {
        display: none;
    }
</style>

<script @if (filled($cspNonce)) nonce="{{ $cspNonce }}" @endif>
    (() => {
        const banner = document.getElementById('lf-install-banner');
        const DISMISSED_UNTIL_KEY = 'leonfrutta:install-banner:dismissed-until';
        const DISMISS_DAYS = 7;

        const isInstalledApp = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;

        // iPadOS reports itself as a Mac: the touch screen gives it away.
        const isAppleMobile = /iphone|ipad|ipod/i.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        const wasDismissedRecently = () => {
            try {
                return Number(localStorage.getItem(DISMISSED_UNTIL_KEY) || 0) > Date.now();
            } catch {
                return false;
            }
        };

        const show = (mode) => {
            banner.dataset.mode = mode;
            banner.hidden = false;
        };

        const hide = () => {
            banner.hidden = true;
        };

        const dismiss = () => {
            try {
                localStorage.setItem(DISMISSED_UNTIL_KEY, String(Date.now() + DISMISS_DAYS * 24 * 60 * 60 * 1000));
            } catch {
                // Private browsing: the banner simply comes back on the next visit.
            }

            hide();
        };

        if (! banner || isInstalledApp || wasDismissedRecently()) {
            return;
        }

        const showAndroidInstallButton = () => {
            if (window.leonfruttaInstallPrompt) {
                show('android');
            }
        };

        if (isAppleMobile) {
            show('ios');
        } else {
            showAndroidInstallButton();
            window.addEventListener('leonfrutta:installable', showAndroidInstallButton);
        }

        banner.querySelector('[data-install]').addEventListener('click', async () => {
            const installPrompt = window.leonfruttaInstallPrompt;

            if (! installPrompt) {
                return;
            }

            // A prompt can be shown only once: the browser fires a new event if it is still installable.
            window.leonfruttaInstallPrompt = null;
            installPrompt.prompt();

            const { outcome } = await installPrompt.userChoice;

            outcome === 'accepted' ? hide() : dismiss();
        });

        banner.querySelector('[data-dismiss]').addEventListener('click', dismiss);

        window.addEventListener('appinstalled', hide);
    })();
</script>
