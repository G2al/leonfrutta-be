<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * One tab per status, with live counters, so new requests stand out.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $countByStatus = Order::countByStatus();

        $statusTabs = collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status): array => [
                $status->value => Tab::make($status->getLabel())
                    ->icon($status->getIcon())
                    ->badge($countByStatus[$status->value])
                    ->badgeColor($status->getColor())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status)),
            ]);

        return [
            'all' => Tab::make('Tutte')->badge(array_sum($countByStatus)),
            ...$statusTabs->all(),
        ];
    }
}
