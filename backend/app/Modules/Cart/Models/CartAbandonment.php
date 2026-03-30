<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartAbandonment extends Model
{
    protected $fillable = [
        'cart_id',
        'user_id',
        'session_id',
        'cart_total_fils',
        'item_count',
        'abandoned_at',
        'recovered_at',
    ];

    protected $casts = [
        'cart_total_fils' => 'integer',
        'item_count' => 'integer',
        'abandoned_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markRecovered(): void
    {
        $this->update(['recovered_at' => now()]);
    }
}
