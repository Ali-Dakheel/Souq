# Phase 3A Implementation Plan — Foundation Fixes

**Date:** 2026-04-03
**Status:** Ready for execution
**Prerequisite:** Phase 2 complete (94/94 tests green)

---

## Implementation Order

Build in this exact sequence — each item depends on the previous:

1. **3A.1 — Store Settings** (no dependencies, unblocks invoice number generation)
2. **3A.2 — Invoice Model** (depends on store_settings for sequence counter)
3. **3A.3 — Shipment Model** (depends on orders, independent of invoices)
4. **3A.4 — COD Payment Method** (depends on invoices + new order statuses)

---

## 3A.1 — Store Settings

### Database Migration

**File:** `database/migrations/2026_04_03_000001_create_store_settings_table.php`

**Table:** `store_settings`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `key` | string(100) | unique, not null |
| `value` | text | nullable |
| `group` | string(50) | not null, default 'general' |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `unique(key)` — primary lookup
- `index(group)` — group-based queries for admin page

**Seeded defaults (in seeder, not migration):**

| key | value | group |
|---|---|---|
| `cr_number` | `''` | `legal` |
| `vat_number` | `''` | `legal` |
| `company_name_en` | `''` | `legal` |
| `company_name_ar` | `''` | `legal` |
| `company_address_en` | `''` | `legal` |
| `company_address_ar` | `''` | `legal` |
| `logo_path` | `null` | `branding` |
| `favicon_path` | `null` | `branding` |
| `support_email` | `''` | `commerce` |
| `support_phone` | `''` | `commerce` |
| `last_invoice_sequence` | `0` | `commerce` |

### Model

**File:** `app/Modules/Settings/Models/StoreSetting.php`

```
Namespace: App\Modules\Settings\Models
Table: store_settings
Fillable: key, value, group
Casts: (none — value is always text, cast in service)
Timestamps: yes
```

No relationships.

### Service

**File:** `app/Modules/Settings/Services/StoreSettingsService.php`

Registered as singleton in `SettingsServiceProvider`.

```php
class StoreSettingsService
{
    /**
     * Get a setting value by key, with optional default.
     * Caches all settings in-memory for the request lifecycle.
     */
    public function get(string $key, ?string $default = null): ?string

    /**
     * Set a setting value. Creates if missing, updates if exists.
     */
    public function set(string $key, ?string $value, string $group = 'general'): void

    /**
     * Get all settings for a given group as key=>value array.
     */
    public function getGroup(string $group): array

    /**
     * Bulk update multiple settings at once (used by admin page).
     * Wraps in DB transaction.
     */
    public function bulkUpdate(array $settings): void

    /**
     * Get the next invoice sequence number.
     * Uses lockForUpdate() inside a DB transaction.
     * Returns the NEW sequence number (already incremented and saved).
     */
    public function getNextInvoiceSequence(): int

    /**
     * Flush the in-memory cache (for testing).
     */
    public function flush(): void
```

**`getNextInvoiceSequence()` implementation detail:**
1. Begin DB transaction
2. `StoreSetting::where('key', 'last_invoice_sequence')->lockForUpdate()->first()`
3. Increment value by 1
4. Save
5. Commit
6. Return new value

### Events

None. Store settings are passive config — no events needed.

### Jobs

None.

### Form Requests

None for API (store settings are admin-only, validated in Filament page).

### API Resources

None. Store settings are not exposed via customer-facing API. Only used internally by InvoiceService and the Filament admin page.

### Controller / Routes

None. No customer-facing API for settings.

### Filament: StoreSettingsPage

**File:** `app/Filament/Pages/StoreSettingsPage.php`

This is a custom Filament **Page** (not a Resource). Registered via `discoverPages` in AdminPanelProvider (already configured for `app/Filament/Pages`).

**Namespace:** `App\Filament\Pages`

**Form sections (grouped):**

**Legal section:**
- `cr_number` — TextInput, required
- `vat_number` — TextInput, required
- `company_name_en` — TextInput, required
- `company_name_ar` — TextInput, required
- `company_address_en` — Textarea
- `company_address_ar` — Textarea

**Branding section:**
- `logo_path` — FileUpload (image, max 2MB, `public/logos`)
- `favicon_path` — FileUpload (image, max 512KB, `public/favicons`)

**Commerce section:**
- `support_email` — TextInput, email
- `support_phone` — TextInput

Navigation: icon `heroicon-o-cog-6-tooth`, sort 99, label "Store Settings".

On mount: load all settings via `StoreSettingsService::getGroup()` for each group, fill form.
On save: call `StoreSettingsService::bulkUpdate()` with form data.

### Module Provider

**File:** `app/Modules/Settings/SettingsServiceProvider.php`

```php
class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StoreSettingsService::class);
    }

    public function boot(): void
    {
        // No routes — admin-only via Filament
    }
}
```

Register in `bootstrap/providers.php`.

### Seeder

**File:** `database/seeders/StoreSettingsSeeder.php`

Uses `updateOrCreate` on key — idempotent, safe to re-run.

### Tests (6 tests)

**File:** `tests/Feature/Settings/StoreSettingsTest.php`

1. `it can get a setting by key`
2. `it returns default when setting does not exist`
3. `it can set a new setting`
4. `it can update an existing setting`
5. `it can bulk update multiple settings`
6. `it generates sequential invoice numbers atomically` — run 10 concurrent calls, assert all unique and sequential

**File:** `tests/Feature/Admin/StoreSettingsPageTest.php`

7. `it renders the store settings page for admin users`
8. `it saves legal settings via the form`

---

## 3A.2 — Invoice Model

### Database Migrations

**File:** `database/migrations/2026_04_03_000002_create_invoices_table.php`

**Table:** `invoices`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `order_id` | unsignedBigInteger | FK orders.id, not null |
| `invoice_number` | string(30) | unique, not null |
| `subtotal_fils` | integer | not null |
| `vat_fils` | integer | not null |
| `discount_fils` | integer | not null, default 0 |
| `total_fils` | integer | not null |
| `cr_number` | string(50) | not null (snapshot from store_settings) |
| `vat_number` | string(50) | not null (snapshot from store_settings) |
| `company_name_en` | string(255) | not null (snapshot) |
| `company_name_ar` | string(255) | not null (snapshot) |
| `company_address_en` | text | nullable (snapshot) |
| `company_address_ar` | text | nullable (snapshot) |
| `issued_at` | timestamp | not null |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `unique(invoice_number)`
- `index(order_id)`
- `index(issued_at)`

**Foreign keys:**
- `order_id` references `orders.id` — `cascadeOnDelete()` (if order is deleted, invoice goes with it)

---

**File:** `database/migrations/2026_04_03_000003_create_invoice_items_table.php`

**Table:** `invoice_items`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `invoice_id` | unsignedBigInteger | FK invoices.id, not null |
| `order_item_id` | unsignedBigInteger | FK order_items.id, nullable |
| `variant_id` | unsignedBigInteger | FK variants.id, nullable |
| `name_en` | string(255) | not null |
| `name_ar` | string(255) | not null |
| `sku` | string(100) | not null |
| `quantity` | integer | not null |
| `unit_price_fils` | integer | not null |
| `vat_rate` | decimal(5,4) | not null, default 0.1000 |
| `vat_fils` | integer | not null |
| `total_fils` | integer | not null (qty * unit_price + vat) |

**Indexes:**
- `index(invoice_id)`
- `index(order_item_id)`

**Foreign keys:**
- `invoice_id` references `invoices.id` — `cascadeOnDelete()`
- `order_item_id` references `order_items.id` — `nullOnDelete()`
- `variant_id` references `variants.id` — `nullOnDelete()`

### Models

**File:** `app/Modules/Orders/Models/Invoice.php`

```
Namespace: App\Modules\Orders\Models
Table: invoices
Fillable: order_id, invoice_number, subtotal_fils, vat_fils, discount_fils,
          total_fils, cr_number, vat_number, company_name_en, company_name_ar,
          company_address_en, company_address_ar, issued_at
Casts:
    subtotal_fils => integer
    vat_fils => integer
    discount_fils => integer
    total_fils => integer
    issued_at => datetime
Relationships:
    order(): BelongsTo Order
    items(): HasMany InvoiceItem
```

**File:** `app/Modules/Orders/Models/InvoiceItem.php`

```
Namespace: App\Modules\Orders\Models
Table: invoice_items
Fillable: invoice_id, order_item_id, variant_id, name_en, name_ar, sku,
          quantity, unit_price_fils, vat_rate, vat_fils, total_fils
Casts:
    quantity => integer
    unit_price_fils => integer
    vat_rate => decimal:4
    vat_fils => integer
    total_fils => integer
Timestamps: false
Relationships:
    invoice(): BelongsTo Invoice
    orderItem(): BelongsTo OrderItem
    variant(): BelongsTo Variant
```

**Add to Order model:**
```php
public function invoice(): HasOne
{
    return $this->hasOne(Invoice::class);
}
```

### Service

**File:** `app/Modules/Orders/Services/InvoiceService.php`

Registered as singleton in `OrdersServiceProvider`.

```php
class InvoiceService
{
    public function __construct(
        private readonly StoreSettingsService $settingsService,
    ) {}

    /**
     * Generate an invoice for an order.
     * Idempotent — returns existing invoice if one already exists.
     * Called by GenerateInvoiceJob.
     *
     * Steps:
     * 1. Check if invoice already exists for this order — return it if so
     * 2. Get next invoice sequence from StoreSettingsService (lockForUpdate)
     * 3. Snapshot legal details from store_settings
     * 4. Create Invoice record
     * 5. Create InvoiceItem records from order items
     * 6. Fire InvoiceGenerated event
     */
    public function generateInvoice(Order $order): Invoice

    /**
     * Build the invoice number string from sequence.
     * Format: INV-{YYYY}-{000001}
     */
    private function buildInvoiceNumber(int $sequence): string

    /**
     * Get invoice for an order. Returns null if not yet generated.
     */
    public function getInvoiceForOrder(Order $order): ?Invoice
```

**`generateInvoice()` implementation detail:**

VAT proration per item:
- Each order_item has `price_fils_per_unit` and `quantity`
- `item_subtotal = price_fils_per_unit * quantity`
- `item_vat = (int) round(item_subtotal * 0.10)` — 10% Bahrain VAT
- `item_total = item_subtotal + item_vat`
- Invoice-level `vat_fils` = sum of all item VATs (matches order `vat_fils` within rounding)
- Invoice-level `discount_fils` = order `coupon_discount_fils`
- Invoice-level `subtotal_fils` = order `subtotal_fils`
- Invoice-level `total_fils` = order `total_fils`

Company snapshots are read from `StoreSettingsService` at invoice generation time and stored on the invoice record. This ensures the invoice is a frozen legal document even if settings change later.

### Events

**File:** `app/Modules/Orders/Events/InvoiceGenerated.php`

```php
class InvoiceGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Order $order,
    ) {}
}
```

**Listeners:**

**File:** `app/Modules/Notifications/Listeners/SendInvoiceEmail.php`

Listens to `InvoiceGenerated`. Sends the invoice email to the customer (or guest_email). Uses a new `InvoiceMail` mailable.

**File:** `app/Modules/Notifications/Mail/InvoiceMail.php`

Queued, bilingual AR/EN mailable. Contains invoice number, total, and line items. PDF attachment is Phase 3F scope — stub with text-only for now.

**Register in `NotificationsServiceProvider::boot()`:**
```php
Event::listen(InvoiceGenerated::class, SendInvoiceEmail::class);
```

### Jobs

**File:** `app/Modules/Orders/Jobs/GenerateInvoiceJob.php`

```php
class GenerateInvoiceJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 30, 120];

    public function __construct(
        public readonly int $orderId,
    ) {}

    public function uniqueId(): string
    {
        return "generate_invoice_{$this->orderId}";
    }

    public function handle(InvoiceService $invoiceService): void
    {
        $order = Order::with('items.variant.product')->findOrFail($this->orderId);
        $invoiceService->generateInvoice($order);
    }
}
```

**Idempotency:** `InvoiceService::generateInvoice()` checks for existing invoice first. `ShouldBeUnique` prevents concurrent jobs for the same order.

**Dispatch point:** Listener on `PaymentCaptured` event (for Tap payments). COD dispatches it directly on order creation (see 3A.4).

**File:** `app/Modules/Orders/Listeners/GenerateInvoiceOnPaymentCaptured.php`

Listens to `PaymentCaptured`, dispatches `GenerateInvoiceJob`.

**Register in `OrdersServiceProvider::boot()`:**
```php
Event::listen(PaymentCaptured::class, [GenerateInvoiceOnPaymentCaptured::class, 'handle']);
```

### Form Requests

None for invoice generation (it's triggered by events, not by API request).

### API Resources

**File:** `app/Modules/Orders/Resources/InvoiceResource.php`

```php
return [
    'id' => $this->id,
    'invoice_number' => $this->invoice_number,
    'order_id' => $this->order_id,
    'subtotal_fils' => $this->subtotal_fils,
    'vat_fils' => $this->vat_fils,
    'discount_fils' => $this->discount_fils,
    'total_fils' => $this->total_fils,
    'cr_number' => $this->cr_number,
    'vat_number' => $this->vat_number,
    'company_name_en' => $this->company_name_en,
    'company_name_ar' => $this->company_name_ar,
    'company_address_en' => $this->company_address_en,
    'company_address_ar' => $this->company_address_ar,
    'issued_at' => $this->issued_at->toIso8601String(),
    'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
];
```

**File:** `app/Modules/Orders/Resources/InvoiceItemResource.php`

```php
return [
    'id' => $this->id,
    'name_en' => $this->name_en,
    'name_ar' => $this->name_ar,
    'sku' => $this->sku,
    'quantity' => $this->quantity,
    'unit_price_fils' => $this->unit_price_fils,
    'vat_rate' => $this->vat_rate,
    'vat_fils' => $this->vat_fils,
    'total_fils' => $this->total_fils,
];
```

### Controller / Routes

**Add to `OrderController`:**

```php
/**
 * GET /api/v1/orders/{orderNumber}/invoice
 * Returns the invoice for an order (authenticated user only).
 */
public function invoice(string $orderNumber): JsonResponse
```

**Add to `app/Modules/Orders/routes.php`:**

```php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // ... existing routes ...
    Route::get('orders/{orderNumber}/invoice', [OrderController::class, 'invoice']);
});
```

Implementation: load order (ownership check), get invoice via `InvoiceService::getInvoiceForOrder()`, return `InvoiceResource` or 404 if not generated yet.

### Filament: InvoiceResource (read-only)

**File:** `app/Modules/Orders/Filament/Resources/InvoiceResource.php`

Read-only resource. No create/edit pages.

**Table columns:**
- `invoice_number` — TextColumn, searchable, sortable
- `order.order_number` — TextColumn, searchable, label "Order"
- `total_fils` — TextColumn, formatted as BHD
- `issued_at` — TextColumn, dateTime, sortable
- `created_at` — TextColumn, dateTime

**Pages:** ListInvoices (index only), ViewInvoice (read-only detail).

**ViewInvoice** shows: all invoice fields + InvoiceItemsRelationManager (read-only table).

**Also add InvoiceRelationManager to OrderResource:**
Shows the invoice (if exists) on the order view page. Single-row relation manager since it's HasOne.

### Tests (8 tests)

**File:** `tests/Feature/Orders/InvoiceTest.php`

1. `it generates an invoice for a paid order`
2. `it is idempotent — does not create duplicate invoice`
3. `it generates sequential invoice numbers` — create 3 invoices, assert INV-2026-000001, INV-2026-000002, INV-2026-000003
4. `it snapshots legal details from store settings`
5. `it creates invoice items matching order items`
6. `it calculates VAT per item correctly`
7. `it fires InvoiceGenerated event`
8. `it generates invoice on PaymentCaptured event via listener`

**File:** `tests/Feature/Orders/InvoiceApiTest.php`

9. `it returns invoice for authenticated user`
10. `it returns 404 when invoice not yet generated`
11. `it returns 403 for another users order`

---

## 3A.3 — Shipment Model

### Database Migrations

**File:** `database/migrations/2026_04_03_000004_create_shipments_table.php`

**Table:** `shipments`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `order_id` | unsignedBigInteger | FK orders.id, not null |
| `shipment_number` | string(30) | unique, not null |
| `carrier` | string(100) | nullable |
| `tracking_number` | string(255) | nullable |
| `status` | enum('pending','shipped','delivered') | not null, default 'pending' |
| `shipped_at` | timestamp | nullable |
| `delivered_at` | timestamp | nullable |
| `notes` | text | nullable |
| `created_by` | unsignedBigInteger | FK users.id, nullable |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `unique(shipment_number)`
- `index(order_id)`
- `index(status)`
- `index(tracking_number)`

**Foreign keys:**
- `order_id` references `orders.id` — `cascadeOnDelete()`
- `created_by` references `users.id` — `nullOnDelete()`

---

**File:** `database/migrations/2026_04_03_000005_create_shipment_items_table.php`

**Table:** `shipment_items`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `shipment_id` | unsignedBigInteger | FK shipments.id, not null |
| `order_item_id` | unsignedBigInteger | FK order_items.id, not null |
| `quantity_shipped` | integer | not null |

**Indexes:**
- `index(shipment_id)`
- `index(order_item_id)`

**Foreign keys:**
- `shipment_id` references `shipments.id` — `cascadeOnDelete()`
- `order_item_id` references `order_items.id` — `cascadeOnDelete()`

---

**File:** `database/migrations/2026_04_03_000006_add_shipped_delivered_statuses_to_orders.php`

Alter the `order_status` enum on the `orders` table to add `'shipped'` and `'delivered'`.

**PostgreSQL approach:** Use raw SQL to add new values to the enum type:
```sql
ALTER TYPE orders_order_status_check ADD VALUE IF NOT EXISTS 'shipped';
ALTER TYPE orders_order_status_check ADD VALUE IF NOT EXISTS 'delivered';
```

**SQLite gate:** Skip ALTER for SQLite (testing) — SQLite CHECK constraints are looser and accept any string. Guard with `if (DB::getDriverName() === 'pgsql')`.

**`down()` method:** No-op (PostgreSQL cannot remove enum values without recreating the type — acceptable for this migration).

### Models

**File:** `app/Modules/Orders/Models/Shipment.php`

```
Namespace: App\Modules\Orders\Models
Table: shipments
Fillable: order_id, shipment_number, carrier, tracking_number, status,
          shipped_at, delivered_at, notes, created_by
Casts:
    shipped_at => datetime
    delivered_at => datetime
Relationships:
    order(): BelongsTo Order
    items(): HasMany ShipmentItem
    creator(): BelongsTo User (foreign key: created_by)
```

**File:** `app/Modules/Orders/Models/ShipmentItem.php`

```
Namespace: App\Modules\Orders\Models
Table: shipment_items
Fillable: shipment_id, order_item_id, quantity_shipped
Casts:
    quantity_shipped => integer
Timestamps: false
Relationships:
    shipment(): BelongsTo Shipment
    orderItem(): BelongsTo OrderItem
```

**Add to Order model:**
```php
public function shipments(): HasMany
{
    return $this->hasMany(Shipment::class);
}
```

**Add to OrderItem model:**
```php
public function shipmentItems(): HasMany
{
    return $this->hasMany(ShipmentItem::class);
}

/**
 * Total quantity already shipped across all shipments.
 */
public function getQuantityShippedAttribute(): int
{
    return $this->shipmentItems->sum('quantity_shipped');
}

/**
 * Remaining quantity available to ship.
 */
public function getQuantityToShipAttribute(): int
{
    return $this->quantity - $this->quantity_shipped;
}
```

### Service

**File:** `app/Modules/Orders/Services/ShipmentService.php`

Registered as singleton in `OrdersServiceProvider`.

```php
class ShipmentService
{
    /**
     * Create a shipment for an order.
     * Validates qty_to_ship per item. Wraps in DB transaction.
     *
     * @param array $items [{order_item_id: int, quantity_shipped: int}, ...]
     * @throws ValidationException if qty exceeds available
     *
     * Steps:
     * 1. Validate order is in a shippable status (paid, pending_collection, shipped)
     * 2. For each item: check quantity_shipped <= quantity_to_ship
     * 3. Generate shipment_number: SHP-{YYYY}-{order_id}-{shipment_count+1}
     * 4. Create Shipment record with status 'pending'
     * 5. Create ShipmentItem records
     * 6. Fire ShipmentCreated event
     * 7. If ALL order items are now fully shipped, update order to 'shipped'
     */
    public function createShipment(
        Order $order,
        array $items,
        ?string $carrier = null,
        ?string $trackingNumber = null,
        ?string $notes = null,
        ?int $createdBy = null,
    ): Shipment

    /**
     * Mark a shipment as shipped (with optional tracking update).
     */
    public function markShipped(Shipment $shipment, ?string $trackingNumber = null): Shipment

    /**
     * Mark a shipment as delivered. Fires OrderFulfilled if ALL shipments delivered.
     *
     * Steps:
     * 1. Update shipment status to 'delivered', set delivered_at
     * 2. Check if ALL shipments for the order are delivered
     * 3. If yes: update order to 'delivered', fire OrderFulfilled event
     */
    public function markDelivered(Shipment $shipment): Shipment

    /**
     * Get all shipments for an order.
     */
    public function getShipmentsForOrder(Order $order): Collection
```

**Cross-module note:** `OrderFulfilled` event is already defined and listened to by Notifications (sends shipping update email). No new event needed for delivery — reuse `OrderFulfilled`.

### Events

**File:** `app/Modules/Orders/Events/ShipmentCreated.php`

```php
class ShipmentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly Order $order,
    ) {}
}
```

**Listeners:**

**File:** `app/Modules/Notifications/Listeners/SendShipmentCreatedEmail.php`

Listens to `ShipmentCreated`. Sends tracking info email to customer.

**File:** `app/Modules/Notifications/Mail/ShipmentCreatedMail.php`

Queued, bilingual. Contains carrier, tracking number, items shipped.

**Register in `NotificationsServiceProvider::boot()`:**
```php
Event::listen(ShipmentCreated::class, SendShipmentCreatedEmail::class);
```

### Jobs

None. Shipments are created synchronously by admin actions (Filament). No queued processing needed.

### Form Requests

None for API. Shipment creation is admin-only via Filament.

### API Resources

**File:** `app/Modules/Orders/Resources/ShipmentResource.php`

```php
return [
    'id' => $this->id,
    'shipment_number' => $this->shipment_number,
    'carrier' => $this->carrier,
    'tracking_number' => $this->tracking_number,
    'status' => $this->status,
    'shipped_at' => $this->shipped_at?->toIso8601String(),
    'delivered_at' => $this->delivered_at?->toIso8601String(),
    'notes' => $this->notes,
    'items' => ShipmentItemResource::collection($this->whenLoaded('items')),
];
```

**File:** `app/Modules/Orders/Resources/ShipmentItemResource.php`

```php
return [
    'order_item_id' => $this->order_item_id,
    'quantity_shipped' => $this->quantity_shipped,
];
```

### Controller / Routes

**Add to `OrderController`:**

```php
/**
 * GET /api/v1/orders/{orderNumber}/shipments
 * Returns all shipments for an order (authenticated user only).
 */
public function shipments(string $orderNumber): JsonResponse
```

**Add to `app/Modules/Orders/routes.php`:**

```php
Route::get('orders/{orderNumber}/shipments', [OrderController::class, 'shipments']);
```

Implementation: load order (ownership check), get shipments via `ShipmentService::getShipmentsForOrder()`, return collection of `ShipmentResource`.

### Filament: Shipment Management on OrderResource

**Do NOT create a standalone ShipmentResource.** Shipments are always in the context of an order. Add shipment management as:

1. **ShipmentsRelationManager** on `OrderResource`

**File:** `app/Modules/Orders/Filament/Resources/OrderResource/RelationManagers/ShipmentsRelationManager.php`

**Table columns:**
- `shipment_number` — TextColumn
- `carrier` — TextColumn
- `tracking_number` — TextColumn
- `status` — TextColumn, badge, color mapped (pending=warning, shipped=info, delivered=success)
- `shipped_at` — TextColumn, dateTime
- `delivered_at` — TextColumn, dateTime

**Header action: "Create Shipment"**

Form fields:
- `carrier` — TextInput
- `tracking_number` — TextInput
- Repeater for items: for each order item with `quantity_to_ship > 0`, show name + SKU + max qty, input quantity_shipped

Action calls `ShipmentService::createShipment()`.

**Row actions:**
- "Mark Shipped" — visible when status=pending, calls `ShipmentService::markShipped()`
- "Mark Delivered" — visible when status=shipped, calls `ShipmentService::markDelivered()`

2. **Register in `OrderResource::getRelationManagers()`:**
```php
ShipmentsRelationManager::class,
```

### Tests (8 tests)

**File:** `tests/Feature/Orders/ShipmentTest.php`

1. `it creates a shipment for a paid order`
2. `it rejects shipment when quantity exceeds available`
3. `it generates unique shipment numbers`
4. `it fires ShipmentCreated event`
5. `it marks shipment as shipped`
6. `it marks shipment as delivered`
7. `it transitions order to shipped when all items shipped`
8. `it fires OrderFulfilled when all shipments delivered`

**File:** `tests/Feature/Orders/ShipmentApiTest.php`

9. `it returns shipments for authenticated user`
10. `it returns 403 for another users order`

---

## 3A.4 — COD Payment Method

### Database Migration

**File:** `database/migrations/2026_04_03_000007_add_cod_statuses_to_orders.php`

**Changes to `orders` table:**

1. Add `'cod'` to the `payment_method` enum.
2. Add `'pending_collection'` and `'collected'` to the `order_status` enum.

**PostgreSQL approach:**
```sql
ALTER TYPE orders_payment_method_check ADD VALUE IF NOT EXISTS 'cod';
ALTER TYPE orders_order_status_check ADD VALUE IF NOT EXISTS 'pending_collection';
ALTER TYPE orders_order_status_check ADD VALUE IF NOT EXISTS 'collected';
```

**SQLite gate:** Same pattern as shipment migration — skip for SQLite.

**`down()`:** No-op.

### Models

No new models. COD uses the existing `Order` model with `payment_method = 'cod'`.

**Add to Order model:**

```php
public function isCod(): bool
{
    return $this->payment_method === 'cod';
}
```

Update `isCancellable()` to include `'pending_collection'`:
```php
public function isCancellable(): bool
{
    return in_array($this->order_status, ['pending', 'initiated', 'pending_collection'], true);
}
```

### Service

No new service class. COD logic is added to existing services:

**Changes to `OrderService::checkout()`:**

After creating the order, check if `$paymentMethod === 'cod'`:
- Set `order_status` to `'pending_collection'` instead of `'pending'`
- Record status history: `pending_collection`
- Dispatch `GenerateInvoiceJob` immediately (COD invoices are generated at order creation, not payment capture)
- Still fire `OrderPlaced` event (for inventory reservation + confirmation email)

**Changes to `OrderController::checkout()`:**

After the order is created, if `payment_method === 'cod'`, return the order directly (no redirect to payment gateway). The frontend skips the Tap payment flow entirely.

**New method on `OrderService`:**

```php
/**
 * Mark a COD order as collected (payment received in cash).
 * Called by admin via Filament action.
 *
 * Steps:
 * 1. Validate order is COD and status is pending_collection
 * 2. Update order_status to 'collected', set paid_at
 * 3. Record status history
 * 4. Fire CODCollected event
 *
 * @throws \InvalidArgumentException if not COD or wrong status
 */
public function markCodCollected(Order $order, ?string $note = null): Order
```

### Events

**File:** `app/Modules/Payments/Events/CODCollected.php`

```php
class CODCollected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}
}
```

**Why in Payments module:** COD collection is a payment event (money received), so it belongs in the Payments module namespace alongside `PaymentCaptured` and `PaymentFailed`.

**Listeners:**

1. **`MarkOrderPaidOnCodCollected`** (Orders module) — listens to `CODCollected`, updates order status to `'collected'` and sets `paid_at`. Wait — actually `markCodCollected()` already does this. The listener pattern would double-update. Decision: `markCodCollected()` fires the event AFTER updating the order. The event is consumed by Notifications only.

2. **`SendCodCollectedEmail`** (Notifications module) — listens to `CODCollected`, sends payment receipt email to customer. Reuses the existing `PaymentReceiptMail` mailable (or a COD-specific variant).

**Register listeners:**
- In `NotificationsServiceProvider::boot()`: `Event::listen(CODCollected::class, SendCodCollectedEmail::class);`

### Jobs

No new jobs. `GenerateInvoiceJob` (from 3A.2) is reused — dispatched synchronously in `OrderService::checkout()` for COD orders.

### Form Requests

**Update `CheckoutRequest`:**

Add `'cod'` to the `payment_method` validation rule:
```php
'payment_method' => ['required', 'string', 'in:benefit,benefit_pay_qr,card,apple_pay,cod'],
```

### API Resources

No new resources. The existing `OrderResource` already returns `payment_method` and `order_status` — the new values `'cod'`, `'pending_collection'`, `'collected'` are returned automatically.

### Controller / Routes

No new routes. The existing checkout endpoint handles COD via the `payment_method` field.

**Behavior change in `OrderController::checkout()`:** After creating the order, if COD, return 201 with the order (frontend shows confirmation page instead of redirecting to Tap).

### Filament: "Mark as Collected" Action on OrderResource

**Add to `OrderResource::table()` actions:**

```php
Action::make('mark_collected')
    ->label('Mark Collected')
    ->icon('heroicon-o-banknotes')
    ->color('success')
    ->requiresConfirmation()
    ->visible(fn (Order $record): bool => $record->isCod() && $record->order_status === 'pending_collection')
    ->form([
        Textarea::make('note')
            ->label('Collection Note (optional)'),
    ])
    ->action(function (Order $record, array $data): void {
        app(OrderService::class)->markCodCollected($record, $data['note'] ?? null);
        Notification::make()
            ->title('COD payment marked as collected.')
            ->success()
            ->send();
    }),
```

**Also update the status override `Select` options** to include the new statuses:
```php
'pending_collection' => 'Pending Collection (COD)',
'collected' => 'Collected (COD)',
'shipped' => 'Shipped',
'delivered' => 'Delivered',
```

**Update BadgeColumn colors** to include new statuses:
```php
'warning' => ['pending', 'pending_collection'],
'success' => ['paid', 'fulfilled', 'collected', 'delivered'],
'info' => ['initiated', 'processing', 'shipped'],
```

### Tests (7 tests)

**File:** `tests/Feature/Orders/CodCheckoutTest.php`

1. `it creates a COD order with pending_collection status`
2. `it generates invoice immediately for COD order`
3. `it fires OrderPlaced event for COD order`
4. `it does not require Tap payment flow for COD`
5. `it allows cancellation of pending_collection COD order`

**File:** `tests/Feature/Orders/CodCollectionTest.php`

6. `it marks COD order as collected`
7. `it rejects collection on non-COD order`
8. `it rejects collection on already collected order`
9. `it fires CODCollected event`

**File:** `tests/Feature/Admin/CodAdminTest.php`

10. `it shows mark collected button for COD pending_collection orders`
11. `it hides mark collected button for non-COD orders`

---

## Cross-Module Dependency Summary

```
store_settings (3A.1)
    └── read by: InvoiceService (3A.2) — legal snapshots + sequence
    └── read by: StoreSettingsPage (Filament)

invoices (3A.2)
    └── triggered by: PaymentCaptured listener (existing event)
    └── triggered by: COD checkout (3A.4)
    └── fires: InvoiceGenerated → Notifications

shipments (3A.3)
    └── fires: ShipmentCreated → Notifications
    └── fires: OrderFulfilled (existing event, on full delivery)
    └── adds: 'shipped'/'delivered' to order_status enum

COD (3A.4)
    └── modifies: OrderService.checkout() (COD branch)
    └── modifies: CheckoutRequest (add 'cod' to allowed methods)
    └── uses: GenerateInvoiceJob (from 3A.2)
    └── fires: CODCollected → Notifications
    └── adds: 'cod' to payment_method enum
    └── adds: 'pending_collection'/'collected' to order_status enum
```

---

## Files to Create (complete list)

### New Module: Settings
- `app/Modules/Settings/SettingsServiceProvider.php`
- `app/Modules/Settings/Models/StoreSetting.php`
- `app/Modules/Settings/Services/StoreSettingsService.php`

### Filament Pages
- `app/Filament/Pages/StoreSettingsPage.php` (create `app/Filament/Pages/` directory)

### Orders Module (new files)
- `app/Modules/Orders/Models/Invoice.php`
- `app/Modules/Orders/Models/InvoiceItem.php`
- `app/Modules/Orders/Models/Shipment.php`
- `app/Modules/Orders/Models/ShipmentItem.php`
- `app/Modules/Orders/Services/InvoiceService.php`
- `app/Modules/Orders/Services/ShipmentService.php`
- `app/Modules/Orders/Events/InvoiceGenerated.php`
- `app/Modules/Orders/Events/ShipmentCreated.php`
- `app/Modules/Orders/Jobs/GenerateInvoiceJob.php`
- `app/Modules/Orders/Listeners/GenerateInvoiceOnPaymentCaptured.php`
- `app/Modules/Orders/Resources/InvoiceResource.php` (API)
- `app/Modules/Orders/Resources/InvoiceItemResource.php` (API)
- `app/Modules/Orders/Resources/ShipmentResource.php` (API)
- `app/Modules/Orders/Resources/ShipmentItemResource.php` (API)
- `app/Modules/Orders/Filament/Resources/InvoiceResource.php` (Filament)
- `app/Modules/Orders/Filament/Resources/InvoiceResource/Pages/ListInvoices.php`
- `app/Modules/Orders/Filament/Resources/InvoiceResource/Pages/ViewInvoice.php`
- `app/Modules/Orders/Filament/Resources/OrderResource/RelationManagers/ShipmentsRelationManager.php`
- `app/Modules/Orders/Filament/Resources/OrderResource/RelationManagers/InvoiceRelationManager.php`

### Payments Module (new files)
- `app/Modules/Payments/Events/CODCollected.php`

### Notifications Module (new files)
- `app/Modules/Notifications/Listeners/SendInvoiceEmail.php`
- `app/Modules/Notifications/Listeners/SendShipmentCreatedEmail.php`
- `app/Modules/Notifications/Listeners/SendCodCollectedEmail.php`
- `app/Modules/Notifications/Mail/InvoiceMail.php`
- `app/Modules/Notifications/Mail/ShipmentCreatedMail.php`

### Migrations
- `database/migrations/2026_04_03_000001_create_store_settings_table.php`
- `database/migrations/2026_04_03_000002_create_invoices_table.php`
- `database/migrations/2026_04_03_000003_create_invoice_items_table.php`
- `database/migrations/2026_04_03_000004_create_shipments_table.php`
- `database/migrations/2026_04_03_000005_create_shipment_items_table.php`
- `database/migrations/2026_04_03_000006_add_shipped_delivered_statuses_to_orders.php`
- `database/migrations/2026_04_03_000007_add_cod_statuses_to_orders.php`

### Seeders
- `database/seeders/StoreSettingsSeeder.php`

### Tests
- `tests/Feature/Settings/StoreSettingsTest.php`
- `tests/Feature/Admin/StoreSettingsPageTest.php`
- `tests/Feature/Orders/InvoiceTest.php`
- `tests/Feature/Orders/InvoiceApiTest.php`
- `tests/Feature/Orders/ShipmentTest.php`
- `tests/Feature/Orders/ShipmentApiTest.php`
- `tests/Feature/Orders/CodCheckoutTest.php`
- `tests/Feature/Orders/CodCollectionTest.php`
- `tests/Feature/Admin/CodAdminTest.php`

### Files to Modify
- `bootstrap/providers.php` — add `SettingsServiceProvider`
- `app/Modules/Orders/OrdersServiceProvider.php` — register InvoiceService, ShipmentService singletons + PaymentCaptured listener for invoice generation
- `app/Modules/Orders/Services/OrderService.php` — COD checkout branch + `markCodCollected()` method
- `app/Modules/Orders/Models/Order.php` — add `invoice()`, `shipments()`, `isCod()` relationships; update `isCancellable()`
- `app/Modules/Orders/Models/OrderItem.php` — add `shipmentItems()` relationship + computed attributes
- `app/Modules/Orders/Controllers/OrderController.php` — add `invoice()` and `shipments()` methods
- `app/Modules/Orders/routes.php` — add invoice + shipments routes
- `app/Modules/Orders/Requests/CheckoutRequest.php` — add 'cod' to payment_method
- `app/Modules/Orders/Resources/OrderResource.php` (API) — add `invoice` and `shipments` to response
- `app/Modules/Orders/Filament/Resources/OrderResource.php` — add COD actions, new statuses to badge/select, register new relation managers
- `app/Modules/Notifications/NotificationsServiceProvider.php` — register new listeners
- `app/Providers/Filament/AdminPanelProvider.php` — no change needed (pages already auto-discovered)

---

## Total Estimated Tests: ~30

- Store Settings: 8
- Invoices: 11
- Shipments: 10
- COD: 11

---

## Execution Checklist

### Step 1: Store Settings (3A.1)
- [ ] Create migration
- [ ] Create Settings module (provider, model, service)
- [ ] Register provider in bootstrap/providers.php
- [ ] Create seeder
- [ ] Create StoreSettingsPage (Filament)
- [ ] Create app/Filament/Pages/ directory
- [ ] Write + run tests

### Step 2: Invoices (3A.2)
- [ ] Create invoices + invoice_items migrations
- [ ] Create Invoice + InvoiceItem models
- [ ] Create InvoiceService
- [ ] Create InvoiceGenerated event
- [ ] Create GenerateInvoiceJob
- [ ] Create GenerateInvoiceOnPaymentCaptured listener
- [ ] Register service + listener in OrdersServiceProvider
- [ ] Create API resources (InvoiceResource, InvoiceItemResource)
- [ ] Add controller methods + routes
- [ ] Create InvoiceMail + SendInvoiceEmail listener
- [ ] Register in NotificationsServiceProvider
- [ ] Create Filament InvoiceResource + InvoiceRelationManager on OrderResource
- [ ] Add `invoice()` relationship to Order model
- [ ] Write + run tests

### Step 3: Shipments (3A.3)
- [ ] Create shipments + shipment_items + status migrations
- [ ] Create Shipment + ShipmentItem models
- [ ] Create ShipmentService
- [ ] Create ShipmentCreated event
- [ ] Register service in OrdersServiceProvider
- [ ] Create API resources
- [ ] Add controller method + route
- [ ] Create ShipmentCreatedMail + listener
- [ ] Register in NotificationsServiceProvider
- [ ] Create ShipmentsRelationManager on OrderResource
- [ ] Add `shipments()` relationship to Order model
- [ ] Add `shipmentItems()` to OrderItem model
- [ ] Write + run tests

### Step 4: COD (3A.4)
- [ ] Create COD status migration
- [ ] Create CODCollected event
- [ ] Update CheckoutRequest validation
- [ ] Update OrderService.checkout() with COD branch
- [ ] Add markCodCollected() to OrderService
- [ ] Update Order model (isCod, isCancellable)
- [ ] Create SendCodCollectedEmail listener
- [ ] Register listener in NotificationsServiceProvider
- [ ] Update Filament OrderResource (mark collected action, badge colors, status options)
- [ ] Write + run tests

### Step 5: Final Verification
- [ ] Run full test suite: `php artisan test --compact`
- [ ] Run Pint: `vendor/bin/pint --dirty --format agent`
- [ ] Verify all 30+ new tests pass
- [ ] Verify existing 94 tests still pass
- [ ] Update CLAUDE.md section 8 with Phase 3A progress
