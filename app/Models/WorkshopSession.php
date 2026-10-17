<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkshopSession extends Model
{
    protected $fillable = [
        'product_id', 'label', 'starts_at', 'ends_at', 'schedule_note', 'capacity', 'seats_reserved', 'price', 'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_visible' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>', now())->orderBy('starts_at');
    }

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->seats_reserved);
    }

    public function isBookable(): bool
    {
        return $this->is_visible && $this->starts_at->isFuture() && $this->seatsLeft() > 0;
    }

    public function effectivePrice(): int
    {
        return $this->price ?? $this->product->price;
    }

    /** e.g. "Salı, 4 Kasım 2026 · 11:00–14:00" */
    public function displayLabel(): string
    {
        $time = $this->starts_at->translatedFormat('l, j F Y · H:i').'–'.$this->ends_at->format('H:i');

        return $this->label ? $this->label.' ('.$time.')' : $time;
    }
}
