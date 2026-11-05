<?php

namespace App\Providers\Filament;

use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(config('leonfrutta.company_name'))
            ->favicon(asset('pwa/icons/icon-192.png'))
            ->colors([
                'primary' => Color::Amber,
            ])
            // Native notification bell next to the user menu (new order requests).
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->userMenuItems([
                Action::make('enablePushNotifications')
                    ->label('Attiva notifiche push')
                    ->icon(Heroicon::OutlinedBellAlert)
                    ->alpineClickHandler('window.leonfruttaEnablePush?.()'),
            ])
            // Installable app (PWA): manifest, iPhone tags, no zoom and install banner.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('pwa.head'))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('pwa.install-banner'))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('pwa.push-notifications'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
