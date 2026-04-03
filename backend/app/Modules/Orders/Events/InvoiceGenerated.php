<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Invoice;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Order $order,
    ) {}
}
