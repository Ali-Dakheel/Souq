<?php

declare(strict_types=1);

namespace App\Modules\Customers\Filament\Resources\CustomerGroupResource\Pages;

use App\Modules\Customers\Filament\Resources\CustomerGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerGroup extends CreateRecord
{
    protected static string $resource = CustomerGroupResource::class;
}
