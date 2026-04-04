<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Filament\Resources\PromotionRuleResource\Pages;

use App\Modules\Promotions\Filament\Resources\PromotionRuleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromotionRule extends EditRecord
{
    protected static string $resource = PromotionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Save Promotion Rule');
    }
}
