<?php

declare(strict_types=1);

namespace App\Modules\Orders\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'in:benefit,benefit_pay_qr,card,apple_pay'],
            'shipping_address_id' => ['required', 'integer', 'exists:customer_addresses,id'],
            'billing_address_id' => ['required', 'integer', 'exists:customer_addresses,id'],
            'guest_email' => [
                $this->isGuestCheckout() ? 'required' : 'nullable',
                'email',
                'max:255',
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function isGuestCheckout(): bool
    {
        return ! Auth::check();
    }
}
