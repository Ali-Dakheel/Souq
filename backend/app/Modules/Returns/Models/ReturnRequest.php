<?php

declare(strict_types=1);

namespace App\Modules\Returns\Models;

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }
}
