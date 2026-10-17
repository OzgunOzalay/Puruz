<?php

namespace App\Filament\Pages;

use App\Filament\Support\MoneyInput;
use App\Filament\Support\NavigationGroup;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ShopSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?string $navigationLabel = 'Mağaza ayarları';

    protected static ?string $title = 'Mağaza ayarları';

    protected static ?string $slug = 'ayarlar';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(Settings::all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('settings')->persistTabInQueryString()->tabs([
                    Tab::make('Mağaza')->icon('heroicon-o-building-storefront')->schema([
                        Section::make('Genel')->columns(2)->schema([
                            TextInput::make('shop_name')->label('Mağaza adı')->required(),
                            TextInput::make('tagline')->label('Slogan'),
                        ]),
                        Section::make('Şirket bilgileri')
                            ->description('Sözleşmelerde, faturada ve site altbilgisinde görünür. iyzico başvurusu için gereklidir.')
                            ->columns(2)
                            ->schema([
                                TextInput::make('legal_name')->label('Ticari unvan / ad soyad'),
                                Textarea::make('address')->label('Adres')->rows(2),
                                TextInput::make('tax_office')->label('Vergi dairesi'),
                                TextInput::make('tax_number')->label('Vergi / T.C. kimlik no'),
                                TextInput::make('mersis_number')->label('MERSİS no'),
                            ]),
                    ]),
                    Tab::make('İletişim')->icon('heroicon-o-chat-bubble-left-right')->schema([
                        Section::make()->columns(2)->schema([
                            TextInput::make('contact_phone')->label('Telefon')->tel(),
                            TextInput::make('whatsapp_number')->label('WhatsApp numarası')->helperText('Ülke koduyla, boşluksuz: 905302759994'),
                            TextInput::make('contact_email')->label('E-posta')->email(),
                            TextInput::make('instagram_handle')->label('Instagram kullanıcı adı')->prefix('@'),
                            TextInput::make('admin_notification_email')->label('Yeni sipariş bildirim e-postası')->email()
                                ->helperText('Yeni siparişler ve ödeme sorunları bu adrese bildirilir.'),
                        ]),
                    ]),
                    Tab::make('Ana sayfa')->icon('heroicon-o-home')->schema([
                        Section::make('Karşılama alanı')->schema([
                            TextInput::make('hero_title')->label('Başlık'),
                            Textarea::make('hero_text')->label('Metin')->rows(2),
                            FileUpload::make('hero_image')->label('Görsel')->image()->disk('public')->directory('site')->imageEditor()
                                ->helperText('Boşsa ilk atölyenin ya da öne çıkan ürünün görseli kullanılır.'),
                        ]),
                        Section::make('Duyuru şeridi')->columns(2)->schema([
                            TextInput::make('announcement')->label('Duyuru metni')->placeholder('ör. 1.500 ₺ üzeri kargo bedava!'),
                            TextInput::make('announcement_link')->label('Duyuru linki')->url(),
                        ]),
                    ]),
                    Tab::make('Kargo & ödeme')->icon('heroicon-o-truck')->schema([
                        Section::make('Kargo')->columns(2)->schema([
                            MoneyInput::make('shipping_fee')->label('Kargo ücreti')->required(),
                            MoneyInput::make('free_shipping_threshold')->label('Ücretsiz kargo limiti')->helperText('Boşsa her siparişte kargo ücreti alınır.'),
                            Textarea::make('shipping_note')->label('Kargo bilgisi (ürün sayfasında)')->rows(2)->columnSpanFull(),
                        ]),
                        Section::make('Ödeme')->columns(2)->schema([
                            Toggle::make('installments_enabled')->label('Taksit seçeneklerini aç')->live()
                                ->helperText('Taksit komisyonları iyzico sözleşmenize göre belirlenir.'),
                            Select::make('max_installment')->label('En fazla taksit')
                                ->options([2 => '2', 3 => '3', 6 => '6', 9 => '9', 12 => '12'])
                                ->visible(fn (Get $get) => (bool) $get('installments_enabled')),
                            TextInput::make('reservation_minutes')->label('Ödeme için stok ayırma süresi (dk)')->numeric()->integer()->minValue(10)->maxValue(120),
                            TextInput::make('low_stock_threshold')->label('Az stok uyarı eşiği')->numeric()->integer()->minValue(0),
                        ]),
                    ]),
                    Tab::make('Mağaza durumu')->icon('heroicon-o-pause-circle')->schema([
                        Section::make()->schema([
                            Toggle::make('store_closed')->label('Tatil modu: yeni siparişleri durdur')
                                ->helperText('Site açık kalır, ürünler görünür ama ödeme alınmaz.')->live(),
                            Textarea::make('store_closed_message')->label('Ziyaretçilere gösterilecek mesaj')->rows(2),
                        ]),
                    ]),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Kaydet')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        Settings::set($this->form->getState());
        cache()->forget('sitemap.xml');

        Notification::make()->success()->title('Ayarlar kaydedildi')->send();
    }
}
