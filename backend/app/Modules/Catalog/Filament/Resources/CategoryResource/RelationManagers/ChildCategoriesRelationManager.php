<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\CategoryResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChildCategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Sub-categories';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name.ar')->label('Name (Arabic)')->required(),
            TextInput::make('name.en')->label('Name (English)')->required(),
            TextInput::make('slug')->required(),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state['ar'] ?? '').' / '.($state['en'] ?? '') : (string) $state),
                TextColumn::make('slug'),
                TextColumn::make('sort_order'),
            ])
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }
}
