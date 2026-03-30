<?php

declare(strict_types=1);

namespace App\Modules\Cart\Events;

use App\Models\User;
use App\Modules\Cart\Models\Cart;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartMerged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Cart $userCart,
        public readonly string $guestSessionId,
        public readonly int $itemsAdded,
        public readonly int $itemsUpdated,
    ) {}
}
