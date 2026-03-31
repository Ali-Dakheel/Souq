# Notifications Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire three transactional emails (order confirmation, payment receipt, shipping update) through Resend, with Arabic/English locale support derived from `order.locale`.

**Architecture:** Each mailable is queued on the `notifications` queue, sets the app locale from `order->locale` before rendering, and uses Markdown templates backed by translation files. Listeners registered in `NotificationsServiceProvider` respond to domain events already fired by Orders and Payments modules.

**Tech Stack:** Laravel 13, `resend/resend-laravel`, Laravel Mailables (ShouldQueue), Pest v3, Redis queue

**Dependency:** Plan 1 Task 4 adds `locale` to `OrderService::checkout()` and `Order` model `$fillable`. Notifications Plan assumes those changes are already applied. If running Notifications Plan standalone, apply the Order model + migration changes from Plan 1 first.

---

### Task 1: Install Resend and configure mail

**Files:**
- Modify: `backend/composer.json` (via composer require)
- Modify: `backend/.env.example`
- Modify: `backend/.env.testing`
- Modify: `backend/config/mail.php` (verify `resend` mailer entry exists)

- [ ] **Step 1: Install resend/resend-laravel**

```bash
cd backend
composer require resend/resend-laravel
```

Expected: Package installs successfully. `composer.json` updated.

- [ ] **Step 2: Add env vars to .env.example**

Open `backend/.env.example` and add after the `MAIL_*` block:

```
RESEND_API_KEY=re_your_key_here
MAIL_MAILER=resend
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

- [ ] **Step 3: Configure .env.testing for mail**

Open `backend/.env.testing` and add/update:

```
MAIL_MAILER=array
RESEND_API_KEY=re_test_fake_key
MAIL_FROM_ADDRESS="test@example.com"
MAIL_FROM_NAME="Test Store"
```

Using `array` mailer in testing means no real HTTP calls — `Mail::fake()` intercepts before delivery anyway, but `array` mailer is an extra safety net.

- [ ] **Step 4: Verify resend mailer is registered in config/mail.php**

Read `backend/config/mail.php`. If the `mailers` array already has a `resend` entry (Laravel Resend package publishes it), you're done.

If not, add inside the `'mailers'` array:

```php
'resend' => [
    'transport' => 'resend',
],
```

- [ ] **Step 5: Commit**

```bash
cd backend
git add composer.json composer.lock .env.example config/mail.php
git commit -m "feat(notifications): install resend/resend-laravel, configure mail"
```

---

### Task 2: Create translation files for email content

**Files:**
- Create: `backend/lang/ar/emails.php`
- Create: `backend/lang/en/emails.php`

- [ ] **Step 1: Create Arabic email translations**

```php
<?php
// backend/lang/ar/emails.php

return [
    // Order confirmation
    'order_confirmation_subject'  => 'تأكيد الطلب :order_number',
    'order_confirmation_greeting' => 'شكراً لطلبك!',
    'order_confirmation_intro'    => 'تم استلام طلبك وسيتم معالجته قريباً.',
    'order_number'                => 'رقم الطلب',
    'order_items'                 => 'المنتجات',
    'item_name'                   => 'المنتج',
    'item_qty'                    => 'الكمية',
    'item_price'                  => 'السعر',
    'subtotal'                    => 'المجموع الفرعي',
    'vat'                         => 'ضريبة القيمة المضافة (10%)',
    'total'                       => 'الإجمالي',

    // Payment receipt
    'payment_receipt_subject'     => 'إيصال الدفع - طلب :order_number',
    'payment_receipt_greeting'    => 'تم استلام دفعتك!',
    'payment_receipt_intro'       => 'تم تأكيد دفعتك بنجاح.',
    'charge_id'                   => 'رقم العملية',
    'amount_paid'                 => 'المبلغ المدفوع',
    'invoice_ref'                 => 'رقم الفاتورة',

    // Shipping update
    'shipping_update_subject'     => 'تم شحن طلبك :order_number',
    'shipping_update_greeting'    => 'طلبك في الطريق!',
    'shipping_update_intro'       => 'تم شحن طلبك وسيصل إليك قريباً.',
    'tracking_number'             => 'رقم التتبع',
    'no_tracking'                 => 'سيتم إرسال رقم التتبع عند توفره.',

    // Shared
    'hello'                       => 'مرحباً :name،',
    'regards'                     => 'مع التحية،',
    'store_name'                  => 'فريق المتجر',
];
```

- [ ] **Step 2: Create English email translations**

```php
<?php
// backend/lang/en/emails.php

return [
    // Order confirmation
    'order_confirmation_subject'  => 'Order Confirmation :order_number',
    'order_confirmation_greeting' => 'Thank you for your order!',
    'order_confirmation_intro'    => 'We have received your order and will process it shortly.',
    'order_number'                => 'Order Number',
    'order_items'                 => 'Items',
    'item_name'                   => 'Product',
    'item_qty'                    => 'Qty',
    'item_price'                  => 'Price',
    'subtotal'                    => 'Subtotal',
    'vat'                         => 'VAT (10%)',
    'total'                       => 'Total',

    // Payment receipt
    'payment_receipt_subject'     => 'Payment Receipt - Order :order_number',
    'payment_receipt_greeting'    => 'Payment received!',
    'payment_receipt_intro'       => 'Your payment has been confirmed successfully.',
    'charge_id'                   => 'Charge ID',
    'amount_paid'                 => 'Amount Paid',
    'invoice_ref'                 => 'Invoice Reference',

    // Shipping update
    'shipping_update_subject'     => 'Your order :order_number has shipped',
    'shipping_update_greeting'    => 'Your order is on its way!',
    'shipping_update_intro'       => 'Your order has been shipped and will arrive soon.',
    'tracking_number'             => 'Tracking Number',
    'no_tracking'                 => 'Tracking information will be provided when available.',

    // Shared
    'hello'                       => 'Hello :name,',
    'regards'                     => 'Regards,',
    'store_name'                  => 'The Store Team',
];
```

- [ ] **Step 3: Commit**

```bash
cd backend
git add lang/ar/emails.php lang/en/emails.php
git commit -m "feat(notifications): add AR/EN email translation files"
```

---

### Task 3: OrderConfirmationMail

**Files:**
- Create: `backend/app/Modules/Notifications/Mail/OrderConfirmationMail.php`
- Create: `backend/resources/views/emails/order-confirmation.blade.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Notifications/OrderConfirmationMailTest.php`:

```php
<?php

use App\Modules\Notifications\Mail\OrderConfirmationMail;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Customers\Models\User;
use Illuminate\Support\Facades\Mail;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sends order confirmation email with correct recipient', function () {
    Mail::fake();

    $user = User::create([
        'name' => 'Test User',
        'email' => 'customer@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-TEST-001',
        'order_status' => 'pending',
        'subtotal_fils' => 10000,
        'vat_fils' => 1000,
        'total_fils' => 11000,
        'locale' => 'en',
        'shipping_address' => ['line1' => '123 Test St', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => 1,
        'variant_id' => 1,
        'product_name' => ['ar' => 'منتج', 'en' => 'Product'],
        'variant_sku' => 'SKU-001',
        'quantity' => 2,
        'unit_price_fils' => 5000,
        'total_price_fils' => 10000,
    ]);

    $mailable = new OrderConfirmationMail($order);
    $mailable->assertTo('customer@example.com');
});

it('sets locale from order locale on render', function () {
    $user = User::create([
        'name' => 'Test',
        'email' => 'ar@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-TEST-002',
        'order_status' => 'pending',
        'subtotal_fils' => 5000,
        'vat_fils' => 500,
        'total_fils' => 5500,
        'locale' => 'ar',
        'shipping_address' => ['line1' => '123 Test', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    $mailable = new OrderConfirmationMail($order);
    $content = $mailable->content();

    expect(app()->getLocale())->toBe('ar');
});

it('queues on the notifications queue', function () {
    $user = User::create([
        'name' => 'Test',
        'email' => 'q@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-TEST-003',
        'order_status' => 'pending',
        'subtotal_fils' => 5000,
        'vat_fils' => 500,
        'total_fils' => 5500,
        'locale' => 'en',
        'shipping_address' => ['line1' => '123 Test', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    $mailable = new OrderConfirmationMail($order);
    expect($mailable->queue)->toBe('notifications');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd backend
php artisan test tests/Feature/Notifications/OrderConfirmationMailTest.php --no-coverage
```

Expected: FAIL — `OrderConfirmationMail` class not found.

- [ ] **Step 3: Create the mailable**

```php
<?php
// backend/app/Modules/Notifications/Mail/OrderConfirmationMail.php

namespace App\Modules\Notifications\Mail;

use App\Modules\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $queue = 'notifications';
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        app()->setLocale($this->order->locale ?? 'ar');

        return new Envelope(
            to: $this->order->user->email,
            subject: __('emails.order_confirmation_subject', [
                'order_number' => $this->order->order_number,
            ]),
        );
    }

    public function content(): Content
    {
        app()->setLocale($this->order->locale ?? 'ar');

        $subtotalBhd = number_format($this->order->subtotal_fils / 1000, 3);
        $vatBhd      = number_format($this->order->vat_fils / 1000, 3);
        $totalBhd    = number_format($this->order->total_fils / 1000, 3);

        return new Content(
            markdown: 'emails.order-confirmation',
            with: [
                'order'       => $this->order,
                'items'       => $this->order->items,
                'subtotalBhd' => $subtotalBhd,
                'vatBhd'      => $vatBhd,
                'totalBhd'    => $totalBhd,
            ],
        );
    }
}
```

- [ ] **Step 4: Create the Blade Markdown template**

```blade
{{-- backend/resources/views/emails/order-confirmation.blade.php --}}
<x-mail::message>
# {{ __('emails.order_confirmation_greeting') }}

{{ __('emails.hello', ['name' => $order->user->name]) }}

{{ __('emails.order_confirmation_intro') }}

**{{ __('emails.order_number') }}:** {{ $order->order_number }}

<x-mail::table>
| {{ __('emails.item_name') }} | {{ __('emails.item_qty') }} | {{ __('emails.item_price') }} |
| :--- | :---: | ---: |
@foreach ($items as $item)
| {{ is_array($item->product_name) ? ($item->product_name[app()->getLocale()] ?? $item->product_name['en'] ?? '') : $item->product_name }} | {{ $item->quantity }} | {{ number_format($item->unit_price_fils / 1000, 3) }} BHD |
@endforeach
</x-mail::table>

| | |
| :--- | ---: |
| {{ __('emails.subtotal') }} | {{ $subtotalBhd }} BHD |
| {{ __('emails.vat') }} | {{ $vatBhd }} BHD |
| **{{ __('emails.total') }}** | **{{ $totalBhd }} BHD** |

{{ __('emails.regards') }}
{{ __('emails.store_name') }}
</x-mail::message>
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
cd backend
php artisan test tests/Feature/Notifications/OrderConfirmationMailTest.php --no-coverage
```

Expected: 3 tests, 3 passed.

- [ ] **Step 6: Commit**

```bash
cd backend
git add app/Modules/Notifications/Mail/OrderConfirmationMail.php \
        resources/views/emails/order-confirmation.blade.php \
        tests/Feature/Notifications/OrderConfirmationMailTest.php
git commit -m "feat(notifications): add OrderConfirmationMail with AR/EN locale"
```

---

### Task 4: PaymentReceiptMail

**Files:**
- Create: `backend/app/Modules/Notifications/Mail/PaymentReceiptMail.php`
- Create: `backend/resources/views/emails/payment-receipt.blade.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Notifications/PaymentReceiptMailTest.php`:

```php
<?php

use App\Modules\Notifications\Mail\PaymentReceiptMail;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\TapTransaction;
use App\Modules\Customers\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sends payment receipt to the order owner', function () {
    $user = User::create([
        'name' => 'Receipt User',
        'email' => 'receipt@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-PAY-001',
        'order_status' => 'paid',
        'subtotal_fils' => 10000,
        'vat_fils' => 1000,
        'total_fils' => 11000,
        'locale' => 'en',
        'shipping_address' => ['line1' => '123 Test', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    $transaction = TapTransaction::create([
        'order_id'       => $order->id,
        'tap_charge_id'  => 'chg_test_abc123',
        'amount_fils'    => 11000,
        'currency'       => 'BHD',
        'status'         => 'CAPTURED',
        'attempt_number' => 1,
        'tap_response'   => ['id' => 'chg_test_abc123'],
    ]);

    $mailable = new PaymentReceiptMail($order, $transaction);
    $mailable->assertTo('receipt@example.com');
});

it('sets locale from order on render', function () {
    $user = User::create([
        'name' => 'Arabic User',
        'email' => 'ar-receipt@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-PAY-002',
        'order_status' => 'paid',
        'subtotal_fils' => 5000,
        'vat_fils' => 500,
        'total_fils' => 5500,
        'locale' => 'ar',
        'shipping_address' => ['line1' => '123', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    $transaction = TapTransaction::create([
        'order_id'       => $order->id,
        'tap_charge_id'  => 'chg_test_xyz',
        'amount_fils'    => 5500,
        'currency'       => 'BHD',
        'status'         => 'CAPTURED',
        'attempt_number' => 1,
        'tap_response'   => ['id' => 'chg_test_xyz'],
    ]);

    $mailable = new PaymentReceiptMail($order, $transaction);
    $mailable->content();

    expect(app()->getLocale())->toBe('ar');
});

it('queues on the notifications queue', function () {
    $user = User::create([
        'name' => 'Q User',
        'email' => 'q2@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-PAY-003',
        'order_status' => 'paid',
        'subtotal_fils' => 5000,
        'vat_fils' => 500,
        'total_fils' => 5500,
        'locale' => 'en',
        'shipping_address' => ['line1' => '123', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    $transaction = TapTransaction::create([
        'order_id'       => $order->id,
        'tap_charge_id'  => 'chg_q',
        'amount_fils'    => 5500,
        'currency'       => 'BHD',
        'status'         => 'CAPTURED',
        'attempt_number' => 1,
        'tap_response'   => [],
    ]);

    $mailable = new PaymentReceiptMail($order, $transaction);
    expect($mailable->queue)->toBe('notifications');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd backend
php artisan test tests/Feature/Notifications/PaymentReceiptMailTest.php --no-coverage
```

Expected: FAIL — `PaymentReceiptMail` class not found.

- [ ] **Step 3: Create the mailable**

```php
<?php
// backend/app/Modules/Notifications/Mail/PaymentReceiptMail.php

namespace App\Modules\Notifications\Mail;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $queue = 'notifications';
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly Order $order,
        public readonly TapTransaction $transaction,
    ) {}

    public function envelope(): Envelope
    {
        app()->setLocale($this->order->locale ?? 'ar');

        return new Envelope(
            to: $this->order->user->email,
            subject: __('emails.payment_receipt_subject', [
                'order_number' => $this->order->order_number,
            ]),
        );
    }

    public function content(): Content
    {
        app()->setLocale($this->order->locale ?? 'ar');

        return new Content(
            markdown: 'emails.payment-receipt',
            with: [
                'order'       => $this->order,
                'transaction' => $this->transaction,
                'amountBhd'   => number_format($this->transaction->amount_fils / 1000, 3),
            ],
        );
    }
}
```

- [ ] **Step 4: Create the Blade Markdown template**

```blade
{{-- backend/resources/views/emails/payment-receipt.blade.php --}}
<x-mail::message>
# {{ __('emails.payment_receipt_greeting') }}

{{ __('emails.hello', ['name' => $order->user->name]) }}

{{ __('emails.payment_receipt_intro') }}

**{{ __('emails.order_number') }}:** {{ $order->order_number }}

| | |
| :--- | ---: |
| {{ __('emails.charge_id') }} | {{ $transaction->tap_charge_id }} |
| {{ __('emails.invoice_ref') }} | {{ $order->order_number }} |
| **{{ __('emails.amount_paid') }}** | **{{ $amountBhd }} BHD** |

{{ __('emails.regards') }}
{{ __('emails.store_name') }}
</x-mail::message>
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
cd backend
php artisan test tests/Feature/Notifications/PaymentReceiptMailTest.php --no-coverage
```

Expected: 3 tests, 3 passed.

- [ ] **Step 6: Commit**

```bash
cd backend
git add app/Modules/Notifications/Mail/PaymentReceiptMail.php \
        resources/views/emails/payment-receipt.blade.php \
        tests/Feature/Notifications/PaymentReceiptMailTest.php
git commit -m "feat(notifications): add PaymentReceiptMail with AR/EN locale"
```

---

### Task 5: ShippingUpdateMail

**Files:**
- Create: `backend/app/Modules/Notifications/Mail/ShippingUpdateMail.php`
- Create: `backend/resources/views/emails/shipping-update.blade.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Notifications/ShippingUpdateMailTest.php`:

```php
<?php

use App\Modules\Notifications\Mail\ShippingUpdateMail;
use App\Modules\Orders\Models\Order;
use App\Modules\Customers\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sends shipping update to the order owner', function () {
    $user = User::create([
        'name' => 'Ship User',
        'email' => 'ship@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id'         => $user->id,
        'order_number'    => 'ORD-SHIP-001',
        'order_status'    => 'fulfilled',
        'subtotal_fils'   => 10000,
        'vat_fils'        => 1000,
        'total_fils'      => 11000,
        'locale'          => 'en',
        'tracking_number' => 'TRACK123',
        'fulfilled_at'    => now(),
        'shipping_address' => ['line1' => '123 Test', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    $mailable = new ShippingUpdateMail($order);
    $mailable->assertTo('ship@example.com');
});

it('includes tracking number in content when present', function () {
    $user = User::create([
        'name' => 'Track User',
        'email' => 'track@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id'         => $user->id,
        'order_number'    => 'ORD-SHIP-002',
        'order_status'    => 'fulfilled',
        'subtotal_fils'   => 5000,
        'vat_fils'        => 500,
        'total_fils'      => 5500,
        'locale'          => 'en',
        'tracking_number' => 'TRACK-XYZ-789',
        'fulfilled_at'    => now(),
        'shipping_address' => ['line1' => '123', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    $mailable = new ShippingUpdateMail($order);
    $rendered = $mailable->render();

    expect($rendered)->toContain('TRACK-XYZ-789');
});

it('shows no-tracking message when tracking number is null', function () {
    $user = User::create([
        'name' => 'No Track User',
        'email' => 'notrack@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id'         => $user->id,
        'order_number'    => 'ORD-SHIP-003',
        'order_status'    => 'fulfilled',
        'subtotal_fils'   => 5000,
        'vat_fils'        => 500,
        'total_fils'      => 5500,
        'locale'          => 'en',
        'tracking_number' => null,
        'fulfilled_at'    => now(),
        'shipping_address' => ['line1' => '123', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    $mailable = new ShippingUpdateMail($order);
    $rendered = $mailable->render();

    // The no-tracking translation key value should appear
    app()->setLocale('en');
    expect($rendered)->toContain(__('emails.no_tracking'));
});

it('sets locale from order on render', function () {
    $user = User::create([
        'name' => 'AR Ship User',
        'email' => 'ar-ship@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id'      => $user->id,
        'order_number' => 'ORD-SHIP-004',
        'order_status' => 'fulfilled',
        'subtotal_fils' => 5000,
        'vat_fils'      => 500,
        'total_fils'    => 5500,
        'locale'        => 'ar',
        'fulfilled_at'  => now(),
        'shipping_address' => ['line1' => '123', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    $mailable = new ShippingUpdateMail($order);
    $mailable->content();

    expect(app()->getLocale())->toBe('ar');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd backend
php artisan test tests/Feature/Notifications/ShippingUpdateMailTest.php --no-coverage
```

Expected: FAIL — `ShippingUpdateMail` class not found.

- [ ] **Step 3: Create the mailable**

```php
<?php
// backend/app/Modules/Notifications/Mail/ShippingUpdateMail.php

namespace App\Modules\Notifications\Mail;

use App\Modules\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShippingUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $queue = 'notifications';
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        app()->setLocale($this->order->locale ?? 'ar');

        return new Envelope(
            to: $this->order->user->email,
            subject: __('emails.shipping_update_subject', [
                'order_number' => $this->order->order_number,
            ]),
        );
    }

    public function content(): Content
    {
        app()->setLocale($this->order->locale ?? 'ar');

        return new Content(
            markdown: 'emails.shipping-update',
            with: [
                'order'          => $this->order,
                'trackingNumber' => $this->order->tracking_number,
            ],
        );
    }
}
```

- [ ] **Step 4: Create the Blade Markdown template**

```blade
{{-- backend/resources/views/emails/shipping-update.blade.php --}}
<x-mail::message>
# {{ __('emails.shipping_update_greeting') }}

{{ __('emails.hello', ['name' => $order->user->name]) }}

{{ __('emails.shipping_update_intro') }}

**{{ __('emails.order_number') }}:** {{ $order->order_number }}

@if ($trackingNumber)
**{{ __('emails.tracking_number') }}:** {{ $trackingNumber }}
{{-- TODO Phase 3: add courier tracking link once courier integration is decided --}}
@else
{{ __('emails.no_tracking') }}
@endif

{{ __('emails.regards') }}
{{ __('emails.store_name') }}
</x-mail::message>
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
cd backend
php artisan test tests/Feature/Notifications/ShippingUpdateMailTest.php --no-coverage
```

Expected: 4 tests, 4 passed.

- [ ] **Step 6: Commit**

```bash
cd backend
git add app/Modules/Notifications/Mail/ShippingUpdateMail.php \
        resources/views/emails/shipping-update.blade.php \
        tests/Feature/Notifications/ShippingUpdateMailTest.php
git commit -m "feat(notifications): add ShippingUpdateMail with tracking number support"
```

---

### Task 6: Listeners and NotificationsServiceProvider

**Files:**
- Create: `backend/app/Modules/Notifications/Listeners/SendOrderConfirmationEmail.php`
- Create: `backend/app/Modules/Notifications/Listeners/SendPaymentReceiptEmail.php`
- Create: `backend/app/Modules/Notifications/Listeners/SendShippingUpdateEmail.php`
- Modify: `backend/app/Modules/Notifications/NotificationsServiceProvider.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Notifications/NotificationListenersTest.php`:

```php
<?php

use App\Modules\Notifications\Mail\OrderConfirmationMail;
use App\Modules\Notifications\Mail\PaymentReceiptMail;
use App\Modules\Notifications\Mail\ShippingUpdateMail;
use App\Modules\Orders\Events\OrderFulfilled;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Payments\Events\PaymentCaptured;
use App\Modules\Payments\Models\TapTransaction;
use App\Modules\Customers\Models\User;
use Illuminate\Support\Facades\Mail;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('sends order confirmation email when OrderPlaced is fired', function () {
    $user = User::create([
        'name' => 'Confirm User',
        'email' => 'confirm@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id'      => $user->id,
        'order_number' => 'ORD-L-001',
        'order_status' => 'pending',
        'subtotal_fils' => 10000,
        'vat_fils'      => 1000,
        'total_fils'    => 11000,
        'locale'        => 'en',
        'shipping_address' => ['line1' => '123', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    $items = [];

    event(new OrderPlaced($order, $items));

    Mail::assertQueued(OrderConfirmationMail::class, function ($mail) use ($order) {
        return $mail->order->id === $order->id;
    });
});

it('sends payment receipt when PaymentCaptured is fired', function () {
    $user = User::create([
        'name' => 'Pay User',
        'email' => 'pay@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id'      => $user->id,
        'order_number' => 'ORD-L-002',
        'order_status' => 'paid',
        'subtotal_fils' => 10000,
        'vat_fils'      => 1000,
        'total_fils'    => 11000,
        'locale'        => 'ar',
        'shipping_address' => ['line1' => '123', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    $transaction = TapTransaction::create([
        'order_id'       => $order->id,
        'tap_charge_id'  => 'chg_listener_test',
        'amount_fils'    => 11000,
        'currency'       => 'BHD',
        'status'         => 'CAPTURED',
        'attempt_number' => 1,
        'tap_response'   => [],
    ]);

    event(new PaymentCaptured($order));

    Mail::assertQueued(PaymentReceiptMail::class, function ($mail) use ($order) {
        return $mail->order->id === $order->id;
    });
});

it('sends shipping update when OrderFulfilled is fired', function () {
    $user = User::create([
        'name' => 'Fulfill User',
        'email' => 'fulfill@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id'         => $user->id,
        'order_number'    => 'ORD-L-003',
        'order_status'    => 'fulfilled',
        'subtotal_fils'   => 10000,
        'vat_fils'        => 1000,
        'total_fils'      => 11000,
        'locale'          => 'en',
        'tracking_number' => 'TRK-999',
        'fulfilled_at'    => now(),
        'shipping_address' => ['line1' => '123', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    event(new OrderFulfilled($order));

    Mail::assertQueued(ShippingUpdateMail::class, function ($mail) use ($order) {
        return $mail->order->id === $order->id;
    });
});

it('sends AR email for AR order locale', function () {
    $user = User::create([
        'name' => 'Arabic Locale User',
        'email' => 'ar-locale@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'user_id'      => $user->id,
        'order_number' => 'ORD-L-004',
        'order_status' => 'pending',
        'subtotal_fils' => 5000,
        'vat_fils'      => 500,
        'total_fils'    => 5500,
        'locale'        => 'ar',
        'shipping_address' => ['line1' => '123', 'city' => 'Manama', 'country' => 'BH'],
    ]);

    event(new OrderPlaced($order, []));

    Mail::assertQueued(OrderConfirmationMail::class, function ($mail) {
        // Render the email and verify locale was set to ar
        $mail->content();
        return app()->getLocale() === 'ar';
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd backend
php artisan test tests/Feature/Notifications/NotificationListenersTest.php --no-coverage
```

Expected: FAIL — mailables not queued (listeners not registered).

- [ ] **Step 3: Create SendOrderConfirmationEmail listener**

```php
<?php
// backend/app/Modules/Notifications/Listeners/SendOrderConfirmationEmail.php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Mail\OrderConfirmationMail;
use App\Modules\Orders\Events\OrderPlaced;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail
{
    public function handle(OrderPlaced $event): void
    {
        Mail::queue(new OrderConfirmationMail($event->order));
    }
}
```

- [ ] **Step 4: Create SendPaymentReceiptEmail listener**

```php
<?php
// backend/app/Modules/Notifications/Listeners/SendPaymentReceiptEmail.php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Mail\PaymentReceiptMail;
use App\Modules\Payments\Events\PaymentCaptured;
use Illuminate\Support\Facades\Mail;

class SendPaymentReceiptEmail
{
    public function handle(PaymentCaptured $event): void
    {
        // Load the most recent captured transaction for this order
        $transaction = $event->order->tapTransactions()
            ->where('status', 'CAPTURED')
            ->latest()
            ->firstOrFail();

        Mail::queue(new PaymentReceiptMail($event->order, $transaction));
    }
}
```

- [ ] **Step 5: Create SendShippingUpdateEmail listener**

```php
<?php
// backend/app/Modules/Notifications/Listeners/SendShippingUpdateEmail.php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Mail\ShippingUpdateMail;
use App\Modules\Orders\Events\OrderFulfilled;
use Illuminate\Support\Facades\Mail;

class SendShippingUpdateEmail
{
    public function handle(OrderFulfilled $event): void
    {
        Mail::queue(new ShippingUpdateMail($event->order));
    }
}
```

- [ ] **Step 6: Register listeners in NotificationsServiceProvider**

Read `backend/app/Modules/Notifications/NotificationsServiceProvider.php`. The current `boot()` is empty. Replace the entire file with:

```php
<?php

namespace App\Modules\Notifications;

use App\Modules\Notifications\Listeners\SendOrderConfirmationEmail;
use App\Modules\Notifications\Listeners\SendPaymentReceiptEmail;
use App\Modules\Notifications\Listeners\SendShippingUpdateEmail;
use App\Modules\Orders\Events\OrderFulfilled;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Payments\Events\PaymentCaptured;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderPlaced::class, SendOrderConfirmationEmail::class);
        Event::listen(PaymentCaptured::class, SendPaymentReceiptEmail::class);
        Event::listen(OrderFulfilled::class, SendShippingUpdateEmail::class);
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
cd backend
php artisan test tests/Feature/Notifications/NotificationListenersTest.php --no-coverage
```

Expected: 4 tests, 4 passed.

- [ ] **Step 8: Commit**

```bash
cd backend
git add app/Modules/Notifications/Listeners/ \
        app/Modules/Notifications/NotificationsServiceProvider.php \
        tests/Feature/Notifications/NotificationListenersTest.php
git commit -m "feat(notifications): register listeners — order confirm, payment receipt, shipping update"
```

---

### Task 7: Horizon queue config and final test run

**Files:**
- Modify: `backend/config/horizon.php` (add `notifications` queue)

- [ ] **Step 1: Add notifications queue to Horizon**

Read `backend/config/horizon.php`. Find the `environments` → `production` (or `local`) block that lists `queue` entries. Add `'notifications'` to the supervised queues list.

The queue block typically looks like:

```php
'supervisor-1' => [
    'maxProcesses' => 1,
    'queue'        => ['default'],
],
```

Update to:

```php
'supervisor-1' => [
    'maxProcesses' => 2,
    'queue'        => ['default', 'notifications'],
],
```

Apply the same change to any other environment blocks present (local, staging, production).

- [ ] **Step 2: Run the full notifications test suite**

```bash
cd backend
php artisan test tests/Feature/Notifications/ --no-coverage
```

Expected: All tests in the suite pass (approximately 14 tests across 4 test files).

- [ ] **Step 3: Run full backend test suite to confirm no regressions**

```bash
cd backend
php artisan test --no-coverage
```

Expected: All existing tests still pass.

- [ ] **Step 4: Commit**

```bash
cd backend
git add config/horizon.php
git commit -m "feat(notifications): add notifications queue to Horizon config"
```

---

## Self-Review

### Spec coverage

| Spec requirement | Task |
|---|---|
| Install `resend/resend-laravel` | Task 1 |
| `MAIL_MAILER=resend`, `RESEND_API_KEY` in env | Task 1 |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` configurable | Task 1 |
| `OrderConfirmationMail` — `OrderPlaced`, `notifications` queue | Tasks 3, 6 |
| `PaymentReceiptMail` — `PaymentCaptured`, `notifications` queue | Tasks 4, 6 |
| `ShippingUpdateMail` — `OrderFulfilled`, `notifications` queue | Tasks 5, 6 |
| All mailables implement `ShouldQueue` | Tasks 3, 4, 5 |
| All mailables in `app/Modules/Notifications/Mail/` | Tasks 3, 4, 5 |
| `app()->setLocale($order->locale)` in `content()` | Tasks 3, 4, 5 |
| Markdown templates use `__()` translation keys | Tasks 3, 4, 5 |
| `lang/ar/emails.php` + `lang/en/emails.php` | Task 2 |
| Listeners in `app/Modules/Notifications/Listeners/` | Task 6 |
| Registered in `NotificationsServiceProvider::boot()` | Task 6 |
| Queue retries: 3 attempts, exponential backoff | Tasks 3, 4, 5 |
| Pest tests: `Mail::fake()`, assert mailable dispatched | Tasks 3, 4, 5, 6 |
| Test locale set correctly (AR/EN) | Tasks 3, 4, 5, 6 |

All spec requirements covered.

### Placeholder scan

No "TBD", no "TODO" except the intentional `TODO Phase 3` comment in the shipping template for courier links — which matches the spec's own "TODO (deferred)" note.

### Type consistency

- `OrderConfirmationMail($order)` — used consistently in Task 3 and Task 6.
- `PaymentReceiptMail($order, $transaction)` — used consistently in Task 4 and Task 6.
- `ShippingUpdateMail($order)` — used consistently in Task 5 and Task 6.
- `OrderPlaced($order, $items)` — matches existing event constructor.
- `PaymentCaptured($order)` — matches existing event constructor.
- `OrderFulfilled($order)` — matches existing event constructor.
- `$order->tapTransactions()` — relationship exists on `Order` model (used in Payments module).
