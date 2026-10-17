<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function getRedirectUrl(): string
    {
        // Stay on the edit page so photos, workshop dates or variants can be adjusted right away.
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /** "Oluştur ve yenisini ekle": start the next piece with the same type and categories. */
    public function createAnother(): void
    {
        $keep = ['type' => $this->data['type'] ?? null, 'categories' => $this->data['categories'] ?? []];

        parent::createAnother();

        $this->form->fill($keep + ['status' => ProductStatus::Published->value, 'visibility' => ProductVisibility::Public->value]);
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->label('Oluştur ve yenisini ekle');
    }
}
