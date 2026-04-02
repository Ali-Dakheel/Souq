<x-mail::message>
# {{ __('emails.payment_receipt.greeting') }}

{{ __('emails.payment_receipt.intro') }}

**{{ __('emails.payment_receipt.charge_id') }}:** {{ $transaction->tap_charge_id }}

**{{ __('emails.payment_receipt.amount') }}:** {{ number_format($transaction->amount_fils / 1000, 3) }} BHD

</x-mail::message>
