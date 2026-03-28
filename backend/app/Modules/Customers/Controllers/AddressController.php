<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Customers\Requests\CreateAddressRequest;
use App\Modules\Customers\Requests\UpdateAddressRequest;
use App\Modules\Customers\Resources\CustomerAddressResource;
use App\Modules\Customers\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    public function __construct(
        private readonly AddressService $addressService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $type = $request->query('type');

        return CustomerAddressResource::collection(
            $this->addressService->listAddresses($user, is_string($type) ? $type : null)
        );
    }

    public function store(CreateAddressRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $address = $this->addressService->createAddress($user, $request->validated());

        return (new CustomerAddressResource($address))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAddressRequest $request, int $address): CustomerAddressResource
    {
        /** @var User $user */
        $user = $request->user();

        return new CustomerAddressResource(
            $this->addressService->updateAddress($user, $address, $request->validated())
        );
    }

    public function destroy(Request $request, int $address): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->addressService->deleteAddress($user, $address);

        return response()->json(null, 204);
    }

    public function setDefault(Request $request, int $address): CustomerAddressResource
    {
        /** @var User $user */
        $user = $request->user();

        return new CustomerAddressResource(
            $this->addressService->setDefaultAddress($user, $address)
        );
    }
}
