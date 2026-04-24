<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $loyalty_account_id
 * @property string $type
 * @property int $points
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $description_en
 * @property string|null $description_ar
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 */
class LoyaltyTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'loyalty_account_id',
        'type',
        'points',
        'reference_type',
        'reference_id',
        'description_en',
        'description_ar',
        'expires_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'reference_id' => 'integer',
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<LoyaltyAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }
}
