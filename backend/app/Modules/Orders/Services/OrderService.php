<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderFulfilled;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Jobs\GenerateInvoiceJob;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderStatusHistory;
use App\Modules\Payments\Events\CODCollected;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    /**
     * Atomically create an order from a cart.
     *
     * Flow:
     *   1. Pre-flight validation (outside transaction — fast fail)
     *   2. DB transaction:
     *      a. Lock inventory rows — re-check stock with lockForUpdate
     *      b. Create Order (temp order_number)
     *      c. Update order_number to ORD-YYYY-{id}
     *      d. Create OrderItem snapshots
     *      e. Record initial status history entry
     *      f. Fire OrderPlaced event
     *   3. Return order with relationships loaded
     *
     * NOTE: Cart is NOT cleared here. It is cleared by ClearCartOnPaymentCaptured
     * listener when the Payments module fires PaymentCaptured.
     *
     * @throws ValidationException
     */
    public function checkout(
        Cart $cart,
        ?int $userId,
        ?string $guestEmail,
        int $shippingAddressId,
        int $billingAddressId,
        string $paymentMethod,
        ?string $notes = null,
        string $locale = 'ar',
    ): Order {
        $cart->loadMissing('items.variant.product.category', 'items.variant.inventory');

        // --- Pre-flight validation (outside transaction) ---
        $validation = $this->cartService->validateForCheckout($cart);
        if (! $validation['valid']) {
            throw ValidationException::withMessages(['cart' => $validation['errors']]);
        }

        $shippingAddress = CustomerAddress::findOrFail($shippingAddressId);
        $billingAddress = CustomerAddress::findOrFail($billingAddressId);

        $totals = $this->cartService->calculateTotals($cart);

        return DB::transaction(function () use (
            $cart, $userId, $guestEmail, $paymentMethod, $notes, $locale,
            $shippingAddress, $billingAddress, $totals,
        ) {
            // --- Re-check stock inside transaction with row-level locks ---
            foreach ($cart->items as $item) {
                if ($item->variant_id === null) {
                    throw ValidationException::withMessages(['cart' => ['One or more items are no longer available.']]);
                }

                $inventory = InventoryItem::where('variant_id', $item->variant_id)
                    ->lockForUpdate()
                    ->first();

                $available = $inventory?->quantity_on_sale ?? 0;
                if ($available < $item->quantity) {
                    $name = $item->variant->product->name['en'] ?? 'An item';
                    throw ValidationException::withMessages([
                        'cart' => [$available === 0
                            ? "{$name} is out of stock."
                            : "Only {$available} of {$name} available."],
                    ]);
                }
            }

            $isCod = $paymentMethod === 'cod';
            $initialStatus = $isCod ? 'pending_collection' : 'pending';

            // --- Create order ---
            $order = Order::create([
                'order_number' => 'TMP-'.Str::uuid(),
                'user_id' => $userId,
                'guest_email' => $guestEmail,
                'order_status' => $initialStatus,
                'subtotal_fils' => $totals['subtotal_fils'],
                'coupon_discount_fils' => $totals['discount_fils'],
                'coupon_code' => $cart->coupon_code,
                'vat_fils' => $totals['vat_fils'],
                'delivery_fee_fils' => 0,
                'total_fils' => $totals['total_fils'],
                'payment_method' => $paymentMethod,
                'shipping_address_id' => $shippingAddress->id,
                'shipping_address_snapshot' => $this->snapshotAddress($shippingAddress),
                'billing_address_id' => $billingAddress->id,
                'billing_address_snapshot' => $this->snapshotAddress($billingAddress),
                'notes' => $notes,
                'locale' => $locale,
            ]);

            // Assign sequential order number using the auto-increment id
            $order->order_number = sprintf('ORD-%d-%05d', now()->year, $order->id);
            $order->saveQuietly();

            // --- Snapshot cart items ---
            $eventItems = [];
            foreach ($cart->items as $item) {
                $variant = $item->variant;
                $product = $variant->product;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'sku' => $variant->sku,
                    'product_name' => $product->name,
                    'variant_attributes' => $variant->attributes,
                    'quantity' => $item->quantity,
                    'price_fils_per_unit' => $item->price_fils_snapshot,
                    'total_fils' => $item->line_total_fils,
                ]);

                $eventItems[] = [
                    'variant_id' => $variant->id,
                    'quantity' => $item->quantity,
                ];
            }

            // --- Initial status history entry ---
            if ($isCod) {
                $this->recordStatusChange($order, 'pending_collection', 'system', 'COD order — awaiting cash collection.');
                GenerateInvoiceJob::dispatch($order->id);
            } else {
                $this->recordStatusChange($order, 'pending', 'system', 'Order placed.');
            }

            // --- Fire event (Inventory reserves stock, Notifications sends email) ---
            OrderPlaced::dispatch($order, $eventItems);

            return $order->load(['items', 'statusHistory', 'shippingAddress', 'billingAddress']);
        });
    }

    /**
     * Mark a COD order as collected (payment received in cash).
     * Called by admin via Filament action.
     *
     * @throws \InvalidArgumentException if not COD or wrong status
     */
    public function markCodCollected(Order $order, ?string $note = null): Order
    {
        $order->refresh(); // ensure we're working with current DB state

        if (! $order->isCod()) {
            throw new \InvalidArgumentException(
                "Order {$order->order_number} is not a COD order."
            );
        }

        if ($order->order_status !== 'pending_collection') {
            throw new \InvalidArgumentException(
                "Order {$order->order_number} cannot be marked collected (status: {$order->order_status})."
            );
        }

        $oldStatus = $order->order_status; // capture BEFORE update

        DB::transaction(function () use ($order, $oldStatus, $note) {
            $order->update([
                'order_status' => 'collected',
                'paid_at' => now(),
            ]);

            $this->recordStatusChange(
                $order,
                'collected',
                'admin',
                $note ?? 'COD payment collected.',
                $oldStatus,
            );
        });

        CODCollected::dispatch($order->fresh());

        return $order->fresh()->load(['items', 'statusHistory', 'shippingAddress', 'billingAddress']);
    }

    /**
     * Cancel an order. Only pending/initiated orders may be cancelled.
     *
     * @throws \InvalidArgumentException
     */
    public function cancelOrder(
        Order $order,
        ?string $reason = null,
        string $changedBy = 'customer',
    ): Order {
        if (! $order->isCancellable()) {
            throw new \InvalidArgumentException(
                "Order {$order->order_number} cannot be cancelled (status: {$order->order_status})."
            );
        }

        $order->loadMissing('items');

        $oldStatus = $order->order_status;

        $order->update([
            'order_status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->recordStatusChange($order, 'cancelled', $changedBy, $reason, $oldStatus);

        $eventItems = $order->items
            ->filter(fn ($i) => $i->variant_id !== null)
            ->map(fn ($i) => ['variant_id' => $i->variant_id, 'quantity' => $i->quantity])
            ->values()
            ->toArray();

        OrderCancelled::dispatch($order, $eventItems, $reason);

        return $order->refresh()->load(['items', 'statusHistory', 'shippingAddress', 'billingAddress']);
    }

    /**
     * Mark an order as fulfilled with a tracking number and fire OrderFulfilled event.
     */
    public function fulfillOrder(Order $order, ?string $trackingNumber): void
    {
        $oldStatus = $order->order_status;

        $order->update([
            'order_status' => 'fulfilled',
            'tracking_number' => $trackingNumber,
            'fulfilled_at' => Carbon::now(),
        ]);

        $this->recordStatusChange($order, 'fulfilled', 'admin', 'Fulfilled by admin', $oldStatus);

        OrderFulfilled::dispatch($order);
    }

    /**
     * Override an order's status with an optional note for audit trail.
     */
    public function overrideOrderStatus(Order $order, string $newStatus, string $note): void
    {
        $oldStatus = $order->order_status;

        $order->update(['order_status' => $newStatus]);

        $this->recordStatusChange($order, $newStatus, 'admin', $note, $oldStatus);
    }

    /**
     * Cancel an order as an admin user. Only certain statuses may be cancelled.
     *
     * @throws \InvalidArgumentException
     */
    public function cancelOrderAsAdmin(Order $order, string $reason = 'Cancelled by admin'): void
    {
        if (! in_array($order->order_status, ['pending', 'initiated', 'processing', 'paid'], true)) {
            throw new \InvalidArgumentException("Order cannot be cancelled in status: {$order->order_status}");
        }

        $order->loadMissing('items');

        $oldStatus = $order->order_status;

        $order->update([
            'order_status' => 'cancelled',
            'cancelled_at' => Carbon::now(),
        ]);

        $this->recordStatusChange($order, 'cancelled', 'admin', $reason, $oldStatus);

        $eventItems = $order->items
            ->filter(fn ($i) => $i->variant_id !== null)
            ->map(fn ($i) => ['variant_id' => $i->variant_id, 'quantity' => $i->quantity])
            ->values()
            ->toArray();

        OrderCancelled::dispatch($order, $eventItems, $reason);
    }

    /**
     * Append an entry to the immutable order_status_history log.
     */
    public function recordStatusChange(
        Order $order,
        string $newStatus,
        ?string $changedBy = null,
        ?string $reason = null,
        ?string $oldStatus = null,
    ): void {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'old_status' => $oldStatus ?? $order->getOriginal('order_status') ?? $order->order_status,
            'new_status' => $newStatus,
            'changed_by' => $changedBy,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Retrieve an order by its order number.
     * If $userId is provided, throws 404 when the order belongs to a different user.
     */
    public function getOrderByNumber(string $orderNumber, ?int $userId = null): Order
    {
        $order = Order::with(['items', 'statusHistory', 'shippingAddress', 'billingAddress'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        if ($userId !== null && $order->user_id !== $userId) {
            abort(403, 'This order does not belong to your account.');
        }

        return $order;
    }

    /**
     * Paginated order list for an authenticated user, with optional status filter.
     */
    public function getUserOrders(
        int $userId,
        int $page = 1,
        int $perPage = 15,
        ?string $status = null,
    ): LengthAwarePaginator {
        $query = Order::where('user_id', $userId)
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('order_status', $status);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function snapshotAddress(CustomerAddress $address): array
    {
        return [
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'governorate' => $address->governorate,
            'district' => $address->district,
            'street_address' => $address->street_address,
            'building_number' => $address->building_number,
            'apartment_number' => $address->apartment_number,
            'postal_code' => $address->postal_code,
            'delivery_instructions' => $address->delivery_instructions,
        ];
    }
}
