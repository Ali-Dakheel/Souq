<?php

declare(strict_types=1);

namespace App\Modules\Returns\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Returns\Requests\CreateReturnRequest;
use App\Modules\Returns\Resources\ReturnRequestResource;
use App\Modules\Returns\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ReturnRequestController extends Controller
{
    public function __construct(private readonly ReturnService $returnService) {}

    public function index(Request $request, string $orderNumber): AnonymousResourceCollection
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $returns = $order->returnRequests()->latest()->get();

        return ReturnRequestResource::collection($returns);
    }

    public function store(CreateReturnRequest $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        try {
            $returnRequest = $this->returnService->createRequest(
                order: $order,
                user: $request->user(),
                reason: $request->input('reason'),
                notes: $request->input('notes'),
                items: $request->input('items'),
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return (new ReturnRequestResource($returnRequest))
            ->response()
            ->setStatusCode(201);
    }
}
