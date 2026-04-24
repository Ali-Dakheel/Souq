<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $promotion_rule_id
 * @property string $type
 * @property string $operator
 * @property array<mixed> $value
 */
class PromotionCondition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promotion_rule_id',
        'type',
        'operator',
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
