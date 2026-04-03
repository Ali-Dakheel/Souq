<?php

declare(strict_types=1);

namespace App\Modules\Orders\Listeners;

use App\Modules\Orders\Jobs\GenerateInvoiceJob;
use App\Modules\Payments\Events\PaymentCaptured;

class GenerateInvoiceOnPaymentCaptured
{
    public function handle(PaymentCaptured $event): void
    {
        GenerateInvoiceJob::dispatch($event->order->id);
    }
}
