<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Models\User;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Payments\Models\TapTransaction;
use App\Modules\Returns\Models\ReturnRequest;
use App\Modules\Shipping\Models\OrderShipping;
use Carbon\Carbon;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $order_number
 * @property int|null $user_id
 * @property string|null $guest_email
 * @property string $order_status
 * @property int $subtotal_fils
 * @property int $coupon_discount_fils
 * @property string|null $coupon_code
 * @property int $vat_fils
 * @property int $delivery_fee_fils
 * @property int $total_fils
 * @property string|null $payment_method
 * @property int|null $shipping_address_id
 * @property array<string, mixed>|null $shipping_address_snapshot
 * @property int|null $billing_address_id
 * @property array<string, mixed>|null $billing_address_snapshot
 * @property string|null $notes
 * @property string|null $locale
 * @property string|null $tracking_number
 * @property Carbon|null $fulfilled_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User|null $user
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

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
        'notes',
        'locale',
        'tracking_number',
        'fulfilled_at',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'shipping_address_snapshot' => 'array',
        'billing_address_snapshot' => 'array',
        'subtotal_fils' => 'integer',
        'coupon_discount_fils' => 'integer',
        'vat_fils' => 'integer',
        'delivery_fee_fils' => 'integer',
        'total_fils' => 'integer',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    /** @return BelongsTo<CustomerAddress, $this> */
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'shipping_address_id');
    }

    /** @return BelongsTo<CustomerAddress, $this> */
    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'billing_address_id');
    }

    /** @return HasMany<TapTransaction, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(TapTransaction::class)->orderByDesc('attempt_number');
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @return HasOne<OrderShipping, $this> */
    public function shipping(): HasOne
    {
        return $this->hasOne(OrderShipping::class);
    }

    public function isCod(): bool
    {
        return $this->payment_method === 'cod';
    }

    public function isCancellable(): bool
    {
        return in_array($this->order_status, ['pending', 'initiated', 'pending_collection'], true);
    }

    /** @return HasMany<ReturnRequest, $this> */
    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }
}
