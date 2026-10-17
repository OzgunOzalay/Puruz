<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\ProductsSummary;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni parça / atölye'),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return ProductsSummary::html();
    }

    public function getTabs(): array
    {
        // Filament injects closure arguments by name, so the scope is wrapped to receive `$query`.
        $tab = fn (string $label, \Closure $scope) => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $scope($query))
            ->badge(fn () => $scope(Product::query())->count());

        return [
            'available' => $tab('Satışta', fn (Builder $q) => $q->forSale()),
            'sold' => $tab('Satıldı', fn (Builder $q) => $q->whereNotNull('sold_at')),
            'draft' => $tab('Taslaklar', fn (Builder $q) => $q->where('status', ProductStatus::Draft)),
            'workshop' => $tab('Atölyeler', fn (Builder $q) => $q->where('type', ProductType::Workshop)),
            'all' => Tab::make('Tümü'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'available';
    }
}
