<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\User;
use App\Modules\Cart\Services\CartService;
use App\Modules\Customers\Models\Wishlist;
use App\Modules\Customers\Models\WishlistItem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WishlistService
{
    public function getOrCreate(User $user): Wishlist
    {
        return Wishlist::firstOrCreate(
            ['user_id' => $user->id],
            ['is_public' => false],
        );
    }

    public function addItem(Wishlist $wishlist, int $variantId): WishlistItem
    {
        try {
            return WishlistItem::create([
                'wishlist_id' => $wishlist->id,
                'variant_id' => $variantId,
                'added_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['variant_id' => ['Item already in wishlist.']]);
        }
    }

    public function removeItem(Wishlist $wishlist, int $variantId): void
    {
        WishlistItem::where('wishlist_id', $wishlist->id)
            ->where('variant_id', $variantId)
            ->delete();
    }

    public function generateShareToken(Wishlist $wishlist): Wishlist
    {
        if (! $wishlist->share_token) {
            $token = Str::uuid();
            $wishlist->update([
                'share_token' => $token,
                'is_public' => true,
            ]);
        } else {
            $wishlist->update(['is_public' => true]);
        }

        return $wishlist;
    }

    public function moveItemToCart(Wishlist $wishlist, int $variantId, User $user): array
    {
        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreateCart($user->id, null);
        $cartItem = $cartService->addItem($cart, $variantId, 1);

        return [
            'cart_item' => $cartItem,
            'cart' => $cart->load(['items.variant.product']),
        ];
    }

    public function getByToken(string $token): ?Wishlist
    {
        return Wishlist::where('share_token', $token)
            ->where('is_public', true)
            ->first();
    }
}
