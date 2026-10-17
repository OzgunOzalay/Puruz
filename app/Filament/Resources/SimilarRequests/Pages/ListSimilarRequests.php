<?php

namespace App\Filament\Resources\SimilarRequests\Pages;

use App\Enums\RequestStatus;
use App\Filament\Resources\SimilarRequests\SimilarRequestResource;
use App\Filament\Resources\SimilarRequests\SimilarRequestsSummary;
use App\Models\SimilarRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListSimilarRequests extends ListRecords
{
    protected static string $resource = SimilarRequestResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return SimilarRequestsSummary::html();
    }

    public function getTabs(): array
    {
        $tab = fn (string $label, array $statuses) => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $statuses))
            ->badge(fn () => SimilarRequest::query()->whereIn('status', $statuses)->count() ?: null);

        return [
            'new' => $tab('Yeni', [RequestStatus::New]),
            'open' => $tab('Devam eden', RequestStatus::open()),
            'done' => $tab('Tamamlanan', [RequestStatus::Done]),
            'cancelled' => $tab('İptal', [RequestStatus::Cancelled]),
            'all' => Tab::make('Tümü'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'new';
    }
}
