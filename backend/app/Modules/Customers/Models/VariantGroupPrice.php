<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Modules\Catalog\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $variant_id
 * @property int $customer_group_id
 * @property int $price_fils
 * @property int|null $compare_at_price_fils
 */
class VariantGroupPrice extends Model
{
    protected $fillable = ['variant_id', 'customer_group_id', 'price_fils', 'compare_at_price_fils'];

    protected $casts = [
        'price_fils' => 'integer',
        'compare_at_price_fils' => 'integer',
    ];

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /** @return BelongsTo<CustomerGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }
}
