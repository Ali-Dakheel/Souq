<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Orders\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $downloadable_link_id
 * @property int|null $order_item_id
 * @property int|null $order_id
 * @property int $download_count
 * @property Carbon|null $last_downloaded_at
 * @property Carbon|null $expires_at
 */
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

    /** @return BelongsTo<DownloadableLink, $this> */
    public function downloadableLink(): BelongsTo
    {
        return $this->belongsTo(DownloadableLink::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
