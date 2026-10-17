# Admin round 2 — requests, orders, dashboard, cleanup — design

Date: 2026-10-16 · Status: approved in conversation, awaiting written-spec review
Builds on: `docs/superpowers/specs/2026-10-16-unique-pieces-design.md` (style rules in its §4 apply here).

## Goal

Finish the minimal, data-first admin: replace the WhatsApp "Benzerini iste" link with a request
form handled in a new Talepler page, restyle orders and the dashboard around "what needs doing",
apply the style to the remaining pages, polish the product add page, and remove features the
workshop will not use (coupons, multi-user management).

Everything stays local; nothing is pushed.

## Scope

1. Removals: coupons; user management and roles.
2. "Benzerini iste" requests: shop form, emails, Talepler admin page, piece creation and payment link.
3. Orders: list and detail page.
4. Dashboard.
5. Smaller pages: categories, pages, workshop list, settings.
6. Product add page polish.

Unchanged: checkout/payment flow (apart from coupon removal), reservations, refunds, workshops
calendar, products list.

## 1. Removals

### Coupons
- Remove: cart coupon box and `CartService` coupon methods, checkout coupon locking, coupon
  use counting in `ReservationService`, discount handling in totals/basket, discount lines in
  emails and the order page, `CouponResource`, `Coupon` model, `CouponType` enum, demo coupon,
  coupon tests.
- Migration drops the `coupons` table and the order's coupon columns (no live data exists).
- Order totals must stay correct without a discount (tested).

### Users
- One admin account only, created by the seeder from `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
- Remove `UserResource` (Kullanıcılar), the `UserRole` enum, the staff restrictions on settings
  and refunds, and the active/disabled switch. `canAccessPanel` allows the single user.
- Migration drops the `role` and `is_active` columns.
- Password and email stay changeable from the existing profile page.

## 2. "Benzerini iste" requests

### Form (sold pieces only)
- Replaces the WhatsApp link on sold pieces. Opens on the same page (bottom sheet on phones).
- Fields:
  1. "Nasıl bir parça istersiniz?" — textarea, required, max 2000 characters; hint suggests
     colour, size, quantity, timing.
  2. "Telefon veya e-posta" — one line, required; must be a valid email or a Turkish mobile
     number (normalised to `+905XXXXXXXXX`), otherwise rejected.
- KVKK consent checkbox (required) linking to the privacy page.
- Spam: honeypot field; at most 5 requests per hour per IP.
- Success message: "Talebinizi aldık, en kısa sürede size döneceğiz."

### Data — `similar_requests`
`product_id` (original piece), `message`, `contact`, `contact_type` (`phone`|`email`),
`status` (`new` Yeni → `contacted` İletişime geçildi → `in_progress` Yapılıyor → `done` Tamamlandı,
or `cancelled` İptal), `admin_note`, `created_product_id` (nullable, the piece made for them),
`ip`, timestamps.

### Emails (queued)
- To the shop notification address: "Yeni talep: {piece} gibi" with the message, contact and a
  link to the request in the admin.
- To the customer, only when the contact is an email: short confirmation quoting their request.

### Admin — Talepler
- Navigation: Satış group, after Siparişler, badge = number of `new` requests.
- Summary line: "3 yeni talep · 2 yapılıyor · bu ay 4 tamamlandı".
- Tabs: Yeni · Devam eden (contacted + in progress) · Tamamlanan · İptal · Tümü. Default Yeni.
- Row: original piece thumbnail + name, contact, first line of the message, status dot + phrase,
  relative time ("2 gün önce").
- Request page: full message and original piece on the left; contact with a matching button
  ("WhatsApp'ta yaz" for phones, "E-posta gönder" for emails), status picker and private notes on
  the right.
- "Parçayı oluştur": creates a draft unique piece copied from the original (text, categories,
  materials, care; no photos), visibility "Sadece link", linked via `created_product_id`, and
  opens its edit page.
- On that piece's edit page, "Müşteriye linki gönder":
  - email contact → emails the piece link;
  - phone contact → opens WhatsApp with a prefilled message containing the link;
  - either way the request moves to `in_progress`.
- When the created piece is sold (`sold_at` set), the request moves to `done` automatically.

## 3. Orders

### List
- Summary line: "3 sipariş hazırlanacak (en eskisi 2 gündür bekliyor) · 1 kargoda · bu hafta 6
  sipariş, 4.850 ₺ · 1 ödeme bekleniyor".
- Tabs unchanged; default "Hazırlanacak".
- Columns: order number + relative date; customer + district/city (or "Atölye"); first item
  thumbnail + "Kobalt Kupa +2"; amount with "… iade" under it when refunded; status dot + phrase;
  source (hidden by default).
- Status phrases:
  - Paid/Preparing: "Hazırlanacak · N gündür" — amber after 2 days, green before;
  - Shipped: "Kargoda · {firma} · N gün";
  - Delivered: "Teslim edildi"; Completed: "Tamamlandı";
  - Confirmed (workshop): "Atölye · 3 Ara Sal 11:00" (first session);
  - PendingPayment: "Ödeme bekleniyor · N dk kaldı" (from `reserved_until`), "süresi doldu" after;
  - PaymentFailed: "Ödeme başarısız"; Refunded: "İade edildi"; Cancelled: "İptal".
- "Kargoya ver" button directly in rows that are to be packed (existing ship action); everything
  else in `⋯`.
- Filters: existing status, source, date range, plus "Ürün içerir" (piece name search).

### Detail page
- Next-step bar at the top with its action: "Paketleyip kargoya verin" [Kargoya ver] /
  "Kargoda, teslim edildiğinde işaretleyin" [Teslim edildi] / "Ödeme bekleniyor — N dk içinde
  düşmezse rezervasyon bırakılır"; none for finished orders.
- Item lines: thumbnail, piece code, price, link to the piece in the admin.
- Özet in sentences: "Kart ile ödendi · dün 14:20 · 3 taksit", "Instagram story'den geldi".
  Raw iyzico fields stay in the collapsed Ödeme section.
- Status history with relative dates. Customer, address and invoice cards unchanged apart from
  lighter styling.

## 4. Dashboard

- Greeting sentence by time of day (Günaydın / İyi günler / İyi akşamlar):
  "Günaydın. 3 sipariş paketlenmeyi, 2 yeni talep yanıt bekliyor. Bu hafta 4.850 ₺ satış."
  When nothing waits: "Bekleyen iş yok."
- Four cards (each a link):
  - Bu hafta — revenue, "N sipariş, geçen haftadan %X fazla/az", 14-day sparkline;
  - Hazırlanacak — count, "en eskisi N gündür bekliyor";
  - Talepler — "N yeni", "M yapılıyor";
  - Satışta — "N parça", "bu ay M satıldı".
- Left column "Bekleyenler": orders to pack and new requests in one list, oldest first, each with
  its next action. Replaces the current orders-to-pack table.
- Right column "Yaklaşan atölyeler": next 5 sessions as text ("3 Ara Sal 11:00 · Mozaik · 5/8
  dolu"), linking to the calendar.
- Bottom "Son satışlar": last 5 sold pieces (thumbnail, price, relative date, days in shop).
  The sales-source chart becomes one line: "Son 30 gün: %60 Instagram story, %25 bio linki, %15
  doğrudan".

## 5. Smaller pages

| Page | Summary | Row |
| --- | --- | --- |
| Kategoriler | "4 kategori · 23 parça" | name, "9 parça, 6'sı satışta", "Mağazada"/"Gizli", parent in grey |
| Sayfalar | "6 sayfa · en son 3 Kas'ta güncellendi" | title, path, "3 Kas'ta güncellendi", "Yayında"/"Taslak" |
| Atölye takvimi → Liste | "8 seans · 46 boş yer · sıradaki 3 Ara Sal" | date/time, group, "5/8 dolu · 3 yer kaldı", "Sitede"/"Gizli"; visibility toggle moves to `⋯` |
| Mağaza ayarları | "Mağaza açık · kargo 90 ₺, 1.500 ₺ üzeri ücretsiz · ödemeler iyzico (test modu)" | same fields; each section gets a one-line explanation |

No toggles or selects inside table rows on any of these pages.

Final navigation: Satış (Siparişler, Talepler) · Katalog (Ürünler & atölyeler, Kategoriler,
Atölye takvimi) · İçerik (Sayfalar) · Sistem (Mağaza ayarları, Mağazayı aç).

## 6. Product add page polish

- "Oluştur ve yenisini ekle" next to "Oluştur" (Filament's create-another), keeping type and
  categories from the previous piece for fast entry after a firing.
- Larger photo drop area with the hint "4:5 dikey fotoğraflar en iyi sonucu verir".
- Cover preview: on the edit page (after the first save) the Durum card shows a small shop-style
  card — cover, name, price — as it will appear in listings. A live preview while uploading is out
  of scope (uploads are temporary until saved).
- Defaults for new pieces: status "Yayında", visibility "Mağazada"; focus moves to price after the
  name.
- Categories select can create a new category inline (name only; slug generated).
- "Mağazada nasıl görünecek?" link on the edit page opens the signed preview URL for drafts or the
  live URL for published pieces.

## 7. Testing & rollout

Order: removals → shared helpers → requests → orders → dashboard → smaller pages → product add
polish → demo data (two sample requests) and final check.

Tests (existing 89 must pass except coupon/staff tests removed on purpose):
- Removals: checkout totals without coupons; `/yonetim/kuponlar` and `/yonetim/kullanicilar` 404;
  admin can sign in and change password.
- Requests: valid phone and valid email accepted; empty message or invalid contact rejected; KVKK
  required; honeypot and rate limit block; form only on sold pieces; admin email always, customer
  email only for email contacts; status changes; "Parçayı oluştur" makes a link-only draft linked to
  the request; "Müşteriye linki gönder" emails or builds the WhatsApp URL; request auto-completes
  when the created piece sells.
- Orders: status phrases incl. amber after 2 days and minutes left for unpaid; summary numbers;
  "Kargoya ver" from the list; "Ürün içerir" filter; next-step bar per status.
- Dashboard: greeting sentence and card numbers; "Bekleyenler" ordering.
- Smaller pages: each summary line renders; workshop list has no toggle column.
- Product add: create-another keeps type/categories; new-piece defaults; inline category creation.

Then Pint, Docker rebuild, demo reset, browser walkthrough. No push.
