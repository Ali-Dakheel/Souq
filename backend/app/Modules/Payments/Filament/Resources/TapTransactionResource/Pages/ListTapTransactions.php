<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\TapTransactionResource\Pages;

use App\Modules\Payments\Filament\Resources\TapTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListTapTransactions extends ListRecords
{
    protected static string $resource = TapTransactionResource::class;
}
