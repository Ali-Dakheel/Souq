<?php

declare(strict_types=1);

namespace App\Modules\Cart\Events;

use App\Modules\Cart\Models\Cart;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartItemRemoved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Cart $cart,
        public readonly int $variantId,
        public readonly int $quantity,
    ) {}
}
