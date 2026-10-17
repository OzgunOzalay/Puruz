<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Where a unique piece is in its life: for sale, held by an unpaid order, or sold. */
enum PieceState: string implements HasColor, HasLabel
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Satışta',
            self::Reserved => 'Ödeme bekleniyor',
            self::Sold => 'Satıldı',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Reserved => 'warning',
            self::Sold => 'gray',
        };
    }
}
