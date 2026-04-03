<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Customers\Models\CustomerGroup;
use App\Modules\Customers\Requests\StoreCustomerGroupRequest;
use App\Modules\Customers\Requests\StoreGroupPriceRequest;
use App\Modules\Customers\Requests\UpdateCustomerGroupRequest;
use App\Modules\Customers\Resources\CustomerGroupResource;
use App\Modules\Customers\Resources\VariantGroupPriceResource;
use App\Modules\Customers\Services\CustomerGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class CustomerGroupController extends Controller
{
    public function __construct(
        private readonly CustomerGroupService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return CustomerGroupResource::collection($this->service->listGroups());
    }

    public function store(StoreCustomerGroupRequest $request): JsonResponse
    {
        $group = $this->service->createGroup($request->validated());

        return (new CustomerGroupResource($group))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CustomerGroup $group): CustomerGroupResource
    {
        return new CustomerGroupResource($group);
    }

    public function update(UpdateCustomerGroupRequest $request, CustomerGroup $group): CustomerGroupResource
    {
        $group = $this->service->updateGroup($group, $request->validated());

        return new CustomerGroupResource($group);
    }

    public function destroy(CustomerGroup $group): JsonResponse
    {
        try {
            $this->service->deleteGroup($group);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['group' => [$e->getMessage()]]);
        }

        return response()->json(null, 204);
    }

    public function setPrice(StoreGroupPriceRequest $request, CustomerGroup $group): JsonResponse
    {
        $validatedData = $request->validated();
        $variant = Variant::findOrFail($validatedData['variant_id']);

        $price = $this->service->setGroupPrice($variant, $group, $validatedData);

        return (new VariantGroupPriceResource($price))
            ->response()
            ->setStatusCode(201);
    }

    public function removePrice(CustomerGroup $group, Variant $variant): JsonResponse
    {
        $this->service->removeGroupPrice($variant, $group);

        return response()->json(null, 204);
    }
}
