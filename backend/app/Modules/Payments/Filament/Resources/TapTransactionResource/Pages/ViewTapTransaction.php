<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\TapTransactionResource\Pages;

use App\Modules\Payments\Filament\Resources\TapTransactionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTapTransaction extends ViewRecord
{
    protected static string $resource = TapTransactionResource::class;
}
