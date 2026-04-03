<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderShipping extends Model
{
    protected $table = 'order_shipping';

    protected $fillable = [
        'order_id', 'shipping_method_id',
        'carrier', 'method_name_en', 'method_name_ar',
        'rate_fils', 'tracking_number',
    ];

    protected $casts = [
        'rate_fils' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }
}
