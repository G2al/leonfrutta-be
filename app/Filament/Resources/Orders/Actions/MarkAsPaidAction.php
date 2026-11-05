<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Exceptions\OrderOperationNotAllowedException;
use App\Models\Order;
use App\Services\Orders\OrderService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class MarkAsPaidAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'markAsPaid';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Segna come pagato')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Order $record): bool => $record->canBeMarkedAsPaid())
            ->requiresConfirmation()
            ->modalHeading('Segnare la richiesta come pagata?')
            ->modalDescription(fn (Order $record): string => sprintf(
                'Registra l\'incasso di %s da %s (%s). Il cliente riceverà in automatico la conferma del pagamento su WhatsApp e l\'importo non sarà più modificabile.',
                Number::currency((float) $record->amount, in: 'EUR', locale: 'it'),
                $record->full_name,
                $record->business_name,
            ))
            ->modalSubmitActionLabel('Segna come pagato')
            ->action(function (Order $record, OrderService $orderService): void {
                try {
                    $orderService->markAsPaid($record);
                } catch (OrderOperationNotAllowedException $exception) {
                    Notification::make()
                        ->title('Operazione non consentita')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Pagamento registrato')
                    ->success()
                    ->send();
            });
    }
}
