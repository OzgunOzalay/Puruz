<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CargoCompany: string implements HasLabel
{
    case Yurtici = 'yurtici';
    case Aras = 'aras';
    case Mng = 'mng';
    case Ptt = 'ptt';
    case Surat = 'surat';
    case HepsiJet = 'hepsijet';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Yurtici => 'Yurtiçi Kargo',
            self::Aras => 'Aras Kargo',
            self::Mng => 'MNG Kargo',
            self::Ptt => 'PTT Kargo',
            self::Surat => 'Sürat Kargo',
            self::HepsiJet => 'HepsiJet',
            self::Other => 'Diğer',
        };
    }

    /**
     * Public tracking page for a shipment. Carriers change these URLs from time to time,
     * so check them once before going live.
     */
    public function trackingUrl(string $number): ?string
    {
        $number = rawurlencode(trim($number));

        return match ($this) {
            self::Yurtici => "https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code={$number}",
            self::Aras => "https://kargotakip.araskargo.com.tr/mainpage.aspx?code={$number}",
            self::Mng => "https://kargotakip.mngkargo.com.tr/?takipNo={$number}",
            self::Ptt => "https://gonderitakip.ptt.gov.tr/Track/Verify?q={$number}",
            self::Surat => "https://suratkargo.com.tr/KargoTakip/?kargotakipno={$number}",
            self::HepsiJet => "https://www.hepsijet.com/gonderi-takibi/{$number}",
            self::Other => null,
        };
    }
}
