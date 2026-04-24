<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shipment_id
 * @property int $order_item_id
 * @property int $quantity_shipped
 */
class ShipmentItem extends Model
{
    protected $table = 'shipment_items';

    public $timestamps = false;

    protected $fillable = [
        'shipment_id',
        'order_item_id',
        'quantity_shipped',
    ];

    protected $casts = [
        'quantity_shipped' => 'integer',
    ];

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
