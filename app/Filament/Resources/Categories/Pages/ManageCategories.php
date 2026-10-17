<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Support\PageSummary;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return PageSummary::make([[Category::query()->count().' kategori · '.Product::query()->whereHas('categories')->count().' parça']]);
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Yeni kategori')];
    }
}
