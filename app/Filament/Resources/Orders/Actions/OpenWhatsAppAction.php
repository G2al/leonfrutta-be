<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Services\WhatsApp\OrderWhatsAppLink;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Reopens the WhatsApp chat with the customer of a confirmed order.
 * It is a plain link: the order status is never touched.
 */
class OpenWhatsAppAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'openWhatsApp';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Apri WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->url(fn (Order $record, OrderWhatsAppLink $whatsAppLink): ?string => $whatsAppLink->chatUrl($record))
            ->openUrlInNewTab()
            ->visible(fn (Order $record, OrderWhatsAppLink $whatsAppLink): bool => $record->isConfirmed()
                && $whatsAppLink->chatUrl($record) !== null);
    }
}
