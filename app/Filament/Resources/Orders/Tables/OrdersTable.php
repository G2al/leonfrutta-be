<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\Actions\CancelOrderAction;
use App\Filament\Resources\Orders\Actions\ConfirmOrderAction;
use App\Filament\Resources\Orders\Actions\DeleteOrderAction;
use App\Filament\Resources\Orders\Actions\EditAmountAction;
use App\Filament\Resources\Orders\Actions\MarkAsPaidAction;
use App\Filament\Resources\Orders\Actions\MarkAsUnpaidAction;
use App\Filament\Resources\Orders\Actions\OpenWhatsAppAction;
use App\Models\Order;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Ricevuta il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Cliente')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('business_name')
                    ->label('Attività')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Importo')
                    ->money('EUR', locale: 'it')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label('Pagamento')
                    ->badge()
                    ->state(fn (Order $record): ?PaymentStatus => $record->isConfirmed() ? $record->payment_status : null)
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(OrderStatus::class),
                TernaryFilter::make('paid')
                    ->label('Pagamento')
                    ->placeholder('Tutte')
                    ->trueLabel('Pagate')
                    ->falseLabel('Confermate da pagare')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('paid_at'),
                        false: fn (Builder $query): Builder => $query->where('status', OrderStatus::Confirmed)->whereNull('paid_at'),
                    ),
                self::receivedBetweenFilter(),
            ])
            ->recordActions([
                ViewAction::make()->label('Dettagli'),
                ConfirmOrderAction::make(),
                OpenWhatsAppAction::make(),
                ActionGroup::make([
                    MarkAsPaidAction::make(),
                    EditAmountAction::make(),
                    MarkAsUnpaidAction::make(),
                    CancelOrderAction::make(),
                    DeleteOrderAction::make(),
                ]),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->label('Elimina selezionate')
                    ->modalHeading('Eliminare definitivamente le richieste selezionate?')
                    ->modalDescription('Le richieste verranno cancellate per sempre, sparendo dagli elenchi e dagli incassi. L\'operazione non si può annullare.')
                    ->modalSubmitActionLabel('Elimina definitivamente')
                    ->modalCancelActionLabel('Indietro'),
            ])
            ->emptyStateHeading('Nessuna richiesta')
            ->emptyStateDescription('Le richieste inviate dai clienti compariranno qui.');
    }

    /**
     * Dates are picked in the panel timezone and compared with the UTC values stored in the database.
     */
    private static function receivedBetweenFilter(): Filter
    {
        return Filter::make('received_between')
            ->label('Data di ricezione')
            ->schema([
                DatePicker::make('from')->label('Ricevute dal'),
                DatePicker::make('until')->label('Ricevute fino al'),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where(
                    'created_at', '>=', Carbon::parse($from, FilamentTimezone::get())->startOfDay()->utc(),
                ))
                ->when($data['until'] ?? null, fn (Builder $query, string $until): Builder => $query->where(
                    'created_at', '<=', Carbon::parse($until, FilamentTimezone::get())->endOfDay()->utc(),
                )))
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if ($data['from'] ?? null) {
                    $indicators[] = Indicator::make('Dal '.Carbon::parse($data['from'])->format('d/m/Y'))->removeField('from');
                }

                if ($data['until'] ?? null) {
                    $indicators[] = Indicator::make('Fino al '.Carbon::parse($data['until'])->format('d/m/Y'))->removeField('until');
                }

                return $indicators;
            });
    }
}
