<?php

namespace App\Filament\Support;

use App\Support\Money;
use Filament\Forms\Components\TextInput;

/** Price field edited in lira (e.g. 650 or 649,90) and stored as kuruş. */
class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->suffix('₺')
            ->inputMode('decimal')
            ->rule('regex:/^\d{1,7}([.,]\d{1,2})?$/')
            ->formatStateUsing(fn ($state) => $state === null ? null : rtrim(rtrim(number_format($state / 100, 2, ',', ''), '0'), ','))
            ->dehydrateStateUsing(fn ($state) => Money::parse($state));
    }
}
