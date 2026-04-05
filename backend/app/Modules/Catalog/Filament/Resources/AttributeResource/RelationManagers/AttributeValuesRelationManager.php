<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\AttributeResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttributeValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $title = 'Values';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name.ar')
                ->label('Name (Arabic)')
                ->required(),
            TextInput::make('name.en')
                ->label('Name (English)')
                ->required(),
            TextInput::make('value_key')
                ->label('Value Key')
                ->required()
                ->maxLength(255),
            TextInput::make('display_value')
                ->label('Display Value')
                ->maxLength(255),
            TextInput::make('sort_order')
                ->label('Sort Order')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state['ar'] ?? '').' / '.($state['en'] ?? '') : (string) $state),
                TextColumn::make('value_key'),
                TextColumn::make('display_value'),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
