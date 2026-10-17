<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value', 'min_total', 'usage_limit', 'used_count', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Coupon $coupon) => $coupon->code = mb_strtoupper(trim($coupon->code)));
    }

    /** Returns a Turkish error message, or null when the coupon can be used for this subtotal. */
    public function validationError(int $subtotal): ?string
    {
        return match (true) {
            ! $this->is_active => 'Bu kupon kodu aktif değil.',
            $this->starts_at && $this->starts_at->isFuture() => 'Bu kupon henüz geçerli değil.',
            $this->ends_at && $this->ends_at->isPast() => 'Bu kuponun süresi dolmuş.',
            $this->usage_limit !== null && $this->used_count >= $this->usage_limit => 'Bu kuponun kullanım limiti dolmuş.',
            $this->min_total !== null && $subtotal < $this->min_total => 'Bu kupon için sepet tutarı yetersiz.',
            default => null,
        };
    }

    public function discountFor(int $subtotal): int
    {
        $discount = $this->type === CouponType::Percent
            ? intdiv($subtotal * min(100, $this->value), 100)
            : $this->value;

        return min($subtotal, $discount);
    }
}
