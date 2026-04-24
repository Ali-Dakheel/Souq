<?php

declare(strict_types=1);

namespace App\Modules\Cart\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Requests\AddToCartRequest;
use App\Modules\Cart\Requests\ApplyCouponRequest;
use App\Modules\Cart\Requests\MergeCartRequest;
use App\Modules\Cart\Requests\UpdateCartItemRequest;
use App\Modules\Cart\Resources\CartItemResource;
use App\Modules\Cart\Resources\CartResource;
use App\Modules\Cart\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $cart->loadMissing('items.variant.product');
        $totals = $this->cartService->calculateTotals($cart);

        return (new CartResource($cart, $totals))
            ->response()
            ->setStatusCode(200);
    }

    public function addItem(AddToCartRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $cartItem = $this->cartService->addItem(
            $cart,
            $request->integer('variant_id'),
            $request->integer('quantity'),
        );

        $cart->loadMissing('items.variant.product');
        $totals = $this->cartService->calculateTotals($cart);

        return response()->json([
            'message' => 'Item added to cart.',
            'data' => new CartItemResource($cartItem),
            'cart' => $this->cartSummary($cart, $totals),
        ], 201);
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        $cart = $this->resolveCart($request);
        abort_unless((int) $cartItem->cart_id === (int) $cart->id, 403, 'This item does not belong to your cart.');

        $updated = $this->cartService->updateItemQuantity($cartItem, $request->integer('quantity'));
        $cart->loadMissing('items.variant.product');
        $totals = $this->cartService->calculateTotals($cart);

        return response()->json([
            'message' => 'Cart item updated.',
            'data' => new CartItemResource($updated),
            'cart' => $this->cartSummary($cart, $totals),
        ]);
    }

    public function removeItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $cart = $this->resolveCart($request);
        abort_unless((int) $cartItem->cart_id === (int) $cart->id, 403, 'This item does not belong to your cart.');

        $this->cartService->removeItem($cartItem);
        $cart->loadMissing('items.variant.product');
        $totals = $this->cartService->calculateTotals($cart);

        return response()->json([
            'message' => 'Item removed from cart.',
            'cart' => $this->cartSummary($cart, $totals),
        ]);
    }

    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $result = $this->cartService->applyCoupon(
            $cart,
            $request->string('coupon_code')->toString(),
            Auth::id(),
        );

        $cart->loadMissing('items.variant.product');
        $totals = $this->cartService->calculateTotals($cart);

        return response()->json([
            'message' => 'Coupon applied.',
            'data' => [
                'coupon' => [
                    'code' => $result['coupon']->code,
                    'description' => $result['coupon']->description,
                    'discount_type' => $result['coupon']->discount_type,
                    'discount_value' => $result['coupon']->discount_value,
                ],
                'discount_fils' => $result['discount_fils'],
            ],
            'cart' => $this->cartSummary($cart, $totals),
        ]);
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $this->cartService->removeCoupon($cart);
        $cart->loadMissing('items.variant.product');
        $totals = $this->cartService->calculateTotals($cart);

        return response()->json([
            'message' => 'Coupon removed.',
            'cart' => $this->cartSummary($cart, $totals),
        ]);
    }

    public function merge(MergeCartRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $userCart = $this->cartService->getOrCreateCart($user->id, null);

        $guestCart = Cart::where('session_id', $request->string('guest_session_id')->toString())
            ->whereNull('user_id')
            ->first();

        if (! $guestCart || $guestCart->items()->doesntExist()) {
            $userCart->loadMissing('items.variant.product');
            $totals = $this->cartService->calculateTotals($userCart);

            return response()->json([
                'message' => 'No guest cart found to merge.',
                'items_added' => 0,
                'items_updated' => 0,
                'items_removed_due_to_limits' => 0,
                'cart' => $this->cartSummary($userCart, $totals),
            ]);
        }

        $stats = $this->cartService->mergeCart($guestCart, $userCart, $user);
        $userCart->loadMissing('items.variant.product');
        $totals = $this->cartService->calculateTotals($userCart);

        return response()->json([
            'message' => 'Cart merged successfully.',
            ...$stats,
            'cart' => $this->cartSummary($userCart, $totals),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $this->cartService->clearCart($cart);

        return response()->json(['message' => 'Cart cleared.']);
    }

    // -----------------------------------------------------------------------

    private function resolveCart(Request $request): Cart
    {
        return $this->cartService->getOrCreateCart(
            Auth::id(),
            $request->header('X-Cart-Session'),
        );
    }

    /**
     * @param  array{subtotal_fils: int, discount_fils: int, promotion_discount_fils: int, vat_fils: int, total_fils: int}  $totals
     * @return array<string, mixed>
     */
    private function cartSummary(Cart $cart, array $totals): array
    {
        return [
            'item_count' => $cart->items->sum('quantity'),
            'subtotal_fils' => $totals['subtotal_fils'],
            'coupon_code' => $cart->coupon_code,
            'discount_fils' => $totals['discount_fils'],
            'promotion_discount_fils' => $totals['promotion_discount_fils'],
            'vat_fils' => $totals['vat_fils'],
            'total_fils' => $totals['total_fils'],
        ];
    }
}
