# Admin Round 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the WhatsApp "Benzerini iste" link with a request form handled in a new Talepler admin page, restyle orders, dashboard and the remaining admin pages, polish the product add page, and remove coupons and multi-user management.

**Architecture:** Requests are a new `SimilarRequest` model with a public form route on sold pieces and a Filament resource. Orders and the dashboard reuse the round-1 helpers (`Human`, `PageSummary`) plus a new `OrderStatusText`. Removals delete code paths and drop the now-unused columns with migrations (no live data exists).

**Tech Stack:** Laravel 13 · PHP 8.3 · Filament 5 · Livewire 4 · Blade/Alpine/Tailwind 4 · PHPUnit/SQLite.

**Spec:** `docs/superpowers/specs/2026-10-16-admin-round-2-design.md` (style rules: `docs/superpowers/specs/2026-10-16-unique-pieces-design.md` §4)

## Global Constraints

- Nothing is pushed; commit locally only.
- Money is integer kuruş; format with `Money::format()`. Dates/durations via `App\Support\Human`.
- Admin tables: no `ToggleColumn`/`SelectColumn`/`TextInputColumn`; state = coloured dot + phrase (green good, amber waiting, red problem, grey neutral).
- **Filament injects closure arguments by name** — use `$record`, `$query`, `$data`, `$state`, `$livewire`, `$arguments`; never `$q` in a Filament closure (plain Laravel `->when()` closures are fine).
- All copy Turkish, exactly as written here.
- Run `php artisan test`; style `vendor/bin/pint --test`. Each task ends green. Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- New migrations are dated `2026_10_18_0002..` and later so they run after existing ones.

## Review Focus

1. A request whose contact is a landline or a typo ("0532 12") is rejected with a clear message, not stored as a phone. (Test in Task 3.)
2. Sending the payment link for a piece that is still a draft must make it reachable (publish it, keep "Sadece link"), otherwise the customer gets a 404. (Test in Task 4.)
3. A request whose created piece is sold through checkout moves to Tamamlandı, but a request already Cancelled stays Cancelled. (Test in Task 4.)
4. Order totals and the iyzico basket stay exact after removing discounts (basket sum == order total). (Test in Task 1.)
5. "Hazırlanacak · N gündür" counts from `paid_at`, not `created_at`, and an unpaid order past `reserved_until` reads "süresi doldu", never negative minutes. (Test in Task 5.)

---

## File Structure

| File | Responsibility |
| --- | --- |
| `database/migrations/2026_10_18_000200_remove_coupons.php` | drop coupons table + order coupon/discount columns |
| `database/migrations/2026_10_18_000300_remove_user_roles.php` | drop users.role, users.is_active |
| `database/migrations/2026_10_18_000400_create_similar_requests_table.php` | requests table |
| `app/Enums/RequestStatus.php` | request statuses with label + colour |
| `app/Models/SimilarRequest.php` | request model, contact helpers |
| `app/Support/Contact.php` | parse "phone or email" input |
| `app/Http/Controllers/SimilarRequestController.php` | public form endpoint |
| `app/Mail/AdminNewSimilarRequest.php`, `SimilarRequestReceived.php`, `SimilarPieceReady.php` + views | request emails |
| `resources/views/shop/partials/similar-request.blade.php` | form bottom sheet |
| `app/Filament/Resources/SimilarRequests/…` | Talepler resource (list + view) |
| `app/Filament/Resources/Orders/OrderStatusText.php`, `OrdersSummary.php` | order phrases + summary |
| `app/Filament/Widgets/Greeting.php`, `Waiting.php`, `RecentSales.php` (+ views) | dashboard |
| Removed: Coupon model/enum/resource, UserRole enum, UserResource, LatestOrders, SalesBySource widgets | |

---

### Task 1: Remove coupons

**Files:**
- Create: `database/migrations/2026_10_18_000200_remove_coupons.php`
- Delete: `app/Models/Coupon.php`, `app/Enums/CouponType.php`, `app/Filament/Resources/Coupons/` (whole directory)
- Modify: `app/Services/Cart/CartService.php`, `app/Http/Controllers/CartController.php`, `routes/web.php:34-35`, `app/Services/Checkout/CheckoutService.php`, `app/Services/Orders/ReservationService.php`, `app/Services/Payment/BasketBuilder.php`, `app/Models/Order.php`, `app/Console/Commands/ReleaseExpiredReservations.php:13`, `app/Filament/Resources/Orders/OrderActions.php` (CSV), `app/Filament/Resources/Orders/Schemas/OrderInfolist.php:46-47`, views `shop/cart.blade.php:53-67`, `shop/partials/summary.blade.php:4-6`, `contracts/cart-summary.blade.php`, `contracts/snapshot.blade.php`, `filament/order-items.blade.php`, `mail/partials-items.blade.php`, `orders/track.blade.php`, `database/seeders/DemoSeeder.php` (coupon block), `tests/Feature/CheckoutTest.php`, `tests/Unit/MoneyTest.php`, `tests/Feature/AdminPanelTest.php:60`

**Interfaces:**
- Produces: `CartService` without `applyCoupon/removeCoupon/coupon/discount`; `CartService::total() = subtotal + shipping`; `Order` without `coupon()`, `discount_total`, `coupon_id`, `coupon_code`; `BasketBuilder::distributeDiscount` removed — `charged_total` = `line_total`.

- [ ] **Step 1: Write the failing tests**

In `tests/Feature/CheckoutTest.php`, delete `test_coupon_discount_is_applied_and_counted` and the `CouponType`/`Coupon` imports, and add:

```php
    // Review focus 4
    public function test_totals_and_basket_are_exact_without_discounts(): void
    {
        $product = Product::factory()->create(['price' => 65000, 'stock' => 3]);

        $order = $this->placeOrder($product, 2);

        $this->assertSame(130000 + 9000, $order->total);
        $this->assertSame([130000], $order->items->pluck('charged_total')->all());
        $this->assertSame($order->total, $order->items->sum('charged_total') + $order->shipping_total);
        $this->post('/sepet/kupon', ['code' => 'X'])->assertStatus(405); // route removed: POST /sepet/{key} does not exist either
    }
```

In `tests/Feature/AdminPanelTest.php::test_admin_pages_render`, remove `'/yonetim/kuponlar'` from the URL list and add after the loop:

```php
        $this->get('/yonetim/kuponlar')->assertNotFound();
```

In `tests/Unit/MoneyTest.php`, delete the test method that calls `BasketBuilder::distributeDiscount` (and the `BasketBuilder` import if unused).

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter="test_totals_and_basket_are_exact_without_discounts|test_admin_pages_render"`
Expected: FAIL — `/sepet/kupon` returns a redirect (route exists) and `/yonetim/kuponlar` returns 200.

- [ ] **Step 3: Implement**

Migration `2026_10_18_000200_remove_coupons.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Coupons are not used by the workshop; no live orders exist, so the columns are dropped outright.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'discount_total']);
        });
        Schema::dropIfExists('coupons');
    }

    public function down(): void
    {
        // Not reversible: the coupon feature was removed from the code.
    }
};
```

`CartService`: delete `COUPON_KEY`, `applyCoupon()`, `removeCoupon()`, `coupon()`, `discount()`, the `Coupon` import; `clear()` forgets only `self::KEY`; replace every `$this->subtotal() - $this->discount()` with `$this->subtotal()`; `total()` becomes `return $this->subtotal() + $this->shipping();`.

`CartController`: delete `applyCoupon()` and `removeCoupon()`. `routes/web.php`: delete the two coupon routes.

`CheckoutService` — in the transaction replace the coupon/discount lines with:

```php
            $subtotal = $lines->sum(fn (CartLine $line) => $line->total());
            $requiresShipping = $lines->contains(fn (CartLine $line) => ! $line->isWorkshop());
            $shipping = CartService::shippingFor($requiresShipping, $subtotal);
            $total = $subtotal + $shipping;
```

remove `'discount_total'`, `'coupon_id'`, `'coupon_code'` from `Order::create`, replace the `$charged = BasketBuilder::distributeDiscount(...)` block with nothing and set `'charged_total' => $line->total(),` in the item create; delete `lockCoupon()`, the `Coupon` import, and "and the coupon" from the docblock.

`ReservationService`: delete both `if ($order->coupon_id) { … }` blocks and the `Coupon` import; docblock "Stock, workshop seats and coupon uses" → "Stock and workshop seats".

`BasketBuilder`: delete `distributeDiscount()` and adjust the class docblock sentence about discounts to "Item prices must sum exactly to the payment price."

`Order` model: delete `coupon()` and the `Coupon` import; remove `discount_total`, `coupon_id`, `coupon_code` from `$fillable`/casts if listed.

`ReleaseExpiredReservations` description: `'Unpaid orders past their reservation window give their stock and seats back'`.

`OrderActions::exportCsv`: remove the `'İndirim'` header and the `Money::toDecimal($order->discount_total),` cell.

`OrderInfolist`: delete the `coupon_code` entry (lines 46-47).

Views: delete the coupon `@if … @else … @endif` block in `shop/cart.blade.php` (keep `@include('shop.partials.summary')`); delete the discount `@if` rows in `shop/partials/summary.blade.php`, `contracts/cart-summary.blade.php`, `contracts/snapshot.blade.php`, `filament/order-items.blade.php`, `mail/partials-items.blade.php`, `orders/track.blade.php`.

`DemoSeeder`: delete the `Coupon::query()->create([...])` block and the `CouponType`/`Coupon` imports.

Delete `app/Models/Coupon.php`, `app/Enums/CouponType.php`, `app/Filament/Resources/Coupons/`. Remove the now-empty `Marketing` case from `app/Filament/Support/NavigationGroup.php` (and its label/order entries).

Run `grep -rni "coupon\|kupon\|discount" app resources routes database/seeders tests` — expected: only the new migration and the CheckoutTest comment.

- [ ] **Step 4: Run tests**

Run: `php artisan test` → all green.

- [ ] **Step 5: Commit**

```bash
git add -A app resources routes database tests
git commit -m "Remove coupons from shop, checkout and admin"
```

---

### Task 2: One admin account, no roles

**Files:**
- Create: `database/migrations/2026_10_18_000300_remove_user_roles.php`
- Delete: `app/Enums/UserRole.php`, `app/Filament/Resources/Users/` (whole directory)
- Modify: `app/Models/User.php`, `app/Http/Controllers/PackingSlipController.php:12`, `app/Filament/Resources/Orders/OrderActions.php:93`, `app/Filament/Pages/ShopSettings.php:45-48`, `database/seeders/AdminUserSeeder.php`, `tests/Feature/AdminPanelTest.php`, `tests/Feature/WorkshopCalendarTest.php`, `tests/Feature/AdminProductsTest.php`

**Interfaces:**
- Produces: `User` with `fillable = ['name', 'email', 'password']`; `canAccessPanel()` returns `true`; no `isAdmin()`.

- [ ] **Step 1: Write the failing tests**

In `AdminPanelTest`: replace `admin()` body with `return User::factory()->create();`; remove the `UserRole` import; delete `test_guests_and_staff_are_restricted` and `test_staff_cannot_see_refund_action`; remove `'/yonetim/kullanicilar'` from the render list; add:

```php
    public function test_single_admin_setup(): void
    {
        $this->get('/yonetim')->assertRedirect('/yonetim/login');
        $this->get(route('admin.orders.print', $this->paidOrder()))->assertRedirect('/yonetim/login');

        $this->actingAs($this->admin());
        $this->get('/yonetim/kullanicilar')->assertNotFound();
        $this->get('/yonetim/ayarlar')->assertOk();
        $this->get('/yonetim/profile')->assertOk();
        Livewire::test(ViewOrder::class, ['record' => $this->paidOrder()->id])->assertActionVisible('refund');
    }
```

In `WorkshopCalendarTest` and `AdminProductsTest`: `User::factory()->create(['role' => UserRole::Admin])` → `User::factory()->create()`; drop the `UserRole` import.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=test_single_admin_setup`
Expected: FAIL — `/yonetim/kullanicilar` is 200 (or forbidden for a default staff user) and settings are forbidden for a default user.

- [ ] **Step 3: Implement**

Migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The panel has a single admin account; roles and the active switch are gone.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }

    public function down(): void
    {
        // Not reversible: roles were removed from the code.
    }
};
```

`User`: remove `UserRole` import, `role`/`is_active` from `$fillable`, `$attributes`, casts; `canAccessPanel()` → `return true;`; delete `isAdmin()`.

`PackingSlipController`: delete the `abort_unless(auth()->user()?->is_active, 403);` line (the route already sits behind the panel's auth middleware — verify with `grep -n "admin.orders.print" routes/web.php` that it uses `auth`/Filament middleware; if it only uses `auth`, keep that).

`OrderActions::refund()` visibility: drop `auth()->user()?->isAdmin() && `.

`ShopSettings::canAccess()`: delete the method.

`AdminUserSeeder`: remove `'role' => UserRole::Admin` and the import.

Delete `app/Enums/UserRole.php` and `app/Filament/Resources/Users/`.

Run `grep -rn "UserRole\|isAdmin\|is_active" app tests database/seeders | grep -v -i "variant\|coupon"` — expected: no matches for users.

- [ ] **Step 4: Run tests** — `php artisan test` → green.

- [ ] **Step 5: Commit**

```bash
git add -A app database tests
git commit -m "Keep a single admin account and remove user management"
```

---

### Task 3: "Benzerini iste" request form

**Files:**
- Create: `app/Enums/RequestStatus.php`, `app/Support/Contact.php`, `app/Models/SimilarRequest.php`, `database/migrations/2026_10_18_000400_create_similar_requests_table.php`, `app/Http/Controllers/SimilarRequestController.php`, `app/Mail/AdminNewSimilarRequest.php`, `app/Mail/SimilarRequestReceived.php`, `resources/views/mail/admin-new-request.blade.php`, `resources/views/mail/request-received.blade.php`, `resources/views/shop/partials/similar-request.blade.php`, `tests/Feature/SimilarRequestTest.php`
- Modify: `routes/web.php`, `app/Providers/AppServiceProvider.php` (rate limiter), `resources/views/shop/product.blade.php`, `app/Models/Product.php` (remove `similarRequestUrl()`), `tests/Feature/UniqueShopTest.php` (WhatsApp tests → form tests)

**Interfaces:**
- Produces:
  - `RequestStatus::{New,Contacted,InProgress,Done,Cancelled}` values `new|contacted|in_progress|done|cancelled`, labels `Yeni|İletişime geçildi|Yapılıyor|Tamamlandı|İptal`, colours `warning|success|success|gray|gray`.
  - `Contact::parse(string $input): ?array{0: 'phone'|'email', 1: string}` — email lower-cased, phone normalised to `+905XXXXXXXXX`; `null` when neither.
  - `SimilarRequest` fields `product_id, message, contact, contact_type, status, admin_note, created_product_id, ip`; relations `product()`, `createdProduct()`; `isEmail(): bool`; `whatsappUrl(string $text = ''): ?string`; `mailtoUrl(string $subject = ''): ?string`.
  - Route `POST /urun/{slug}/benzerini-iste` named `shop.similar-request`, throttle `similar-request`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/SimilarRequestTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Mail\AdminNewSimilarRequest;
use App\Mail\SimilarRequestReceived;
use App\Models\Product;
use App\Models\SimilarRequest;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SimilarRequestTest extends TestCase
{
    use RefreshDatabase;

    private Product $sold;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Settings::set(['admin_notification_email' => 'atolye@example.com']);
        $this->sold = Product::factory()->unique()->sold()->create(['name' => 'Kobalt Kupa']);
    }

    private function send(array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('shop.similar-request', $this->sold->slug), $data + [
            'message' => 'Aynısından yeşil sırlı 2 tane istiyorum.',
            'contact' => '0532 123 45 67',
            'accept_kvkk' => '1',
        ]);
    }

    public function test_sold_piece_shows_the_request_form(): void
    {
        $this->get($this->sold->url())->assertOk()
            ->assertSee('benzerini iste')
            ->assertSee('Nasıl bir parça istersiniz?')
            ->assertSee('Telefon veya e-posta')
            ->assertDontSee('wa.me', false);

        $available = Product::factory()->unique()->create();
        $this->get($available->url())->assertDontSee('Nasıl bir parça istersiniz?');
    }

    public function test_request_with_phone_is_saved_and_admin_is_emailed(): void
    {
        $this->send()->assertRedirect()->assertSessionHas('similar_request_sent');

        $request = SimilarRequest::query()->sole();
        $this->assertSame($this->sold->id, $request->product_id);
        $this->assertSame('phone', $request->contact_type);
        $this->assertSame('+905321234567', $request->contact);
        $this->assertSame(RequestStatus::New, $request->status);
        Mail::assertQueued(AdminNewSimilarRequest::class, fn ($mail) => $mail->hasTo('atolye@example.com'));
        Mail::assertNotQueued(SimilarRequestReceived::class);
    }

    public function test_request_with_email_also_confirms_to_customer(): void
    {
        $this->send(['contact' => 'Ayse@Example.com']);

        $this->assertSame('ayse@example.com', SimilarRequest::query()->sole()->contact);
        Mail::assertQueued(SimilarRequestReceived::class, fn ($mail) => $mail->hasTo('ayse@example.com'));
    }

    // Review focus 1
    public function test_invalid_input_is_rejected(): void
    {
        $this->send(['message' => ''])->assertSessionHasErrors('message');
        $this->send(['contact' => '0532 12'])->assertSessionHasErrors('contact');
        $this->send(['contact' => '0232 123 45 67'])->assertSessionHasErrors('contact'); // landline
        $this->send(['accept_kvkk' => null])->assertSessionHasErrors('accept_kvkk');
        $this->send(['message' => str_repeat('a', 2001)])->assertSessionHasErrors('message');

        $this->assertSame(0, SimilarRequest::query()->count());
    }

    public function test_only_sold_pieces_accept_requests(): void
    {
        $available = Product::factory()->unique()->create();

        $this->post(route('shop.similar-request', $available->slug), [
            'message' => 'x', 'contact' => 'a@b.co', 'accept_kvkk' => '1',
        ])->assertNotFound();
    }

    public function test_honeypot_and_rate_limit_block_spam(): void
    {
        $this->send(['website' => 'http://spam.example'])->assertSessionHas('similar_request_sent');
        $this->assertSame(0, SimilarRequest::query()->count());

        foreach (range(1, 5) as $i) {
            $this->send()->assertRedirect();
        }
        $this->send()->assertStatus(429);
        $this->assertSame(5, SimilarRequest::query()->count());
    }
}
```

In `tests/Feature/UniqueShopTest.php`: delete `test_request_falls_back_to_instagram_without_whatsapp`; in `test_sold_piece_shows_sold_badge_and_whatsapp_request` rename to `test_sold_piece_shows_sold_badge_and_request_button`, drop the `Settings::set` and `wa.me` assertions, keep `satıldı`, `benzerini iste`, `assertDontSee('sepete ekle')`.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=SimilarRequestTest`
Expected: FAIL — route `shop.similar-request` not defined.

- [ ] **Step 3: Implement data + parsing**

`app/Enums/RequestStatus.php`:

```php
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
```

`app/Support/Contact.php`:

```php
<?php

namespace App\Support;

use App\Services\Checkout\CheckoutService;

/** Reads a "phone or email" field: a Turkish mobile number or an email address. */
class Contact
{
    /** @return array{0: 'phone'|'email', 1: string}|null */
    public static function parse(string $input): ?array
    {
        $input = trim($input);

        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            return ['email', mb_strtolower($input)];
        }

        if (preg_match('/^[\d\s()+-]+$/', $input)) {
            $phone = CheckoutService::normalizePhone($input);

            return preg_match('/^\+905\d{9}$/', $phone) ? ['phone', $phone] : null;
        }

        return null;
    }
}
```

Migration `2026_10_18_000400_create_similar_requests_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('similar_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('contact', 190);
            $table->string('contact_type', 10);
            $table->string('status', 20)->default('new')->index();
            $table->text('admin_note')->nullable();
            $table->foreignId('created_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('similar_requests');
    }
};
```

`app/Models/SimilarRequest.php`:

```php
<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer asking for a piece like a sold one ("Benzerini iste"). */
class SimilarRequest extends Model
{
    protected $fillable = ['product_id', 'message', 'contact', 'contact_type', 'status', 'admin_note', 'created_product_id', 'ip'];

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return ['status' => RequestStatus::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function createdProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'created_product_id');
    }

    public function isEmail(): bool
    {
        return $this->contact_type === 'email';
    }

    public function whatsappUrl(string $text = ''): ?string
    {
        return $this->isEmail() ? null : 'https://wa.me/'.ltrim($this->contact, '+').($text !== '' ? '?text='.rawurlencode($text) : '');
    }

    public function mailtoUrl(string $subject = ''): ?string
    {
        return $this->isEmail() ? 'mailto:'.$this->contact.($subject !== '' ? '?subject='.rawurlencode($subject) : '') : null;
    }
}
```

- [ ] **Step 4: Implement controller, route, limiter and mails**

`app/Providers/AppServiceProvider.php` — next to the other limiters:

```php
        RateLimiter::for('similar-request', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
```

`routes/web.php` — after the product routes:

```php
Route::post('/urun/{slug}/benzerini-iste', [SimilarRequestController::class, 'store'])
    ->name('shop.similar-request')->middleware('throttle:similar-request');
```

(add `use App\Http\Controllers\SimilarRequestController;`).

`app/Http/Controllers/SimilarRequestController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\PieceState;
use App\Mail\AdminNewSimilarRequest;
use App\Mail\SimilarRequestReceived;
use App\Models\Product;
use App\Models\SimilarRequest;
use App\Support\Contact;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SimilarRequestController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $product = Product::query()->reachable()->where('slug', $slug)->firstOrFail();
        abort_unless($product->pieceState() === PieceState::Sold, 404);

        // Honeypot: bots fill every field; pretend it worked.
        if (filled($request->input('website'))) {
            return back()->with('similar_request_sent', true);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'contact' => ['required', 'string', 'max:190'],
            'accept_kvkk' => ['accepted'],
        ], [
            'message.required' => 'Lütfen nasıl bir parça istediğinizi yazın.',
            'accept_kvkk.accepted' => 'Devam etmek için KVKK metnini onaylayın.',
        ]);

        $contact = Contact::parse($data['contact'])
            ?? throw ValidationException::withMessages(['contact' => 'Geçerli bir cep telefonu ya da e-posta adresi yazın.']);

        $similar = SimilarRequest::query()->create([
            'product_id' => $product->id,
            'message' => trim($data['message']),
            'contact_type' => $contact[0],
            'contact' => $contact[1],
            'ip' => $request->ip(),
        ]);

        if (filled($admin = Settings::get('admin_notification_email'))) {
            Mail::to($admin)->send(new AdminNewSimilarRequest($similar));
        }

        if ($similar->isEmail()) {
            Mail::to($similar->contact)->send(new SimilarRequestReceived($similar));
        }

        return back()->with('similar_request_sent', true);
    }
}
```

`app/Mail/AdminNewSimilarRequest.php` (pattern of `AdminNewOrder`):

```php
<?php

namespace App\Mail;

use App\Models\SimilarRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewSimilarRequest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SimilarRequest $similar) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Yeni talep: '.$this->similar->product->name.' gibi');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.admin-new-request');
    }
}
```

`app/Mail/SimilarRequestReceived.php` — identical shape, subject `'Talebinizi aldık · '.config('app.name')`, view `mail.request-received`.

`resources/views/mail/admin-new-request.blade.php`:

```blade
@extends('mail.layout')

@section('body')
    <p style="font-size:22px;margin:0 0 12px;">Yeni talep: {{ $similar->product->name }} gibi</p>
    <p style="margin:0;">{{ $similar->contact }}</p>
    <p style="margin:12px 0;padding:12px 16px;background:#f5f4ef;border-radius:12px;white-space:pre-line;">{{ $similar->message }}</p>
    @include('mail.partials-button', ['url' => url('/yonetim/talepler/'.$similar->id), 'label' => 'Talebi aç'])
@endsection
```

`resources/views/mail/request-received.blade.php`:

```blade
@extends('mail.layout')

@section('body')
    <p style="font-size:22px;margin:0 0 12px;">Talebinizi aldık</p>
    <p style="margin:0 0 12px;">“{{ $similar->product->name }}” benzeri bir parça için yazdığınız talep bize ulaştı. En kısa sürede size döneceğiz.</p>
    <p style="margin:12px 0;padding:12px 16px;background:#f5f4ef;border-radius:12px;white-space:pre-line;">{{ $similar->message }}</p>
@endsection
```

- [ ] **Step 5: Implement the form in the shop**

`app/Models/Product.php`: delete `similarRequestUrl()` and the now-unused `Settings` import if nothing else uses it.

`resources/views/shop/product.blade.php` — in the `@elseif ($isSold)` block replace the `@if ($similar = …) … @endif` link with:

```blade
                        @if (session('similar_request_sent'))
                            <p class="mt-3 text-[15px] font-medium text-ink">Talebinizi aldık, en kısa sürede size döneceğiz.</p>
                        @else
                            <button type="button" class="btn-primary mt-3 inline-flex" x-data @click="$dispatch('open-similar-request')">benzerini iste</button>
                        @endif
```

and directly after the closing `</form>` of `#buy-form` add:

```blade
    @if ($isSold)
        @include('shop.partials.similar-request', ['product' => $product])
    @endif
```

`resources/views/shop/partials/similar-request.blade.php` (outside the buy form; bottom sheet on phones, centred dialog on desktop):

```blade
@php($hasErrors = $errors->hasAny(['message', 'contact', 'accept_kvkk']))
<div x-data="{ open: @js($hasErrors) }" x-on:open-similar-request.window="open = true" x-show="open" x-cloak
     class="fixed inset-0 z-40 flex items-end justify-center bg-ink/40 sm:items-center" @keydown.escape.window="open = false">
    <form method="POST" action="{{ route('shop.similar-request', $product->slug) }}" id="benzerini-iste"
          class="w-full max-w-lg rounded-t-3xl bg-paper p-6 shadow-xl sm:rounded-3xl" @click.outside="open = false">
        @csrf
        <div class="mb-4 flex items-start justify-between gap-4">
            <h2 class="font-hand text-2xl">benzerini iste</h2>
            <button type="button" @click="open = false" aria-label="Kapat"><x-glyph name="close" class="size-5" /></button>
        </div>

        {{-- Honeypot: hidden from people, filled by bots. --}}
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

        <label for="similar-message" class="label">Nasıl bir parça istersiniz?</label>
        <textarea id="similar-message" name="message" rows="5" maxlength="2000" required class="input"
                  placeholder="Renk, ölçü, adet, ne zamana lazım olduğu…">{{ old('message') }}</textarea>
        @error('message')<p class="field-error">{{ $message }}</p>@enderror

        <label for="similar-contact" class="label mt-4">Telefon veya e-posta</label>
        <input id="similar-contact" name="contact" value="{{ old('contact') }}" required class="input" autocomplete="email">
        @error('contact')<p class="field-error">{{ $message }}</p>@enderror

        <label class="mt-4 flex items-start gap-2 text-sm text-ink-soft">
            <input type="checkbox" name="accept_kvkk" value="1" @checked(old('accept_kvkk')) class="mt-0.5 size-4 shrink-0 rounded border-line text-ink focus:ring-ink">
            <span><a href="{{ url('/sayfa/kvkk-aydinlatma-metni') }}" target="_blank" class="underline">KVKK Aydınlatma Metni</a>’ni okudum.</span>
        </label>
        @error('accept_kvkk')<p class="field-error">{{ $message }}</p>@enderror

        <button class="btn-primary mt-6 w-full">gönder</button>
    </form>
</div>
```

If `x-glyph name="close"` does not exist, check `resources/views/components/glyph.blade.php` for the available names and use the closest (e.g. `x`).

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter="SimilarRequestTest|UniqueShopTest"` → PASS; then `php artisan test` → green.

- [ ] **Step 7: Commit**

```bash
git add -A app resources routes database tests
git commit -m "Replace the WhatsApp link with a Benzerini iste request form"
```

---

### Task 4: Talepler admin page, piece creation and payment link

**Files:**
- Create: `app/Filament/Resources/SimilarRequests/SimilarRequestResource.php`, `…/Pages/ListSimilarRequests.php`, `…/Pages/ViewSimilarRequest.php`, `…/SimilarRequestsSummary.php`, `app/Mail/SimilarPieceReady.php`, `resources/views/mail/similar-piece-ready.blade.php`, `tests/Feature/AdminSimilarRequestsTest.php`
- Modify: `app/Filament/Resources/Products/Pages/EditProduct.php` (send-link action), `app/Services/Orders/ReservationService.php` (`markSold` completes requests), `app/Models/Product.php` (`similarRequest()` relation)

**Interfaces:**
- Consumes: `SimilarRequest`, `RequestStatus` (Task 3); `Human`, `PageSummary`, `ProductResource` (round 1).
- Produces: `Product::similarRequest(): HasOne` (request whose `created_product_id` is this product); `SimilarRequestsSummary::stats(): array{new:int, open:int, done_month:int}`; resource slug `talepler`; actions `createPiece` (view page), `sendLink` (product edit page).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AdminSimilarRequestsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Enums\RequestStatus;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\SimilarRequests\Pages\ListSimilarRequests;
use App\Filament\Resources\SimilarRequests\Pages\ViewSimilarRequest;
use App\Filament\Resources\SimilarRequests\SimilarRequestsSummary;
use App\Mail\SimilarPieceReady;
use App\Models\Product;
use App\Models\SimilarRequest;
use App\Models\User;
use App\Services\Orders\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSimilarRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAs(User::factory()->create());
    }

    private function request(array $attributes = []): SimilarRequest
    {
        return SimilarRequest::query()->create($attributes + [
            'product_id' => Product::factory()->unique()->sold()->create(['name' => 'Kobalt Kupa', 'materials' => 'Stoneware'])->id,
            'message' => "Aynısından yeşil olsun.\n2 tane.",
            'contact' => 'ayse@example.com',
            'contact_type' => 'email',
        ]);
    }

    public function test_list_page_with_summary_and_tabs(): void
    {
        $new = $this->request();
        $open = $this->request(['status' => RequestStatus::InProgress]);
        $this->request(['status' => RequestStatus::Done]);

        $this->assertSame(['new' => 1, 'open' => 1, 'done_month' => 1], SimilarRequestsSummary::stats());
        $this->get('/yonetim/talepler')->assertOk()->assertSee('1 yeni talep')->assertSee('Aynısından yeşil olsun.');

        Livewire::test(ListSimilarRequests::class)
            ->assertCanSeeTableRecords([$new])->assertCanNotSeeTableRecords([$open])
            ->set('activeTab', 'open')->assertCanSeeTableRecords([$open]);
    }

    public function test_view_page_changes_status_and_note(): void
    {
        $request = $this->request(['contact' => '+905321234567', 'contact_type' => 'phone']);

        $this->get('/yonetim/talepler/'.$request->id)->assertOk()
            ->assertSee('WhatsApp')->assertSee('https://wa.me/905321234567', false);

        Livewire::test(ViewSimilarRequest::class, ['record' => $request->id])
            ->callAction('changeStatus', data: ['status' => 'contacted'])
            ->callAction('note', data: ['admin_note' => 'Salı arayacağım']);

        $this->assertSame(RequestStatus::Contacted, $request->fresh()->status);
        $this->assertSame('Salı arayacağım', $request->fresh()->admin_note);
    }

    public function test_create_piece_makes_a_link_only_draft_linked_to_the_request(): void
    {
        $request = $this->request();

        Livewire::test(ViewSimilarRequest::class, ['record' => $request->id])->callAction('createPiece');

        $piece = $request->fresh()->createdProduct;
        $this->assertNotNull($piece);
        $this->assertSame(ProductStatus::Draft, $piece->status);
        $this->assertSame(ProductVisibility::Unlisted, $piece->visibility);
        $this->assertSame('Stoneware', $piece->materials);
        $this->assertSame([], $piece->images ?? []);
        $this->assertNull($piece->sold_at);
        $this->assertSame(1, $piece->stock);
    }

    // Review focus 2
    public function test_send_link_by_email_publishes_the_piece_and_moves_request_on(): void
    {
        $request = $this->request();
        $piece = Product::factory()->unique()->create(['status' => ProductStatus::Draft, 'visibility' => ProductVisibility::Unlisted]);
        $request->update(['created_product_id' => $piece->id]);

        Livewire::test(EditProduct::class, ['record' => $piece->slug])->callAction('sendLink');

        $this->assertSame(ProductStatus::Published, $piece->fresh()->status);
        $this->assertSame(ProductVisibility::Unlisted, $piece->fresh()->visibility);
        $this->assertSame(RequestStatus::InProgress, $request->fresh()->status);
        Mail::assertQueued(SimilarPieceReady::class, fn ($mail) => $mail->hasTo('ayse@example.com'));
        $this->get($piece->fresh()->url())->assertOk();
    }

    public function test_send_link_for_phone_contact_opens_whatsapp(): void
    {
        $request = $this->request(['contact' => '+905321234567', 'contact_type' => 'phone']);
        $piece = Product::factory()->unique()->create(['status' => ProductStatus::Draft, 'visibility' => ProductVisibility::Unlisted]);
        $request->update(['created_product_id' => $piece->id]);

        Livewire::test(EditProduct::class, ['record' => $piece->slug])
            ->callAction('sendLink')
            ->assertDispatched('open-url', url: $request->fresh()->whatsappUrl('Merhaba, istediğiniz parça hazır: '.$piece->fresh()->url()));

        $this->assertSame(RequestStatus::InProgress, $request->fresh()->status);
        Mail::assertNotQueued(SimilarPieceReady::class);
    }

    // Review focus 3
    public function test_request_completes_when_its_piece_sells_unless_cancelled(): void
    {
        $active = $this->request(['status' => RequestStatus::InProgress]);
        $cancelled = $this->request(['status' => RequestStatus::Cancelled]);
        $pieceA = Product::factory()->unique()->create();
        $pieceB = Product::factory()->unique()->create();
        $active->update(['created_product_id' => $pieceA->id]);
        $cancelled->update(['created_product_id' => $pieceB->id]);

        $order = \App\Models\Order::factory()->create();
        foreach ([$pieceA, $pieceB] as $piece) {
            $order->items()->create(['product_id' => $piece->id, 'product_type' => 'unique', 'name' => $piece->name, 'unit_price' => 1000, 'quantity' => 1, 'line_total' => 1000]);
        }
        app(ReservationService::class)->markSold($order->fresh('items'));

        $this->assertSame(RequestStatus::Done, $active->fresh()->status);
        $this->assertSame(RequestStatus::Cancelled, $cancelled->fresh()->status);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=AdminSimilarRequestsTest`
Expected: FAIL — `SimilarRequestsSummary` class not found.

- [ ] **Step 3: Model relation and auto-complete**

`app/Models/Product.php`:

```php
    /** The "Benzerini iste" request this piece was made for, if any. */
    public function similarRequest(): HasOne
    {
        return $this->hasOne(SimilarRequest::class, 'created_product_id');
    }
```

(add `use Illuminate\Database\Eloquent\Relations\HasOne;`).

`ReservationService::markSold()` — inside the `if ($ids->isNotEmpty())` block, after the update:

```php
            SimilarRequest::query()->whereIn('created_product_id', $ids)
                ->whereNotIn('status', [RequestStatus::Done, RequestStatus::Cancelled])
                ->update(['status' => RequestStatus::Done]);
```

(add `use App\Enums\RequestStatus;` and `use App\Models\SimilarRequest;`).

- [ ] **Step 4: Summary and resource**

`app/Filament/Resources/SimilarRequests/SimilarRequestsSummary.php`:

```php
<?php

namespace App\Filament\Resources\SimilarRequests;

use App\Enums\RequestStatus;
use App\Filament\Support\PageSummary;
use App\Models\SimilarRequest;
use Illuminate\Support\HtmlString;

final class SimilarRequestsSummary
{
    /** @return array{new: int, open: int, done_month: int} */
    public static function stats(): array
    {
        return [
            'new' => SimilarRequest::query()->where('status', RequestStatus::New)->count(),
            'open' => SimilarRequest::query()->whereIn('status', RequestStatus::open())->count(),
            'done_month' => SimilarRequest::query()->where('status', RequestStatus::Done)->where('updated_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    public static function html(): HtmlString
    {
        $s = self::stats();

        return PageSummary::make([
            [$s['new'] ? $s['new'].' yeni talep' : 'Yeni talep yok'],
            [$s['open'] ? $s['open'].' devam ediyor' : null],
            [$s['done_month'] ? 'bu ay '.$s['done_month'].' tamamlandı' : null],
        ]);
    }
}
```

`app/Filament/Resources/SimilarRequests/SimilarRequestResource.php`:

```php
<?php

namespace App\Filament\Resources\SimilarRequests;

use App\Enums\RequestStatus;
use App\Filament\Resources\SimilarRequests\Pages\ListSimilarRequests;
use App\Filament\Resources\SimilarRequests\Pages\ViewSimilarRequest;
use App\Filament\Support\NavigationGroup;
use App\Models\SimilarRequest;
use App\Support\Human;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class SimilarRequestResource extends Resource
{
    protected static ?string $model = SimilarRequest::class;

    protected static ?string $slug = 'talepler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Orders;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'talep';

    protected static ?string $pluralModelLabel = 'talepler';

    protected static ?string $navigationLabel = 'Talepler';

    public static function getNavigationBadge(): ?string
    {
        $count = SimilarRequest::query()->where('status', RequestStatus::New)->count();

        return $count ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function statusHtml(SimilarRequest $record): HtmlString
    {
        $dot = match ($record->status->getColor()) {
            'warning' => 'bg-warning-500',
            'success' => 'bg-success-500',
            default => 'bg-gray-400',
        };

        return new HtmlString('<span class="inline-flex items-center gap-2"><span class="size-2 shrink-0 rounded-full '.$dot.'"></span>'.e($record->status->getLabel()).'</span>');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('product'))
            ->columns([
                ImageColumn::make('cover')->label('')
                    ->state(fn (SimilarRequest $record) => $record->product?->coverImage())
                    ->disk('public')->imageSize(48)->square(),
                TextColumn::make('product.name')->label('Parça')
                    ->formatStateUsing(fn (SimilarRequest $record) => $record->product?->name.' gibi')
                    ->description(fn (SimilarRequest $record) => $record->contact),
                TextColumn::make('message')->label('Talep')
                    ->formatStateUsing(fn (string $state) => Str::limit(Str::before($state, "\n") ?: $state, 70))
                    ->searchable(),
                TextColumn::make('status')->label('Durum')
                    ->formatStateUsing(fn (SimilarRequest $record) => self::statusHtml($record))->html(),
                TextColumn::make('created_at')->label('Geldi')->sortable()
                    ->formatStateUsing(fn (SimilarRequest $record) => Human::date($record->created_at)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSimilarRequests::route('/'),
            'view' => ViewSimilarRequest::route('/{record}'),
        ];
    }
}
```

The sales group is `NavigationGroup::Orders` (label "Satış"). Check `OrderResource::$navigationSort` and keep Talepler right after it.

`…/Pages/ListSimilarRequests.php`:

```php
<?php

namespace App\Filament\Resources\SimilarRequests\Pages;

use App\Enums\RequestStatus;
use App\Filament\Resources\SimilarRequests\SimilarRequestResource;
use App\Filament\Resources\SimilarRequests\SimilarRequestsSummary;
use App\Models\SimilarRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListSimilarRequests extends ListRecords
{
    protected static string $resource = SimilarRequestResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return SimilarRequestsSummary::html();
    }

    public function getTabs(): array
    {
        $tab = fn (string $label, array $statuses) => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $statuses))
            ->badge(fn () => SimilarRequest::query()->whereIn('status', $statuses)->count() ?: null);

        return [
            'new' => $tab('Yeni', [RequestStatus::New]),
            'open' => $tab('Devam eden', RequestStatus::open()),
            'done' => $tab('Tamamlanan', [RequestStatus::Done]),
            'cancelled' => $tab('İptal', [RequestStatus::Cancelled]),
            'all' => Tab::make('Tümü'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'new';
    }
}
```

`…/Pages/ViewSimilarRequest.php`:

```php
<?php

namespace App\Filament\Resources\SimilarRequests\Pages;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Enums\RequestStatus;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\SimilarRequests\SimilarRequestResource;
use App\Models\Product;
use App\Models\SimilarRequest;
use App\Support\Human;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ViewSimilarRequest extends ViewRecord
{
    protected static string $resource = SimilarRequestResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->product?->name.' gibi bir parça';
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->schema([
                Section::make('Talep')->schema([
                    TextEntry::make('message')->hiddenLabel()->extraAttributes(['class' => 'whitespace-pre-line']),
                    TextEntry::make('created_at')->label('Geldi')
                        ->formatStateUsing(fn (SimilarRequest $record) => Human::date($record->created_at)),
                ]),
                Section::make('Örnek parça')->schema([
                    TextEntry::make('product.name')->label('Parça')
                        ->url(fn (SimilarRequest $record) => $record->product ? ProductResource::getUrl('edit', ['record' => $record->product]) : null),
                    TextEntry::make('createdProduct.name')->label('Bu talep için yapılan parça')->placeholder('Henüz yok')
                        ->url(fn (SimilarRequest $record) => $record->createdProduct ? ProductResource::getUrl('edit', ['record' => $record->createdProduct]) : null),
                ]),
            ]),
            Grid::make(1)->columnSpan(1)->schema([
                Section::make('İletişim')->schema([
                    TextEntry::make('contact')->hiddenLabel()->copyable()
                        ->suffix(fn (SimilarRequest $record) => $record->isEmail() ? null : ' · WhatsApp')
                        ->url(fn (SimilarRequest $record) => $record->whatsappUrl() ?? $record->mailtoUrl('Pürüz Atölye · talebiniz'), shouldOpenInNewTab: true)
                        ->helperText(fn (SimilarRequest $record) => $record->isEmail() ? 'E-posta göndermek için tıklayın' : 'WhatsApp’ta yazmak için tıklayın'),
                ]),
                Section::make('Durum')->schema([
                    TextEntry::make('status')->hiddenLabel()
                        ->formatStateUsing(fn (SimilarRequest $record) => SimilarRequestResource::statusHtml($record))->html(),
                ]),
                Section::make('Notlarım')->schema([
                    TextEntry::make('admin_note')->hiddenLabel()->placeholder('Not yok')->extraAttributes(['class' => 'whitespace-pre-line']),
                ]),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createPiece')
                ->label('Parçayı oluştur')
                ->icon('heroicon-o-sparkles')
                ->visible(fn (SimilarRequest $record) => ! $record->created_product_id && $record->product)
                ->requiresConfirmation()
                ->modalDescription('Örnek parçanın metinleriyle, fotoğrafsız ve “sadece link ile” görünür bir taslak oluşturulur.')
                ->action(function (SimilarRequest $record) {
                    $original = $record->product;
                    $piece = $original->replicate(['view_count', 'sku', 'images', 'og_image', 'sold_at', 'published_at', 'slug', 'stock', 'is_featured']);
                    $piece->forceFill([
                        'type' => ProductType::Unique,
                        'name' => $original->name.' (özel)',
                        'slug' => $original->slug.'-ozel-'.Str::lower(Str::random(4)),
                        'status' => ProductStatus::Draft,
                        'visibility' => ProductVisibility::Unlisted,
                        'images' => [],
                    ])->save();
                    $piece->categories()->sync($original->categories()->pluck('categories.id'));
                    $record->update(['created_product_id' => $piece->id]);

                    $this->redirect(ProductResource::getUrl('edit', ['record' => $piece]));
                }),
            Action::make('changeStatus')
                ->label('Durumu değiştir')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->fillForm(fn (SimilarRequest $record) => ['status' => $record->status->value])
                ->schema([Select::make('status')->label('Durum')->options(RequestStatus::class)->required()])
                ->action(function (SimilarRequest $record, array $data) {
                    $record->update(['status' => $data['status']]);
                    Notification::make()->success()->title('Durum güncellendi')->send();
                }),
            Action::make('note')
                ->label('Not')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->fillForm(fn (SimilarRequest $record) => ['admin_note' => $record->admin_note])
                ->schema([Textarea::make('admin_note')->label('Sadece sizin gördüğünüz not')->rows(4)])
                ->action(function (SimilarRequest $record, array $data) {
                    $record->update(['admin_note' => $data['admin_note']]);
                    Notification::make()->success()->title('Not kaydedildi')->send();
                }),
        ];
    }
}
```

Ruling-ready note: the status picker and notes are header actions (one click from the right-column cards that show their values); this satisfies "status picker and notes on the right" in spirit and keeps Filament's standard modal flow.

- [ ] **Step 5: Send-link action and mail**

`app/Mail/SimilarPieceReady.php` — same shape as `AdminNewSimilarRequest`, constructor `public SimilarRequest $similar, public Product $piece`, subject `'İstediğiniz parça hazır · '.config('app.name')`, view `mail.similar-piece-ready`.

`resources/views/mail/similar-piece-ready.blade.php`:

```blade
@extends('mail.layout')

@section('body')
    <p style="font-size:22px;margin:0 0 12px;">İstediğiniz parça hazır</p>
    <p style="margin:0 0 12px;">“{{ $similar->product->name }}” benzeri parça için talebinizi hazırladık. Aşağıdaki linkten görüp satın alabilirsiniz.</p>
    @include('mail.partials-button', ['url' => $piece->url(), 'label' => 'Parçayı gör'])
@endsection
```

`app/Filament/Resources/Products/Pages/EditProduct.php` — first in `getHeaderActions()`:

```php
            Action::make('sendLink')
                ->label('Müşteriye linki gönder')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (Product $record) => $record->similarRequest && $record->sold_at === null)
                ->requiresConfirmation()
                ->modalDescription(fn (Product $record) => $record->similarRequest->isEmail()
                    ? $record->similarRequest->contact.' adresine parçanın linki gönderilecek. Taslaksa yayına alınır (sadece link ile).'
                    : 'WhatsApp açılacak; mesajı siz gönderirsiniz. Taslaksa yayına alınır (sadece link ile).')
                ->action(function (Product $record, $livewire) {
                    $request = $record->similarRequest;
                    // The customer must be able to open the link: publish, but keep it out of listings.
                    $record->update(['status' => ProductStatus::Published, 'visibility' => ProductVisibility::Unlisted, 'published_at' => $record->published_at ?? now()]);
                    $request->update(['status' => RequestStatus::InProgress]);

                    if ($request->isEmail()) {
                        Mail::to($request->contact)->send(new SimilarPieceReady($request, $record));
                        Notification::make()->success()->title('Link e-postayla gönderildi')->send();

                        return;
                    }

                    $livewire->dispatch('open-url', url: $request->whatsappUrl('Merhaba, istediğiniz parça hazır: '.$record->url()));
                }),
```

(imports: `App\Enums\ProductStatus`, `App\Enums\ProductVisibility`, `App\Enums\RequestStatus`, `App\Mail\SimilarPieceReady`, `App\Models\Product`, `Filament\Notifications\Notification`, `Illuminate\Support\Facades\Mail`).

Listen for `open-url` once for the panel: in `app/Providers/Filament/AdminPanelProvider.php` add

```php
            ->renderHook(PanelsRenderHook::BODY_END, fn () => new HtmlString(
                '<script>window.addEventListener("open-url", e => window.open(e.detail.url, "_blank", "noopener"))</script>'))
```

(imports `Filament\View\PanelsRenderHook`, `Illuminate\Support\HtmlString`).

- [ ] **Step 6: Run tests** — `php artisan test --filter=AdminSimilarRequestsTest` → PASS; `php artisan test` → green.

- [ ] **Step 7: Commit**

```bash
git add -A app resources tests
git commit -m "Add the Talepler admin page with piece creation and payment links"
```

---

### Task 5: Orders list and detail page

**Files:**
- Create: `app/Filament/Resources/Orders/OrderStatusText.php`, `app/Filament/Resources/Orders/OrdersSummary.php`, `resources/views/filament/order-next-step.blade.php`, `tests/Feature/AdminOrdersTest.php`
- Modify: `app/Filament/Resources/Orders/Tables/OrdersTable.php`, `app/Filament/Resources/Orders/Pages/ListOrders.php`, `app/Filament/Resources/Orders/Pages/ViewOrder.php`, `app/Filament/Resources/Orders/OrderActions.php` (add `markDelivered()`), `app/Filament/Resources/Orders/Schemas/OrderInfolist.php`, `resources/views/filament/order-items.blade.php`, `resources/views/filament/order-history.blade.php`

**Interfaces:**
- Produces: `OrderStatusText::for(Order $order): array{0: string, 1: string}`, `OrderStatusText::html(Order $order): HtmlString`, `OrderStatusText::nextStep(Order $order): ?array{0: string, 1: ?string}` (sentence, action name or null); `OrdersSummary::stats(): array{to_pack:int, oldest_days:?int, shipped:int, week_count:int, week_total:int, unpaid:int}`; `OrderActions::markDelivered(): Action` named `markDelivered`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AdminOrdersTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\CargoCompany;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderStatusText;
use App\Filament\Resources\Orders\OrdersSummary;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 15:00', 'Europe/Istanbul'));
        $this->actingAs(User::factory()->create());
    }

    private function order(array $attributes = [], string $item = 'Kobalt Kupa'): Order
    {
        $order = Order::factory()->create($attributes);
        $product = Product::factory()->unique()->create(['name' => $item]);
        $order->items()->create(['product_id' => $product->id, 'product_type' => 'unique', 'name' => $item, 'unit_price' => 50000, 'quantity' => 1, 'line_total' => 50000, 'charged_total' => 50000, 'stock_reserved' => true, 'payment_transaction_id' => '1']);

        return $order->fresh('items');
    }

    // Review focus 5
    public function test_status_phrases(): void
    {
        $fresh = $this->order(['created_at' => now()->subDays(9), 'paid_at' => now()->subDay()]);
        $late = $this->order(['paid_at' => now()->subDays(3)]);
        $shipped = $this->order(['status' => OrderStatus::Shipped, 'cargo_company' => CargoCompany::Aras, 'shipped_at' => now()->subDays(3)]);
        $unpaid = $this->order(['status' => OrderStatus::PendingPayment, 'paid_at' => null, 'reserved_until' => now()->addMinutes(12)]);
        $expired = $this->order(['status' => OrderStatus::PendingPayment, 'paid_at' => null, 'reserved_until' => now()->subMinutes(5)]);

        $this->assertSame(['success', 'Hazırlanacak · 1 gündür'], OrderStatusText::for($fresh));
        $this->assertSame(['warning', 'Hazırlanacak · 3 gündür'], OrderStatusText::for($late));
        $this->assertSame(['success', 'Kargoda · Aras Kargo · 3 gün'], OrderStatusText::for($shipped));
        $this->assertSame(['gray', 'Ödeme bekleniyor · 12 dk kaldı'], OrderStatusText::for($unpaid));
        $this->assertSame(['gray', 'Ödeme bekleniyor · süresi doldu'], OrderStatusText::for($expired));
    }

    public function test_summary_numbers(): void
    {
        $this->order(['paid_at' => now()->subDays(2), 'total' => 59000]);
        $this->order(['status' => OrderStatus::Shipped, 'paid_at' => now()->subDays(1), 'total' => 100000]);
        $this->order(['status' => OrderStatus::PendingPayment, 'paid_at' => null, 'reserved_until' => now()->addMinutes(5)]);

        $stats = OrdersSummary::stats();
        $this->assertSame(1, $stats['to_pack']);
        $this->assertSame(2, $stats['oldest_days']);
        $this->assertSame(1, $stats['shipped']);
        $this->assertSame(2, $stats['week_count']);
        $this->assertSame(159000, $stats['week_total']);
        $this->assertSame(1, $stats['unpaid']);
        $this->get('/yonetim/siparisler')->assertOk()->assertSee('1 sipariş hazırlanacak');
    }

    public function test_ship_from_the_list_and_filter_by_piece(): void
    {
        $kobalt = $this->order([], 'Kobalt Kupa');
        $vazo = $this->order([], 'Uzun Vazo');

        Livewire::test(ListOrders::class)
            ->filterTable('item', ['name' => 'vazo'])
            ->assertCanSeeTableRecords([$vazo])->assertCanNotSeeTableRecords([$kobalt]);

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('ship')->table($kobalt), data: ['cargo_company' => 'aras', 'tracking_number' => 'AR1', 'notify' => false]);

        $this->assertSame(OrderStatus::Shipped, $kobalt->fresh()->status);
    }

    public function test_next_step_bar(): void
    {
        $toPack = $this->order();
        $shipped = $this->order(['status' => OrderStatus::Shipped, 'cargo_company' => CargoCompany::Aras, 'shipped_at' => now()->subDay()]);

        $this->assertSame(['Paketleyip kargoya verin', 'ship'], OrderStatusText::nextStep($toPack));
        $this->assertSame(['Kargoda, teslim edildiğinde işaretleyin', 'markDelivered'], OrderStatusText::nextStep($shipped));
        $this->assertNull(OrderStatusText::nextStep($this->order(['status' => OrderStatus::Delivered])));

        $this->get('/yonetim/siparisler/'.$toPack->id)->assertOk()->assertSee('Paketleyip kargoya verin');

        Livewire::test(ViewOrder::class, ['record' => $shipped->id])->callAction('markDelivered');
        $this->assertSame(OrderStatus::Delivered, $shipped->fresh()->status);
    }
}
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter=AdminOrdersTest` → FAIL (`OrderStatusText` not found).

- [ ] **Step 3: `OrderStatusText` and `OrdersSummary`**

`app/Filament/Resources/Orders/OrderStatusText.php`:

```php
<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Human;
use Illuminate\Support\HtmlString;

/** Readable order status with what happens next, e.g. "Hazırlanacak · 2 gündür". */
final class OrderStatusText
{
    /** Orders waiting longer than this to be packed turn amber. */
    public const LATE_DAYS = 2;

    /** @return array{0: string, 1: string} */
    public static function for(Order $order): array
    {
        return match ($order->status) {
            OrderStatus::Paid, OrderStatus::Preparing => self::toPack($order),
            OrderStatus::Shipped => ['success', collect(['Kargoda', $order->cargo_company?->getLabel(), $order->shipped_at ? Human::days($order->shipped_at).' gün' : null])->filter()->implode(' · ')],
            OrderStatus::Delivered => ['gray', 'Teslim edildi'],
            OrderStatus::Completed => ['gray', 'Tamamlandı'],
            OrderStatus::Confirmed => ['success', self::workshopPhrase($order)],
            OrderStatus::PendingPayment => ['gray', 'Ödeme bekleniyor · '.self::minutesLeft($order)],
            OrderStatus::PaymentFailed => ['danger', 'Ödeme başarısız'],
            OrderStatus::Refunded => ['gray', 'İade edildi'],
            OrderStatus::Cancelled => ['gray', 'İptal'],
        };
    }

    public static function html(Order $order): HtmlString
    {
        [$color, $text] = self::for($order);
        $dot = match ($color) {
            'success' => 'bg-success-500',
            'warning' => 'bg-warning-500',
            'danger' => 'bg-danger-500',
            default => 'bg-gray-400',
        };

        return new HtmlString('<span class="inline-flex items-center gap-2"><span class="size-2 shrink-0 rounded-full '.$dot.'"></span>'.e($text).'</span>');
    }

    /** @return array{0: string, 1: ?string}|null sentence and the action that does it */
    public static function nextStep(Order $order): ?array
    {
        return match (true) {
            in_array($order->status, [OrderStatus::Paid, OrderStatus::Preparing], true) && $order->requires_shipping => ['Paketleyip kargoya verin', 'ship'],
            $order->status === OrderStatus::Shipped => ['Kargoda, teslim edildiğinde işaretleyin', 'markDelivered'],
            $order->status === OrderStatus::PendingPayment => ['Ödeme bekleniyor — '.self::minutesLeft($order).'; ödeme gelmezse rezervasyon bırakılır', null],
            default => null,
        };
    }

    /** @return array{0: string, 1: string} */
    private static function toPack(Order $order): array
    {
        $days = Human::days($order->paid_at ?? $order->created_at);

        return [$days > self::LATE_DAYS ? 'warning' : 'success', 'Hazırlanacak · '.($days === 0 ? 'bugünden beri' : $days.' gündür')];
    }

    private static function workshopPhrase(Order $order): string
    {
        $session = $order->items->firstWhere('workshop_session_id')?->session;

        return $session ? 'Atölye · '.$session->starts_at->translatedFormat('j M D H:i') : 'Atölye rezervasyonu';
    }

    private static function minutesLeft(Order $order): string
    {
        if (! $order->reserved_until || $order->reserved_until->isPast()) {
            return 'süresi doldu';
        }

        return max(1, (int) ceil(now()->diffInMinutes($order->reserved_until))).' dk kaldı';
    }
}
```

`app/Filament/Resources/Orders/OrdersSummary.php`:

```php
<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Support\PageSummary;
use App\Models\Order;
use App\Support\Human;
use App\Support\Money;
use Illuminate\Support\HtmlString;

final class OrdersSummary
{
    public static function stats(): array
    {
        $toPack = Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing]);
        $oldest = (clone $toPack)->oldest('paid_at')->value('paid_at');
        $week = Order::query()->paid()->where('paid_at', '>=', now()->startOfWeek());

        return [
            'to_pack' => $toPack->count(),
            'oldest_days' => $oldest ? Human::days(\Illuminate\Support\Carbon::parse($oldest)) : null,
            'shipped' => Order::query()->where('status', OrderStatus::Shipped)->count(),
            'week_count' => $week->count(),
            'week_total' => (int) $week->sum('total') - (int) (clone $week)->sum('refunded_total'),
            'unpaid' => Order::query()->where('status', OrderStatus::PendingPayment)->count(),
        ];
    }

    public static function html(): HtmlString
    {
        $s = self::stats();

        return PageSummary::make([
            [$s['to_pack'] ? $s['to_pack'].' sipariş hazırlanacak' : 'Hazırlanacak sipariş yok',
                $s['oldest_days'] ? '(en eskisi '.$s['oldest_days'].' gündür bekliyor)' : null],
            [$s['shipped'] ? $s['shipped'].' kargoda' : null],
            ['bu hafta '.$s['week_count'].' sipariş', Money::format($s['week_total'])],
            [$s['unpaid'] ? $s['unpaid'].' ödeme bekleniyor' : null],
        ]);
    }
}
```

Note: `startOfWeek()` with Turkish locale starts on Monday. The test travels to Friday 2026-11-20, so orders paid on Wed/Thu count.

- [ ] **Step 4: List page and table**

`ListOrders`: add

```php
    public function getSubheading(): string|Htmlable|null
    {
        return OrdersSummary::html();
    }
```

(imports `Illuminate\Contracts\Support\Htmlable`).

`OrdersTable` columns become:

```php
            ->columns([
                TextColumn::make('number')
                    ->label('Sipariş')
                    ->searchable(['number', 'customer_name', 'customer_email', 'customer_phone'])
                    ->weight('medium')
                    ->description(fn (Order $record) => Human::date($record->created_at)),
                TextColumn::make('customer_name')
                    ->label('Müşteri')
                    ->description(fn (Order $record) => $record->requires_shipping ? $record->shipping_district.' / '.$record->shipping_city : 'Atölye'),
                ImageColumn::make('first_item_cover')->label('')
                    ->state(fn (Order $record) => $record->items->first()?->product?->coverImage())
                    ->disk('public')->imageSize(40)->square(),
                TextColumn::make('items_summary')
                    ->label('Ürünler')
                    ->state(fn (Order $record) => $record->items->first()?->name.($record->items->count() > 1 ? ' +'.($record->items->count() - 1) : ''))
                    ->tooltip(fn (Order $record) => $record->items->map(fn ($i) => $i->quantity.'× '.$i->displayName())->implode("\n")),
                TextColumn::make('total')
                    ->label('Tutar')
                    ->sortable()
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->description(fn (Order $record) => $record->refunded_total ? Money::format($record->refunded_total).' iade' : null),
                TextColumn::make('status')
                    ->label('Durum')
                    ->formatStateUsing(fn (Order $record) => OrderStatusText::html($record))
                    ->html()
                    ->sortable(),
                TextColumn::make('utm_source')
                    ->label('Kaynak')
                    ->formatStateUsing(fn (Order $record) => trim($record->utm_source.' '.($record->utm_medium ? '/ '.$record->utm_medium : '')))
                    ->placeholder('doğrudan')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
```

Make sure the query eager-loads `items.product` and `items.session` (`->modifyQueryUsing(fn (Builder $query) => $query->with(['items.product', 'items.session']))`; replace any existing `with('items')`). Drop the separate `cargo_tracking_number` and `created_at` columns (their info is in the status phrase and the number's description).

Add to `->filters([...])`:

```php
                Filter::make('item')
                    ->label('Ürün içerir')
                    ->schema([TextInput::make('name')->label('Ürün adı')])
                    ->query(fn (Builder $query, array $data) => $query->when(filled($data['name'] ?? null),
                        fn (Builder $q) => $q->whereHas('items', fn (Builder $items) => $items->where('name', 'like', '%'.$data['name'].'%')))),
```

Record actions: `OrderActions::ship()->button()->size('sm'),` then `ViewAction::make()->iconButton(),`.

Imports: `App\Filament\Resources\Orders\OrderStatusText`, `App\Support\Human`, `Filament\Forms\Components\TextInput`, `Filament\Tables\Columns\ImageColumn`.

- [ ] **Step 5: Detail page**

`OrderActions::markDelivered()`:

```php
    public static function markDelivered(): Action
    {
        return Action::make('markDelivered')
            ->label('Teslim edildi')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (Order $record) => $record->status === OrderStatus::Shipped)
            ->requiresConfirmation()
            ->action(function (Order $record) {
                app(OrderStatusService::class)->transition($record, OrderStatus::Delivered, null, true, auth()->user());
                Notification::make()->success()->title('Teslim edildi olarak işaretlendi')->send();
            });
    }
```

`ViewOrder::getHeaderActions()`: add `OrderActions::markDelivered(),` after `OrderActions::ship(),`.

`resources/views/filament/order-next-step.blade.php`:

```blade
@php($step = \App\Filament\Resources\Orders\OrderStatusText::nextStep($getRecord()))
@if ($step)
    <div class="flex items-center gap-3 rounded-lg bg-primary-50 px-4 py-3 text-sm font-medium text-primary-800 dark:bg-primary-500/10 dark:text-primary-300">
        <x-filament::icon icon="heroicon-o-arrow-right-circle" class="size-5" />
        <span>{{ $step[0] }}</span>
        @if ($step[1])<span class="text-primary-600/70 dark:text-primary-300/70">— yukarıdaki “{{ $step[1] === 'ship' ? 'Kargoya ver' : 'Teslim edildi' }}” düğmesini kullanın</span>@endif
    </div>
@endif
```

`OrderInfolist`: first component (before the `Grid` columns), spanning all 3 columns:

```php
                ViewEntry::make('next_step')->hiddenLabel()->view('filament.order-next-step')->columnSpanFull(),
```

In the `Özet` section replace the `created_at` entry with:

```php
                        TextEntry::make('payment_sentence')->label('Ödeme')
                            ->state(fn (Order $record) => $record->paid_at
                                ? collect(['Kart ile ödendi', Human::date($record->paid_at), $record->installment > 1 ? $record->installment.' taksit' : null])->filter()->implode(' · ')
                                : 'Henüz ödenmedi'),
```

and the `source` entry state with:

```php
                            ->state(fn (Order $record) => match (true) {
                                ! $record->utm_source => 'Doğrudan geldi',
                                $record->utm_source === 'instagram' => 'Instagram '.($record->utm_medium ?: '').'’dan geldi',
                                default => ucfirst($record->utm_source).'’dan geldi',
                            }),
```

Change the `status` entry from `->badge()` to `->formatStateUsing(fn (Order $record) => OrderStatusText::html($record))->html()`.

`resources/views/filament/order-items.blade.php` — inside the product `<td>`, before the name, add a thumbnail and make the name a link:

```blade
                        <div class="flex gap-3">
                            @if ($cover = $item->product?->coverImage())
                                <img src="{{ \App\Support\ProductImages::url($cover, 200) }}" alt="" class="size-10 shrink-0 rounded object-cover">
                            @endif
                            <div>
                                <p class="font-medium">
                                    @if ($item->product)
                                        <a href="{{ \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $item->product]) }}" class="hover:underline">{{ $item->name }}</a>
                                    @else
                                        {{ $item->name }}
                                    @endif
                                </p>
                                {{-- existing variant/session/participants/sku/refund lines move inside this div unchanged --}}
                            </div>
                        </div>
```

`resources/views/filament/order-history.blade.php` — replace the date formatting (e.g. `->format('d.m.Y H:i')`) with `{{ \App\Support\Human::date($history->created_at) }}`; check the variable name in the file.

- [ ] **Step 6: Run tests** — `php artisan test --filter=AdminOrdersTest` → PASS; `php artisan test` → green (AdminPanelTest's ship/refund tests must still pass).

- [ ] **Step 7: Commit**

```bash
git add -A app resources tests
git commit -m "Restyle orders around readable status and the next step"
```

---

### Task 6: Dashboard

**Files:**
- Create: `app/Filament/Widgets/Greeting.php`, `app/Filament/Widgets/Waiting.php`, `app/Filament/Widgets/RecentSales.php`, views `resources/views/filament/widgets/greeting.blade.php`, `waiting.blade.php`, `recent-sales.blade.php`, `tests/Feature/AdminDashboardTest.php`
- Modify: `app/Filament/Widgets/SalesOverview.php`, `app/Filament/Widgets/UpcomingWorkshops.php`
- Delete: `app/Filament/Widgets/LatestOrders.php`, `app/Filament/Widgets/SalesBySource.php`
- Modify: `tests/Feature/AdminPanelTest.php` (`test_dashboard_counts_pieces_for_sale_instead_of_low_stock` keeps passing with new texts — update assertions as below)

**Interfaces:**
- Consumes: `OrdersSummary::stats()` (Task 5), `SimilarRequestsSummary::stats()` (Task 4), `ProductsSummary::stats()` (round 1), `Human`.
- Produces: `Greeting::sentence(): string`; `Waiting::items(): Collection<array{type: 'order'|'request', title: string, meta: string, since: CarbonInterface, url: string, action: ?string}>`; `RecentSales::sourceLine(): ?string`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AdminDashboardTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Widgets\Greeting;
use App\Filament\Widgets\RecentSales;
use App\Filament\Widgets\Waiting;
use App\Models\Order;
use App\Models\Product;
use App\Models\SimilarRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:30', 'Europe/Istanbul'));
        $this->actingAs(User::factory()->create());
    }

    public function test_greeting_sentence(): void
    {
        $this->assertSame('Günaydın. Bekleyen iş yok.', Greeting::sentence());

        Order::factory()->create(['paid_at' => now()->subDay(), 'total' => 485000]);
        SimilarRequest::query()->create(['product_id' => Product::factory()->unique()->sold()->create()->id, 'message' => 'x', 'contact' => 'a@b.co', 'contact_type' => 'email']);

        $this->assertSame('Günaydın. 1 sipariş paketlenmeyi, 1 yeni talep yanıt bekliyor. Bu hafta 4.850 ₺ satış.', Greeting::sentence());

        $this->travelTo(CarbonImmutable::parse('2026-11-20 20:00', 'Europe/Istanbul'));
        $this->assertStringStartsWith('İyi akşamlar.', Greeting::sentence());
    }

    public function test_waiting_list_mixes_orders_and_requests_oldest_first(): void
    {
        $order = Order::factory()->create(['paid_at' => now()->subDays(2), 'customer_name' => 'Ayşe Yılmaz']);
        $request = SimilarRequest::query()->forceCreate(['product_id' => Product::factory()->unique()->sold()->create(['name' => 'Kobalt Kupa'])->id, 'message' => 'x', 'contact' => 'a@b.co', 'contact_type' => 'email', 'created_at' => now()->subDay()]);
        Order::factory()->create(['status' => OrderStatus::Shipped]);

        $items = Waiting::items();

        $this->assertSame(['order', 'request'], $items->pluck('type')->all());
        $this->assertStringContainsString($order->number, $items[0]['title']);
        $this->assertSame('Kobalt Kupa gibi', $items[1]['title']);
    }

    public function test_dashboard_renders_and_source_line(): void
    {
        Order::factory()->create(['paid_at' => now()->subDay(), 'utm_source' => 'instagram', 'utm_medium' => 'story', 'total' => 60000]);
        Order::factory()->create(['paid_at' => now()->subDay(), 'total' => 40000]);

        $this->assertSame('Son 30 gün: %60 instagram story, %40 doğrudan', RecentSales::sourceLine());

        $this->get('/yonetim')->assertOk()
            ->assertSee('Günaydın')
            ->assertSee('Bekleyenler')
            ->assertSee('Yaklaşan atölyeler')
            ->assertSee('Son satışlar')
            ->assertDontSee('Satış kaynağı (son 30 gün)');
    }
}
```

Update `AdminPanelTest::test_dashboard_counts_pieces_for_sale_instead_of_low_stock` assertions: keep `assertSee('Satışta')`, `assertSee('7 parça')`-style lines as they are (the Satışta card stays), keep `assertDontSee('Az stok')`.

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter=AdminDashboardTest` → FAIL (`Greeting` not found).

- [ ] **Step 3: Greeting widget**

`app/Filament/Widgets/Greeting.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrdersSummary;
use App\Filament\Resources\SimilarRequests\SimilarRequestsSummary;
use App\Support\Money;
use Filament\Widgets\Widget;

class Greeting extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.greeting';

    public static function sentence(): string
    {
        $hour = (int) now()->timezone(config('app.timezone'))->format('G');
        $hello = match (true) {
            $hour < 12 => 'Günaydın.',
            $hour < 18 => 'İyi günler.',
            default => 'İyi akşamlar.',
        };

        $orders = OrdersSummary::stats();
        $requests = SimilarRequestsSummary::stats();

        $waiting = collect([
            $orders['to_pack'] ? $orders['to_pack'].' sipariş paketlenmeyi' : null,
            $requests['new'] ? $requests['new'].' yeni talep yanıt' : null,
        ])->filter();

        if ($waiting->isEmpty()) {
            return $hello.' Bekleyen iş yok.';
        }

        $sentence = $hello.' '.$waiting->implode(', ').' bekliyor.';

        return $orders['week_total'] > 0 ? $sentence.' Bu hafta '.Money::format($orders['week_total']).' satış.' : $sentence;
    }
}
```

`resources/views/filament/widgets/greeting.blade.php`:

```blade
<x-filament-widgets::widget>
    <p class="text-lg text-gray-700 dark:text-gray-200">{{ \App\Filament\Widgets\Greeting::sentence() }}</p>
</x-filament-widgets::widget>
```

- [ ] **Step 4: Cards (SalesOverview)**

Rewrite `getStats()` in `SalesOverview` to four cards:

```php
    protected function getStats(): array
    {
        $orders = OrdersSummary::stats();
        $requests = SimilarRequestsSummary::stats();
        $pieces = ProductsSummary::stats();

        $lastWeek = (int) Order::query()->paid()->whereBetween('paid_at', [now()->subWeek()->startOfWeek(), now()->subWeek()])->sum('total');
        $change = $lastWeek > 0 ? (int) round(($orders['week_total'] - $lastWeek) / $lastWeek * 100) : null;
        $daily = collect(range(13, 0))->map(fn (int $daysAgo) => (int) Order::query()->paid()
            ->whereDate('paid_at', now()->subDays($daysAgo)->toDateString())->sum('total') / 100)->all();

        return [
            Stat::make('Bu hafta', Money::format($orders['week_total']))
                ->description($orders['week_count'].' sipariş'.($change !== null ? ', geçen haftadan %'.abs($change).($change >= 0 ? ' fazla' : ' az') : ''))
                ->chart($daily)
                ->color('gray'),
            Stat::make('Hazırlanacak', (string) $orders['to_pack'])
                ->description($orders['oldest_days'] !== null ? 'en eskisi '.$orders['oldest_days'].' gündür bekliyor' : 'bekleyen sipariş yok')
                ->color($orders['oldest_days'] > OrderStatusText::LATE_DAYS ? 'warning' : 'gray')
                ->url(OrderResource::getUrl()),
            Stat::make('Talepler', $requests['new'].' yeni')
                ->description($requests['open'].' yapılıyor')
                ->color('gray')
                ->url(SimilarRequestResource::getUrl()),
            Stat::make('Satışta', $pieces['available_count'].' parça')
                ->description($pieces['sold_month_count'] ? 'bu ay '.$pieces['sold_month_count'].' satıldı' : 'bu ay henüz satış yok')
                ->color('gray')
                ->url(ProductResource::getUrl()),
        ];
    }
```

Imports: `OrdersSummary`, `OrderStatusText`, `SimilarRequestsSummary`, `SimilarRequestResource`, `ProductsSummary`, `ProductResource`, `OrderResource`, `Order`, `Money`; remove unused ones. Keep `$sort = 1`.

Note: the Satışta description changes from "bu ay N parça satıldı" to "bu ay N satıldı"; update `AdminPanelTest::test_dashboard_counts_pieces_for_sale_instead_of_low_stock` `assertSee('bu ay 1 parça satıldı')` → `assertSee('bu ay 1 satıldı')`.

- [ ] **Step 5: Waiting, upcoming workshops, recent sales**

`app/Filament/Widgets/Waiting.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\RequestStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SimilarRequests\SimilarRequestResource;
use App\Models\Order;
use App\Models\SimilarRequest;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class Waiting extends Widget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.waiting';

    public static function items(): Collection
    {
        $orders = Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing])->get()
            ->map(fn (Order $order) => [
                'type' => 'order',
                'title' => $order->number.' · '.$order->customer_name,
                'meta' => $order->requires_shipping ? 'Kargoya ver' : 'Hazırla',
                'since' => $order->paid_at ?? $order->created_at,
                'url' => OrderResource::getUrl('view', ['record' => $order]),
                'action' => $order->requires_shipping ? 'Kargoya ver' : null,
            ]);

        $requests = SimilarRequest::query()->where('status', RequestStatus::New)->with('product')->get()
            ->map(fn (SimilarRequest $request) => [
                'type' => 'request',
                'title' => $request->product?->name.' gibi',
                'meta' => 'Talep · '.$request->contact,
                'since' => $request->created_at,
                'url' => SimilarRequestResource::getUrl('view', ['record' => $request]),
                'action' => null,
            ]);

        return $orders->concat($requests)->sortBy('since')->values()->take(8);
    }
}
```

`resources/views/filament/widgets/waiting.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section heading="Bekleyenler">
        @forelse (\App\Filament\Widgets\Waiting::items() as $item)
            <a href="{{ $item['url'] }}" class="flex items-center justify-between gap-3 border-b border-gray-100 py-2.5 text-sm last:border-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5">
                <span>
                    <span class="font-medium text-gray-950 dark:text-white">{{ $item['title'] }}</span>
                    <span class="block text-gray-500 dark:text-gray-400">{{ $item['meta'] }} · {{ \App\Support\Human::date($item['since']) }}</span>
                </span>
                @if ($item['action'])<span class="shrink-0 text-primary-600 dark:text-primary-400">{{ $item['action'] }} →</span>@endif
            </a>
        @empty
            <p class="text-sm text-gray-500">Bekleyen iş yok.</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
```

`UpcomingWorkshops` — set `protected static ?int $sort = 3;`, heading stays "Yaklaşan atölyeler", `->paginated(false)`, query `->limit(5)` (use `->query(WorkshopSession::query()->upcoming()->with('product')->limit(5))`), columns become one text column:

```php
                TextColumn::make('starts_at')->label('')
                    ->formatStateUsing(fn (WorkshopSession $record) => $record->starts_at->translatedFormat('j M D H:i').' · '.$record->product->name.' · '.$record->seats_reserved.'/'.$record->capacity.' dolu'),
```

and remove `->columnSpan` if set so it sits next to Waiting.

`app/Filament/Widgets/RecentSales.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Enums\ProductType;
use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class RecentSales extends Widget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.recent-sales';

    public static function pieces(): Collection
    {
        return Product::query()->where('type', ProductType::Unique)->whereNotNull('sold_at')->latest('sold_at')->limit(5)->get();
    }

    public static function sourceLine(): ?string
    {
        $orders = Order::query()->paid()->where('paid_at', '>=', now()->subDays(30))->get(['utm_source', 'utm_medium', 'total']);
        $sum = $orders->sum('total');
        if ($sum <= 0) {
            return null;
        }

        return 'Son 30 gün: '.$orders
            ->groupBy(fn (Order $o) => $o->utm_source ? trim($o->utm_source.' '.($o->utm_medium ?? '')) : 'doğrudan')
            ->map(fn ($group) => $group->sum('total'))
            ->sortDesc()
            ->map(fn (int $total, string $source) => '%'.round($total / $sum * 100).' '.$source)
            ->implode(', ');
    }
}
```

`resources/views/filament/widgets/recent-sales.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section heading="Son satışlar">
        <div class="grid gap-3 sm:grid-cols-5">
            @forelse (\App\Filament\Widgets\RecentSales::pieces() as $piece)
                <a href="{{ \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $piece]) }}" class="text-sm">
                    @if ($cover = $piece->coverImage())
                        <img src="{{ \App\Support\ProductImages::url($cover, 400) }}" alt="" class="aspect-square w-full rounded-lg object-cover">
                    @endif
                    <span class="mt-1.5 block font-medium text-gray-950 dark:text-white">{{ $piece->name }}</span>
                    <span class="block text-gray-500 dark:text-gray-400">{{ \App\Support\Money::format($piece->price) }} · {{ \App\Support\Human::day($piece->sold_at) }} · {{ \App\Support\Human::took($piece->published_at ?? $piece->created_at, $piece->sold_at) }}</span>
                </a>
            @empty
                <p class="text-sm text-gray-500">Henüz satış yok.</p>
            @endforelse
        </div>
        @if ($line = \App\Filament\Widgets\RecentSales::sourceLine())
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ $line }}</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
```

Delete `LatestOrders.php` and `SalesBySource.php`.

- [ ] **Step 6: Run tests** — `php artisan test --filter="AdminDashboardTest|AdminPanelTest"` → PASS; `php artisan test` → green.

- [ ] **Step 7: Commit**

```bash
git add -A app resources tests
git commit -m "Rebuild the dashboard around what needs attention"
```

---

### Task 7: Smaller pages

**Files:**
- Modify: `app/Filament/Resources/Categories/CategoryResource.php`, `…/Categories/Pages/ManageCategories.php`, `app/Filament/Resources/Pages/PageResource.php`, `…/Pages/Pages/ListPages.php`, `app/Filament/Resources/WorkshopSessions/WorkshopSessionResource.php`, `…/WorkshopSessions/Pages/ManageWorkshopSessions.php`, `app/Filament/Pages/ShopSettings.php`
- Create: `tests/Feature/AdminSmallPagesTest.php`

**Interfaces:**
- Consumes: `PageSummary`, `Human`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AdminSmallPagesTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\WorkshopSessions\Pages\ManageWorkshopSessions;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkshopSession;
use Database\Seeders\PageSeeder;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSmallPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_categories_summary_and_counts(): void
    {
        $category = Category::query()->create(['name' => 'Kupalar', 'slug' => 'kupalar']);
        $category->products()->attach([Product::factory()->unique()->create()->id, Product::factory()->unique()->sold()->create()->id]);

        $this->get('/yonetim/kategoriler')->assertOk()
            ->assertSee('1 kategori · 2 parça')
            ->assertSee('2 parça, 1’i satışta')
            ->assertSee('Mağazada');
    }

    public function test_pages_summary(): void
    {
        $this->seed(PageSeeder::class);

        $this->get('/yonetim/sayfalar')->assertOk()->assertSee('sayfa · en son')->assertSee('Yayında');
    }

    public function test_workshop_list_summary_and_no_toggles(): void
    {
        $workshop = Product::factory()->workshop()->create();
        WorkshopSession::query()->create(['product_id' => $workshop->id, 'starts_at' => now()->addDays(3)->setTime(11, 0), 'ends_at' => now()->addDays(3)->setTime(14, 0), 'capacity' => 8, 'seats_reserved' => 5]);

        $this->get('/yonetim/atolye-takvimi/liste')->assertOk()->assertSee('1 seans · 3 boş yer · sıradaki')->assertSee('5/8 dolu · 3 yer kaldı');

        $columns = Livewire::test(ManageWorkshopSessions::class)->instance()->getTable()->getColumns();
        $this->assertEmpty(array_filter($columns, fn ($c) => $c instanceof ToggleColumn));
    }

    public function test_settings_summary(): void
    {
        $this->get('/yonetim/ayarlar')->assertOk()->assertSee('Mağaza açık')->assertSee('ödemeler');
    }
}
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter=AdminSmallPagesTest` → FAIL.

- [ ] **Step 3: Categories**

`CategoryResource::table()` columns:

```php
                ImageColumn::make('image')->label('')->disk('public')->circular()->imageSize(40),
                TextColumn::make('name')->label('Ad')->searchable()->description(fn (Category $record) => $record->parent?->name),
                TextColumn::make('products_count')->label('Parçalar')
                    ->formatStateUsing(fn (Category $record) => $record->products_count.' parça, '.$record->for_sale_count.'’i satışta'),
                TextColumn::make('is_visible')->label('Nerede')->color('gray')
                    ->formatStateUsing(fn (Category $record) => $record->is_visible ? 'Mağazada' : 'Gizli'),
```

with `->modifyQueryUsing(fn (Builder $query) => $query->withCount(['products', 'products as for_sale_count' => fn (Builder $products) => $products->forSale()->where('stock', '>', 0)]))` and the record actions adding `Action::make('toggleVisible')->label(fn (Category $record) => $record->is_visible ? 'Gizle' : 'Mağazada göster')->action(fn (Category $record) => $record->update(['is_visible' => ! $record->is_visible]))` inside an `ActionGroup` with Edit/Delete. Remove the `ToggleColumn` import.

Note on the Turkish suffix: "1’i", "2’si", "3’ü", "4’ü", "5’i", "6’sı" depend on the number. Add a tiny helper in `App\Support\Human`:

```php
    /** Turkish possessive suffix after a number: 1'i, 2'si, 3'ü, 6'sı, 10'u … */
    public static function ofThem(int $n): string
    {
        $last = $n % 10;
        $suffix = match (true) {
            $n === 0 => 'ı',
            $last === 0 => match ($n % 100 === 0 ? 100 : $n % 100) { 10, 30 => 'u', 20, 50 => 'si', 40, 60, 90 => 'ı', 70, 80 => 'i', default => 'ü' },
            in_array($last, [1, 5, 8], true) => 'i',
            in_array($last, [2, 7], true) => 'si',
            in_array($last, [3, 4], true) => 'ü',
            $last === 6 => 'sı',
            default => 'u', // 9
        };

        return $n.'’'.$suffix;
    }
```

and use `Human::ofThem($record->for_sale_count).' satışta'`. Add to `HumanTest`: `assertSame('1’i', Human::ofThem(1)); assertSame('2’si', Human::ofThem(2)); assertSame('6’sı', Human::ofThem(6)); assertSame('10’u', Human::ofThem(10));`.

`ManageCategories::getSubheading()`:

```php
    public function getSubheading(): string|Htmlable|null
    {
        return PageSummary::make([[Category::query()->count().' kategori · '.Product::query()->whereHas('categories')->count().' parça']]);
    }
```

- [ ] **Step 4: Pages**

`PageResource::table()` columns: keep title+path; replace the two `ToggleColumn`s with

```php
                TextColumn::make('is_published')->label('Durum')->color('gray')
                    ->formatStateUsing(fn (Page $record) => ($record->is_published ? 'Yayında' : 'Taslak').($record->show_in_footer ? ' · alt menüde' : '')),
                TextColumn::make('updated_at')->label('Güncellendi')
                    ->formatStateUsing(fn (Page $record) => Human::date($record->updated_at)),
```

(`Human::date` avoids Turkish case suffixes after dates.) Add record actions for publish/footer toggles (`Action::make('togglePublished')…`, `Action::make('toggleFooter')…`) in an `ActionGroup` with the existing open/edit/delete.

`ListPages::getSubheading()`:

```php
        $last = Page::query()->max('updated_at');

        return PageSummary::make([[Page::query()->count().' sayfa', $last ? '· en son '.Human::day(\Illuminate\Support\Carbon::parse($last)).' güncellendi' : null]]);
```

- [ ] **Step 5: Workshop list**

In `WorkshopSessionResource::sessionsTable()`: replace `ToggleColumn::make('is_visible')` with

```php
                TextColumn::make('is_visible')->label('Nerede')->color('gray')
                    ->formatStateUsing(fn (WorkshopSession $record) => $record->is_visible ? 'Sitede' : 'Gizli'),
```

replace the `seats_reserved` badge column with

```php
                TextColumn::make('seats_reserved')->label('Doluluk')
                    ->formatStateUsing(fn (WorkshopSession $record) => $record->seats_reserved.'/'.$record->capacity.' dolu · '.$record->seatsLeft().' yer kaldı')
                    ->color(fn (WorkshopSession $record) => $record->seatsLeft() === 0 ? 'danger' : 'gray'),
```

and add to the `ActionGroup` in `recordActions`:

```php
                    Action::make('toggleVisible')
                        ->label(fn (WorkshopSession $record) => $record->is_visible ? 'Sitede gizle' : 'Sitede göster')
                        ->icon('heroicon-o-eye')
                        ->action(fn (WorkshopSession $record) => $record->update(['is_visible' => ! $record->is_visible])),
```

Remove the `ToggleColumn` import. `ManageWorkshopSessions::getSubheading()`:

```php
    public function getSubheading(): string|Htmlable|null
    {
        $upcoming = WorkshopSession::query()->where('starts_at', '>', now())->orderBy('starts_at')->get();

        return PageSummary::make([[
            $upcoming->count().' seans · '.$upcoming->sum(fn (WorkshopSession $s) => $s->seatsLeft()).' boş yer',
            $upcoming->first() ? '· sıradaki '.$upcoming->first()->starts_at->translatedFormat('j M D') : null,
        ]]);
    }
```

- [ ] **Step 6: Settings**

`ShopSettings`: add

```php
    public function getSubheading(): string|Htmlable|null
    {
        $closed = (bool) Settings::get('store_closed');
        $fee = (int) Settings::get('shipping_fee', 0);
        $free = (int) Settings::get('free_shipping_threshold');
        $gateway = config('services.payment_gateway') === 'fake' ? 'test ödeme sayfası' : 'iyzico'.(str_contains((string) config('services.iyzico.base_url'), 'sandbox') ? ' (test modu)' : '');

        return PageSummary::make([
            [$closed ? 'Mağaza kapalı' : 'Mağaza açık'],
            ['kargo '.Money::format($fee), $free ? Money::format($free).' üzeri ücretsiz' : null],
            ['ödemeler', $gateway],
        ]);
    }
```

(check the store-closed setting key with `grep -n "store_closed" app/Support/Settings.php`). For each `Section::make(...)` add a `->description('…')` one-liner: Genel "Mağaza adı ve iletişim bilgileri; e-postalarda ve sitenin altında görünür.", Şirket bilgileri "Faturada ve yasal sayfalarda kullanılır.", Karşılama alanı "Ana sayfanın en üstündeki yazı ve görsel.", Duyuru şeridi "Sitenin en üstünde ince bir duyuru satırı.", Kargo "Kargo ücreti ve ücretsiz kargo sınırı; sepette hemen uygulanır.", Ödeme "Taksit seçenekleri ve ödeme süresi." (untitled sections stay without description).

- [ ] **Step 7: Run tests** — `php artisan test --filter="AdminSmallPagesTest|HumanTest"` → PASS; `php artisan test` → green.

- [ ] **Step 8: Commit**

```bash
git add -A app tests
git commit -m "Apply summaries and plain-text values to the remaining admin pages"
```

---

### Task 8: Product add page polish

**Files:**
- Modify: `app/Filament/Resources/Products/Schemas/ProductForm.php`, `app/Filament/Resources/Products/Pages/CreateProduct.php`, `app/Filament/Resources/Products/Pages/EditProduct.php`, `resources/views/filament/product-state.blade.php`, `tests/Feature/AdminProductsTest.php`

**Interfaces:**
- Consumes: `ProductForm`, `ProductResource` (round 1).

- [ ] **Step 1: Write the failing tests**

Append to `AdminProductsTest` (imports: `App\Enums\ProductVisibility`, `App\Models\Category`):

```php
    public function test_new_piece_defaults_to_live_in_the_shop(): void
    {
        Livewire::test(CreateProduct::class)
            ->assertSchemaStateSet(['status' => ProductStatus::Published->value, 'visibility' => ProductVisibility::Public->value, 'type' => 'unique']);
    }

    public function test_create_another_keeps_type_and_categories(): void
    {
        $category = Category::query()->create(['name' => 'Kupalar', 'slug' => 'kupalar']);

        Livewire::test(CreateProduct::class)
            ->fillForm(['type' => 'unique', 'name' => 'Kupa Bir', 'slug' => 'kupa-bir', 'price' => '500', 'categories' => [$category->id]])
            ->call('createAnother')
            ->assertHasNoFormErrors()
            ->assertSchemaStateSet(['type' => 'unique', 'categories' => [$category->id], 'name' => null]);

        $this->assertTrue(Product::query()->where('slug', 'kupa-bir')->exists());
    }

    public function test_category_can_be_created_inline(): void
    {
        Livewire::test(CreateProduct::class)
            ->assertFormComponentActionExists('categories', 'createOption');
    }

    public function test_edit_page_shows_shop_preview_card_and_link(): void
    {
        $draft = Product::factory()->unique()->create(['status' => ProductStatus::Draft, 'name' => 'Taslak Kase', 'price' => 45000]);

        $this->get('/yonetim/urunler/'.$draft->slug.'/edit')->assertOk()
            ->assertSee('Mağazada nasıl görünecek?')
            ->assertSee('/onizleme/urun/'.$draft->slug, false)
            ->assertSee('450 ₺');
    }
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter=AdminProductsTest` → new tests FAIL.

If `assertSchemaStateSet` / `assertFormComponentActionExists` are not the exact names in this Filament version, find them with `grep -n "public function assert" vendor/filament/schemas/src/Testing/*.php vendor/filament/forms/src/Testing/*.php` and use the equivalents (`assertFormSet`, `assertFormFieldExists` + action check).

- [ ] **Step 3: Implement**

`ProductForm`:
- `Select::make('status')->default(ProductStatus::Published)`; `Radio::make('visibility')->default(ProductVisibility::Public)`.
- Photos `FileUpload` helper text: `'4:5 dikey fotoğraflar en iyi sonucu verir. Sürükleyerek sıralayın; ilk fotoğraf kapak olur.'` and add `->extraAttributes(['class' => 'min-h-64'])` for a larger drop area.
- `TextInput::make('name')` keep `->live(onBlur: true)`; `MoneyInput::make('price')` add `->autofocus(fn (string $operation) => false)` — skip focusing tricks if MoneyInput does not support it; the name field gets `->autofocus()` on create instead (`->autofocus(fn (string $operation) => $operation === 'create')`).
- Categories select:

```php
                        Select::make('categories')->hiddenLabel()->relationship('categories', 'name')->multiple()->preload()->searchable()
                            ->createOptionForm([TextInput::make('name')->label('Kategori adı')->required()->maxLength(120)])
                            ->createOptionUsing(fn (array $data) => Category::query()->create(['name' => $data['name'], 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(3))])->getKey()),
```

(import `App\Models\Category`).

`CreateProduct`: Filament's create page already has `createAnother`; keep type and categories after it:

```php
    /** "Oluştur ve yenisini ekle": start the next piece with the same type and categories. */
    public function createAnother(): void
    {
        $keep = ['type' => $this->data['type'] ?? null, 'categories' => $this->data['categories'] ?? []];

        parent::createAnother();

        $this->form->fill($keep + ['status' => ProductStatus::Published->value, 'visibility' => ProductVisibility::Public->value]);
    }
```

(imports `App\Enums\ProductStatus`, `App\Enums\ProductVisibility`). Label: `protected function getCreateAnotherFormAction(): Action { return parent::getCreateAnotherFormAction()->label('Oluştur ve yenisini ekle'); }` (import `Filament\Actions\Action`).

`product-state.blade.php` — prepend a shop-style card:

```blade
<div class="mb-3 flex items-center gap-3">
    @if ($cover = $product->coverImage())
        <img src="{{ \App\Support\ProductImages::url($cover, 200) }}" alt="" class="size-14 rounded-lg object-cover">
    @else
        <div class="flex size-14 items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-400 dark:bg-white/5">foto yok</div>
    @endif
    <div class="text-sm">
        <p class="font-medium text-gray-950 dark:text-white">{{ $product->name }}</p>
        <p class="text-gray-500 dark:text-gray-400">{{ \App\Support\Money::format($product->price) }}</p>
    </div>
</div>
```

and append after the existing content:

```blade
<a href="{{ $product->isReachable() ? $product->url() : $product->previewUrl() }}" target="_blank" class="mt-2 inline-block text-sm text-primary-600 underline dark:text-primary-400">Mağazada nasıl görünecek?</a>
```

`EditProduct`: remove the now-duplicated "Sitede gör" header action only if it is identical (keep Instagram linki, Sil, restore/force-delete).

- [ ] **Step 4: Run tests** — `php artisan test --filter=AdminProductsTest` → PASS; `php artisan test` → green.

- [ ] **Step 5: Commit**

```bash
git add -A app resources tests
git commit -m "Polish the product add page for fast entry"
```

---

### Task 9: Demo data and final check

**Files:**
- Modify: `database/seeders/DemoSeeder.php`

- [ ] **Step 1: Two sample requests**

At the end of `DemoSeeder::run()` (after products exist):

```php
        $kum = Product::query()->where('slug', 'kum-kupa')->first();
        $kase = Product::query()->where('slug', 'kiremit-kase')->first();
        if ($kum && $kase) {
            // forceCreate so the backdated created_at is kept.
            SimilarRequest::query()->forceCreate(['product_id' => $kum->id, 'message' => "Merhaba, bu kupadan 4 tane istiyorum, düğün hediyesi olacak.\nAralık ortasına yetişir mi?", 'contact' => '+905321112233', 'contact_type' => 'phone', 'created_at' => now()->subDay()]);
            SimilarRequest::query()->forceCreate(['product_id' => $kase->id, 'message' => 'Biraz daha büyük olabilir mi? Salata kasesi olarak kullanacağım.', 'contact' => 'mehmet@example.com', 'contact_type' => 'email', 'status' => 'contacted', 'admin_note' => 'Ölçüyü sordum, 22 cm istiyor.', 'created_at' => now()->subDays(3)]);
        }
```

(import `App\Models\SimilarRequest`).

- [ ] **Step 2: Verify seeding on a throwaway database**

```bash
DB=$(mktemp --suffix=.sqlite)
DB_CONNECTION=sqlite DB_DATABASE=$DB CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan migrate:fresh --seed --force
DB_CONNECTION=sqlite DB_DATABASE=$DB CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan db:seed --class=DemoSeeder --force
DB_CONNECTION=sqlite DB_DATABASE=$DB php artisan tinker --execute='echo App\Models\SimilarRequest::count()." requests, ".Schema::hasTable("coupons")."|".Schema::hasColumn("users","role");'
rm -f $DB
```

Expected: `2 requests, |` (no coupons table, no role column).

- [ ] **Step 3: Commit**

```bash
git add database/seeders/DemoSeeder.php
git commit -m "Seed sample Benzerini iste requests"
```

- [ ] **Step 4: Rebuild local Docker and reset demo data** (agreed; demo data only)

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up --build -d
docker compose exec -T app php artisan migrate:fresh --force --seed
docker compose exec -T app php artisan db:seed --class=DemoSeeder --force
```

- [ ] **Step 5: Browser walkthrough** (no console errors)
- Shop: sold piece → "benzerini iste" opens the sheet; send with a phone → thank-you text; Mailpit (http://localhost:8025) shows the admin email.
- Admin: Talepler badge, list summary/tabs, request page contact button, "Parçayı oluştur" → edit page → "Müşteriye linki gönder".
- Orders list summary and status phrases; order page next-step bar.
- Dashboard greeting, cards, Bekleyenler, Yaklaşan atölyeler, Son satışlar.
- Categories, Sayfalar, Atölye listesi, Ayarlar summaries; menu has no Kuponlar/Kullanıcılar.
- Product add: defaults, "Oluştur ve yenisini ekle", inline category.

- [ ] **Step 6: Final checks** — `php artisan test && vendor/bin/pint --test && git status --short` → green, Pint passed, clean tree. Nothing pushed.
