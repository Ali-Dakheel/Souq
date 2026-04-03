<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Customers\Requests\AddWishlistItemRequest;
use App\Modules\Customers\Resources\WishlistResource;
use App\Modules\Customers\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WishlistController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlistService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wishlist = $this->wishlistService->getOrCreate($user);
        $wishlist->load('items.variant.product');

        return (new WishlistResource($wishlist))
            ->response()
            ->setStatusCode(200);
    }

    public function addItem(AddWishlistItemRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wishlist = $this->wishlistService->getOrCreate($user);
        $this->wishlistService->addItem($wishlist, $request->integer('variant_id'));

        $wishlist->load('items.variant.product');

        return (new WishlistResource($wishlist))
            ->response()
            ->setStatusCode(201);
    }

    public function removeItem(Request $request, int $variantId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wishlist = $this->wishlistService->getOrCreate($user);
        $item = $wishlist->items()->where('variant_id', $variantId)->first();

        if (! $item) {
            abort(404);
        }

        $this->wishlistService->removeItem($wishlist, $variantId);

        return response()->json(['message' => 'Removed.']);
    }

    public function generateShareToken(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wishlist = $this->wishlistService->getOrCreate($user);
        $wishlist = $this->wishlistService->generateShareToken($wishlist);

        $shareUrl = config('app.url').'/api/v1/wishlists/shared/'.$wishlist->share_token;

        return response()->json([
            'share_token' => $wishlist->share_token,
            'share_url' => $shareUrl,
        ]);
    }

    public function moveToCart(Request $request, int $variantId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wishlist = $this->wishlistService->getOrCreate($user);

        try {
            $result = $this->wishlistService->moveItemToCart($wishlist, $variantId, $user);

            return response()->json([
                'message' => 'Item moved to cart.',
                'cart' => $result['cart'],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function showShared(Request $request, string $token): JsonResponse
    {
        $wishlist = $this->wishlistService->getByToken($token);

        if (! $wishlist) {
            abort(404);
        }

        $wishlist->load('items.variant.product');

        return (new WishlistResource($wishlist))
            ->response()
            ->setStatusCode(200);
    }
}
