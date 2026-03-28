<?php

declare(strict_types=1);

namespace App\Modules\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;

class AddressDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $addressId,
        public readonly string $addressType,
    ) {}
}
