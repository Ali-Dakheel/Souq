<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use Carbon\Carbon;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $tap_transaction_id
 * @property string|null $tap_refund_id
 * @property int $refund_amount_fils
 * @property string $refund_reason
 * @property string|null $refund_notes
 * @property string|null $customer_notes
 * @property string|null $admin_notes
 * @property string $status
 * @property array<string, mixed>|null $tap_response
 * @property int|null $processed_by_user_id
 * @property int|null $requested_by_user_id
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Order $order
 * @property TapTransaction $tapTransaction
 */
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected static function newFactory(): RefundFactory
    {
        return RefundFactory::new();
    }

    protected $fillable = [
        'order_id',
        'tap_transaction_id',
        'tap_refund_id',
        'refund_amount_fils',
        'refund_reason',
        'refund_notes',
        'customer_notes',
        'admin_notes',
        'status',
        'tap_response',
        'processed_by_user_id',
        'requested_by_user_id',
        'processed_at',
    ];

    protected $casts = [
        'refund_amount_fils' => 'integer',
        'tap_response' => 'array',
        'processed_at' => 'datetime',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<TapTransaction, $this> */
    public function tapTransaction(): BelongsTo
    {
        return $this->belongsTo(TapTransaction::class, 'tap_transaction_id');
    }

    /** @return BelongsTo<User, $this> */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'processing', 'completed'], true);
    }
}
