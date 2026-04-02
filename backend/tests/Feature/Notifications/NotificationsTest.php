<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Notifications\Mail\OrderConfirmationMail;
use App\Modules\Notifications\Mail\PaymentReceiptMail;
use App\Modules\Notifications\Mail\ShippingUpdateMail;
use App\Modules\Orders\Events\OrderFulfilled;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Events\PaymentCaptured;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeVariantWithStock(int $priceFils = 5000, int $stock = 10): Variant
    {
        $category = Category::create([
            'name' => ['ar' => 'قسم', 'en' => 'Category'],
            'slug' => 'cat-'.uniqid(),
        ]);

        $product = Product::create([
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $category->id,
            'base_price_fils' => $priceFils,
            'is_available' => true,
        ]);

        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'attributes' => [],
            'price_fils' => $priceFils,
            'is_available' => true,
        ]);

        InventoryItem::create([
            'variant_id' => $variant->id,
            'stock_quantity' => $stock,
        ]);

        return $variant;
    }

    private function makeOrder(User $user, string $locale = 'ar'): Order
    {
        $variant = $this->makeVariantWithStock();
        $product = $variant->product;

        $address = CustomerAddress::create([
            'user_id' => $user->id,
            'address_type' => 'shipping',
            'recipient_name' => 'Test Address',
            'phone' => '33123456',
            'governorate' => 'Manama',
            'district' => 'Adliya',
            'building_number' => '123',
            'street_address' => 'Test Street',
            'is_default' => true,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'user_id' => $user->id,
            'guest_email' => null,
            'order_status' => 'pending',
            'subtotal_fils' => $variant->price_fils,
            'coupon_discount_fils' => 0,
            'coupon_code' => null,
            'vat_fils' => intval($variant->price_fils * 0.1),
            'delivery_fee_fils' => 0,
            'total_fils' => $variant->price_fils + intval($variant->price_fils * 0.1),
            'payment_method' => 'card',
            'shipping_address_id' => $address->id,
            'shipping_address_snapshot' => [
                'recipient_name' => $address->recipient_name,
                'phone' => $address->phone,
                'governorate' => $address->governorate,
            ],
            'billing_address_id' => $address->id,
            'billing_address_snapshot' => [
                'recipient_name' => $address->recipient_name,
                'phone' => $address->phone,
                'governorate' => $address->governorate,
            ],
            'delivery_zone_id' => null,
            'delivery_method_id' => null,
            'notes' => null,
            'locale' => $locale,
            'tracking_number' => null,
            'fulfilled_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'product_name' => $product->name,
            'variant_attributes' => [],
            'quantity' => 1,
            'price_fils_per_unit' => $variant->price_fils,
            'total_fils' => $variant->price_fils,
        ]);

        return $order;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function test_order_placed_event_dispatches_confirmation_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com']);
        $order = $this->makeOrder($user);
        $items = [];

        OrderPlaced::dispatch($order, $items);

        Mail::assertQueued(OrderConfirmationMail::class, fn ($mail) => $mail->order->id === $order->id &&
            $mail->order->user->email === 'test@example.com'
        );
    }

    public function test_order_confirmation_mail_uses_ar_locale_for_ar_order(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com']);
        $order = $this->makeOrder($user, 'ar');
        $items = [];

        OrderPlaced::dispatch($order, $items);

        Mail::assertQueued(OrderConfirmationMail::class, fn ($mail) => str_contains($mail->envelope()->subject, 'تأكيد')
        );
    }

    public function test_order_confirmation_mail_uses_en_locale_for_en_order(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com']);
        $order = $this->makeOrder($user, 'en');
        $items = [];

        OrderPlaced::dispatch($order, $items);

        Mail::assertQueued(OrderConfirmationMail::class, fn ($mail) => str_contains($mail->envelope()->subject, 'Order Confirmation')
        );
    }

    public function test_payment_captured_event_dispatches_receipt_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com']);
        $order = $this->makeOrder($user);

        $transaction = TapTransaction::create([
            'order_id' => $order->id,
            'attempt_number' => 1,
            'tap_charge_id' => 'chg_test_123',
            'amount_fils' => $order->total_fils,
            'currency' => 'BHD',
            'status' => 'captured',
            'payment_method' => 'card',
            'source_id' => 'src_test_123',
            'tap_response' => [],
            'failure_reason' => null,
            'redirect_url' => null,
            'webhook_received_at' => now(),
        ]);

        PaymentCaptured::dispatch($order);

        Mail::assertQueued(PaymentReceiptMail::class, fn ($mail) => $mail->order->id === $order->id &&
            $mail->transaction->id === $transaction->id
        );
    }

    public function test_payment_receipt_mail_uses_ar_locale_for_ar_order(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com']);
        $order = $this->makeOrder($user, 'ar');

        TapTransaction::create([
            'order_id' => $order->id,
            'attempt_number' => 1,
            'tap_charge_id' => 'chg_test_123',
            'amount_fils' => $order->total_fils,
            'currency' => 'BHD',
            'status' => 'captured',
            'payment_method' => 'card',
            'source_id' => 'src_test_123',
            'tap_response' => [],
            'failure_reason' => null,
            'redirect_url' => null,
            'webhook_received_at' => now(),
        ]);

        PaymentCaptured::dispatch($order);

        Mail::assertQueued(PaymentReceiptMail::class, fn ($mail) => str_contains($mail->envelope()->subject, 'إيصال')
        );
    }

    public function test_payment_receipt_mail_uses_en_locale_for_en_order(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com']);
        $order = $this->makeOrder($user, 'en');

        TapTransaction::create([
            'order_id' => $order->id,
            'attempt_number' => 1,
            'tap_charge_id' => 'chg_test_123',
            'amount_fils' => $order->total_fils,
            'currency' => 'BHD',
            'status' => 'captured',
            'payment_method' => 'card',
            'source_id' => 'src_test_123',
            'tap_response' => [],
            'failure_reason' => null,
            'redirect_url' => null,
            'webhook_received_at' => now(),
        ]);

        PaymentCaptured::dispatch($order);

        Mail::assertQueued(PaymentReceiptMail::class, fn ($mail) => str_contains($mail->envelope()->subject, 'Payment Receipt')
        );
    }

    public function test_order_fulfilled_event_dispatches_shipping_update_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com']);
        $order = $this->makeOrder($user);
        $order->update(['tracking_number' => 'TRACK123']);

        OrderFulfilled::dispatch($order);

        Mail::assertQueued(ShippingUpdateMail::class, fn ($mail) => $mail->order->id === $order->id
        );
    }

    public function test_shipping_update_mail_uses_ar_locale_for_ar_order(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com']);
        $order = $this->makeOrder($user, 'ar');
        $order->update(['tracking_number' => 'TRACK123']);

        OrderFulfilled::dispatch($order);

        Mail::assertQueued(ShippingUpdateMail::class, fn ($mail) => str_contains($mail->envelope()->subject, 'شحن')
        );
    }

    public function test_shipping_update_mail_uses_en_locale_for_en_order(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com']);
        $order = $this->makeOrder($user, 'en');
        $order->update(['tracking_number' => 'TRACK123']);

        OrderFulfilled::dispatch($order);

        Mail::assertQueued(ShippingUpdateMail::class, fn ($mail) => str_contains($mail->envelope()->subject, 'Shipped')
        );
    }

    public function test_shipping_update_mail_handles_missing_tracking_number(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com']);
        $order = $this->makeOrder($user);
        // tracking_number is null by default

        OrderFulfilled::dispatch($order);

        Mail::assertQueued(ShippingUpdateMail::class, fn ($mail) => $mail->order->id === $order->id
        );
    }
}
