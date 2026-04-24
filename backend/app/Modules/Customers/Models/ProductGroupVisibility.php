<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int $customer_group_id
 */
class ProductGroupVisibility extends Model
{
    protected $table = 'product_group_visibility';

    public $timestamps = false;

    protected $fillable = ['product_id', 'customer_group_id'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<CustomerGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }
}
