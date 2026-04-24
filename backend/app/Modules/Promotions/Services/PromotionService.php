<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Services;

use App\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Promotions\Models\PromotionCondition;
use App\Modules\Promotions\Models\PromotionRule;
use App\Modules\Promotions\Models\PromotionUsage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class PromotionService
{
    /**
     * Return all applicable promotion rules for the given cart and user,
     * ordered by priority (ascending = applied first).
     * Exclusive rule stops further rule application.
     *
     * @return EloquentCollection<int, PromotionRule>
     */
    public function getApplicableRules(Cart $cart, ?User $user): EloquentCollection
    {
        // Load cart items with variant and product relationships to avoid N+1
        $cart->loadMissing('items.variant.product');

        $applicable = new EloquentCollection;
        $rules = PromotionRule::active()
            ->with(['conditions', 'actions'])
            ->orderBy('priority', 'asc')
            ->get();

        foreach ($rules as $rule) {
            // Check global usage limit
            if ($rule->isExhaustedGlobally()) {
                continue;
            }

            // Check per-user usage limit
            if ($user !== null && $rule->isExhaustedForUser($user->id)) {
                continue;
            }

            // Evaluate all conditions (AND logic)
            $allConditionsMet = $rule->conditions->every(
                fn ($condition) => $this->evaluateCondition($condition, $cart, $user)
            );

            if ($allConditionsMet) {
                $applicable->push($rule);

                // Stop evaluating further rules if this one is exclusive
                if ($rule->is_exclusive) {
                    break;
                }
            }
        }

        return $applicable;
    }

    /**
     * Evaluate a single promotion condition against a cart and user.
     */
    private function evaluateCondition(PromotionCondition $condition, Cart $cart, ?User $user): bool
    {
        $type = $condition->type;
        $operator = $condition->operator;
        $value = $condition->value;

        return match ($type) {
            'cart_total' => $this->evaluateCartTotal($cart, $operator, $value),
            'item_qty' => $this->evaluateItemQty($cart, $operator, $value),
            'customer_group' => $this->evaluateCustomerGroup($user, $operator, $value),
            'product_in_cart' => $this->evaluateProductInCart($cart, $operator, $value),
            'category_in_cart' => $this->evaluateCategoryInCart($cart, $operator, $value),
            default => false,
        };
    }

    /**
     * Evaluate cart_total condition.
     */
    private function evaluateCartTotal(Cart $cart, string $operator, mixed $value): bool
    {
        $cartTotal = $cart->items->sum('line_total_fils');

        return $this->compareValues($cartTotal, $operator, $value);
    }

    /**
     * Evaluate item_qty condition.
     */
    private function evaluateItemQty(Cart $cart, string $operator, mixed $value): bool
    {
        $totalQty = $cart->items->sum('quantity');

        return $this->compareValues($totalQty, $operator, $value);
    }

    /**
     * Evaluate customer_group condition.
     */
    /** @param array<int, int>|mixed $value */
    private function evaluateCustomerGroup(?User $user, string $operator, mixed $value): bool
    {
        if ($user === null) {
            return false;
        }

        $userGroupId = $user->customer_group_id;

        if ($operator === 'in') {
            return in_array($userGroupId, $value, true);
        }

        if ($operator === 'not_in') {
            return ! in_array($userGroupId, $value, true);
        }

        return false;
    }

    /**
     * Evaluate product_in_cart condition.
     */
    /** @param array<int, int>|mixed $value */
    private function evaluateProductInCart(Cart $cart, string $operator, mixed $value): bool
    {
        $productIds = $cart->items
            ->map(fn ($item) => $item->variant?->product_id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if ($operator === 'in') {
            return count(array_intersect($productIds, $value)) > 0;
        }

        if ($operator === 'not_in') {
            return count(array_intersect($productIds, $value)) === 0;
        }

        return false;
    }

    /**
     * Evaluate category_in_cart condition.
     */
    /** @param array<int, int>|mixed $value */
    private function evaluateCategoryInCart(Cart $cart, string $operator, mixed $value): bool
    {
        $categoryIds = $cart->items
            ->map(fn ($item) => $item->variant?->product?->category_id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if ($operator === 'in') {
            return count(array_intersect($categoryIds, $value)) > 0;
        }

        if ($operator === 'not_in') {
            return count(array_intersect($categoryIds, $value)) === 0;
        }

        return false;
    }

    /**
     * Compare values using the specified operator.
     */
    private function compareValues(mixed $left, string $operator, mixed $right): bool
    {
        return match ($operator) {
            'gte' => $left >= $right,
            'lte' => $left <= $right,
            'eq' => $left == $right,
            default => false,
        };
    }

    /**
     * Calculate the total discount and free shipping flag from all actions on a rule.
     *
     * @return array{promotion_discount_fils: int, free_shipping: bool}
     */
    public function calculateActionDiscount(PromotionRule $rule, Cart $cart, ?int $effectiveSubtotal = null): array
    {
        $cartSubtotal = $effectiveSubtotal ?? $cart->items->sum('line_total_fils');
        $totalDiscount = 0;
        $freeShipping = false;

        foreach ($rule->actions as $action) {
            $type = $action->type;
            $value = $action->value;

            $discount = match ($type) {
                'percent_off_cart' => isset($value['percent']) ? (int) round($cartSubtotal * ($value['percent'] / 100)) : 0,
                'fixed_off_cart' => isset($value['amount_fils']) ? min((int) $value['amount_fils'], $cartSubtotal) : 0,
                // percent_off_items: applies percentage discount to the full cart subtotal.
                // Future enhancement: could be scoped to specific items matching rule conditions.
                'percent_off_items' => isset($value['percent']) ? (int) round($cartSubtotal * ($value['percent'] / 100)) : 0,
                'bogo' => $this->calculateBogoDiscount($cart),
                'free_shipping' => 0, // free_shipping has no discount value
                default => 0,
            };

            if ($type === 'free_shipping') {
                $freeShipping = true;
            } else {
                $totalDiscount += $discount;
            }
        }

        // Cap discount at subtotal
        $totalDiscount = min($totalDiscount, $cartSubtotal);

        return [
            'promotion_discount_fils' => $totalDiscount,
            'free_shipping' => $freeShipping,
        ];
    }

    /**
     * Calculate BOGO (Buy One Get One) discount — 50% off the lowest-priced item.
     */
    private function calculateBogoDiscount(Cart $cart): int
    {
        if ($cart->items->isEmpty()) {
            return 0;
        }

        // Find the item with the lowest line_total_fils
        $lowestItem = $cart->items->sortBy('line_total_fils')->first();

        if ($lowestItem === null) {
            return 0;
        }

        // Return 50% off the lowest item
        return (int) round($lowestItem->line_total_fils / 2);
    }

    /**
     * Record usage of a promotion rule.
     */
    public function recordUsage(PromotionRule $rule, ?User $user, ?int $orderId): void
    {
        PromotionUsage::create([
            'promotion_rule_id' => $rule->id,
            'user_id' => $user?->id,
            'order_id' => $orderId,
            'used_at' => now(),
        ]);
    }
}
