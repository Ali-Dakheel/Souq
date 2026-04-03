<?php

declare(strict_types=1);

namespace App\Modules\Customers\Filament\Resources\CustomerGroupResource\Pages;

use App\Modules\Customers\Filament\Resources\CustomerGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerGroup extends EditRecord
{
    protected static string $resource = CustomerGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
