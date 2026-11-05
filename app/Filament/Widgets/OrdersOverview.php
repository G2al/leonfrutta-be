<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrdersOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Richieste ordini';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $countByStatus = Order::countByStatus();

        $totalStat = Stat::make('Totali', array_sum($countByStatus))
            ->icon(Heroicon::OutlinedInboxStack)
            ->url(OrderResource::getUrl('index'));

        $statusStats = array_map(
            fn (OrderStatus $status): Stat => Stat::make($status->getLabel(), $countByStatus[$status->value])
                ->icon($status->getIcon())
                ->color($status->getColor())
                ->url(OrderResource::getUrl('index', ['tab' => $status->value])),
            OrderStatus::cases(),
        );

        return [$totalStat, ...$statusStats];
    }
}
