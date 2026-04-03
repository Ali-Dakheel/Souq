<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
