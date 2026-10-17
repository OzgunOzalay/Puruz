<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Support\PageSummary;
use App\Models\Page;
use App\Support\Human;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $last = Page::query()->max('updated_at');

        return PageSummary::make([[Page::query()->count().' sayfa', $last ? '· en son '.Human::day(Carbon::parse($last)).' güncellendi' : null]]);
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Yeni sayfa')];
    }
}
