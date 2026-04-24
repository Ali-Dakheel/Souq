<?php

declare(strict_types=1);

namespace App\Modules\Returns\Models;

use App\Modules\Orders\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $order_id
 * @property int $user_id
 * @property string $request_number
 * @property string $status
 * @property string $reason
 * @property string|null $notes
 * @property string|null $admin_notes
 * @property string|null $resolution
 * @property int|null $resolution_amount_fils
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ReturnRequest extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'request_number',
        'status',
        'reason',
        'notes',
        'admin_notes',
        'resolution',
        'resolution_amount_fils',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<ReturnRequestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }
}
