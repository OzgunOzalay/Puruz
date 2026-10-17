# iyzico ödeme kurulumu

Mağaza iyzico'nun **Ortak Ödeme Sayfası** (Checkout Form) ürününü kullanır. Müşteri "öde"
butonuna bastığında iyzico'nun güvenli sayfasına yönlenir, kart bilgilerini orada girer
(3D Secure), sonra mağazaya geri döner. Kart bilgileri bu sunucuya hiç gelmez.

## 1. Üye işyeri başvurusu

iyzico başvurusu sırasında sitenizin şu şartları sağlaması istenir. Hepsi hazır gelir, yalnızca
bilgileri doldurmanız gerekir:

- [ ] **Mesafeli Satış Sözleşmesi**, **Ön Bilgilendirme Formu**, **KVKK Aydınlatma Metni**,
      **Teslimat ve İade** sayfaları: Panel → İçerik → Sayfalar. Şablon metinleri kendi
      işletmenize göre düzenleyin; mümkünse bir hukukçuya kontrol ettirin.
- [ ] **Şirket bilgileri** (unvan, adres, vergi dairesi/no, MERSİS): Panel → Mağaza ayarları → Mağaza.
      Bu bilgiler sözleşmelere ve altbilgiye otomatik eklenir.
- [ ] **İletişim bilgileri** sitede görünür olmalı (altbilgide gösterilir).
- [ ] **iyzico logo bandı**: altbilgide "iyzico ile güvenli ödeme" yazısı var. iyzico'nun
      verdiği resmi logo bandı görselini `resources/views/layouts/shop.blade.php` içindeki ilgili
      yere koyabilirsiniz.
- [ ] Siteniz HTTPS ile açılıyor olmalı (Docker kurulumunda otomatik).

Ayrıca ETBİS (Elektronik Ticaret Bilgi Sistemi) kaydını işletmenin kendisinin yapması gerekir.

## 2. Sandbox ile test

1. https://sandbox-merchant.iyzipay.com adresinden ücretsiz sandbox hesabı açın.
2. Ayarlar → API anahtarlarından **API Key** ve **Secret Key** değerlerini alın.
3. `.env`:
   ```dotenv
   PAYMENT_GATEWAY=iyzico
   IYZICO_BASE_URL=https://sandbox-api.iyzipay.com
   IYZICO_API_KEY=sandbox-...
   IYZICO_SECRET_KEY=sandbox-...
   ```
4. Test kartı: `5528 7900 0000 0008`, son kullanma ileri bir tarih, CVC `123`. 3D doğrulama ekranında
   sandbox’ın gösterdiği test kodunu girin.
   Başarısız ödeme denemek için iyzico dokümantasyonundaki hata kartlarını kullanın.

## 3. Canlıya geçiş

Başvurunuz onaylanınca canlı paneldeki anahtarlarla:

```dotenv
IYZICO_BASE_URL=https://api.iyzipay.com
IYZICO_API_KEY=...
IYZICO_SECRET_KEY=...
```

Sonra `docker compose up -d` ile servisleri yeniden başlatın.

## 4. Bildirim (webhook) adresi — önerilir

Müşteri ödemeyi yapıp tarayıcıyı mağazaya dönmeden kapatırsa, sipariş yine de "ödendi" olsun diye
iyzico panelinde **Ayarlar → Bildirimler** bölümüne şu adresi girin:

```
https://alanadiniz.com/odeme/webhook
```

Gelen bildirimlerin imzası (`X-IYZ-SIGNATURE-V3`) secret key ile doğrulanır.

## Nasıl çalışıyor (teknik)

- Sipariş oluşturulunca stok / atölye kontenjanı / kupon **35 dakikalığına ayrılır**. Ödeme bu sürede
  tamamlanmazsa zamanlayıcı rezervasyonu serbest bırakır.
- Dönüşte (`POST /odeme/donus`) sonuç iyzico'dan tekrar sorgulanır ve şunlar kontrol edilir:
  yanıt imzası, ödeme durumu, sipariş numarası, sepet numarası, para birimi ve **tutar**.
  Herhangi biri tutmazsa sipariş ödenmiş sayılmaz ve yöneticiye uyarı e-postası gider.
- Aynı ödeme hem dönüşten hem webhook'tan gelirse sipariş bir kez işlenir.
- Süre dolduktan sonra gelen ödemelerde stok hâlâ varsa sipariş kabul edilir; yoksa ödeme otomatik
  iptal edilip müşteriye bilgi verilir.
- İadeler panelden yapılır: aynı gün yapılan tam iade **iptal** (cancel) olarak gider, sonraki
  günlerde ürün bazında **iade** (refund) yapılır. Kısmi iade de desteklenir.
- Taksit: Panel → Mağaza ayarları → Kargo & ödeme. Taksit komisyonu iyzico sözleşmenize göre
  müşteriye yansıtılır.
