<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Enums\RequestStatus;
use App\Filament\Resources\Products\ProductResource;
use App\Mail\SimilarPieceReady;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendLink')
                ->label('Müşteriye linki gönder')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (Product $record) => $record->similarRequest && $record->sold_at === null)
                ->requiresConfirmation()
                ->modalDescription(fn (Product $record) => $record->similarRequest->isEmail()
                    ? $record->similarRequest->contact.' adresine parçanın linki gönderilecek. Taslaksa yayına alınır (sadece link ile).'
                    : 'WhatsApp açılacak; mesajı siz gönderirsiniz. Taslaksa yayına alınır (sadece link ile).')
                ->action(function (Product $record, $livewire) {
                    $request = $record->similarRequest;
                    // The customer must be able to open the link: publish, but keep it out of listings.
                    $record->update(['status' => ProductStatus::Published, 'visibility' => ProductVisibility::Unlisted, 'published_at' => $record->published_at ?? now()]);
                    $request->update(['status' => RequestStatus::InProgress]);

                    if ($request->isEmail()) {
                        Mail::to($request->contact)->send(new SimilarPieceReady($request, $record));
                        Notification::make()->success()->title('Link e-postayla gönderildi')->send();

                        return;
                    }

                    $livewire->dispatch('open-url', url: $request->whatsappUrl('Merhaba, istediğiniz parça hazır: '.$record->url()));
                }),
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
