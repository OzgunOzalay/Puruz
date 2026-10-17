<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Starter texts for the pages iyzico and Turkish distance-sales rules expect a shop to have.
 * They are templates: the owner (ideally with a lawyer) must review them and fill in the
 * company details from the admin panel before going live.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            ['hakkimizda', 'Hakkımızda', 1, <<<'HTML'
<p>Pürüz, el yapımı seramik ve mozaik üzerine çalışan küçük bir atölye. Her kupa, tabak ve vazo çarkta ya da elde tek tek şekilleniyor, sırlanıyor ve fırınlanıyor.</p>
<p>Adımızı da buradan alıyoruz: elde yapılan her parçada küçük pürüzler, sır akıntıları ve iz bırakan parmaklar olur. Bunları kusur değil, parçanın hikâyesi olarak görüyoruz.</p>
<p>Atölyemizde ayrıca herkesin katılabileceği mozaik ve seramik atölyeleri düzenliyoruz. Tarihleri <a href="/atolyeler">atölyeler</a> sayfasından takip edebilirsiniz.</p>
HTML],
            ['teslimat-ve-iade', 'Teslimat ve İade', 2, <<<'HTML'
<h3>Teslimat</h3>
<p>Stokta bulunan ürünler, ödemenin onaylanmasından sonra 1-3 iş günü içinde kargoya verilir. Siparişe özel üretilen ürünlerde hazırlık süresi ürün sayfasında belirtilir. Kargoya verildiğinde takip numaranız e-posta ile iletilir ve sipariş takip sayfanızda görünür.</p>
<p>Seramik ürünler kırılmaya karşı özenle paketlenir. Teslim alırken paketi kontrol etmenizi, hasarlı paketler için kargo görevlisine tutanak tutturmanızı rica ederiz.</p>
<h3>Cayma hakkı ve iade</h3>
<p>Ürünü teslim aldığınız tarihten itibaren 14 gün içinde herhangi bir gerekçe göstermeksizin cayma hakkınızı kullanabilirsiniz. Bunun için sipariş numaranızla birlikte bize e-posta veya WhatsApp üzerinden ulaşmanız yeterlidir.</p>
<p>Cayma bildiriminizin bize ulaşmasından itibaren 14 gün içinde ödemeniz, ödemede kullandığınız karta iade edilir. İade edilecek ürünün kullanılmamış ve hasarsız olması gerekir.</p>
<p>Mesafeli Sözleşmeler Yönetmeliği uyarınca, alıcının istekleri doğrultusunda kişiye özel hazırlanan ürünlerde ve belirli bir tarihte yapılması gereken boş zaman değerlendirme hizmetlerinde (atölyeler) cayma hakkı kullanılamaz. Atölye tarihinizi değiştirmek isterseniz en az 48 saat önce bize yazın, uygun bir tarihe aktarmaya çalışalım.</p>
HTML],
            ['on-bilgilendirme-formu', 'Ön Bilgilendirme Formu', 3, <<<'HTML'
<p>Bu form, 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği uyarınca, sözleşme kurulmadan önce alıcıyı bilgilendirmek amacıyla hazırlanmıştır. Satıcı bilgileri, ürün/hizmetlerin temel nitelikleri ve vergiler dahil toplam fiyat yukarıda yer almaktadır.</p>
<h3>Ödeme ve teslimat</h3>
<p>Ödeme, iyzico ödeme altyapısı üzerinden kredi veya banka kartı ile alınır. Ürünler, alıcının sipariş formunda belirttiği adrese kargo ile teslim edilir. Teslimat süresi, stoktaki ürünler için en geç 30 gündür; siparişe özel ürünlerde ürün sayfasında belirtilen hazırlık süresi geçerlidir.</p>
<h3>Cayma hakkı</h3>
<p>Alıcı, ürünü teslim aldığı tarihten itibaren 14 gün içinde herhangi bir gerekçe göstermeksizin ve cezai şart ödemeksizin sözleşmeden cayma hakkına sahiptir. Cayma bildirimi satıcının iletişim adreslerine yazılı olarak veya kalıcı veri saklayıcısı ile yapılabilir. Alıcının istekleri doğrultusunda kişiye özel hazırlanan ürünlerde ve belirli bir tarihte ifa edilen boş zaman değerlendirme hizmetlerinde cayma hakkı bulunmamaktadır.</p>
<h3>Şikâyet ve itirazlar</h3>
<p>Alıcı, şikâyet ve itirazlarını satıcıya iletebilir; ayrıca her yıl Ticaret Bakanlığı tarafından ilan edilen parasal sınırlar dahilinde tüketici hakem heyetlerine veya tüketici mahkemelerine başvurabilir.</p>
HTML],
            ['mesafeli-satis-sozlesmesi', 'Mesafeli Satış Sözleşmesi', 4, <<<'HTML'
<h3>Madde 1 – Taraflar ve konu</h3>
<p>İşbu sözleşme, yukarıda bilgileri yer alan satıcı ile alıcı arasında, alıcının satıcıya ait internet sitesi üzerinden elektronik ortamda siparişini verdiği ürün/hizmetlerin satışı ve teslimi ile ilgili olarak 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği hükümleri uyarınca tarafların hak ve yükümlülüklerini düzenler.</p>
<h3>Madde 2 – Ürün/hizmet ve bedel</h3>
<p>Sözleşme konusu ürün/hizmetlerin türü, miktarı, KDV dahil satış bedeli ve kargo ücreti yukarıda belirtildiği gibidir. Bedel, sipariş sırasında kredi veya banka kartı ile tahsil edilir.</p>
<h3>Madde 3 – Teslimat</h3>
<p>Ürünler, alıcının belirttiği teslimat adresine kargo ile gönderilir. Satıcı, siparişin ulaşmasından itibaren yasal 30 günlük süreyi aşmamak koşuluyla ürünü teslim etmekle yükümlüdür. Atölye hizmetleri, seçilen tarih ve saatte satıcının atölyesinde ifa edilir.</p>
<h3>Madde 4 – Cayma hakkı</h3>
<p>Alıcı, ürünün kendisine veya gösterdiği kişiye tesliminden itibaren 14 gün içinde cayma hakkını kullanabilir. Cayma hakkının kullanılması halinde satıcı, cayma bildiriminin kendisine ulaştığı tarihten itibaren 14 gün içinde tahsil edilen bedeli alıcının ödeme aracına iade eder; alıcı da ürünü 10 gün içinde satıcıya geri gönderir. Yönetmeliğin 15. maddesi uyarınca kişiye özel hazırlanan ürünler ile belirli bir tarihte ifa edilen boş zaman değerlendirme hizmetlerinde cayma hakkı kullanılamaz.</p>
<h3>Madde 5 – Genel hükümler</h3>
<p>Alıcı, ön bilgilendirme formunu okuyup bilgi sahibi olduğunu ve elektronik ortamda gerekli teyidi verdiğini kabul eder. Ürünün teslimatından sonra ödeme aracının yetkisiz kullanımı nedeniyle bedelin ilgili banka tarafından satıcıya ödenmemesi halinde alıcı ürünü satıcıya iade eder.</p>
<h3>Madde 6 – Uyuşmazlıklar</h3>
<p>İşbu sözleşmeden doğan uyuşmazlıklarda, Ticaret Bakanlığınca ilan edilen değere kadar tüketicinin yerleşim yerindeki tüketici hakem heyetleri, bu değerin üzerindeki uyuşmazlıklarda tüketici mahkemeleri yetkilidir.</p>
HTML],
            ['kvkk-aydinlatma-metni', 'KVKK Aydınlatma Metni', 5, <<<'HTML'
<p>6698 sayılı Kişisel Verilerin Korunması Kanunu (“KVKK”) uyarınca, veri sorumlusu sıfatıyla satıcı tarafından kişisel verileriniz aşağıda açıklanan kapsamda işlenmektedir.</p>
<h3>İşlenen veriler</h3>
<p>Ad soyad, e-posta adresi, telefon numarası, teslimat ve fatura adresi, isteğe bağlı olarak T.C. kimlik numarası veya vergi bilgileri, sipariş ve ödeme işlem bilgileri (kart bilgileriniz satıcı tarafından görülmez ve saklanmaz), IP adresi.</p>
<h3>Amaçlar ve hukuki sebepler</h3>
<p>Verileriniz; siparişinizin alınması, ödemenin gerçekleştirilmesi, ürünlerin teslimi, fatura düzenlenmesi, sipariş durumu hakkında bilgilendirme yapılması ve yasal yükümlülüklerin yerine getirilmesi amaçlarıyla, KVKK m.5/2-c (sözleşmenin ifası) ve m.5/2-ç (hukuki yükümlülük) hukuki sebeplerine dayanarak işlenir. Pazarlama amaçlı e-postalar yalnızca açık rızanız ile gönderilir.</p>
<h3>Aktarım</h3>
<p>Verileriniz, yalnızca yukarıdaki amaçlarla sınırlı olarak ödeme kuruluşu (iyzico), kargo firmaları, e-posta hizmet sağlayıcısı ve yetkili kamu kurumları ile paylaşılır.</p>
<h3>Haklarınız</h3>
<p>KVKK m.11 uyarınca verilerinizin işlenip işlenmediğini öğrenme, düzeltilmesini veya silinmesini isteme ve diğer haklarınızı kullanmak için bize e-posta ile başvurabilirsiniz.</p>
HTML],
            ['cerez-politikasi', 'Çerez Politikası', 6, <<<'HTML'
<p>Bu site yalnızca çalışması için zorunlu olan çerezleri kullanır: oturum çerezi (sepetinizi hatırlamak için) ve güvenlik çerezi (form güvenliği için). Reklam veya üçüncü taraf takip çerezi kullanılmaz.</p>
HTML],
        ];

        foreach ($pages as [$slug, $title, $order, $content]) {
            Page::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'content' => $content,
                'sort_order' => $order,
                'is_published' => true,
                'show_in_footer' => true,
            ]);
        }
    }
}
