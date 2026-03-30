<?php

declare(strict_types=1);

namespace App\Modules\Cart\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Resources\PublicCouponResource;
use App\Modules\Cart\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService,
    ) {}

    /**
     * Return publicly visible active coupons, optionally filtered by category or variant.
     */
    public function active(Request $request): AnonymousResourceCollection
    {
        $coupons = $this->couponService->getActiveCoupons(
            $request->integer('category_id') ?: null,
            $request->integer('product_id') ?: null,
        );

        return PublicCouponResource::collection($coupons);
    }

    /**
     * Validate a coupon code without applying it — useful for discount previews.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'subtotal_fils' => ['sometimes', 'integer', 'min:0'],
        ]);

        $coupon = $this->couponService->validate(
            $request->string('code')->toString(),
            ['subtotal_fils' => $request->integer('subtotal_fils', 0)],
        );

        return response()->json([
            'valid' => true,
            'coupon' => [
                'code' => $coupon->code,
                'description' => $coupon->description,
                'discount_type' => $coupon->discount_type,
                'discount_value' => $coupon->discount_value,
            ],
        ]);
    }
}
