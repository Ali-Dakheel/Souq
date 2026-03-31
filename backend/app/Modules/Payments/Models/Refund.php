<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tapTransaction(): BelongsTo
    {
        return $this->belongsTo(TapTransaction::class, 'tap_transaction_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

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
