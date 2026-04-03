# Bagisto Invoices Analysis

## Models

**Invoice** (`Webkul\Sales\Models\Invoice`)
- Relationships: `order()` (BelongsTo), `items()` (HasMany), `customer()` (MorphTo), `channel()` (MorphTo), `address()` (BelongsTo Order Address)
- Key columns: `increment_id` (sequence), `state` (pending/pending_payment/paid/overdue/refunded), `email_sent`, `total_qty`, amounts (sub_total, grand_total, tax_amount, discount_amount — decimal 12,4), `order_id`, `transaction_id`, `reminders` counter, `next_reminder_at`
- Traits: `InvoiceReminder` (overdue reminder logic), `PaymentTerm` (due date calculation)

**InvoiceItem** (`Webkul\Sales\Models\InvoiceItem`)
- Relationships: `invoice()` (BelongsTo), `order_item()` (BelongsTo), `product()` (MorphTo), `children()` (HasMany)
- Key columns: `parent_id` (for composite products), `qty`, price + base_price, total + base_total, tax_amount, discount_amount, product_id, product_type, order_item_id, additional (JSON)

## Migrations (table names only)

- `invoices` — 18 columns, cascades on order delete
- `invoice_items` — 18 columns, cascades on invoice/parent delete

## Services/Repositories

**InvoiceRepository::create()**
- Fires `sales.invoice.save.before` / `sales.invoice.save.after` events
- Validates & caps qty to `qty_to_invoice`
- **Tax/discount proration**: `tax = (order_item.tax / qty_ordered) * invoice_qty`
- Handles composite product children via `parent_id`
- Updates downloadable product inventory
- Collects totals via `collectTotals()`
- Updates order status after creation

**InvoiceSequencer** — generates sequential invoice numbers with configurable prefix/suffix (no per-period reset in Bagisto)

## Events

- `sales.invoice.save.before` — pre-save hook
- `sales.invoice.save.after` → `Shop\Listeners\Invoice::afterCreated()` — sends email, sets `email_sent=1`

## Useful Patterns

1. **Qty proration formula** — `tax = (order_item.tax / qty_ordered) * invoice_qty` — use for partial invoices
2. **parent_id for composites** — store parent/child invoice items for bundle products (Phase 3B)
3. **PDF email attachment** — generate on-the-fly from Blade view, attach to invoice email
4. **Tax/discount denormalization** — store on invoice_items for audit trail (Bahrain VAT compliance)
5. **Sequential increment_id** — scope by year + zero-padded sequence; hardcode format INV-YYYY-XXXXXX

## Skip These Patterns

1. **Repository pattern** — our Service layer is simpler and sufficient
2. **Proxy classes** — unnecessary for modular monolith
3. **MorphTo for customer/channel** — Bagisto is multi-tenant; we're single-tenant (direct FK)
4. **Triple currency codes** — Magento legacy; we store BHD fils only
5. **Configurable numbering prefix/suffix** — Bahrain requires sequential + year scope; hardcode INV-YYYY-XXXXXX
6. **overdue reminders / PaymentTerm trait** — not needed for MVP; invoices are generated post-payment
