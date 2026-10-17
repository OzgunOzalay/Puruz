<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasLabel
{
    case Unique = 'unique';
    case Physical = 'physical';
    case Workshop = 'workshop';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unique => 'Tek parça',
            self::Physical => 'Seri üretim',
            self::Workshop => 'Atölye',
        };
    }

    /** Types offered when creating a product. */
    public static function creatable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type !== self::Physical || config('shop.mass_production')));
    }

    /** Types that are shipped goods rather than bookings. */
    public static function goods(): array
    {
        return [self::Unique, self::Physical];
    }
}
