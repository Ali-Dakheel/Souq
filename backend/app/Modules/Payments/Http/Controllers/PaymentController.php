<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Exceptions\TapApiException;
use App\Modules\Payments\Http\Requests\CreateChargeRequest;
use App\Modules\Payments\Http\Requests\RefundRequestRequest;
use App\Modules\Payments\Http\Resources\RefundResource;
use App\Modules\Payments\Http\Resources\TapTransactionResource;
use App\Modules\Payments\Models\TapTransaction;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\RefundService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly RefundService $refundService,
    ) {}

    /**
     * POST /api/v1/payments/charge
     * Create a Tap charge for an order and return the redirect URL.
     */
    public function charge(CreateChargeRequest $request): JsonResponse
    {
        $user = $request->user();
        $order = Order::findOrFail($request->validated('order_id'));

        // Validate ownership
        if ($order->user_id !== $user->id) {
            abort(403, 'This order does not belong to your account.');
        }

        // Validate order is in a payable state
        if (! in_array($order->order_status, ['pending', 'failed'], true)) {
            throw ValidationException::withMessages([
                'order' => ["Order cannot be paid (status: {$order->order_status})."],
            ]);
        }

        try {
            $transaction = $this->paymentService->initiatePayment($order, $user);
        } catch (TapApiException $e) {
            throw ValidationException::withMessages([
                'payment' => ['Payment service error: ' . $e->getMessage()],
            ]);
        }

        return response()->json([
            'data'         => new TapTransactionResource($transaction),
            'redirect_url' => $transaction->redirect_url,
        ], 201);
    }

    /**
     * GET /api/v1/payments/result?tap_id=chg_xxx
     * Handle the redirect return from Tap — called with charge ID.
     * Requires authentication (user was authenticated when the charge was created).
     */
    public function result(Request $request): JsonResponse
    {
        $tapChargeId = $request->query('tap_id');

        if (! $tapChargeId) {
            throw ValidationException::withMessages([
                'tap_id' => ['Missing tap_id parameter.'],
            ]);
        }

        try {
            $transaction = $this->paymentService->handleChargeResult($tapChargeId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw ValidationException::withMessages([
                'tap_id' => ['Payment not found.'],
            ]);
        } catch (TapApiException $e) {
            // Tap API unreachable — return current known status
            $transaction = $this->paymentService->getByChargeId($tapChargeId);
            if (! $transaction) {
                throw ValidationException::withMessages([
                    'tap_id' => ['Payment not found.'],
                ]);
            }
        }

        // Ownership check — prevents enumerating other customers' charge results
        if ($transaction->order->user_id !== $request->user()->id) {
            abort(403, 'This payment does not belong to your account.');
        }

        return response()->json([
            'data' => new TapTransactionResource($transaction),
        ]);
    }

    /**
     * GET /api/v1/payments/order/{orderId}
     * Get the latest payment transaction for an order.
     */
    public function orderPayment(Request $request, int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        // Validate ownership
        if ($order->user_id !== $request->user()->id) {
            abort(403, 'This order does not belong to your account.');
        }

        $transaction = $this->paymentService->getLatestTransaction($order);

        if (! $transaction) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => new TapTransactionResource($transaction),
        ]);
    }

    /**
     * POST /api/v1/payments/{transaction}/refund
     * Customer requests a refund for a payment.
     */
    public function requestRefund(RefundRequestRequest $request, TapTransaction $transaction): JsonResponse
    {
        $user = $request->user();
        $order = $transaction->order;

        // Validate ownership
        if ($order->user_id !== $user->id) {
            abort(403, 'This order does not belong to your account.');
        }

        $refund = $this->refundService->requestRefund(
            transaction: $transaction,
            customer: $user,
            reason: $request->validated('reason'),
            customerNotes: $request->validated('notes'),
        );

        return (new RefundResource($refund))
            ->response()
            ->setStatusCode(201);
    }
}
