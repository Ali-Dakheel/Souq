<x-mail::message>
# {{ __('emails.invoice.greeting') }}

{{ __('emails.invoice.intro') }}

**{{ __('emails.invoice.invoice_number') }}:** {{ $invoice->invoice_number }}

**{{ __('emails.invoice.order_number') }}:** {{ $order->order_number }}

**{{ __('emails.invoice.issued_at') }}:** {{ $invoice->issued_at->format('Y-m-d') }}

**{{ __('emails.invoice.cr_number') }}:** {{ $invoice->cr_number }}

**{{ __('emails.invoice.vat_number') }}:** {{ $invoice->vat_number }}

---

## {{ __('emails.invoice.items') }}

@foreach ($invoice->items as $item)
- **{{ app()->getLocale() === 'ar' ? $item->name_ar : $item->name_en }}** ({{ $item->sku }}) × {{ $item->quantity }} — {{ number_format($item->unit_price_fils / 1000, 3) }} BHD + {{ __('emails.invoice.vat') }} = {{ number_format($item->total_fils / 1000, 3) }} BHD
@endforeach

---

**{{ __('emails.invoice.subtotal') }}:** {{ number_format($invoice->subtotal_fils / 1000, 3) }} BHD

**{{ __('emails.invoice.vat') }}:** {{ number_format($invoice->vat_fils / 1000, 3) }} BHD

@if ($invoice->discount_fils > 0)
**{{ __('emails.invoice.discount') }}:** -{{ number_format($invoice->discount_fils / 1000, 3) }} BHD
@endif

**{{ __('emails.invoice.total') }}:** {{ number_format($invoice->total_fils / 1000, 3) }} BHD

</x-mail::message>
