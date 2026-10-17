<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Models\Category;
use App\Models\Product;
use App\Models\SimilarRequest;
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
            ['Kobalt Kupa', 'kupalar', 'kupa-kobalt', 65000, null, null, true, 'Derin kobalt sırlı, el yapımı stoneware kupa. Avuç içine tam oturan kulp.', '350 ml · 9 cm yükseklik', 'Stoneware, kurşunsuz sır'],
            ['Kum Kupa', 'kupalar', 'kupa-kum', 60000, null, 2, true, 'Kum rengi mat sır, içi parlak.', '300 ml', 'Stoneware'],
            ['Yosun Kupa', 'kupalar', 'kupa-yesil', 60000, 72000, null, false, 'Yosun yeşili akıntılı sır.', '320 ml', 'Stoneware'],
            ['Benekli Servis Tabağı', 'tabaklar-kaseler', 'tabak-benekli', 95000, null, null, true, 'Benekli beyaz sırlı geniş servis tabağı.', 'Ø 28 cm', 'Benekli stoneware'],
            ['Kiremit Kase', 'tabaklar-kaseler', 'kase-terracotta', 55000, null, 5, false, 'Toprak kırmızısı salata ve meyve kasesi.', 'Ø 16 cm', 'Kırmızı kil'],
            ['Uzun Siyah Vazo', 'vazolar', 'vazo-uzun', 120000, null, null, true, 'Mat siyah, uzun boyunlu vazo.', '32 cm yükseklik', 'Siyah kil'],
            ['Yuvarlak Vazo', 'vazolar', 'vazo-yuvarlak', 85000, null, null, false, 'Kirli beyaz, yuvarlak gövdeli küçük vazo.', '18 cm yükseklik', 'Stoneware'],
        ];

        foreach ($products as $i => [$name, $category, $image, $price, $compare, $soldDaysAgo, $featured, $short, $dimensions, $materials]) {
            $product = Product::query()->create([
                'type' => ProductType::Unique,
                'name' => $name,
                'slug' => Str::slug($name),
                'short_description' => $short,
                'description' => '<p>'.$short.' Atölyemizde çarkta şekillendirilip iki kez fırınlandı. Her parça tek; renk ve dokusu fotoğraftakiyle aynıdır.</p>',
                'images' => [$this->storeImage($image)],
                'price' => $price,
                'compare_at_price' => $compare,
                'dimensions' => $dimensions,
                'materials' => $materials,
                'care' => $care,
                'status' => ProductStatus::Published,
                'visibility' => ProductVisibility::Public,
                'is_featured' => $featured,
                'sort_order' => $i,
                'published_at' => now()->subDays(20 - $i),
                'sold_at' => $soldDaysAgo ? now()->subDays($soldDaysAgo) : null,
            ]);
            $product->categories()->attach($categories[$category]);
        }

        foreach ([['Espresso Fincanı · Kobalt', 'espresso-fincani-kobalt'], ['Espresso Fincanı · Kirli Beyaz', 'espresso-fincani-kirli-beyaz']] as [$name, $slug]) {
            Product::query()->create([
                'type' => ProductType::Unique,
                'name' => $name,
                'slug' => $slug,
                'short_description' => 'Tek parça espresso fincanı.',
                'description' => '<p>Çarkta şekillendirilmiş, tek parça espresso fincanı.</p>',
                'images' => [$this->storeImage('espresso-seti')],
                'price' => 45000,
                'dimensions' => '90 ml',
                'materials' => 'Stoneware',
                'care' => $care,
                'status' => ProductStatus::Published,
                'visibility' => ProductVisibility::Public,
                'published_at' => now()->subDays(3),
            ])->categories()->attach($categories['kupalar']);
        }

        Product::query()->create([
            'type' => ProductType::Unique,
            'name' => 'Sırlı Küçük Tabak',
            'slug' => 'sirli-kucuk-tabak',
            'short_description' => 'Fırından yeni çıktı, fotoğrafları bekliyor.',
            'price' => 40000,
            'status' => ProductStatus::Draft,
            'visibility' => ProductVisibility::Public,
        ])->categories()->attach($categories['tabaklar-kaseler']);

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

        $kum = Product::query()->where('slug', 'kum-kupa')->first();
        $kase = Product::query()->where('slug', 'kiremit-kase')->first();
        if ($kum && $kase) {
            // forceCreate so the backdated created_at is kept.
            SimilarRequest::query()->forceCreate(['product_id' => $kum->id, 'message' => "Merhaba, bu kupadan 4 tane istiyorum, düğün hediyesi olacak.\nAralık ortasına yetişir mi?", 'contact' => '+905321112233', 'contact_type' => 'phone', 'created_at' => now()->subDay()]);
            SimilarRequest::query()->forceCreate(['product_id' => $kase->id, 'message' => 'Biraz daha büyük olabilir mi? Salata kasesi olarak kullanacağım.', 'contact' => 'mehmet@example.com', 'contact_type' => 'email', 'status' => 'contacted', 'admin_note' => 'Ölçüyü sordum, 22 cm istiyor.', 'created_at' => now()->subDays(3)]);
        }
    }

    private function storeImage(string $name): string
    {
        $path = 'products/'.$name.'.jpg';
        Storage::disk('public')->put($path, file_get_contents(database_path('seeders/assets/'.$name.'.jpg')));
        ProductImages::generate($path);

        return $path;
    }
}
