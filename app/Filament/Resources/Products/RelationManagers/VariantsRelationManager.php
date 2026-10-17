<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Support\MoneyInput;
use App\Models\Product;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Seçenekler (renk, sır, boyut…)';

    protected static ?string $modelLabel = 'seçenek';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Product $ownerRecord */
        return ! $ownerRecord->isWorkshop();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Seçenek adı')->placeholder('ör. Kobalt sır')->required()->maxLength(120),
            TextInput::make('sku')->label('Stok kodu')->unique(ignoreRecord: true),
            MoneyInput::make('price')->label('Farklı fiyat')->helperText('Boşsa ürün fiyatı geçerli.'),
            TextInput::make('stock')->label('Stok')->numeric()->integer()->default(0)->required(),
            Toggle::make('is_active')->label('Satışta')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->description('Seçenek eklerseniz müşteri satın alırken birini seçmek zorunda olur; stok seçenek bazında düşer.')
            ->columns([
                TextColumn::make('name')->label('Seçenek'),
                TextColumn::make('price')->label('Fiyat')
                    ->formatStateUsing(fn (?int $state) => $state ? Money::format($state) : '—'),
                TextInputColumn::make('stock')->label('Stok')->type('number')->rules(['integer', 'min:0'])->width('8rem'),
                ToggleColumn::make('is_active')->label('Satışta'),
            ])
            ->headerActions([CreateAction::make()->label('Seçenek ekle')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
