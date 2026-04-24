<?php

declare(strict_types=1);

namespace App\Modules\Customers\Resources;

use App\Models\User;
use App\Modules\Customers\Models\CustomerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->profile()->first();
        $profileIsValid = $profile instanceof CustomerProfile;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'phone' => $profileIsValid ? $profile->phone : null,
            'preferred_locale' => $profileIsValid ? ($profile->preferred_locale ?? 'ar') : 'ar',
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
