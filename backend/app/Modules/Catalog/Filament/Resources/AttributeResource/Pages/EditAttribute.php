<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\AttributeResource\Pages;

use App\Modules\Catalog\Filament\Resources\AttributeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttribute extends EditRecord
{
    protected static string $resource = AttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
