# VPS'e kurulum (Docker)

Bu rehber, mağazayı tek bir Linux sunucuda (VPS) Docker ile çalıştırmayı anlatır. Kurulum
sonunda şunlar çalışır:

| Servis | Görevi |
| --- | --- |
| `app` | Web sunucusu (FrankenPHP/Caddy). HTTPS sertifikasını Let's Encrypt'ten otomatik alır. |
| `worker` | E-postaları ve görsel küçültmeyi arka planda yapar. |
| `scheduler` | Her dakika ödenmemiş siparişlerin stok rezervasyonlarını serbest bırakır. |
| `mariadb` | Veritabanı. |
| `redis` | Oturum, önbellek ve kuyruk. |
| `backup` | Her gece veritabanı ve yüklenen görsellerin yedeği (`./backups`). |

## 1. Sunucu

- Ubuntu 24.04, en az 2 GB RAM, 2 vCPU yeterlidir. Kişisel veriler (KVKK) için Türkiye'de
  veri merkezi olan bir sağlayıcı tercih edilebilir.
- Alan adının **A kaydını** (ve varsa `www` kaydını) sunucunun IP adresine yönlendirin.
- 80 ve 443 portları açık olmalı:

```bash
sudo ufw allow OpenSSH && sudo ufw allow 80 && sudo ufw allow 443 && sudo ufw enable
```

- Docker kurun (resmi kurulum betiği):

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER   # sonra oturumu kapatıp açın
```

## 2. Kodu alın ve ayarları yapın

```bash
git clone https://github.com/OzgunOzalay/Puruz.git puruz && cd puruz
cp .env.example .env
nano .env
```

Doldurulması gerekenler:

- `APP_URL=https://alanadiniz.com` ve `SERVER_NAME="alanadiniz.com, www.alanadiniz.com"`
- `DB_PASSWORD` ve `DB_ROOT_PASSWORD`: uzun, rastgele iki şifre (`openssl rand -base64 24`)
- `MAIL_*`: e-posta gönderimi için SMTP bilgileri
- `IYZICO_*`: önce sandbox anahtarları, onaydan sonra canlı anahtarlar (bkz. [IYZICO.md](IYZICO.md))
- `ADMIN_EMAIL` ve `ADMIN_PASSWORD`: ilk yönetici hesabı

Uygulama anahtarını üretip `.env` içine `APP_KEY=` olarak yazın:

```bash
docker compose build
docker compose run --rm --no-deps app php artisan key:generate --show
```

## 3. Başlatın

```bash
docker compose up -d
docker compose exec app php artisan db:seed --force    # yönetici hesabı + yasal sayfa şablonları
```

Canlı ortamda (`APP_ENV=production`) demo ürünler eklenmez. Örnek ürünlerle denemek isterseniz:
`docker compose exec app php artisan db:seed --class=DemoSeeder --force`

Site birkaç saniye içinde `https://alanadiniz.com` adresinde açılır, panel `https://alanadiniz.com/yonetim`.
Veritabanı tabloları `app` her başladığında otomatik güncellenir (`RUN_MIGRATIONS=true`).

## 4. Güncelleme

```bash
cd ~/puruz
git pull
docker compose build
docker compose up -d
```

## 5. Yedekler

- Her gece 03:30'da `./backups/` klasörüne `db-*.sql.gz` ve `uploads-*.tar.gz` yazılır; 14 günden
  eski olanlar silinir (`BACKUP_KEEP_DAYS`).
- **Yedekleri sunucu dışına da kopyalayın.** Örneğin [rclone](https://rclone.org) ile:

```bash
rclone sync ~/puruz/backups uzak:puruz-yedek   # bir cron görevine ekleyin
```

Geri yükleme:

```bash
gunzip -c backups/db-20261101-0330.sql.gz | docker compose exec -T mariadb sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
docker compose run --rm -v $PWD/backups:/backups app tar -xzf /backups/uploads-20261101-0330.tar.gz -C /app/storage
```

## 6. Sorun giderme

```bash
docker compose ps                    # servislerin durumu
docker compose logs -f app worker    # canlı loglar
docker compose exec app php artisan about
docker compose exec app php artisan queue:failed   # gönderilemeyen e-postalar
```

- HTTPS sertifikası alınamıyorsa: DNS kaydının sunucuyu gösterdiğini ve 80/443 portlarının açık olduğunu kontrol edin.
- E-posta gitmiyorsa: `MAIL_*` ayarlarını kontrol edin, sonra `docker compose restart worker`.

## Yerelde Docker ile deneme

HTTPS olmadan, e-postaları Mailpit'te görerek:

```bash
cp .env.example .env   # APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD doldurun; PAYMENT_GATEWAY=fake yapabilirsiniz
docker compose -f docker-compose.yml -f docker-compose.dev.yml up --build
```

İlk açılışta yönetici hesabını ve örnek ürünleri ekleyin:

```bash
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan db:seed --class=DemoSeeder --force
```

Mağaza http://localhost, panel http://localhost/yonetim, e-postalar http://localhost:8025.
