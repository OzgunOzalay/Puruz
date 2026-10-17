<?php

namespace App\Filament\Resources\SimilarRequests;

use App\Enums\RequestStatus;
use App\Filament\Support\PageSummary;
use App\Models\SimilarRequest;
use Illuminate\Support\HtmlString;

final class SimilarRequestsSummary
{
    /** @return array{new: int, open: int, done_month: int} */
    public static function stats(): array
    {
        return [
            'new' => SimilarRequest::query()->where('status', RequestStatus::New)->count(),
            'open' => SimilarRequest::query()->whereIn('status', RequestStatus::open())->count(),
            'done_month' => SimilarRequest::query()->where('status', RequestStatus::Done)->where('updated_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    public static function html(): HtmlString
    {
        $s = self::stats();

        return PageSummary::make([
            [$s['new'] ? $s['new'].' yeni talep' : 'Yeni talep yok'],
            [$s['open'] ? $s['open'].' devam ediyor' : null],
            [$s['done_month'] ? 'bu ay '.$s['done_month'].' tamamlandı' : null],
        ]);
    }
}
