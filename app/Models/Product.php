<?php

namespace App\Models;

use App\Enums\PieceState;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Support\Money;
use App\Support\ProductImages;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type', 'name', 'slug', 'sku', 'short_description', 'description', 'images',
        'price', 'compare_at_price', 'track_stock', 'stock', 'made_to_order', 'lead_time_days',
        'materials', 'dimensions', 'care', 'weight_grams',
        'status', 'visibility', 'is_featured', 'sort_order', 'published_at',
        'seo_title', 'seo_description', 'og_image', 'instagram_url',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'visibility' => ProductVisibility::class,
            'images' => 'array',
            'track_stock' => 'boolean',
            'made_to_order' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'sold_at' => 'datetime',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(WorkshopSession::class)->orderBy('starts_at');
    }

    public function upcomingSessions(): HasMany
    {
        return $this->sessions()->where('is_visible', true)->where('starts_at', '>', now());
    }

    /** Reachable by URL: published, not hidden, publish date reached. Includes unlisted products. */
    public function scopeReachable(Builder $query): void
    {
        $query->where('status', ProductStatus::Published)
            ->where('visibility', '!=', ProductVisibility::Hidden)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /** Shown in listings, search and the sitemap. */
    public function scopeListed(Builder $query): void
    {
        $query->reachable()->where('visibility', ProductVisibility::Public);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->latest('id');
    }

    public function scopeGoods(Builder $query): void
    {
        $query->whereIn('type', ProductType::goods());
    }

    /** Published goods that have not been sold (reserved pieces included). */
    public function scopeForSale(Builder $query): void
    {
        $query->goods()->where('status', ProductStatus::Published)->whereNull('sold_at');
    }

    public function scopeAvailableFirst(Builder $query): void
    {
        $query->orderByRaw('CASE WHEN sold_at IS NULL THEN 0 ELSE 1 END');
    }

    public function isWorkshop(): bool
    {
        return $this->type === ProductType::Workshop;
    }

    public function isUnique(): bool
    {
        return $this->type === ProductType::Unique;
    }

    /** Null for workshops and mass-produced products. */
    public function pieceState(): ?PieceState
    {
        if (! $this->isUnique()) {
            return null;
        }

        return match (true) {
            $this->sold_at !== null => PieceState::Sold,
            $this->stock <= 0 => PieceState::Reserved,
            default => PieceState::Available,
        };
    }

    /** Next free automatic piece code: PRZ-0001, PRZ-0002, … */
    public static function nextSku(): string
    {
        $last = static::withTrashed()->where('sku', 'like', 'PRZ-%')->pluck('sku')
            ->map(fn (string $sku) => (int) substr($sku, 4))
            ->max() ?? 0;

        return 'PRZ-'.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    public function isReachable(): bool
    {
        return $this->status === ProductStatus::Published
            && $this->visibility !== ProductVisibility::Hidden
            && ($this->published_at === null || $this->published_at->isPast());
    }

    public function hasVariants(): bool
    {
        return $this->variants->where('is_active', true)->isNotEmpty();
    }

    /** Units that can be sold right now, null meaning unlimited. */
    public function availableStock(?ProductVariant $variant = null): ?int
    {
        if ($this->isWorkshop()) {
            return null;
        }

        if ($variant) {
            return $this->track_stock ? max(0, $variant->stock) : null;
        }

        if (! $this->track_stock || $this->made_to_order) {
            return null;
        }

        return max(0, $this->stock);
    }

    public function isInStock(): bool
    {
        if ($this->isWorkshop()) {
            return $this->upcomingSessions->contains(fn (WorkshopSession $s) => $s->isBookable());
        }

        if ($this->hasVariants()) {
            return ! $this->track_stock || $this->variants->where('is_active', true)->contains(fn ($v) => $v->stock > 0);
        }

        $stock = $this->availableStock();

        return $stock === null || $stock > 0;
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price;
    }

    public function formattedPrice(): string
    {
        return Money::format($this->price);
    }

    public function coverImage(): ?string
    {
        return $this->images[0] ?? null;
    }

    public function coverUrl(int $width = 800): ?string
    {
        return ProductImages::url($this->coverImage(), $width);
    }

    public function ogImageUrl(): ?string
    {
        return ProductImages::url($this->og_image ?: $this->coverImage(), 1600);
    }

    public function url(array $query = []): string
    {
        return route('shop.product', ['slug' => $this->slug] + $query);
    }

    public function shortUrl(): string
    {
        return route('shop.short', $this->id);
    }

    /** Link with UTM tags for a given Instagram placement (bio, story, post, dm). */
    public function instagramLink(string $placement = 'story'): string
    {
        return $this->url([
            'utm_source' => 'instagram',
            'utm_medium' => $placement,
            'utm_campaign' => $this->slug,
        ]);
    }

    /** Temporary link that lets the admin preview a draft or hidden product. */
    public function previewUrl(): string
    {
        return URL::temporarySignedRoute('shop.product.preview', now()->addDays(3), ['product' => $this->slug]);
    }

    /** "Benzerini iste": WhatsApp with a prefilled message, or Instagram DM when no number is set. */
    public function similarRequestUrl(): ?string
    {
        $text = 'Merhaba, "'.$this->name.'" gibi bir parça yaptırmak istiyorum: '.$this->url();

        if (filled($whatsapp = Settings::get('whatsapp_number'))) {
            return 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($text);
        }

        return filled($instagram = Settings::get('instagram_handle')) ? 'https://ig.me/m/'.$instagram : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
