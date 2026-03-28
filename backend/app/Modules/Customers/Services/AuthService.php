<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\User;
use App\Modules\Customers\Events\CustomerRegistered;
use App\Modules\Customers\Events\PasswordReset;
use App\Modules\Customers\Events\PasswordResetRequested;
use App\Modules\Customers\Models\CustomerProfile;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\PasswordReset as LaravelPasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Register a new customer, create their profile, and fire CustomerRegistered.
     *
     * @param  array{name: string, email: string, password: string, phone?: string, preferred_locale?: string, marketing_consent?: bool}  $data
     */
    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        CustomerProfile::create([
            'user_id' => $user->id,
            'phone' => $data['phone'] ?? null,
            'preferred_locale' => $data['preferred_locale'] ?? 'ar',
            'marketing_consent' => $data['marketing_consent'] ?? false,
        ]);

        $user->load('profile');

        CustomerRegistered::dispatch($user);

        return $user;
    }

    /**
     * Authenticate a customer via session guard.
     *
     * @throws AuthenticationException
     */
    public function login(string $email, string $password): User
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            throw new AuthenticationException('Invalid credentials.');
        }

        /** @var User $user */
        $user = Auth::user();
        $user->load('profile');

        return $user;
    }

    /**
     * Revoke the current session.
     */
    public function logout(): void
    {
        Auth::guard('web')->logout();
    }

    /**
     * Generate a password reset token and dispatch PasswordResetRequested.
     * Always returns void so callers cannot determine whether the email exists.
     */
    public function requestPasswordReset(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return;
        }

        $token = Password::createToken($user);

        PasswordResetRequested::dispatch($email, $token);
    }

    /**
     * Validate the reset token and update the password.
     *
     * @throws ValidationException
     */
    public function resetPassword(string $email, string $token, string $password): void
    {
        $status = Password::reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $user, string $newPassword) {
                $user->forceFill(['password' => Hash::make($newPassword)])->save();

                event(new LaravelPasswordReset($user));

                PasswordReset::dispatch($user);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    /**
     * Verify a plain-text password against the user's stored hash.
     */
    public function verifyPassword(User $user, string $plainPassword): bool
    {
        return Hash::check($plainPassword, $user->password);
    }
}
