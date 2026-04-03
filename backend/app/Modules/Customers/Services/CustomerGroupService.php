<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Customers\Models\CustomerGroup;
use App\Modules\Customers\Models\VariantGroupPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerGroupService
{
    /**
     * Return all groups.
     *
     * @return Collection<int, CustomerGroup>
     */
    public function listGroups(): Collection
    {
        return CustomerGroup::orderBy('name_en')->get();
    }

    /**
     * Create a new customer group.
     * - If slug not provided, auto-generate from name_en using Str::slug()
     * - If is_default=true, wrap in DB::transaction: set all others is_default=false, then create the new group
     * - If is_default=false or not provided, just create normally
     *
     * @param  array{name_en: string, name_ar: string, slug?: string, description?: string, is_default?: bool}  $data
     */
    public function createGroup(array $data): CustomerGroup
    {
        return DB::transaction(function () use ($data) {
            // Auto-generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name_en']);
            }

            // If is_default is true, unset all other defaults
            if (! empty($data['is_default'])) {
                CustomerGroup::query()->update(['is_default' => false]);
            }

            return CustomerGroup::create($data);
        });
    }

    /**
     * Update an existing group.
     * - Same is_default enforcement: if is_default=true, set all others to false first (DB::transaction)
     * - If slug not in $data, do NOT touch existing slug
     *
     * @param  array<string, mixed>  $data
     */
    public function updateGroup(CustomerGroup $group, array $data): CustomerGroup
    {
        return DB::transaction(function () use ($group, $data) {
            // If is_default is true, unset all other defaults
            if (! empty($data['is_default'])) {
                CustomerGroup::query()
                    ->where('id', '!=', $group->id)
                    ->update(['is_default' => false]);
            }

            $group->fill($data);
            $group->save();

            return $group;
        });
    }

    /**
     * Delete a group.
     * - Throw \InvalidArgumentException if $group->is_default is true (cannot delete default group)
     * - Otherwise delete
     *
     * @throws \InvalidArgumentException
     */
    public function deleteGroup(CustomerGroup $group): void
    {
        if ($group->is_default) {
            throw new \InvalidArgumentException('Cannot delete the default customer group.');
        }

        $group->delete();
    }

    /**
     * Set (upsert) a group price for a variant.
     * Uses updateOrCreate(['variant_id' => ..., 'customer_group_id' => ...], ['price_fils' => ..., 'compare_at_price_fils' => ...])
     *
     * @param  array{price_fils: int, compare_at_price_fils?: int|null}  $data
     */
    public function setGroupPrice(Variant $variant, CustomerGroup $group, array $data): VariantGroupPrice
    {
        return VariantGroupPrice::updateOrCreate(
            [
                'variant_id' => $variant->id,
                'customer_group_id' => $group->id,
            ],
            [
                'price_fils' => $data['price_fils'],
                'compare_at_price_fils' => $data['compare_at_price_fils'] ?? null,
            ]
        );
    }

    /**
     * Remove group price for a variant.
     * Delete the VariantGroupPrice row. No-op if not found (use ->delete() on query).
     */
    public function removeGroupPrice(Variant $variant, CustomerGroup $group): void
    {
        VariantGroupPrice::where('variant_id', $variant->id)
            ->where('customer_group_id', $group->id)
            ->delete();
    }

    /**
     * Get the effective price for a user+variant pair.
     * - If $user is null (guest): return $variant->price_fils
     * - If $user->customer_group_id is null: return $variant->price_fils
     * - Otherwise: look for VariantGroupPrice where variant_id + customer_group_id match
     *   - If found: return the group price_fils
     *   - If not found: return $variant->price_fils
     */
    public function getGroupPriceForUser(?User $user, Variant $variant): int
    {
        // If user is null (guest), return default price
        if ($user === null) {
            return $variant->effective_price_fils;
        }

        // If user has no customer group, return default price
        if ($user->customer_group_id === null) {
            return $variant->effective_price_fils;
        }

        // Look for a group price
        $groupPrice = VariantGroupPrice::where('variant_id', $variant->id)
            ->where('customer_group_id', $user->customer_group_id)
            ->first();

        // If found, return the group price; otherwise return default price
        return $groupPrice?->price_fils ?? $variant->effective_price_fils;
    }
}
