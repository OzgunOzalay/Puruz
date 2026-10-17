<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasLabel
{
    case Physical = 'physical';
    case Workshop = 'workshop';

    public function getLabel(): string
    {
        return match ($this) {
            self::Physical => 'Ürün',
            self::Workshop => 'Atölye',
        };
    }
}
