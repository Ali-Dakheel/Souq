<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Modules\Catalog\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantGroupPrice extends Model
{
    protected $fillable = ['variant_id', 'customer_group_id', 'price_fils', 'compare_at_price_fils'];

    protected $casts = [
        'price_fils' => 'integer',
        'compare_at_price_fils' => 'integer',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }
}
