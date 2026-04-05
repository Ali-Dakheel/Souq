<?php

declare(strict_types=1);

namespace App\Modules\Returns\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'in:defective,wrong_item,not_as_described,changed_mind,other'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.quantity_returned' => ['required', 'integer', 'min:1'],
            'items.*.condition' => ['required', 'string', 'in:unopened,opened,damaged'],
        ];
    }
}
