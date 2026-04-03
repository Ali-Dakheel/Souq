<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\InvoiceResource\Pages;

use App\Modules\Orders\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;
}
