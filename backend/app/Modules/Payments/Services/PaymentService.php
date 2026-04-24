<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Events\PaymentCaptured;
use App\Modules\Payments\Events\PaymentFailed;
use App\Modules\Payments\Exceptions\TapApiException;
use App\Modules\Payments\Jobs\ReleaseInventoryReservationJob;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private readonly TapApiService $tapApi,
    ) {}

    /**
     * Initiate a Tap charge for an order, returning the transaction with a redirect URL.
     *
     * @throws TapApiException
     */
    public function initiatePayment(Order $order, ?User $user): TapTransaction
    {
        // Determine attempt number
        $attemptNumber = TapTransaction::where('order_id', $order->id)->count() + 1;

        // Create Tap customer if registered user without tap_customer_id
        if ($user && ! $user->tap_customer_id) {
            $this->createTapCustomer($user);
        }

        // Build the charge payload per Tap API v2 docs
        $payload = $this->buildChargePayload($order, $user, $attemptNumber);

        // Call Tap API — fail fast on error
        $tapResponse = $this->tapApi->createCharge($payload);

        // Store the transaction record
        $transaction = TapTransaction::create([
            'order_id' => $order->id,
            'attempt_number' => $attemptNumber,
            'tap_charge_id' => $tapResponse['id'],
            'amount_fils' => $order->total_fils,
            'currency' => 'BHD',
            'status' => 'initiated',
            'tap_response' => $tapResponse,
            'redirect_url' => $tapResponse['transaction']['url'] ?? null,
        ]);

        // Transition order to initiated if still pending
        if ($order->order_status === 'pending') {
            $order->update(['order_status' => 'initiated']);
            app(OrderService::class)
                ->recordStatusChange($order, 'initiated', 'system', 'Payment initiated.', 'pending');
        }

        return $transaction;
    }

    /**
     * Idempotent charge result handler — called by BOTH redirect and webhook.
     * Uses pessimistic lock on the TapTransaction row to prevent race conditions.
     */
    public function handleChargeResult(string $tapChargeId): TapTransaction
    {
        ['transaction' => $transaction, 'toDispatch' => $toDispatch] = DB::transaction(function () use ($tapChargeId) {
            $transaction = TapTransaction::where('tap_charge_id', $tapChargeId)
                ->lockForUpdate()
                ->firstOrFail();

            // Already in a terminal state — skip
            if (in_array($transaction->status, ['captured', 'cancelled'], true)) {
                return ['transaction' => $transaction, 'toDispatch' => null];
            }

            // Fetch latest status from Tap
            $tapCharge = $this->tapApi->retrieveCharge($tapChargeId);

            $transaction->tap_response = $tapCharge;

            $tapStatus = strtoupper($tapCharge['status'] ?? '');

            $toDispatch = match ($tapStatus) {
                'CAPTURED' => $this->handleCaptured($transaction),
                'FAILED' => $this->handleFailed($transaction, $tapCharge),
                'VOID' => $this->handleVoid($transaction),
                default => null, // INITIATED or other — no state change yet
            };

            $transaction->save();

            return ['transaction' => $transaction, 'toDispatch' => $toDispatch];
        });

        // --- Fire events outside the transaction so queued listeners don't run on rollback ---
        if ($toDispatch !== null) {
            match ($toDispatch['event']) {
                'captured' => PaymentCaptured::dispatch($toDispatch['order']),
                'failed' => (function () use ($toDispatch): void {
                    PaymentFailed::dispatch($toDispatch['order']);
                    ReleaseInventoryReservationJob::dispatch($toDispatch['order']->id)
                        ->delay(now()->addMinutes(30));
                })(),
                default => null,
            };
        }

        return $transaction;
    }

    /**
     * Get the latest payment for an order.
     */
    public function getLatestTransaction(Order $order): ?TapTransaction
    {
        return TapTransaction::where('order_id', $order->id)
            ->orderByDesc('attempt_number')
            ->first();
    }

    /**
     * Get transaction by tap_charge_id.
     */
    public function getByChargeId(string $tapChargeId): ?TapTransaction
    {
        return TapTransaction::where('tap_charge_id', $tapChargeId)->first();
    }

    // -----------------------------------------------------------------------
    // Private handlers
    // -----------------------------------------------------------------------

    /** @return array{event: string, order: Order} */
    private function handleCaptured(TapTransaction $transaction): array
    {
        $transaction->status = 'captured';
        $transaction->webhook_received_at = now();

        $order = $transaction->order;

        Log::info('Payment captured', [
            'order_id' => $order->id,
            'tap_charge_id' => $transaction->tap_charge_id,
        ]);

        return ['event' => 'captured', 'order' => $order];
    }

    /**
     * @param  array<string, mixed>  $tapCharge
     * @return array{event: string, order: Order}
     */
    private function handleFailed(TapTransaction $transaction, array $tapCharge): array
    {
        $transaction->status = 'failed';
        $transaction->failure_reason = $tapCharge['response']['message'] ?? null;
        $transaction->webhook_received_at = now();

        $order = $transaction->order;

        Log::info('Payment failed', [
            'order_id' => $order->id,
            'tap_charge_id' => $transaction->tap_charge_id,
            'reason' => $transaction->failure_reason,
        ]);

        return ['event' => 'failed', 'order' => $order];
    }

    private function handleVoid(TapTransaction $transaction): null
    {
        $transaction->status = 'cancelled';
        $transaction->webhook_received_at = now();

        Log::info('Payment voided', [
            'order_id' => $transaction->order_id,
            'tap_charge_id' => $transaction->tap_charge_id,
        ]);

        return null;
    }

    private function createTapCustomer(User $user): void
    {
        try {
            $response = $this->tapApi->createCustomer([
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => [
                    'country_code' => '973',
                    'number' => $user->profile->phone ?? '',
                ],
            ]);

            $user->update(['tap_customer_id' => $response['id']]);
        } catch (TapApiException $e) {
            // Non-blocking — customer creation failure should not prevent payment
            Log::warning('Failed to create Tap customer', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function buildChargePayload(Order $order, ?User $user, int $attemptNumber): array
    {
        $amountDecimal = number_format($order->total_fils / 1000, 3, '.', '');
        $redirectUrl = config('app.frontend_url').'/checkout/result';

        $payload = [
            'amount' => (float) $amountDecimal,
            'currency' => 'BHD',
            'threeDSecure' => true,
            'save_card' => false,
            'description' => "Order #{$order->order_number}",
            'metadata' => [
                'order_id' => (string) $order->id,
                'customer_id' => $user ? (string) $user->id : null,
                'attempt_number' => $attemptNumber,
                'environment' => app()->environment(),
            ],
            'reference' => [
                'transaction' => $order->order_number,
                'order' => (string) $order->id,
            ],
            'receipt' => [
                'email' => true,
                'sms' => false,
            ],
            'customer' => $this->buildCustomerPayload($order, $user),
            'source' => ['id' => 'src_all'],
            'redirect' => ['url' => $redirectUrl],
        ];

        return $payload;
    }

    /** @return array<string, mixed> */
    private function buildCustomerPayload(Order $order, ?User $user): array
    {
        if ($user) {
            $profile = $user->profile;

            return [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => [
                    'country_code' => '973',
                    'number' => $profile->phone ?? '',
                ],
            ];
        }

        // Guest checkout
        return [
            'first_name' => 'Guest',
            'email' => $order->guest_email,
        ];
    }
}
