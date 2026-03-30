<?php

declare(strict_types=1);

namespace App\Modules\Cart;

use App\Modules\Cart\Console\PruneExpiredCartsCommand;
use App\Modules\Cart\Events\CartAbandoned;
use App\Modules\Cart\Events\CartItemAdded;
use App\Modules\Cart\Events\CartItemRemoved;
use App\Modules\Cart\Events\CartMerged;
use App\Modules\Cart\Events\CouponApplied;
use App\Modules\Cart\Events\CouponRemoved;
use App\Modules\Cart\Listeners\ClearCartOnPaymentCaptured;
use App\Modules\Cart\Listeners\LogCartActivity;
use App\Modules\Cart\Listeners\RecordCouponUsageOnOrderPlaced;
use App\Modules\Cart\Listeners\ReleaseCouponUsageOnPaymentFailed;
use App\Modules\Cart\Listeners\UpdateCartOnMerge;
use App\Modules\Cart\Services\CartService;
use App\Modules\Cart\Services\CouponService;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Payments\Events\PaymentCaptured;
use App\Modules\Payments\Events\PaymentFailed;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CouponService::class);
        $this->app->singleton(CartService::class);

        $this->mergeConfigFrom(__DIR__.'/../../../config/cart.php', 'cart');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        $logger = LogCartActivity::class;

        Event::listen(CartItemAdded::class, [$logger, 'handleItemAdded']);
        Event::listen(CartItemRemoved::class, [$logger, 'handleItemRemoved']);
        Event::listen(CouponApplied::class, [$logger, 'handleCouponApplied']);
        Event::listen(CouponRemoved::class, [$logger, 'handleCouponRemoved']);
        Event::listen(CartMerged::class, [$logger, 'handleCartMerged']);
        Event::listen(CartAbandoned::class, [$logger, 'handleCartAbandoned']);
        Event::listen(CartMerged::class, [UpdateCartOnMerge::class, 'handle']);

        Event::listen(OrderPlaced::class,    [RecordCouponUsageOnOrderPlaced::class, 'handle']);
        Event::listen(PaymentFailed::class,  [ReleaseCouponUsageOnPaymentFailed::class, 'handle']);
        Event::listen(PaymentCaptured::class, [ClearCartOnPaymentCaptured::class, 'handle']);

        if ($this->app->runningInConsole()) {
            $this->commands([PruneExpiredCartsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('cart:prune-expired')->daily()->at('02:00');
        });
    }
}
