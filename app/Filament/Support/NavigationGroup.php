<?php

namespace App\Filament\Support;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Orders = 'orders';
    case Catalog = 'catalog';
    case Content = 'content';
    case System = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::Orders => 'Satış',
            self::Catalog => 'Katalog',
            self::Content => 'İçerik',
            self::System => 'Sistem',
        };
    }
}
