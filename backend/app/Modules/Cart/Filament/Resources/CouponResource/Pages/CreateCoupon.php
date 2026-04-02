<?php

declare(strict_types=1);

namespace App\Modules\Cart\Filament\Resources\CouponResource\Pages;

use App\Modules\Cart\Filament\Resources\CouponResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;
}
