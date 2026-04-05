# Phase 3F Design Spec
**Date:** 2026-04-05  
**Branch:** feature/phase-3e  
**Status:** Approved for implementation

---

## Overview

Phase 3F completes the Filament admin panel for all Phase 3E modules and adds an analytics dashboard with KPI widgets, revenue/status charts, and CSV export endpoints.

Two parts:
- **3F.1** — Filament Resources: Returns, Loyalty, Inventory Movements
- **3F.2** — Analytics Dashboard + CSV Exports

Filament version installed: **v4.9.3** (same Schema API as documented in CLAUDE.md §10)

---

## Part 1 — Filament Admin Resources

### 1A. Guest Returns — Schema Change (prerequisite)

Before building any Filament UI, `return_requests.user_id` must be made nullable to support guest returns.

**Migration** (zero-downtime safe — nullable column):
```sql
ALTER TABLE return_requests ALTER COLUMN user_id DROP NOT NULL;
```

**ReturnService changes:**
- `createRequest()` signature: `?User $user` (nullable)
- Ownership check: if `$user !== null`, verify `$order->user_id === $user->id`; if `$user === null`, verify `$order->guest_email !== null`
- `user_id` on the new `ReturnRequest` becomes `$user?->id`
- Guest identification: use `$order->guest_email` throughout

**Filament display:** when `user_id` is null, show `"Guest (guest@email.com)"` from `$record->order->guest_email`.

---

### 1B. ReturnRequestResource

**Location:** `app/Modules/Returns/Filament/Resources/ReturnRequestResource.php`

**Pages:** List + View (no Create/Edit — returns are customer-initiated)

**List columns:**
| Column | Notes |
|--------|-------|
| `request_number` | searchable |
| `order.order_number` | link-style |
| `user.name` / guest email | `formatStateUsing` to show "Guest (email)" when null |
| `status` | badge, color: pending=warning, approved=info, completed=success, rejected=danger |
| `reason` | badge |
| `created_at` | sortable, default desc |

**View page infolist sections:**
1. Summary: request_number, status, reason, notes, admin_notes, resolution, resolution_amount_fils (BHD)
2. Order: link to order, order_number, user/guest
3. Items relation manager (see below)

**Relation manager — ReturnRequestItemsRelationManager:**
- Read-only table: `order_item_id`, `quantity_returned`, `condition` (badge)
- No create/edit/delete

**Table actions (all on list + view):**

**Approve** (visible when `status === 'pending'`):
```
Modal form:
  - resolution: Select(['refund', 'store_credit', 'exchange']) required
  - resolution_amount_fils: TextInput (integer, /1000 hint showing BHD)
      → visible/required only when resolution is 'refund' or 'store_credit'
      → hidden (set to 0) when resolution is 'exchange'
  - admin_notes: Textarea (nullable)
Calls: app(ReturnService::class)->approveReturn($record, Auth::user(), $resolution, $amount, $notes)
```

**Reject** (visible when `status === 'pending'`):
```
Modal form:
  - admin_notes: Textarea required
Calls: app(ReturnService::class)->rejectReturn($record, Auth::user(), $adminNotes)
```

**Complete** (visible when `status === 'approved'`):
```
requiresConfirmation() — "This will restock inventory."
Calls: app(ReturnService::class)->completeReturn($record)
```

**Issue Store Credit** (separate action, visible only when `status === 'completed' AND resolution === 'store_credit' AND user_id IS NOT NULL`):
```
requiresConfirmation() — "Issue {BHD amount} store credit to {user email}?"
Calls: app(LoyaltyService::class)->creditStoreCredit(
    user: $record->order->user,
    amountFils: $record->resolution_amount_fils,
    referenceType: 'return_request',
    referenceId: $record->id
)
Success notification: "Store credit issued."
```
Note: guest returns (user_id null) will never see this action — the `AND user_id IS NOT NULL` visibility guard ensures it.

---

### 1C. LoyaltyAccountResource

**Location:** `app/Modules/Loyalty/Filament/Resources/LoyaltyAccountResource.php`

**Pages:** List + View

**List columns:**
| Column | Notes |
|--------|-------|
| `user.name` | searchable |
| `user.email` | searchable |
| `points_balance` | sortable |
| `lifetime_points_earned` | sortable |
| `updated_at` | sortable |

**View page:**
- Infolist: user name, email, points_balance, lifetime_points_earned
- Relation manager: LoyaltyTransactionsRelationManager

**LoyaltyTransactionsRelationManager:**
- Read-only table: type (badge: earn=success, redeem=warning, expire=danger, adjust=info, store_credit=primary), points (formatted with sign: +100 / -50), reference_type, reference_id, description_en, created_at
- Sorted by created_at desc
- No create/edit/delete

**Table action on view page — Manual Adjust:**
```
Modal form:
  - points: TextInput (integer, can be negative — label: "Points (negative to deduct)")
  - description_en: TextInput required
  - description_ar: TextInput required
Pre-action validation (in action closure, before service call):
  if ($data['points'] < 0) {
      $balance = $record->points_balance;
      if (abs($data['points']) > $balance) {
          Notification::make()->title('Insufficient balance')->danger()->send();
          return; // halt
      }
  }
Calls: app(LoyaltyService::class)->manualAdjust(
    user: $record->user,
    points: (int) $data['points'],
    descriptionEn: $data['description_en'],
    descriptionAr: $data['description_ar'],
    admin: Auth::user()
)
```

---

### 1D. LoyaltyConfigResource

**Location:** `app/Modules/Loyalty/Filament/Resources/LoyaltyConfigResource.php`

**Pages:** List only (no Create, no Delete, no View — inline edit only)

**Design decision:** Fixed keys, edit values only, with human-readable description column.

**Table:**
| Column | Notes |
|--------|-------|
| `key` | read-only |
| Description | virtual, `formatStateUsing` maps key → description string (see below) |
| `value` | editable via row `EditAction` — modal with a single `TextInput::make('value')` field |

**Key descriptions map:**
```php
'points_per_fil'    => 'Points earned per fil spent (e.g. 0.001 = 1 point per 1 BHD)',
'fils_per_point'    => 'Fils value of each redeemed point (e.g. 100 = 0.100 BHD per point)',
'max_redeem_percent'=> 'Max % of order total payable with points (e.g. 0.5 = 50%)',
'points_expiry_days'=> 'Days before earned points expire (0 = never)',
```

**No create/delete buttons** — `->headerActions([])` and disable delete.

---

### 1E. InventoryMovementResource

**Location:** `app/Modules/Inventory/Filament/Resources/InventoryMovementResource.php`

**Pages:** List only (read-only audit log — no View page needed)

**List columns:**
| Column | Notes |
|--------|-------|
| `variant.sku` | eager load via `->with('variant')`, searchable |
| `type` | badge: sale=danger, return=success, cancellation=warning, reservation=gray, release=info, manual_in=success, manual_out=danger |
| `quantity_delta` | format with sign (+5 / -3) |
| `quantity_after` | plain integer |
| `reference_type` | |
| `reference_id` | |
| `notes` | |
| `created_by` (user name) | nullable, via `creator.name` |
| `created_at` | sortable, default desc |

**Filters:**
1. `SelectFilter::make('type')` — all 7 movement types
2. `Filter::make('sku')` — text input, queries `whereHas('variant', fn($q) => $q->where('sku', 'like', "%{$data['sku']}%"))`
3. `Filter::make('date_range')` — two date inputs (from/to), filters `created_at`

**No actions** — pure audit log, no mutations.

---

### 1F. AdminPanelProvider — New registrations + Navigation Groups

Add `discoverResources` for the three new modules in `AdminPanelProvider::panel()`:

```php
->discoverResources(
    in: app_path('Modules/Returns/Filament/Resources'),
    for: 'App\\Modules\\Returns\\Filament\\Resources',
)
->discoverResources(
    in: app_path('Modules/Loyalty/Filament/Resources'),
    for: 'App\\Modules\\Loyalty\\Filament\\Resources',
)
->discoverResources(
    in: app_path('Modules/Inventory/Filament/Resources'),
    for: 'App\\Modules\\Inventory\\Filament\\Resources',
)
->discoverPages(
    in: app_path('Filament/Pages'),
    for: 'App\\Filament\\Pages',
)
```

**Navigation groups** — set via `$navigationGroup` on each resource:

| Group | Resources |
|-------|-----------|
| Orders & Returns | OrderResource, InvoiceResource, ReturnRequestResource |
| Catalog & Inventory | (Catalog resources), InventoryMovementResource |
| Finance | (Payments resources), LoyaltyAccountResource, LoyaltyConfigResource |
| Customers | CustomerResource |
| Config | ShippingResource, PromotionResource, CurrencyResource, StoreSettingsPage |
| Analytics | AnalyticsPage (new) |

---

## Part 2 — Analytics Dashboard

### 2A. Analytics Page

**Location:** `app/Filament/Pages/AnalyticsPage.php`

Custom Filament page extending `Filament\Pages\Page` (not a resource). Navigation icon: `heroicon-o-chart-bar`. Group: Analytics. Sort: 99 (bottom of nav).

The page registers widgets using `getWidgets()` and renders them via `getHeaderWidgets()` / `getFooterWidgets()`.

---

### 2B. KPI Widgets — StatsOverviewWidget

**Location:** `app/Filament/Widgets/StatsOverviewWidget.php`

Extends `Filament\Widgets\StatsOverviewWidget`. No polling (static data, admin refreshes manually).

**Stats:**
| Stat | Query | Display |
|------|-------|---------|
| Total Revenue | `Order::where('order_status','paid')->sum('total_fils')` | `number_format($fils/1000, 3) . ' BHD'` |
| Orders Today | `Order::whereDate('created_at', Carbon::today('Asia/Bahrain'))->count()` | count |
| Orders This Month | `Order::whereMonth/Year('created_at', now('Asia/Bahrain'))->count()` | count |
| Avg Order Value | paid orders: total_revenue_fils / paid_count | BHD string |
| Total Customers | `User::count()` | count |
| Pending Returns | `ReturnRequest::where('status','pending')->count()` | count, color=warning if >0 |
| Low Stock Variants | query below | count, color=danger if >0 |

**Low stock query:**
```php
$threshold = (int) StoreSetting::where('key', 'low_stock_threshold')->value('value') ?? 5;
InventoryItem::whereRaw('quantity_available - quantity_reserved <= ?', [$threshold])->count();
```

---

### 2C. Revenue Chart Widget

**Location:** `app/Filament/Widgets/RevenueChartWidget.php`

Extends `Filament\Widgets\ChartWidget`. Type: `'bar'`. Heading: "Revenue — Last 30 Days".

```php
protected function getData(): array
{
    $tz = 'Asia/Bahrain';
    $start = Carbon::now($tz)->subDays(29)->startOfDay()->utc();
    $end = Carbon::now($tz)->endOfDay()->utc();

    $rows = Order::where('order_status', 'paid')
        ->whereBetween('created_at', [$start, $end])
        ->selectRaw("DATE(created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Bahrain') as day, SUM(total_fils) as total")
        ->groupBy('day')
        ->orderBy('day')
        ->get()
        ->keyBy('day');

    $labels = [];
    $data = [];
    for ($i = 29; $i >= 0; $i--) {
        $day = Carbon::now($tz)->subDays($i)->toDateString();
        $labels[] = Carbon::parse($day)->format('M d');
        $data[] = round(($rows[$day]->total ?? 0) / 1000, 3);
    }

    return [
        'datasets' => [['label' => 'Revenue (BHD)', 'data' => $data, 'backgroundColor' => '#f59e0b']],
        'labels' => $labels,
    ];
}
```

---

### 2D. Orders by Status Chart Widget

**Location:** `app/Filament/Widgets/OrdersByStatusChartWidget.php`

Extends `Filament\Widgets\ChartWidget`. Type: `'doughnut'`. Heading: "Orders by Status".

```php
protected function getData(): array
{
    $rows = Order::selectRaw('order_status, count(*) as count')
        ->groupBy('order_status')
        ->pluck('count', 'order_status');

    return [
        'datasets' => [['data' => $rows->values()->toArray()]],
        'labels' => $rows->keys()->toArray(),
    ];
}
```

---

### 2E. CSV Export Controller

**Location:** `app/Http/Controllers/Admin/ExportController.php`

Two endpoints, registered in `routes/web.php` under a new route group:

```php
Route::middleware(['web', 'auth'])->prefix('admin/exports')->group(function () {
    Route::get('orders', [ExportController::class, 'orders']);
    Route::get('returns', [ExportController::class, 'returns']);
});
```

Authentication: Filament's `web` guard (session login). This means the admin opens these URLs in the same browser session as the admin panel — standard `GET /admin/exports/orders` triggers a CSV download.

For Sanctum token access (external scripts): same routes are also registered in `routes/api.php` with `auth:sanctum` middleware at `/api/admin/exports/{orders|returns}`. The controller logic is shared — only the route group differs.

**Date range params:** `?from=YYYY-MM-DD&to=YYYY-MM-DD`. Both optional. Default: last 90 days.

**Orders export columns:**
```
order_number, customer_email (user.email ?? guest_email), order_status,
total_bhd (total_fils/1000 formatted to 3dp), payment_method, created_at
```

**Returns export columns:**
```
request_number, order_number (order.order_number), status, reason,
resolution, resolution_bhd (resolution_amount_fils/1000), created_at
```

Both use Laravel's `StreamedResponse` with `fputcsv`. No memory issues — chunked via `cursor()` or `chunk()`.

**Admin-only guard:** The `verified` middleware is insufficient; the controller checks `Auth::user()->hasRole('admin') || Auth::user()->hasPermissionTo(...)` using the existing `spatie/laravel-permission` package. If no role system is set up yet, the check is skipped for MVP (admin panel login is already protected).

---

## Navigation Group Assignment Summary

All resources get a `protected static ?string $navigationGroup` property:

```
'Orders & Returns'     → OrderResource, InvoiceResource, ReturnRequestResource
'Catalog & Inventory'  → (existing Catalog), InventoryMovementResource
'Finance'              → (existing Payments), LoyaltyAccountResource, LoyaltyConfigResource
'Customers'            → CustomerResource
'Config'               → ShippingResource, PromotionResource, CurrencyResource, StoreSettingsPage
```

---

## Files to Create

### New files
```
app/Modules/Returns/Filament/Resources/ReturnRequestResource.php
app/Modules/Returns/Filament/Resources/ReturnRequestResource/Pages/ListReturnRequests.php
app/Modules/Returns/Filament/Resources/ReturnRequestResource/Pages/ViewReturnRequest.php
app/Modules/Returns/Filament/Resources/ReturnRequestResource/RelationManagers/ReturnItemsRelationManager.php

app/Modules/Loyalty/Filament/Resources/LoyaltyAccountResource.php
app/Modules/Loyalty/Filament/Resources/LoyaltyAccountResource/Pages/ListLoyaltyAccounts.php
app/Modules/Loyalty/Filament/Resources/LoyaltyAccountResource/Pages/ViewLoyaltyAccount.php
app/Modules/Loyalty/Filament/Resources/LoyaltyAccountResource/RelationManagers/LoyaltyTransactionsRelationManager.php
app/Modules/Loyalty/Filament/Resources/LoyaltyConfigResource.php
app/Modules/Loyalty/Filament/Resources/LoyaltyConfigResource/Pages/ListLoyaltyConfig.php

app/Modules/Inventory/Filament/Resources/InventoryMovementResource.php
app/Modules/Inventory/Filament/Resources/InventoryMovementResource/Pages/ListInventoryMovements.php

app/Filament/Pages/AnalyticsPage.php
app/Filament/Widgets/StatsOverviewWidget.php
app/Filament/Widgets/RevenueChartWidget.php
app/Filament/Widgets/OrdersByStatusChartWidget.php

app/Http/Controllers/Admin/ExportController.php

database/migrations/XXXX_make_return_requests_user_id_nullable.php
```

### Modified files
```
app/Modules/Returns/Services/ReturnService.php  → nullable User, guest support
app/Providers/Filament/AdminPanelProvider.php   → 3 new discoverResources + discoverWidgets
routes/api.php (or new routes/admin.php)        → export routes
+ navigation group properties on all existing resources
```

---

## Out of Scope for 3F

- Spatie role/permission setup (export auth uses login-only guard for MVP)
- Filament Infolists on Returns view (use disabled form if infolist API is complex)
- Guest return API endpoint changes (user_id nullable migration + ReturnService changes are included, but no new API route/request class)
- Loyalty redemption at checkout for guests (future)
