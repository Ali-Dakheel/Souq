<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors(['primary' => Color::Amber])
            ->discoverResources(
                in: app_path('Modules/Orders/Filament/Resources'),
                for: 'App\\Modules\\Orders\\Filament\\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Catalog/Filament/Resources'),
                for: 'App\\Modules\\Catalog\\Filament\\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Payments/Filament/Resources'),
                for: 'App\\Modules\\Payments\\Filament\\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Customers/Filament/Resources'),
                for: 'App\\Modules\\Customers\\Filament\\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Cart/Filament/Resources'),
                for: 'App\\Modules\\Cart\\Filament\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages',
            )
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets',
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
