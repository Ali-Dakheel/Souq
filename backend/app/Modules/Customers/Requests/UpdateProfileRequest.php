<?php

declare(strict_types=1);

namespace App\Modules\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'nationality' => ['sometimes', 'nullable', 'string', 'size:2'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female,other'],
            'preferred_locale' => ['sometimes', 'string', 'in:ar,en'],
            'marketing_consent' => ['sometimes', 'boolean'],
        ];
    }
}
