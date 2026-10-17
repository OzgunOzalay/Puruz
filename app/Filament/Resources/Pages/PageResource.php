<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Support\NavigationGroup;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $slug = 'sayfalar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Content;

    protected static ?string $modelLabel = 'sayfa';

    protected static ?string $pluralModelLabel = 'sayfalar';

    /** Pages the checkout and contracts depend on; their address must not change. */
    public const PROTECTED_SLUGS = ['on-bilgilendirme-formu', 'mesafeli-satis-sozlesmesi', 'kvkk-aydinlatma-metni'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->schema([
                TextInput::make('title')->label('Başlık')->required()->maxLength(190),
                TextInput::make('slug')->label('Bağlantı')->prefix('/sayfa/')->required()->alphaDash()->unique(ignoreRecord: true)
                    ->disabled(fn (?Page $record) => $record && in_array($record->slug, self::PROTECTED_SLUGS, true))
                    ->helperText(fn (?Page $record) => $record && in_array($record->slug, self::PROTECTED_SLUGS, true) ? 'Ödeme sayfası bu metni kullanıyor, bağlantısı değiştirilemez.' : null),
                RichEditor::make('content')->label('İçerik'),
            ]),
            Section::make()->columnSpan(1)->schema([
                Toggle::make('is_published')->label('Yayında')->default(true),
                Toggle::make('show_in_footer')->label('Alt menüde göster')->default(true),
                TextInput::make('sort_order')->label('Sıralama')->numeric()->integer()->default(0),
                Textarea::make('seo_description')->label('SEO açıklaması')->rows(3)->maxLength(300),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->label('Başlık')->searchable()->description(fn (Page $record) => '/sayfa/'.$record->slug),
                ToggleColumn::make('is_published')->label('Yayında'),
                ToggleColumn::make('show_in_footer')->label('Alt menü'),
                TextColumn::make('updated_at')->label('Güncellendi')->since(),
            ])
            ->recordActions([
                Action::make('open')->label('Aç')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Page $record) => $record->url(), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Page $record) => in_array($record->slug, self::PROTECTED_SLUGS, true)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
