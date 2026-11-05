<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\Actions\CancelOrderAction;
use App\Filament\Resources\Orders\Actions\ConfirmOrderAction;
use App\Filament\Resources\Orders\Actions\DeleteOrderAction;
use App\Filament\Resources\Orders\Actions\EditAmountAction;
use App\Filament\Resources\Orders\Actions\MarkAsPaidAction;
use App\Filament\Resources\Orders\Actions\MarkAsUnpaidAction;
use App\Filament\Resources\Orders\Actions\OpenWhatsAppAction;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConfirmOrderAction::make(),
            MarkAsPaidAction::make(),
            OpenWhatsAppAction::make(),
            EditAmountAction::make(),
            MarkAsUnpaidAction::make(),
            CancelOrderAction::make(),
            DeleteOrderAction::make(),
        ];
    }
}
