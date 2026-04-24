<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Catalog\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int|null $order_item_id
 * @property int|null $variant_id
 * @property string|null $name_en
 * @property string|null $name_ar
 * @property string|null $sku
 * @property int $quantity
 * @property int $unit_price_fils
 * @property int $vat_rate
 * @property int $vat_fils
 * @property int $total_fils
 */
class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'order_item_id',
        'variant_id',
        'name_en',
        'name_ar',
        'sku',
        'quantity',
        'unit_price_fils',
        'vat_rate',
        'vat_fils',
        'total_fils',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_fils' => 'integer',
        'vat_rate' => 'integer', // 10 = 10% VAT; integer percentage, not decimal
        'vat_fils' => 'integer',
        'total_fils' => 'integer',
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }
}
