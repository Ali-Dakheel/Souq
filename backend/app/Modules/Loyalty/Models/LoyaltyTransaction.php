<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }
}
