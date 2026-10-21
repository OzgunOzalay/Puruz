# Project status

Last updated: 2026-10-20 (evening). Branch `claude/hopeful-gates-8lmvow` (local only, **not pushed**: stay local until the owner says the site is ready).

## Running it locally

- Docker trial of the production stack over plain HTTP:
  `docker compose -f docker-compose.yml -f docker-compose.dev.yml up --build -d`
- Shop http://localhost · Admin http://localhost/yonetim · Mail (Mailpit) http://localhost:8025
- Admin login: `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` (git-ignored). Payments use the fake gateway (`PAYMENT_GATEWAY=fake`).
- Reset demo data: `docker compose exec -T app php artisan migrate:fresh --force --seed && docker compose exec -T app php artisan db:seed --class=DemoSeeder --force`
- Tests run locally (PHP 8.3 installed): `php artisan test` (115 passing) · style `vendor/bin/pint --test` · assets `npm run build`.

## Public preview through Tailscale Funnel (set up 2026-10-20)

While the VPS is pending, the local Docker stack is public at **https://ubuntu24-home.tail3ad131.ts.net**. Funnel used to point at the Reviewer app (:8001). It now points at `http://127.0.0.1:80`. `reviewer.service` still runs, but only locally.

- Start the stack with the funnel override, or product photos break (they would link to `http://localhost`):
  `docker compose -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.funnel.yml up -d`
  `docker-compose.funnel.yml` is local only (listed in `.git/info/exclude`). It sets `APP_URL` to the funnel URL for app, worker and scheduler.
- `.env` now has `TRUSTED_PROXIES=127.0.0.1,172.16.0.0/12`. Inside Docker the proxy arrives from the bridge network. Without this, Laravel ignores `X-Forwarded-Proto` and emits `http://` CSS/JS, which browsers block as mixed content. **The VPS needs the same check** if anything sits in front of Caddy.
- Changing funnel needs `sudo tailscale funnel ...`. Claude has no sudo password, so the owner runs it as `! sudo ...`.
- The site and the admin at `/yonetim` are reachable from the internet, so keep a strong `ADMIN_PASSWORD`.

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

4. **Storefront redesign (mosaic + ink sketch).** Brand photos from Instagram, tile-shaped cards, light scroll motion for phones, story and visit sections. Branch `storefront-redesign` (not merged yet; the owner approved the look).
   Spec: `docs/superpowers/specs/2026-10-20-storefront-redesign-design.md`.

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

## Next: first VPS test (paused 2026-10-20, waiting for GoDaddy to create the VPS)

Decided:
- **Package:** GoDaddy VPS, 2 vCPU / 4 GB RAM, self-managed, Ubuntu 24.04 LTS, root SSH, 50 GB+ SSD, data centre in Europe if offered.
- **Nothing else preinstalled:** no cPanel/Plesk, Apache, MySQL or PHP, since everything runs in Docker and a panel would clash on ports 80/443.
- **No paid SSL:** Caddy gets and renews Let's Encrypt certificates automatically.
- **Email** needs separate SMTP (a mailbox provider or a transactional service) for `MAIL_*`.

Still to decide when picking up:
1. Merge `storefront-redesign` into `claude/hopeful-gates-8lmvow` before building?
2. How the code reaches the server: push to GitHub (needs the owner's OK) or rsync the source over SSH. With 4 GB, building on the server is fine.
3. Domain pointed at the VPS, or test on the bare IP (`SERVER_NAME=":80"`, `APP_URL=http://IP`, `SESSION_SECURE_COOKIE=false`)?

Server setup checklist:
1. Add 1–2 GB swap.
2. Open ports 80/443 in both the GoDaddy firewall panel and `ufw`.
3. Install Docker.
4. Set up `.env`, starting with `PAYMENT_GATEWAY=fake`.
5. `docker compose up -d`, then `db:seed` and `db:seed --class=DemoSeeder`.

## Working agreements

- Design work goes brainstorm → spec → plan → build, task by task with TDD. A fresh reviewer checks each branch, then it is merged locally.
- Never push or open PRs until the owner says so.
