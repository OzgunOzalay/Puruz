<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum ProductVisibility: string implements HasColor, HasDescription, HasLabel
{
    case Public = 'public';
    case Unlisted = 'unlisted';
    case Hidden = 'hidden';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Herkese açık',
            self::Unlisted => 'Sadece link ile',
            self::Hidden => 'Gizli',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Public => 'Mağazada listelenir ve aramada çıkar.',
            self::Unlisted => 'Listelenmez; yalnızca Instagram vb. paylaşılan link ile açılır.',
            self::Hidden => 'Hiçbir şekilde müşterilere gösterilmez.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Unlisted => 'info',
            self::Hidden => 'gray',
        };
    }
}
