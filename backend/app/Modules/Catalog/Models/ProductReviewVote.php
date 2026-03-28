<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReviewVote extends Model
{
    protected $table = 'product_review_votes';

    public $timestamps = false;

    protected $fillable = [
        'product_review_id',
        'user_id',
        'vote_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'product_review_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
