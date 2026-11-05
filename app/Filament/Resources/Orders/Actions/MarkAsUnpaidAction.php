<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Exceptions\OrderOperationNotAllowedException;
use App\Models\Order;
use App\Services\Orders\OrderService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Undo for a payment registered by mistake.
 */
class MarkAsUnpaidAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'markAsUnpaid';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Segna come non pagato')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (Order $record): bool => $record->canBeMarkedAsUnpaid())
            ->requiresConfirmation()
            ->modalHeading('Annullare il pagamento registrato?')
            ->modalDescription('Usalo solo se il pagamento è stato segnato per errore. La richiesta tornerà "Da pagare" e l\'importo sarà di nuovo modificabile.')
            ->modalSubmitActionLabel('Segna come non pagato')
            ->modalCancelActionLabel('Indietro')
            ->action(function (Order $record, OrderService $orderService): void {
                try {
                    $orderService->markAsUnpaid($record);
                } catch (OrderOperationNotAllowedException $exception) {
                    Notification::make()
                        ->title('Operazione non consentita')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Pagamento rimosso')
                    ->success()
                    ->send();
            });
    }
}
