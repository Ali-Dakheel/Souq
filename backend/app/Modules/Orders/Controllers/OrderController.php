<?php

declare(strict_types=1);

namespace App\Modules\Orders\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Services\CartService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Requests\CancelOrderRequest;
use App\Modules\Orders\Requests\CheckoutRequest;
use App\Modules\Orders\Resources\OrderListResource;
use App\Modules\Orders\Resources\OrderResource;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly CartService $cartService,
    ) {}

    /**
     * POST /api/v1/checkout
     * Create an order from the current cart.
     */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart(
            Auth::id(),
            $request->header('X-Cart-Session'),
        );

        $order = $this->orderService->checkout(
            cart: $cart,
            userId: Auth::id(),
            guestEmail: $request->string('guest_email')->toString() ?: null,
            shippingAddressId: $request->integer('shipping_address_id'),
            billingAddressId: $request->integer('billing_address_id'),
            paymentMethod: $request->string('payment_method')->toString(),
            notes: $request->string('notes')->toString() ?: null,
            locale: $request->string('locale', 'ar')->toString(),
        );

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/orders
     * List authenticated user's orders (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->orderService->getUserOrders(
            userId: Auth::id(),
            page: (int) $request->query('page', 1),
            perPage: (int) $request->query('per_page', 15),
            status: $request->query('status'),
        );

        return response()->json([
            'data' => OrderListResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/orders/{orderNumber}
     * Show a single order for the authenticated user.
     */
    public function show(string $orderNumber): JsonResponse
    {
        $order = $this->orderService->getOrderByNumber($orderNumber, Auth::id());

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * GET /api/v1/orders/{orderNumber}/guest
     * Show a single order for a guest, verified by email.
     */
    public function showGuest(Request $request, string $orderNumber): JsonResponse
    {
        $email = $request->query('email', '');

        $order = Order::with(['items', 'statusHistory'])
            ->where('order_number', $orderNumber)
            ->whereNotNull('guest_email')
            ->firstOrFail();

        if (! hash_equals($order->guest_email, (string) $email)) {
            abort(401, 'Email address does not match this order.');
        }

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * POST /api/v1/orders/{orderNumber}/cancel
     * Cancel a pending/initiated order.
     */
    public function cancel(CancelOrderRequest $request, string $orderNumber): JsonResponse
    {
        $order = $this->orderService->getOrderByNumber($orderNumber, Auth::id());

        try {
            $order = $this->orderService->cancelOrder(
                order: $order,
                reason: $request->string('reason')->toString() ?: null,
                changedBy: 'customer',
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['order' => [$e->getMessage()]]);
        }

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(200);
    }
}
