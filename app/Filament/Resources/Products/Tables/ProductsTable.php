<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variants', 'sessions', 'categories']))
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->state(fn (Product $record) => $record->coverImage())
                    ->disk('public')
                    ->imageSize(48)
                    ->square(),
                TextColumn::make('name')
                    ->label('Ürün')
                    ->searchable(['name', 'sku'])
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Product $record) => collect([$record->type->getLabel(), $record->sku])->filter()->implode(' · ')),
                TextColumn::make('price')
                    ->label('Fiyat')
                    ->sortable()
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->description(fn (Product $record) => $record->isOnSale() ? 'önce '.Money::format($record->compare_at_price) : null),
                TextColumn::make('stock')
                    ->label('Stok')
                    ->sortable()
                    ->state(fn (Product $record) => self::stockLabel($record))
                    ->color(fn (Product $record) => self::stockColor($record))
                    ->badge(),
                SelectColumn::make('status')
                    ->label('Durum')
                    ->options(ProductStatus::class)
                    ->selectablePlaceholder(false)
                    ->sortable(),
                SelectColumn::make('visibility')
                    ->label('Görünürlük')
                    ->options(ProductVisibility::class)
                    ->selectablePlaceholder(false),
                ToggleColumn::make('is_featured')
                    ->label('Öne çıkan'),
                TextColumn::make('categories.name')
                    ->label('Kategoriler')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('view_count')
                    ->label('Görüntülenme')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Güncellendi')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tür')->options(ProductType::class),
                SelectFilter::make('status')->label('Durum')->options(ProductStatus::class),
                SelectFilter::make('visibility')->label('Görünürlük')->options(ProductVisibility::class),
                SelectFilter::make('categories')->label('Kategori')->relationship('categories', 'name')->multiple()->preload(),
                TernaryFilter::make('is_featured')->label('Öne çıkan'),
                Filter::make('low_stock')
                    ->label('Stoğu azalanlar')
                    ->query(fn (Builder $query) => $query
                        ->where('type', ProductType::Physical)
                        ->where('track_stock', true)
                        ->where('made_to_order', false)
                        ->where('stock', '<=', (int) Settings::get('low_stock_threshold', 2))
                        ->whereDoesntHave('variants')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
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
                        ->excludeAttributes(['view_count', 'sku'])
                        ->beforeReplicaSaved(function (Product $replica, Product $record) {
                            $replica->name = $record->name.' (kopya)';
                            $replica->slug = $record->slug.'-'.Str::lower(Str::random(4));
                            $replica->status = ProductStatus::Draft;
                        })
                        ->after(function (Product $replica, Product $record) {
                            $replica->categories()->sync($record->categories->pluck('id'));
                            foreach ($record->variants as $variant) {
                                $replica->variants()->create($variant->only(['name', 'price', 'stock', 'is_active', 'sort_order']));
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

    private static function stockLabel(Product $record): string
    {
        if ($record->isWorkshop()) {
            $upcoming = $record->sessions->filter(fn ($s) => $s->starts_at->isFuture());

            return $upcoming->count().' seans · '.$upcoming->sum(fn ($s) => $s->seatsLeft()).' yer';
        }

        if ($record->variants->isNotEmpty()) {
            return $record->track_stock ? $record->variants->sum('stock').' (seçenekler)' : 'takipsiz';
        }

        if ($record->made_to_order) {
            return 'siparişe özel';
        }

        return $record->track_stock ? (string) $record->stock : 'takipsiz';
    }

    private static function stockColor(Product $record): string
    {
        if ($record->isWorkshop() || $record->made_to_order || ! $record->track_stock) {
            return 'gray';
        }

        $stock = $record->variants->isNotEmpty() ? $record->variants->sum('stock') : $record->stock;

        return match (true) {
            $stock <= 0 => 'danger',
            $stock <= (int) Settings::get('low_stock_threshold', 2) => 'warning',
            default => 'success',
        };
    }
}
