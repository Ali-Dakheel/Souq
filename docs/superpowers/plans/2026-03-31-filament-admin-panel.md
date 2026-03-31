# Filament Admin Panel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Install Filament v3 admin panel with RBAC (Spatie Permission) providing full CRUD for Orders, Products, Categories, Attributes, Payments, Refunds, Customers, and Coupons.

**Architecture:** Filament resources are co-located inside each module at `app/Modules/*/Filament/Resources/`. The `AdminPanelProvider` discovers them via multiple `discoverResources()` calls. A single `super_admin` role gates panel access.

**Tech Stack:** Laravel 13, Filament v3, Spatie Laravel Permission v7.2 (already in composer.json), Pest v3

---

## File Map

**New files:**
- `app/Providers/Filament/AdminPanelProvider.php`
- `database/migrations/2026_03_31_000004_add_fulfillment_fields_to_orders_table.php`
- `database/seeders/AdminSeeder.php`
- `app/Modules/Orders/Filament/Resources/OrderResource.php`
- `app/Modules/Orders/Filament/Resources/OrderResource/Pages/ListOrders.php`
- `app/Modules/Orders/Filament/Resources/OrderResource/Pages/ViewOrder.php`
- `app/Modules/Orders/Filament/Resources/OrderResource/RelationManagers/OrderItemsRelationManager.php`
- `app/Modules/Orders/Filament/Resources/OrderResource/RelationManagers/StatusHistoryRelationManager.php`
- `app/Modules/Catalog/Filament/Resources/ProductResource.php`
- `app/Modules/Catalog/Filament/Resources/ProductResource/Pages/ListProducts.php`
- `app/Modules/Catalog/Filament/Resources/ProductResource/Pages/CreateProduct.php`
- `app/Modules/Catalog/Filament/Resources/ProductResource/Pages/EditProduct.php`
- `app/Modules/Catalog/Filament/Resources/ProductResource/RelationManagers/VariantsRelationManager.php`
- `app/Modules/Catalog/Filament/Resources/ProductResource/RelationManagers/TagsRelationManager.php`
- `app/Modules/Catalog/Filament/Resources/ProductResource/RelationManagers/ReviewsRelationManager.php`
- `app/Modules/Catalog/Filament/Resources/CategoryResource.php`
- `app/Modules/Catalog/Filament/Resources/CategoryResource/Pages/ListCategories.php`
- `app/Modules/Catalog/Filament/Resources/CategoryResource/Pages/CreateCategory.php`
- `app/Modules/Catalog/Filament/Resources/CategoryResource/Pages/EditCategory.php`
- `app/Modules/Catalog/Filament/Resources/CategoryResource/RelationManagers/ChildCategoriesRelationManager.php`
- `app/Modules/Catalog/Filament/Resources/AttributeResource.php`
- `app/Modules/Catalog/Filament/Resources/AttributeResource/Pages/ListAttributes.php`
- `app/Modules/Catalog/Filament/Resources/AttributeResource/Pages/CreateAttribute.php`
- `app/Modules/Catalog/Filament/Resources/AttributeResource/Pages/EditAttribute.php`
- `app/Modules/Catalog/Filament/Resources/AttributeResource/RelationManagers/AttributeValuesRelationManager.php`
- `app/Modules/Payments/Filament/Resources/TapTransactionResource.php`
- `app/Modules/Payments/Filament/Resources/TapTransactionResource/Pages/ListTapTransactions.php`
- `app/Modules/Payments/Filament/Resources/TapTransactionResource/Pages/ViewTapTransaction.php`
- `app/Modules/Payments/Filament/Resources/RefundResource.php`
- `app/Modules/Payments/Filament/Resources/RefundResource/Pages/ListRefunds.php`
- `app/Modules/Customers/Filament/Resources/CustomerResource.php`
- `app/Modules/Customers/Filament/Resources/CustomerResource/Pages/ListCustomers.php`
- `app/Modules/Customers/Filament/Resources/CustomerResource/Pages/ViewCustomer.php`
- `app/Modules/Customers/Filament/Resources/CustomerResource/RelationManagers/OrdersRelationManager.php`
- `app/Modules/Cart/Filament/Resources/CouponResource.php`
- `app/Modules/Cart/Filament/Resources/CouponResource/Pages/ListCoupons.php`
- `app/Modules/Cart/Filament/Resources/CouponResource/Pages/CreateCoupon.php`
- `app/Modules/Cart/Filament/Resources/CouponResource/Pages/EditCoupon.php`
- `app/Modules/Cart/Filament/Resources/CouponResource/RelationManagers/UsageHistoryRelationManager.php`
- `tests/Feature/Admin/AdminPanelAccessTest.php`
- `tests/Feature/Admin/OrderResourceTest.php`
- `tests/Feature/Admin/ProductResourceTest.php`
- `tests/Feature/Admin/RefundResourceTest.php`

**Modified files:**
- `app/Models/User.php` — add `HasRoles` trait, `FilamentUser` interface, `canAccessPanel()`
- `app/Modules/Orders/Models/Order.php` — add `locale`, `tracking_number`, `fulfilled_at` to `$fillable` and `$casts`
- `app/Modules/Orders/Services/OrderService.php` — add `fulfillOrder()`, `overrideOrderStatus()`, `cancelOrderAsAdmin()`
- `app/Modules/Orders/Controllers/OrderController.php` — pass `locale` from request to `checkout()`
- `bootstrap/providers.php` — register `AdminPanelProvider`
- `database/seeders/DatabaseSeeder.php` — call `AdminSeeder`

---

### Task 1: Install Filament v3 and publish assets

**Files:**
- Create: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `bootstrap/providers.php`

- [ ] **Step 1: Install Filament**

Run from `backend/`:
```bash
composer require filament/filament:"^3.3"
```
Expected: Filament installed, no errors.

- [ ] **Step 2: Run Filament install command**

```bash
php artisan filament:install --panels
```
When prompted for panel ID: enter `admin`.
This creates `app/Providers/Filament/AdminPanelProvider.php` and registers it in `bootstrap/providers.php`.

- [ ] **Step 3: Replace generated AdminPanelProvider with multi-module version**

Overwrite `app/Providers/Filament/AdminPanelProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors(['primary' => Color::Amber])
            ->discoverResources(
                in: app_path('Modules/Orders/Filament/Resources'),
                for: 'App\\Modules\\Orders\\Filament\\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Catalog/Filament/Resources'),
                for: 'App\\Modules\\Catalog\\Filament\\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Payments/Filament/Resources'),
                for: 'App\\Modules\\Payments\\Filament\\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Customers/Filament/Resources'),
                for: 'App\\Modules\\Customers\\Filament\\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Cart/Filament/Resources'),
                for: 'App\\Modules\\Cart\\Filament\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages',
            )
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets',
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
```

- [ ] **Step 4: Publish Filament assets**

```bash
php artisan filament:assets
```
Expected: Assets published to `public/`.

- [ ] **Step 5: Verify panel loads**

```bash
php artisan route:list | grep admin
```
Expected: `/admin` and `/admin/login` routes listed.

- [ ] **Step 6: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php bootstrap/providers.php public/
git commit -m "feat: install Filament v3 admin panel with multi-module resource discovery"
```

---

### Task 2: Spatie Permission — RBAC + AdminSeeder

**Files:**
- Modify: `app/Models/User.php`
- Create: `database/seeders/AdminSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Publish Spatie Permission migration**

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```
Expected: `config/permission.php` and a migration for `roles`, `permissions`, `model_has_roles`, etc. published.

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```
Expected: Spatie tables created.

- [ ] **Step 3: Update User model**

Replace `app/Models/User.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Customers\Models\CustomerProfile;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'tap_customer_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('super_admin');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }
}
```

- [ ] **Step 4: Add Gate wildcard bypass in AppServiceProvider**

Edit `app/Providers/AppServiceProvider.php` — add to `boot()`:

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::before(function (\App\Models\User $user, string $ability): ?bool {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        return null;
    });
}
```

- [ ] **Step 5: Create AdminSeeder**

Create `database/seeders/AdminSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::firstOrCreate(
            ['email' => (string) config('admin.email', env('ADMIN_EMAIL', 'admin@example.com'))],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make((string) config('admin.password', env('ADMIN_PASSWORD', 'password'))),
            ]
        );

        $admin->assignRole($role);
    }
}
```

- [ ] **Step 6: Call AdminSeeder from DatabaseSeeder**

Edit `database/seeders/DatabaseSeeder.php` — add to `run()`:

```php
$this->call(AdminSeeder::class);
```

- [ ] **Step 7: Add ADMIN_EMAIL and ADMIN_PASSWORD to .env.example**

Add to `.env.example`:
```
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=changeme
```

Also add to `.env` (your local values).

- [ ] **Step 8: Write failing test**

Create `tests/Feature/Admin/AdminPanelAccessTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_panel(): void
    {
        $role = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirectContains('/admin'); // redirects to dashboard, not login
    }

    public function test_regular_user_cannot_access_panel(): void
    {
        $user = User::create([
            'name'     => 'Customer',
            'email'    => 'customer@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirectContains('/admin/login');
    }
}
```

- [ ] **Step 9: Run failing test**

```bash
cd backend && php artisan test tests/Feature/Admin/AdminPanelAccessTest.php --testdox
```
Expected: FAIL (panel doesn't exist yet in full or role table missing).

- [ ] **Step 10: Run tests to verify they pass after setup**

```bash
php artisan test tests/Feature/Admin/AdminPanelAccessTest.php --testdox
```
Expected: All 3 tests PASS.

- [ ] **Step 11: Commit**

```bash
git add app/Models/User.php app/Providers/AppServiceProvider.php database/seeders/AdminSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Admin/AdminPanelAccessTest.php .env.example
git commit -m "feat: RBAC — super_admin role, FilamentUser interface, Gate wildcard bypass"
```

---

### Task 3: Migration — fulfillment fields on orders

**Files:**
- Create: `database/migrations/2026_03_31_000004_add_fulfillment_fields_to_orders_table.php`
- Modify: `app/Modules/Orders/Models/Order.php`

- [ ] **Step 1: Create migration**

Create `database/migrations/2026_03_31_000004_add_fulfillment_fields_to_orders_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('locale', 5)->default('ar')->after('notes');
            $table->string('tracking_number')->nullable()->after('locale');
            $table->timestamp('fulfilled_at')->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['locale', 'tracking_number', 'fulfilled_at']);
        });
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```
Expected: Migration runs, no errors.

- [ ] **Step 3: Update Order model fillable and casts**

Edit `app/Modules/Orders/Models/Order.php` — add to `$fillable`:

```php
protected $fillable = [
    'order_number',
    'user_id',
    'guest_email',
    'order_status',
    'subtotal_fils',
    'coupon_discount_fils',
    'coupon_code',
    'vat_fils',
    'delivery_fee_fils',
    'total_fils',
    'payment_method',
    'shipping_address_id',
    'shipping_address_snapshot',
    'billing_address_id',
    'billing_address_snapshot',
    'delivery_zone_id',
    'delivery_method_id',
    'notes',
    'locale',
    'tracking_number',
    'fulfilled_at',
    'paid_at',
    'cancelled_at',
];
```

Add to `$casts`:
```php
'fulfilled_at' => 'datetime',
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_03_31_000004_add_fulfillment_fields_to_orders_table.php app/Modules/Orders/Models/Order.php
git commit -m "feat: add locale, tracking_number, fulfilled_at columns to orders table"
```

---

### Task 4: OrderService — fulfillment methods

**Files:**
- Modify: `app/Modules/Orders/Services/OrderService.php`
- Modify: `app/Modules/Orders/Controllers/OrderController.php`

- [ ] **Step 1: Write failing tests for new service methods**

Create `tests/Feature/Admin/OrderFulfillmentTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderFulfilled;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusHistory;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status = 'paid'): Order
    {
        $user = User::create([
            'name'     => 'Customer',
            'email'    => 'c@test.com',
            'password' => bcrypt('pw'),
        ]);

        return Order::create([
            'order_number'    => 'ORD-2026-001',
            'user_id'         => $user->id,
            'order_status'    => $status,
            'subtotal_fils'   => 10000,
            'vat_fils'        => 1000,
            'total_fils'      => 11000,
            'payment_method'  => 'card',
            'locale'          => 'ar',
        ]);
    }

    public function test_fulfill_order_sets_status_and_fires_event(): void
    {
        Event::fake([OrderFulfilled::class]);
        $order   = $this->makeOrder('paid');
        $service = app(OrderService::class);

        $service->fulfillOrder($order, 'TRK-123');

        $order->refresh();
        $this->assertEquals('fulfilled', $order->order_status);
        $this->assertEquals('TRK-123', $order->tracking_number);
        $this->assertNotNull($order->fulfilled_at);

        Event::assertDispatched(OrderFulfilled::class, fn ($e) => $e->order->id === $order->id);

        $this->assertDatabaseHas('order_status_history', [
            'order_id'   => $order->id,
            'new_status' => 'fulfilled',
        ]);
    }

    public function test_override_order_status_records_history(): void
    {
        $order   = $this->makeOrder('paid');
        $service = app(OrderService::class);

        $service->overrideOrderStatus($order, 'processing', 'Moving to processing');

        $order->refresh();
        $this->assertEquals('processing', $order->order_status);
        $this->assertDatabaseHas('order_status_history', [
            'order_id'   => $order->id,
            'new_status' => 'processing',
            'reason'     => 'Moving to processing',
        ]);
    }

    public function test_cancel_order_as_admin_fires_event(): void
    {
        Event::fake([OrderCancelled::class]);
        $order   = $this->makeOrder('pending');
        $service = app(OrderService::class);

        $service->cancelOrderAsAdmin($order);

        $order->refresh();
        $this->assertEquals('cancelled', $order->order_status);
        Event::assertDispatched(OrderCancelled::class);
    }
}
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Admin/OrderFulfillmentTest.php --testdox
```
Expected: FAIL (methods don't exist).

- [ ] **Step 3: Add fulfillment methods to OrderService**

Add these three methods to `app/Modules/Orders/Services/OrderService.php`:

First, add import at top:
```php
use App\Modules\Orders\Events\OrderFulfilled;
use Carbon\Carbon;
```

Then add methods after `cancelOrder()`:

```php
public function fulfillOrder(Order $order, ?string $trackingNumber): void
{
    $oldStatus = $order->order_status;

    $order->update([
        'order_status'    => 'fulfilled',
        'tracking_number' => $trackingNumber,
        'fulfilled_at'    => Carbon::now(),
    ]);

    $this->recordStatusChange($order, $oldStatus, 'fulfilled', null, 'Fulfilled by admin');

    OrderFulfilled::dispatch($order);
}

public function overrideOrderStatus(Order $order, string $newStatus, string $note): void
{
    $oldStatus = $order->order_status;

    $order->update(['order_status' => $newStatus]);

    $this->recordStatusChange($order, $oldStatus, $newStatus, null, $note);
}

public function cancelOrderAsAdmin(Order $order, string $reason = 'Cancelled by admin'): void
{
    if (! in_array($order->order_status, ['pending', 'initiated', 'processing', 'paid'], true)) {
        throw new \InvalidArgumentException("Order cannot be cancelled in status: {$order->order_status}");
    }

    $oldStatus = $order->order_status;

    $order->update([
        'order_status' => 'cancelled',
        'cancelled_at' => Carbon::now(),
    ]);

    $this->recordStatusChange($order, $oldStatus, 'cancelled', null, $reason);

    OrderCancelled::dispatch($order);
}
```

- [ ] **Step 4: Add locale to OrderService::checkout()**

In `OrderService::checkout()`, add `string $locale = 'ar'` parameter after `?string $notes = null`. Then inside the DB transaction where the order is created, add `'locale' => $locale` to the `Order::create()` array.

Full updated signature:
```php
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
```

Find the `Order::create([` call inside the transaction (around line 90–120) and add:
```php
'locale' => $locale,
```

- [ ] **Step 5: Pass locale in OrderController**

Edit `app/Modules/Orders/Controllers/OrderController.php` — in `checkout()` method, add `locale` to the `orderService->checkout()` call:

```php
$order = $this->orderService->checkout(
    cart:              $cart,
    userId:            Auth::id(),
    guestEmail:        $request->string('guest_email')->toString() ?: null,
    shippingAddressId: $request->integer('shipping_address_id'),
    billingAddressId:  $request->integer('billing_address_id'),
    paymentMethod:     $request->string('payment_method')->toString(),
    notes:             $request->string('notes')->toString() ?: null,
    locale:            $request->string('locale', 'ar')->toString(),
);
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Admin/OrderFulfillmentTest.php --testdox
```
Expected: All 3 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Orders/Services/OrderService.php app/Modules/Orders/Controllers/OrderController.php tests/Feature/Admin/OrderFulfillmentTest.php
git commit -m "feat: add fulfillOrder, overrideOrderStatus, cancelOrderAsAdmin to OrderService"
```

---

### Task 5: OrderResource — List + View + Actions

**Files:**
- Create: `app/Modules/Orders/Filament/Resources/OrderResource.php`
- Create: `app/Modules/Orders/Filament/Resources/OrderResource/Pages/ListOrders.php`
- Create: `app/Modules/Orders/Filament/Resources/OrderResource/Pages/ViewOrder.php`

- [ ] **Step 1: Create directory**

```bash
mkdir -p backend/app/Modules/Orders/Filament/Resources/OrderResource/Pages
```

- [ ] **Step 2: Create ListOrders page**

Create `app/Modules/Orders/Filament/Resources/OrderResource/Pages/ListOrders.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\OrderResource\Pages;

use App\Modules\Orders\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
```

- [ ] **Step 3: Create ViewOrder page**

Create `app/Modules/Orders/Filament/Resources/OrderResource/Pages/ViewOrder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\OrderResource\Pages;

use App\Modules\Orders\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;
}
```

- [ ] **Step 4: Create OrderResource**

Create `app/Modules/Orders/Filament/Resources/OrderResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources;

use App\Modules\Orders\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Modules\Orders\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
use App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers\StatusHistoryRelationManager;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Orders';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),
                BadgeColumn::make('order_status')
                    ->label('Status')
                    ->colors([
                        'warning'  => 'pending',
                        'info'     => ['initiated', 'processing'],
                        'success'  => ['paid', 'fulfilled'],
                        'danger'   => ['cancelled', 'failed'],
                        'gray'     => 'refunded',
                    ]),
                TextColumn::make('total_fils')
                    ->label('Total (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3) . ' BHD'),
                TextColumn::make('locale')
                    ->label('Lang')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
                Action::make('fulfill')
                    ->label('Mark Fulfilled')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->order_status === 'paid')
                    ->form([
                        TextInput::make('tracking_number')
                            ->label('Tracking Number (optional)'),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(OrderService::class)->fulfillOrder(
                            $record,
                            $data['tracking_number'] ?: null,
                        );
                        Notification::make()->title('Order marked as fulfilled.')->success()->send();
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => in_array($record->order_status, ['pending', 'initiated', 'processing', 'paid'], true))
                    ->action(function (Order $record): void {
                        try {
                            app(OrderService::class)->cancelOrderAsAdmin($record);
                            Notification::make()->title('Order cancelled.')->success()->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('override_status')
                    ->label('Override Status')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->form([
                        Select::make('new_status')
                            ->options([
                                'pending'    => 'Pending',
                                'initiated'  => 'Initiated',
                                'processing' => 'Processing',
                                'paid'       => 'Paid',
                                'fulfilled'  => 'Fulfilled',
                                'cancelled'  => 'Cancelled',
                                'failed'     => 'Failed',
                                'refunded'   => 'Refunded',
                            ])
                            ->required(),
                        Textarea::make('note')
                            ->label('Reason')
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(OrderService::class)->overrideOrderStatus(
                            $record,
                            $data['new_status'],
                            $data['note'],
                        );
                        Notification::make()->title('Order status updated.')->success()->send();
                    }),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            OrderItemsRelationManager::class,
            StatusHistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view'  => ViewOrder::route('/{record}'),
        ];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Orders/Filament/
git commit -m "feat: OrderResource — list, view, fulfill/cancel/override actions"
```

---

### Task 6: OrderResource relation managers

**Files:**
- Create: `app/Modules/Orders/Filament/Resources/OrderResource/RelationManagers/OrderItemsRelationManager.php`
- Create: `app/Modules/Orders/Filament/Resources/OrderResource/RelationManagers/StatusHistoryRelationManager.php`

- [ ] **Step 1: Create OrderItemsRelationManager**

Create `app/Modules/Orders/Filament/Resources/OrderResource/RelationManagers/OrderItemsRelationManager.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Items';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_name_snapshot')
                    ->label('Product'),
                TextColumn::make('sku_snapshot')
                    ->label('SKU'),
                TextColumn::make('quantity'),
                TextColumn::make('unit_price_fils')
                    ->label('Unit Price (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3) . ' BHD'),
                TextColumn::make('total_price_fils')
                    ->label('Line Total (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3) . ' BHD'),
            ])
            ->paginated(false);
    }
}
```

- [ ] **Step 2: Create StatusHistoryRelationManager**

Create `app/Modules/Orders/Filament/Resources/OrderResource/RelationManagers/StatusHistoryRelationManager.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistory';
    protected static ?string $title = 'Status History';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('old_status')->label('From'),
                TextColumn::make('new_status')->label('To')->badge(),
                TextColumn::make('reason'),
                TextColumn::make('created_at')->dateTime()->label('At'),
            ])
            ->defaultSort('created_at', 'asc')
            ->paginated(false);
    }
}
```

- [ ] **Step 3: Ensure Order model has statusHistory relation**

Verify `app/Modules/Orders/Models/Order.php` has:
```php
public function statusHistory(): HasMany
{
    return $this->hasMany(OrderStatusHistory::class);
}
```
If missing, add it.

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Orders/Filament/Resources/OrderResource/RelationManagers/
git commit -m "feat: OrderResource relation managers — items and status history"
```

---

### Task 7: ProductResource — full CRUD

**Files:**
- Create: `app/Modules/Catalog/Filament/Resources/ProductResource.php`
- Create: `app/Modules/Catalog/Filament/Resources/ProductResource/Pages/ListProducts.php`
- Create: `app/Modules/Catalog/Filament/Resources/ProductResource/Pages/CreateProduct.php`
- Create: `app/Modules/Catalog/Filament/Resources/ProductResource/Pages/EditProduct.php`

- [ ] **Step 1: Create directory**

```bash
mkdir -p backend/app/Modules/Catalog/Filament/Resources/ProductResource/Pages
mkdir -p backend/app/Modules/Catalog/Filament/Resources/ProductResource/RelationManagers
```

- [ ] **Step 2: Create page classes**

Create `app/Modules/Catalog/Filament/Resources/ProductResource/Pages/ListProducts.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Filament\Resources\ProductResource\Pages;
use App\Modules\Catalog\Filament\Resources\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
```

Create `app/Modules/Catalog/Filament/Resources/ProductResource/Pages/CreateProduct.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Filament\Resources\ProductResource\Pages;
use App\Modules\Catalog\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
}
```

Create `app/Modules/Catalog/Filament/Resources/ProductResource/Pages/EditProduct.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Filament\Resources\ProductResource\Pages;
use App\Modules\Catalog\Filament\Resources\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
```

- [ ] **Step 3: Create ProductResource**

Create `app/Modules/Catalog/Filament/Resources/ProductResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Modules\Catalog\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Modules\Catalog\Filament\Resources\ProductResource\RelationManagers\ReviewsRelationManager;
use App\Modules\Catalog\Filament\Resources\ProductResource\RelationManagers\TagsRelationManager;
use App\Modules\Catalog\Filament\Resources\ProductResource\RelationManagers\VariantsRelationManager;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Products';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name.ar')
                ->label('Name (Arabic)')
                ->required(),
            TextInput::make('name.en')
                ->label('Name (English)')
                ->required(),
            Textarea::make('description.ar')
                ->label('Description (Arabic)'),
            Textarea::make('description.en')
                ->label('Description (English)'),
            TextInput::make('base_price_fils')
                ->label('Base Price (fils)')
                ->numeric()
                ->required()
                ->minValue(0),
            Select::make('category_id')
                ->label('Category')
                ->options(Category::query()->pluck('name', 'id')->mapWithKeys(
                    fn ($name, $id) => [$id => is_array($name) ? ($name['en'] ?? $name['ar'] ?? '') : $name]
                ))
                ->searchable()
                ->required(),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state['ar'] ?? '') . ' / ' . ($state['en'] ?? '') : (string) $state)
                    ->searchable(),
                TextColumn::make('base_price_fils')
                    ->label('Price (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3) . ' BHD'),
                ToggleColumn::make('is_active')->label('Active'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([EditAction::make()]);
    }

    public static function getRelationManagers(): array
    {
        return [
            VariantsRelationManager::class,
            TagsRelationManager::class,
            ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit'   => EditProduct::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Catalog/Filament/Resources/ProductResource.php app/Modules/Catalog/Filament/Resources/ProductResource/Pages/
git commit -m "feat: ProductResource — bilingual CRUD"
```

---

### Task 8: ProductResource relation managers

**Files:**
- Create: `app/Modules/Catalog/Filament/Resources/ProductResource/RelationManagers/VariantsRelationManager.php`
- Create: `app/Modules/Catalog/Filament/Resources/ProductResource/RelationManagers/TagsRelationManager.php`
- Create: `app/Modules/Catalog/Filament/Resources/ProductResource/RelationManagers/ReviewsRelationManager.php`

- [ ] **Step 1: Create VariantsRelationManager**

Create `app/Modules/Catalog/Filament/Resources/ProductResource/RelationManagers/VariantsRelationManager.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('sku')->required(),
            TextInput::make('price_fils')
                ->label('Price (fils, leave blank to inherit product price)')
                ->numeric()
                ->nullable(),
            TextInput::make('stock')
                ->numeric()
                ->default(0),
            KeyValue::make('attributes')
                ->label('Attributes (key: value pairs)'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku'),
                TextColumn::make('price_fils')
                    ->label('Price (BHD)')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1000, 3) . ' BHD' : 'Inherited'),
                TextColumn::make('inventoryItem.quantity_available')
                    ->label('Stock'),
            ])
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }
}
```

- [ ] **Step 2: Create TagsRelationManager**

Create `app/Modules/Catalog/Filament/Resources/ProductResource/RelationManagers/TagsRelationManager.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
            ])
            ->headerActions([AttachAction::make()->preloadRecordSelect()])
            ->actions([DetachAction::make()]);
    }
}
```

- [ ] **Step 3: Create ReviewsRelationManager**

Create `app/Modules/Catalog/Filament/Resources/ProductResource/RelationManagers/ReviewsRelationManager.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Reviewer'),
                TextColumn::make('rating'),
                TextColumn::make('body')->limit(80),
                BadgeColumn::make('status')
                    ->colors(['success' => 'approved', 'danger' => 'hidden', 'warning' => 'pending']),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->actions([
                Action::make('approve')
                    ->visible(fn ($record): bool => $record->status !== 'approved')
                    ->action(fn ($record) => $record->update(['status' => 'approved'])),
                Action::make('hide')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record->status !== 'hidden')
                    ->action(fn ($record) => $record->update(['status' => 'hidden'])),
            ]);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Catalog/Filament/Resources/ProductResource/RelationManagers/
git commit -m "feat: ProductResource relation managers — variants, tags, reviews"
```

---

### Task 9: CategoryResource

**Files:**
- Create: `app/Modules/Catalog/Filament/Resources/CategoryResource.php`
- Create: pages and relation manager in `CategoryResource/`

- [ ] **Step 1: Create directories**

```bash
mkdir -p backend/app/Modules/Catalog/Filament/Resources/CategoryResource/Pages
mkdir -p backend/app/Modules/Catalog/Filament/Resources/CategoryResource/RelationManagers
```

- [ ] **Step 2: Create page stubs**

Create `Pages/ListCategories.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Filament\Resources\CategoryResource\Pages;
use App\Modules\Catalog\Filament\Resources\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
```

Create `Pages/CreateCategory.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Filament\Resources\CategoryResource\Pages;
use App\Modules\Catalog\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;
class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}
```

Create `Pages/EditCategory.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Filament\Resources\CategoryResource\Pages;
use App\Modules\Catalog\Filament\Resources\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
```

- [ ] **Step 3: Create ChildCategoriesRelationManager**

Create `RelationManagers/ChildCategoriesRelationManager.php`:

```php
<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Filament\Resources\CategoryResource\RelationManagers;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
class ChildCategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'children';
    protected static ?string $title = 'Sub-categories';
    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name.ar')->label('Name (Arabic)')->required(),
            TextInput::make('name.en')->label('Name (English)')->required(),
            TextInput::make('slug')->required(),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state['ar'] ?? '') . ' / ' . ($state['en'] ?? '') : (string) $state),
                TextColumn::make('slug'),
                TextColumn::make('sort_order'),
            ])
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make()]);
    }
}
```

- [ ] **Step 4: Create CategoryResource**

Create `app/Modules/Catalog/Filament/Resources/CategoryResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Modules\Catalog\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Modules\Catalog\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Modules\Catalog\Filament\Resources\CategoryResource\RelationManagers\ChildCategoriesRelationManager;
use App\Modules\Catalog\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Categories';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name.ar')
                ->label('Name (Arabic)')
                ->required(),
            TextInput::make('name.en')
                ->label('Name (English)')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set): void {
                    $set('slug', Str::slug($state));
                }),
            TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true),
            Select::make('parent_id')
                ->label('Parent Category')
                ->options(Category::whereNull('parent_id')->pluck('name', 'id')->mapWithKeys(
                    fn ($name, $id) => [$id => is_array($name) ? ($name['en'] ?? $name['ar'] ?? '') : $name]
                ))
                ->nullable()
                ->searchable(),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state['ar'] ?? '') . ' / ' . ($state['en'] ?? '') : (string) $state)
                    ->searchable(),
                TextColumn::make('slug'),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state['en'] ?? $state['ar'] ?? '') : (string) ($state ?? '—')),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([EditAction::make()]);
    }

    public static function getRelationManagers(): array
    {
        return [ChildCategoriesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit'   => EditCategory::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Catalog/Filament/Resources/CategoryResource.php app/Modules/Catalog/Filament/Resources/CategoryResource/
git commit -m "feat: CategoryResource — bilingual CRUD with parent/child"
```

---

### Task 10: AttributeResource

**Files:**
- Create: `app/Modules/Catalog/Filament/Resources/AttributeResource.php` and pages/relation manager

- [ ] **Step 1: Create directories**

```bash
mkdir -p backend/app/Modules/Catalog/Filament/Resources/AttributeResource/Pages
mkdir -p backend/app/Modules/Catalog/Filament/Resources/AttributeResource/RelationManagers
```

- [ ] **Step 2: Create page stubs**

Create `Pages/ListAttributes.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Filament\Resources\AttributeResource\Pages;
use App\Modules\Catalog\Filament\Resources\AttributeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListAttributes extends ListRecords
{
    protected static string $resource = AttributeResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
```

Create `Pages/CreateAttribute.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Filament\Resources\AttributeResource\Pages;
use App\Modules\Catalog\Filament\Resources\AttributeResource;
use Filament\Resources\Pages\CreateRecord;
class CreateAttribute extends CreateRecord
{
    protected static string $resource = AttributeResource::class;
}
```

Create `Pages/EditAttribute.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Filament\Resources\AttributeResource\Pages;
use App\Modules\Catalog\Filament\Resources\AttributeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditAttribute extends EditRecord
{
    protected static string $resource = AttributeResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
```

- [ ] **Step 3: Create AttributeValuesRelationManager**

Create `RelationManagers/AttributeValuesRelationManager.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\AttributeResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttributeValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';
    protected static ?string $title = 'Values';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name.ar')->label('Label (Arabic)')->required(),
            TextInput::make('name.en')->label('Label (English)')->required(),
            TextInput::make('value_key')->label('Value Key')->required(),
            TextInput::make('display_value')->label('Display Value'),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state['ar'] ?? '') . ' / ' . ($state['en'] ?? '') : (string) $state),
                TextColumn::make('value_key'),
                TextColumn::make('display_value'),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }
}
```

- [ ] **Step 4: Create AttributeResource**

Create `app/Modules/Catalog/Filament/Resources/AttributeResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Filament\Resources\AttributeResource\Pages\CreateAttribute;
use App\Modules\Catalog\Filament\Resources\AttributeResource\Pages\EditAttribute;
use App\Modules\Catalog\Filament\Resources\AttributeResource\Pages\ListAttributes;
use App\Modules\Catalog\Filament\Resources\AttributeResource\RelationManagers\AttributeValuesRelationManager;
use App\Modules\Catalog\Models\Attribute;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;
    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationLabel = 'Attributes';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name.ar')->label('Name (Arabic)')->required(),
            TextInput::make('name.en')->label('Name (English)')->required(),
            Select::make('attribute_type')
                ->options([
                    'select' => 'Select',
                    'text'   => 'Text',
                    'color'  => 'Color',
                ])
                ->required(),
            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_filterable')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state['ar'] ?? '') . ' / ' . ($state['en'] ?? '') : (string) $state),
                TextColumn::make('attribute_type')->badge(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([EditAction::make()]);
    }

    public static function getRelationManagers(): array
    {
        return [AttributeValuesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit'   => EditAttribute::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Catalog/Filament/Resources/AttributeResource.php app/Modules/Catalog/Filament/Resources/AttributeResource/
git commit -m "feat: AttributeResource — bilingual CRUD with attribute values"
```

---

### Task 11: TapTransactionResource (read-only)

**Files:**
- Create: `app/Modules/Payments/Filament/Resources/TapTransactionResource.php`
- Create: pages

- [ ] **Step 1: Create directories**

```bash
mkdir -p backend/app/Modules/Payments/Filament/Resources/TapTransactionResource/Pages
```

- [ ] **Step 2: Create page stubs**

Create `Pages/ListTapTransactions.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Payments\Filament\Resources\TapTransactionResource\Pages;
use App\Modules\Payments\Filament\Resources\TapTransactionResource;
use Filament\Resources\Pages\ListRecords;
class ListTapTransactions extends ListRecords
{
    protected static string $resource = TapTransactionResource::class;
}
```

Create `Pages/ViewTapTransaction.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Payments\Filament\Resources\TapTransactionResource\Pages;
use App\Modules\Payments\Filament\Resources\TapTransactionResource;
use Filament\Resources\Pages\ViewRecord;
class ViewTapTransaction extends ViewRecord
{
    protected static string $resource = TapTransactionResource::class;
}
```

- [ ] **Step 3: Create TapTransactionResource**

Create `app/Modules/Payments/Filament/Resources/TapTransactionResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Payments\Filament\Resources\TapTransactionResource\Pages\ListTapTransactions;
use App\Modules\Payments\Filament\Resources\TapTransactionResource\Pages\ViewTapTransaction;
use App\Modules\Payments\Models\TapTransaction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Form;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TapTransactionResource extends Resource
{
    protected static ?string $model = TapTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Transactions';
    protected static ?string $navigationGroup = 'Payments';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('tap_charge_id')->label('Charge ID'),
            TextEntry::make('amount_fils')
                ->label('Amount (BHD)')
                ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3) . ' BHD'),
            TextEntry::make('status')->badge(),
            TextEntry::make('order.order_number')->label('Order'),
            KeyValueEntry::make('tap_response')->label('Tap Response JSON'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tap_charge_id')->label('Charge ID')->searchable(),
                TextColumn::make('amount_fils')
                    ->label('Amount (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3) . ' BHD'),
                BadgeColumn::make('status')
                    ->colors(['success' => 'CAPTURED', 'danger' => ['FAILED', 'CANCELLED'], 'warning' => 'INITIATED']),
                TextColumn::make('order.order_number')->label('Order')->url(
                    fn (TapTransaction $record): string => TapTransactionResource::getUrl('view', ['record' => $record])
                ),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTapTransactions::route('/'),
            'view'  => ViewTapTransaction::route('/{record}'),
        ];
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Payments/Filament/Resources/TapTransactionResource.php app/Modules/Payments/Filament/Resources/TapTransactionResource/
git commit -m "feat: TapTransactionResource — read-only with JSON response viewer"
```

---

### Task 12: RefundResource — approve/reject actions

**Files:**
- Create: `app/Modules/Payments/Filament/Resources/RefundResource.php`
- Create: `app/Modules/Payments/Filament/Resources/RefundResource/Pages/ListRefunds.php`

- [ ] **Step 1: Create directory**

```bash
mkdir -p backend/app/Modules/Payments/Filament/Resources/RefundResource/Pages
```

- [ ] **Step 2: Create ListRefunds**

Create `Pages/ListRefunds.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Payments\Filament\Resources\RefundResource\Pages;
use App\Modules\Payments\Filament\Resources\RefundResource;
use Filament\Resources\Pages\ListRecords;
class ListRefunds extends ListRecords
{
    protected static string $resource = RefundResource::class;
}
```

- [ ] **Step 3: Write failing test for refund actions**

Create `tests/Feature/Admin/RefundResourceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Events\RefundApproved;
use App\Modules\Payments\Events\RefundRejected;
use App\Modules\Payments\Models\Refund;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RefundResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $role = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.com',
            'password' => bcrypt('pw'),
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function makeRefund(): Refund
    {
        $customer = User::create([
            'name'     => 'Customer',
            'email'    => 'customer@test.com',
            'password' => bcrypt('pw'),
        ]);
        $order = Order::create([
            'order_number'   => 'ORD-2026-001',
            'user_id'        => $customer->id,
            'order_status'   => 'paid',
            'subtotal_fils'  => 10000,
            'vat_fils'       => 1000,
            'total_fils'     => 11000,
            'payment_method' => 'card',
            'locale'         => 'ar',
        ]);
        $tx = TapTransaction::create([
            'order_id'       => $order->id,
            'tap_charge_id'  => 'chg_test_001',
            'amount_fils'    => 11000,
            'currency'       => 'BHD',
            'status'         => 'CAPTURED',
            'attempt_number' => 1,
            'tap_response'   => [],
        ]);
        return Refund::create([
            'order_id'            => $order->id,
            'tap_transaction_id'  => $tx->id,
            'refund_amount_fils'  => 5000,
            'refund_reason'       => 'Product defective',
            'status'              => 'pending',
            'requested_by_user_id' => $customer->id,
        ]);
    }

    public function test_approve_action_fires_refund_approved(): void
    {
        Event::fake([RefundApproved::class]);
        $admin  = $this->makeAdmin();
        $refund = $this->makeRefund();

        $this->actingAs($admin);
        // Directly call the service to verify event
        \App\Modules\Payments\Services\RefundService::approveRefund($refund, $admin->id);

        Event::assertDispatched(RefundApproved::class, fn ($e) => $e->refund->id === $refund->id);
    }

    public function test_reject_action_fires_refund_rejected(): void
    {
        Event::fake([RefundRejected::class]);
        $admin  = $this->makeAdmin();
        $refund = $this->makeRefund();

        $this->actingAs($admin);
        \App\Modules\Payments\Services\RefundService::rejectRefund($refund, $admin->id, 'Not eligible');

        Event::assertDispatched(RefundRejected::class, fn ($e) => $e->refund->id === $refund->id);
    }
}
```

- [ ] **Step 4: Check RefundService for approve/reject methods**

Read `app/Modules/Payments/Services/RefundService.php` to find the method names for approving and rejecting refunds. Adjust the test above to use the correct method signatures.

```bash
grep -n "function " backend/app/Modules/Payments/Services/RefundService.php
```

Update the test method calls to match actual method names.

- [ ] **Step 5: Create RefundResource**

Create `app/Modules/Payments/Filament/Resources/RefundResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Payments\Filament\Resources\RefundResource\Pages\ListRefunds;
use App\Modules\Payments\Models\Refund;
use App\Modules\Payments\Services\RefundService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static ?string $navigationLabel = 'Refunds';
    protected static ?string $navigationGroup = 'Payments';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.user.name')->label('Customer'),
                TextColumn::make('order.order_number')->label('Order'),
                TextColumn::make('refund_amount_fils')
                    ->label('Amount (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3) . ' BHD'),
                TextColumn::make('refund_reason')->label('Reason')->limit(60),
                BadgeColumn::make('status')
                    ->colors(['warning' => 'pending', 'success' => 'approved', 'danger' => 'rejected', 'info' => 'processing']),
                TextColumn::make('created_at')->label('Requested At')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('approve')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn (Refund $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (Refund $record): void {
                        try {
                            app(RefundService::class)->approveRefund($record, Auth::id());
                            Notification::make()->title('Refund approved.')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Failed: ' . $e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (Refund $record): bool => $record->status === 'pending')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->action(function (Refund $record, array $data): void {
                        app(RefundService::class)->rejectRefund($record, Auth::id(), $data['rejection_reason']);
                        Notification::make()->title('Refund rejected.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRefunds::route('/'),
        ];
    }
}
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Admin/RefundResourceTest.php --testdox
```
Expected: PASS (adjust method calls in test if needed per Step 4).

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Payments/Filament/Resources/RefundResource.php app/Modules/Payments/Filament/Resources/RefundResource/ tests/Feature/Admin/RefundResourceTest.php
git commit -m "feat: RefundResource — approve/reject actions firing domain events"
```

---

### Task 13: CustomerResource (read-only)

**Files:**
- Create: `app/Modules/Customers/Filament/Resources/CustomerResource.php` and pages/relation manager

- [ ] **Step 1: Create directories**

```bash
mkdir -p backend/app/Modules/Customers/Filament/Resources/CustomerResource/Pages
mkdir -p backend/app/Modules/Customers/Filament/Resources/CustomerResource/RelationManagers
```

- [ ] **Step 2: Create page stubs**

Create `Pages/ListCustomers.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Customers\Filament\Resources\CustomerResource\Pages;
use App\Modules\Customers\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\ListRecords;
class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}
```

Create `Pages/ViewCustomer.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Customers\Filament\Resources\CustomerResource\Pages;
use App\Modules\Customers\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\ViewRecord;
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;
}
```

- [ ] **Step 3: Create OrdersRelationManager**

Create `RelationManagers/OrdersRelationManager.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Customers\Filament\Resources\CustomerResource\RelationManagers;

use App\Modules\Orders\Filament\Resources\OrderResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number'),
                BadgeColumn::make('order_status')
                    ->colors(['success' => ['paid', 'fulfilled'], 'danger' => ['cancelled', 'failed'], 'warning' => 'pending']),
                TextColumn::make('total_fils')
                    ->label('Total (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3) . ' BHD'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('view')
                    ->url(fn ($record): string => OrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
```

- [ ] **Step 4: Create CustomerResource**

Create `app/Modules/Customers/Filament/Resources/CustomerResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Customers\Filament\Resources;

use App\Modules\Customers\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Modules\Customers\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Modules\Customers\Filament\Resources\CustomerResource\RelationManagers\OrdersRelationManager;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Customers';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Customer';
    protected static ?string $pluralModelLabel = 'Customers';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('name'),
            TextEntry::make('email'),
            TextEntry::make('profile.phone')->label('Phone'),
            TextEntry::make('created_at')->dateTime()->label('Registered'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('profile.phone')->label('Phone'),
                TextColumn::make('created_at')->dateTime()->sortable()->label('Registered'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([ViewAction::make()]);
    }

    public static function getRelationManagers(): array
    {
        return [OrdersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view'  => ViewCustomer::route('/{record}'),
        ];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Customers/Filament/Resources/
git commit -m "feat: CustomerResource — read-only with orders relation manager"
```

---

### Task 14: CouponResource

**Files:**
- Create: `app/Modules/Cart/Filament/Resources/CouponResource.php` and pages/relation managers

- [ ] **Step 1: Create directories**

```bash
mkdir -p backend/app/Modules/Cart/Filament/Resources/CouponResource/Pages
mkdir -p backend/app/Modules/Cart/Filament/Resources/CouponResource/RelationManagers
```

- [ ] **Step 2: Create page stubs**

Create `Pages/ListCoupons.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Cart\Filament\Resources\CouponResource\Pages;
use App\Modules\Cart\Filament\Resources\CouponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
```

Create `Pages/CreateCoupon.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Cart\Filament\Resources\CouponResource\Pages;
use App\Modules\Cart\Filament\Resources\CouponResource;
use Filament\Resources\Pages\CreateRecord;
class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;
}
```

Create `Pages/EditCoupon.php`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Cart\Filament\Resources\CouponResource\Pages;
use App\Modules\Cart\Filament\Resources\CouponResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
```

- [ ] **Step 3: Create UsageHistoryRelationManager**

Create `RelationManagers/UsageHistoryRelationManager.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Cart\Filament\Resources\CouponResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsageHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';
    protected static ?string $title = 'Usage History';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Customer'),
                TextColumn::make('order.order_number')->label('Order'),
                TextColumn::make('discount_amount_fils')
                    ->label('Discount (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3) . ' BHD'),
                TextColumn::make('created_at')->dateTime()->label('Used At')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(25);
    }
}
```

- [ ] **Step 4: Create CouponResource**

Create `app/Modules/Cart/Filament/Resources/CouponResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Cart\Filament\Resources;

use App\Modules\Cart\Filament\Resources\CouponResource\Pages\CreateCoupon;
use App\Modules\Cart\Filament\Resources\CouponResource\Pages\EditCoupon;
use App\Modules\Cart\Filament\Resources\CouponResource\Pages\ListCoupons;
use App\Modules\Cart\Filament\Resources\CouponResource\RelationManagers\UsageHistoryRelationManager;
use App\Modules\Cart\Models\Coupon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Coupons';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')
                ->required()
                ->unique(ignoreRecord: true)
                ->alphaDash()
                ->maxLength(50),
            Select::make('discount_type')
                ->options([
                    'percentage'  => 'Percentage (%)',
                    'fixed_fils'  => 'Fixed Amount (fils)',
                ])
                ->required()
                ->live(),
            TextInput::make('discount_value')
                ->numeric()
                ->required()
                ->minValue(1),
            TextInput::make('minimum_order_amount_fils')
                ->label('Minimum Order (fils)')
                ->numeric()
                ->nullable(),
            TextInput::make('maximum_discount_fils')
                ->label('Maximum Discount (fils)')
                ->numeric()
                ->nullable(),
            TextInput::make('max_uses_global')
                ->label('Global Use Limit')
                ->numeric()
                ->nullable(),
            TextInput::make('max_uses_per_user')
                ->label('Per-User Limit')
                ->numeric()
                ->nullable(),
            DateTimePicker::make('starts_at')
                ->label('Start Date')
                ->nullable(),
            DateTimePicker::make('expires_at')
                ->label('Expiry Date')
                ->nullable(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable(),
                BadgeColumn::make('discount_type')
                    ->colors(['info' => 'percentage', 'success' => 'fixed_fils']),
                TextColumn::make('discount_value'),
                TextColumn::make('expires_at')->dateTime()->label('Expires'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([EditAction::make()]);
    }

    public static function getRelationManagers(): array
    {
        return [UsageHistoryRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit'   => EditCoupon::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 5: Ensure Coupon model has usages relation**

Verify `app/Modules/Cart/Models/Coupon.php` has:
```php
public function usages(): HasMany
{
    return $this->hasMany(CouponUsage::class);
}
```
If missing, add it.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Cart/Filament/Resources/
git commit -m "feat: CouponResource — full CRUD with usage history relation manager"
```

---

### Task 15: Feature tests — admin resources

**Files:**
- Create: `tests/Feature/Admin/OrderResourceTest.php`
- Create: `tests/Feature/Admin/ProductResourceTest.php`

- [ ] **Step 1: Create OrderResourceTest**

Create `tests/Feature/Admin/OrderResourceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $role = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.com',
            'password' => bcrypt('pw'),
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function makeOrder(): Order
    {
        $customer = User::create([
            'name'     => 'Customer',
            'email'    => 'customer@test.com',
            'password' => bcrypt('pw'),
        ]);
        return Order::create([
            'order_number'   => 'ORD-2026-001',
            'user_id'        => $customer->id,
            'order_status'   => 'paid',
            'subtotal_fils'  => 10000,
            'vat_fils'       => 1000,
            'total_fils'     => 11000,
            'payment_method' => 'card',
            'locale'         => 'ar',
        ]);
    }

    public function test_admin_can_list_orders(): void
    {
        $admin = $this->makeAdmin();
        $this->makeOrder();

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk();
    }

    public function test_non_admin_cannot_access_orders(): void
    {
        $user = User::create([
            'name'     => 'Customer',
            'email'    => 'customer@test.com',
            'password' => bcrypt('pw'),
        ]);

        $this->actingAs($user)
            ->get('/admin/orders')
            ->assertForbidden();
    }

    public function test_fulfill_action_updates_order(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder();

        $this->actingAs($admin);

        Livewire::test(\App\Modules\Orders\Filament\Resources\OrderResource\Pages\ListOrders::class)
            ->callTableAction('fulfill', $order, data: ['tracking_number' => 'TRK-999'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'order_status'   => 'fulfilled',
            'tracking_number' => 'TRK-999',
        ]);
    }
}
```

- [ ] **Step 2: Create ProductResourceTest**

Create `tests/Feature/Admin/ProductResourceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $role = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.com',
            'password' => bcrypt('pw'),
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name'     => ['ar' => 'تصنيف', 'en' => 'Category'],
            'slug'     => 'category',
        ]);
    }

    public function test_admin_can_list_products(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get('/admin/products')
            ->assertOk();
    }

    public function test_create_product_form_validates_required_fields(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        Livewire::test(\App\Modules\Catalog\Filament\Resources\ProductResource\Pages\CreateProduct::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['name.ar', 'name.en', 'base_price_fils', 'category_id']);
    }

    public function test_create_product_saves_bilingual_name(): void
    {
        $admin    = $this->makeAdmin();
        $category = $this->makeCategory();
        $this->actingAs($admin);

        Livewire::test(\App\Modules\Catalog\Filament\Resources\ProductResource\Pages\CreateProduct::class)
            ->fillForm([
                'name'            => ['ar' => 'منتج', 'en' => 'Product'],
                'base_price_fils' => 5000,
                'category_id'     => $category->id,
                'is_active'       => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', ['base_price_fils' => 5000]);
        $product = Product::first();
        $this->assertEquals('منتج', $product->name['ar']);
        $this->assertEquals('Product', $product->name['en']);
    }
}
```

- [ ] **Step 3: Run all admin tests**

```bash
php artisan test tests/Feature/Admin/ --testdox
```
Expected: All tests PASS.

- [ ] **Step 4: Run full test suite to catch regressions**

```bash
php artisan test --parallel
```
Expected: No regressions introduced.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Admin/
git commit -m "test: admin resource feature tests — access, validation, actions"
```

---

### Task 16: Seed admin user and final check

- [ ] **Step 1: Run seeder**

```bash
php artisan db:seed --class=AdminSeeder
```
Expected: `super_admin` role created, admin user created with credentials from `.env`.

- [ ] **Step 2: Smoke test the panel**

```bash
php artisan serve
```
Open `http://localhost:8000/admin` in browser. Log in with ADMIN_EMAIL/ADMIN_PASSWORD from `.env`.
Verify: Orders, Products, Categories, Attributes, Transactions, Refunds, Customers, Coupons all appear.

- [ ] **Step 3: Commit**

```bash
git add .
git commit -m "feat: Filament admin panel — complete Phase 2 admin module"
```
