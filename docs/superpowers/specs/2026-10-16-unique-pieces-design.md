# Unique pieces & minimal admin — design

Date: 2026-10-16 · Status: approved in conversation, awaiting written-spec review

## Goal

Pürüz Atölye sells one-of-a-kind handmade pieces, not mass-produced stock. The shop and the admin
panel should model that directly: a piece is available, reserved or sold, never "16 in stock".
At the same time the admin panel moves to a minimal, data-first style with human-readable output
and summaries, starting with the products page.

Mass-produced items may come later, so the existing stock/variant logic stays in the code, hidden.

## Scope

In this round:

1. Unique-piece data model and shop behaviour.
2. Admin products page redesign (`/yonetim/urunler`).
3. Product create/edit form redesign.
4. Panel-wide style rules applied through the theme, plus shared formatter/summary building blocks.
   Only the dashboard gets logic fixes (low stock no longer applies).

Out of scope (later, one page at a time, using the rules from section 4): orders, dashboard
layout, coupons, categories, pages, users, settings.

Unchanged: workshops and sessions, checkout and payment (iyzico), reservation timing, refunds,
order emails (except wording noted below).

## 1. Data model & shop behaviour

### Approach

A new product type on top of the existing stock logic. A unique piece is stored with stock 1
(unsold) or 0 (reserved or sold), so cart, checkout, `ReservationService` and refunds keep working
unchanged. Nobody sees or edits that number; the admin and shop show a derived state instead.

Rejected: a separate state column with its own reservation path (duplicates the riskiest code),
and deleting stock/variants entirely (expensive to rebuild for mass production).

### Data

- `ProductType` gains `Unique` ("Tek parça"), the default for new products. `Physical` is
  relabelled "Seri üretim" and is only offered in the type picker when
  `config('shop.mass_production')` is true (default false). `Workshop` is unchanged.
- Saving a `Unique` product forces `track_stock = true`, `made_to_order = false`,
  `lead_time_days = null`, no variants, and stock 1 when not sold. Enforced in the model layer
  (observer), not only the form.
- New nullable column `products.sold_at`:
  - set when the order containing the piece becomes paid;
  - cleared when a refund restocks the piece;
  - kept when a refund does not restock (piece stays sold).
- `Product::pieceState()` returns an enum:
  - `Sold` — `sold_at` set;
  - `Reserved` — stock 0, `sold_at` null (an unpaid order holds it);
  - `Available` — stock 1.
  Draft/hidden stay on the existing `status` and `visibility` fields, independent of piece state.
- Piece code (`sku`) is auto-filled as `PRZ-0001`, `PRZ-0002`, … when left empty; editable.

### Shop

- Product page, unique piece:
  - no quantity picker, no variant buttons; button reads "Sepete ekle";
  - reserved: "Şu an başka birinin sepetinde" note, button disabled (frees itself when the other
    payment fails or expires);
  - sold: "Satıldı" badge, no add-to-cart, and a "Benzerini iste" button that opens WhatsApp
    (`whatsapp_number` setting) with the message
    `Merhaba, "{name}" gibi bir parça yaptırmak istiyorum: {url}`; falls back to Instagram DM
    (`instagram_handle`) when no WhatsApp number is set.
- Listings (cards): sold pieces carry a small "Satıldı" label and sort after available ones.
  The "in stock only" filter becomes "Satıştakiler".
- Cart: unique pieces are always quantity 1; adding again does not increase it. If a piece in a
  cart is sold or reserved meanwhile, checkout refuses it with "Bu parça satıldı".
- Made-to-order badges and lead-time text are not shown for unique pieces.

## 2. Admin products page

- Summary line above the table, e.g.
  `14 parça satışta (8.450 ₺ değerinde) · bu ay 3 satıldı, 1.850 ₺ · ortalama 21 günde satılıyor · 2 taslak · 1 ödeme bekliyor`.
  Average time to sell = mean of (`sold_at` − `published_at`, falling back to `created_at`) over
  pieces sold in the last 90 days; omitted when there are none.
- Tabs with counts: Satışta (default) · Satıldı · Taslaklar · Atölyeler · Tümü.
- Columns (no inline selects or toggles):
  - photo, 56 px square;
  - piece: name, with a grey line `category · code · dimensions`;
  - price, with struck-through compare-at price when on sale;
  - Durum: dot + phrase — "Satışta · 12 gündür", "Satıldı · 3 Kas, 18 günde",
    "Ödeme bekleniyor", "Taslak"; workshops "4 seans · 32 boş yer · sıradaki 3 Kas";
  - Nerede: "Mağazada" / "Sadece link" / "Gizli", plus ★ when featured;
  - Görüntülenme (toggleable).
- Row click opens the edit page. Row `⋯` menu: Yayınla / Taslağa al, Görünürlük, Öne çıkar,
  Instagram linki, Sitede gör, Kopyala, Sil (plus restore / force delete in trash).
- Kopyala creates a draft with the same text, categories, details and care notes, but no photos,
  not sold, new code, and opens it for editing.
- Bulk actions unchanged: publish, draft, visibility, feature, add to category, delete.
- Sort: newest (default), price, days in shop, sold date, views. Filters: category, price range,
  featured, visibility, trashed. Low-stock filter and "Tükenenler" tab are removed.
- `sort_order` still controls shop order; it is no longer the admin default sort.

## 3. Product form

One scrolling page instead of tabs.

Main column, in order:
1. Type — chosen only on create (Tek parça / Atölye, plus Seri üretim when enabled); afterwards a
   read-only label.
2. Photos — first; drag to reorder; first is the cover.
3. Name & description — name, slug (auto), short description, description.
4. Details — dimensions, materials, weight, care (workshop labels as today).
5. SEO & sharing — collapsed: title, description, OG image, Instagram post, `sort_order`.

Sidebar:
- Durum card (read-only): "Satışta · 12 gündür · 142 görüntülenme" /
  "Ödeme bekleniyor · Sipariş #1043" (linked) / "Satıldı · 3 Kasım · 650 ₺ · Sipariş #1043 · 18 günde satıldı".
- Price: price, compare-at price.
- Publishing: status, visibility (with descriptions), featured, publish date.
- Categories.
- Piece code (auto if empty).

Unique pieces have no stock, track-stock, made-to-order, lead-time fields and no variants panel.
Workshops keep the sessions panel. Sold pieces stay editable; a note says price changes don't
affect the existing order.

## 4. Panel-wide style

Rules:
- Data first, controls second — values as text; changes through `⋯` menus or edit pages; no
  selects/toggles inside table rows.
- Colour carries meaning only — greyscale except state: green good, amber waiting, red problem;
  shown as a small dot + word.
- Human-readable — dates "3 Kas" / "dün 14:20", durations "12 gündür" / "18 günde", money
  "1.850 ₺", tabular figures, no raw enum values.
- Every list page gets a one-line summary at the top.
- Calm layout — smaller page headings, tighter rows, lighter borders, grey secondary text.

Applied now through `resources/css/filament/admin/theme.css` (density, borders, headings,
tabular numbers, calmer badges), so all pages pick them up.

Shared building blocks:
- `App\Support\Human` — relative dates, durations, money.
- A `PageSummary` view component for list-page summary lines.

Dashboard logic fixes: "Az stok" card becomes "Satışta: N parça"; low-stock count removed from the
sales overview.

## 5. Testing & rollout

- Migration adds `products.sold_at`. Demo seeder creates unique pieces (some available, a couple
  sold, one draft); the Espresso Fincanı variant becomes separate pieces.
- Local Docker database is reset and reseeded (demo data only).
- Tests (existing 59 must still pass):
  - unique rules: forced stock/flags, no variants, cart quantity stays 1;
  - lifecycle: available → reserved on checkout → available on failure/expiry → sold on payment
    (`sold_at`) → available on refund with restock / still sold without restock;
  - shop: sold page shows "Satıldı" and the WhatsApp "Benzerini iste" link, no add-to-cart;
    reserved notice; listings put available before sold;
  - admin: list renders, tabs filter, summary numbers, state phrases, Kopyala, code auto-fill;
  - mass production: type hidden unless enabled; old stock/variant behaviour intact when enabled.
- Then Pint, Docker rebuild, and a browser walkthrough of shop and admin.
