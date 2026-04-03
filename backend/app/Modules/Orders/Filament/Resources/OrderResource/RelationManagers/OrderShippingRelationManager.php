<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderShippingRelationManager extends RelationManager
{
    protected static string $relationship = 'shipping';

    protected static ?string $title = 'Shipping Info';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('carrier')
                    ->label('Carrier')
                    ->searchable(),
                TextColumn::make('method_name_en')
                    ->label('Method (EN)'),
                TextColumn::make('rate_fils')
                    ->label('Rate (BHD)')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 3).' BHD'),
                TextColumn::make('tracking_number')
                    ->label('Tracking Number')
                    ->formatStateUsing(fn ($state) => $state ?? '—'),
            ])
            ->paginated(false);
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit($record): bool
    {
        return false;
    }

    public function canDelete($record): bool
    {
        return false;
    }
}
