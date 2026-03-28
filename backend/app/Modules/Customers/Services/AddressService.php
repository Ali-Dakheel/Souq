<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\User;
use App\Modules\Customers\Events\AddressAdded;
use App\Modules\Customers\Events\AddressDeleted;
use App\Modules\Customers\Events\AddressUpdated;
use App\Modules\Customers\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddressService
{
    /**
     * Return all active addresses for a user, optionally filtered by type.
     *
     * @return Collection<int, CustomerAddress>
     */
    public function listAddresses(User $user, ?string $type = null): Collection
    {
        return $user->addresses()
            ->where('is_active', true)
            ->when($type, fn ($q) => $q->where('address_type', $type))
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Fetch a single address that belongs to the given user.
     */
    public function getAddress(User $user, int $addressId): CustomerAddress
    {
        return $user->addresses()->findOrFail($addressId);
    }

    /**
     * Create a new address. Unsets any existing default of the same type when is_default=true.
     *
     * @param  array{address_type: string, recipient_name: string, phone: string, governorate: string, district?: string|null, street_address: string, building_number?: string|null, apartment_number?: string|null, postal_code?: string|null, delivery_instructions?: string|null, is_default?: bool}  $data
     */
    public function createAddress(User $user, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $data) {
            if (! empty($data['is_default'])) {
                $this->clearDefault($user, $data['address_type']);
            }

            $address = $user->addresses()->create($data);

            AddressAdded::dispatch($address);

            return $address;
        });
    }

    /**
     * Update an existing address. Unsets any existing default of the same type when is_default toggled on.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAddress(User $user, int $addressId, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $addressId, $data) {
            $address = $this->getAddress($user, $addressId);

            if (! empty($data['is_default'])) {
                $this->clearDefault($user, $address->address_type);
            }

            $before = $address->only(array_keys($data));
            $address->fill($data);

            $changed = array_keys(array_filter(
                $data,
                fn ($value, string $key) => ($before[$key] ?? null) !== $value,
                ARRAY_FILTER_USE_BOTH
            ));

            if (! empty($changed)) {
                $address->save();
                AddressUpdated::dispatch($address, $changed);
            }

            return $address;
        });
    }

    /**
     * Delete an address. Blocks deletion of the only active shipping address.
     *
     * @throws ValidationException
     */
    public function deleteAddress(User $user, int $addressId): void
    {
        $address = $this->getAddress($user, $addressId);

        if ($address->address_type === 'shipping') {
            $shippingCount = $user->addresses()
                ->where('address_type', 'shipping')
                ->where('is_active', true)
                ->count();

            if ($shippingCount <= 1) {
                throw ValidationException::withMessages([
                    'address' => ['Cannot delete your only shipping address.'],
                ]);
            }
        }

        $userId = $user->id;
        $type = $address->address_type;

        $address->delete();

        AddressDeleted::dispatch($userId, $addressId, $type);
    }

    /**
     * Mark an address as the default for its type, clearing the previous default.
     */
    public function setDefaultAddress(User $user, int $addressId): CustomerAddress
    {
        return DB::transaction(function () use ($user, $addressId) {
            $address = $this->getAddress($user, $addressId);

            $this->clearDefault($user, $address->address_type);

            $address->forceFill(['is_default' => true])->save();

            AddressUpdated::dispatch($address, ['is_default']);

            return $address;
        });
    }

    /**
     * Return the default shipping address for the user, or null.
     */
    public function getDefaultShippingAddress(User $user): ?CustomerAddress
    {
        return $user->addresses()
            ->where('address_type', 'shipping')
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Return the default billing address for the user, or null.
     */
    public function getDefaultBillingAddress(User $user): ?CustomerAddress
    {
        return $user->addresses()
            ->where('address_type', 'billing')
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Unset is_default on all addresses of the given type for the user.
     */
    private function clearDefault(User $user, string $addressType): void
    {
        $user->addresses()
            ->where('address_type', $addressType)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
