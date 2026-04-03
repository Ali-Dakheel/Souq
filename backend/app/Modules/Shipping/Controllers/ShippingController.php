<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Services\CartService;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Shipping\Requests\ShippingRatesRequest;
use App\Modules\Shipping\Resources\ShippingRateResource;
use App\Modules\Shipping\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ShippingController extends Controller
{
    public function __construct(
        private readonly ShippingService $shippingService,
        private readonly CartService $cartService,
    ) {}

    /**
     * GET /api/v1/shipping/rates?address_id={id}
     * Get available shipping rates for a cart and address.
     */
    public function rates(ShippingRatesRequest $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart(
            Auth::id(),
            $request->header('X-Cart-Session'),
        );

        $address = CustomerAddress::findOrFail($request->integer('address_id'));

        // Ownership check: authenticated users can only check rates for their own addresses
        if (Auth::id() !== null && $address->user_id !== Auth::id()) {
            abort(403, 'Address not found');
        }

        $rates = $this->shippingService->getAvailableRates($cart, $address);

        return response()->json([
            'data' => ShippingRateResource::collection(collect($rates)),
        ]);
    }
}
