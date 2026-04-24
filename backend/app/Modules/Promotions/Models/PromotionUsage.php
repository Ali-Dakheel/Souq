<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $promotion_rule_id
 * @property int|null $user_id
 * @property int|null $order_id
 * @property Carbon|null $used_at
 */
class PromotionUsage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promotion_rule_id',
        'user_id',
        'order_id',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    /** @return BelongsTo<PromotionRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(PromotionRule::class, 'promotion_rule_id');
    }
}
