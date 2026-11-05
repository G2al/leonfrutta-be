<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Actions\CancelOrderAction;
use App\Filament\Resources\Orders\Actions\ConfirmOrderAction;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Requests still waiting for the administrator, oldest first,
 * so they can be handled straight from the dashboard.
 */
class NewOrdersTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Richieste da gestire')
            ->query(Order::query()->where('status', OrderStatus::New))
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Ricevuta')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i'),
                TextColumn::make('full_name')
                    ->label('Cliente'),
                TextColumn::make('business_name')
                    ->label('Attività')
                    ->wrap(),
                TextColumn::make('message')
                    ->label('Richiesta')
                    ->limit(80)
                    ->wrap(),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ConfirmOrderAction::make(),
                CancelOrderAction::make(),
            ])
            ->emptyStateHeading('Nessuna richiesta da gestire')
            ->emptyStateDescription('Tutte le richieste sono state confermate o annullate.')
            ->emptyStateIcon(OrderStatus::Confirmed->getIcon());
    }
}
