<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Order requests arrive from the public API: the administrator never creates
 * or edits them by hand, they only move through the confirm / cancel /
 * payment actions. They can be deleted permanently when no longer needed.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Richieste ordini';

    protected static ?string $modelLabel = 'richiesta ordine';

    protected static ?string $pluralModelLabel = 'richieste ordini';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record ? "Richiesta #{$record->getKey()}" : 'Richiesta';
    }

    /**
     * Number of requests still waiting for the administrator.
     */
    public static function getNavigationBadge(): ?string
    {
        $newOrdersCount = Order::countByStatus()[OrderStatus::New->value];

        return $newOrdersCount > 0 ? (string) $newOrdersCount : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return OrderStatus::New->getColor();
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return 'Richieste da gestire';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
