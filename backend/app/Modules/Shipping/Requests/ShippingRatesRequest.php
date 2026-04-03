<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShippingRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', 'exists:customer_addresses,id'],
        ];
    }
}
