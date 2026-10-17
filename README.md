# Pürüz Atölye — online mağaza

Online shop for Pürüz Atölye, a ceramics & mosaic workshop in Turkey. Built for traffic that
arrives from Instagram links: mobile-first product pages, card-only checkout through iyzico,
bookable workshop seats and order tracking by email link.

- **Storefront** (Turkish): product & workshop pages, link-in-bio page (`/link`), cart, checkout, order tracking (`/siparis/{token}`).
- **Admin panel** (`/yonetim`): products, variants, workshop calendar, orders, refunds, coupons, pages, settings. See [docs/yonetici-kilavuzu.md](docs/yonetici-kilavuzu.md).
- **Payments**: iyzico hosted Checkout Form with 3D Secure. Card data never touches this server. See [docs/IYZICO.md](docs/IYZICO.md).
- **Hosting**: Docker Compose on a Linux VPS with FrankenPHP (automatic HTTPS), MariaDB, Redis, a queue worker, a scheduler and nightly backups. See [docs/DEPLOY-VPS.md](docs/DEPLOY-VPS.md).

## Stack

Laravel 13 · PHP 8.3 · Filament 5 (admin) · Blade + Alpine.js + Tailwind CSS 4 (storefront) ·
MariaDB · Redis · iyzico PHP SDK · dompdf (contract PDFs) · Intervention Image (WebP thumbnails).
All open source.

## Local development

Requirements: PHP 8.3 with `intl`, `gd`, `zip`, `pdo_sqlite`; Composer; Node 22.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Then edit `.env` for local use:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite        # and remove the other DB_* lines
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=false
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log             # emails go to storage/logs/laravel.log
PAYMENT_GATEWAY=fake        # local fake payment page instead of iyzico
```

```bash
touch database/database.sqlite
php artisan migrate --seed   # demo products, workshop sessions, legal pages, admin user
php artisan storage:link
npm run build                # or: npm run dev
php artisan serve
```

The seeder prints the admin login (`admin@example.com` unless `ADMIN_EMAIL`/`ADMIN_PASSWORD` are set).
Shop: http://localhost:8000 · Admin: http://localhost:8000/yonetim

To try real iyzico payments locally, set `PAYMENT_GATEWAY=iyzico` with sandbox keys. iyzico's
callback is a browser redirect, so it works on localhost. Test card: `5528 7900 0000 0008`, any
future expiry date, CVC `123`.

## Tests

```bash
php artisan test        # PHPUnit: checkout, payments, reservations, visibility, admin actions
vendor/bin/pint --test  # code style
```

## Where things are

| Area | Code |
| --- | --- |
| Cart, checkout, reservations | `app/Services/Cart`, `app/Services/Checkout`, `app/Services/Orders/ReservationService.php` |
| iyzico integration | `app/Services/Payment` (`IyzicoGateway`, `PaymentService`, `BasketBuilder`) |
| Refunds, order statuses | `app/Services/Orders` |
| Storefront | `app/Http/Controllers`, `resources/views/shop`, `resources/css/app.css` |
| Admin panel | `app/Filament` |
| Emails | `app/Mail`, `resources/views/mail` |
| Legal page templates | `database/seeders/PageSeeder.php` (edit afterwards from the admin) |
| Deployment | `Dockerfile`, `docker-compose.yml`, `docker/` |

Money is stored as integer kuruş everywhere and prices include KDV.

Province/district data (`resources/data/turkiye-il-ilce.json`) comes from the MIT-licensed
[turkey-neighbourhoods](https://www.npmjs.com/package/turkey-neighbourhoods) package.
