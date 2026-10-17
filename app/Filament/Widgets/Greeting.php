<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrdersSummary;
use App\Filament\Resources\SimilarRequests\SimilarRequestsSummary;
use App\Support\Money;
use Filament\Widgets\Widget;

class Greeting extends Widget
{
    protected static ?int $sort = 0;

    // Rendered with the page: the dashboard should show what needs doing without a loading step.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.greeting';

    public static function sentence(): string
    {
        $hour = (int) now()->timezone(config('app.timezone'))->format('G');
        $hello = match (true) {
            $hour < 12 => 'Günaydın.',
            $hour < 18 => 'İyi günler.',
            default => 'İyi akşamlar.',
        };

        $orders = OrdersSummary::stats();
        $requests = SimilarRequestsSummary::stats();

        $waiting = collect([
            $orders['to_pack'] ? $orders['to_pack'].' sipariş paketlenmeyi' : null,
            $requests['new'] ? $requests['new'].' yeni talep yanıt' : null,
        ])->filter();

        if ($waiting->isEmpty()) {
            return $hello.' Bekleyen iş yok.';
        }

        $sentence = $hello.' '.$waiting->implode(', ').' bekliyor.';

        return $orders['week_total'] > 0 ? $sentence.' Bu hafta '.Money::format($orders['week_total']).' satış.' : $sentence;
    }
}
