<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Models\User;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'guest_email',
        'order_status',
        'subtotal_fils',
        'coupon_discount_fils',
        'coupon_code',
        'vat_fils',
        'delivery_fee_fils',
        'total_fils',
        'payment_method',
        'shipping_address_id',
        'shipping_address_snapshot',
        'billing_address_id',
        'billing_address_snapshot',
        'delivery_zone_id',
        'delivery_method_id',
        'notes',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'shipping_address_snapshot' => 'array',
        'billing_address_snapshot' => 'array',
        'subtotal_fils'             => 'integer',
        'coupon_discount_fils'      => 'integer',
        'vat_fils'                  => 'integer',
        'delivery_fee_fils'         => 'integer',
        'total_fils'                => 'integer',
        'paid_at'                   => 'datetime',
        'cancelled_at'              => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'shipping_address_id');
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'billing_address_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TapTransaction::class)->orderByDesc('attempt_number');
    }

    public function isCancellable(): bool
    {
        return in_array($this->order_status, ['pending', 'initiated'], true);
    }
}
