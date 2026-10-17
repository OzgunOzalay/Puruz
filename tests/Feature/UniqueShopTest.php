<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniqueShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_piece_has_add_to_cart_and_no_quantity_picker(): void
    {
        $piece = Product::factory()->unique()->create();

        $this->get($piece->url())->assertOk()
            ->assertSee('sepete ekle')
            ->assertDontSee('son 1 adet')
            ->assertDontSee('inputmode="numeric"', false);
    }

    public function test_sold_piece_shows_sold_badge_and_whatsapp_request(): void
    {
        Settings::set(['whatsapp_number' => '905551112233']);
        $piece = Product::factory()->unique()->sold()->create(['name' => 'Kobalt Kupa']);

        $text = rawurlencode('Merhaba, "Kobalt Kupa" gibi bir parça yaptırmak istiyorum: '.$piece->url());

        $this->get($piece->url())->assertOk()
            ->assertSee('satıldı')
            ->assertSee('benzerini iste')
            ->assertSee('https://wa.me/905551112233?text='.$text, false)
            ->assertDontSee('sepete ekle');
    }

    public function test_request_falls_back_to_instagram_without_whatsapp(): void
    {
        Settings::set(['whatsapp_number' => '', 'instagram_handle' => 'puruzatolye']);
        $piece = Product::factory()->unique()->sold()->create();

        $this->assertSame('https://ig.me/m/puruzatolye', $piece->similarRequestUrl());
    }

    public function test_reserved_piece_shows_notice_and_disabled_button(): void
    {
        $piece = Product::factory()->unique()->create();
        Product::query()->whereKey($piece->id)->update(['stock' => 0]);

        $this->get($piece->url())->assertOk()
            ->assertSee('başka birinin sepetinde')
            ->assertDontSee('hemen al');
    }

    public function test_listings_show_available_pieces_before_sold_ones(): void
    {
        $sold = Product::factory()->unique()->sold()->create(['name' => 'Satılmış Vazo', 'sort_order' => 0]);
        $available = Product::factory()->unique()->create(['name' => 'Duran Kase', 'sort_order' => 5]);

        $this->get(route('shop.index'))->assertOk()->assertSeeInOrder(['Duran Kase', 'Satılmış Vazo']);
        $this->get(route('shop.index', ['stokta' => 1]))->assertSee('Duran Kase')->assertDontSee('Satılmış Vazo');
        $this->get(route('shop.home'))->assertOk()->assertSee('Duran Kase');
    }
}
