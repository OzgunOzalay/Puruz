<?php

namespace App\Filament\Resources\SimilarRequests;

use App\Enums\RequestStatus;
use App\Filament\Resources\SimilarRequests\Pages\ListSimilarRequests;
use App\Filament\Resources\SimilarRequests\Pages\ViewSimilarRequest;
use App\Filament\Support\NavigationGroup;
use App\Models\SimilarRequest;
use App\Support\Human;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class SimilarRequestResource extends Resource
{
    protected static ?string $model = SimilarRequest::class;

    protected static ?string $slug = 'talepler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Orders;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'talep';

    protected static ?string $pluralModelLabel = 'talepler';

    protected static ?string $navigationLabel = 'Talepler';

    public static function getNavigationBadge(): ?string
    {
        $count = SimilarRequest::query()->where('status', RequestStatus::New)->count();

        return $count ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function statusHtml(SimilarRequest $record): HtmlString
    {
        $dot = match ($record->status->getColor()) {
            'warning' => 'bg-warning-500',
            'success' => 'bg-success-500',
            default => 'bg-gray-400',
        };

        return new HtmlString('<span class="inline-flex items-center gap-2"><span class="size-2 shrink-0 rounded-full '.$dot.'"></span>'.e($record->status->getLabel()).'</span>');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('product'))
            ->columns([
                ImageColumn::make('cover')->label('')
                    ->state(fn (SimilarRequest $record) => $record->product?->coverImage())
                    ->disk('public')->imageSize(48)->square(),
                TextColumn::make('product.name')->label('Parça')
                    ->formatStateUsing(fn (SimilarRequest $record) => $record->product?->name.' gibi')
                    ->description(fn (SimilarRequest $record) => $record->displayContact()),
                TextColumn::make('message')->label('Talep')
                    ->formatStateUsing(fn (string $state) => Str::limit(Str::before($state, "\n") ?: $state, 70))
                    ->searchable(),
                TextColumn::make('status')->label('Durum')
                    ->formatStateUsing(fn (SimilarRequest $record) => self::statusHtml($record))->html(),
                TextColumn::make('created_at')->label('Geldi')->sortable()
                    ->formatStateUsing(fn (SimilarRequest $record) => Human::date($record->created_at)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSimilarRequests::route('/'),
            'view' => ViewSimilarRequest::route('/{record}'),
        ];
    }
}
