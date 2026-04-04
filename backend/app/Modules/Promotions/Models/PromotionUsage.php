<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PromotionRule::class, 'promotion_rule_id');
    }
}
