<?php

declare(strict_types=1);

namespace App\Modules\Customers\Filament\Resources\CustomerGroupResource\Pages;

use App\Modules\Customers\Filament\Resources\CustomerGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerGroups extends ListRecords
{
    protected static string $resource = CustomerGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
