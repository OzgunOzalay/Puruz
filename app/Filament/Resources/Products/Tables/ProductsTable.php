<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\ProductStatusText;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variants', 'sessions', 'categories']))
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->state(fn (Product $record) => $record->coverImage())
                    ->disk('public')
                    ->imageSize(56)
                    ->square(),
                TextColumn::make('name')
                    ->label('Parça')
                    ->searchable(['name', 'sku'])
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->description(fn (Product $record) => collect([$record->categories->first()?->name, $record->sku, $record->dimensions])->filter()->implode(' · ')),
                TextColumn::make('price')
                    ->label('Fiyat')
                    ->sortable()
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->description(fn (Product $record) => $record->isOnSale() ? new HtmlString('<s>'.e(Money::format($record->compare_at_price)).'</s>') : null),
                TextColumn::make('state')
                    ->label('Durum')
                    ->state(fn (Product $record) => ProductStatusText::html($record))
                    ->html()
                    // "Days in shop" for available pieces, sale date for sold ones.
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw(
                        'COALESCE(sold_at, published_at, created_at) '.($direction === 'asc' ? 'asc' : 'desc'))),
                TextColumn::make('visibility')
                    ->label('Nerede')
                    ->formatStateUsing(fn (Product $record) => match ($record->visibility) {
                        ProductVisibility::Public => 'Mağazada',
                        ProductVisibility::Unlisted => 'Sadece link',
                        ProductVisibility::Hidden => 'Gizli',
                    }.($record->is_featured ? ' · ★' : ''))
                    ->color('gray'),
                TextColumn::make('view_count')
                    ->label('Görüntülenme')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('categories')->label('Kategori')->relationship('categories', 'name')->multiple()->preload(),
                Filter::make('price')
                    ->label('Fiyat aralığı')
                    ->schema([
                        TextInput::make('min')->label('En az (₺)')->numeric(),
                        TextInput::make('max')->label('En çok (₺)')->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(filled($data['min'] ?? null), fn (Builder $q) => $q->where('price', '>=', (int) $data['min'] * 100))
                        ->when(filled($data['max'] ?? null), fn (Builder $q) => $q->where('price', '<=', (int) $data['max'] * 100))),
                TernaryFilter::make('is_featured')->label('Öne çıkan'),
                SelectFilter::make('visibility')->label('Görünürlük')->options(ProductVisibility::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('togglePublish')
                        ->label(fn (Product $record) => $record->status === ProductStatus::Published ? 'Taslağa al' : 'Yayınla')
                        ->icon(fn (Product $record) => $record->status === ProductStatus::Published ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                        ->action(fn (Product $record) => self::bulkUpdate(new Collection([$record]), [
                            'status' => $record->status === ProductStatus::Published ? ProductStatus::Draft : ProductStatus::Published,
                        ])),
                    Action::make('visibility')
                        ->label('Görünürlük')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->fillForm(fn (Product $record) => ['visibility' => $record->visibility])
                        ->schema([Radio::make('visibility')->label('Görünürlük')->options(ProductVisibility::class)->required()])
                        ->action(fn (Product $record, array $data) => self::bulkUpdate(new Collection([$record]), ['visibility' => $data['visibility']])),
                    Action::make('toggleFeatured')
                        ->label(fn (Product $record) => $record->is_featured ? 'Öne çıkarmayı kaldır' : 'Öne çıkar')
                        ->icon('heroicon-o-star')
                        ->action(fn (Product $record) => self::bulkUpdate(new Collection([$record]), ['is_featured' => ! $record->is_featured])),
                    Action::make('share')
                        ->label('Instagram linki')
                        ->icon('heroicon-o-link')
                        ->modalHeading(fn (Product $record) => $record->name.' · paylaşım linkleri')
                        ->modalContent(fn (Product $record) => view('filament.product-share', ['product' => $record]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Kapat'),
                    Action::make('view')
                        ->label('Sitede gör')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (Product $record) => $record->isReachable() ? $record->url() : $record->previewUrl(), shouldOpenInNewTab: true),
                    ReplicateAction::make()
                        ->label('Kopyala')
                        ->excludeAttributes(['view_count', 'sku', 'images', 'og_image', 'sold_at', 'published_at'])
                        ->beforeReplicaSaved(function (Product $replica, Product $record) {
                            $replica->name = $record->name.' (kopya)';
                            $replica->slug = $record->slug.'-'.Str::lower(Str::random(4));
                            $replica->status = ProductStatus::Draft;
                            $replica->images = [];
                            $replica->stock = $record->isUnique() ? 1 : $record->stock;
                        })
                        ->after(function (Product $replica, Product $record) {
                            $replica->categories()->sync($record->categories->pluck('id'));
                            if ($record->type === ProductType::Physical) {
                                foreach ($record->variants as $variant) {
                                    $replica->variants()->create($variant->only(['name', 'price', 'stock', 'is_active', 'sort_order']));
                                }
                            }
                        })
                        ->successRedirectUrl(fn (Product $replica) => ProductResource::getUrl('edit', ['record' => $replica])),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label('Yayınla')
                        ->icon('heroicon-o-eye')
                        ->action(fn (Collection $records) => self::bulkUpdate($records, ['status' => ProductStatus::Published])),
                    BulkAction::make('draft')
                        ->label('Taslağa al')
                        ->icon('heroicon-o-eye-slash')
                        ->action(fn (Collection $records) => self::bulkUpdate($records, ['status' => ProductStatus::Draft])),
                    BulkAction::make('visibility')
                        ->label('Görünürlüğü değiştir')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema([Select::make('visibility')->label('Görünürlük')->options(ProductVisibility::class)->required()])
                        ->action(fn (Collection $records, array $data) => self::bulkUpdate($records, ['visibility' => $data['visibility']])),
                    BulkAction::make('feature')
                        ->label('Öne çıkar / kaldır')
                        ->icon('heroicon-o-star')
                        ->schema([Select::make('is_featured')->label('Öne çıkan')->options([1 => 'Öne çıkar', 0 => 'Öne çıkandan kaldır'])->required()])
                        ->action(fn (Collection $records, array $data) => self::bulkUpdate($records, ['is_featured' => (bool) $data['is_featured']])),
                    BulkAction::make('categorize')
                        ->label('Kategoriye ekle')
                        ->icon('heroicon-o-tag')
                        ->schema([Select::make('category_id')->label('Kategori')->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))->required()])
                        ->action(function (Collection $records, array $data) {
                            $records->each(fn (Product $p) => $p->categories()->syncWithoutDetaching([$data['category_id']]));
                            Notification::make()->success()->title($records->count().' ürün kategoriye eklendi')->send();
                        }),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function bulkUpdate(Collection $records, array $attributes): void
    {
        $records->each(fn (Product $product) => $product->update($attributes));
        Notification::make()->success()->title($records->count().' ürün güncellendi')->send();
    }
}
