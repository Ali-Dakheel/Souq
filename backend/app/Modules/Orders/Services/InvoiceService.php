<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Events\InvoiceGenerated;
use App\Modules\Orders\Models\Invoice;
use App\Modules\Orders\Models\Order;
use App\Modules\Settings\Services\StoreSettingsService;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly StoreSettingsService $settingsService,
    ) {}

    /**
     * Generate invoice for an order. Idempotent — returns existing if already created.
     *
     * The entire body runs inside a single DB::transaction so that the sequence
     * increment (inner transaction via savepoint) and Invoice::create() are atomic.
     * The order row is locked with lockForUpdate() to prevent concurrent calls from
     * both passing the idempotency check and creating duplicate invoices.
     *
     * VAT assumption: prices stored in order_items.price_fils_per_unit are
     * VAT-EXCLUSIVE (the cart adds 10% VAT on top of the subtotal — see
     * CartService::calculateTotals). Therefore itemVat = price * quantity * 0.10
     * and itemTotal = price * quantity + itemVat. This matches how the order total
     * was originally calculated.
     */
    public function generateInvoice(Order $order): Invoice
    {
        return DB::transaction(function () use ($order): Invoice {
            // Lock the order row to prevent concurrent invoice generation
            Order::where('id', $order->id)->lockForUpdate()->first();

            // Re-check for existing invoice inside the transaction (idempotent)
            $existing = Invoice::where('order_id', $order->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            $order->loadMissing('items.variant.product');

            // Get next sequence — the inner DB::transaction participates via savepoint,
            // so if Invoice::create() fails below the sequence increment is rolled back too.
            $sequence = $this->settingsService->getNextInvoiceSequence();
            $invoiceNumber = $this->buildInvoiceNumber($sequence);

            // Snapshot legal details
            $crNumber = (string) ($this->settingsService->get('cr_number') ?? '');
            $vatNumber = (string) ($this->settingsService->get('vat_number') ?? '');

            if ($crNumber === '' || $vatNumber === '') {
                throw new \RuntimeException('Cannot generate invoice: CR number and VAT number must be configured in store settings.');
            }

            $companyNameEn = (string) ($this->settingsService->get('company_name_en') ?? '');
            $companyNameAr = (string) ($this->settingsService->get('company_name_ar') ?? '');
            $companyAddressEn = $this->settingsService->get('company_address_en');
            $companyAddressAr = $this->settingsService->get('company_address_ar');

            // Calculate totals from order items.
            // Prices are VAT-exclusive: VAT = price × qty × 10%, total = price × qty + VAT.
            // Invoice-level VAT is computed on the discounted subtotal (coupon reduces taxable base).
            $subtotalFils = 0;
            $lineItems = [];

            foreach ($order->items as $item) {
                $itemSubtotal = $item->price_fils_per_unit * $item->quantity;
                $itemVat = (int) round($itemSubtotal * 0.10);
                $itemTotal = $itemSubtotal + $itemVat;

                $subtotalFils += $itemSubtotal;

                // Resolve bilingual product name
                $productName = $item->product_name;
                $nameEn = '';
                $nameAr = '';
                if (is_array($productName)) {
                    $nameEn = (string) ($productName['en'] ?? '');
                    $nameAr = (string) ($productName['ar'] ?? '');
                } else {
                    $nameEn = (string) ($productName ?? '');
                    $nameAr = (string) ($productName ?? '');
                }

                $lineItems[] = [
                    'order_item_id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'name_en' => $nameEn,
                    'name_ar' => $nameAr,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price_fils' => $item->price_fils_per_unit,
                    'vat_rate' => 10, // 10 = 10% VAT; stored as integer per project convention
                    'vat_fils' => $itemVat,
                    'total_fils' => $itemTotal,
                ];
            }

            // Invoice-level totals: VAT applied to discounted subtotal, total derived from components
            $discountFils = $order->coupon_discount_fils ?? 0;
            $discountedSubtotal = $subtotalFils - $discountFils;
            $vatFils = (int) round($discountedSubtotal * 0.10);
            $totalFils = $discountedSubtotal + $vatFils;

            $invoice = Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'subtotal_fils' => $subtotalFils,
                'vat_fils' => $vatFils,
                'discount_fils' => $discountFils,
                'total_fils' => $totalFils,
                'cr_number' => $crNumber,
                'vat_number' => $vatNumber,
                'company_name_en' => $companyNameEn,
                'company_name_ar' => $companyNameAr,
                'company_address_en' => $companyAddressEn,
                'company_address_ar' => $companyAddressAr,
                'issued_at' => now(),
            ]);

            foreach ($lineItems as $lineItem) {
                $invoice->items()->create($lineItem);
            }

            InvoiceGenerated::dispatch($invoice, $order);

            return $invoice;
        });
    }

    /**
     * Build the invoice number from a sequence integer.
     * Format: INV-{YEAR}-{SEQUENCE padded to 6 digits}
     */
    private function buildInvoiceNumber(int $sequence): string
    {
        return 'INV-'.now()->format('Y').'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get invoice for an order. Returns null if not yet generated.
     */
    public function getInvoiceForOrder(Order $order): ?Invoice
    {
        return $order->invoice;
    }
}
