<?php

namespace App\Filament\Resources\Products;

use App\Enums\PieceState;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\WorkshopSession;
use App\Support\Human;
use App\Support\Settings;
use Illuminate\Support\HtmlString;

/** One readable phrase for where a product stands, e.g. "Satışta · 12 gündür". */
final class ProductStatusText
{
    /** @return array{0: string, 1: string} colour and phrase */
    public static function for(Product $product): array
    {
        // A sale is the most important fact about a piece, even when it was hidden afterwards.
        if ($product->pieceState() === PieceState::Sold) {
            return ['gray', 'Satıldı · '.Human::day($product->sold_at).', '.Human::took($product->published_at ?? $product->created_at, $product->sold_at)];
        }

        if ($product->status === ProductStatus::Draft) {
            return ['gray', 'Taslak'];
        }

        if ($product->status === ProductStatus::Archived) {
            return ['gray', 'Arşiv'];
        }

        if ($product->published_at?->isFuture()) {
            return ['gray', 'Yayına girecek · '.Human::day($product->published_at)];
        }

        if ($product->isWorkshop()) {
            return self::workshop($product);
        }

        $listedAt = $product->published_at ?? $product->created_at;

        return match ($product->pieceState()) {
            PieceState::Reserved => ['warning', 'Ödeme bekleniyor'],
            PieceState::Available => ['success', 'Satışta · '.Human::since($listedAt)],
            null => self::stock($product),
        };
    }

    public static function html(Product $product): HtmlString
    {
        [$color, $text] = self::for($product);
        $dot = match ($color) {
            'success' => 'bg-success-500',
            'warning' => 'bg-warning-500',
            'danger' => 'bg-danger-500',
            default => 'bg-gray-400',
        };

        return new HtmlString('<span class="inline-flex items-center gap-2"><span class="size-2 shrink-0 rounded-full '.$dot.'"></span>'.e($text).'</span>');
    }

    /** @return array{0: string, 1: string} */
    private static function workshop(Product $product): array
    {
        $upcoming = $product->sessions->filter(fn (WorkshopSession $s) => $s->starts_at->isFuture());

        if ($upcoming->isEmpty()) {
            return ['gray', 'Planlanmış seans yok'];
        }

        $seats = $upcoming->sum(fn (WorkshopSession $s) => $s->seatsLeft());

        return [$seats > 0 ? 'success' : 'warning',
            $upcoming->count().' seans · '.$seats.' boş yer · sıradaki '.Human::day($upcoming->first()->starts_at)];
    }

    /** Mass-produced products ("Seri üretim"). @return array{0: string, 1: string} */
    private static function stock(Product $product): array
    {
        if ($product->made_to_order || ! $product->track_stock) {
            return ['gray', $product->made_to_order ? 'Siparişe özel' : 'Stok takipsiz'];
        }

        $stock = $product->variants->isNotEmpty() ? $product->variants->sum('stock') : $product->stock;

        return match (true) {
            $stock <= 0 => ['danger', 'Tükendi'],
            $stock <= (int) Settings::get('low_stock_threshold', 2) => ['warning', 'Stok: '.$stock],
            default => ['success', 'Stok: '.$stock],
        };
    }
}
