<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\OrderResource\Pages;

use App\Modules\Orders\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;
}
