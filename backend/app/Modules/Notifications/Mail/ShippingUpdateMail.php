<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Mail;

use App\Modules\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShippingUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order)
    {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        app()->setLocale($this->order->locale ?? 'ar');

        return new Envelope(
            to: $this->order->user?->email ?? $this->order->guest_email,
            subject: __('emails.shipping_update.subject', ['number' => $this->order->order_number]),
        );
    }

    public function content(): Content
    {
        app()->setLocale($this->order->locale ?? 'ar');

        return new Content(
            markdown: 'emails.shipping-update',
            with: ['order' => $this->order],
        );
    }

    public function tags(): array
    {
        return ['shipping-update'];
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }
}
