<x-mail::message>
# {{ __('emails.shipment_created.greeting') }}

{{ __('emails.shipment_created.intro', ['order' => $order->order_number]) }}

**{{ __('emails.shipment_created.shipment_number') }}:** {{ $shipment->shipment_number }}

@if($shipment->carrier)
**{{ __('emails.shipment_created.carrier') }}:** {{ $shipment->carrier }}
@endif

@if($shipment->tracking_number)
**{{ __('emails.shipment_created.tracking') }}:** {{ $shipment->tracking_number }}
@else
{{ __('emails.shipment_created.no_tracking') }}
@endif

</x-mail::message>
