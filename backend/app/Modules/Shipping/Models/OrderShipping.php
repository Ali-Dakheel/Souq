<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use App\Modules\Orders\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $shipping_method_id
 * @property string|null $carrier
 * @property string|null $method_name_en
 * @property string|null $method_name_ar
 * @property int $rate_fils
 * @property string|null $tracking_number
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<ShippingMethod, $this> */
    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }
}
