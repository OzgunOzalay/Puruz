<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Filament\Support\NavigationGroup;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'siparisler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Orders;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'sipariş';

    protected static ?string $pluralModelLabel = 'siparişler';

    protected static ?string $navigationLabel = 'Siparişler';

    protected static ?string $recordTitleAttribute = 'number';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    /** Orders waiting to be packed, shown as a badge next to the menu item. */
    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'customer_name', 'customer_email', 'customer_phone'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
