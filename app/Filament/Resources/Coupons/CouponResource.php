<?php

namespace App\Filament\Resources\Coupons;

use App\Enums\CouponType;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\NavigationGroup;
use App\Models\Coupon;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $slug = 'kuponlar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Marketing;

    protected static ?string $modelLabel = 'kupon';

    protected static ?string $pluralModelLabel = 'kuponlar';

    public static function form(Schema $schema): Schema
    {
        $isPercent = fn (Get $get) => in_array($get('type'), [CouponType::Percent, CouponType::Percent->value], true);

        return $schema->columns(2)->components([
            TextInput::make('code')->label('Kod')->required()->maxLength(50)->unique(ignoreRecord: true)
                ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                ->helperText('Instagram’da paylaşacağınız kod, ör. HOSGELDIN10'),
            TextInput::make('description')->label('Açıklama (iç kullanım)'),
            ToggleButtons::make('type')->label('İndirim türü')->options(CouponType::class)->default(CouponType::Percent)->inline()->live()->required(),
            // One field for both types: a percentage, or an amount in lira that is stored as kuruş.
            TextInput::make('value')
                ->label(fn (Get $get) => $isPercent($get) ? 'İndirim yüzdesi' : 'İndirim tutarı')
                ->suffix(fn (Get $get) => $isPercent($get) ? '%' : '₺')
                ->required()
                ->rule('regex:/^(\d{1,3}(\.\d{3})+|\d{1,7})([.,]\d{1,2})?$/')
                ->formatStateUsing(fn ($state, Get $get) => $state === null ? null : ($isPercent($get) ? $state : rtrim(rtrim(number_format($state / 100, 2, ',', ''), '0'), ',')))
                ->dehydrateStateUsing(fn ($state, Get $get) => $isPercent($get) ? min(100, (int) $state) : Money::parse($state)),
            MoneyInput::make('min_total')->label('En düşük sepet tutarı'),
            TextInput::make('usage_limit')->label('Toplam kullanım limiti')->numeric()->integer()->minValue(1)->helperText('Boşsa sınırsız.'),
            DateTimePicker::make('starts_at')->label('Başlangıç')->native(false)->seconds(false),
            DateTimePicker::make('ends_at')->label('Bitiş')->native(false)->seconds(false)->after('starts_at'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Kod')->searchable()->copyable()->weight('medium')->description(fn (Coupon $record) => $record->description),
                TextColumn::make('value')->label('İndirim')
                    ->formatStateUsing(fn (Coupon $record) => $record->type === CouponType::Percent ? '%'.$record->value : Money::format($record->value)),
                TextColumn::make('used_count')->label('Kullanım')
                    ->formatStateUsing(fn (Coupon $record) => $record->used_count.($record->usage_limit ? ' / '.$record->usage_limit : '')),
                TextColumn::make('ends_at')->label('Bitiş')->dateTime('d.m.Y H:i')->placeholder('süresiz'),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCoupons::route('/')];
    }
}
