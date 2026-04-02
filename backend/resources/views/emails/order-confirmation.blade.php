<x-mail::message>
# {{ __('emails.order_confirmation.greeting') }}

{{ __('emails.order_confirmation.intro') }}

**{{ __('emails.order_confirmation.order_number') }}:** {{ $order->order_number }}

<x-mail::table>
| {{ __('emails.order_confirmation.items') }} | |
| --- | --- |
@foreach($order->items as $item)
| {{ $item->product_name['en'] ?? $item->product_name['ar'] ?? 'Item' }} x{{ $item->quantity }} | {{ number_format($item->price_fils_per_unit / 1000, 3) }} BHD |
@endforeach
</x-mail::table>

**{{ __('emails.order_confirmation.subtotal') }}:** {{ number_format($order->subtotal_fils / 1000, 3) }} BHD

**{{ __('emails.order_confirmation.vat') }}:** {{ number_format($order->vat_fils / 1000, 3) }} BHD

**{{ __('emails.order_confirmation.total') }}:** {{ number_format($order->total_fils / 1000, 3) }} BHD

</x-mail::message>
