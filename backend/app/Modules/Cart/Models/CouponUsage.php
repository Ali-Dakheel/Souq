<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $coupon_id
 * @property int|null $order_id
 * @property int|null $user_id
 * @property int $discount_amount_fils
 * @property Carbon|null $used_at
 */
class CouponUsage extends Model
{
    public $timestamps = false;

    protected $table = 'coupon_usage';

    protected $fillable = [
        'coupon_id',
        'order_id',
        'user_id',
        'discount_amount_fils',
        'used_at',
    ];

    protected $casts = [
        'discount_amount_fils' => 'integer',
        'used_at' => 'datetime',
    ];

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
