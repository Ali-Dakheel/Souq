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
    'cod_collected' => [
        'subject' => 'تم استلام الدفع (نقداً) - طلب #:number',
        'greeting' => 'تم استلام دفعتك النقدية!',
        'intro' => 'شكراً لك. تم استلام دفعتك النقدية وطلبك مكتمل الآن.',
        'order_number' => 'رقم الطلب',
        'total' => 'المبلغ المُستلم',
        'paid_at' => 'وقت الاستلام',
    ],
    'invoice' => [
        'subject' => 'فاتورة :number — طلب #:order',
        'greeting' => 'فاتورتك جاهزة.',
        'intro' => 'يرجى الاطلاع على تفاصيل فاتورتك أدناه.',
        'invoice_number' => 'رقم الفاتورة',
        'order_number' => 'رقم الطلب',
        'issued_at' => 'تاريخ الإصدار',
        'cr_number' => 'رقم السجل التجاري',
        'vat_number' => 'رقم تسجيل ضريبة القيمة المضافة',
        'items' => 'المنتجات',
        'item_name' => 'المنتج',
        'qty' => 'الكمية',
        'unit_price' => 'سعر الوحدة',
        'vat' => 'ضريبة القيمة المضافة (10%)',
        'line_total' => 'الإجمالي',
        'subtotal' => 'المجموع الفرعي',
        'discount' => 'الخصم',
        'total' => 'الإجمالي',
    ],
];
