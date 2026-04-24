<?php

declare(strict_types=1);

namespace App\Modules\Customers\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerProfileUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $changedFields
     */
    public function __construct(
        public readonly User $user,
        public readonly array $changedFields,
    ) {}
}
