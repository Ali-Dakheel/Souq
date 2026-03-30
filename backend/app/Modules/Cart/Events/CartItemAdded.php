<?php

declare(strict_types=1);

namespace App\Modules\Cart\Events;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartItemAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Cart $cart,
        public readonly CartItem $cartItem,
    ) {}
}
