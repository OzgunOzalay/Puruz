<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\NavigationGroup;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'kullanicilar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?string $modelLabel = 'kullanıcı';

    protected static ?string $pluralModelLabel = 'panel kullanıcıları';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Ad')->required()->maxLength(120),
            TextInput::make('email')->label('E-posta')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')->label('Rol')->options(UserRole::class)->default(UserRole::Staff)->required()
                ->helperText('Personel ürün ve siparişleri yönetebilir; iade, ayarlar ve kullanıcılar yalnızca yöneticidedir.'),
            TextInput::make('password')->label('Şifre')->password()->revealable()
                ->rule(Password::min(10))
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Değiştirmek istemiyorsanız boş bırakın.' : null),
            Toggle::make('is_active')->label('Panel erişimi açık')->default(true)
                ->disabled(fn (?User $record) => $record?->is(auth()->user())),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ad')->searchable(),
                TextColumn::make('email')->label('E-posta')->searchable(),
                TextColumn::make('role')->label('Rol')->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('created_at')->label('Eklendi')->date('d.m.Y'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (User $record) => $record->is(auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
