<?php

declare(strict_types=1);

namespace App\Modules\Loyalty;

use App\Modules\Loyalty\Listeners\EarnPointsOnPaymentCaptured;
use App\Modules\Payments\Events\PaymentCaptured;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LoyaltyServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(PaymentCaptured::class, [EarnPointsOnPaymentCaptured::class, 'handle']);
    }
}
