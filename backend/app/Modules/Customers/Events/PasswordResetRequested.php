<?php

declare(strict_types=1);

namespace App\Modules\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PasswordResetRequested
{
    use Dispatchable;

    public function __construct(
        public readonly string $email,
        public readonly string $token,
    ) {}
}
