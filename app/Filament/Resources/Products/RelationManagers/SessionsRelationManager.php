<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\WorkshopSessions\WorkshopSessionResource;
use App\Models\Product;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    protected static ?string $title = 'Atölye tarihleri';

    protected static ?string $modelLabel = 'seans';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Product $ownerRecord */
        return $ownerRecord->isWorkshop();
    }

    public function form(Schema $schema): Schema
    {
        return WorkshopSessionResource::form($schema, withProduct: false);
    }

    public function table(Table $table): Table
    {
        return WorkshopSessionResource::sessionsTable($table, withProduct: false);
    }
}
