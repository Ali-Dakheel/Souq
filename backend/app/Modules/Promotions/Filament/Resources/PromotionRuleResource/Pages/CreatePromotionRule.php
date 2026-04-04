<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Filament\Resources\PromotionRuleResource\Pages;

use App\Modules\Promotions\Filament\Resources\PromotionRuleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotionRule extends CreateRecord
{
    protected static string $resource = PromotionRuleResource::class;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Create Promotion Rule');
    }
}
