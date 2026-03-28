<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\User;
use App\Modules\Customers\Events\CustomerProfileUpdated;
use App\Modules\Customers\Models\CustomerProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    /**
     * Get or lazily create the customer profile for the given user.
     */
    public function getProfile(User $user): CustomerProfile
    {
        return $user->profile ?? CustomerProfile::create([
            'user_id' => $user->id,
            'preferred_locale' => 'ar',
            'marketing_consent' => false,
        ]);
    }

    /**
     * Update mutable profile fields. Fires CustomerProfileUpdated when anything changes.
     *
     * @param  array{phone?: string|null, nationality?: string|null, date_of_birth?: string|null, gender?: string|null, preferred_locale?: string, marketing_consent?: bool}  $data
     */
    public function updateProfile(User $user, array $data): CustomerProfile
    {
        $profile = $this->getProfile($user);

        $before = $profile->only(array_keys($data));
        $profile->fill($data);

        $changed = array_keys(array_filter(
            $data,
            fn ($value, string $key) => $before[$key] !== $value,
            ARRAY_FILTER_USE_BOTH
        ));

        if (! empty($changed)) {
            $profile->save();
            CustomerProfileUpdated::dispatch($user, $changed);
        }

        return $profile;
    }

    /**
     * Change the authenticated user's password after verifying the current one.
     *
     * @throws ValidationException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($newPassword)])->save();
    }
}
