<x-mail::message>
# {{ __('emails.shipping_update.greeting') }}

{{ __('emails.shipping_update.intro') }}

@if($order->tracking_number)
**{{ __('emails.shipping_update.tracking') }}:** {{ $order->tracking_number }}
@else
{{ __('emails.shipping_update.no_tracking') }}
@endif

</x-mail::message>
