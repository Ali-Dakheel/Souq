<?php

declare(strict_types=1);

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

    public function __construct(
        public readonly Order $order,
        public readonly TapTransaction $transaction,
    ) {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        app()->setLocale($this->order->locale ?? 'ar');

        return new Envelope(
            to: $this->order->user->email,
            subject: __('emails.payment_receipt.subject', ['number' => $this->order->order_number]),
        );
    }

    public function content(): Content
    {
        app()->setLocale($this->order->locale ?? 'ar');

        return new Content(
            markdown: 'emails.payment-receipt',
            with: [
                'order' => $this->order,
                'transaction' => $this->transaction,
            ],
        );
    }

    public function tags(): array
    {
        return ['payment-receipt'];
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }
}
