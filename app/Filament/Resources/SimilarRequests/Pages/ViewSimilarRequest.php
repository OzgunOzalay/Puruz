<?php

namespace App\Filament\Resources\SimilarRequests\Pages;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Enums\RequestStatus;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\SimilarRequests\SimilarRequestResource;
use App\Models\SimilarRequest;
use App\Support\Human;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ViewSimilarRequest extends ViewRecord
{
    protected static string $resource = SimilarRequestResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->product?->name.' gibi bir parça';
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->schema([
                Section::make('Talep')->schema([
                    TextEntry::make('message')->hiddenLabel()
                        ->formatStateUsing(fn (string $state) => new HtmlString(nl2br(e($state))))->html(),
                    TextEntry::make('created_at')->label('Geldi')
                        ->formatStateUsing(fn (SimilarRequest $record) => Human::date($record->created_at)),
                ]),
                Section::make('Örnek parça')->schema([
                    TextEntry::make('product.name')->label('Parça')
                        ->url(fn (SimilarRequest $record) => $record->product ? ProductResource::getUrl('edit', ['record' => $record->product]) : null),
                    TextEntry::make('createdProduct.name')->label('Bu talep için yapılan parça')->placeholder('Henüz yok')
                        ->url(fn (SimilarRequest $record) => $record->createdProduct ? ProductResource::getUrl('edit', ['record' => $record->createdProduct]) : null),
                ]),
            ]),
            Grid::make(1)->columnSpan(1)->schema([
                Section::make('İletişim')->schema([
                    TextEntry::make('contact')->hiddenLabel()->copyable()
                        ->formatStateUsing(fn (SimilarRequest $record) => $record->displayContact())
                        ->suffix(fn (SimilarRequest $record) => $record->isEmail() ? null : ' · WhatsApp')
                        ->url(fn (SimilarRequest $record) => $record->whatsappUrl() ?? $record->mailtoUrl('Pürüz Atölye · talebiniz'), shouldOpenInNewTab: true)
                        ->helperText(fn (SimilarRequest $record) => $record->isEmail() ? 'E-posta göndermek için tıklayın' : 'WhatsApp’ta yazmak için tıklayın'),
                ]),
                Section::make('Durum')->schema([
                    TextEntry::make('status')->hiddenLabel()
                        ->formatStateUsing(fn (SimilarRequest $record) => SimilarRequestResource::statusHtml($record))->html(),
                ]),
                Section::make('Notlarım')->schema([
                    TextEntry::make('admin_note')->hiddenLabel()->placeholder('Not yok')
                        ->formatStateUsing(fn (string $state) => new HtmlString(nl2br(e($state))))->html(),
                ]),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createPiece')
                ->label('Parçayı oluştur')
                ->icon('heroicon-o-sparkles')
                ->visible(fn (SimilarRequest $record) => ! $record->created_product_id && $record->product)
                ->requiresConfirmation()
                ->modalDescription('Örnek parçanın metinleriyle, fotoğrafsız ve “sadece link ile” görünür bir taslak oluşturulur.')
                ->action(function (SimilarRequest $record) {
                    $original = $record->product;
                    $piece = $original->replicate(['view_count', 'sku', 'images', 'og_image', 'sold_at', 'published_at', 'slug', 'stock', 'is_featured']);
                    $piece->forceFill([
                        'type' => ProductType::Unique,
                        'name' => $original->name.' (özel)',
                        'slug' => $original->slug.'-ozel-'.Str::lower(Str::random(4)),
                        'status' => ProductStatus::Draft,
                        'visibility' => ProductVisibility::Unlisted,
                        'images' => [],
                    ])->save();
                    $piece->categories()->sync($original->categories()->pluck('categories.id'));
                    $record->update(['created_product_id' => $piece->id]);

                    $this->redirect(ProductResource::getUrl('edit', ['record' => $piece]));
                }),
            Action::make('changeStatus')
                ->label('Durumu değiştir')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->fillForm(fn (SimilarRequest $record) => ['status' => $record->status->value])
                ->schema([Select::make('status')->label('Durum')->options(RequestStatus::class)->required()])
                ->action(function (SimilarRequest $record, array $data) {
                    $record->update(['status' => $data['status']]);
                    Notification::make()->success()->title('Durum güncellendi')->send();
                }),
            Action::make('note')
                ->label('Not')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->fillForm(fn (SimilarRequest $record) => ['admin_note' => $record->admin_note])
                ->schema([Textarea::make('admin_note')->label('Sadece sizin gördüğünüz not')->rows(4)])
                ->action(function (SimilarRequest $record, array $data) {
                    $record->update(['admin_note' => $data['admin_note']]);
                    Notification::make()->success()->title('Not kaydedildi')->send();
                }),
        ];
    }
}
