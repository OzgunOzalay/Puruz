<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Mail\AdminNewSimilarRequest;
use App\Mail\SimilarRequestReceived;
use App\Models\Product;
use App\Models\SimilarRequest;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SimilarRequestTest extends TestCase
{
    use RefreshDatabase;

    private Product $sold;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Settings::set(['admin_notification_email' => 'atolye@example.com']);
        $this->sold = Product::factory()->unique()->sold()->create(['name' => 'Kobalt Kupa']);
    }

    private function send(array $data = []): TestResponse
    {
        return $this->post(route('shop.similar-request', $this->sold->slug), $data + [
            'message' => 'Aynısından yeşil sırlı 2 tane istiyorum.',
            'contact' => '0532 123 45 67',
            'accept_kvkk' => '1',
        ]);
    }

    public function test_sold_piece_shows_the_request_form(): void
    {
        $this->get($this->sold->url())->assertOk()
            ->assertSee('benzerini iste')
            ->assertSee('Nasıl bir parça istersiniz?')
            ->assertSee('Telefon veya e-posta')
            ->assertDontSee('gibi bir parça yaptırmak istiyorum'); // the old WhatsApp prefill is gone

        $available = Product::factory()->unique()->create();
        $this->get($available->url())->assertDontSee('Nasıl bir parça istersiniz?');
    }

    public function test_request_with_phone_is_saved_and_admin_is_emailed(): void
    {
        $this->send()->assertRedirect()->assertSessionHas('similar_request_sent');

        $request = SimilarRequest::query()->sole();
        $this->assertSame($this->sold->id, $request->product_id);
        $this->assertSame('phone', $request->contact_type);
        $this->assertSame('+905321234567', $request->contact);
        $this->assertSame(RequestStatus::New, $request->status);
        Mail::assertQueued(AdminNewSimilarRequest::class, fn ($mail) => $mail->hasTo('atolye@example.com'));
        Mail::assertNotQueued(SimilarRequestReceived::class);
    }

    public function test_request_with_email_also_confirms_to_customer(): void
    {
        $this->send(['contact' => 'Ayse@Example.com']);

        $this->assertSame('ayse@example.com', SimilarRequest::query()->sole()->contact);
        Mail::assertQueued(SimilarRequestReceived::class, fn ($mail) => $mail->hasTo('ayse@example.com'));
    }

    // Review focus 1
    public function test_invalid_input_is_rejected(): void
    {
        $this->send(['message' => ''])->assertSessionHasErrors('message');
        $this->send(['contact' => '0532 12'])->assertSessionHasErrors('contact');
        $this->send(['contact' => '0232 123 45 67'])->assertSessionHasErrors('contact'); // landline
        $this->send(['accept_kvkk' => null])->assertSessionHasErrors('accept_kvkk');
        $this->send(['message' => str_repeat('a', 2001)])->assertSessionHasErrors('message');

        $this->assertSame(0, SimilarRequest::query()->count());
    }

    public function test_only_sold_pieces_accept_requests(): void
    {
        $available = Product::factory()->unique()->create();

        $this->post(route('shop.similar-request', $available->slug), [
            'message' => 'x', 'contact' => 'a@b.co', 'accept_kvkk' => '1',
        ])->assertNotFound();
    }

    public function test_honeypot_and_rate_limit_block_spam(): void
    {
        $this->send(['website' => 'http://spam.example'])->assertSessionHas('similar_request_sent');
        $this->assertSame(0, SimilarRequest::query()->count());

        // The honeypot hit counts toward the hourly limit of 5 as well.
        foreach (range(1, 4) as $i) {
            $this->send()->assertRedirect();
        }
        $this->send()->assertStatus(429);
        $this->assertSame(4, SimilarRequest::query()->count());
    }
}
