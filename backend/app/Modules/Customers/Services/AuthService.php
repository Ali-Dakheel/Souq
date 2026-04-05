<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
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
     * If a guest_session_id is provided, CartMerged is dispatched so the Cart
     * module can merge the guest cart into the new user's cart.
     *
     * @param  array{name: string, email: string, password: string, phone?: string, preferred_locale?: string, marketing_consent?: bool, guest_session_id?: string}  $data
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

        $guestSessionId = $data['guest_session_id'] ?? null;
        if ($guestSessionId) {
            $this->dispatchCartMergeIfNeeded($user, $guestSessionId);
        }

        return $user;
    }

    /**
     * Authenticate a customer and issue a Sanctum API token.
     *
     * @return array{user: User, token: string}
     * @throws AuthenticationException
     */
    public function login(string $email, string $password, ?string $guestSessionId = null): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $user->load('profile');

        $token = $user->createToken('api-token')->plainTextToken;

        if ($guestSessionId) {
            $this->dispatchCartMergeIfNeeded($user, $guestSessionId);
        }

        return ['user' => $user, 'token' => $token];
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

    /**
     * If a guest cart exists for the given session, resolve CartService and
     * perform the merge. CartService::mergeCart() fires CartMerged internally.
     * Using app() rather than constructor injection avoids a circular
     * Customers → Cart dependency at the provider level.
     */
    private function dispatchCartMergeIfNeeded(User $user, string $guestSessionId): void
    {
        $guestCart = Cart::where('session_id', $guestSessionId)
            ->whereNull('user_id')
            ->first();

        if (! $guestCart) {
            return;
        }

        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $userCart = $cartService->getOrCreateCart($user->id, null);

        $cartService->mergeCart($guestCart, $userCart, $user);
    }
}
