<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ProductResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Reviewer'),
                TextColumn::make('rating'),
                TextColumn::make('body')->limit(80),
                TextColumn::make('is_approved')->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->actions([
                Action::make('approve')
                    ->visible(fn ($record): bool => ! $record->is_approved)
                    ->action(fn ($record) => $record->update(['is_approved' => true])),
                Action::make('hide')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record->is_approved)
                    ->action(fn ($record) => $record->update(['is_approved' => false])),
            ]);
    }
}
