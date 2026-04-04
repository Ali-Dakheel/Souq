<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Controllers;

use App\Modules\Cart\Models\Cart;
use App\Modules\Promotions\Resources\PromotionRuleResource;
use App\Modules\Promotions\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $promotionService) {}

    /**
     * GET /api/v1/promotions/applicable
     * Returns applicable rules for the authenticated user's active cart.
     */
    public function applicable(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get or resolve user's active cart
        $cart = Cart::where('user_id', $user->id)->first();

        // If no cart, return empty array
        if ($cart === null) {
            return response()->json([]);
        }

        // Get applicable rules
        $rules = $this->promotionService->getApplicableRules($cart, $user);

        // Return as resource
        return response()->json(PromotionRuleResource::collection($rules));
    }
}
