<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('share')
                ->label('Instagram linki')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->modalHeading('Paylaşım linkleri')
                ->modalContent(fn (Product $record) => view('filament.product-share', ['product' => $record]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Kapat'),
            Action::make('view')
                ->label(fn (Product $record) => $record->isReachable() ? 'Sitede gör' : 'Önizle')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (Product $record) => $record->isReachable() ? $record->url() : $record->previewUrl(), shouldOpenInNewTab: true),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
