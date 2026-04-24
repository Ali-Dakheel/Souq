<?php

declare(strict_types=1);

namespace App\Modules\Cart\Services;

use App\Modules\Cart\Models\Coupon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Validate a coupon code against cart context.
     * Returns the validated Coupon model.
     *
     * @param  array{subtotal_fils?: int, category_ids?: int[], product_ids?: int[]}  $cartData
     *
     * @throws ValidationException
     */
    public function validate(string $code, array $cartData = [], ?int $userId = null): Coupon
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (! $coupon || ! $coupon->is_active) {
            throw ValidationException::withMessages(['coupon_code' => ['Coupon code is invalid.']]);
        }

        if ($coupon->isNotYetActive()) {
            throw ValidationException::withMessages(['coupon_code' => ['This coupon is not yet active.']]);
        }

        if ($coupon->isExpired()) {
            throw ValidationException::withMessages(['coupon_code' => ['This coupon has expired.']]);
        }

        if ($coupon->isExhausted()) {
            throw ValidationException::withMessages(['coupon_code' => ['This coupon has reached its usage limit.']]);
        }

        $subtotal = $cartData['subtotal_fils'] ?? 0;
        if ($coupon->minimum_order_amount_fils > 0 && $subtotal < $coupon->minimum_order_amount_fils) {
            $minBhd = number_format($coupon->minimum_order_amount_fils / 1000, 3);
            throw ValidationException::withMessages([
                'coupon_code' => ["A minimum order of BHD {$minBhd} is required for this coupon."],
            ]);
        }

        // Per-user usage check (requires user to be authenticated)
        if ($userId !== null && $coupon->max_uses_per_user !== null) {
            $userUsages = $coupon->usages()->where('user_id', $userId)->count();
            if ($userUsages >= $coupon->max_uses_per_user) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['You have already used this coupon the maximum number of times.'],
                ]);
            }
        }

        // Scope check
        if (! $coupon->appliesToAll()) {
            $productIds = $cartData['product_ids'] ?? [];
            $categoryIds = $cartData['category_ids'] ?? [];

            $match = false;

            if ($coupon->appliesToProducts() && ! empty($productIds)) {
                $match = $coupon->applicableItems()
                    ->where('itemable_type', 'product')
                    ->whereIn('itemable_id', $productIds)
                    ->exists();
            }

            if (! $match && $coupon->appliesToCategories() && ! empty($categoryIds)) {
                $match = $coupon->applicableItems()
                    ->where('itemable_type', 'category')
                    ->whereIn('itemable_id', $categoryIds)
                    ->exists();
            }

            if (! $match) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['This coupon does not apply to any items in your cart.'],
                ]);
            }
        }

        return $coupon;
    }

    /**
     * Calculate the discount in fils for a given coupon and cart items.
     *
     * @param  array<int, array{product_id: int|null, category_id: int|null, line_total_fils: int}>  $items
     */
    public function calculateDiscount(Coupon $coupon, int $subtotalFils, array $items = []): int
    {
        $applicableSubtotal = $this->getApplicableSubtotal($coupon, $subtotalFils, $items);

        if ($coupon->discount_type === 'percentage') {
            $discount = (int) round($applicableSubtotal * $coupon->discount_value / 100);

            // Apply percentage cap if set
            if ($coupon->maximum_discount_fils !== null) {
                $discount = min($discount, $coupon->maximum_discount_fils);
            }

            return $discount;
        }

        // fixed_fils: capped at applicable subtotal
        return min($coupon->discount_value, $applicableSubtotal);
    }

    /**
     * Return active coupons visible to customers (for listing).
     *
     * @return Collection<int, Coupon>
     */
    public function getActiveCoupons(?int $categoryId = null, ?int $productId = null)
    {
        $query = Coupon::active();

        if ($categoryId !== null || $productId !== null) {
            $query->where(function ($q) use ($categoryId, $productId) {
                $q->where('applicable_to', 'all_products');

                if ($categoryId !== null) {
                    $q->orWhere(function ($sq) use ($categoryId) {
                        $sq->where('applicable_to', 'specific_categories')
                            ->whereHas('applicableItems', fn ($ai) => $ai
                                ->where('itemable_type', 'category')
                                ->where('itemable_id', $categoryId)
                            );
                    });
                }

                if ($productId !== null) {
                    $q->orWhere(function ($sq) use ($productId) {
                        $sq->where('applicable_to', 'specific_products')
                            ->whereHas('applicableItems', fn ($ai) => $ai
                                ->where('itemable_type', 'product')
                                ->where('itemable_id', $productId)
                            );
                    });
                }
            });
        }

        return $query->get();
    }

    /** @param array<int, array{product_id: int|null, category_id: int|null, line_total_fils: int}> $items */
    private function getApplicableSubtotal(Coupon $coupon, int $subtotalFils, array $items): int
    {
        if ($coupon->appliesToAll() || empty($items)) {
            return $subtotalFils;
        }

        $couponProductIds = $coupon->appliesToProducts()
            ? $coupon->applicableItems()->where('itemable_type', 'product')->pluck('itemable_id')->toArray()
            : [];

        $couponCategoryIds = $coupon->appliesToCategories()
            ? $coupon->applicableItems()->where('itemable_type', 'category')->pluck('itemable_id')->toArray()
            : [];

        $applicable = 0;
        foreach ($items as $item) {
            $productMatch = ! empty($couponProductIds) && in_array($item['product_id'] ?? null, $couponProductIds, true);
            $categoryMatch = ! empty($couponCategoryIds) && in_array($item['category_id'] ?? null, $couponCategoryIds, true);

            if ($productMatch || $categoryMatch) {
                $applicable += $item['line_total_fils'];
            }
        }

        return $applicable;
    }
}
