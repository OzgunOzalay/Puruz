<?php

namespace App\Providers\Filament;

use App\Filament\Support\InitialsAvatarProvider;
use App\Filament\Support\NavigationGroup;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('yonetim')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->brandName('Pürüz Atölye')
            ->brandLogo(asset('images/brand/logo.svg'))
            ->darkModeBrandLogo(asset('images/brand/logo-light.svg'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::hex('#2d4ba0'),
                'danger' => Color::hex('#d9402b'),
                'gray' => Color::Stone,
            ])
            ->font('DM Sans', provider: LocalFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->navigationGroups(NavigationGroup::class)
            ->navigationItems([
                NavigationItem::make('Mağazayı aç')
                    ->url(fn () => route('shop.home'), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->group(NavigationGroup::System)
                    ->sort(99),
            ])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->databaseNotifications(false)
            ->sidebarCollapsibleOnDesktop()
            // Actions dispatch "open-url" to open a link (e.g. WhatsApp) in a new tab after saving.
            ->renderHook(PanelsRenderHook::BODY_END, fn () => new HtmlString(
                '<script>window.addEventListener("open-url", e => window.open(e.detail.url, "_blank", "noopener"))</script>'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
