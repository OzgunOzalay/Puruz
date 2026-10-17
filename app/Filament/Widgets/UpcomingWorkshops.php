<?php

namespace App\Filament\Widgets;

use App\Models\WorkshopSession;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class UpcomingWorkshops extends Widget
{
    protected static ?int $sort = 3;

    // Rendered with the page: the dashboard should show what needs doing without a loading step.
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.upcoming-workshops';

    /** @return Collection<int, WorkshopSession> */
    public static function sessions(): Collection
    {
        return WorkshopSession::query()->upcoming()->with('product')->limit(5)->get();
    }
}
