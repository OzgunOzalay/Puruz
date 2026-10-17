<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Enums\RequestStatus;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\SimilarRequests\Pages\ListSimilarRequests;
use App\Filament\Resources\SimilarRequests\Pages\ViewSimilarRequest;
use App\Filament\Resources\SimilarRequests\SimilarRequestsSummary;
use App\Mail\SimilarPieceReady;
use App\Models\Order;
use App\Models\Product;
use App\Models\SimilarRequest;
use App\Models\User;
use App\Services\Orders\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSimilarRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAs(User::factory()->create());
    }

    private function request(array $attributes = []): SimilarRequest
    {
        return SimilarRequest::query()->create($attributes + [
            'product_id' => Product::factory()->unique()->sold()->create(['name' => 'Kobalt Kupa', 'materials' => 'Stoneware'])->id,
            'message' => "Aynısından yeşil olsun.\n2 tane.",
            'contact' => 'ayse@example.com',
            'contact_type' => 'email',
        ]);
    }

    public function test_list_page_with_summary_and_tabs(): void
    {
        $new = $this->request();
        $open = $this->request(['status' => RequestStatus::InProgress]);
        $this->request(['status' => RequestStatus::Done]);

        $this->assertSame(['new' => 1, 'open' => 1, 'done_month' => 1], SimilarRequestsSummary::stats());
        $this->get('/yonetim/talepler')->assertOk()->assertSee('1 yeni talep')->assertSee('Aynısından yeşil olsun.');

        Livewire::test(ListSimilarRequests::class)
            ->assertCanSeeTableRecords([$new])->assertCanNotSeeTableRecords([$open])
            ->set('activeTab', 'open')->assertCanSeeTableRecords([$open]);
    }

    public function test_view_page_changes_status_and_note(): void
    {
        $request = $this->request(['contact' => '+905321234567', 'contact_type' => 'phone']);

        $this->get('/yonetim/talepler/'.$request->id)->assertOk()
            ->assertSee('WhatsApp')->assertSee('https://wa.me/905321234567', false);

        Livewire::test(ViewSimilarRequest::class, ['record' => $request->id])
            ->callAction('changeStatus', data: ['status' => 'contacted'])
            ->callAction('note', data: ['admin_note' => 'Salı arayacağım']);

        $this->assertSame(RequestStatus::Contacted, $request->fresh()->status);
        $this->assertSame('Salı arayacağım', $request->fresh()->admin_note);
    }

    public function test_create_piece_makes_a_link_only_draft_linked_to_the_request(): void
    {
        $request = $this->request();

        Livewire::test(ViewSimilarRequest::class, ['record' => $request->id])->callAction('createPiece');

        $piece = $request->fresh()->createdProduct;
        $this->assertNotNull($piece);
        $this->assertSame(ProductStatus::Draft, $piece->status);
        $this->assertSame(ProductVisibility::Unlisted, $piece->visibility);
        $this->assertSame('Stoneware', $piece->materials);
        $this->assertSame([], $piece->images ?? []);
        $this->assertNull($piece->sold_at);
        $this->assertSame(1, $piece->stock);
    }

    // Review focus 2
    public function test_send_link_by_email_publishes_the_piece_and_moves_request_on(): void
    {
        $request = $this->request();
        $piece = Product::factory()->unique()->create(['status' => ProductStatus::Draft, 'visibility' => ProductVisibility::Unlisted]);
        $request->update(['created_product_id' => $piece->id]);

        Livewire::test(EditProduct::class, ['record' => $piece->slug])->callAction('sendLink');

        $this->assertSame(ProductStatus::Published, $piece->fresh()->status);
        $this->assertSame(ProductVisibility::Unlisted, $piece->fresh()->visibility);
        $this->assertSame(RequestStatus::InProgress, $request->fresh()->status);
        Mail::assertQueued(SimilarPieceReady::class, fn ($mail) => $mail->hasTo('ayse@example.com'));
        $this->get($piece->fresh()->url())->assertOk();
    }

    public function test_send_link_for_phone_contact_opens_whatsapp(): void
    {
        $request = $this->request(['contact' => '+905321234567', 'contact_type' => 'phone']);
        $piece = Product::factory()->unique()->create(['status' => ProductStatus::Draft, 'visibility' => ProductVisibility::Unlisted]);
        $request->update(['created_product_id' => $piece->id]);

        Livewire::test(EditProduct::class, ['record' => $piece->slug])
            ->callAction('sendLink')
            ->assertDispatched('open-url', url: $request->fresh()->whatsappUrl('Merhaba, istediğiniz parça hazır: '.$piece->fresh()->url()));

        $this->assertSame(RequestStatus::InProgress, $request->fresh()->status);
        Mail::assertNotQueued(SimilarPieceReady::class);
    }

    // Review focus 3
    public function test_request_completes_when_its_piece_sells_unless_cancelled(): void
    {
        $active = $this->request(['status' => RequestStatus::InProgress]);
        $cancelled = $this->request(['status' => RequestStatus::Cancelled]);
        $pieceA = Product::factory()->unique()->create();
        $pieceB = Product::factory()->unique()->create();
        $active->update(['created_product_id' => $pieceA->id]);
        $cancelled->update(['created_product_id' => $pieceB->id]);

        $order = Order::factory()->create();
        foreach ([$pieceA, $pieceB] as $piece) {
            $order->items()->create(['product_id' => $piece->id, 'product_type' => 'unique', 'name' => $piece->name, 'unit_price' => 1000, 'quantity' => 1, 'line_total' => 1000]);
        }
        app(ReservationService::class)->markSold($order->fresh('items'));

        $this->assertSame(RequestStatus::Done, $active->fresh()->status);
        $this->assertSame(RequestStatus::Cancelled, $cancelled->fresh()->status);
    }

    public function test_saving_after_sending_the_link_keeps_the_piece_published(): void
    {
        $request = $this->request();
        $piece = Product::factory()->unique()->create(['status' => ProductStatus::Draft, 'visibility' => ProductVisibility::Unlisted]);
        $request->update(['created_product_id' => $piece->id]);

        Livewire::test(EditProduct::class, ['record' => $piece->slug])
            ->callAction('sendLink')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(ProductStatus::Published, $piece->fresh()->status);
        $this->get($piece->fresh()->url())->assertOk();
    }

    public function test_sending_the_link_makes_a_scheduled_piece_reachable_now(): void
    {
        $request = $this->request();
        $piece = Product::factory()->unique()->create(['status' => ProductStatus::Draft, 'visibility' => ProductVisibility::Unlisted, 'published_at' => now()->addWeek()]);
        $request->update(['created_product_id' => $piece->id]);

        Livewire::test(EditProduct::class, ['record' => $piece->slug])->callAction('sendLink');

        $this->get($piece->fresh()->url())->assertOk();
    }

    public function test_phone_link_also_shows_a_whatsapp_notification(): void
    {
        $request = $this->request(['contact' => '+905321234567', 'contact_type' => 'phone']);
        $piece = Product::factory()->unique()->create(['status' => ProductStatus::Draft, 'visibility' => ProductVisibility::Unlisted]);
        $request->update(['created_product_id' => $piece->id]);

        Livewire::test(EditProduct::class, ['record' => $piece->slug])
            ->callAction('sendLink')
            ->assertNotified('Parça yayında; mesajı WhatsApp’tan gönderin');
    }

    public function test_create_piece_from_a_trashed_original_is_not_trashed_or_discounted(): void
    {
        $request = $this->request();
        $request->product->update(['compare_at_price' => 99000]);
        $request->product->delete();

        Livewire::test(ViewSimilarRequest::class, ['record' => $request->id])->callAction('createPiece');

        $piece = $request->fresh()->createdProduct;
        $this->assertNotNull($piece);
        $this->assertNull($piece->deleted_at);
        $this->assertNull($piece->compare_at_price);
    }
}
