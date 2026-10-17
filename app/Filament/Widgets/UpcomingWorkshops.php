<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\WorkshopSessions\WorkshopSessionResource;
use App\Models\WorkshopSession;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingWorkshops extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Yaklaşan atölyeler';

    public function table(Table $table): Table
    {
        return $table
            ->query(WorkshopSession::query()->upcoming()->with('product'))
            ->paginated([5])
            ->recordUrl(fn () => WorkshopSessionResource::getUrl())
            ->emptyStateHeading('Planlanmış atölye yok')
            ->columns([
                TextColumn::make('product.name')->label('Atölye'),
                TextColumn::make('starts_at')->label('Tarih')
                    ->formatStateUsing(fn (WorkshopSession $record) => $record->starts_at->translatedFormat('j M D H:i')),
                TextColumn::make('seats_reserved')->label('Doluluk')->badge()
                    ->formatStateUsing(fn (WorkshopSession $record) => $record->seats_reserved.' / '.$record->capacity)
                    ->color(fn (WorkshopSession $record) => $record->seatsLeft() === 0 ? 'danger' : ($record->seatsLeft() <= 2 ? 'warning' : 'success')),
            ]);
    }
}
