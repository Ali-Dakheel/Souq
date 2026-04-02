<?php

declare(strict_types=1);

return [
    'order_confirmation' => [
        'subject' => 'تأكيد الطلب #:number',
        'greeting' => 'شكراً لطلبك!',
        'intro' => 'تم استلام طلبك وهو قيد المعالجة.',
        'order_number' => 'رقم الطلب',
        'items' => 'المنتجات',
        'subtotal' => 'المجموع الفرعي',
        'vat' => 'ضريبة القيمة المضافة (10%)',
        'total' => 'الإجمالي',
    ],
    'payment_receipt' => [
        'subject' => 'إيصال الدفع - طلب #:number',
        'greeting' => 'تم تأكيد دفعتك!',
        'intro' => 'تم استلام دفعتك بنجاح.',
        'charge_id' => 'رقم العملية',
        'amount' => 'المبلغ المدفوع',
    ],
    'shipping_update' => [
        'subject' => 'تم شحن طلبك #:number',
        'greeting' => 'طلبك في الطريق!',
        'intro' => 'تم شحن طلبك وهو في طريقه إليك.',
        'tracking' => 'رقم التتبع',
        'no_tracking' => 'سيتم توفير رقم التتبع قريباً.',
    ],
];
