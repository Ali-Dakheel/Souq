<?php

declare(strict_types=1);

namespace App\Modules\Returns\Events;

use App\Modules\Returns\Models\ReturnRequest;
use Illuminate\Foundation\Events\Dispatchable;

class ReturnCompleted
{
    use Dispatchable;

    public function __construct(public readonly ReturnRequest $returnRequest) {}
}
