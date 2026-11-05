<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Exceptions\OrderOperationNotAllowedException;
use App\Filament\Resources\Orders\Schemas\AmountInput;
use App\Models\Order;
use App\Services\Orders\OrderService;
use App\Services\WhatsApp\OrderWhatsAppLink;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;
use Livewire\Component;

/**
 * Confirms a new order request and opens WhatsApp with a prefilled message.
 *
 * Browsers may block a tab opened after an asynchronous request, so the
 * success notification always carries a persistent "Apri WhatsApp" button:
 * the status is saved either way and the chat is one click away.
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
            ->label('Conferma e apri WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->visible(fn (Order $record): bool => $record->isNew())
            ->requiresConfirmation()
            ->modalHeading('Confermare la richiesta?')
            ->modalDescription(fn (Order $record): string => "La richiesta di {$record->full_name} ({$record->business_name}) passerà allo stato \"Confermato\". "
                .'Subito dopo si aprirà WhatsApp con un messaggio precompilato, comprensivo del totale, da controllare e inviare.')
            ->schema([
                AmountInput::make(),
            ])
            ->modalSubmitActionLabel('Conferma e apri WhatsApp')
            ->action(function (array $data, Order $record, OrderService $orderService, OrderWhatsAppLink $whatsAppLink, Component $livewire): void {
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

                $whatsAppUrl = $whatsAppLink->confirmationUrl($record);

                if ($whatsAppUrl === null) {
                    Notification::make()
                        ->title('Richiesta confermata')
                        ->body("Il numero {$record->phone} non è valido per WhatsApp: contatta il cliente in un altro modo.")
                        ->warning()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Richiesta confermata')
                    ->body('Se WhatsApp non si è aperto automaticamente, usa il pulsante qui sotto.')
                    ->success()
                    ->persistent()
                    ->actions([
                        Action::make('openWhatsApp')
                            ->label('Apri WhatsApp')
                            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                            ->color('success')
                            ->button()
                            ->url($whatsAppUrl, shouldOpenInNewTab: true),
                    ])
                    ->send();

                $livewire->js('window.open('.Js::from($whatsAppUrl).', "_blank", "noopener")');
            });
    }
}
