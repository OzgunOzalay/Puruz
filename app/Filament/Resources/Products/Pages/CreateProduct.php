<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function getRedirectUrl(): string
    {
        // Stay on the edit page so variants or workshop dates can be added right away.
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
