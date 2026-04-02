<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Modules\Orders\Models\Order;
use Database\Factories\TapTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TapTransaction extends Model
{
    use HasFactory;

    protected static function newFactory(): TapTransactionFactory
    {
        return TapTransactionFactory::new();
    }

    protected $table = 'tap_transactions';

    protected $fillable = [
        'order_id',
        'attempt_number',
        'tap_charge_id',
        'amount_fils',
        'currency',
        'status',
        'payment_method',
        'source_id',
        'tap_response',
        'failure_reason',
        'redirect_url',
        'webhook_received_at',
    ];

    protected $casts = [
        'amount_fils' => 'integer',
        'attempt_number' => 'integer',
        'tap_response' => 'array',
        'webhook_received_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'tap_transaction_id');
    }

    public function isCaptured(): bool
    {
        return $this->status === 'captured';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isInitiated(): bool
    {
        return $this->status === 'initiated';
    }
}
