<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promotion_rule_id',
        'type',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PromotionRule::class, 'promotion_rule_id');
    }
}
