<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\AttributeResource\Pages;

use App\Modules\Catalog\Filament\Resources\AttributeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttribute extends CreateRecord
{
    protected static string $resource = AttributeResource::class;
}
