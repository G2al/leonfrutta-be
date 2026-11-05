@php
    $pwa = config('leonfrutta.pwa');
    $cspNonce = \Illuminate\Support\Facades\Vite::cspNonce();
    $viewport = 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover';
@endphp

{{-- Installable app: manifest for Android / desktop, Apple tags for iPhone "Add to Home Screen". --}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ $pwa['theme_color'] }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $pwa['short_name'] }}">
<meta name="application-name" content="{{ $pwa['short_name'] }}">
<link rel="apple-touch-icon" href="{{ asset('pwa/icons/apple-touch-icon.png') }}">

{{-- No zoom, like a native app. Filament prints its own viewport tag first: this one replaces it. --}}
<meta name="viewport" content="{{ $viewport }}">

<style>
    html {
        touch-action: manipulation;
        -webkit-text-size-adjust: 100%;
        text-size-adjust: 100%;
    }
</style>

<script @if (filled($cspNonce)) nonce="{{ $cspNonce }}" @endif>
    (() => {
        // Every viewport tag gets the same no-zoom value, whatever order the browser reads them in.
        document.querySelectorAll('meta[name="viewport"]').forEach((meta) => {
            meta.setAttribute('content', @js($viewport));
        });

        // Safari on iPhone ignores "user-scalable=no": pinch gestures are blocked explicitly.
        ['gesturestart', 'gesturechange', 'gestureend'].forEach((gesture) => {
            document.addEventListener(gesture, (event) => event.preventDefault(), { passive: false });
        });

        document.addEventListener('touchmove', (event) => {
            if (event.touches.length > 1) {
                event.preventDefault();
            }
        }, { passive: false });

        // Android / Chromium fire this once the app is installable: keep it for the install button.
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            window.leonfruttaInstallPrompt = event;
            window.dispatchEvent(new Event('leonfrutta:installable'));
        });

        if ('serviceWorker' in navigator && window.isSecureContext) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
            });
        }
    })();
</script>
