<?php

declare(strict_types=1);

namespace App\Modules\Customers\Filament\Resources\CustomerResource\Pages;

use App\Modules\Customers\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}
