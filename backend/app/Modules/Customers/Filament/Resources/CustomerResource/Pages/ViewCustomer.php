<?php

declare(strict_types=1);

namespace App\Modules\Customers\Filament\Resources\CustomerResource\Pages;

use App\Modules\Customers\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;
}
