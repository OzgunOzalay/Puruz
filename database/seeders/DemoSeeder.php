<?php

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Support\ProductImages;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->exists()) {
            return;
        }

        $categories = collect([
            ['Kupalar', 'kupalar', 'Sabah kahvesinden akşam çayına, elde şekillenmiş kupalar.'],
            ['Tabaklar & Kaseler', 'tabaklar-kaseler', 'Sofranıza biraz pürüz katacak tabak ve kaseler.'],
            ['Vazolar', 'vazolar', 'Tek dal çiçek için bile yeter.'],
            ['Atölyeler', 'atolyeler', 'Mozaik ve seramik atölyeleri.'],
        ])->mapWithKeys(fn ($c, $i) => [$c[1] => Category::query()->create([
            'name' => $c[0], 'slug' => $c[1], 'description' => $c[2], 'sort_order' => $i,
        ])]);

        $care = "Bulaşık makinesinde yıkanabilir.\nMikrodalgada kullanılabilir.\nAni sıcaklık değişimlerinden koruyun.";

        $products = [
            ['Kobalt Kupa', 'kupalar', 'kupa-kobalt', 65000, null, 6, true, 'Derin kobalt sırlı, el yapımı stoneware kupa. Avuç içine tam oturan kulp.', '350 ml · 9 cm yükseklik', 'Stoneware, kurşunsuz sır'],
            ['Kum Kupa', 'kupalar', 'kupa-kum', 60000, null, 1, true, 'Kum rengi mat sır, içi parlak. Her biri hafif farklı.', '300 ml', 'Stoneware'],
            ['Yosun Kupa', 'kupalar', 'kupa-yesil', 60000, 72000, 4, false, 'Yosun yeşili akıntılı sır.', '320 ml', 'Stoneware'],
            ['Benekli Servis Tabağı', 'tabaklar-kaseler', 'tabak-benekli', 95000, null, 3, true, 'Benekli beyaz sırlı geniş servis tabağı.', 'Ø 28 cm', 'Benekli stoneware'],
            ['Kiremit Kase', 'tabaklar-kaseler', 'kase-terracotta', 55000, null, 0, false, 'Toprak kırmızısı salata ve meyve kasesi.', 'Ø 16 cm', 'Kırmızı kil'],
            ['Uzun Siyah Vazo', 'vazolar', 'vazo-uzun', 120000, null, 2, true, 'Mat siyah, uzun boyunlu vazo.', '32 cm yükseklik', 'Siyah kil'],
            ['Yuvarlak Vazo', 'vazolar', 'vazo-yuvarlak', 85000, null, 5, false, 'Kirli beyaz, yuvarlak gövdeli küçük vazo.', '18 cm yükseklik', 'Stoneware'],
        ];

        foreach ($products as $i => [$name, $category, $image, $price, $compare, $stock, $featured, $short, $dimensions, $materials]) {
            $product = Product::query()->create([
                'type' => ProductType::Physical,
                'name' => $name,
                'slug' => Str::slug($name),
                'sku' => 'PRZ-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'short_description' => $short,
                'description' => '<p>'.$short.' Atölyemizde çarkta şekillendirilip iki kez fırınlandı. Elde yapıldığı için renk ve ölçülerde küçük farklılıklar olabilir.</p>',
                'images' => [$this->storeImage($image)],
                'price' => $price,
                'compare_at_price' => $compare,
                'stock' => $stock,
                'track_stock' => true,
                'dimensions' => $dimensions,
                'materials' => $materials,
                'care' => $care,
                'status' => ProductStatus::Published,
                'visibility' => ProductVisibility::Public,
                'is_featured' => $featured,
                'sort_order' => $i,
                'published_at' => now()->subDays(10 - $i),
            ]);
            $product->categories()->attach($categories[$category]);
        }

        $espresso = Product::query()->create([
            'type' => ProductType::Physical,
            'name' => 'Espresso Fincanı',
            'slug' => 'espresso-fincani',
            'short_description' => 'İki renk seçeneğiyle siparişe özel espresso fincanı.',
            'description' => '<p>Siparişiniz üzerine üretilir; sır rengini siz seçersiniz.</p>',
            'images' => [$this->storeImage('espresso-seti')],
            'price' => 45000,
            'track_stock' => true,
            'made_to_order' => true,
            'lead_time_days' => 10,
            'dimensions' => '90 ml',
            'materials' => 'Stoneware',
            'care' => $care,
            'status' => ProductStatus::Published,
            'visibility' => ProductVisibility::Public,
            'is_featured' => true,
            'published_at' => now()->subDay(),
        ]);
        $espresso->categories()->attach($categories['kupalar']);
        $espresso->variants()->createMany([
            ['name' => 'Kobalt', 'stock' => 8, 'sort_order' => 1],
            ['name' => 'Kirli beyaz', 'stock' => 8, 'sort_order' => 2],
        ]);

        $workshop = Product::query()->create([
            'type' => ProductType::Workshop,
            'name' => 'Mozaik Atölyesi',
            'slug' => 'mozaik-atolyesi',
            'short_description' => 'Aylık mozaik atölyesi: 4 hafta boyunca haftada bir gün, 3 saat. Tüm malzemeler dahil.',
            'description' => '<p>Cam ve seramik parçalarla kendi mozaik panonuzu tasarlayıp tamamlıyorsunuz. Kesme, yerleştirme ve derz tekniklerini adım adım öğreniyoruz. Deneyim gerekmez.</p><p>Salı veya perşembe grubundan birini seçin: 11.00–14.00 ya da 19.00–22.00.</p>',
            'images' => [$this->storeImage('mozaik-atolyesi')],
            'price' => 320000,
            'track_stock' => false,
            'materials' => 'Tüm malzemeler, çay ve kahve dahil',
            'status' => ProductStatus::Published,
            'visibility' => ProductVisibility::Public,
            'is_featured' => true,
            'published_at' => now()->subDays(3),
        ]);
        $workshop->categories()->attach($categories['atolyeler']);

        // Two monthly groups per weekday, starting on the first Tuesday/Thursday of next month.
        $month = CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();
        foreach ([CarbonImmutable::TUESDAY => 'Salı', CarbonImmutable::THURSDAY => 'Perşembe'] as $weekday => $dayName) {
            $first = $month->next($weekday);
            foreach ([['11:00', '14:00'], ['19:00', '22:00']] as [$start, $end]) {
                $workshop->sessions()->create([
                    'label' => $month->translatedFormat('F').' · '.$dayName.' grubu',
                    'starts_at' => $first->setTimeFromTimeString($start),
                    'ends_at' => $first->setTimeFromTimeString($end),
                    'schedule_note' => '4 hafta, her '.mb_strtolower($dayName),
                    'capacity' => 8,
                ]);
            }
        }

        Coupon::query()->create([
            'code' => 'HOSGELDIN10',
            'description' => 'İlk siparişe %10 indirim',
            'type' => CouponType::Percent,
            'value' => 10,
        ]);
    }

    private function storeImage(string $name): string
    {
        $path = 'products/'.$name.'.jpg';
        Storage::disk('public')->put($path, file_get_contents(database_path('seeders/assets/'.$name.'.jpg')));
        ProductImages::generate($path);

        return $path;
    }
}
