<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Customers\Requests\ChangePasswordRequest;
use App\Modules\Customers\Requests\UpdateProfileRequest;
use App\Modules\Customers\Resources\CustomerProfileResource;
use App\Modules\Customers\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new CustomerProfileResource($this->profileService->getProfile($user)))
            ->response()
            ->setStatusCode(200);
    }

    public function update(UpdateProfileRequest $request): CustomerProfileResource
    {
        /** @var User $user */
        $user = $request->user();

        return new CustomerProfileResource(
            $this->profileService->updateProfile($user, $request->validated())
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->profileService->changePassword(
            $user,
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
