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
                    $publishedAt = $record->published_at && $record->published_at->isPast() ? $record->published_at : now();
                    $record->update(['status' => ProductStatus::Published, 'visibility' => ProductVisibility::Unlisted, 'published_at' => $publishedAt]);
                    // Keep the open form in sync, otherwise a later "Kaydet" would put the piece back to draft.
                    $this->refreshFormData(['status', 'visibility', 'published_at']);
                    $request->update(['status' => RequestStatus::InProgress]);

                    if ($request->isEmail()) {
                        Mail::to($request->contact)->send(new SimilarPieceReady($request, $record));
                        Notification::make()->success()->title('Link e-postayla gönderildi')->send();

                        return;
                    }

                    $whatsapp = $request->whatsappUrl('Merhaba, istediğiniz parça hazır: '.$record->url());
                    $livewire->dispatch('open-url', url: $whatsapp);
                    // Some phone browsers block tabs opened after a round-trip; the notification keeps a button.
                    Notification::make()->success()->persistent()
                        ->title('Parça yayında; mesajı WhatsApp’tan gönderin')
                        ->actions([Action::make('whatsapp')->label('WhatsApp’ta aç')->url($whatsapp, shouldOpenInNewTab: true)])
                        ->send();
                }),
            Action::make('share')
                ->label('Instagram linki')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->modalHeading('Paylaşım linkleri')
                ->modalContent(fn (Product $record) => view('filament.product-share', ['product' => $record]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Kapat'),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
