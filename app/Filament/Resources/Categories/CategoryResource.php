<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Support\NavigationGroup;
use App\Models\Category;
use App\Support\Human;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $slug = 'kategoriler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Catalog;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'kategori';

    protected static ?string $pluralModelLabel = 'kategoriler';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Ad')->required()->maxLength(120)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set, ?string $state, string $operation) => $operation === 'create' || blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
            TextInput::make('slug')->label('Bağlantı')->prefix('/kategori/')->required()->alphaDash()->unique(ignoreRecord: true),
            Select::make('parent_id')->label('Üst kategori')->relationship('parent', 'name', ignoreRecord: true)->placeholder('Yok (ana kategori)'),
            TextInput::make('sort_order')->label('Sıralama')->numeric()->integer()->default(0),
            Textarea::make('description')->label('Açıklama')->rows(2)->columnSpanFull(),
            FileUpload::make('image')->label('Görsel')->image()->disk('public')->directory('categories')->imageEditor(),
            Toggle::make('is_visible')->label('Sitede göster')->default(true),
            TextInput::make('seo_title')->label('SEO başlığı')->maxLength(70),
            TextInput::make('seo_description')->label('SEO açıklaması')->maxLength(300),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['products', 'products as for_sale_count' => fn (Builder $products) => $products->forSale()->where('stock', '>', 0)]))
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->circular()->imageSize(40),
                TextColumn::make('name')->label('Ad')->searchable()->description(fn (Category $record) => $record->parent?->name),
                TextColumn::make('products_count')->label('Parçalar')
                    ->formatStateUsing(fn (Category $record) => $record->products_count.' parça, '.Human::ofThem($record->for_sale_count).' satışta'),
                TextColumn::make('is_visible')->label('Nerede')->color('gray')
                    ->formatStateUsing(fn (Category $record) => $record->is_visible ? 'Mağazada' : 'Gizli'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('toggleVisible')
                        ->label(fn (Category $record) => $record->is_visible ? 'Gizle' : 'Mağazada göster')
                        ->icon('heroicon-o-eye')
                        ->action(fn (Category $record) => $record->update(['is_visible' => ! $record->is_visible])),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
