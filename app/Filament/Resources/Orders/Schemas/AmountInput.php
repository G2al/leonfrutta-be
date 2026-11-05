<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Services\Orders\OrderService;
use Filament\Forms\Components\TextInput;

/**
 * The order amount field, shared by the confirm and edit-amount actions.
 */
class AmountInput
{
    public static function make(): TextInput
    {
        return TextInput::make('amount')
            ->label('Importo ordine')
            ->helperText('Totale in euro dell\'ordine. Potrai correggerlo finché non risulta pagato.')
            ->prefix('€')
            ->numeric()
            ->inputMode('decimal')
            ->step(0.01)
            ->minValue(0.01)
            ->maxValue(OrderService::MAX_AMOUNT)
            ->rule('decimal:0,2')
            ->required()
            ->autofocus();
    }
}
