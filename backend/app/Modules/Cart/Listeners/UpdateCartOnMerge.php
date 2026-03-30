<?php

declare(strict_types=1);

namespace App\Modules\Cart\Listeners;

use App\Modules\Cart\Events\CartMerged;

/**
 * Placeholder listener for post-merge side effects.
 * Extend this when cart sync WebSocket broadcasts are added (Phase 3).
 */
class UpdateCartOnMerge
{
    public function handle(CartMerged $event): void
    {
        // Phase 3: broadcast cart updated event to other browser tabs
    }
}
