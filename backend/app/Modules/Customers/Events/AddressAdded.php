<?php

declare(strict_types=1);

namespace App\Modules\Customers\Events;

use App\Modules\Customers\Models\CustomerAddress;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AddressAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CustomerAddress $address,
    ) {}
}
