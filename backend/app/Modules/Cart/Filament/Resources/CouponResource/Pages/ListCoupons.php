<?php

declare(strict_types=1);

namespace App\Modules\Cart\Filament\Resources\CouponResource\Pages;

use App\Modules\Cart\Filament\Resources\CouponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
