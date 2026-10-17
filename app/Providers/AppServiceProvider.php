<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Observers\ProductObserver;
use App\Services\Cart\CartService;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\IyzicoGateway;
use App\Services\Payment\PaymentGateway;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function () {
            if (config('services.payment_gateway') === 'fake') {
                return new FakeGateway;
            }

            return new IyzicoGateway(
                (string) config('services.iyzico.api_key'),
                (string) config('services.iyzico.secret_key'),
                (string) config('services.iyzico.base_url'),
            );
        });

        $this->app->scoped(CartService::class, fn ($app) => new CartService($app['session.store']));
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Product::observe(ProductObserver::class);

        RateLimiter::for('order-lookup', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('similar-request', fn (Request $request) => Limit::perHour(5)->by($request->ip()));

        View::composer(['shop.*', 'orders.*', 'errors.*'], fn ($view) => $view->with('settings', Settings::all()));

        View::composer('layouts.shop', function ($view) {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $view->with([
                'settings' => Settings::all(),
                'cartCount' => app(CartService::class)->count(),
                'navCategories' => Category::query()->visible()->whereNull('parent_id')->ordered()->get(),
                'footerPages' => Page::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            ]);
        });
    }
}
