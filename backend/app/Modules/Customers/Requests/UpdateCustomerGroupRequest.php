<?php

declare(strict_types=1);

namespace App\Modules\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerGroupRequest extends FormRequest
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
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('customer_groups', 'slug')->ignore($this->route('group'))],
            'description' => ['nullable', 'string'],
            'is_default' => ['boolean'],
        ];
    }
}
