<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Shop-wide settings editable from the admin panel. Defaults live here so a fresh
 * install works before anyone opens the settings page.
 */
class Settings
{
    public const CACHE_KEY = 'shop-settings';

    public static function defaults(): array
    {
        return [
            'shop_name' => 'Pürüz Atölye',
            'tagline' => 'el yapımı seramik & mozaik atölyesi',
            'contact_email' => 'merhaba@puruzatolye.com',
            'contact_phone' => '+90 530 275 99 94',
            'whatsapp_number' => '905302759994',
            'instagram_handle' => 'puruz.atolye',
            'address' => '',
            'legal_name' => '',
            'tax_office' => '',
            'tax_number' => '',
            'mersis_number' => '',
            'shipping_fee' => 9000,
            'free_shipping_threshold' => 150000,
            'shipping_note' => 'Siparişleriniz 1-3 iş günü içinde özenle paketlenip kargoya verilir.',
            'announcement' => '',
            'announcement_link' => '',
            'hero_title' => 'elde yapılmış, biraz pürüzlü, tamamen gerçek',
            'hero_text' => 'Atölyemizde tek tek şekillenen seramikler ve herkesin katılabileceği mozaik & seramik atölyeleri.',
            'hero_image' => null,
            'store_closed' => false,
            'store_closed_message' => 'Kısa bir moladayız, siparişler yakında yeniden açılacak.',
            'installments_enabled' => false,
            'max_installment' => 6,
            'admin_notification_email' => '',
            'reservation_minutes' => 30,
            'low_stock_threshold' => 2,
        ];
    }

    public static function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Setting::query()->pluck('value', 'key')->all();
        });

        return array_merge(self::defaults(), $stored);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
