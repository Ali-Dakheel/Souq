<?php

declare(strict_types=1);

namespace App\Modules\Customers\Events;

use App\Modules\Customers\Models\CustomerAddress;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AddressUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $changedFields
     */
    public function __construct(
        public readonly CustomerAddress $address,
        public readonly array $changedFields,
    ) {}
}
