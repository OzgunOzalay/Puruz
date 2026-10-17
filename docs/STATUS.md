# Project status

Last updated: 2026-10-16. Branch `claude/hopeful-gates-8lmvow` (local only, **not pushed**: stay local until the owner says the site is ready).

## Running it locally

- Docker trial of the production stack over plain HTTP:
  `docker compose -f docker-compose.yml -f docker-compose.dev.yml up --build -d`
- Shop http://localhost · Admin http://localhost/yonetim · Mail (Mailpit) http://localhost:8025
- Admin login: `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` (git-ignored). Payments use the fake gateway (`PAYMENT_GATEWAY=fake`).
- Reset demo data: `docker compose exec -T app php artisan migrate:fresh --force --seed && docker compose exec -T app php artisan db:seed --class=DemoSeeder --force`
- Tests run locally (PHP 8.3 installed): `php artisan test` (115 passing) · style `vendor/bin/pint --test` · assets `npm run build`.

## Done

1. **Workshop calendar.** Month/week/day calendar at `/yonetim/atolye-takvimi` (FullCalendar): click a day to add a session, drag to move it. The list view is at `/liste`.
2. **Unique pieces.** Every product is one of a kind ("Tek parça"): available, reserved or sold (`sold_at`). Sold pieces stay in the shop marked "Satıldı". Mass production ("Seri üretim") is hidden behind `SHOP_MASS_PRODUCTION`.
   Spec: `docs/superpowers/specs/2026-10-16-unique-pieces-design.md`.
3. **Admin round 2.**
   - "Benzerini iste" request form on sold pieces, and the Talepler admin page with "Parçayı oluştur" and "Müşteriye linki gönder".
   - Orders and dashboard restyled, with summaries on every list page.
   - Product add page polished.
   - Coupons and multi-user management removed: a single admin account.
   Spec: `docs/superpowers/specs/2026-10-16-admin-round-2-design.md`.

## Open items (owner to decide or set)

- Set the **notification email** in Mağaza ayarları; until then new-order and new-request emails are not sent.
- Deferred minor issues from the reviews:
  - Request form: 5 invalid submissions lock a customer out for an hour, with a generic error page.
  - Request form: the confirmation email goes to any typed address and quotes the typed text.
  - The "0090 532 …" phone format is rejected.
  - The "Şirket bilgileri" settings section has two descriptions.
  - Talepler: rows show "bugün 14:20" instead of "2 gün önce", and the contact is a link rather than buttons.
  - Categories: "0'ı satışta" shows for the workshops category.
  - `markSold` runs after the payment transaction, so a crash in between leaves the piece reading "Ödeme bekleniyor".
  - The Durum card misses the order link when a refund didn't restock the piece.
  - "Satışta" counts include future-dated and hidden pieces.
  - Concurrent piece creation can collide on the auto `PRZ-` code.
  - Kopyala on a trashed product produces a trashed copy.
  - The home page's featured strip doesn't put sold pieces last.
- Later: deploy to a VPS following `docs/DEPLOY-VPS.md` (real domain, `SESSION_SECURE_COOKIE=true`, iyzico keys, SMTP).

## Working agreements

- Design work goes brainstorm → spec → plan → build, task by task with TDD. A fresh reviewer checks each branch, then it is merged locally.
- Never push or open PRs until the owner says so.
