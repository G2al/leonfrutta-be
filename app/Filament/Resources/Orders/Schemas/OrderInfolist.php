<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class OrderInfolist
{
    private const DATE_TIME_FORMAT = 'd/m/Y H:i';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::customerSection(),
                self::paymentSection(),
                self::requestSection(),
            ]);
    }

    private static function paymentSection(): Section
    {
        return Section::make('Importo e pagamento')
            ->icon(Heroicon::OutlinedCurrencyEuro)
            ->columns(['default' => 1, 'sm' => 3])
            ->visible(fn (Order $record): bool => $record->isConfirmed())
            ->schema([
                TextEntry::make('amount')
                    ->label('Importo')
                    ->money('EUR', locale: 'it')
                    ->size(TextSize::Large)
                    ->weight(FontWeight::Bold)
                    ->placeholder('Non indicato'),
                TextEntry::make('payment_status')
                    ->label('Pagamento')
                    ->badge(),
                TextEntry::make('paid_at')
                    ->label('Pagato il')
                    ->dateTime(self::DATE_TIME_FORMAT)
                    ->visible(fn (Order $record): bool => $record->isPaid()),
            ]);
    }

    private static function customerSection(): Section
    {
        return Section::make('Informazioni cliente')
            ->icon(Heroicon::OutlinedUser)
            ->columns(['default' => 1, 'sm' => 2])
            ->schema([
                TextEntry::make('first_name')
                    ->label('Nome'),
                TextEntry::make('last_name')
                    ->label('Cognome'),
                TextEntry::make('business_name')
                    ->label('Attività commerciale'),
                TextEntry::make('phone')
                    ->label('Telefono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->url(fn (Order $record): string => "tel:{$record->phone}")
                    ->copyable()
                    ->copyMessage('Numero copiato'),
            ]);
    }

    private static function requestSection(): Section
    {
        return Section::make('Dettagli richiesta')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
            ->schema([
                TextEntry::make('status')
                    ->label('Stato')
                    ->badge(),
                TextEntry::make('created_at')
                    ->label('Ricevuta il')
                    ->dateTime(self::DATE_TIME_FORMAT),
                TextEntry::make('confirmed_at')
                    ->label('Confermata il')
                    ->dateTime(self::DATE_TIME_FORMAT)
                    ->visible(fn (Order $record): bool => $record->confirmed_at !== null),
                TextEntry::make('cancelled_at')
                    ->label('Annullata il')
                    ->dateTime(self::DATE_TIME_FORMAT)
                    ->visible(fn (Order $record): bool => $record->cancelled_at !== null),
                // Plain escaped text: line breaks are kept through CSS, no HTML from the customer is rendered.
                TextEntry::make('message')
                    ->label('Testo della richiesta')
                    ->extraAttributes(['style' => 'white-space: pre-line; overflow-wrap: anywhere;'])
                    ->columnSpanFull(),
            ]);
    }
}
