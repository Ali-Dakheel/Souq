<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Events\OrderFulfilled;
use App\Modules\Orders\Events\ShipmentCreated;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Shipment;
use App\Modules\Orders\Models\ShipmentItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    /**
     * Create a shipment for an order.
     *
     * @param  array<int, array{order_item_id: int, quantity_shipped: int}>  $items
     *
     * @throws ValidationException
     */
    public function createShipment(
        Order $order,
        array $items,
        ?string $carrier = null,
        ?string $trackingNumber = null,
        ?string $notes = null,
        ?int $createdBy = null,
    ): Shipment {
        if (! in_array($order->order_status, ['paid', 'pending_collection', 'shipped'], true)) {
            throw ValidationException::withMessages([
                'order' => ['Order must be in paid, pending_collection, or shipped status to create a shipment.'],
            ]);
        }

        $shipment = DB::transaction(function () use ($order, $items, $carrier, $trackingNumber, $notes, $createdBy): Shipment {
            // Load order items with shipmentItems eager-loaded for computed attributes
            $order->load(['items.shipmentItems']);

            // Scope fetch to this order's items via DB query — prevents cross-order item injection
            $submittedIds = array_column($items, 'order_item_id');
            $orderItems = $order->items()->with('shipmentItems')->whereIn('id', $submittedIds)->get()->keyBy('id');

            $errors = [];
            foreach ($items as $item) {
                $orderItem = $orderItems->get($item['order_item_id']);

                if (! $orderItem) {
                    $errors["items.{$item['order_item_id']}"] = ["Order item {$item['order_item_id']} does not belong to this order."];

                    continue;
                }

                if ($item['quantity_shipped'] <= 0) {
                    $errors["items.{$item['order_item_id']}"] = ['Quantity shipped must be greater than zero.'];

                    continue;
                }

                if ($item['quantity_shipped'] > $orderItem->quantity_to_ship) {
                    $errors["items.{$item['order_item_id']}"] = [
                        "Cannot ship {$item['quantity_shipped']} units of '{$orderItem->sku}' — only {$orderItem->quantity_to_ship} remaining.",
                    ];
                }
            }

            if (! empty($errors)) {
                throw ValidationException::withMessages($errors);
            }

            $shipmentCount = $order->shipments()->lockForUpdate()->count();
            $shipmentNumber = 'SHP-'.date('Y').'-'.$order->id.'-'.($shipmentCount + 1);

            $shipment = Shipment::create([
                'order_id' => $order->id,
                'shipment_number' => $shipmentNumber,
                'carrier' => $carrier,
                'tracking_number' => $trackingNumber,
                'status' => 'pending',
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);

            foreach ($items as $item) {
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'order_item_id' => $item['order_item_id'],
                    'quantity_shipped' => $item['quantity_shipped'],
                ]);
            }

            // Reload items with fresh shipment data to check totals
            $order->load(['items.shipmentItems']);
            $allShipped = $order->items->every(fn ($orderItem) => $orderItem->quantity_to_ship === 0);

            if ($allShipped) {
                $order->update(['order_status' => 'shipped']);
            }

            return $shipment->load('items');
        });

        // Dispatch AFTER transaction commits — prevents emails firing on rollback
        ShipmentCreated::dispatch($shipment, $shipment->order);

        return $shipment;
    }

    /**
     * Mark a shipment as shipped.
     */
    public function markShipped(Shipment $shipment, ?string $trackingNumber = null): Shipment
    {
        $updates = ['status' => 'shipped', 'shipped_at' => now()];

        if ($trackingNumber !== null) {
            $updates['tracking_number'] = $trackingNumber;
        }

        $shipment->update($updates);

        return $shipment->fresh();
    }

    /**
     * Mark a shipment as delivered.
     * If ALL shipments for the order are delivered, fires OrderFulfilled.
     */
    public function markDelivered(Shipment $shipment): Shipment
    {
        $shipment->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $order = $shipment->order;

        $allDelivered = $order->shipments()
            ->where('status', '!=', 'delivered')
            ->doesntExist();

        if ($allDelivered) {
            // Set order status to 'delivered' (final state for shipped orders).
            // 'fulfilled' is retained for non-shipment fulfillment paths.
            // OrderFulfilled event fires here to trigger the delivery notification email.
            $order->update(['order_status' => 'delivered']);
            OrderFulfilled::dispatch($order);
        }

        return $shipment->fresh();
    }

    /**
     * Get all shipments for an order with items loaded.
     */
    public function getShipmentsForOrder(Order $order): Collection
    {
        return $order->shipments()->with('items')->get();
    }
}
