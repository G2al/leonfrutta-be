<?php

namespace App\Providers;

use App\Services\Orders\OrderRevenue;
use App\Services\Phone\PhoneNumberNormalizer;
use App\Services\WhatsApp\OrderWhatsAppMessage;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PhoneNumberNormalizer::class, fn (): PhoneNumberNormalizer => new PhoneNumberNormalizer(
            defaultCountryCode: (string) config('leonfrutta.whatsapp.default_country_code'),
        ));

        $this->app->singleton(OrderWhatsAppMessage::class, fn (): OrderWhatsAppMessage => new OrderWhatsAppMessage(
            companyName: (string) config('leonfrutta.company_name'),
            maxSummaryLength: (int) config('leonfrutta.whatsapp.max_summary_length'),
        ));

        $this->app->singleton(OrderRevenue::class, fn (): OrderRevenue => new OrderRevenue(
            timezone: (string) config('leonfrutta.display_timezone'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        FilamentTimezone::set(config('leonfrutta.display_timezone'));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('orders', fn (Request $request): Limit => Limit::perMinute(
            (int) config('leonfrutta.orders.rate_limit_per_minute'),
        )->by($request->ip()));

        RateLimiter::for('order-tracking', fn (Request $request): Limit => Limit::perMinute(
            (int) config('leonfrutta.orders.tracking_rate_limit_per_minute'),
        )->by($request->ip()));
    }
}
