<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_pages_render(): void
    {
        $product = Product::factory()->create(['name' => 'Kobalt Kupa']);
        $workshop = Product::factory()->workshop()->create(['name' => 'Mozaik Atölyesi']);
        $workshop->sessions()->create(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(3), 'capacity' => 8]);

        $this->get('/')->assertOk()->assertSee('Kobalt Kupa');
        $this->get('/urunler')->assertOk()->assertSee('Kobalt Kupa');
        $this->get('/atolyeler')->assertOk()->assertSee('Mozaik Atölyesi');
        $this->get('/link')->assertOk();
        $this->get($product->url())->assertOk()->assertSee('og:type', false)->assertSee('application/ld+json', false);
        $this->get($workshop->url())->assertOk()->assertSee('8 yer kaldı');
        $this->get('/sitemap.xml')->assertOk()->assertSee($product->url());
    }

    public function test_hidden_and_draft_products_are_not_reachable(): void
    {
        $hidden = Product::factory()->create(['visibility' => ProductVisibility::Hidden]);
        $draft = Product::factory()->create(['status' => ProductStatus::Draft]);
        $scheduled = Product::factory()->create(['published_at' => now()->addDay()]);

        $this->get($hidden->url())->assertNotFound();
        $this->get($draft->url())->assertNotFound();
        $this->get($scheduled->url())->assertNotFound();
    }

    public function test_unlisted_products_open_by_link_but_are_not_listed(): void
    {
        $unlisted = Product::factory()->create(['name' => 'Gizli Vazo', 'visibility' => ProductVisibility::Unlisted]);

        $this->get($unlisted->url())->assertOk()->assertSee('Gizli Vazo');
        $this->get('/urunler')->assertDontSee('Gizli Vazo');
        $this->get('/urunler?q=Gizli')->assertDontSee('Gizli Vazo');
        $this->get('/sitemap.xml')->assertDontSee($unlisted->url());
    }

    public function test_draft_can_be_previewed_with_signed_link_only(): void
    {
        $draft = Product::factory()->create(['name' => 'Taslak Tabak', 'status' => ProductStatus::Draft]);

        $this->get($draft->previewUrl())->assertOk()->assertSee('Önizleme')->assertSee('noindex', false);
        $this->get(route('shop.product.preview', $draft))->assertForbidden();
    }

    public function test_short_link_redirects_and_keeps_utm_tags(): void
    {
        $product = Product::factory()->create();

        $this->get('/p/'.$product->id.'?utm_source=instagram&utm_medium=story')
            ->assertRedirect($product->url(['utm_source' => 'instagram', 'utm_medium' => 'story']));
    }

    public function test_utm_tags_are_remembered_in_session(): void
    {
        $product = Product::factory()->create();

        $this->get($product->url(['utm_source' => 'instagram', 'utm_medium' => 'story', 'utm_campaign' => 'yaz']))
            ->assertSessionHas('utm.utm_medium', 'story');
    }
}
