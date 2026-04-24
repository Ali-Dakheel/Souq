<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Modules\Orders\Models\Order;
use Carbon\Carbon;
use Database\Factories\TapTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $order_id
 * @property int $attempt_number
 * @property string $tap_charge_id
 * @property int $amount_fils
 * @property string $currency
 * @property string $status
 * @property string|null $payment_method
 * @property string|null $source_id
 * @property array<string, mixed>|null $tap_response
 * @property string|null $failure_reason
 * @property string|null $redirect_url
 * @property Carbon|null $webhook_received_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Order $order
 */
class TapTransaction extends Model
{
    /** @use HasFactory<TapTransactionFactory> */
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<Refund, $this> */
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
