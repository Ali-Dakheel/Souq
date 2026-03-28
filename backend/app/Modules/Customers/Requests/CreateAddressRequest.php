<?php

declare(strict_types=1);

namespace App\Modules\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'address_type' => ['required', 'string', 'in:shipping,billing'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'governorate' => ['required', 'string', 'max:100'],
            'district' => ['sometimes', 'nullable', 'string', 'max:100'],
            'street_address' => ['required', 'string', 'max:500'],
            'building_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'apartment_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'delivery_instructions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
