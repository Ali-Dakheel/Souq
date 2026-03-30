<?php

declare(strict_types=1);

namespace App\Modules\Cart\Listeners;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
use App\Modules\Payments\Events\PaymentCaptured;

class ClearCartOnPaymentCaptured
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function handle(PaymentCaptured $event): void
    {
        $order = $event->order;

        // Only handle authenticated user carts; guest carts expire via TTL
        if ($order->user_id === null) {
            return;
        }

        $cart = Cart::where('user_id', $order->user_id)->first();

        if ($cart === null) {
            return;
        }

        $this->cartService->clearCart($cart);
    }
}
