<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\WorkshopSessions\Pages\WorkshopCalendar;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkshopSession;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkshopCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function makeSession(array $attributes = []): WorkshopSession
    {
        return WorkshopSession::query()->create($attributes + [
            'product_id' => Product::factory()->workshop()->create()->id,
            'starts_at' => '2026-11-03 11:00',
            'ends_at' => '2026-11-03 14:00',
            'capacity' => 8,
        ]);
    }

    private function book(WorkshopSession $session): void
    {
        Order::factory()->create()->items()->create([
            'product_id' => $session->product_id,
            'workshop_session_id' => $session->id,
            'name' => $session->product->name,
            'unit_price' => 320000,
            'quantity' => 1,
            'line_total' => 320000,
        ]);
    }

    public function test_calendar_and_list_pages_render(): void
    {
        $this->get('/yonetim/atolye-takvimi')->assertOk()->assertSee('Toplu seans oluştur');
        $this->get('/yonetim/atolye-takvimi/liste')->assertOk();
    }

    public function test_events_cover_only_the_visible_range(): void
    {
        $inside = $this->makeSession(['starts_at' => '2026-11-10 19:00', 'ends_at' => '2026-11-10 22:00', 'seats_reserved' => 3]);
        $this->makeSession(['starts_at' => '2026-12-20 11:00', 'ends_at' => '2026-12-20 14:00']);
        $booked = $this->makeSession(['starts_at' => '2026-11-12 11:00', 'ends_at' => '2026-11-12 14:00']);
        $this->book($booked);

        $events = collect(Livewire::test(WorkshopCalendar::class)->instance()
            ->fetchEvents('2026-10-26T00:00:00Z', '2026-12-07T00:00:00Z'))->keyBy('id');

        $this->assertSame([$inside->id, $booked->id], $events->keys()->sort()->values()->all());
        $this->assertSame('2026-11-10T19:00:00', $events[$inside->id]['start']);
        $this->assertSame('2026-11-10T22:00:00', $events[$inside->id]['end']);
        $this->assertStringContainsString('3/8', $events[$inside->id]['title']);
        $this->assertTrue($events[$inside->id]['editable']);
        $this->assertFalse($events[$booked->id]['editable']);
    }

    public function test_dragging_a_session_saves_the_new_times(): void
    {
        $session = $this->makeSession();

        $moved = Livewire::test(WorkshopCalendar::class)->instance()
            ->moveSession($session->id, '2026-11-05T19:00:00Z', '2026-11-05T22:30:00Z');

        $this->assertTrue($moved);
        $session->refresh();
        $this->assertSame('2026-11-05 19:00', $session->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-05 22:30', $session->ends_at->format('Y-m-d H:i'));
    }

    public function test_booked_sessions_and_invalid_times_cannot_be_dragged(): void
    {
        $booked = $this->makeSession();
        $this->book($booked);
        $free = $this->makeSession();
        $calendar = Livewire::test(WorkshopCalendar::class)->instance();

        $this->assertFalse($calendar->moveSession($booked->id, '2026-11-05T11:00:00Z', '2026-11-05T14:00:00Z'));
        $this->assertFalse($calendar->moveSession($free->id, '2026-11-05T14:00:00Z', '2026-11-05T14:00:00Z'));

        $this->assertSame('2026-11-03 11:00', $booked->fresh()->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-03 11:00', $free->fresh()->starts_at->format('Y-m-d H:i'));
    }

    public function test_clicking_a_day_prefills_and_creates_a_session(): void
    {
        $workshop = Product::factory()->workshop()->create();

        Livewire::test(WorkshopCalendar::class)
            ->mountAction(TestAction::make('createSession')->arguments(['start' => '2026-11-04T00:00:00Z', 'allDay' => true]))
            ->assertActionDataSet(['starts_at' => '2026-11-04 11:00', 'ends_at' => '2026-11-04 14:00'])
            ->setActionData(['product_id' => $workshop->id, 'capacity' => 6])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $session = $workshop->sessions()->sole();
        $this->assertSame('2026-11-04 11:00', $session->starts_at->format('Y-m-d H:i'));
        $this->assertSame(6, $session->capacity);
    }

    public function test_selecting_hours_prefills_the_exact_times(): void
    {
        Livewire::test(WorkshopCalendar::class)
            ->mountAction(TestAction::make('createSession')->arguments(['start' => '2026-11-04T18:00:00Z', 'end' => '2026-11-04T21:30:00Z', 'allDay' => false]))
            ->assertActionDataSet(['starts_at' => '2026-11-04 18:00', 'ends_at' => '2026-11-04 21:30']);
    }

    public function test_clicking_a_session_allows_editing_it(): void
    {
        $session = $this->makeSession();

        Livewire::test(WorkshopCalendar::class)
            ->callAction([TestAction::make('session')->arguments(['session' => $session->id]), 'edit'], data: ['capacity' => 12])
            ->assertHasNoActionErrors();

        $this->assertSame(12, $session->fresh()->capacity);
    }

    public function test_sessions_with_bookings_cannot_be_deleted_from_the_calendar(): void
    {
        $booked = $this->makeSession();
        $this->book($booked);
        $free = $this->makeSession();

        Livewire::test(WorkshopCalendar::class)
            ->mountAction(TestAction::make('session')->arguments(['session' => $booked->id]))
            ->assertActionDisabled('delete');

        Livewire::test(WorkshopCalendar::class)
            ->callAction([TestAction::make('session')->arguments(['session' => $free->id]), 'delete']);

        $this->assertModelExists($booked);
        $this->assertModelMissing($free);
    }
}
