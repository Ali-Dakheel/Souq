<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\OrderResource\Pages;

use App\Modules\Orders\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
