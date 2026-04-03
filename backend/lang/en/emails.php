<?php

declare(strict_types=1);

return [
    'order_confirmation' => [
        'subject' => 'Order Confirmation #:number',
        'greeting' => 'Thank you for your order!',
        'intro' => 'Your order has been received and is being processed.',
        'order_number' => 'Order Number',
        'items' => 'Items',
        'subtotal' => 'Subtotal',
        'vat' => 'VAT (10%)',
        'total' => 'Total',
    ],
    'payment_receipt' => [
        'subject' => 'Payment Receipt - Order #:number',
        'greeting' => 'Your payment was confirmed!',
        'intro' => 'Your payment has been successfully received.',
        'charge_id' => 'Charge ID',
        'amount' => 'Amount Paid',
    ],
    'shipping_update' => [
        'subject' => 'Your Order #:number Has Shipped',
        'greeting' => 'Your order is on the way!',
        'intro' => 'Your order has been shipped and is on its way to you.',
        'tracking' => 'Tracking Number',
        'no_tracking' => 'Tracking information will be provided soon.',
    ],
    'cod_collected' => [
        'subject' => 'Payment Received (Cash) - Order #:number',
        'greeting' => 'Your cash payment has been received!',
        'intro' => 'Thank you. Your cash payment has been collected and your order is now complete.',
        'order_number' => 'Order Number',
        'total' => 'Amount Collected',
        'paid_at' => 'Collected At',
    ],
    'invoice' => [
        'subject' => 'Invoice :number — Order #:order',
        'greeting' => 'Your invoice is ready.',
        'intro' => 'Please find your invoice details below.',
        'invoice_number' => 'Invoice Number',
        'order_number' => 'Order Number',
        'issued_at' => 'Issued',
        'cr_number' => 'CR Number',
        'vat_number' => 'VAT Registration Number',
        'items' => 'Items',
        'item_name' => 'Item',
        'qty' => 'Qty',
        'unit_price' => 'Unit Price',
        'vat' => 'VAT (10%)',
        'line_total' => 'Total',
        'subtotal' => 'Subtotal',
        'discount' => 'Discount',
        'total' => 'Total',
    ],
];
