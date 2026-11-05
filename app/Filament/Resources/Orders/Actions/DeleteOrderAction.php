<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use Filament\Actions\DeleteAction;

/**
 * Permanently removes an order request, whatever its status.
 */
class DeleteOrderAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Elimina')
            ->modalHeading(fn (Order $record): string => "Eliminare definitivamente la richiesta #{$record->getKey()}?")
            ->modalDescription(fn (Order $record): string => "La richiesta di {$record->full_name} ({$record->business_name}) verrà cancellata per sempre. "
                .'Sparirà dagli elenchi e dagli incassi, e il cliente non potrà più consultarla. L\'operazione non si può annullare.')
            ->modalSubmitActionLabel('Elimina definitivamente')
            ->modalCancelActionLabel('Indietro')
            ->successNotificationTitle('Richiesta eliminata');
    }
}
