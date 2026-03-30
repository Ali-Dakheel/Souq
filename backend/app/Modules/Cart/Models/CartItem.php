<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Modules\Catalog\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'variant_id',
        'quantity',
        'price_fils_snapshot',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_fils_snapshot' => 'integer',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /**
     * Current effective price of the variant.
     * Returns null when variant has been deleted (variant_id is null).
     */
    public function getCurrentPriceFilsAttribute(): ?int
    {
        if (! $this->relationLoaded('variant') || $this->variant === null) {
            return null;
        }

        return $this->variant->effective_price_fils;
    }

    /**
     * True when current price differs from the snapshot taken at add-to-cart time.
     */
    public function getPriceChangedAttribute(): bool
    {
        $current = $this->current_price_fils;

        return $current !== null && $current !== $this->price_fils_snapshot;
    }

    public function getLineTotalFilsAttribute(): int
    {
        return $this->price_fils_snapshot * $this->quantity;
    }
}
