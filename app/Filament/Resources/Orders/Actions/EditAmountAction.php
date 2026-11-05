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
 * Corrects the amount of a confirmed order (e.g. after weighing the goods),
 * as long as it has not been paid yet.
 */
class EditAmountAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'editAmount';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Modifica importo')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn (Order $record): bool => $record->hasEditableAmount())
            ->modalHeading('Modifica importo')
            ->modalWidth('md')
            ->fillForm(fn (Order $record): array => ['amount' => $record->amount])
            ->schema([
                AmountInput::make(),
            ])
            ->modalSubmitActionLabel('Salva importo')
            ->action(function (array $data, Order $record, OrderService $orderService): void {
                try {
                    $orderService->updateAmount($record, $data['amount']);
                } catch (OrderOperationNotAllowedException $exception) {
                    Notification::make()
                        ->title('Operazione non consentita')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Importo aggiornato')
                    ->success()
                    ->send();
            });
    }
}
