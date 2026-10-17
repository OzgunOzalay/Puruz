<?php

namespace App\Models;

use App\Enums\CargoCompany;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['payment_response', 'tracking_token'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'cargo_company' => CargoCompany::class,
            'requires_shipping' => 'boolean',
            'marketing_consent' => 'boolean',
            'reservation_released' => 'boolean',
            'payment_response' => 'array',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'reserved_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->tracking_token ??= Str::random(40);
        });

        static::created(function (Order $order) {
            if (! $order->number) {
                $order->number = sprintf('PRZ-%s-%06d', now()->format('y'), $order->id);
                $order->saveQuietly();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function scopePaid(Builder $query): void
    {
        $query->whereIn('status', OrderStatus::paidStatuses());
    }

    public function trackingUrl(): string
    {
        return route('orders.track', $this->tracking_token);
    }

    public function cargoTrackingUrl(): ?string
    {
        if (! $this->cargo_company || ! $this->cargo_tracking_number) {
            return null;
        }

        return $this->cargo_company->trackingUrl($this->cargo_tracking_number);
    }

    public function hasWorkshops(): bool
    {
        return $this->items->contains(fn (OrderItem $item) => $item->product_type === ProductType::Workshop->value);
    }

    public function isWorkshopOnly(): bool
    {
        return ! $this->requires_shipping;
    }

    public function firstName(): string
    {
        return Str::before(trim($this->customer_name), ' ');
    }

    public function refundableTotal(): int
    {
        return max(0, $this->total - $this->refunded_total);
    }

    /** Address with the street part shortened, for the public tracking page. */
    public function maskedAddress(): ?string
    {
        if (! $this->requires_shipping) {
            return null;
        }

        return trim(Str::limit((string) $this->shipping_address, 12, '…').' '.$this->shipping_district.' / '.$this->shipping_city);
    }

    /** Timeline steps for the tracking page, depending on the kind of order. */
    public function timelineSteps(): array
    {
        return $this->requires_shipping
            ? [OrderStatus::Paid, OrderStatus::Preparing, OrderStatus::Shipped, OrderStatus::Delivered]
            : [OrderStatus::Paid, OrderStatus::Confirmed, OrderStatus::Completed];
    }
}
