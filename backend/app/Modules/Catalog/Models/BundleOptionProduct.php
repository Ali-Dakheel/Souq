<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleOptionProduct extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'bundle_option_id',
        'product_id',
        'default_quantity',
        'min_quantity',
        'max_quantity',
        'price_override_fils',
        'sort_order',
    ];

    protected $casts = [
        'default_quantity' => 'integer',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'price_override_fils' => 'integer',
        'sort_order' => 'integer',
    ];

    public function bundleOption(): BelongsTo
    {
        return $this->belongsTo(BundleOption::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
