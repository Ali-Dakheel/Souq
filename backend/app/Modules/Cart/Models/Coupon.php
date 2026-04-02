<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'minimum_order_amount_fils',
        'maximum_discount_fils',
        'max_uses_global',
        'max_uses_per_user',
        'starts_at',
        'expires_at',
        'is_active',
        'applicable_to',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'discount_value' => 'integer',
        'minimum_order_amount_fils' => 'integer',
        'maximum_discount_fils' => 'integer',
        'max_uses_global' => 'integer',
        'max_uses_per_user' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Polymorphic pivot — itemable_type = 'category' | 'product'
     */
    public function applicableItems(): HasMany
    {
        return $this->hasMany(CouponApplicableItem::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isNotYetActive(): bool
    {
        return $this->starts_at !== null && $this->starts_at->isFuture();
    }

    public function isExhausted(): bool
    {
        if ($this->max_uses_global === null) {
            return false;
        }

        return $this->usages()->count() >= $this->max_uses_global;
    }

    public function appliesToAll(): bool
    {
        return $this->applicable_to === 'all_products';
    }

    public function appliesToCategories(): bool
    {
        return $this->applicable_to === 'specific_categories';
    }

    public function appliesToProducts(): bool
    {
        return $this->applicable_to === 'specific_products';
    }
}
