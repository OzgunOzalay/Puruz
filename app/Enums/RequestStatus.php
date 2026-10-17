<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RequestStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Yeni',
            self::Contacted => 'İletişime geçildi',
            self::InProgress => 'Yapılıyor',
            self::Done => 'Tamamlandı',
            self::Cancelled => 'İptal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Contacted, self::InProgress => 'success',
            self::Done, self::Cancelled => 'gray',
        };
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::Contacted, self::InProgress];
    }
}
