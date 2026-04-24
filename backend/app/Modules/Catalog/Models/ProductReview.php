<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $user_id
 * @property int $rating
 * @property array<string, string>|null $title
 * @property string|null $body
 * @property string|null $reviewer_name
 * @property string|null $reviewer_email
 * @property bool $is_verified_purchase
 * @property bool $is_approved
 * @property int $helpful_count
 * @property int $unhelpful_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ProductReview extends Model
{
    protected $table = 'product_reviews';

    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'title',
        'body',
        'reviewer_name',
        'reviewer_email',
        'is_verified_purchase',
        'is_approved',
    ];

    protected $casts = [
        'title' => 'array',
        'rating' => 'integer',
        'helpful_count' => 'integer',
        'unhelpful_count' => 'integer',
        'is_verified_purchase' => 'boolean',
        'is_approved' => 'boolean',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ProductReviewVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(ProductReviewVote::class);
    }

    /**
     * @param  Builder<ProductReview>  $query
     * @return Builder<ProductReview>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }
}
