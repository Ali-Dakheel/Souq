<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ProductResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // TODO: Populate in Task 8
            ])
            ->filters([
                // TODO: Populate in Task 8
            ])
            ->headerActions([
                // TODO: Populate in Task 8
            ])
            ->actions([
                // TODO: Populate in Task 8
            ])
            ->bulkActions([
                // TODO: Populate in Task 8
            ]);
    }
}
