<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni ürün / atölye'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tümü'),
            'physical' => Tab::make('Ürünler')->modifyQueryUsing(fn (Builder $query) => $query->where('type', ProductType::Physical)),
            'workshop' => Tab::make('Atölyeler')->modifyQueryUsing(fn (Builder $query) => $query->where('type', ProductType::Workshop)),
            'draft' => Tab::make('Taslaklar')->modifyQueryUsing(fn (Builder $query) => $query->where('status', ProductStatus::Draft)),
            'sold_out' => Tab::make('Tükenenler')->modifyQueryUsing(fn (Builder $query) => $query
                ->where('type', ProductType::Physical)->where('track_stock', true)->where('made_to_order', false)
                ->where(fn (Builder $q) => $q
                    ->where(fn (Builder $query) => $query->whereDoesntHave('variants')->where('stock', '<=', 0))
                    ->orWhere(fn (Builder $query) => $query->whereHas('variants')->whereDoesntHave('variants', fn (Builder $v) => $v->where('stock', '>', 0))))),
        ];
    }
}
