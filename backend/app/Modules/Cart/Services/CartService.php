<?php

declare(strict_types=1);

namespace App\Modules\Cart\Services;

use App\Models\User;
use App\Modules\Cart\Events\CartAbandoned;
use App\Modules\Cart\Events\CartItemAdded;
use App\Modules\Cart\Events\CartItemRemoved;
use App\Modules\Cart\Events\CartMerged;
use App\Modules\Cart\Events\CouponApplied;
use App\Modules\Cart\Events\CouponRemoved;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartAbandonment;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Customers\Services\CustomerGroupService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(
        private readonly CouponService $couponService,
        private readonly CustomerGroupService $customerGroupService,
    ) {}

    /**
     * Get or create the cart for an authenticated user or guest session.
     */
    public function getOrCreateCart(?int $userId, ?string $sessionId): Cart
    {
        if ($userId !== null) {
            return Cart::firstOrCreate(
                ['user_id' => $userId],
                ['expires_at' => null],
            );
        }

        if ($sessionId !== null) {
            return Cart::firstOrCreate(
                ['session_id' => $sessionId, 'user_id' => null],
                ['expires_at' => now()->addDays(config('cart.guest_ttl_days', 30))],
            );
        }

        // Fallback: ephemeral cart (no session provided)
        return Cart::create([
            'expires_at' => now()->addDays(config('cart.guest_ttl_days', 30)),
        ]);
    }

    /**
     * Add or increment a variant in the cart.
     *
     * @throws ValidationException
     */
    public function addItem(Cart $cart, int $variantId, int $quantity): CartItem
    {
        $variant = Variant::with(['inventory', 'product'])->find($variantId);

        if (! $variant || ! $variant->is_available) {
            throw ValidationException::withMessages(['variant_id' => ['This product is not available.']]);
        }

        $stock = $variant->inventory?->quantity_available ?? 0;
        $maxQty = config('cart.max_quantity_per_item', 10);

        $existing = CartItem::where('cart_id', $cart->id)
            ->where('variant_id', $variantId)
            ->first();

        $newQuantity = $quantity + ($existing?->quantity ?? 0);
        $this->assertQuantity($newQuantity, $stock, $maxQty);

        if ($existing) {
            $existing->update(['quantity' => $newQuantity]);
            $cartItem = $existing->fresh();
        } else {
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'variant_id' => $variantId,
                'quantity' => $newQuantity,
                'price_fils_snapshot' => $this->customerGroupService->getGroupPriceForUser(Auth::user(), $variant),
            ]);
        }

        $cartItem->setRelation('variant', $variant);

        CartItemAdded::dispatch($cart, $cartItem);

        return $cartItem;
    }

    /**
     * Update the quantity of an existing cart item.
     *
     * @throws ValidationException
     */
    public function updateItemQuantity(CartItem $item, int $quantity): CartItem
    {
        $item->loadMissing('variant.inventory');

        $stock = $item->variant?->inventory?->quantity_available ?? 0;
        $maxQty = config('cart.max_quantity_per_item', 10);

        $this->assertQuantity($quantity, $stock, $maxQty);

        $item->update(['quantity' => $quantity]);

        return $item->fresh(['variant.product']);
    }

    /**
     * Remove an item from the cart entirely.
     */
    public function removeItem(CartItem $item): void
    {
        $cart = $item->cart;
        $variantId = $item->variant_id ?? 0;
        $quantity = $item->quantity;

        $item->delete();

        CartItemRemoved::dispatch($cart, $variantId, $quantity);
    }

    /**
     * Apply a coupon code to the cart.
     *
     * @return array{coupon: Coupon, discount_fils: int}
     *
     * @throws ValidationException
     */
    public function applyCoupon(Cart $cart, string $code, ?int $userId = null): array
    {
        $cart->loadMissing('items.variant.product');
        $totals = $this->calculateTotals($cart);

        $cartData = $this->buildCouponCartData($cart, $totals['subtotal_fils']);
        $coupon = $this->couponService->validate($code, $cartData, $userId);

        $items = $this->buildItemsForDiscount($cart);
        $discountFils = $this->couponService->calculateDiscount($coupon, $totals['subtotal_fils'], $items);

        $cart->update(['coupon_code' => $coupon->code]);

        CouponApplied::dispatch($cart, $coupon, $discountFils);

        return ['coupon' => $coupon, 'discount_fils' => $discountFils];
    }

    /**
     * Remove the coupon from the cart.
     */
    public function removeCoupon(Cart $cart): void
    {
        $removedCode = $cart->coupon_code ?? '';
        $cart->update(['coupon_code' => null]);

        if ($removedCode) {
            CouponRemoved::dispatch($cart, $removedCode);
        }
    }

    /**
     * Merge a guest cart into an authenticated user's cart.
     * Sums quantities, respects stock and max-quantity limits.
     *
     * @return array{items_added: int, items_updated: int, items_removed_due_to_limits: int}
     */
    public function mergeCart(Cart $guestCart, Cart $userCart, User $user): array
    {
        $stats = ['items_added' => 0, 'items_updated' => 0, 'items_removed_due_to_limits' => 0];

        $guestCart->loadMissing('items.variant.inventory');
        $maxQty = config('cart.max_quantity_per_item', 10);

        DB::transaction(function () use ($guestCart, $userCart, $maxQty, &$stats) {
            foreach ($guestCart->items as $guestItem) {
                if ($guestItem->variant_id === null) {
                    continue; // variant was deleted
                }

                $stock = $guestItem->variant?->inventory?->quantity_available ?? 0;
                $cap = min($stock, $maxQty);

                if ($cap <= 0) {
                    $stats['items_removed_due_to_limits']++;

                    continue;
                }

                $userItem = CartItem::where('cart_id', $userCart->id)
                    ->where('variant_id', $guestItem->variant_id)
                    ->first();

                if ($userItem) {
                    $merged = min($userItem->quantity + $guestItem->quantity, $cap);
                    if ($merged > $userItem->quantity) {
                        $userItem->update(['quantity' => $merged]);
                        $stats['items_updated']++;
                    }
                } else {
                    $qty = min($guestItem->quantity, $cap);
                    CartItem::create([
                        'cart_id' => $userCart->id,
                        'variant_id' => $guestItem->variant_id,
                        'quantity' => $qty,
                        'price_fils_snapshot' => $guestItem->price_fils_snapshot,
                    ]);
                    $stats['items_added']++;
                }
            }

            // Carry over coupon if user cart has none
            if ($userCart->coupon_code === null && $guestCart->coupon_code !== null) {
                $userCart->update(['coupon_code' => $guestCart->coupon_code]);
            }

            $guestCart->items()->delete();
            $guestCart->delete();
        });

        CartMerged::dispatch(
            $user,
            $userCart->fresh(),
            $guestCart->session_id ?? '',
            $stats['items_added'],
            $stats['items_updated'],
        );

        return $stats;
    }

    /**
     * Calculate subtotal, discount, VAT (10%), and total — all in fils.
     *
     * @return array{subtotal_fils: int, discount_fils: int, vat_fils: int, total_fils: int}
     */
    public function calculateTotals(Cart $cart): array
    {
        $cart->loadMissing('items.variant.product');

        $subtotal = 0;
        foreach ($cart->items as $item) {
            $subtotal += $item->line_total_fils;
        }

        $discountFils = 0;
        if ($cart->coupon_code) {
            $coupon = $cart->coupon()->first();
            if ($coupon && $coupon->is_active && ! $coupon->isExpired() && ! $coupon->isNotYetActive() && ! $coupon->isExhausted()) {
                $items = $this->buildItemsForDiscount($cart);
                $discountFils = $this->couponService->calculateDiscount($coupon, $subtotal, $items);
            }
        }

        $taxable = $subtotal - $discountFils;
        $vatRate = config('cart.vat_rate', 0.10);
        $vatFils = (int) round($taxable * $vatRate);
        $total = $taxable + $vatFils;

        return [
            'subtotal_fils' => $subtotal,
            'discount_fils' => $discountFils,
            'vat_fils' => $vatFils,
            'total_fils' => $total,
        ];
    }

    /**
     * Return cart items whose current price differs from the stored snapshot.
     *
     * @return Collection<int, CartItem>
     */
    public function getItemsWithPriceChanges(Cart $cart)
    {
        $cart->loadMissing('items.variant');

        return $cart->items->filter(fn (CartItem $item) => $item->price_changed);
    }

    /**
     * Validate the cart is ready for checkout.
     * Does NOT reserve stock — that is the Orders module's responsibility.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateForCheckout(Cart $cart): array
    {
        $cart->loadMissing('items.variant.inventory');
        $errors = [];

        if ($cart->items->isEmpty()) {
            $errors[] = 'Your cart is empty.';
        }

        foreach ($cart->items as $item) {
            if ($item->variant_id === null || $item->variant === null) {
                $errors[] = 'One or more items are no longer available.';

                continue;
            }

            $stock = $item->variant->inventory?->quantity_available ?? 0;
            if ($stock < $item->quantity) {
                $name = $item->variant->product->name['en'] ?? 'An item';
                if ($stock === 0) {
                    $errors[] = "{$name} is out of stock.";
                } else {
                    $errors[] = "Only {$stock} of {$name} available.";
                }
            }
        }

        // Re-validate coupon if one is applied
        if ($cart->coupon_code) {
            $coupon = $cart->coupon()->first();
            if (! $coupon || ! $coupon->is_active || $coupon->isExpired() || $coupon->isNotYetActive() || $coupon->isExhausted()) {
                $errors[] = 'The applied coupon is no longer valid and has been removed.';
                $cart->update(['coupon_code' => null]);
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Remove all items from the cart.
     */
    public function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->update(['coupon_code' => null]);
    }

    /**
     * Record a cart abandonment and fire the CartAbandoned event (stub listener).
     */
    public function markAbandoned(Cart $cart): void
    {
        $cart->loadMissing('items');
        $totals = $this->calculateTotals($cart);

        $abandonment = CartAbandonment::firstOrCreate(
            ['cart_id' => $cart->id],
            [
                'user_id' => $cart->user_id,
                'session_id' => $cart->session_id,
                'cart_total_fils' => $totals['total_fils'],
                'item_count' => $cart->items->sum('quantity'),
                'abandoned_at' => now(),
            ],
        );

        CartAbandoned::dispatch($cart, $abandonment);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function assertQuantity(int $quantity, int $stock, int $maxQty): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => ['Quantity must be at least 1.']]);
        }

        if ($stock <= 0) {
            throw ValidationException::withMessages(['quantity' => ['This item is out of stock.']]);
        }

        if ($quantity > $stock) {
            throw ValidationException::withMessages(['quantity' => ["Only {$stock} available in stock."]]);
        }

        if ($quantity > $maxQty) {
            throw ValidationException::withMessages(['quantity' => ["Maximum {$maxQty} per item allowed."]]);
        }
    }

    /** @return array{subtotal_fils: int, category_ids: int[], product_ids: int[]} */
    private function buildCouponCartData(Cart $cart, int $subtotalFils): array
    {
        $productIds = $cart->items
            ->filter(fn ($i) => $i->variant?->product !== null)
            ->pluck('variant.product_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $categoryIds = $cart->items
            ->filter(fn ($i) => $i->variant?->product !== null)
            ->pluck('variant.product.category_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return [
            'subtotal_fils' => $subtotalFils,
            'product_ids' => $productIds,
            'category_ids' => $categoryIds,
        ];
    }

    /** @return array<int, array{product_id: int|null, category_id: int|null, line_total_fils: int}> */
    private function buildItemsForDiscount(Cart $cart): array
    {
        return $cart->items->map(fn (CartItem $item) => [
            'product_id' => $item->variant?->product_id,
            'category_id' => $item->variant?->product?->category_id,
            'line_total_fils' => $item->line_total_fils,
        ])->toArray();
    }
}
