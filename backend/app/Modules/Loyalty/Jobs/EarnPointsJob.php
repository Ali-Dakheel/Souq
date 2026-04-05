<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Jobs;

use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EarnPointsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function uniqueId(): string
    {
        return 'earn-points-order-'.$this->order->id;
    }

    public function handle(LoyaltyService $loyaltyService): void
    {
        if ($this->order->user === null) {
            return;
        }

        $loyaltyService->earnPoints(
            user: $this->order->user,
            amountFils: $this->order->subtotal_fils,
            referenceType: 'order',
            referenceId: $this->order->id,
        );
    }
}
