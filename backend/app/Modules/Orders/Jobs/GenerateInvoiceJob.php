<?php

declare(strict_types=1);

namespace App\Modules\Orders\Jobs;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [5, 30, 120];

    public function __construct(public readonly int $orderId) {}

    public function uniqueId(): string
    {
        return "generate_invoice_{$this->orderId}";
    }

    public function handle(InvoiceService $invoiceService): void
    {
        $order = Order::with('items.variant.product')->findOrFail($this->orderId);
        $invoiceService->generateInvoice($order);
    }
}
