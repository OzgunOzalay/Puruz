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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Tabs::make('product')
                    ->columnSpan(2)
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Genel')->icon('heroicon-o-pencil-square')->schema([
                            ToggleButtons::make('type')
                                ->label('Tür')
                                ->options(ProductType::class)
                                ->default(ProductType::Physical)
                                ->inline()
                                ->live()
                                ->required(),
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

                        Tab::make('Görseller')->icon('heroicon-o-photo')->schema([
                            FileUpload::make('images')
                                ->label('Ürün görselleri')
                                ->helperText('Sürükleyerek sıralayın; ilk görsel kapak olur. Instagram için 4:5 dikey fotoğraflar idealdir.')
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

                        Tab::make('Fiyat & stok')->icon('heroicon-o-banknotes')->schema([
                            Grid::make(2)->schema([
                                MoneyInput::make('price')
                                    ->label(fn (Get $get) => self::isWorkshop($get) ? 'Kişi başı fiyat (KDV dahil)' : 'Fiyat (KDV dahil)')
                                    ->required(),
                                MoneyInput::make('compare_at_price')
                                    ->label('İndirim öncesi fiyat')
                                    ->helperText('Doluysa ürün “indirimde” görünür ve bu fiyat üstü çizili gösterilir.'),
                                TextInput::make('sku')
                                    ->label('Stok kodu (SKU)')
                                    ->unique(Product::class, 'sku', ignoreRecord: true),
                            ]),
                            Grid::make(2)->hidden(fn (Get $get) => self::isWorkshop($get))->schema([
                                Toggle::make('track_stock')
                                    ->label('Stok takibi yap')
                                    ->default(true)
                                    ->live(),
                                Toggle::make('made_to_order')
                                    ->label('Siparişe özel üretilir')
                                    ->helperText('Stok sınırı olmadan sipariş alınır.')
                                    ->live(),
                                TextInput::make('stock')
                                    ->label('Stok adedi')
                                    ->numeric()
                                    ->integer()
                                    ->default(0)
                                    ->helperText('Seçenekli ürünlerde stok, seçeneklerde ayrı ayrı tutulur.')
                                    ->visible(fn (Get $get) => $get('track_stock') && ! $get('made_to_order')),
                                TextInput::make('lead_time_days')
                                    ->label('Hazırlık süresi (gün)')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->visible(fn (Get $get) => (bool) $get('made_to_order')),
                            ]),
                        ]),

                        Tab::make('Detaylar')->icon('heroicon-o-list-bullet')->schema([
                            Grid::make(2)->schema([
                                TextInput::make('dimensions')->label(fn (Get $get) => self::isWorkshop($get) ? 'Süre / kapsam' : 'Ölçüler')->maxLength(190),
                                TextInput::make('materials')->label(fn (Get $get) => self::isWorkshop($get) ? 'Dahil olanlar' : 'Malzeme')->maxLength(190),
                                TextInput::make('weight_grams')->label('Ağırlık (gram)')->numeric()->integer()
                                    ->hidden(fn (Get $get) => self::isWorkshop($get)),
                            ]),
                            Textarea::make('care')->label('Bakım bilgisi')->rows(3)
                                ->hidden(fn (Get $get) => self::isWorkshop($get)),
                        ]),

                        Tab::make('SEO & paylaşım')->icon('heroicon-o-share')->schema([
                            TextInput::make('seo_title')->label('Sayfa başlığı')->maxLength(70)
                                ->helperText('Boş bırakılırsa ürün adı kullanılır.'),
                            Textarea::make('seo_description')->label('Açıklama (Google & link önizlemesi)')->rows(2)->maxLength(300),
                            FileUpload::make('og_image')
                                ->label('Paylaşım görseli')
                                ->helperText('WhatsApp/Instagram DM önizlemesi için. Boşsa kapak görseli kullanılır.')
                                ->image()
                                ->disk('public')
                                ->directory('products/og'),
                            TextInput::make('instagram_url')->label('İlgili Instagram gönderisi')->url()->maxLength(255),
                        ]),
                    ]),

                Grid::make(1)->columnSpan(1)->schema([
                    Section::make('Yayın')->schema([
                        Select::make('status')
                            ->label('Durum')
                            ->options(ProductStatus::class)
                            ->default(ProductStatus::Draft)
                            ->required()
                            ->native(false),
                        Radio::make('visibility')
                            ->label('Görünürlük')
                            ->options(ProductVisibility::class)
                            ->default(ProductVisibility::Public)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label('Yayın tarihi')
                            ->helperText('İleri bir tarih seçerseniz ürün o zaman görünür olur.')
                            ->native(false)
                            ->seconds(false),
                        Toggle::make('is_featured')->label('Öne çıkar (ana sayfa & link sayfası)'),
                        TextInput::make('sort_order')->label('Sıralama')->numeric()->integer()->default(0)
                            ->helperText('Küçük sayı önce gösterilir.'),
                    ]),
                    Section::make('Kategoriler')->schema([
                        Select::make('categories')
                            ->hiddenLabel()
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),
                ]),
            ]);
    }

    private static function isWorkshop(Get $get): bool
    {
        $type = $get('type');

        return ($type instanceof ProductType ? $type : ProductType::tryFrom((string) $type)) === ProductType::Workshop;
    }
}
