<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Listeners;

use App\Modules\Loyalty\Jobs\EarnPointsJob;
use App\Modules\Payments\Events\PaymentCaptured;

class EarnPointsOnPaymentCaptured
{
    public function handle(PaymentCaptured $event): void
    {
        EarnPointsJob::dispatch($event->order);
    }
}
