<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Services\Orders\OrderRevenue;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class RevenueOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Incassi';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $revenue = app(OrderRevenue::class);

        return [
            Stat::make('Incassato oggi', $this->euro($revenue->collectedToday()))
                ->icon(PaymentStatus::Paid->getIcon())
                ->color('success'),
            Stat::make('Incassato questo mese', $this->euro($revenue->collectedThisMonth()))
                ->icon(PaymentStatus::Paid->getIcon())
                ->color('success'),
            Stat::make('Confermato questo mese', $this->euro($revenue->confirmedThisMonth()))
                ->description('Valore degli ordini confermati, pagati e non')
                ->icon(Heroicon::OutlinedCurrencyEuro),
            Stat::make('Da incassare', $this->euro($revenue->outstanding()))
                ->description('Ordini confermati non ancora pagati')
                ->icon(PaymentStatus::Unpaid->getIcon())
                ->color('warning')
                ->url(OrderResource::getUrl('index', ['filters' => ['paid' => ['value' => 0]]])),
        ];
    }

    private function euro(string $amount): string
    {
        return Number::currency((float) $amount, in: 'EUR', locale: 'it');
    }
}
