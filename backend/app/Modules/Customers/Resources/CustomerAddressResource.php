<?php

declare(strict_types=1);

namespace App\Modules\Customers\Resources;

use App\Modules\Customers\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerAddress */
class CustomerAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'address_type' => $this->address_type,
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'governorate' => $this->governorate,
            'district' => $this->district,
            'street_address' => $this->street_address,
            'building_number' => $this->building_number,
            'apartment_number' => $this->apartment_number,
            'postal_code' => $this->postal_code,
            'delivery_instructions' => $this->delivery_instructions,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
