<?php

namespace App\Filament\Resources\Products;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Filament\Support\PageSummary;
use App\Models\Product;
use App\Support\Human;
use App\Support\Money;
use Illuminate\Support\HtmlString;

/** Numbers behind the products page summary line. */
final class ProductsSummary
{
    public static function stats(): array
    {
        $pieces = fn () => Product::query()->where('type', ProductType::Unique);
        $available = $pieces()->forSale()->where('stock', '>', 0);
        $soldThisMonth = $pieces()->where('sold_at', '>=', now()->startOfMonth());
        $recentSales = $pieces()->where('sold_at', '>=', now()->subDays(90))->get(['sold_at', 'published_at', 'created_at']);

        return [
            'available_count' => $available->count(),
            'available_value' => (int) $available->sum('price'),
            'sold_month_count' => $soldThisMonth->count(),
            'sold_month_value' => (int) $soldThisMonth->sum('price'),
            'avg_days' => $recentSales->isEmpty() ? null
                : (int) round($recentSales->avg(fn (Product $p) => Human::days($p->published_at ?? $p->created_at, $p->sold_at))),
            'drafts' => Product::query()->where('status', ProductStatus::Draft)->count(),
            'reserved' => $pieces()->forSale()->where('stock', '<=', 0)->count(),
        ];
    }

    public static function html(): HtmlString
    {
        $s = self::stats();

        return PageSummary::make([
            [$s['available_count'].' parça satışta', '('.Money::format($s['available_value']).' değerinde)'],
            [$s['sold_month_count'] ? 'bu ay '.$s['sold_month_count'].' satıldı' : null, Money::format($s['sold_month_value'])],
            [$s['avg_days'] !== null ? 'ortalama '.$s['avg_days'].' günde' : null, 'satılıyor'],
            [$s['drafts'] ? $s['drafts'].' taslak' : null],
            [$s['reserved'] ? $s['reserved'].' ödeme bekliyor' : null],
        ]);
    }
}
