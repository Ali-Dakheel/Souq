<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $promotion_rule_id
 * @property string $type
 * @property array<string, mixed> $value
 */
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

    /** @return BelongsTo<PromotionRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(PromotionRule::class, 'promotion_rule_id');
    }
}
