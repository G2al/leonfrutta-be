<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Exceptions\OrderOperationNotAllowedException;
use App\Models\Order;
use App\Services\Orders\OrderService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class CancelOrderAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancelOrder';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Annulla richiesta')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Order $record): bool => $record->isNew())
            ->requiresConfirmation()
            ->modalHeading('Annullare la richiesta?')
            ->modalDescription(fn (Order $record): string => "La richiesta di {$record->full_name} ({$record->business_name}) passerà allo stato \"Annullato\". "
                .'L\'operazione non può essere annullata e il cliente non verrà avvisato automaticamente.')
            ->modalSubmitActionLabel('Annulla richiesta')
            ->modalCancelActionLabel('Indietro')
            ->action(function (Order $record, OrderService $orderService): void {
                try {
                    $orderService->cancel($record);
                } catch (OrderOperationNotAllowedException $exception) {
                    Notification::make()
                        ->title('Operazione non consentita')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Richiesta annullata')
                    ->success()
                    ->send();
            });
    }
}
