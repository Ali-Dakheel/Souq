# Bagisto Shipments Analysis

## Models

**Shipment** (`Webkul\Sales\Models\Shipment`)
- Relationships: `order()` (BelongsTo), `items()` (HasMany), `customer()` (MorphTo), `inventory_source()` (BelongsTo)
- Key columns: `order_id`, `customer_id`/`customer_type`, `order_address_id`, `inventory_source_id`, carrier info (carrier_code, carrier_title, track_number), `total_qty`, `total_weight`, `email_sent`
- No explicit status enum — status is nullable string

**ShipmentItem** (`Webkul\Sales\Models\ShipmentItem`)
- Relationships: `shipment()` (BelongsTo), `order_item()` (BelongsTo), `product()` (MorphTo), `children()` (HasMany)
- Key columns: `parent_id` (composite products), `order_item_id`, `product_id`/`product_type`, denormalized: `name`, `sku`, `price`, `base_price`, `total`, `base_total`, `weight`, `qty`, `additional` (JSON)

## Migrations (table names only)

- `shipments` — order_id, customer_id/type, order_address_id, inventory_source_id, carrier info, total_qty, total_weight
- `shipment_items` — shipment_id, order_item_id, product_id/type, denormalized pricing + weight, qty, additional JSON

## Services/Repositories

**ShipmentRepository::create()**
- Transactional; validates `qty_to_ship` per order item
- Fires `sales.shipment.save.before` / `sales.shipment.save.after` events
- Updates order item `qty_shipped` after creation
- Handles composite products: child qty = `(child_qty_ordered / parent_qty_ordered) * shipment_qty`

**ShipmentItemRepository::updateProductInventory()**
- Decrements both `ordered_inventories` (reserved) and `inventories` (physical) on shipment creation
- Enables safe cancellations via two-level tracking

## Events

- `sales.shipment.save.before` — pre-save hook
- `sales.shipment.save.after` → sends email, sets `email_sent=1`

## Useful Patterns

1. **Denormalization for auditability** — ShipmentItem copies name/SKU/pricing so shipments are frozen even if products change later
2. **Composite product handling** — `child_qty = (child_qty_ordered / parent_qty_ordered) * shipment_qty` — useful for Phase 3B bundle products
3. **Two-level inventory tracking** — `ordered_inventories` (reserved) vs `inventories` (physical) allows clean cancellation releases
4. **qty_to_ship validation** — cap shipment qty at unfulfilled order item qty to prevent over-shipping
5. **Email sent flag** — `email_sent` boolean prevents duplicate notification emails on retry

## Skip These Patterns

1. **Proxy pattern** (`ShipmentProxy::modelClass()`) — unnecessary for our modular monolith
2. **EAV attributes** — we use PostgreSQL JSONB directly
3. **Multi-channel/locale complexity** — single Bahrain base, no channel abstraction
4. **Configuration-driven email gates** — we use direct event listeners (Resend)
5. **Nullable string status** — use explicit PHP enum: Pending/Shipped/Delivered
6. **inventory_source** (multi-warehouse) — single fulfillment source for Phase 3; no multi-warehouse complexity
7. **Triple currency codes** — BHD fils only, no base/channel/order currency split
