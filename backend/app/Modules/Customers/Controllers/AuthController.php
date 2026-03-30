<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Customers\Requests\ForgotPasswordRequest;
use App\Modules\Customers\Requests\LoginRequest;
use App\Modules\Customers\Requests\RegisterRequest;
use App\Modules\Customers\Requests\ResetPasswordRequest;
use App\Modules\Customers\Resources\UserResource;
use App\Modules\Customers\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return (new UserResource($user))
            ->additional(['message' => 'Registration successful.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @throws AuthenticationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('guest_session_id')->toString() ?: null,
        );

        return (new UserResource($user))
            ->additional(['message' => 'Login successful.'])
            ->response();
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout();

        return response()->json(['message' => 'Logout successful.']);
    }

    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('profile');

        return new UserResource($user);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->requestPasswordReset(
            $request->string('email')->toString()
        );

        return response()->json(['message' => 'If that email is registered, a reset link has been sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json(['message' => 'Password reset successful.']);
    }
}
