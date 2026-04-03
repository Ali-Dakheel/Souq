<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Filament\Resources\ShippingZoneResource\Pages;

use App\Modules\Shipping\Filament\Resources\ShippingZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingZone extends CreateRecord
{
    protected static string $resource = ShippingZoneResource::class;
}
