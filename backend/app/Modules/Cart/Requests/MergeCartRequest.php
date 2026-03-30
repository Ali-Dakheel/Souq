<?php

declare(strict_types=1);

namespace App\Modules\Cart\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guest_session_id' => ['required', 'string', 'max:255'],
        ];
    }
}
