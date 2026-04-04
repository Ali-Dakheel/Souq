<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Filament\Resources\PromotionRuleResource\Pages;

use App\Modules\Promotions\Filament\Resources\PromotionRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromotionRules extends ListRecords
{
    protected static string $resource = PromotionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
