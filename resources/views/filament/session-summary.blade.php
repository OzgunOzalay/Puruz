<div class="space-y-4">
    <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        <div>
            <dt class="text-gray-500">Doluluk</dt>
            <dd class="font-medium">{{ $session->seats_reserved }} / {{ $session->capacity }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Fiyat</dt>
            <dd class="font-medium">{{ \App\Support\Money::format($session->effectivePrice()) }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Sitede</dt>
            <dd class="font-medium">{{ $session->is_visible ? 'Görünür' : 'Gizli' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Program</dt>
            <dd class="font-medium">{{ $session->schedule_note ?: '—' }}</dd>
        </div>
    </dl>

    <div>
        <h3 class="mb-1 text-sm font-semibold">Katılımcılar</h3>
        @include('filament.session-attendees', ['items' => $items])
    </div>
</div>
