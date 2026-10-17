<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Filament\Support\MoneyInput;
use App\Models\Product;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)->columnSpan(2)->schema([
                    Section::make()->schema([
                        ToggleButtons::make('type')
                            ->label('Tür')
                            // After creation the type is fixed, so only the record's own type is shown.
                            ->options(fn (string $operation, ?Product $record) => collect($operation === 'create' || ! $record ? ProductType::creatable() : [$record->type])
                                ->mapWithKeys(fn (ProductType $type) => [$type->value => $type->getLabel()])->all())
                            ->default(ProductType::Unique->value)
                            ->inline()
                            ->live()
                            ->disabledOn('edit')
                            ->required(),
                    ]),

                    Section::make('Fotoğraflar')->schema([
                        FileUpload::make('images')
                            ->hiddenLabel()
                            ->helperText('Sürükleyerek sıralayın; ilk fotoğraf kapak olur. Instagram için 4:5 dikey fotoğraflar idealdir.')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->panelLayout('grid')
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['4:5', '1:1', null])
                            ->disk('public')
                            ->directory('products')
                            ->maxSize(8192)
                            ->maxFiles(12),
                    ]),

                    Section::make('Ad & açıklama')->schema([
                        TextInput::make('name')
                            ->label('Ad')
                            ->required()
                            ->maxLength(190)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation) {
                                if ($operation === 'create' || blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Bağlantı (slug)')
                            ->prefix(fn () => url('/urun').'/')
                            ->required()
                            ->alphaDash()
                            ->unique(Product::class, 'slug', ignoreRecord: true)
                            ->helperText('Instagram’da paylaştığınız link bu kısmı içerir; yayına aldıktan sonra değiştirmeyin.'),
                        Textarea::make('short_description')
                            ->label('Kısa açıklama')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('Ürün sayfasında fiyatın altında ve link önizlemelerinde görünür.'),
                        RichEditor::make('description')
                            ->label('Açıklama')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h3', 'undo', 'redo']),
                    ]),

                    Section::make('Detaylar')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('dimensions')->label(fn (Get $get) => self::isWorkshop($get) ? 'Süre / kapsam' : 'Ölçüler')->maxLength(190),
                            TextInput::make('materials')->label(fn (Get $get) => self::isWorkshop($get) ? 'Dahil olanlar' : 'Malzeme')->maxLength(190),
                            TextInput::make('weight_grams')->label('Ağırlık (gram)')->numeric()->integer()
                                ->hidden(fn (Get $get) => self::isWorkshop($get)),
                        ]),
                        Textarea::make('care')->label('Bakım bilgisi')->rows(3)
                            ->hidden(fn (Get $get) => self::isWorkshop($get)),
                    ]),

                    // Only for mass-produced products ("Seri üretim").
                    Section::make('Stok')->visible(fn (Get $get) => self::isPhysical($get))->schema([
                        Grid::make(2)->schema([
                            Toggle::make('track_stock')->label('Stok takibi yap')->default(true)->live(),
                            Toggle::make('made_to_order')->label('Siparişe özel üretilir')->helperText('Stok sınırı olmadan sipariş alınır.')->live(),
                            TextInput::make('stock')->label('Stok adedi')->numeric()->integer()->default(0)
                                ->helperText('Seçenekli ürünlerde stok, seçeneklerde ayrı ayrı tutulur.')
                                ->visible(fn (Get $get) => $get('track_stock') && ! $get('made_to_order')),
                            TextInput::make('lead_time_days')->label('Hazırlık süresi (gün)')->numeric()->integer()->minValue(1)
                                ->visible(fn (Get $get) => (bool) $get('made_to_order')),
                        ]),
                    ]),

                    Section::make('SEO & paylaşım')->collapsed()->schema([
                        TextInput::make('seo_title')->label('Sayfa başlığı')->maxLength(70)->helperText('Boş bırakılırsa ürün adı kullanılır.'),
                        Textarea::make('seo_description')->label('Açıklama (Google & link önizlemesi)')->rows(2)->maxLength(300),
                        FileUpload::make('og_image')
                            ->label('Paylaşım görseli')
                            ->helperText('WhatsApp/Instagram DM önizlemesi için. Boşsa kapak fotoğrafı kullanılır.')
                            ->image()
                            ->disk('public')
                            ->directory('products/og'),
                        TextInput::make('instagram_url')->label('İlgili Instagram gönderisi')->url()->maxLength(255),
                        TextInput::make('sort_order')->label('Mağazadaki sıra')->numeric()->integer()->default(0)->helperText('Küçük sayı önce gösterilir.'),
                    ]),
                ]),

                Grid::make(1)->columnSpan(1)->schema([
                    Section::make('Durum')->visibleOn('edit')->schema([
                        TextEntry::make('piece_state')
                            ->hiddenLabel()
                            ->state(fn (?Product $record) => $record ? new HtmlString(view('filament.product-state', ['product' => $record])->render()) : null)
                            ->html(),
                    ]),
                    Section::make('Fiyat')->schema([
                        MoneyInput::make('price')
                            ->label(fn (Get $get) => self::isWorkshop($get) ? 'Kişi başı fiyat (KDV dahil)' : 'Fiyat (KDV dahil)')
                            ->helperText(fn (?Product $record) => $record?->sold_at ? 'Bu parça satıldı; fiyatı değiştirmek mevcut siparişi etkilemez.' : null)
                            ->required(),
                        MoneyInput::make('compare_at_price')
                            ->label('İndirim öncesi fiyat')
                            ->helperText('Doluysa “indirimde” görünür ve bu fiyat üstü çizili gösterilir.'),
                    ]),
                    Section::make('Yayın')->schema([
                        Select::make('status')->label('Durum')->options(ProductStatus::class)->default(ProductStatus::Draft)->required()->native(false),
                        Radio::make('visibility')->label('Görünürlük')->options(ProductVisibility::class)->default(ProductVisibility::Public)->required(),
                        DateTimePicker::make('published_at')->label('Yayın tarihi')
                            ->helperText('İleri bir tarih seçerseniz o zaman görünür olur.')->native(false)->seconds(false),
                        Toggle::make('is_featured')->label('Öne çıkar (ana sayfa & link sayfası)'),
                    ]),
                    Section::make('Kategoriler')->schema([
                        Select::make('categories')->hiddenLabel()->relationship('categories', 'name')->multiple()->preload()->searchable(),
                    ]),
                    Section::make('Parça kodu')->hidden(fn (Get $get) => self::isWorkshop($get))->schema([
                        TextInput::make('sku')->hiddenLabel()->placeholder('Boş bırakırsanız otomatik verilir')
                            ->unique(Product::class, 'sku', ignoreRecord: true),
                    ]),
                ]),
            ]);
    }

    private static function type(Get $get): ?ProductType
    {
        $type = $get('type');

        return $type instanceof ProductType ? $type : ProductType::tryFrom((string) $type);
    }

    private static function isWorkshop(Get $get): bool
    {
        return self::type($get) === ProductType::Workshop;
    }

    private static function isPhysical(Get $get): bool
    {
        return self::type($get) === ProductType::Physical;
    }
}
