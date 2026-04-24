<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Modules\Catalog\Models\Variant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cart_id
 * @property int|null $variant_id
 * @property int $quantity
 * @property int $price_fils_snapshot
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Variant|null $variant
 * @property Cart $cart
 * @property-read int|null $current_price_fils
 * @property-read bool $price_changed
 * @property-read int $line_total_fils
 */
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

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<Variant, $this> */
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
