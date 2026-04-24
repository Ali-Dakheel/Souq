<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $coupon_id
 * @property string $itemable_type
 * @property int $itemable_id
 */
class CouponApplicableItem extends Model
{
    public $timestamps = false;

    protected $table = 'coupon_applicable_items';

    protected $fillable = [
        'coupon_id',
        'itemable_type',
        'itemable_id',
    ];

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
