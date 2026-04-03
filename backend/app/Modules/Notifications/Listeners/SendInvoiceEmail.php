<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Mail\InvoiceMail;
use App\Modules\Orders\Events\InvoiceGenerated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendInvoiceEmail implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(InvoiceGenerated $event): void
    {
        Mail::send(new InvoiceMail($event->invoice, $event->order));
    }
}
