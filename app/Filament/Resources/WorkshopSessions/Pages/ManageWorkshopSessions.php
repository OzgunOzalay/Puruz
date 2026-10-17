<?php

namespace App\Filament\Resources\WorkshopSessions\Pages;

use App\Filament\Resources\WorkshopSessions\WorkshopSessionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkshopSessions extends ManageRecords
{
    protected static string $resource = WorkshopSessionResource::class;

    protected static ?string $title = 'Atölye seansları (liste)';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendar')
                ->label('Takvim')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->url(WorkshopSessionResource::getUrl()),
        ];
    }
}
