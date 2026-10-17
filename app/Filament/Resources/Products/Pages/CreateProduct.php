<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function getRedirectUrl(): string
    {
        // Stay on the edit page so photos, workshop dates or variants can be adjusted right away.
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /** "Oluştur ve yenisini ekle": the next piece starts with the same type and categories; the rest comes from defaults. */
    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        return Arr::only($data, ['type', 'categories']);
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->label('Oluştur ve yenisini ekle');
    }
}
