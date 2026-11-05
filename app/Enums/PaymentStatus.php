<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum PaymentStatus: string implements HasColor, HasIcon, HasLabel
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'Da pagare',
            self::Paid => 'Pagato',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'gray',
            self::Paid => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Unpaid => Heroicon::OutlinedClock,
            self::Paid => Heroicon::OutlinedBanknotes,
        };
    }
}
