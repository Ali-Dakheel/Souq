<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadableLinkPurchase extends Model
{
    protected $fillable = [
        'downloadable_link_id',
        'order_item_id',
        'order_id',
        'download_count',
        'last_downloaded_at',
        'expires_at',
    ];

    protected $casts = [
        'download_count' => 'integer',
        'last_downloaded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function downloadableLink(): BelongsTo
    {
        return $this->belongsTo(DownloadableLink::class);
    }

    public function order(): BelongsTo
    {
        $orderModel = 'App\Modules\Orders\Models\Order';

        return $this->belongsTo($orderModel, 'order_id');
    }
}
