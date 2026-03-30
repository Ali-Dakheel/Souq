---
name: tap-payments
description: >
  Tap Payments API v2 integration for Bahrain ecommerce. Charge creation,
  BenefitPay QR, webhook handling, refunds, BHD amounts, gotchas.
  Auto-referenced when working in Payments module.
---

# Tap Payments Integration Patterns

## Critical gotchas — read first

1. **Amount normalization for webhooks**: Tap signs `100.0` and `100.00`
   differently. ALWAYS normalize: `number_format($fils / 1000, 3, '.', '')`
   before hash comparison. Never trust amount strings without normalizing.

2. **Tap retries webhooks TWICE only**: If your endpoint is down, Tap retries
   once, then marks as ERROR. Acknowledge immediately (200), process in queue.

3. **BHD has 3 decimal places**: Always `"10.500"` not `"10.5"`.
   Tap API will reject malformed amounts.

4. **BenefitPay domain registration**: Contact Tap support BEFORE going live.
   Without domain registration, BenefitPay QR will fail silently in production.

5. **3DS is mandatory**: All Bahrain card payments require 3DS for
   customer-initiated transactions. Redirect flow handles this automatically.

---

## Charge creation (Laravel)

```php
class TapPaymentService
{
    private string $baseUrl = 'https://api.tap.company/v2';

    public function createCharge(Order $order): array
    {
        // Amount ALWAYS from DB — never trust frontend
        $amountBhd = number_format($order->total_fils / 1000, 3, '.', '');

        $payload = [
            'amount'           => $amountBhd,
            'currency'         => 'BHD',
            'customer_initiated'=> true,
            'threeDSecure'     => true,
            'description'      => "Order #{$order->reference}",
            'reference'        => [
                'transaction' => $order->reference,
                'order'       => (string) $order->id,
            ],
            'customer'         => [
                'first_name' => $order->customer->first_name,
                'email'      => $order->customer->email,
                'phone'      => [
                    'country_code' => '973',
                    'number'       => $order->customer->phone,
                ],
            ],
            'source'           => ['id' => 'src_all'],
            'redirect'         => ['url' => route('checkout.return')],
            'post'             => ['url' => route('webhooks.tap')],
        ];

        $response = Http::withToken(config('services.tap.secret_key'))
            ->post("{$this->baseUrl}/charges", $payload)
            ->throw()
            ->json();

        // Store immediately before redirecting
        $order->update([
            'tap_charge_id' => $response['id'],
            'status'        => OrderStatus::INITIATED,
            'tap_response'  => $response,
        ]);

        return $response;
    }
}
```

## Webhook handler (Laravel)

```php
class TapWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        // Step 1: Verify signature FIRST — before anything
        $this->verifySignature($request);

        // Step 2: Queue for async processing
        ProcessTapWebhook::dispatch($request->all())->onQueue('payments');

        // Step 3: Acknowledge immediately (200)
        return response()->noContent();
    }

    private function verifySignature(Request $request): void
    {
        $received = $request->header('hashstring');
        $chargeId = $request->input('id');
        $amount   = $this->normalizeAmount($request->input('amount'));
        $status   = $request->input('status');
        $currency = $request->input('currency');

        $expected = hash_hmac(
            'sha256',
            "x_id{$chargeId}x_amount{$amount}x_currency{$currency}x_status{$status}",
            config('services.tap.webhook_secret')
        );

        if (!hash_equals($expected, $received)) {
            abort(400, 'Invalid webhook signature');
        }
    }

    private function normalizeAmount(string|float $amount): string
    {
        // "100.0" and "100.00" and "100.000" all → "100.000"
        return number_format((float) $amount, 3, '.', '');
    }
}
```

## Webhook job (idempotent)

```php
class ProcessTapWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(private array $payload) {}

    public function handle(PaymentService $service): void
    {
        $chargeId = $this->payload['id'];
        $order    = Order::where('tap_charge_id', $chargeId)->first();

        if (!$order) {
            Log::warning('Tap webhook: order not found', compact('chargeId'));
            return;
        }

        match ($this->payload['status']) {
            'CAPTURED'          => $service->handleCapture($order, $this->payload),
            'FAILED', 'ABANDONED' => $service->handleFailure($order, $this->payload),
            default             => null,
        };
    }

    // Prevents duplicate processing of same webhook
    public function uniqueId(): string
    {
        return $this->payload['id'] . '_' . $this->payload['status'];
    }
}
```

## Return URL handler (Laravel)

```php
class CheckoutReturnController extends Controller
{
    public function handle(Request $request): RedirectResponse
    {
        $tapId = $request->query('tap_id');

        // ALWAYS verify server-side — never trust query params
        $charge = Http::withToken(config('services.tap.secret_key'))
            ->get("https://api.tap.company/v2/charges/{$tapId}")
            ->throw()
            ->json();

        $order = Order::where('tap_charge_id', $tapId)->firstOrFail();

        if ($charge['status'] === 'CAPTURED') {
            return redirect()->route('orders.confirmation', $order);
        }

        return redirect()->route('checkout')
            ->withErrors(['payment' => 'Payment was not completed. Please try again.']);
    }
}
```

## Refund

```php
public function refund(Order $order, int $amountFils, string $reason): void
{
    $amountBhd = number_format($amountFils / 1000, 3, '.', '');

    $response = Http::withToken(config('services.tap.secret_key'))
        ->post('https://api.tap.company/v2/refunds', [
            'charge_id'  => $order->tap_charge_id,
            'amount'     => $amountBhd,
            'currency'   => 'BHD',
            'reason'     => $reason,
            'description'=> "Refund for order #{$order->reference}",
        ])
        ->throw()
        ->json();

    $order->refunds()->create([
        'tap_refund_id' => $response['id'],
        'amount_fils'   => $amountFils,
        'reason'        => $reason,
        'tap_response'  => $response,
    ]);

    event(new OrderRefunded($order, $amountFils));
}
```

## Environment config

```env
# .env — never commit production keys
TAP_SECRET_KEY=sk_test_xxxxxxxxxxxx
TAP_PUBLIC_KEY=pk_test_xxxxxxxxxxxx
TAP_WEBHOOK_SECRET=your_webhook_secret_here
```
