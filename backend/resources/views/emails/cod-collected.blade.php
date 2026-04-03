<x-mail::message>
# {{ __('emails.cod_collected.greeting') }}

{{ __('emails.cod_collected.intro') }}

**{{ __('emails.cod_collected.order_number') }}:** {{ $order->order_number }}

**{{ __('emails.cod_collected.total') }}:** {{ number_format($order->total_fils / 1000, 3) }} BHD

**{{ __('emails.cod_collected.paid_at') }}:** {{ $order->paid_at?->format('Y-m-d H:i') }}

</x-mail::message>
