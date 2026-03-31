<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Models\User;
use App\Modules\Payments\Events\OrderRefunded;
use App\Modules\Payments\Events\RefundApproved;
use App\Modules\Payments\Events\RefundRejected;
use App\Modules\Payments\Events\RefundRequested;
use App\Modules\Payments\Exceptions\TapApiException;
use App\Modules\Payments\Models\Refund;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(
        private readonly TapApiService $tapApi,
    ) {}

    /**
     * Customer submits a refund request. Status: pending → awaiting admin approval.
     *
     * @throws ValidationException
     */
    public function requestRefund(
        TapTransaction $transaction,
        User $customer,
        string $reason,
        ?string $customerNotes = null,
        ?int $amountFils = null,
    ): Refund {
        $order = $transaction->order;

        // Validate: payment must be captured
        if ($transaction->status !== 'captured') {
            throw ValidationException::withMessages([
                'payment' => ['Refund can only be requested for a captured payment.'],
            ]);
        }

        // Validate: no existing pending/approved refund for this transaction
        $existingRefund = Refund::where('tap_transaction_id', $transaction->id)
            ->whereIn('status', ['pending', 'approved', 'initiated', 'processing'])
            ->first();

        if ($existingRefund) {
            throw ValidationException::withMessages([
                'refund' => ['A refund request is already in progress for this payment.'],
            ]);
        }

        // Validate: amount does not exceed original charge
        $refundFils = $amountFils ?? $transaction->amount_fils;
        $totalRefunded = Refund::where('tap_transaction_id', $transaction->id)
            ->where('status', 'completed')
            ->sum('refund_amount_fils');

        if ($refundFils > ($transaction->amount_fils - $totalRefunded)) {
            throw ValidationException::withMessages([
                'amount' => ['Refund amount exceeds the remaining refundable balance.'],
            ]);
        }

        $refund = Refund::create([
            'order_id' => $order->id,
            'tap_transaction_id' => $transaction->id,
            'refund_amount_fils' => $refundFils,
            'refund_reason' => $reason,
            'customer_notes' => $customerNotes,
            'status' => 'pending',
            'requested_by_user_id' => $customer->id,
        ]);

        RefundRequested::dispatch($refund);

        return $refund;
    }

    /**
     * Admin approves a refund → calls Tap API → marks completed.
     *
     * @throws TapApiException
     * @throws ValidationException
     */
    public function approveRefund(Refund $refund, User $admin, ?string $adminNotes = null): Refund
    {
        if (! $refund->isPending()) {
            throw ValidationException::withMessages([
                'refund' => ['Only pending refunds can be approved.'],
            ]);
        }

        $refund->update([
            'status' => 'processing',
            'admin_notes' => $adminNotes,
            'processed_by_user_id' => $admin->id,
        ]);

        $transaction = $refund->tapTransaction;
        $amountDecimal = (float) number_format($refund->refund_amount_fils / 1000, 3, '.', '');

        // Map internal reason to Tap API reason values
        $tapReason = match ($refund->refund_reason) {
            'duplicate_charge' => 'duplicate',
            'payment_error' => 'fraudulent',
            default => 'requested_by_customer',
        };

        try {
            $tapResponse = $this->tapApi->createRefund([
                'charge_id' => $transaction->tap_charge_id,
                'amount' => $amountDecimal,
                'currency' => 'BHD',
                'reason' => $tapReason,
                'metadata' => [
                    'refund_id' => (string) $refund->id,
                    'order_id' => (string) $refund->order_id,
                ],
            ]);

            $refund->update([
                'status' => 'completed',
                'tap_refund_id' => $tapResponse['id'],
                'tap_response' => $tapResponse,
                'processed_at' => now(),
            ]);

            // Fire events
            OrderRefunded::dispatch($refund->order, $refund->refund_amount_fils);
            RefundApproved::dispatch($refund);

            return $refund;
        } catch (TapApiException $e) {
            $refund->update([
                'status' => 'failed',
                'refund_notes' => 'Tap API error: '.$e->getMessage(),
                'processed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Admin rejects a refund request.
     */
    public function rejectRefund(Refund $refund, User $admin, string $adminNotes): Refund
    {
        if (! $refund->isPending()) {
            throw ValidationException::withMessages([
                'refund' => ['Only pending refunds can be rejected.'],
            ]);
        }

        $refund->update([
            'status' => 'rejected',
            'admin_notes' => $adminNotes,
            'processed_by_user_id' => $admin->id,
            'processed_at' => now(),
        ]);

        RefundRejected::dispatch($refund);

        return $refund;
    }
}
