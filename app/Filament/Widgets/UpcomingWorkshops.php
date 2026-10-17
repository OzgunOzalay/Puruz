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

    // Rendered with the page: the dashboard should show what needs doing without a loading step.
    protected static bool $isLazy = false;

    protected static ?string $heading = 'Yaklaşan atölyeler';

    public function table(Table $table): Table
    {
        return $table
            ->query(WorkshopSession::query()->upcoming()->with('product')->limit(5))
            ->paginated(false)
            ->recordUrl(fn () => WorkshopSessionResource::getUrl())
            ->emptyStateHeading('Planlanmış atölye yok')
            ->columns([
                TextColumn::make('starts_at')->label('')
                    ->formatStateUsing(fn (WorkshopSession $record) => $record->starts_at->translatedFormat('j M D H:i').' · '.$record->product->name.' · '.$record->seats_reserved.'/'.$record->capacity.' dolu'),
            ]);
    }
}
