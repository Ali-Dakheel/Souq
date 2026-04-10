<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Mail\PaymentReceiptMail;
use App\Modules\Payments\Events\PaymentCaptured;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendPaymentReceiptEmail implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(PaymentCaptured $event): void
    {
        $transaction = $event->order->payments()->first();
        if ($transaction) {
            Mail::queue(new PaymentReceiptMail($event->order, $transaction));
        }
    }
}
