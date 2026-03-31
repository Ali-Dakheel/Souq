<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('sku')
                ->required()
                ->maxLength(255),
            TextInput::make('price_fils')
                ->label('Price (fils, leave blank to inherit product price)')
                ->numeric()
                ->nullable(),
            KeyValue::make('attributes')
                ->label('Attributes (key: value pairs)'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku'),
                TextColumn::make('price_fils')
                    ->label('Price (BHD)')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1000, 3).' BHD' : 'Inherited'),
                TextColumn::make('inventory.quantity_available')
                    ->label('Stock')
                    ->fallback('—'),
            ])
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }
}
