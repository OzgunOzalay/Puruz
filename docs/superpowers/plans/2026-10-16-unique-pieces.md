# Unique Pieces & Minimal Admin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Model every physical product as a one-of-a-kind piece (available / reserved / sold) across shop and admin, and move the admin to a minimal, data-first style starting with the products page.

**Architecture:** A new `ProductType::Unique` rides on the existing stock logic: a unique piece has stock 1 (unsold) or 0 (reserved or sold), so cart, checkout, `ReservationService` and refunds keep working. A new `products.sold_at` column, set on payment and cleared on restocking refunds, distinguishes sold from reserved. The admin shows a derived, human-readable state instead of stock numbers; shared helpers (`Human`, `PageSummary`) carry the new style to later pages.

**Tech Stack:** Laravel 13 · PHP 8.3 · Filament 5 · Livewire 4 · Blade/Alpine/Tailwind 4 · PHPUnit · SQLite (tests) / MariaDB (Docker).

**Spec:** `docs/superpowers/specs/2026-10-16-unique-pieces-design.md`

## Global Constraints

- Money is integer kuruş everywhere; format only with `App\Support\Money::format()` (e.g. `1.850 ₺`).
- All user-facing copy is Turkish, as written in this plan; no raw enum values (`published`, `unlisted`) in the UI.
- `config('shop.mass_production')` defaults to `false`; `ProductType::Physical` ("Seri üretim") is offered on create only when it is `true`. Existing Physical products keep working either way.
- Unique piece invariants (enforced in `ProductObserver`, not only the form): `track_stock = true`, `made_to_order = false`, `lead_time_days = null`, stock ∈ {0, 1}, stock 0 whenever `sold_at` is set.
- Workshops, checkout/payment flow, reservation timing and order emails are unchanged apart from the wording named in Task 2.
- Admin tables: no `SelectColumn`/`ToggleColumn`/`TextInputColumn` in product rows; state is a coloured dot + phrase (green good, amber waiting, red problem, grey neutral).
- Dates in admin: `bugün`, `dün`, `3 Kas`, `3 Kas 2025`; durations `12 gündür`, `18 günde`, `aynı gün`, `bugünden beri`.
- Run tests with `php artisan test` (PHP 8.3 is installed locally); style with `vendor/bin/pint --test`. All 59 existing tests must keep passing, except the one product-creation test Task 6 deliberately updates.
- Commit after each task with a message ending in `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. A late payment for a piece someone else bought meanwhile is voided — that piece must keep the *other* order's `sold_at`, and the voided order must not mark anything sold. (Test in Task 2.)
2. A partial refund of a sold piece never restocks, so the piece stays "Satıldı". (Test in Task 2.)
3. Existing "Seri üretim" products created before this change must stay editable with their stock field and variants panel while `shop.mass_production` is off. (Test in Task 6.)
4. A piece listed or sold today reads "Satışta · bugünden beri" / "Satıldı · bugün, aynı gün", never "0 gündür"; a future publish date reads "Yayına girecek · 5 Kas", never a negative duration. (Tests in Task 4 and Task 5.)
5. "Kopyala" on a sold piece produces an *available* draft (stock 1, no `sold_at`, new code), not a sold copy. (Test in Task 5.)

---

## File Structure

| File | Responsibility |
| --- | --- |
| `config/shop.php` (new) | `mass_production` flag |
| `app/Enums/ProductType.php` | adds `Unique`, `creatable()`, `goods()` |
| `app/Enums/PieceState.php` (new) | `Available` / `Reserved` / `Sold` with label + colour |
| `database/migrations/2026_10_16_000100_add_sold_at_to_products.php` (new) | `products.sold_at` |
| `app/Models/Product.php` | `isUnique()`, `pieceState()`, `pieceOrder()`, `similarRequestUrl()`, `nextSku()`, scopes `goods`, `forSale`, `availableFirst` |
| `app/Observers/ProductObserver.php` | unique invariants + SKU auto-fill (`creating`/`saving`) |
| `database/factories/ProductFactory.php` | `unique()`, `sold()` states |
| `app/Services/Cart/CartService.php` | quantity 1 for unique pieces, "satıldı" wording |
| `app/Services/Checkout/CheckoutService.php` | "satıldı" wording on reserve failure |
| `app/Services/Orders/ReservationService.php` | `markSold()`, clear `sold_at` on restore |
| `app/Services/Payment/PaymentService.php` | call `markSold()` after a successful payment |
| `app/Http/Controllers/ShopController.php` | goods scope on home, available-first listings |
| `resources/views/shop/product.blade.php`, `components/shop/product-card.blade.php`, `shop/index.blade.php` | sold/reserved UI, "Benzerini iste" |
| `app/Support/Human.php` (new) | relative dates and durations |
| `app/Filament/Support/PageSummary.php` (new) | one-line summary HTML for list pages |
| `app/Filament/Resources/Products/ProductStatusText.php` (new) | `[colour, phrase]` for any product |
| `app/Filament/Resources/Products/ProductsSummary.php` (new) | numbers + parts for the products summary line |
| `app/Filament/Resources/Products/Tables/ProductsTable.php` | rewritten table |
| `app/Filament/Resources/Products/Pages/ListProducts.php` | tabs, default tab, subheading |
| `app/Filament/Resources/Products/Schemas/ProductForm.php` | rewritten as one scrolling page |
| `resources/views/filament/product-state.blade.php` (new) | Durum card body |
| `app/Filament/Resources/Products/RelationManagers/VariantsRelationManager.php` | only for Physical |
| `resources/css/filament/admin/theme.css` | panel-wide minimal rules |
| `app/Filament/Widgets/SalesOverview.php` | "Satışta" card replaces "Az stok" |
| `database/seeders/DemoSeeder.php` | unique demo pieces |
| Tests: `tests/Feature/UniquePieceTest.php`, `UniquePieceLifecycleTest.php`, `UniqueShopTest.php`, `HumanTest.php`, `AdminProductsTest.php`; edits to `AdminPanelTest.php` | |

---

### Task 1: Unique type, piece state and model rules

**Files:**
- Create: `config/shop.php`, `app/Enums/PieceState.php`, `database/migrations/2026_10_16_000100_add_sold_at_to_products.php`, `tests/Feature/UniquePieceTest.php`
- Modify: `app/Enums/ProductType.php`, `app/Models/Product.php`, `app/Observers/ProductObserver.php`, `database/factories/ProductFactory.php`

**Interfaces:**
- Produces: `ProductType::Unique`, `ProductType::creatable(): array<ProductType>`, `ProductType::goods(): array<ProductType>`; `PieceState::{Available,Reserved,Sold}` (implements `HasLabel`, `HasColor`); `Product::isUnique(): bool`, `Product::pieceState(): ?PieceState`, `Product::nextSku(): string` (static), scopes `goods()`, `forSale()`, `availableFirst()`; cast `sold_at` → datetime; factory states `unique()`, `sold()`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/UniquePieceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\PieceState;
use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniquePieceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_piece_is_saved_with_one_in_stock_and_no_made_to_order(): void
    {
        $piece = Product::factory()->unique()->create(['stock' => 5, 'track_stock' => false, 'made_to_order' => true, 'lead_time_days' => 7]);

        $piece->refresh();
        $this->assertSame(1, $piece->stock);
        $this->assertTrue($piece->track_stock);
        $this->assertFalse($piece->made_to_order);
        $this->assertNull($piece->lead_time_days);
        $this->assertSame(PieceState::Available, $piece->pieceState());

        $piece->update(['stock' => 3]);
        $this->assertSame(1, $piece->fresh()->stock);
    }

    public function test_piece_state_follows_stock_and_sold_date(): void
    {
        $sold = Product::factory()->unique()->sold()->create();
        $reserved = Product::factory()->unique()->create();
        Product::query()->whereKey($reserved->id)->decrement('stock');

        $this->assertSame(0, $sold->fresh()->stock);
        $this->assertSame(PieceState::Sold, $sold->fresh()->pieceState());
        $this->assertSame(PieceState::Reserved, $reserved->fresh()->pieceState());
        $this->assertNull(Product::factory()->create()->pieceState()); // mass-produced: no piece state
    }

    public function test_piece_codes_fill_in_automatically(): void
    {
        $first = Product::factory()->unique()->create(['sku' => null]);
        $second = Product::factory()->unique()->create(['sku' => null]);
        Product::factory()->unique()->create(['sku' => 'PRZ-007']);
        $after = Product::factory()->unique()->create(['sku' => null]);
        $manual = Product::factory()->unique()->create(['sku' => 'KASE-1']);
        $workshop = Product::factory()->workshop()->create(['sku' => null]);

        $this->assertSame('PRZ-0001', $first->sku);
        $this->assertSame('PRZ-0002', $second->sku);
        $this->assertSame('PRZ-0008', $after->sku);
        $this->assertSame('KASE-1', $manual->sku);
        $this->assertNull($workshop->sku);
    }

    public function test_mass_production_type_is_only_offered_when_enabled(): void
    {
        $this->assertSame([ProductType::Unique, ProductType::Workshop], ProductType::creatable());

        config(['shop.mass_production' => true]);
        $this->assertSame([ProductType::Unique, ProductType::Physical, ProductType::Workshop], ProductType::creatable());
    }

    public function test_available_first_puts_sold_pieces_last(): void
    {
        $sold = Product::factory()->unique()->sold()->create(['sort_order' => 0]);
        $available = Product::factory()->unique()->create(['sort_order' => 9]);

        $this->assertSame([$available->id, $sold->id], Product::query()->availableFirst()->ordered()->pluck('id')->all());
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=UniquePieceTest`
Expected: FAIL — `Call to undefined method ... unique()` on the factory.

- [ ] **Step 3: Implement**

`config/shop.php`:

```php
<?php

return [
    // Mass-produced products with stock counts and variants ("Seri üretim"). Off while the
    // workshop only sells unique pieces; existing products of that type keep working.
    'mass_production' => (bool) env('SHOP_MASS_PRODUCTION', false),
];
```

`app/Enums/ProductType.php`:

```php
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
```

`app/Enums/PieceState.php`:

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Where a unique piece is in its life: for sale, held by an unpaid order, or sold. */
enum PieceState: string implements HasColor, HasLabel
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Satışta',
            self::Reserved => 'Ödeme bekleniyor',
            self::Sold => 'Satıldı',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Reserved => 'warning',
            self::Sold => 'gray',
        };
    }
}
```

`database/migrations/2026_10_16_000100_add_sold_at_to_products.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('sold_at')->nullable()->after('stock')->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sold_at');
        });
    }
};
```

`app/Models/Product.php` — add `use App\Enums\PieceState;`; add `'sold_at' => 'datetime',` to `casts()`; add after `isWorkshop()`:

```php
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
```

and next to the other scopes:

```php
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
```

`app/Observers/ProductObserver.php` — add `use App\Enums\ProductType;` and these methods above `saved()`:

```php
    public function creating(Product $product): void
    {
        if ($product->type !== ProductType::Workshop && blank($product->sku)) {
            $product->sku = Product::nextSku();
        }
    }

    /** A unique piece is one item: stock 1 while unsold, 0 while held by an order or sold. */
    public function saving(Product $product): void
    {
        if (! $product->isUnique()) {
            return;
        }

        $product->track_stock = true;
        $product->made_to_order = false;
        $product->lead_time_days = null;
        $product->stock = match (true) {
            $product->sold_at !== null => 0,
            ! $product->exists => 1,
            default => max(0, min(1, (int) $product->stock)),
        };
    }
```

(`$model->decrement()` and query-builder updates used by checkout do not fire `saving`, so reservations are unaffected.)

`database/factories/ProductFactory.php` — add:

```php
    public function unique(): static
    {
        return $this->state(['type' => ProductType::Unique, 'stock' => 1]);
    }

    public function sold(): static
    {
        return $this->state(['sold_at' => now()->subDays(2), 'stock' => 0]);
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=UniquePieceTest` → PASS. Then `php artisan test` → all green.

- [ ] **Step 5: Commit**

```bash
git add config/shop.php app/Enums app/Models/Product.php app/Observers/ProductObserver.php database tests/Feature/UniquePieceTest.php
git commit -m "Add unique piece product type with derived piece state"
```

---

### Task 2: Piece lifecycle through cart, checkout, payment and refunds

**Files:**
- Create: `tests/Feature/UniquePieceLifecycleTest.php`
- Modify: `app/Services/Cart/CartService.php`, `app/Services/Checkout/CheckoutService.php:150-165`, `app/Services/Orders/ReservationService.php`, `app/Services/Payment/PaymentService.php:132-160`

**Interfaces:**
- Consumes: `Product::isUnique()`, `ProductType::Unique`, factory `unique()` (Task 1).
- Produces: `ReservationService::markSold(Order $order): void`; `CartService::SOLD_OUT = 'Tükendi'`, `CartService::PIECE_SOLD = 'Bu parça satıldı'`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/UniquePieceLifecycleTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PieceState;
use App\Models\Order;
use App\Models\Product;
use App\Services\Orders\RefundService;
use App\Services\Payment\FakeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UniquePieceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function placeOrder(Product $product): Order
    {
        $this->post('/sepet', ['product_id' => $product->id])->assertSessionHasNoErrors();
        $this->assertNull(session('error'), (string) session('error'));
        $this->post('/odeme', $this->checkoutData())->assertRedirectContains('/odeme/test/');

        return Order::query()->latest('id')->firstOrFail();
    }

    private function pay(Order $order): void
    {
        FakeGateway::$results[$order->payment_token] = FakeGateway::successFor($order);
        $this->post('/odeme/donus', ['token' => $order->payment_token]);
    }

    public function test_adding_a_piece_twice_keeps_quantity_one(): void
    {
        $piece = Product::factory()->unique()->create();

        $this->post('/sepet', ['product_id' => $piece->id, 'quantity' => 3]);
        $this->post('/sepet', ['product_id' => $piece->id]);

        $this->assertNull(session('error'));
        $this->assertSame([1], array_column(session('cart.items'), 'quantity'));
    }

    public function test_piece_is_reserved_at_checkout_and_sold_when_paid(): void
    {
        $piece = Product::factory()->unique()->create();

        $order = $this->placeOrder($piece);
        $this->assertSame(PieceState::Reserved, $piece->fresh()->pieceState());

        $this->pay($order);
        $this->assertSame(PieceState::Sold, $piece->fresh()->pieceState());
        $this->assertNotNull($piece->fresh()->sold_at);
    }

    public function test_failed_or_expired_payment_makes_the_piece_available_again(): void
    {
        $piece = Product::factory()->unique()->create();

        $order = $this->placeOrder($piece);
        $order->update(['reserved_until' => now()->subMinute()]);
        $this->artisan('shop:release-reservations')->assertSuccessful();

        $this->assertSame(PieceState::Available, $piece->fresh()->pieceState());
    }

    public function test_sold_piece_cannot_be_added_or_checked_out(): void
    {
        $piece = Product::factory()->unique()->create();
        $this->post('/sepet', ['product_id' => $piece->id]);
        $piece->forceFill(['sold_at' => now()])->save();

        $this->post('/odeme', $this->checkoutData())->assertRedirect('/sepet');
        $this->post('/sepet', ['product_id' => $piece->id]);
        $this->assertSame('Üzgünüz, bu parça satıldı.', session('error'));
    }

    public function test_refund_with_restock_makes_the_piece_available_and_without_keeps_it_sold(): void
    {
        $refunds = app(RefundService::class);

        $restocked = Product::factory()->unique()->create();
        $order = $this->placeOrder($restocked);
        $this->pay($order);
        $refunds->refundAll($order->fresh(), 'Vazgeçti', restock: true, notify: false, user: null, ip: '127.0.0.1');
        $this->assertSame(PieceState::Available, $restocked->fresh()->pieceState());

        $broken = Product::factory()->unique()->create();
        $order = $this->placeOrder($broken);
        $this->pay($order);
        $refunds->refundAll($order->fresh(), 'Kırık geldi', restock: false, notify: false, user: null, ip: '127.0.0.1');
        $this->assertSame(PieceState::Sold, $broken->fresh()->pieceState());
    }

    // Review focus 2
    public function test_partial_refund_keeps_the_piece_sold(): void
    {
        $piece = Product::factory()->unique()->create(['price' => 65000]);
        $order = $this->placeOrder($piece);
        $this->pay($order);
        $order = $order->fresh('items');

        app(RefundService::class)->refundPartial($order, $order->items->first(), 10000, 'Küçük çizik', null, '127.0.0.1');

        $this->assertSame(PieceState::Sold, $piece->fresh()->pieceState());
    }

    // Review focus 1
    public function test_late_payment_for_a_piece_sold_meanwhile_is_voided_and_marks_nothing(): void
    {
        $piece = Product::factory()->unique()->create();

        $late = $this->placeOrder($piece);
        $late->update(['reserved_until' => now()->subMinute()]);
        $this->artisan('shop:release-reservations');
        $this->flushSession();

        $winner = $this->placeOrder($piece);
        $this->pay($winner);
        $soldAt = $piece->fresh()->sold_at;

        $this->pay($late);

        $this->assertSame(OrderStatus::Cancelled, $late->fresh()->status);
        $this->assertSame(PieceState::Sold, $piece->fresh()->pieceState());
        $this->assertEquals($soldAt, $piece->fresh()->sold_at);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=UniquePieceLifecycleTest`
Expected: FAIL — quantity error on second add, `sold_at` stays null after payment.

- [ ] **Step 3: Implement**

`app/Services/Cart/CartService.php`:

1. Add constants under `MAX_QUANTITY`:

```php
    public const SOLD_OUT = 'Tükendi';

    public const PIECE_SOLD = 'Bu parça satıldı';
```

2. In `add()`, change the variant branch condition to skip unique pieces:

```php
        } elseif (! $product->isUnique() && $product->variants()->where('is_active', true)->exists()) {
```

3. Replace the `$newQuantity` line and the max-check `throw` with:

```php
        $newQuantity = $product->isUnique() ? 1 : ($items[$key]['quantity'] ?? 0) + $quantity;

        $line = new CartLine($key, $product, $variant, $workshopSession, $newQuantity);
        $max = $line->maxQuantity();
        if ($max !== null && $newQuantity > $max) {
            throw new InvalidArgumentException(match (true) {
                $product->isUnique() => 'Üzgünüz, bu parça satıldı.',
                $max === 0 => 'Üzgünüz, bu ürün tükendi.',
                default => "Bu üründen en fazla {$max} adet alabilirsiniz.",
            });
        }
```

4. In `update()`, clamp unique pieces to 1 — replace the `else` branch:

```php
        } else {
            $isUnique = Product::query()->whereKey($items[$key]['product_id'])->value('type') === ProductType::Unique->value;
            $items[$key]['quantity'] = $isUnique ? 1 : min(self::MAX_QUANTITY, $quantity);
        }
```

(add `use App\Enums\ProductType;`).

5. In `lines()`, replace `$line->problem = 'Tükendi';` with:

```php
                $line->problem = $product->isUnique() ? self::PIECE_SOLD : self::SOLD_OUT;
```

6. Replace `hasSoldOutLines()` body:

```php
        return $this->lines()->contains(fn (CartLine $line) => in_array($line->problem, [self::SOLD_OUT, self::PIECE_SOLD], true));
```

`app/Services/Checkout/CheckoutService.php` — in `reserve()`, replace the stock exception:

```php
        if (! $model || $model->stock < $line->quantity) {
            throw new CheckoutException($line->product->isUnique()
                ? "Üzgünüz, “{$line->product->name}” satıldı."
                : "Üzgünüz, “{$line->product->name}” için yeterli stok kalmadı.");
        }
```

`app/Services/Orders/ReservationService.php` — add `use App\Enums\ProductType;`, add the method after `reacquire()`:

```php
    /** Marks the unique pieces of a paid order as sold. */
    public function markSold(Order $order): void
    {
        $ids = $order->items->where('product_type', ProductType::Unique->value)->pluck('product_id')->filter();

        if ($ids->isNotEmpty()) {
            Product::withTrashed()->whereIn('id', $ids)->whereNull('sold_at')->update(['sold_at' => now()]);
        }
    }
```

and in `restore()` replace the product increment line:

```php
            Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity, ['sold_at' => null]);
```

`app/Services/Payment/PaymentService.php` — in `afterPaid()`, directly after the closing `}` of the `if ($order->reservation_released && ! $this->reservations->reacquire($order)) { … return; }` block, add:

```php
        $this->reservations->markSold($order);
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=UniquePieceLifecycleTest` → PASS; `php artisan test` → all green (CheckoutTest still uses Physical products and must be unaffected).

- [ ] **Step 5: Commit**

```bash
git add app/Services tests/Feature/UniquePieceLifecycleTest.php
git commit -m "Track unique pieces through checkout, payment and refunds"
```

---

### Task 3: Shop — sold and reserved pieces, "Benzerini iste"

**Files:**
- Create: `tests/Feature/UniqueShopTest.php`
- Modify: `app/Models/Product.php`, `app/Http/Controllers/ShopController.php`, `resources/views/shop/product.blade.php`, `resources/views/components/shop/product-card.blade.php`, `resources/views/shop/index.blade.php:24`

**Interfaces:**
- Consumes: `pieceState()`, `PieceState`, scopes `goods()`, `availableFirst()` (Task 1).
- Produces: `Product::similarRequestUrl(): ?string`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/UniqueShopTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniqueShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_piece_has_add_to_cart_and_no_quantity_picker(): void
    {
        $piece = Product::factory()->unique()->create();

        $this->get($piece->url())->assertOk()
            ->assertSee('sepete ekle')
            ->assertDontSee('son 1 adet')
            ->assertDontSee('inputmode="numeric"', false);
    }

    public function test_sold_piece_shows_sold_badge_and_whatsapp_request(): void
    {
        Settings::set(['whatsapp_number' => '905551112233']);
        $piece = Product::factory()->unique()->sold()->create(['name' => 'Kobalt Kupa']);

        $text = rawurlencode('Merhaba, "Kobalt Kupa" gibi bir parça yaptırmak istiyorum: '.$piece->url());

        $this->get($piece->url())->assertOk()
            ->assertSee('satıldı')
            ->assertSee('benzerini iste')
            ->assertSee('https://wa.me/905551112233?text='.$text, false)
            ->assertDontSee('sepete ekle');
    }

    public function test_request_falls_back_to_instagram_without_whatsapp(): void
    {
        Settings::set(['whatsapp_number' => '', 'instagram_handle' => 'puruzatolye']);
        $piece = Product::factory()->unique()->sold()->create();

        $this->assertSame('https://ig.me/m/puruzatolye', $piece->similarRequestUrl());
    }

    public function test_reserved_piece_shows_notice_and_disabled_button(): void
    {
        $piece = Product::factory()->unique()->create();
        Product::query()->whereKey($piece->id)->update(['stock' => 0]);

        $this->get($piece->url())->assertOk()
            ->assertSee('başka birinin sepetinde')
            ->assertDontSee('hemen al');
    }

    public function test_listings_show_available_pieces_before_sold_ones(): void
    {
        $sold = Product::factory()->unique()->sold()->create(['name' => 'Satılmış Vazo', 'sort_order' => 0]);
        $available = Product::factory()->unique()->create(['name' => 'Duran Kase', 'sort_order' => 5]);

        $this->get(route('shop.index'))->assertOk()->assertSeeInOrder(['Duran Kase', 'Satılmış Vazo']);
        $this->get(route('shop.index', ['stokta' => 1]))->assertSee('Duran Kase')->assertDontSee('Satılmış Vazo');
        $this->get(route('shop.home'))->assertOk()->assertSee('Duran Kase');
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=UniqueShopTest`
Expected: FAIL (no "satıldı" badge, quantity input present, unique pieces missing from home).

- [ ] **Step 3: Implement**

`app/Models/Product.php` — add `use App\Support\Settings;` and:

```php
    /** "Benzerini iste": WhatsApp with a prefilled message, or Instagram DM when no number is set. */
    public function similarRequestUrl(): ?string
    {
        $text = 'Merhaba, "'.$this->name.'" gibi bir parça yaptırmak istiyorum: '.$this->url();

        if (filled($whatsapp = Settings::get('whatsapp_number'))) {
            return 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($text);
        }

        return filled($instagram = Settings::get('instagram_handle')) ? 'https://ig.me/m/'.$instagram : null;
    }
```

`app/Http/Controllers/ShopController.php`:
- In `home()`, replace both `->where('type', ProductType::Physical)` with `->goods()`, and add `->availableFirst()` before `->latest(...)`/`->ordered()` on the `$latest` query.
- In `listing()`, add before the `match ($request->query('sirala'))`:

```php
        $query->availableFirst();
```

(The existing `stokta` filter already excludes sold and reserved pieces because their stock is 0.)

`resources/views/shop/index.blade.php:24` — change `sadece stoktakiler` to `sadece satıştakiler`.

`resources/views/components/shop/product-card.blade.php` — replace the `@php` block and the badge `<div>`:

```blade
@php
    $piece = $product->pieceState();
    $inStock = $product->isInStock();
    $dimmed = $piece ? $piece === \App\Enums\PieceState::Sold : ! $inStock;
    $stock = $product->hasVariants() || $product->isUnique() ? null : $product->availableStock();
    $cover = $product->coverImage();
@endphp
```

change the image class condition from `{{ $inStock ? '' : 'opacity-60 grayscale-[40%]' }}` to `{{ $dimmed ? 'opacity-60 grayscale-[40%]' : '' }}`, and the badges to:

```blade
        <div class="absolute left-3 top-3 flex flex-col items-start gap-1.5">
            @if ($product->isWorkshop())
                <span class="badge bg-cobalt text-paper">atölye</span>
            @endif
            @if ($piece === \App\Enums\PieceState::Sold)
                <span class="badge bg-ink text-paper">satıldı</span>
            @elseif (! $piece && ! $inStock)
                <span class="badge bg-ink text-paper">tükendi</span>
            @elseif ($stock === 1)
                <span class="badge bg-tile text-paper">son 1 adet</span>
            @elseif ($product->isOnSale())
                <span class="badge bg-tile text-paper">indirim</span>
            @endif
            @if ($product->made_to_order)
                <span class="badge bg-paper/90 text-ink">siparişe özel</span>
            @endif
        </div>
```

`resources/views/shop/product.blade.php`:

1. In the top `@php` block add:

```php
    $piece = $product->pieceState();
    $isSold = $piece === \App\Enums\PieceState::Sold;
    $isReserved = $piece === \App\Enums\PieceState::Reserved;
```

and change `$stock = $variants->isEmpty() ? $product->availableStock() : null;` to
`$stock = $variants->isEmpty() && ! $product->isUnique() ? $product->availableStock() : null;`

2. JSON-LD availability (line ~48): `'availability' => $inStock ? 'https://schema.org/InStock' : ($isSold ? 'https://schema.org/SoldOut' : 'https://schema.org/OutOfStock'),`

3. Replace the badge block (`@if (! $inStock) … @endif` around line 108) with:

```blade
                    @if ($isSold)
                        <span class="badge bg-ink text-paper">satıldı</span>
                    @elseif ($isReserved)
                        <span class="badge bg-tile text-paper">şu an başka birinin sepetinde</span>
                    @elseif (! $inStock)
                        <span class="badge bg-ink text-paper">{{ $product->isWorkshop() ? 'kontenjan dolu' : 'tükendi' }}</span>
                    @elseif ($stock !== null && $stock <= 3)
                        <span class="badge bg-tile text-paper">son {{ $stock }} adet</span>
                    @endif
```

4. In the desktop `@if ($inStock)` block (line ~183), wrap the quantity `<div class="mt-7 flex items-center gap-3">…</div>` in `@unless ($product->isUnique()) … @else <input type="hidden" name="quantity" value="1"> @endunless`.

5. Directly after that `@if ($inStock) … @endif` block, add:

```blade
                @if ($isReserved)
                    <div class="mt-7">
                        <p class="rounded-2xl bg-paper-deep px-4 py-3 text-[15px] text-ink-soft">Bu parça şu an başka birinin sepetinde. Ödeme tamamlanmazsa kısa süre içinde yeniden satışa açılır.</p>
                        <button type="button" class="btn-outline mt-4 w-full cursor-not-allowed opacity-50" disabled>sepete ekle</button>
                    </div>
                @elseif ($isSold)
                    <div class="mt-7 rounded-2xl bg-paper-deep px-5 py-4">
                        <p class="text-[15px] text-ink-soft">Bu parça yeni evine gitti. Her parçamız tek; benzerini sizin için yapabiliriz.</p>
                        @if ($similar = $product->similarRequestUrl())
                            <a href="{{ $similar }}" rel="noopener" class="btn-primary mt-3 inline-flex">benzerini iste</a>
                        @endif
                    </div>
                @endif
```

Note: the reserved notice's button text "sepete ekle" makes `assertDontSee('sepete ekle')` only valid for *sold* pieces — the reserved test asserts `hemen al` is absent instead.

6. Change `@if ($whatsapp)` on the "Sorunuz mu var?" link to `@if ($whatsapp && ! $isSold)`.

7. In the made-to-order badge condition use `@if ($product->made_to_order && ! $product->isUnique())` (unique pieces never have it, this guards stale data).

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=UniqueShopTest` → PASS; `php artisan test` → all green (StorefrontTest uses Physical products).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Product.php app/Http/Controllers/ShopController.php resources/views tests/Feature/UniqueShopTest.php
git commit -m "Show sold and reserved pieces in the shop with a request-similar button"
```

---

### Task 4: Shared style helpers — Human and PageSummary

**Files:**
- Create: `app/Support/Human.php`, `app/Filament/Support/PageSummary.php`, `tests/Feature/HumanTest.php`

**Interfaces:**
- Produces:
  - `Human::days(CarbonInterface $from, ?CarbonInterface $to = null): int` — whole calendar days, never negative.
  - `Human::since(CarbonInterface $from): string` — `bugünden beri` / `N gündür`.
  - `Human::took(CarbonInterface $from, CarbonInterface $to): string` — `aynı gün` / `N günde`.
  - `Human::day(CarbonInterface $date): string` — `bugün` / `dün` / `3 Kas` / `3 Kas 2025`.
  - `Human::date(CarbonInterface $date): string` — `bugün 14:20` / `dün 09:05` / `3 Kas` / `3 Kas 2025`.
  - `PageSummary::make(array $parts): HtmlString` — `$parts` is `list<array{0: ?string, 1?: ?string}>`: bold lead + optional plain rest; parts with an empty lead are skipped; joined with ` · `.
- Money stays in `Money::format()` (not duplicated in `Human`).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/HumanTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Support\PageSummary;
use App\Support\Human;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class HumanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 15:00', 'Europe/Istanbul'));
    }

    public function test_durations_read_naturally(): void
    {
        $this->assertSame('bugünden beri', Human::since(now()->setTime(9, 0)));
        $this->assertSame('12 gündür', Human::since(now()->subDays(12)));
        $this->assertSame('aynı gün', Human::took(now()->setTime(9, 0), now()));
        $this->assertSame('18 günde', Human::took(now()->subDays(18), now()));
        $this->assertSame(0, Human::days(now()->addDays(3))); // future dates never go negative
    }

    public function test_dates_read_naturally(): void
    {
        $this->assertSame('bugün', Human::day(now()));
        $this->assertSame('dün', Human::day(now()->subDay()));
        $this->assertSame('3 Kas', Human::day(CarbonImmutable::parse('2026-11-03')));
        $this->assertSame('3 Kas 2025', Human::day(CarbonImmutable::parse('2025-11-03')));
        $this->assertSame('bugün 14:20', Human::date(now()->setTime(14, 20)));
        $this->assertSame('dün 09:05', Human::date(now()->subDay()->setTime(9, 5)));
    }

    public function test_page_summary_joins_parts_and_skips_empty_ones(): void
    {
        $html = (string) PageSummary::make([['14 parça satışta', '(8.450 ₺ değerinde)'], [null, 'yok'], ['2 taslak']]);

        $this->assertStringContainsString('<strong', $html);
        $this->assertStringContainsString('14 parça satışta</strong> (8.450 ₺ değerinde)', $html);
        $this->assertStringContainsString('2 taslak</strong>', $html);
        $this->assertStringNotContainsString('yok', $html);
        $this->assertSame(1, substr_count($html, '·'));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=HumanTest`
Expected: FAIL — `Class "App\Support\Human" not found`.

- [ ] **Step 3: Implement**

`app/Support/Human.php`:

```php
<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Short Turkish phrases for dates and durations, as the admin panel shows them. */
class Human
{
    public static function days(CarbonInterface $from, ?CarbonInterface $to = null): int
    {
        $to ??= now();

        return max(0, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()));
    }

    public static function since(CarbonInterface $from): string
    {
        $days = self::days($from);

        return $days === 0 ? 'bugünden beri' : $days.' gündür';
    }

    public static function took(CarbonInterface $from, CarbonInterface $to): string
    {
        $days = self::days($from, $to);

        return $days === 0 ? 'aynı gün' : $days.' günde';
    }

    public static function day(CarbonInterface $date): string
    {
        $date = $date->copy()->timezone(config('app.timezone'));

        return match (true) {
            $date->isToday() => 'bugün',
            $date->isYesterday() => 'dün',
            $date->isSameYear(now()) => $date->translatedFormat('j M'),
            default => $date->translatedFormat('j M Y'),
        };
    }

    public static function date(CarbonInterface $date): string
    {
        $date = $date->copy()->timezone(config('app.timezone'));

        return $date->isToday() || $date->isYesterday()
            ? self::day($date).' '.$date->format('H:i')
            : self::day($date);
    }
}
```

`app/Filament/Support/PageSummary.php`:

```php
<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

/** The one-line summary shown under a list page's title, e.g. "14 parça satışta · 2 taslak". */
final class PageSummary
{
    /** @param list<array{0: ?string, 1?: ?string}> $parts bold lead text and optional plain rest */
    public static function make(array $parts): HtmlString
    {
        $html = collect($parts)
            ->filter(fn (array $part) => filled($part[0] ?? null))
            ->map(fn (array $part) => '<strong class="font-semibold text-gray-950 dark:text-white">'.e($part[0]).'</strong>'
                .(filled($part[1] ?? null) ? ' '.e($part[1]) : ''))
            ->implode(' <span class="text-gray-400 dark:text-gray-500">·</span> ');

        return new HtmlString('<span class="text-sm text-gray-600 dark:text-gray-400">'.$html.'</span>');
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=HumanTest` → PASS. If `3 Kas` comes out as `3 Nov`, check `config('app.locale')` is `tr` (it is in `.env.example` via `APP_LOCALE=tr`; phpunit inherits `config/app.php` default) and set `Carbon::setLocale('tr')` is not needed — Laravel sets it from `app.locale`.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Human.php app/Filament/Support/PageSummary.php tests/Feature/HumanTest.php
git commit -m "Add Human date phrases and PageSummary for the minimal admin style"
```

---

### Task 5: Admin products page

**Files:**
- Create: `app/Filament/Resources/Products/ProductStatusText.php`, `app/Filament/Resources/Products/ProductsSummary.php`, `tests/Feature/AdminProductsTest.php`
- Modify (rewrite): `app/Filament/Resources/Products/Tables/ProductsTable.php`, `app/Filament/Resources/Products/Pages/ListProducts.php`

**Interfaces:**
- Consumes: `Human::since/took/day`, `PageSummary::make` (Task 4); `pieceState()`, scopes `goods/forSale` (Task 1).
- Produces:
  - `ProductStatusText::for(Product $product): array{0: string, 1: string}` — `[colour, phrase]`, colour ∈ `success|warning|danger|gray`.
  - `ProductStatusText::html(Product $product): HtmlString` — dot + phrase.
  - `ProductsSummary::stats(): array{available_count:int, available_value:int, sold_month_count:int, sold_month_value:int, avg_days:?int, drafts:int, reserved:int}`.
  - `ProductsSummary::html(): HtmlString`.
  - List tabs keyed `available`, `sold`, `draft`, `workshop`, `all`; default `available`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AdminProductsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductsSummary;
use App\Filament\Resources\Products\ProductStatusText;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 15:00', 'Europe/Istanbul'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    public function test_state_phrases_read_naturally(): void
    {
        $available = Product::factory()->unique()->create(['published_at' => now()->subDays(12)]);
        $today = Product::factory()->unique()->create(['published_at' => now()->setTime(9, 0)]);
        $sold = Product::factory()->unique()->create(['published_at' => CarbonImmutable::parse('2026-10-16'), 'sold_at' => CarbonImmutable::parse('2026-11-03 10:00')]);
        $reserved = Product::factory()->unique()->create();
        Product::query()->whereKey($reserved->id)->update(['stock' => 0]);
        $draft = Product::factory()->unique()->create(['status' => ProductStatus::Draft]);
        $future = Product::factory()->unique()->create(['published_at' => CarbonImmutable::parse('2026-12-05 10:00')]);

        $this->assertSame(['success', 'Satışta · 12 gündür'], ProductStatusText::for($available));
        $this->assertSame(['success', 'Satışta · bugünden beri'], ProductStatusText::for($today));
        $this->assertSame(['gray', 'Satıldı · 3 Kas, 18 günde'], ProductStatusText::for($sold->fresh()));
        $this->assertSame(['warning', 'Ödeme bekleniyor'], ProductStatusText::for($reserved->fresh()));
        $this->assertSame(['gray', 'Taslak'], ProductStatusText::for($draft));
        $this->assertSame(['gray', 'Yayına girecek · 5 Ara'], ProductStatusText::for($future)); // review focus 4
    }

    public function test_workshop_phrase_summarises_upcoming_sessions(): void
    {
        $workshop = Product::factory()->workshop()->create();
        $workshop->sessions()->create(['starts_at' => '2026-12-03 11:00', 'ends_at' => '2026-12-03 14:00', 'capacity' => 8]);
        $workshop->sessions()->create(['starts_at' => '2026-12-10 11:00', 'ends_at' => '2026-12-10 14:00', 'capacity' => 8, 'seats_reserved' => 2]);

        $this->assertSame(['success', '2 seans · 14 boş yer · sıradaki 3 Ara'], ProductStatusText::for($workshop->fresh()));
    }

    public function test_summary_numbers(): void
    {
        Product::factory()->unique()->create(['price' => 65000]);
        Product::factory()->unique()->create(['price' => 120000]);
        Product::factory()->unique()->create(['price' => 50000, 'published_at' => now()->subDays(20), 'sold_at' => now()->subDays(2)]);
        Product::factory()->unique()->create(['price' => 30000, 'published_at' => now()->subDays(150), 'sold_at' => now()->subMonths(4)]);
        Product::factory()->unique()->create(['status' => ProductStatus::Draft]);
        $reserved = Product::factory()->unique()->create();
        Product::query()->whereKey($reserved->id)->update(['stock' => 0]);

        $stats = ProductsSummary::stats();

        $this->assertSame(2, $stats['available_count']);
        $this->assertSame(185000, $stats['available_value']);
        $this->assertSame(1, $stats['sold_month_count']);
        $this->assertSame(50000, $stats['sold_month_value']);
        $this->assertSame(18, $stats['avg_days']); // only the sale within 90 days counts: 20 − 2
        $this->assertSame(1, $stats['drafts']);
        $this->assertSame(1, $stats['reserved']);
        $this->assertStringContainsString('2 parça satışta', (string) ProductsSummary::html());
    }

    public function test_tabs_filter_pieces(): void
    {
        $available = Product::factory()->unique()->create();
        $sold = Product::factory()->unique()->sold()->create();
        $draft = Product::factory()->unique()->create(['status' => ProductStatus::Draft]);
        $workshop = Product::factory()->workshop()->create();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$available])
            ->assertCanNotSeeTableRecords([$sold, $draft, $workshop])
            ->set('activeTab', 'sold')->assertCanSeeTableRecords([$sold])->assertCanNotSeeTableRecords([$available])
            ->set('activeTab', 'draft')->assertCanSeeTableRecords([$draft])
            ->set('activeTab', 'workshop')->assertCanSeeTableRecords([$workshop])->assertCanNotSeeTableRecords([$available]);

        $this->get('/yonetim/urunler')->assertOk()->assertSee('parça satışta');
    }

    // Review focus 5
    public function test_copying_a_sold_piece_makes_an_available_draft_without_photos(): void
    {
        $sold = Product::factory()->unique()->sold()->create(['images' => ['products/a.jpg'], 'materials' => 'Stoneware']);

        Livewire::test(ListProducts::class)
            ->set('activeTab', 'all')
            ->callAction(TestAction::make('replicate')->table($sold));

        $copy = Product::query()->latest('id')->firstOrFail();
        $this->assertNotSame($sold->id, $copy->id);
        $this->assertSame(ProductStatus::Draft, $copy->status);
        $this->assertNull($copy->sold_at);
        $this->assertSame(1, $copy->stock);
        $this->assertSame([], $copy->images ?? []);
        $this->assertSame('Stoneware', $copy->materials);
        $this->assertNotSame($sold->sku, $copy->sku);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=AdminProductsTest`
Expected: FAIL — `ProductStatusText` not found.

- [ ] **Step 3: Implement `ProductStatusText`**

`app/Filament/Resources/Products/ProductStatusText.php`:

```php
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
            PieceState::Sold => ['gray', 'Satıldı · '.Human::day($product->sold_at).', '.Human::took($listedAt, $product->sold_at)],
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
```

- [ ] **Step 4: Implement `ProductsSummary`**

`app/Filament/Resources/Products/ProductsSummary.php`:

```php
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
```

(`sold_month_value` uses the piece's current price; sold prices are frozen on the order item and are shown there.)

- [ ] **Step 5: Rewrite `ListProducts`**

`app/Filament/Resources/Products/Pages/ListProducts.php`:

```php
<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\ProductsSummary;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni parça / atölye'),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return ProductsSummary::html();
    }

    public function getTabs(): array
    {
        $tab = fn (string $label, \Closure $scope) => Tab::make($label)
            ->modifyQueryUsing($scope)
            ->badge(fn () => $scope(Product::query())->count());

        return [
            'available' => $tab('Satışta', fn (Builder $q) => $q->forSale()),
            'sold' => $tab('Satıldı', fn (Builder $q) => $q->whereNotNull('sold_at')),
            'draft' => $tab('Taslaklar', fn (Builder $q) => $q->where('status', ProductStatus::Draft)),
            'workshop' => $tab('Atölyeler', fn (Builder $q) => $q->where('type', ProductType::Workshop)),
            'all' => Tab::make('Tümü'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'available';
    }
}
```

- [ ] **Step 6: Rewrite `ProductsTable`**

`app/Filament/Resources/Products/Tables/ProductsTable.php` — keep the class name, `bulkUpdate()` and the toolbar bulk actions block exactly as they are today; replace `configure()`'s `defaultSort`, `columns`, `filters` and `recordActions`, and delete `stockLabel()`/`stockColor()` (now in `ProductStatusText`). New imports: `App\Filament\Resources\Products\ProductStatusText`, `Filament\Forms\Components\Radio`, `Filament\Forms\Components\TextInput`, `Filament\Support\Enums\FontWeight`, `Illuminate\Support\HtmlString`, `App\Enums\ProductType`. Remove now-unused imports (`SelectColumn`, `ToggleColumn`, `SelectFilter` for type/status, `Settings`).

```php
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variants', 'sessions', 'categories']))
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->state(fn (Product $record) => $record->coverImage())
                    ->disk('public')
                    ->imageSize(56)
                    ->square(),
                TextColumn::make('name')
                    ->label('Parça')
                    ->searchable(['name', 'sku'])
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->description(fn (Product $record) => collect([$record->categories->first()?->name, $record->sku, $record->dimensions])->filter()->implode(' · ')),
                TextColumn::make('price')
                    ->label('Fiyat')
                    ->sortable()
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->description(fn (Product $record) => $record->isOnSale() ? new HtmlString('<s>'.e(Money::format($record->compare_at_price)).'</s>') : null),
                TextColumn::make('state')
                    ->label('Durum')
                    ->state(fn (Product $record) => ProductStatusText::html($record))
                    ->html()
                    // "Days in shop" for available pieces, sale date for sold ones.
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw(
                        'COALESCE(sold_at, published_at, created_at) '.($direction === 'asc' ? 'asc' : 'desc'))),
                TextColumn::make('visibility')
                    ->label('Nerede')
                    ->formatStateUsing(fn (Product $record) => match ($record->visibility) {
                        ProductVisibility::Public => 'Mağazada',
                        ProductVisibility::Unlisted => 'Sadece link',
                        ProductVisibility::Hidden => 'Gizli',
                    }.($record->is_featured ? ' · ★' : ''))
                    ->color('gray'),
                TextColumn::make('view_count')
                    ->label('Görüntülenme')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('categories')->label('Kategori')->relationship('categories', 'name')->multiple()->preload(),
                Filter::make('price')
                    ->label('Fiyat aralığı')
                    ->schema([
                        TextInput::make('min')->label('En az (₺)')->numeric(),
                        TextInput::make('max')->label('En çok (₺)')->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(filled($data['min'] ?? null), fn (Builder $q) => $q->where('price', '>=', (int) $data['min'] * 100))
                        ->when(filled($data['max'] ?? null), fn (Builder $q) => $q->where('price', '<=', (int) $data['max'] * 100))),
                TernaryFilter::make('is_featured')->label('Öne çıkan'),
                SelectFilter::make('visibility')->label('Görünürlük')->options(ProductVisibility::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('togglePublish')
                        ->label(fn (Product $record) => $record->status === ProductStatus::Published ? 'Taslağa al' : 'Yayınla')
                        ->icon(fn (Product $record) => $record->status === ProductStatus::Published ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                        ->action(fn (Product $record) => self::bulkUpdate(new Collection([$record]), [
                            'status' => $record->status === ProductStatus::Published ? ProductStatus::Draft : ProductStatus::Published,
                        ])),
                    Action::make('visibility')
                        ->label('Görünürlük')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->fillForm(fn (Product $record) => ['visibility' => $record->visibility])
                        ->schema([Radio::make('visibility')->label('Görünürlük')->options(ProductVisibility::class)->required()])
                        ->action(fn (Product $record, array $data) => self::bulkUpdate(new Collection([$record]), ['visibility' => $data['visibility']])),
                    Action::make('toggleFeatured')
                        ->label(fn (Product $record) => $record->is_featured ? 'Öne çıkarmayı kaldır' : 'Öne çıkar')
                        ->icon('heroicon-o-star')
                        ->action(fn (Product $record) => self::bulkUpdate(new Collection([$record]), ['is_featured' => ! $record->is_featured])),
                    Action::make('share')
                        ->label('Instagram linki')
                        ->icon('heroicon-o-link')
                        ->modalHeading(fn (Product $record) => $record->name.' · paylaşım linkleri')
                        ->modalContent(fn (Product $record) => view('filament.product-share', ['product' => $record]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Kapat'),
                    Action::make('view')
                        ->label('Sitede gör')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (Product $record) => $record->isReachable() ? $record->url() : $record->previewUrl(), shouldOpenInNewTab: true),
                    ReplicateAction::make()
                        ->label('Kopyala')
                        ->excludeAttributes(['view_count', 'sku', 'images', 'og_image', 'sold_at', 'published_at'])
                        ->beforeReplicaSaved(function (Product $replica, Product $record) {
                            $replica->name = $record->name.' (kopya)';
                            $replica->slug = $record->slug.'-'.Str::lower(Str::random(4));
                            $replica->status = ProductStatus::Draft;
                            $replica->images = [];
                            $replica->stock = $record->isUnique() ? 1 : $record->stock;
                        })
                        ->after(function (Product $replica, Product $record) {
                            $replica->categories()->sync($record->categories->pluck('id'));
                            if ($record->type === ProductType::Physical) {
                                foreach ($record->variants as $variant) {
                                    $replica->variants()->create($variant->only(['name', 'price', 'stock', 'is_active', 'sort_order']));
                                }
                            }
                        })
                        ->successRedirectUrl(fn (Product $replica) => ProductResource::getUrl('edit', ['record' => $replica])),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
```

`ReplicateAction` saves the replica as a *new* model, so `ProductObserver::creating` assigns a fresh `PRZ-` code and `saving` sets stock 1 because `sold_at` is excluded.

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter=AdminProductsTest` → PASS; `php artisan test` → all green (the product form is unchanged until Task 6).

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/Products tests/Feature/AdminProductsTest.php
git commit -m "Redesign the admin products page around readable piece states"
```

---

### Task 6: Product form as one scrolling page

**Files:**
- Create: `resources/views/filament/product-state.blade.php`
- Modify (rewrite): `app/Filament/Resources/Products/Schemas/ProductForm.php`
- Modify: `app/Filament/Resources/Products/RelationManagers/VariantsRelationManager.php` (`canViewForRecord`), `app/Models/Product.php` (`pieceOrder()`), `tests/Feature/AdminPanelTest.php` (product creation test), `tests/Feature/AdminProductsTest.php` (new tests)

**Interfaces:**
- Consumes: `ProductType::creatable()`, `pieceState()` (Task 1); `ProductStatusText::html()` (Task 5); `Human::day/took` (Task 4).
- Produces: `Product::pieceOrder(): ?Order` — the unpaid order holding a reserved piece or the paid order a sold piece went to.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/AdminProductsTest.php` (add `use App\Filament\Resources\Products\Pages\CreateProduct;`, `use App\Filament\Resources\Products\Pages\EditProduct;`, `use App\Enums\ProductType;`):

```php
    public function test_creating_a_piece_offers_only_unique_and_workshop(): void
    {
        $this->get('/yonetim/urunler/create')->assertOk()->assertSee('Tek parça')->assertDontSee('Seri üretim');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'unique',
                'name' => 'Benekli Kupa',
                'slug' => 'benekli-kupa',
                'price' => '649,90',
                'status' => 'published',
                'visibility' => 'public',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $piece = Product::query()->where('slug', 'benekli-kupa')->firstOrFail();
        $this->assertSame(ProductType::Unique, $piece->type);
        $this->assertSame(64990, $piece->price);
        $this->assertSame(1, $piece->stock);
        $this->assertSame('PRZ-0001', $piece->sku);
    }

    public function test_sold_piece_edit_page_shows_its_sale(): void
    {
        $sold = Product::factory()->unique()->create(['published_at' => now()->subDays(20), 'sold_at' => now()->subDays(2)]);

        $this->get('/yonetim/urunler/'.$sold->slug.'/edit')->assertOk()
            ->assertSee('Satıldı')
            ->assertSee('mevcut siparişi etkilemez')
            ->assertDontSee('Stok adedi');
    }

    // Review focus 3
    public function test_existing_mass_produced_product_keeps_its_stock_field(): void
    {
        $physical = Product::factory()->create(['stock' => 4]);

        Livewire::test(EditProduct::class, ['record' => $physical->slug])
            ->assertFormFieldVisible('stock')
            ->assertSee('Seri üretim');
    }
```

Change `tests/Feature/AdminPanelTest.php::test_product_can_be_created_with_prices_in_lira` form data to drop the mass-production fields (Physical is no longer offered on create):

```php
            ->fillForm([
                'type' => 'unique',
                'name' => 'Benekli Kupa',
                'slug' => 'benekli-kupa',
                'price' => '649,90',
                'status' => 'published',
                'visibility' => 'unlisted',
            ])
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter="AdminProductsTest|AdminPanelTest"`
Expected: FAIL — "Seri üretim" still offered / `'unique'` rejected by the old default, no "mevcut siparişi etkilemez" note.

- [ ] **Step 3: Add `pieceOrder()` to the model**

`app/Models/Product.php` (add `use App\Enums\OrderStatus;`):

```php
    /** The unpaid order holding a reserved piece, or the paid order a sold piece went to. */
    public function pieceOrder(): ?Order
    {
        $state = $this->pieceState();
        if ($state === null || $state === PieceState::Available) {
            return null;
        }

        return Order::query()
            ->whereHas('items', fn (Builder $q) => $q->where('product_id', $this->id))
            ->when($state === PieceState::Sold,
                fn (Builder $q) => $q->paid(),
                fn (Builder $q) => $q->where('status', OrderStatus::PendingPayment)->where('reservation_released', false))
            ->with('items')
            ->latest('id')
            ->first();
    }
```

- [ ] **Step 4: Create the Durum card view**

`resources/views/filament/product-state.blade.php`:

```blade
@php
    use App\Enums\PieceState;
    use App\Support\Human;
    use App\Support\Money;

    $order = $product->pieceOrder();
    $item = $order?->items->firstWhere('product_id', $product->id);
    $listedAt = $product->published_at ?? $product->created_at;
@endphp
<div class="space-y-1 text-sm">
    <p class="font-medium">{{ \App\Filament\Resources\Products\ProductStatusText::html($product) }}</p>
    <p class="text-gray-500 dark:text-gray-400">
        @if ($product->pieceState() === PieceState::Sold)
            {{ $product->sold_at->translatedFormat('j F') }}
            @if ($item) · {{ Money::format($item->unit_price) }} @endif
            · {{ Human::took($listedAt, $product->sold_at) }} satıldı
        @else
            {{ number_format($product->view_count, 0, ',', '.') }} görüntülenme
        @endif
    </p>
    @if ($order)
        <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order]) }}" class="text-primary-600 underline dark:text-primary-400">Sipariş {{ $order->number }}</a>
    @endif
</div>
```

- [ ] **Step 5: Rewrite `ProductForm`**

`app/Filament/Resources/Products/Schemas/ProductForm.php` — same imports as today minus `Tabs`/`Tab`, plus `Filament\Infolists\Components\TextEntry`, `Illuminate\Support\HtmlString`. Keep `isWorkshop()` and add `isPhysical()`:

```php
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)->columnSpan(2)->schema([
                    Section::make()->schema([
                        ToggleButtons::make('type')
                            ->label('Tür')
                            ->options(fn (string $operation) => collect($operation === 'create' ? ProductType::creatable() : ProductType::cases())
                                ->mapWithKeys(fn (ProductType $type) => [$type->value => $type->getLabel()])->all())
                            ->default(ProductType::Unique->value)
                            ->inline()
                            ->live()
                            ->disabledOn('edit')
                            ->required(),
                    ]),

                    Section::make('Fotoğraflar')->schema([
                        FileUpload::make('images')
                            ->hiddenLabel()
                            ->helperText('Sürükleyerek sıralayın; ilk fotoğraf kapak olur. Instagram için 4:5 dikey fotoğraflar idealdir.')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->panelLayout('grid')
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['4:5', '1:1', null])
                            ->disk('public')
                            ->directory('products')
                            ->maxSize(8192)
                            ->maxFiles(12),
                    ]),

                    Section::make('Ad & açıklama')->schema([
                        TextInput::make('name')
                            ->label('Ad')
                            ->required()
                            ->maxLength(190)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation) {
                                if ($operation === 'create' || blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Bağlantı (slug)')
                            ->prefix(fn () => url('/urun').'/')
                            ->required()
                            ->alphaDash()
                            ->unique(Product::class, 'slug', ignoreRecord: true)
                            ->helperText('Instagram’da paylaştığınız link bu kısmı içerir; yayına aldıktan sonra değiştirmeyin.'),
                        Textarea::make('short_description')
                            ->label('Kısa açıklama')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('Ürün sayfasında fiyatın altında ve link önizlemelerinde görünür.'),
                        RichEditor::make('description')
                            ->label('Açıklama')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h3', 'undo', 'redo']),
                    ]),

                    Section::make('Detaylar')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('dimensions')->label(fn (Get $get) => self::isWorkshop($get) ? 'Süre / kapsam' : 'Ölçüler')->maxLength(190),
                            TextInput::make('materials')->label(fn (Get $get) => self::isWorkshop($get) ? 'Dahil olanlar' : 'Malzeme')->maxLength(190),
                            TextInput::make('weight_grams')->label('Ağırlık (gram)')->numeric()->integer()
                                ->hidden(fn (Get $get) => self::isWorkshop($get)),
                        ]),
                        Textarea::make('care')->label('Bakım bilgisi')->rows(3)
                            ->hidden(fn (Get $get) => self::isWorkshop($get)),
                    ]),

                    // Only for mass-produced products ("Seri üretim").
                    Section::make('Stok')->visible(fn (Get $get) => self::isPhysical($get))->schema([
                        Grid::make(2)->schema([
                            Toggle::make('track_stock')->label('Stok takibi yap')->default(true)->live(),
                            Toggle::make('made_to_order')->label('Siparişe özel üretilir')->helperText('Stok sınırı olmadan sipariş alınır.')->live(),
                            TextInput::make('stock')->label('Stok adedi')->numeric()->integer()->default(0)
                                ->helperText('Seçenekli ürünlerde stok, seçeneklerde ayrı ayrı tutulur.')
                                ->visible(fn (Get $get) => $get('track_stock') && ! $get('made_to_order')),
                            TextInput::make('lead_time_days')->label('Hazırlık süresi (gün)')->numeric()->integer()->minValue(1)
                                ->visible(fn (Get $get) => (bool) $get('made_to_order')),
                        ]),
                    ]),

                    Section::make('SEO & paylaşım')->collapsed()->schema([
                        TextInput::make('seo_title')->label('Sayfa başlığı')->maxLength(70)->helperText('Boş bırakılırsa ürün adı kullanılır.'),
                        Textarea::make('seo_description')->label('Açıklama (Google & link önizlemesi)')->rows(2)->maxLength(300),
                        FileUpload::make('og_image')
                            ->label('Paylaşım görseli')
                            ->helperText('WhatsApp/Instagram DM önizlemesi için. Boşsa kapak fotoğrafı kullanılır.')
                            ->image()
                            ->disk('public')
                            ->directory('products/og'),
                        TextInput::make('instagram_url')->label('İlgili Instagram gönderisi')->url()->maxLength(255),
                        TextInput::make('sort_order')->label('Mağazadaki sıra')->numeric()->integer()->default(0)->helperText('Küçük sayı önce gösterilir.'),
                    ]),
                ]),

                Grid::make(1)->columnSpan(1)->schema([
                    Section::make('Durum')->visibleOn('edit')->schema([
                        TextEntry::make('piece_state')
                            ->hiddenLabel()
                            ->state(fn (?Product $record) => $record ? new HtmlString(view('filament.product-state', ['product' => $record])->render()) : null)
                            ->html(),
                    ]),
                    Section::make('Fiyat')->schema([
                        MoneyInput::make('price')
                            ->label(fn (Get $get) => self::isWorkshop($get) ? 'Kişi başı fiyat (KDV dahil)' : 'Fiyat (KDV dahil)')
                            ->helperText(fn (?Product $record) => $record?->sold_at ? 'Bu parça satıldı; fiyatı değiştirmek mevcut siparişi etkilemez.' : null)
                            ->required(),
                        MoneyInput::make('compare_at_price')
                            ->label('İndirim öncesi fiyat')
                            ->helperText('Doluysa “indirimde” görünür ve bu fiyat üstü çizili gösterilir.'),
                    ]),
                    Section::make('Yayın')->schema([
                        Select::make('status')->label('Durum')->options(ProductStatus::class)->default(ProductStatus::Draft)->required()->native(false),
                        Radio::make('visibility')->label('Görünürlük')->options(ProductVisibility::class)->default(ProductVisibility::Public)->required(),
                        DateTimePicker::make('published_at')->label('Yayın tarihi')
                            ->helperText('İleri bir tarih seçerseniz o zaman görünür olur.')->native(false)->seconds(false),
                        Toggle::make('is_featured')->label('Öne çıkar (ana sayfa & link sayfası)'),
                    ]),
                    Section::make('Kategoriler')->schema([
                        Select::make('categories')->hiddenLabel()->relationship('categories', 'name')->multiple()->preload()->searchable(),
                    ]),
                    Section::make('Parça kodu')->hidden(fn (Get $get) => self::isWorkshop($get))->schema([
                        TextInput::make('sku')->hiddenLabel()->placeholder('Boş bırakırsanız otomatik verilir')
                            ->unique(Product::class, 'sku', ignoreRecord: true),
                    ]),
                ]),
            ]);
    }

    private static function type(Get $get): ?ProductType
    {
        $type = $get('type');

        return $type instanceof ProductType ? $type : ProductType::tryFrom((string) $type);
    }

    private static function isWorkshop(Get $get): bool
    {
        return self::type($get) === ProductType::Workshop;
    }

    private static function isPhysical(Get $get): bool
    {
        return self::type($get) === ProductType::Physical;
    }
```

If `TextEntry` inside a form schema does not render in this Filament version, swap it for `Filament\Schemas\Components\View::make('filament.product-state')->viewData(fn (?Product $record) => ['product' => $record])` — the test in Step 1 (`assertSee('Satıldı')`) tells you which works.

`app/Filament/Resources/Products/RelationManagers/VariantsRelationManager.php` — `canViewForRecord` becomes:

```php
        return $ownerRecord->type === ProductType::Physical;
```

(add `use App\Enums\ProductType;`).

`app/Filament/Resources/Products/Pages/CreateProduct.php` — update the comment on `getRedirectUrl()` to "Stay on the edit page so photos, workshop dates or variants can be adjusted right away."

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter="AdminProductsTest|AdminPanelTest"` → PASS; `php artisan test` → all green.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/Products app/Models/Product.php resources/views/filament/product-state.blade.php tests/Feature
git commit -m "Turn the product form into one page with a piece status card"
```

---

### Task 7: Panel-wide minimal style and dashboard

**Files:**
- Modify: `resources/css/filament/admin/theme.css`, `app/Filament/Widgets/SalesOverview.php`, `tests/Feature/AdminPanelTest.php`

**Interfaces:**
- Consumes: `forSale()` scope, `ProductType::Unique` (Task 1).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/AdminPanelTest.php` (add `use App\Filament\Widgets\SalesOverview;`):

```php
    public function test_dashboard_counts_pieces_for_sale_instead_of_low_stock(): void
    {
        $this->actingAs($this->admin());
        Product::factory()->unique()->count(3)->create();
        Product::factory()->unique()->sold()->create(['sold_at' => now()]);

        Livewire::test(SalesOverview::class)
            ->assertSee('Satışta')
            ->assertSee('3 parça')
            ->assertSee('bu ay 1 parça satıldı')
            ->assertDontSee('Az stok');
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=test_dashboard_counts_pieces_for_sale_instead_of_low_stock`
Expected: FAIL — sees "Az stok".

- [ ] **Step 3: Implement the dashboard change**

`app/Filament/Widgets/SalesOverview.php` — replace the `$lowStock` query with:

```php
        $forSale = Product::query()->where('type', ProductType::Unique)->forSale()->where('stock', '>', 0)->count();
        $soldThisMonth = Product::query()->where('type', ProductType::Unique)->where('sold_at', '>=', now()->startOfMonth())->count();
```

and the last `Stat` with:

```php
            Stat::make('Satışta', $forSale.' parça')
                ->description($soldThisMonth ? 'bu ay '.$soldThisMonth.' parça satıldı' : 'bu ay henüz satış yok')
                ->color('gray')
                ->url(ProductResource::getUrl()),
```

Remove the now-unused `use App\Support\Settings;`.

- [ ] **Step 4: Verify Filament class names, then add the theme rules**

Run: `grep -rhoE 'fi-(header-heading|ta-text|ta-row|badge|ta-cell|in-text|wi-stats-overview-stat-value)\b' vendor/filament/*/resources/views | sort | uniq -c`
Expected: each class listed at least once. Drop any rule below whose class is missing.

Append to `resources/css/filament/admin/theme.css`:

```css
/* Minimal, data-first admin — see docs/superpowers/specs/2026-10-16-unique-pieces-design.md §4. */
.fi-header-heading {
    font-size: 1.375rem;
    line-height: 1.875rem;
    letter-spacing: -0.01em;
}

.fi-ta-text,
.fi-in-text,
.fi-wi-stats-overview-stat-value {
    font-variant-numeric: tabular-nums;
}

.fi-ta-text:not(.fi-inline) {
    padding-block: 0.625rem;
}

.fi-ta-row {
    border-color: color-mix(in oklab, var(--color-gray-200) 60%, transparent);
}

.dark .fi-ta-row {
    border-color: rgb(255 255 255 / 0.05);
}

.fi-badge {
    box-shadow: none;
    font-weight: 500;
}
```

- [ ] **Step 5: Build and run all tests**

Run: `npm run build && php artisan test && vendor/bin/pint --test`
Expected: build succeeds; all tests pass; Pint `passed`.

- [ ] **Step 6: Commit**

```bash
git add resources/css/filament/admin/theme.css app/Filament/Widgets/SalesOverview.php tests/Feature/AdminPanelTest.php
git commit -m "Apply minimal admin style panel-wide and count pieces for sale on the dashboard"
```

---

### Task 8: Demo data and end-to-end check

**Files:**
- Modify: `database/seeders/DemoSeeder.php`

**Interfaces:**
- Consumes: `ProductType::Unique`, `sold_at`, observer SKU auto-fill (Task 1).

- [ ] **Step 1: Rewrite the demo pieces**

In `database/seeders/DemoSeeder.php`:

1. In the `$products` array, replace the 6th column (stock) with "sold N days ago or null":

```php
        $products = [
            ['Kobalt Kupa', 'kupalar', 'kupa-kobalt', 65000, null, null, true, 'Derin kobalt sırlı, el yapımı stoneware kupa. Avuç içine tam oturan kulp.', '350 ml · 9 cm yükseklik', 'Stoneware, kurşunsuz sır'],
            ['Kum Kupa', 'kupalar', 'kupa-kum', 60000, null, 2, true, 'Kum rengi mat sır, içi parlak.', '300 ml', 'Stoneware'],
            ['Yosun Kupa', 'kupalar', 'kupa-yesil', 60000, 72000, null, false, 'Yosun yeşili akıntılı sır.', '320 ml', 'Stoneware'],
            ['Benekli Servis Tabağı', 'tabaklar-kaseler', 'tabak-benekli', 95000, null, null, true, 'Benekli beyaz sırlı geniş servis tabağı.', 'Ø 28 cm', 'Benekli stoneware'],
            ['Kiremit Kase', 'tabaklar-kaseler', 'kase-terracotta', 55000, null, 5, false, 'Toprak kırmızısı salata ve meyve kasesi.', 'Ø 16 cm', 'Kırmızı kil'],
            ['Uzun Siyah Vazo', 'vazolar', 'vazo-uzun', 120000, null, null, true, 'Mat siyah, uzun boyunlu vazo.', '32 cm yükseklik', 'Siyah kil'],
            ['Yuvarlak Vazo', 'vazolar', 'vazo-yuvarlak', 85000, null, null, false, 'Kirli beyaz, yuvarlak gövdeli küçük vazo.', '18 cm yükseklik', 'Stoneware'],
        ];
```

2. In the loop, destructure `$soldDaysAgo` instead of `$stock`, and in `create([...])`: `'type' => ProductType::Unique,`, remove `'sku'`, `'stock'` and `'track_stock'` (the observer sets them), add `'sold_at' => $soldDaysAgo ? now()->subDays($soldDaysAgo) : null,`, and change `'published_at' => now()->subDays(10 - $i)` to `now()->subDays(20 - $i)` so sold pieces have a believable time in the shop. Change the description sentence to `'<p>'.$short.' Atölyemizde çarkta şekillendirilip iki kez fırınlandı. Her parça tek; renk ve dokusu fotoğraftakiyle aynıdır.</p>'`.

3. Replace the whole `$espresso` block (product + variants) with two pieces and one draft:

```php
        foreach ([['Espresso Fincanı · Kobalt', 'espresso-fincani-kobalt'], ['Espresso Fincanı · Kirli Beyaz', 'espresso-fincani-kirli-beyaz']] as [$name, $slug]) {
            Product::query()->create([
                'type' => ProductType::Unique,
                'name' => $name,
                'slug' => $slug,
                'short_description' => 'Tek parça espresso fincanı.',
                'description' => '<p>Çarkta şekillendirilmiş, tek parça espresso fincanı.</p>',
                'images' => [$this->storeImage('espresso-seti')],
                'price' => 45000,
                'dimensions' => '90 ml',
                'materials' => 'Stoneware',
                'care' => $care,
                'status' => ProductStatus::Published,
                'visibility' => ProductVisibility::Public,
                'published_at' => now()->subDays(3),
            ])->categories()->attach($categories['kupalar']);
        }

        Product::query()->create([
            'type' => ProductType::Unique,
            'name' => 'Sırlı Küçük Tabak',
            'slug' => 'sirli-kucuk-tabak',
            'short_description' => 'Fırından yeni çıktı, fotoğrafları bekliyor.',
            'price' => 40000,
            'status' => ProductStatus::Draft,
            'visibility' => ProductVisibility::Public,
        ])->categories()->attach($categories['tabaklar-kaseler']);
```

The workshop and coupon blocks stay unchanged.

- [ ] **Step 2: Seed a fresh test database to check the seeder**

Run: `php artisan test` (all green), then:

```bash
touch /tmp/claude-seed-check.sqlite
DB_CONNECTION=sqlite DB_DATABASE=/tmp/claude-seed-check.sqlite php artisan migrate:fresh --seed --force
DB_CONNECTION=sqlite DB_DATABASE=/tmp/claude-seed-check.sqlite php artisan db:seed --class=DemoSeeder --force
DB_CONNECTION=sqlite DB_DATABASE=/tmp/claude-seed-check.sqlite php artisan tinker --execute="echo App\Models\Product::query()->pluck('sku','name')->toJson(JSON_UNESCAPED_UNICODE);"
rm /tmp/claude-seed-check.sqlite
```

Expected: 10 unique pieces with codes `PRZ-0001`…`PRZ-0010`, plus the workshop with no code.

- [ ] **Step 3: Commit**

```bash
git add database/seeders/DemoSeeder.php
git commit -m "Seed unique demo pieces"
```

- [ ] **Step 4: Rebuild the local Docker site with fresh demo data**

This wipes the local Docker database (demo data only, as agreed in the spec):

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up --build -d
docker compose exec -T app php artisan migrate:fresh --force --seed
docker compose exec -T app php artisan db:seed --class=DemoSeeder --force
```

The admin password stays the one in `.env` (`ADMIN_PASSWORD`).

- [ ] **Step 5: Browser walkthrough**

Check in Chrome, with no console errors:
- Shop `/urunler`: sold pieces (Kum Kupa, Kiremit Kase) show "satıldı" and come after available ones; no "son 1 adet" badges.
- A sold piece page: "benzerini iste" opens a `wa.me` link with the piece name.
- An available piece: "sepete ekle"/"hemen al", no quantity picker; add it twice → cart shows quantity 1.
- Admin `/yonetim/urunler`: summary line, Satışta tab default with counts, Durum phrases, ⋯ menu actions, Kopyala on a sold piece opens an unsold draft.
- Admin edit page of Kum Kupa: Durum card "Satıldı · …", price note.
- Dashboard: "Satışta · N parça" card.

- [ ] **Step 6: Final checks**

Run: `php artisan test && vendor/bin/pint --test && git status --short`
Expected: all tests pass, Pint passed, clean tree.
