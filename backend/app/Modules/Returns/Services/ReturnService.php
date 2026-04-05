<?php

declare(strict_types=1);

namespace App\Modules\Returns\Services;

use App\Models\User;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Orders\Models\Order;
use App\Modules\Returns\Events\ReturnApproved;
use App\Modules\Returns\Events\ReturnCompleted;
use App\Modules\Returns\Events\ReturnRejected;
use App\Modules\Returns\Events\ReturnRequested;
use App\Modules\Returns\Models\ReturnRequest;
use App\Modules\Returns\Models\ReturnRequestItem;
use App\Modules\Settings\Models\StoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    private const RETURN_WINDOW_DAYS = 14;

    public function createRequest(
        Order $order,
        User $user,
        string $reason,
        ?string $notes,
        array $items,
    ): ReturnRequest {
        if ($order->order_status !== 'delivered') {
            throw ValidationException::withMessages([
                'order' => ['Only delivered orders can be returned.'],
            ]);
        }

        if ($order->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'order' => ['You do not own this order.'],
            ]);
        }

        if ($order->created_at->diffInDays(now()) > self::RETURN_WINDOW_DAYS) {
            throw ValidationException::withMessages([
                'order' => ['Return window of '.self::RETURN_WINDOW_DAYS.' days has passed.'],
            ]);
        }

        $returnRequest = DB::transaction(function () use ($order, $user, $reason, $notes, $items): ReturnRequest {
            $rmaNumber = $this->getNextRmaNumber();

            $returnRequest = ReturnRequest::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'request_number' => $rmaNumber,
                'status' => 'pending',
                'reason' => $reason,
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                ReturnRequestItem::create([
                    'return_request_id' => $returnRequest->id,
                    'order_item_id' => $item['order_item_id'],
                    'quantity_returned' => $item['quantity_returned'],
                    'condition' => $item['condition'],
                ]);
            }

            return $returnRequest;
        });

        ReturnRequested::dispatch($returnRequest);

        return $returnRequest->load('items');
    }

    public function approveReturn(
        ReturnRequest $returnRequest,
        User $admin,
        string $resolution,
        int $resolutionAmountFils,
        ?string $adminNotes = null,
    ): ReturnRequest {
        if ($returnRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending returns can be approved.'],
            ]);
        }

        $returnRequest->update([
            'status' => 'approved',
            'resolution' => $resolution,
            'resolution_amount_fils' => $resolutionAmountFils,
            'admin_notes' => $adminNotes,
        ]);

        ReturnApproved::dispatch($returnRequest);

        return $returnRequest;
    }

    public function rejectReturn(
        ReturnRequest $returnRequest,
        User $admin,
        string $adminNotes,
    ): ReturnRequest {
        if ($returnRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending returns can be rejected.'],
            ]);
        }

        $returnRequest->update([
            'status' => 'rejected',
            'admin_notes' => $adminNotes,
        ]);

        ReturnRejected::dispatch($returnRequest);

        return $returnRequest;
    }

    public function completeReturn(ReturnRequest $returnRequest): ReturnRequest
    {
        if ($returnRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => ['Only approved returns can be completed.'],
            ]);
        }

        DB::transaction(function () use ($returnRequest): void {
            foreach ($returnRequest->items as $returnItem) {
                $orderItem = $returnItem->orderItem;
                InventoryItem::where('variant_id', $orderItem->variant_id)
                    ->lockForUpdate()
                    ->increment('quantity_available', $returnItem->quantity_returned);
            }

            $returnRequest->update(['status' => 'completed']);
        });

        ReturnCompleted::dispatch($returnRequest);

        return $returnRequest;
    }

    private function getNextRmaNumber(): string
    {
        $sequence = DB::transaction(function (): int {
            StoreSetting::firstOrCreate(
                ['key' => 'last_rma_sequence'],
                ['value' => '0', 'group' => 'commerce']
            );

            $row = StoreSetting::where('key', 'last_rma_sequence')
                ->lockForUpdate()
                ->first();

            $next = (int) $row->value + 1;
            $row->value = (string) $next;
            $row->save();

            return $next;
        });

        return sprintf('RMA-%d-%06d', now()->year, $sequence);
    }
}
