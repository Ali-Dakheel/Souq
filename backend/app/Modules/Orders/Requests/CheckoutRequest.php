<?php

declare(strict_types=1);

namespace App\Modules\Orders\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = Auth::id();

        $addressRule = $userId !== null
            ? Rule::exists('customer_addresses', 'id')->where('user_id', $userId)
            : 'exists:customer_addresses,id';

        return [
            'payment_method' => ['required', 'string', 'in:benefit,benefit_pay_qr,card,apple_pay,cod'],
            'shipping_address_id' => ['required', 'integer', $addressRule],
            'billing_address_id' => ['required', 'integer', $addressRule],
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
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
