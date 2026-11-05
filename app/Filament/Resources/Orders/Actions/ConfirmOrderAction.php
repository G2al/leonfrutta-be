<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Exceptions\OrderOperationNotAllowedException;
use App\Filament\Resources\Orders\Schemas\AmountInput;
use App\Models\Order;
use App\Services\Orders\OrderService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Confirms a new order request with its amount. The customer receives the
 * confirmation automatically on WhatsApp (see SendWhatsAppMessageToCustomer).
 */
class ConfirmOrderAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'confirmOrder';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Conferma ordine')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Order $record): bool => $record->isNew())
            ->requiresConfirmation()
            ->modalHeading('Confermare la richiesta?')
            ->modalDescription(fn (Order $record): string => "La richiesta di {$record->full_name} ({$record->business_name}) passerà allo stato \"Confermato\" "
                .'e il cliente riceverà in automatico la conferma su WhatsApp, con il totale.')
            ->schema([
                AmountInput::make(),
            ])
            ->modalSubmitActionLabel('Conferma ordine')
            ->action(function (array $data, Order $record, OrderService $orderService): void {
                try {
                    $orderService->confirm($record, $data['amount']);
                } catch (OrderOperationNotAllowedException $exception) {
                    Notification::make()
                        ->title('Operazione non consentita')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Richiesta confermata')
                    ->body('Il messaggio di conferma viene inviato al cliente su WhatsApp.')
                    ->success()
                    ->send();
            });
    }
}
