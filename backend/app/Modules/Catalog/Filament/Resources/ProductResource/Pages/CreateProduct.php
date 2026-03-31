<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ProductResource\Pages;

use App\Modules\Catalog\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
}
